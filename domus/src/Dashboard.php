<?php
// Riepilogo per la pagina iniziale.
declare(strict_types=1);

namespace Domus;

final class Dashboard
{
    /** @return array<string,mixed> */
    public static function data(?array $user = null): array
    {
        $counts = Db::one(
            "SELECT
                SUM(status = 'in_corso')   AS in_corso,
                SUM(status = 'preventivo') AS preventivo,
                SUM(status = 'sospeso')    AS sospeso,
                SUM(status = 'concluso')   AS concluso
             FROM projects"
        );
        return [
            'permits' => Permits::pending(),
            'finance' => Auth::isAdmin($user) ? Payments::studioSummary() : null,
            'hours_week' => (float) Db::value(
                "SELECT COALESCE(SUM(hours), 0) FROM hours WHERE user_id = ? AND work_date >= date('now','localtime','-6 days','weekday 1')",
                [$user['id'] ?? 0]
            ),
            'counts' => array_map('intval', $counts ?? []),
            'clients' => (int) Db::value('SELECT COUNT(*) FROM clients'),
            'overdue' => Db::all(
                "SELECT t.*, p.code AS project_code, p.title AS project_title FROM tasks t
                 LEFT JOIN projects p ON p.id = t.project_id
                 WHERE t.done = 0 AND t.due_date < date('now','localtime') ORDER BY t.due_date LIMIT 20"
            ),
            'upcoming' => Db::all(
                "SELECT t.*, p.code AS project_code, p.title AS project_title FROM tasks t
                 LEFT JOIN projects p ON p.id = t.project_id
                 WHERE t.done = 0 AND t.due_date BETWEEN date('now','localtime') AND date('now','localtime','+14 days')
                 ORDER BY t.due_date LIMIT 20"
            ),
            'active' => array_slice(Projects::list('in_corso'), 0, 8),
            'journal' => Db::all(
                'SELECT j.id, j.entry_date, j.kind, j.text, j.project_id, p.code AS project_code, p.title AS project_title
                 FROM journal j JOIN projects p ON p.id = j.project_id ORDER BY j.entry_date DESC, j.id DESC LIMIT 6'
            ),
        ];
    }
}
