<?php
// Statistiche dello studio per la pagina "Studio": incassi, ore, carico di lavoro, pratiche.
declare(strict_types=1);

namespace Domus;

final class Reports
{
    /** @return array<string,mixed> */
    public static function data(array $user): array
    {
        $admin = Auth::isAdmin($user);
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = date('Y-m', strtotime("first day of -$i months"));
        }
        $paid = $admin ? self::byMonth("SELECT substr(paid_at,1,7) AS m, SUM(amount) AS v FROM payments WHERE paid_at IS NOT NULL GROUP BY m") : [];
        $invoiced = $admin ? self::byMonth("SELECT substr(invoiced_at,1,7) AS m, SUM(amount) AS v FROM payments WHERE invoiced_at IS NOT NULL GROUP BY m") : [];
        $hoursMonth = self::byMonth('SELECT substr(work_date,1,7) AS m, SUM(hours) AS v FROM hours GROUP BY m');

        return [
            'months' => $months,
            'paid' => $admin ? array_map(fn ($m) => $paid[$m] ?? 0, $months) : null,
            'invoiced' => $admin ? array_map(fn ($m) => $invoiced[$m] ?? 0, $months) : null,
            'hours_month' => array_map(fn ($m) => $hoursMonth[$m] ?? 0, $months),
            'hours_projects' => Db::all(
                "SELECT p.id, p.code, p.title, SUM(h.hours) AS hours FROM hours h JOIN projects p ON p.id = h.project_id
                 WHERE h.work_date >= ? GROUP BY p.id ORDER BY hours DESC LIMIT 8",
                [App::today(-90)]
            ),
            'hours_people' => Db::all(
                "SELECT u.id, u.name, SUM(h.hours) AS hours FROM hours h JOIN users u ON u.id = h.user_id
                 WHERE h.work_date >= ? GROUP BY u.id ORDER BY hours DESC",
                [App::today(-30)]
            ),
            'status' => Db::all('SELECT status, COUNT(*) AS n FROM projects GROUP BY status'),
            'permits' => Db::one(
                "SELECT COUNT(*) AS total,
                        SUM(status IN ('depositata','integrazioni')) AS waiting,
                        SUM(status = 'approvata') AS approved,
                        CAST(AVG(CASE WHEN approved_at IS NOT NULL AND filed_at IS NOT NULL
                                 THEN julianday(approved_at) - julianday(filed_at) END) AS INTEGER) AS avg_days
                 FROM permits"
            ),
            'totals' => $admin ? Db::one(
                "SELECT COALESCE(SUM(fee), 0) AS fees,
                        (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE paid_at IS NOT NULL) AS paid,
                        (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoiced_at IS NOT NULL AND paid_at IS NULL) AS to_collect
                 FROM projects WHERE status != 'archiviato'"
            ) : null,
        ];
    }

    /** @return array<string,float> */
    private static function byMonth(string $sql): array
    {
        $out = [];
        foreach (Db::all($sql) as $r) {
            $out[$r['m']] = (float) $r['v'];
        }
        return $out;
    }

    /** @return list<array<string,mixed>> tutte le immagini dei progetti, per la galleria */
    public static function gallery(): array
    {
        return Db::all(
            "SELECT f.id, f.name, f.created_at, p.id AS project_id, p.code, p.title, p.city
             FROM files f JOIN projects p ON p.id = f.project_id
             WHERE f.mime IN ('image/jpeg','image/png','image/webp','image/gif')
             ORDER BY f.created_at DESC, f.id DESC LIMIT 300"
        );
    }
}
