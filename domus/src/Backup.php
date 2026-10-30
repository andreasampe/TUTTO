<?php
// Esportazione completa dei dati in JSON: backup leggibile e indipendente da SQLite.
declare(strict_types=1);

namespace Domus;

final class Backup
{
    private const TABLES = ['clients', 'projects', 'phases', 'tasks', 'journal', 'permits', 'payments', 'hours', 'files', 'settings'];

    /** @return array<string,mixed> */
    public static function export(): array
    {
        $out = [
            'app' => 'DOMUS',
            'exported_at' => date('c'),
            'schema_version' => (int) Db::value('PRAGMA user_version'),
            // Gli utenti si esportano senza password.
            'users' => Db::all('SELECT id, name, email, role, active, created_at FROM users ORDER BY id'),
            // I documenti sono elencati qui; i file veri stanno in domus-data/files/.
        ];
        foreach (self::TABLES as $t) {
            $out[$t] = Db::all("SELECT * FROM $t");
        }
        return $out;
    }
}
