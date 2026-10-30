<?php
// Accesso con email e password. Il primo avvio crea l'account dell'amministratore.
// Protezioni: password con hash, token CSRF, blocco dopo troppi tentativi falliti.
declare(strict_types=1);

namespace Domus;

final class Auth
{
    private const MAX_ATTEMPTS = 8;
    private const WINDOW = 900; // 15 minuti

    public static function needsSetup(): bool
    {
        return (int) Db::value('SELECT COUNT(*) FROM users') === 0;
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        $id = $_SESSION['user_id'] ?? null;
        if (!$id) {
            return null;
        }
        return Db::one('SELECT id, name, email, role FROM users WHERE id = ? AND active = 1', [$id]);
    }

    /** @return array<string,mixed> */
    public static function requireUser(): array
    {
        $user = self::user();
        if (!$user) {
            throw new HttpError(401, 'Accesso richiesto');
        }
        return $user;
    }

    /** @return array<string,mixed> */
    public static function requireAdmin(): array
    {
        $user = self::requireUser();
        if ($user['role'] !== 'admin') {
            throw new HttpError(403, 'Questa funzione è riservata all\'amministratore');
        }
        return $user;
    }

    public static function isAdmin(?array $user): bool
    {
        return ($user['role'] ?? '') === 'admin';
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    // Ogni richiesta che modifica dati deve portare il token nell'intestazione X-CSRF-Token.
    public static function checkCsrf(): void
    {
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
            throw new HttpError(403, 'Sessione scaduta: ricarica la pagina');
        }
    }

    /** @return array<string,mixed> */
    public static function setup(array $in): array
    {
        if (!self::needsSetup()) {
            throw new HttpError(409, 'L\'amministratore esiste già');
        }
        $data = self::validateAccount($in, requirePassword: true);
        $id = Db::insert('users', [
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role' => 'admin',
        ]);
        if (!empty($in['studio_name'])) {
            Settings::set('studio_name', Validate::text($in['studio_name'], 120));
        }
        self::loginAs($id);
        return self::user();
    }

    /** @return array<string,mixed> */
    public static function login(array $in): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        Db::run('DELETE FROM login_attempts WHERE attempt_at < ?', [time() - self::WINDOW]);
        $recent = (int) Db::value('SELECT COUNT(*) FROM login_attempts WHERE ip = ?', [$ip]);
        if ($recent >= self::MAX_ATTEMPTS) {
            throw new HttpError(429, 'Troppi tentativi. Riprova tra qualche minuto.');
        }

        $email = trim((string) ($in['email'] ?? ''));
        $password = (string) ($in['password'] ?? '');
        $row = Db::one('SELECT id, password_hash FROM users WHERE email = ? AND active = 1', [$email]);
        if (!$row || !password_verify($password, $row['password_hash'])) {
            Db::insert('login_attempts', ['ip' => $ip, 'attempt_at' => time()]);
            throw new HttpError(401, 'Email o password non corretti');
        }
        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            Db::update('users', (int) $row['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        }
        Db::run('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
        self::loginAs((int) $row['id']);
        return self::user();
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    /** @return array<string,mixed> */
    public static function updateProfile(array $user, array $in): array
    {
        $changes = [];
        if (isset($in['name'])) {
            $changes['name'] = Validate::required($in['name'], 'name', 80);
        }
        if (!empty($in['new_password'])) {
            $row = Db::one('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
            if (!password_verify((string) ($in['current_password'] ?? ''), $row['password_hash'])) {
                throw new HttpError(422, 'La password attuale non è corretta', ['current_password' => 'Non corretta']);
            }
            $changes['password_hash'] = password_hash(self::checkPassword((string) $in['new_password'], 'new_password'), PASSWORD_DEFAULT);
        }
        Db::update('users', (int) $user['id'], $changes);
        return self::user();
    }

    private static function loginAs(int $id): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    /** @return array{name:string,email:string,password:string} */
    private static function validateAccount(array $in, bool $requirePassword): array
    {
        $name = Validate::required($in['name'] ?? '', 'name', 80);
        $email = Validate::email($in['email'] ?? '', 'email', required: true);
        $password = $requirePassword ? self::checkPassword((string) ($in['password'] ?? ''), 'password') : '';
        return ['name' => $name, 'email' => $email, 'password' => $password];
    }

    private static function checkPassword(string $password, string $field): string
    {
        if (mb_strlen($password) < 10) {
            throw new HttpError(422, 'La password deve avere almeno 10 caratteri', [$field => 'Almeno 10 caratteri']);
        }
        return $password;
    }
}
