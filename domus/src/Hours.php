<?php
// Ore lavorate sui progetti, per persona.
declare(strict_types=1);

namespace Domus;

final class Hours
{
    /** @return array<string,mixed> */
    public static function forProject(int $projectId): array
    {
        return [
            'items' => Db::all(
                'SELECT h.*, u.name AS user_name FROM hours h LEFT JOIN users u ON u.id = h.user_id
                 WHERE h.project_id = ? ORDER BY h.work_date DESC, h.id DESC LIMIT 300',
                [$projectId]
            ),
            'total' => (float) Db::value('SELECT COALESCE(SUM(hours), 0) FROM hours WHERE project_id = ?', [$projectId]),
            'by_user' => Db::all(
                'SELECT COALESCE(u.name, \'—\') AS user_name, SUM(h.hours) AS hours FROM hours h LEFT JOIN users u ON u.id = h.user_id
                 WHERE h.project_id = ? GROUP BY h.user_id ORDER BY hours DESC',
                [$projectId]
            ),
        ];
    }

    public static function create(array $user, array $in): int
    {
        $hours = self::hours($in['hours'] ?? null);
        return Db::insert('hours', [
            'project_id' => Validate::id($in['project_id'] ?? null, 'projects', 'project_id', required: true),
            'user_id' => $user['id'],
            'work_date' => Validate::date($in['work_date'] ?? null, 'work_date') ?? date('Y-m-d'),
            'hours' => $hours,
            'description' => Validate::text($in['description'] ?? null, 300),
        ]);
    }

    // Un collaboratore può eliminare solo le proprie ore; l'amministratore tutte.
    public static function delete(array $user, int $id): void
    {
        $row = Db::one('SELECT user_id FROM hours WHERE id = ?', [$id]);
        if (!$row) {
            throw new HttpError(404, 'Registrazione non trovata');
        }
        if (!Auth::isAdmin($user) && (int) $row['user_id'] !== (int) $user['id']) {
            throw new HttpError(403, 'Puoi eliminare solo le tue ore');
        }
        Db::run('DELETE FROM hours WHERE id = ?', [$id]);
    }

    // Accetta "1,5", "1.5" e "1:30".
    private static function hours(mixed $v): float
    {
        $s = trim((string) $v);
        if (preg_match('/^(\d{1,2}):([0-5]\d)$/', $s, $m)) {
            $h = (int) $m[1] + (int) $m[2] / 60;
        } else {
            $s = str_replace(',', '.', $s);
            $h = is_numeric($s) ? (float) $s : 0.0;
        }
        if ($h <= 0 || $h > 24) {
            throw new HttpError(422, 'Ore non valide (da 0,25 a 24)', ['hours' => 'Non valide']);
        }
        return round($h, 2);
    }
}
