<?php
// Clienti dello studio (committenti privati, aziende, enti).
declare(strict_types=1);

namespace Domus;

final class Clients
{
    public const KINDS = ['privato', 'azienda', 'ente'];

    /** @return list<array<string,mixed>> */
    public static function list(string $q = ''): array
    {
        $sql = "SELECT c.*,
                       (SELECT COUNT(*) FROM projects p WHERE p.client_id = c.id) AS projects_count,
                       (SELECT COUNT(*) FROM projects p WHERE p.client_id = c.id AND p.status IN ('preventivo','in_corso','sospeso')) AS active_count
                FROM clients c";
        $params = [];
        if ($q !== '') {
            $sql .= ' WHERE c.name LIKE ? OR c.email LIKE ? OR c.city LIKE ? OR c.tax_code LIKE ? OR c.vat LIKE ?';
            $params = array_fill(0, 5, '%' . $q . '%');
        }
        return Db::all($sql . ' ORDER BY c.name COLLATE NOCASE', $params);
    }

    /** @return array<string,mixed> */
    public static function get(int $id): array
    {
        $c = Db::one('SELECT * FROM clients WHERE id = ?', [$id]);
        if (!$c) {
            throw new HttpError(404, 'Cliente non trovato');
        }
        $c['projects'] = Db::all('SELECT id, code, title, status, city FROM projects WHERE client_id = ? ORDER BY code DESC', [$id]);
        return $c;
    }

    public static function create(array $in): int
    {
        return Db::insert('clients', self::clean($in));
    }

    public static function update(int $id, array $in): void
    {
        self::get($id);
        Db::update('clients', $id, self::clean($in) + ['updated_at' => date('Y-m-d H:i:s')]);
    }

    public static function delete(int $id): void
    {
        self::get($id);
        // I progetti restano: perdono solo il collegamento al cliente.
        Db::run('DELETE FROM clients WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed> */
    private static function clean(array $in): array
    {
        return [
            'name' => Validate::required($in['name'] ?? '', 'name', 160),
            'kind' => Validate::oneOf($in['kind'] ?? null, self::KINDS, 'kind', 'privato'),
            'tax_code' => ($v = Validate::text($in['tax_code'] ?? null, 16)) ? mb_strtoupper($v) : null,
            'vat' => Validate::text($in['vat'] ?? null, 20),
            'email' => Validate::email($in['email'] ?? null, 'email'),
            'phone' => Validate::text($in['phone'] ?? null, 40),
            'address' => Validate::text($in['address'] ?? null, 200),
            'city' => Validate::text($in['city'] ?? null, 100),
            'notes' => Validate::text($in['notes'] ?? null, 5000),
        ];
    }
}
