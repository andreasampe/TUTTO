<?php
// Ricerca rapida (⌘K): progetti, clienti, pratiche e documenti.
declare(strict_types=1);

namespace Domus;

final class Search
{
    /** @return list<array<string,mixed>> */
    public static function run(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $like = '%' . $q . '%';
        $out = [];
        foreach (Db::all(
            'SELECT p.id, p.code, p.title, p.city, p.status, c.name AS client FROM projects p LEFT JOIN clients c ON c.id = p.client_id
             WHERE p.title LIKE ? OR p.code LIKE ? OR p.city LIKE ? OR p.address LIKE ? OR p.cadastral LIKE ? OR c.name LIKE ?
             ORDER BY p.status IN (\'concluso\',\'archiviato\'), p.code DESC LIMIT 8',
            array_fill(0, 6, $like)
        ) as $r) {
            $out[] = ['type' => 'progetto', 'href' => "/progetti/{$r['id']}", 'title' => $r['title'], 'meta' => implode(' · ', array_filter([$r['code'], $r['client'], $r['city']]))];
        }
        foreach (Db::all(
            'SELECT id, name, city, email FROM clients WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? OR tax_code LIKE ? OR vat LIKE ? ORDER BY name LIMIT 6',
            array_fill(0, 5, $like)
        ) as $r) {
            $out[] = ['type' => 'cliente', 'href' => "/clienti/{$r['id']}", 'title' => $r['name'], 'meta' => implode(' · ', array_filter([$r['city'], $r['email']]))];
        }
        foreach (Db::all(
            'SELECT pe.kind, pe.protocol, pe.project_id, p.code FROM permits pe JOIN projects p ON p.id = pe.project_id
             WHERE pe.protocol LIKE ? OR pe.kind LIKE ? LIMIT 5',
            [$like, $like]
        ) as $r) {
            $out[] = ['type' => 'pratica', 'href' => "/progetti/{$r['project_id']}/pratiche", 'title' => $r['kind'] . ($r['protocol'] ? " · prot. {$r['protocol']}" : ''), 'meta' => $r['code']];
        }
        foreach (Db::all(
            'SELECT f.name, f.project_id, p.code FROM files f JOIN projects p ON p.id = f.project_id WHERE f.name LIKE ? ORDER BY f.id DESC LIMIT 5',
            [$like]
        ) as $r) {
            $out[] = ['type' => 'documento', 'href' => "/progetti/{$r['project_id']}/documenti", 'title' => $r['name'], 'meta' => $r['code']];
        }
        return $out;
    }
}
