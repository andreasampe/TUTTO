<?php
// Calendario in formato iCal (.ics) da "abbonare" sul Mac o sull'iPhone.
// L'indirizzo contiene un codice segreto personale: chi lo conosce vede le scadenze, quindi si può rigenerare.
declare(strict_types=1);

namespace Domus;

final class Ical
{
    public static function token(array $user, bool $regenerate = false): string
    {
        $key = 'ical_token_' . $user['id'];
        $t = Settings::get($key);
        if ($t === '' || $regenerate) {
            $t = bin2hex(random_bytes(20));
            Settings::set($key, $t);
        }
        return $t;
    }

    public static function feed(string $token): never
    {
        $row = $token !== '' ? Db::one("SELECT key FROM settings WHERE key LIKE 'ical_token_%' AND value = ?", [$token]) : null;
        if (!$row) {
            throw new HttpError(404, 'Calendario non trovato');
        }
        $user = Db::one('SELECT id, role FROM users WHERE id = ? AND active = 1', [(int) substr($row['key'], 11)]);
        if (!$user) {
            throw new HttpError(404, 'Calendario non trovato');
        }
        $events = Calendar::events($user, date('Y-m-d', strtotime('-60 days')), date('Y-m-d', strtotime('+365 days')));
        $studio = Settings::get('studio_name') ?: 'DOMUS';
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//DOMUS//IT', 'CALSCALE:GREGORIAN', 'X-WR-CALNAME:' . self::esc("DOMUS · $studio")];
        $label = ['task' => 'Scadenza', 'phase' => 'Fase', 'delivery' => 'Consegna', 'permit' => 'Pratica', 'payment' => 'Rata'];
        foreach ($events as $i => $e) {
            $day = str_replace('-', '', $e['date']);
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . md5($e['type'] . $e['date'] . $e['title'] . ($e['project_id'] ?? '')) . '@domus';
            $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
            $lines[] = "DTSTART;VALUE=DATE:$day";
            $lines[] = 'SUMMARY:' . self::esc(($e['code'] ? $e['code'] . ' · ' : '') . $e['title'] . ($e['done'] ? ' ✓' : ''));
            $lines[] = 'CATEGORIES:' . ($label[$e['type']] ?? 'DOMUS');
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="domus.ics"');
        echo implode("\r\n", $lines) . "\r\n";
        exit;
    }

    private static function esc(string $s): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\\,', '\\n'], $s);
    }
}
