<?php
// Progetti (commesse): dati dell'intervento, fasi, scadenze e diario.
declare(strict_types=1);

namespace Domus;

final class Projects
{
    public const STATUSES = ['preventivo', 'in_corso', 'sospeso', 'concluso', 'archiviato'];
    public const ACTIVE = ['preventivo', 'in_corso', 'sospeso'];
    public const PHASE_STATUSES = ['da_fare', 'in_corso', 'completata', 'non_prevista'];
    public const JOURNAL_KINDS = ['nota', 'sopralluogo', 'riunione', 'telefonata', 'email', 'pratica'];

    // Copertina: quella scelta (se il file esiste ancora), altrimenti la prima immagine, preferendo le foto.
    private const COVER = "COALESCE(
        (SELECT f.id FROM files f WHERE f.id = p.cover_file_id AND f.project_id = p.id
         AND f.mime IN ('image/jpeg','image/png','image/webp','image/gif')),
        (SELECT f.id FROM files f WHERE f.project_id = p.id AND f.mime IN ('image/jpeg','image/png','image/webp','image/gif')
         ORDER BY f.category = 'foto' DESC, f.id LIMIT 1)
    )";

    /** @return list<array<string,mixed>> */
    public static function list(string $status = '', string $q = ''): array
    {
        $where = [];
        $params = [];
        if ($status === 'attivi') {
            $where[] = "p.status IN ('preventivo','in_corso','sospeso')";
        } elseif ($status !== '') {
            $where[] = 'p.status = ?';
            $params[] = Validate::oneOf($status, self::STATUSES, 'status');
        }
        if ($q !== '') {
            $where[] = '(p.title LIKE ? OR p.code LIKE ? OR p.city LIKE ? OR p.address LIKE ? OR c.name LIKE ?)';
            array_push($params, ...array_fill(0, 5, '%' . $q . '%'));
        }
        $sql = "SELECT p.id, p.code, p.title, p.status, p.city, p.address, p.due_date, p.intervention, p.start_date, p.created_at,
                       c.name AS client_name, p.client_id, " . self::COVER . " AS cover_id,
                       (SELECT COUNT(*) FROM phases f WHERE f.project_id = p.id AND f.status != 'non_prevista') AS phases_total,
                       (SELECT COUNT(*) FROM phases f WHERE f.project_id = p.id AND f.status = 'completata') AS phases_done,
                       (SELECT name FROM phases f WHERE f.project_id = p.id AND f.status IN ('in_corso','da_fare') ORDER BY f.status = 'in_corso' DESC, f.position LIMIT 1) AS current_phase,
                       (SELECT MIN(due_date) FROM tasks t WHERE t.project_id = p.id AND t.done = 0 AND t.due_date IS NOT NULL) AS next_deadline
                FROM projects p LEFT JOIN clients c ON c.id = p.client_id"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY p.code DESC';
        return Db::all($sql, $params);
    }

    /** @return array<string,mixed> */
    public static function get(int $id, ?array $user = null): array
    {
        $p = Db::one(
            'SELECT p.*, ' . self::COVER . ' AS cover_id, c.name AS client_name, c.phone AS client_phone, c.email AS client_email
             FROM projects p LEFT JOIN clients c ON c.id = p.client_id WHERE p.id = ?',
            [$id]
        );
        if (!$p) {
            throw new HttpError(404, 'Progetto non trovato');
        }
        $p['phases'] = Db::all('SELECT * FROM phases WHERE project_id = ? ORDER BY position', [$id]);
        $p['tasks'] = Db::all('SELECT * FROM tasks WHERE project_id = ? ORDER BY done, due_date IS NULL, due_date, id', [$id]);
        $p['journal'] = Db::all(
            'SELECT j.*, u.name AS user_name FROM journal j LEFT JOIN users u ON u.id = j.user_id
             WHERE j.project_id = ? ORDER BY j.entry_date DESC, j.id DESC',
            [$id]
        );
        $p['permits'] = Permits::forProject($id);
        $p['files'] = Files::forProject($id);
        $p['hours'] = Hours::forProject($id);
        if (Auth::isAdmin($user)) {
            $p['payments'] = Payments::forProject($id);
        } else {
            // I dati economici sono riservati all'amministratore.
            unset($p['fee']);
        }
        return $p;
    }

    // Codice progressivo per anno: 2026-001, 2026-002…
    public static function nextCode(): string
    {
        $year = date('Y');
        $last = (string) Db::value("SELECT code FROM projects WHERE code LIKE ? ORDER BY code DESC LIMIT 1", [$year . '-%']);
        $n = $last ? ((int) substr($last, 5)) + 1 : 1;
        return sprintf('%s-%03d', $year, $n);
    }

    public static function create(array $in, ?array $user = null): int
    {
        $data = self::clean($in);
        if (!Auth::isAdmin($user)) {
            unset($data['fee']);
        }
        $data['code'] = Validate::text($in['code'] ?? null, 20) ?? self::nextCode();
        if (Db::value('SELECT 1 FROM projects WHERE code = ?', [$data['code']])) {
            throw new HttpError(422, 'Codice già usato da un altro progetto', ['code' => 'Già usato']);
        }
        return Db::transaction(function () use ($data, $in) {
            $id = Db::insert('projects', $data);
            $phases = isset($in['phases']) && is_array($in['phases']) ? $in['phases'] : Settings::defaultPhases();
            foreach (array_values($phases) as $i => $name) {
                $name = Validate::text($name, 120);
                if ($name !== null) {
                    Db::insert('phases', ['project_id' => $id, 'position' => $i + 1, 'name' => $name]);
                }
            }
            return $id;
        });
    }

    public static function update(int $id, array $in, ?array $user = null): void
    {
        self::exists($id);
        $data = self::clean($in);
        if (!Auth::isAdmin($user)) {
            unset($data['fee']);
        }
        if (isset($in['code'])) {
            $code = Validate::required($in['code'], 'code', 20);
            if (Db::value('SELECT 1 FROM projects WHERE code = ? AND id != ?', [$code, $id])) {
                throw new HttpError(422, 'Codice già usato da un altro progetto', ['code' => 'Già usato']);
            }
            $data['code'] = $code;
        }
        Db::update('projects', $id, $data + ['updated_at' => date('Y-m-d H:i:s')]);
    }

    public static function delete(int $id): void
    {
        self::exists($id);
        Db::run('DELETE FROM projects WHERE id = ?', [$id]);
        Files::deleteProjectFolder($id);
    }

    public static function exists(int $id): void
    {
        if (!Db::value('SELECT 1 FROM projects WHERE id = ?', [$id])) {
            throw new HttpError(404, 'Progetto non trovato');
        }
    }

    public static function setStatus(int $id, array $in): void
    {
        self::exists($id);
        Db::update('projects', $id, [
            'status' => Validate::oneOf($in['status'] ?? null, self::STATUSES, 'status'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function setCover(int $id, array $in): void
    {
        self::exists($id);
        $fileId = $in['file_id'] ?? null;
        if ($fileId !== null && !Db::value(
            "SELECT 1 FROM files WHERE id = ? AND project_id = ? AND mime IN ('image/jpeg','image/png','image/webp','image/gif')",
            [$fileId, $id]
        )) {
            throw new HttpError(422, 'Come copertina si può usare solo un\'immagine del progetto');
        }
        Db::update('projects', $id, ['cover_file_id' => $fileId]);
    }

    // ---------- fasi ----------

    public static function updatePhase(int $id, array $in): void
    {
        $phase = Db::one('SELECT * FROM phases WHERE id = ?', [$id]);
        if (!$phase) {
            throw new HttpError(404, 'Fase non trovata');
        }
        $data = [];
        if (array_key_exists('status', $in)) {
            $data['status'] = Validate::oneOf($in['status'], self::PHASE_STATUSES, 'status');
            $data['completed_at'] = $data['status'] === 'completata' ? ($phase['completed_at'] ?? date('Y-m-d')) : null;
        }
        if (array_key_exists('name', $in)) {
            $data['name'] = Validate::required($in['name'], 'name', 120);
        }
        if (array_key_exists('due_date', $in)) {
            $data['due_date'] = Validate::date($in['due_date'], 'due_date');
        }
        if (array_key_exists('notes', $in)) {
            $data['notes'] = Validate::text($in['notes'], 2000);
        }
        Db::update('phases', $id, $data);
        Db::run('UPDATE projects SET updated_at = ? WHERE id = ?', [App::now(), $phase['project_id']]);
    }

    public static function addPhase(int $projectId, array $in): int
    {
        self::exists($projectId);
        $pos = (int) Db::value('SELECT COALESCE(MAX(position), 0) + 1 FROM phases WHERE project_id = ?', [$projectId]);
        return Db::insert('phases', [
            'project_id' => $projectId,
            'position' => $pos,
            'name' => Validate::required($in['name'] ?? '', 'name', 120),
        ]);
    }

    public static function deletePhase(int $id): void
    {
        if (!Db::run('DELETE FROM phases WHERE id = ?', [$id])) {
            throw new HttpError(404, 'Fase non trovata');
        }
    }

    // ---------- diario ----------

    public static function addJournal(array $user, array $in): int
    {
        $projectId = Validate::id($in['project_id'] ?? null, 'projects', 'project_id', required: true);
        return Db::insert('journal', [
            'project_id' => $projectId,
            'entry_date' => Validate::date($in['entry_date'] ?? null, 'entry_date') ?? date('Y-m-d'),
            'kind' => Validate::oneOf($in['kind'] ?? null, self::JOURNAL_KINDS, 'kind', 'nota'),
            'text' => Validate::required($in['text'] ?? '', 'text', 10000),
            'user_id' => $user['id'],
        ]);
    }

    public static function deleteJournal(int $id): void
    {
        if (!Db::run('DELETE FROM journal WHERE id = ?', [$id])) {
            throw new HttpError(404, 'Nota non trovata');
        }
    }

    /** @return array<string,mixed> */
    private static function clean(array $in): array
    {
        return [
            'title' => Validate::required($in['title'] ?? '', 'title', 200),
            'client_id' => Validate::id($in['client_id'] ?? null, 'clients', 'client_id'),
            'address' => Validate::text($in['address'] ?? null, 200),
            'city' => Validate::text($in['city'] ?? null, 100),
            'cadastral' => Validate::text($in['cadastral'] ?? null, 300),
            'intervention' => Validate::text($in['intervention'] ?? null, 120),
            'status' => Validate::oneOf($in['status'] ?? null, self::STATUSES, 'status', 'in_corso'),
            'start_date' => Validate::date($in['start_date'] ?? null, 'start_date'),
            'due_date' => Validate::date($in['due_date'] ?? null, 'due_date'),
            'fee' => Validate::money($in['fee'] ?? null, 'fee'),
            'notes' => Validate::text($in['notes'] ?? null, 10000),
        ];
    }
}
