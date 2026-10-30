<?php
// Calendario: raccoglie in un'unica vista scadenze, fasi, pratiche, consegne e rate.
declare(strict_types=1);

namespace Domus;

final class Calendar
{
    /** @return list<array<string,mixed>> */
    public static function events(array $user, string $from, string $to): array
    {
        $from = Validate::date($from, 'from') ?? date('Y-m-01');
        $to = Validate::date($to, 'to') ?? date('Y-m-t');
        $range = [$from, $to];
        $events = [];

        foreach (Db::all(
            'SELECT t.id, t.title, t.due_date AS date, t.done, t.project_id, p.code FROM tasks t
             LEFT JOIN projects p ON p.id = t.project_id WHERE t.due_date BETWEEN ? AND ?',
            $range
        ) as $r) {
            $events[] = ['type' => 'task', 'date' => $r['date'], 'title' => $r['title'], 'done' => (bool) $r['done'], 'project_id' => $r['project_id'], 'code' => $r['code'], 'id' => $r['id']];
        }
        foreach (Db::all(
            "SELECT f.name, f.due_date AS date, f.status, f.project_id, p.code FROM phases f
             JOIN projects p ON p.id = f.project_id WHERE f.due_date BETWEEN ? AND ? AND f.status != 'non_prevista'",
            $range
        ) as $r) {
            $events[] = ['type' => 'phase', 'date' => $r['date'], 'title' => $r['name'], 'done' => $r['status'] === 'completata', 'project_id' => $r['project_id'], 'code' => $r['code']];
        }
        foreach (Db::all(
            "SELECT id, title, due_date AS date, code, status FROM projects WHERE due_date BETWEEN ? AND ? AND status != 'archiviato'",
            $range
        ) as $r) {
            $events[] = ['type' => 'delivery', 'date' => $r['date'], 'title' => 'Consegna: ' . $r['title'], 'done' => $r['status'] === 'concluso', 'project_id' => $r['id'], 'code' => $r['code']];
        }
        foreach (['filed_at' => 'depositata', 'approved_at' => 'approvata', 'expires_at' => 'in scadenza'] as $col => $label) {
            foreach (Db::all(
                "SELECT pe.kind, pe.$col AS date, pe.project_id, p.code FROM permits pe
                 JOIN projects p ON p.id = pe.project_id WHERE pe.$col BETWEEN ? AND ?",
                $range
            ) as $r) {
                $events[] = ['type' => 'permit', 'date' => $r['date'], 'title' => "{$r['kind']} $label", 'done' => $col !== 'expires_at', 'project_id' => $r['project_id'], 'code' => $r['code']];
            }
        }
        if (Auth::isAdmin($user)) {
            foreach (Db::all(
                'SELECT pa.description, pa.amount, pa.due_date AS date, pa.paid_at, pa.project_id, p.code FROM payments pa
                 JOIN projects p ON p.id = pa.project_id WHERE pa.due_date BETWEEN ? AND ?',
                $range
            ) as $r) {
                $events[] = ['type' => 'payment', 'date' => $r['date'], 'title' => $r['description'], 'amount' => (float) $r['amount'], 'done' => (bool) $r['paid_at'], 'project_id' => $r['project_id'], 'code' => $r['code']];
            }
        }
        usort($events, fn ($a, $b) => [$a['date'], $a['type']] <=> [$b['date'], $b['type']]);
        return $events;
    }
}
