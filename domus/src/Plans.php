<?php
// Area "Planimetrie": planimetrie esecutive (DXF) dei progetti, con lo storico delle versioni.
// I ritocchi (quote, testi, cartiglio) e la quotatura automatica avvengono nel browser con il
// motore DOMUS CAD; qui si conservano i file: ogni versione è un documento del progetto.
declare(strict_types=1);

namespace Domus;

final class Plans
{
    public const KINDS = [
        'pianta_quotata' => 'Pianta quotata',
        'stato_fatto' => 'Stato di fatto',
        'stato_progetto' => 'Stato di progetto',
        'demolizioni_costruzioni' => 'Demolizioni e costruzioni',
        'impianto_elettrico' => 'Impianto elettrico',
        'impianto_idrico' => 'Impianto idrico-sanitario',
        'impianto_scarichi' => 'Impianto di scarico',
        'altro' => 'Altra planimetria',
    ];

    /** @return list<array<string,mixed>> */
    public static function list(?int $projectId, string $q): array
    {
        $where = [];
        $params = [];
        if ($projectId) {
            $where[] = 'pl.project_id = ?';
            $params[] = $projectId;
        }
        if ($q !== '') {
            $where[] = '(pl.title LIKE ? OR p.title LIKE ? OR p.code LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }
        return Db::all(
            'SELECT pl.id, pl.project_id, pl.title, pl.kind, pl.updated_at, p.code, p.title AS project_title,
                    v.number AS version, v.file_id, v.created_at AS version_at, f.name AS file_name, f.size AS file_size,
                    (SELECT COUNT(*) FROM plan_versions x WHERE x.plan_id = pl.id) AS versions
             FROM plans pl
             JOIN projects p ON p.id = pl.project_id
             LEFT JOIN plan_versions v ON v.id = (SELECT id FROM plan_versions y WHERE y.plan_id = pl.id ORDER BY number DESC LIMIT 1)
             LEFT JOIN files f ON f.id = v.file_id'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY pl.updated_at DESC, pl.id DESC',
            $params
        );
    }

    /** @return array<string,mixed> */
    public static function get(int $id): array
    {
        $pl = Db::one(
            'SELECT pl.*, p.code, p.title AS project_title FROM plans pl JOIN projects p ON p.id = pl.project_id WHERE pl.id = ?',
            [$id]
        );
        if (!$pl) {
            throw new HttpError(404, 'Planimetria non trovata');
        }
        $pl['versions'] = Db::all(
            'SELECT v.id, v.number, v.note, v.report, v.created_at, v.file_id, f.name AS file_name, f.size AS file_size, u.name AS user_name
             FROM plan_versions v JOIN files f ON f.id = v.file_id LEFT JOIN users u ON u.id = v.user_id
             WHERE v.plan_id = ? ORDER BY v.number DESC',
            [$id]
        );
        foreach ($pl['versions'] as &$v) {
            $v['report'] = $v['report'] ? json_decode((string) $v['report'], true) : null;
        }
        return $pl;
    }

    /** Nuova planimetria a partire da un DXF già caricato nei Documenti del progetto. */
    public static function create(array $user, array $in): int
    {
        $errors = [];
        $projectId = (int) ($in['project_id'] ?? 0);
        if (!Db::one('SELECT id FROM projects WHERE id = ?', [$projectId])) {
            $errors['project_id'] = 'Scegli il progetto';
        }
        $title = Validate::text($in['title'] ?? '', 160) ?? '';
        if ($title === '') {
            $errors['title'] = 'Indica un titolo';
        }
        $kind = (string) ($in['kind'] ?? 'pianta_quotata');
        if (!isset(self::KINDS[$kind])) {
            $errors['kind'] = 'Tipo di planimetria non valido';
        }
        $file = Db::one('SELECT id, project_id, name FROM files WHERE id = ?', [(int) ($in['file_id'] ?? 0)]);
        if (!$file || (int) $file['project_id'] !== $projectId) {
            $errors['file_id'] = 'Scegli un file DXF del progetto';
        } elseif (!self::isDxf($file['name'])) {
            $errors['file_id'] = 'Serve un file DXF (da AutoCAD: Salva con nome → DXF 2018)';
        }
        if ($errors) {
            throw new HttpError(422, 'Controlla i campi evidenziati', $errors);
        }
        return Db::transaction(function () use ($user, $projectId, $title, $kind, $file, $in) {
            $id = Db::insert('plans', [
                'project_id' => $projectId,
                'title' => $title,
                'kind' => $kind,
                'notes' => Validate::text($in['notes'] ?? '', 2000) ?: null,
            ]);
            Db::insert('plan_versions', [
                'plan_id' => $id, 'file_id' => (int) $file['id'], 'number' => 1,
                'note' => 'Disegno di partenza', 'user_id' => $user['id'],
            ]);
            return $id;
        });
    }

    /** Nuova versione (DXF prodotto nel browser): diventa anche un documento del progetto. */
    public static function addVersion(array $user, int $id, array $files, string $note, string $report): array
    {
        $pl = self::get($id);
        $f = $files['file'] ?? null;
        if (!$f || !self::isDxf((string) $f['name'])) {
            throw new HttpError(422, 'Serve un file DXF');
        }
        if ($report !== '' && (strlen($report) > 65536 || json_decode($report) === null)) {
            throw new HttpError(422, 'Resoconto dei controlli non valido');
        }
        [$fileId] = Files::upload($user, (int) $pl['project_id'], ['file' => $f], 'tavola');
        $number = (int) Db::value('SELECT COALESCE(MAX(number), 0) + 1 FROM plan_versions WHERE plan_id = ?', [$id]);
        Db::insert('plan_versions', [
            'plan_id' => $id, 'file_id' => $fileId, 'number' => $number,
            'note' => Validate::text($note, 300) ?: null, 'report' => $report !== '' ? $report : null, 'user_id' => $user['id'],
        ]);
        Db::run("UPDATE plans SET updated_at = datetime('now') WHERE id = ?", [$id]);
        return ['number' => $number, 'file_id' => $fileId];
    }

    public static function update(int $id, array $in): void
    {
        self::get($id);
        $data = [];
        if (array_key_exists('title', $in)) {
            $data['title'] = Validate::text($in['title'], 160) ?? '';
            if ($data['title'] === '') {
                throw new HttpError(422, 'Indica un titolo', ['title' => 'Indica un titolo']);
            }
        }
        if (array_key_exists('kind', $in)) {
            $data['kind'] = Validate::oneOf($in['kind'], array_keys(self::KINDS), 'kind');
        }
        if (array_key_exists('notes', $in)) {
            $data['notes'] = Validate::text($in['notes'], 2000) ?: null;
        }
        if ($data) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            Db::update('plans', $id, $data);
        }
    }

    // I file delle versioni restano nei Documenti del progetto: si toglie solo la planimetria.
    public static function delete(int $id): void
    {
        self::get($id);
        Db::run('DELETE FROM plans WHERE id = ?', [$id]);
    }

    private static function isDxf(string $name): bool
    {
        return strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'dxf';
    }
}
