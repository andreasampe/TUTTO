<?php
// Collaboratori dello studio. Solo l'amministratore li gestisce.
// Ruoli: "admin" (tutto) e "collaboratore" (niente economia, utenti e impostazioni).
declare(strict_types=1);

namespace Domus;

final class Users
{
    public const ROLES = ['admin', 'collaboratore'];

    /** @return list<array<string,mixed>> */
    public static function list(): array
    {
        return Db::all(
            "SELECT u.id, u.name, u.email, u.role, u.active, u.created_at,
                    (SELECT COALESCE(SUM(h.hours), 0) FROM hours h WHERE h.user_id = u.id
                       AND h.work_date >= date('now','localtime','start of month')) AS hours_month
             FROM users u ORDER BY u.active DESC, u.name COLLATE NOCASE"
        );
    }

    public static function create(array $in): int
    {
        $email = Validate::email($in['email'] ?? '', 'email', required: true);
        if (Db::value('SELECT 1 FROM users WHERE email = ?', [$email])) {
            throw new HttpError(422, 'Esiste già un utente con questa email', ['email' => 'Già usata']);
        }
        $password = (string) ($in['password'] ?? '');
        if (mb_strlen($password) < 10) {
            throw new HttpError(422, 'La password deve avere almeno 10 caratteri', ['password' => 'Almeno 10 caratteri']);
        }
        return Db::insert('users', [
            'name' => Validate::required($in['name'] ?? '', 'name', 80),
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => Validate::oneOf($in['role'] ?? null, self::ROLES, 'role', 'collaboratore'),
        ]);
    }

    public static function update(array $me, int $id, array $in): void
    {
        $user = Db::one('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$user) {
            throw new HttpError(404, 'Utente non trovato');
        }
        $data = [];
        if (array_key_exists('name', $in)) {
            $data['name'] = Validate::required($in['name'], 'name', 80);
        }
        if (array_key_exists('role', $in)) {
            $data['role'] = Validate::oneOf($in['role'], self::ROLES, 'role');
        }
        if (array_key_exists('active', $in)) {
            $data['active'] = filter_var($in['active'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }
        if (!empty($in['password'])) {
            if (mb_strlen((string) $in['password']) < 10) {
                throw new HttpError(422, 'La password deve avere almeno 10 caratteri', ['password' => 'Almeno 10 caratteri']);
            }
            $data['password_hash'] = password_hash((string) $in['password'], PASSWORD_DEFAULT);
        }
        // Lo studio non deve mai restare senza un amministratore attivo.
        $losesAdmin = (isset($data['role']) && $data['role'] !== 'admin') || (isset($data['active']) && !$data['active']);
        if ($losesAdmin && $user['role'] === 'admin' && (int) Db::value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1 AND id != ?", [$id]) === 0) {
            throw new HttpError(422, 'Deve restare almeno un amministratore attivo');
        }
        if ($id === (int) $me['id'] && isset($data['active']) && !$data['active']) {
            throw new HttpError(422, 'Non puoi disattivare il tuo account');
        }
        Db::update('users', $id, $data);
    }
}
