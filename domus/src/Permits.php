<?php
// Pratiche edilizie e autorizzazioni legate a un progetto.
declare(strict_types=1);

namespace Domus;

final class Permits
{
    public const KINDS = [
        'CILA', 'CILAS', 'SCIA', 'SCIA alternativa al PdC', 'Permesso di costruire',
        'Autorizzazione paesaggistica', 'Nulla osta Soprintendenza', 'Deposito strutturale (sismica)',
        'SCA (agibilità)', 'Accatastamento (DOCFA)', 'APE', 'Altro',
    ];
    public const STATUSES = ['in_preparazione', 'depositata', 'integrazioni', 'approvata', 'respinta', 'archiviata'];

    /** @return list<array<string,mixed>> */
    public static function forProject(int $projectId): array
    {
        return Db::all('SELECT * FROM permits WHERE project_id = ? ORDER BY COALESCE(filed_at, created_at) DESC, id DESC', [$projectId]);
    }

    /** @return list<array<string,mixed>> pratiche in attesa di esito, per la panoramica */
    public static function pending(): array
    {
        return Db::all(
            "SELECT pe.*, p.code AS project_code, p.title AS project_title,
                    CAST(julianday('now','localtime') - julianday(pe.filed_at) AS INTEGER) AS days_waiting
             FROM permits pe JOIN projects p ON p.id = pe.project_id
             WHERE pe.status IN ('depositata','integrazioni') ORDER BY pe.filed_at"
        );
    }

    public static function create(array $in): int
    {
        $data = self::clean($in);
        $data['project_id'] = Validate::id($in['project_id'] ?? null, 'projects', 'project_id', required: true);
        return Db::insert('permits', $data);
    }

    public static function update(int $id, array $in): void
    {
        if (!Db::value('SELECT 1 FROM permits WHERE id = ?', [$id])) {
            throw new HttpError(404, 'Pratica non trovata');
        }
        Db::update('permits', $id, self::clean($in));
    }

    public static function delete(int $id): void
    {
        if (!Db::run('DELETE FROM permits WHERE id = ?', [$id])) {
            throw new HttpError(404, 'Pratica non trovata');
        }
    }

    /** @return array<string,mixed> */
    private static function clean(array $in): array
    {
        $data = [
            'kind' => Validate::oneOf($in['kind'] ?? null, self::KINDS, 'kind'),
            'status' => Validate::oneOf($in['status'] ?? null, self::STATUSES, 'status', 'in_preparazione'),
            'authority' => Validate::text($in['authority'] ?? null, 120),
            'protocol' => Validate::text($in['protocol'] ?? null, 60),
            'filed_at' => Validate::date($in['filed_at'] ?? null, 'filed_at'),
            'approved_at' => Validate::date($in['approved_at'] ?? null, 'approved_at'),
            'expires_at' => Validate::date($in['expires_at'] ?? null, 'expires_at'),
            'notes' => Validate::text($in['notes'] ?? null, 3000),
        ];
        if (in_array($data['status'], ['depositata', 'integrazioni', 'approvata', 'respinta'], true) && !$data['filed_at']) {
            $data['filed_at'] = date('Y-m-d');
        }
        if ($data['status'] === 'approvata' && !$data['approved_at']) {
            $data['approved_at'] = date('Y-m-d');
        }
        return $data;
    }
}
