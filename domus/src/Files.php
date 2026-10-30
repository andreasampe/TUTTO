<?php
// Documenti dei progetti: tavole, foto dei sopralluoghi, PDF, pratiche firmate…
// I file stanno in domus-data/files/<progetto>/ con un nome casuale e si scaricano
// solo dopo l'accesso, passando da qui (mai direttamente dal browser).
declare(strict_types=1);

namespace Domus;

final class Files
{
    public const CATEGORIES = ['tavola', 'foto', 'documento', 'pratica', 'contratto', 'altro'];

    // Estensioni ammesse → tipo. Un elenco chiuso evita di caricare script eseguibili.
    private const TYPES = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        'gif' => 'image/gif', 'heic' => 'image/heic', 'tif' => 'image/tiff', 'tiff' => 'image/tiff',
        'dwg' => 'application/acad', 'dxf' => 'application/dxf', 'ifc' => 'application/x-step',
        'skp' => 'application/vnd.sketchup.skp', 'rvt' => 'application/octet-stream', '3dm' => 'application/octet-stream',
        'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odt' => 'application/vnd.oasis.opendocument.text', 'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'txt' => 'text/plain', 'csv' => 'text/csv', 'rtf' => 'application/rtf',
        'zip' => 'application/zip', '7z' => 'application/x-7z-compressed',
        'p7m' => 'application/pkcs7-mime', 'eml' => 'message/rfc822', 'msg' => 'application/vnd.ms-outlook',
        'mp4' => 'video/mp4', 'mov' => 'video/quicktime',
    ];

    // Immagini che il browser sa mostrare in anteprima.
    private const PREVIEW = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** @return list<array<string,mixed>> */
    public static function forProject(int $projectId): array
    {
        $rows = Db::all(
            'SELECT f.id, f.project_id, f.name, f.mime, f.size, f.category, f.created_at, u.name AS user_name
             FROM files f LEFT JOIN users u ON u.id = f.user_id WHERE f.project_id = ? ORDER BY f.created_at DESC, f.id DESC',
            [$projectId]
        );
        foreach ($rows as &$r) {
            $r['preview'] = in_array($r['mime'], self::PREVIEW, true);
        }
        return $rows;
    }

    private static function dir(int $projectId): string
    {
        $dir = App::config('data_dir') . '/files/' . $projectId;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            throw new HttpError(500, 'Impossibile creare la cartella dei documenti');
        }
        return $dir;
    }

    /** @return list<int> id dei file caricati */
    public static function upload(array $user, int $projectId, array $files, string $category): array
    {
        Projects::exists($projectId);
        $category = Validate::oneOf($category, self::CATEGORIES, 'category', 'documento');
        $list = self::normalize($files);
        if (!$list) {
            throw new HttpError(422, 'Nessun file ricevuto');
        }
        $ids = [];
        foreach ($list as $f) {
            if ($f['error'] !== UPLOAD_ERR_OK) {
                throw new HttpError(422, self::uploadError($f['error'], $f['name']));
            }
            $name = self::cleanName($f['name']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!isset(self::TYPES[$ext])) {
                throw new HttpError(422, "Tipo di file non ammesso: .$ext ($name)");
            }
            $stored = bin2hex(random_bytes(16)) . '.' . $ext;
            if (!move_uploaded_file($f['tmp_name'], self::dir($projectId) . '/' . $stored)) {
                throw new HttpError(500, "Impossibile salvare $name");
            }
            $ids[] = Db::insert('files', [
                'project_id' => $projectId,
                'name' => $name,
                'stored' => $stored,
                'mime' => self::TYPES[$ext],
                'size' => (int) $f['size'],
                'category' => $category,
                'user_id' => $user['id'],
            ]);
        }
        return $ids;
    }

    public static function update(int $id, array $in): void
    {
        $f = self::find($id);
        $data = [];
        if (array_key_exists('category', $in)) {
            $data['category'] = Validate::oneOf($in['category'], self::CATEGORIES, 'category');
        }
        if (array_key_exists('name', $in)) {
            $name = self::cleanName((string) $in['name']);
            // L'estensione non si cambia: resterebbe incoerente con il contenuto.
            $oldExt = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== $oldExt) {
                $name .= '.' . $oldExt;
            }
            $data['name'] = $name;
        }
        Db::update('files', $id, $data);
    }

    // Invia il file al browser. "inline" per le anteprime delle immagini.
    public static function send(int $id, bool $inline): never
    {
        $f = self::find($id);
        $path = App::config('data_dir') . '/files/' . $f['project_id'] . '/' . $f['stored'];
        if (!is_file($path)) {
            throw new HttpError(404, 'File mancante sul disco');
        }
        $canInline = $inline && (in_array($f['mime'], self::PREVIEW, true) || $f['mime'] === 'application/pdf');
        header('Content-Type: ' . $f['mime']);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($canInline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($f['name']));
        header('Cache-Control: private, max-age=3600');
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
        readfile($path);
        exit;
    }

    public static function delete(int $id): void
    {
        $f = self::find($id);
        @unlink(App::config('data_dir') . '/files/' . $f['project_id'] . '/' . $f['stored']);
        Db::run('DELETE FROM files WHERE id = ?', [$id]);
    }

    // Quando si elimina un progetto, via anche la sua cartella.
    public static function deleteProjectFolder(int $projectId): void
    {
        $dir = App::config('data_dir') . '/files/' . $projectId;
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }

    /** @return array<string,mixed> */
    private static function find(int $id): array
    {
        $f = Db::one('SELECT * FROM files WHERE id = ?', [$id]);
        if (!$f) {
            throw new HttpError(404, 'File non trovato');
        }
        return $f;
    }

    private static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = trim(mb_substr($name, 0, 180));
        if ($name === '' || $name === '.' || $name === '..') {
            throw new HttpError(422, 'Nome del file non valido');
        }
        return $name;
    }

    /** @return list<array{name:string,tmp_name:string,error:int,size:int}> */
    private static function normalize(array $files): array
    {
        $f = $files['files'] ?? $files['file'] ?? null;
        if (!$f) {
            return [];
        }
        if (!is_array($f['name'])) {
            return [$f];
        }
        $out = [];
        foreach ($f['name'] as $i => $name) {
            $out[] = ['name' => $name, 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
        }
        return $out;
    }

    private static function uploadError(int $code, string $name): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "$name è troppo grande per le impostazioni del server (limite " . ini_get('upload_max_filesize') . ')',
            UPLOAD_ERR_PARTIAL => "$name è arrivato incompleto: riprova",
            UPLOAD_ERR_NO_FILE => 'Nessun file selezionato',
            default => "Errore nel caricamento di $name (codice $code)",
        };
    }

    public static function limits(): array
    {
        return ['max_file' => ini_get('upload_max_filesize'), 'max_post' => ini_get('post_max_size'), 'max_count' => (int) ini_get('max_file_uploads')];
    }
}
