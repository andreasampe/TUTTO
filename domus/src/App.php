<?php
// Avvio dell'app: configurazione, autoload, sessione, risposte JSON ed errori.
declare(strict_types=1);

namespace Domus;

final class HttpError extends \RuntimeException
{
    /** @param array<string,string> $fields errori per singolo campo del modulo */
    public function __construct(public readonly int $status, string $message, public readonly array $fields = [])
    {
        parent::__construct($message);
    }
}

final class App
{
    /** @var array<string,mixed> */
    private static array $config = [];

    public static function boot(): void
    {
        spl_autoload_register(function (string $class): void {
            if (str_starts_with($class, 'Domus\\')) {
                $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 6)) . '.php';
                if (is_file($file)) {
                    require $file;
                }
            }
        });
        // HttpError è definita qui sopra: non serve un file a parte.
        self::$config = require dirname(__DIR__) . '/config.php';
        date_default_timezone_set(self::config('timezone'));
    }

    /** Data di oggi (più $days giorni) nel fuso dello studio: le query la ricevono come parametro,
     *  perché date('now') di SQLite è in UTC e 'localtime' segue il fuso del server, non config.php. */
    public static function today(int $days = 0): string
    {
        return date('Y-m-d', strtotime("$days days"));
    }

    /** Data e ora attuali nel fuso dello studio, nel formato di created_at / updated_at. */
    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public static function config(string $key): mixed
    {
        return self::$config[$key] ?? null;
    }

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $lifetime = (int) self::config('session_lifetime');
        session_name('domus_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => self::basePath() . '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        ]);
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.use_strict_mode', '1');
        session_start();
        if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > $lifetime) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['last_seen'] = time();
    }

    // Cartella in cui è installata l'app, vista dal browser (es. "/domus").
    public static function basePath(): string
    {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        return rtrim($dir, '/');
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** @return array<string,mixed> */
    public static function input(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new HttpError(400, 'Dati inviati non validi');
        }
        return $data;
    }

    public static function handleError(\Throwable $e): never
    {
        if ($e instanceof HttpError) {
            $body = ['error' => $e->getMessage()];
            if ($e->fields) {
                $body['fields'] = $e->fields;
            }
            self::json($body, $e->status);
        }
        error_log('[DOMUS] ' . $e);
        self::json(['error' => 'Errore interno. Dettagli nel log del server.'], 500);
    }
}
