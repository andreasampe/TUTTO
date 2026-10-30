<?php
// Database SQLite in un singolo file, fuori dalla cartella pubblica.
// Lo schema si crea e si aggiorna da solo all'avvio (migrazioni numerate).
declare(strict_types=1);

namespace Domus;

use PDO;

final class Db
{
    private static ?PDO $pdo = null;

    // Ogni voce è una migrazione: non modificare quelle già esistenti, aggiungerne di nuove in coda.
    private const MIGRATIONS = [
        1 => <<<'SQL'
            CREATE TABLE users (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                name          TEXT NOT NULL,
                email         TEXT NOT NULL UNIQUE COLLATE NOCASE,
                password_hash TEXT NOT NULL,
                role          TEXT NOT NULL DEFAULT 'admin',
                created_at    TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE clients (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT NOT NULL,
                kind       TEXT NOT NULL DEFAULT 'privato',
                tax_code   TEXT,
                vat        TEXT,
                email      TEXT,
                phone      TEXT,
                address    TEXT,
                city       TEXT,
                notes      TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE projects (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                code         TEXT NOT NULL UNIQUE,
                title        TEXT NOT NULL,
                client_id    INTEGER REFERENCES clients(id) ON DELETE SET NULL,
                address      TEXT,
                city         TEXT,
                cadastral    TEXT,
                intervention TEXT,
                status       TEXT NOT NULL DEFAULT 'in_corso',
                start_date   TEXT,
                due_date     TEXT,
                fee          REAL,
                notes        TEXT,
                created_at   TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at   TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE phases (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id   INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                position     INTEGER NOT NULL,
                name         TEXT NOT NULL,
                status       TEXT NOT NULL DEFAULT 'da_fare',
                due_date     TEXT,
                completed_at TEXT,
                notes        TEXT
            );

            CREATE TABLE tasks (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
                title      TEXT NOT NULL,
                due_date   TEXT,
                done       INTEGER NOT NULL DEFAULT 0,
                done_at    TEXT,
                notes      TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE journal (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                entry_date TEXT NOT NULL,
                kind       TEXT NOT NULL DEFAULT 'nota',
                text       TEXT NOT NULL,
                user_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE settings (
                key   TEXT PRIMARY KEY,
                value TEXT
            );

            CREATE TABLE login_attempts (
                ip         TEXT NOT NULL,
                attempt_at INTEGER NOT NULL
            );

            CREATE INDEX idx_projects_client ON projects(client_id);
            CREATE INDEX idx_projects_status ON projects(status);
            CREATE INDEX idx_phases_project  ON phases(project_id, position);
            CREATE INDEX idx_tasks_due       ON tasks(done, due_date);
            CREATE INDEX idx_journal_project ON journal(project_id, entry_date);
            SQL,

        // Fase 2: documenti, pratiche edilizie, economia, ore, collaboratori.
        2 => <<<'SQL'
            CREATE TABLE files (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                name       TEXT NOT NULL,
                stored     TEXT NOT NULL UNIQUE,
                mime       TEXT NOT NULL,
                size       INTEGER NOT NULL,
                category   TEXT NOT NULL DEFAULT 'documento',
                user_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE permits (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id  INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                kind        TEXT NOT NULL,
                status      TEXT NOT NULL DEFAULT 'in_preparazione',
                authority   TEXT,
                protocol    TEXT,
                filed_at    TEXT,
                approved_at TEXT,
                expires_at  TEXT,
                notes       TEXT,
                created_at  TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE payments (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id  INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                description TEXT NOT NULL,
                amount      REAL NOT NULL,
                due_date    TEXT,
                invoice_no  TEXT,
                invoiced_at TEXT,
                paid_at     TEXT,
                notes       TEXT,
                created_at  TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE hours (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id  INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                user_id     INTEGER REFERENCES users(id) ON DELETE SET NULL,
                work_date   TEXT NOT NULL,
                hours       REAL NOT NULL,
                description TEXT,
                created_at  TEXT NOT NULL DEFAULT (datetime('now'))
            );

            ALTER TABLE users ADD COLUMN active INTEGER NOT NULL DEFAULT 1;

            CREATE INDEX idx_files_project    ON files(project_id);
            CREATE INDEX idx_permits_project  ON permits(project_id);
            CREATE INDEX idx_payments_project ON payments(project_id);
            CREATE INDEX idx_hours_project    ON hours(project_id, work_date);
            SQL,

        // Copertina del progetto scelta a mano (altrimenti si usa la prima foto).
        3 => <<<'SQL'
            ALTER TABLE projects ADD COLUMN cover_file_id INTEGER;
            SQL,

        // Area "Planimetrie": planimetrie esecutive in DXF con lo storico delle versioni.
        // Ogni versione è un file dei Documenti del progetto (categoria "tavola").
        4 => <<<'SQL'
            CREATE TABLE plans (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                title      TEXT NOT NULL,
                kind       TEXT NOT NULL DEFAULT 'pianta_quotata',
                notes      TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE plan_versions (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                plan_id    INTEGER NOT NULL REFERENCES plans(id) ON DELETE CASCADE,
                file_id    INTEGER NOT NULL REFERENCES files(id) ON DELETE CASCADE,
                number     INTEGER NOT NULL,
                note       TEXT,
                report     TEXT,
                user_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE INDEX idx_plans_project       ON plans(project_id);
            CREATE INDEX idx_plan_versions_plan  ON plan_versions(plan_id, number);
            SQL,
    ];

    public static function pdo(): PDO
    {
        if (self::$pdo) {
            return self::$pdo;
        }
        if (!extension_loaded('pdo_sqlite')) {
            throw new HttpError(500, 'Il PHP di questo sito non ha SQLite (pdo_sqlite): attivalo nelle impostazioni del sito.');
        }
        $dir = App::config('data_dir');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            throw new HttpError(500, "Impossibile creare la cartella dei dati: $dir");
        }
        $pdo = new PDO('sqlite:' . $dir . '/domus.sqlite', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
        self::migrate($pdo);
        return self::$pdo = $pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $current = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
        foreach (self::MIGRATIONS as $version => $sql) {
            if ($version <= $current) {
                continue;
            }
            $pdo->beginTransaction();
            try {
                $pdo->exec($sql);
                $pdo->exec("PRAGMA user_version = $version");
                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    }

    /** @return list<array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchColumn();
    }

    public static function run(string $sql, array $params = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $cols),
            implode(', ', array_fill(0, count($cols), '?'))
        );
        self::run($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, int $id, array $data): int
    {
        if (!$data) {
            return 0;
        }
        $set = implode(', ', array_map(fn ($c) => "$c = ?", array_keys($data)));
        return self::run("UPDATE $table SET $set WHERE id = ?", [...array_values($data), $id]);
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $out = $fn();
            $pdo->commit();
            return $out;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
