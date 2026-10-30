<?php
// Scadenze e cose da fare, legate a un progetto oppure generali dello studio.
declare(strict_types=1);

namespace Domus;

final class Tasks
{
    /** @return list<array<string,mixed>> */
    public static function list(string $filter = 'aperte'): array
    {
        $where = match ($filter) {
            'aperte' => 't.done = 0',
            'scadute' => "t.done = 0 AND t.due_date < date('now','localtime')",
            'settimana' => "t.done = 0 AND t.due_date <= date('now','localtime','+7 days')",
            'fatte' => 't.done = 1',
            'tutte' => '1 = 1',
            default => throw new HttpError(422, 'Filtro non valido'),
        };
        $order = $filter === 'fatte' ? 't.done_at DESC' : 't.due_date IS NULL, t.due_date, t.id';
        return Db::all(
            "SELECT t.*, p.code AS project_code, p.title AS project_title
             FROM tasks t LEFT JOIN projects p ON p.id = t.project_id
             WHERE $where ORDER BY $order LIMIT 500"
        );
    }

    public static function create(array $in): int
    {
        return Db::insert('tasks', [
            'project_id' => Validate::id($in['project_id'] ?? null, 'projects', 'project_id'),
            'title' => Validate::required($in['title'] ?? '', 'title', 200),
            'due_date' => Validate::date($in['due_date'] ?? null, 'due_date'),
            'notes' => Validate::text($in['notes'] ?? null, 2000),
        ]);
    }

    public static function update(int $id, array $in): void
    {
        $task = Db::one('SELECT * FROM tasks WHERE id = ?', [$id]);
        if (!$task) {
            throw new HttpError(404, 'Scadenza non trovata');
        }
        $data = [];
        if (array_key_exists('title', $in)) {
            $data['title'] = Validate::required($in['title'], 'title', 200);
        }
        if (array_key_exists('due_date', $in)) {
            $data['due_date'] = Validate::date($in['due_date'], 'due_date');
        }
        if (array_key_exists('notes', $in)) {
            $data['notes'] = Validate::text($in['notes'], 2000);
        }
        if (array_key_exists('done', $in)) {
            $done = filter_var($in['done'], FILTER_VALIDATE_BOOLEAN);
            $data['done'] = $done ? 1 : 0;
            $data['done_at'] = $done ? ($task['done_at'] ?? date('Y-m-d H:i:s')) : null;
        }
        Db::update('tasks', $id, $data);
    }

    public static function delete(int $id): void
    {
        if (!Db::run('DELETE FROM tasks WHERE id = ?', [$id])) {
            throw new HttpError(404, 'Scadenza non trovata');
        }
    }
}
