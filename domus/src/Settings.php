<?php
// Impostazioni dello studio (chiave/valore).
declare(strict_types=1);

namespace Domus;

final class Settings
{
    private const DEFAULTS = [
        'studio_name' => '',
        'cad_profile' => '',
        // Fasi proposte per ogni nuovo progetto, una per riga. Modificabili da Impostazioni.
        'default_phases' => "Sopralluogo e rilievo\nStudio di fattibilità\nProgetto preliminare\nProgetto definitivo\nPratica edilizia\nProgetto esecutivo\nDirezione lavori\nFine lavori e agibilità",
    ];

    public static function get(string $key): string
    {
        $v = Db::value('SELECT value FROM settings WHERE key = ?', [$key]);
        return $v === false ? (self::DEFAULTS[$key] ?? '') : (string) $v;
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        $out = self::DEFAULTS;
        foreach (Db::all("SELECT key, value FROM settings WHERE key NOT LIKE 'ical_token_%'") as $r) {
            $out[$r['key']] = (string) $r['value'];
        }
        return $out;
    }

    public static function set(string $key, ?string $value): void
    {
        Db::run(
            'INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value',
            [$key, $value ?? '']
        );
    }

    /** @return array<string,string> */
    public static function save(array $in): array
    {
        if (array_key_exists('studio_name', $in)) {
            self::set('studio_name', Validate::text($in['studio_name'], 120));
        }
        // Profilo CAD dello studio, ricavato nel browser dal suo file di riferimento (cad/domus_cad/profilo.py).
        if (array_key_exists('cad_profile', $in)) {
            $json = (string) $in['cad_profile'];
            $p = json_decode($json, true);
            if ($json !== '' && (strlen($json) > 200000 || !is_array($p) || !isset($p['layer_quote']['name'], $p['stile_quote']['name']))) {
                throw new HttpError(422, 'Profilo CAD non valido');
            }
            self::set('cad_profile', $json);
        }
        if (array_key_exists('default_phases', $in)) {
            $lines = array_values(array_filter(array_map(
                fn ($l) => mb_substr(trim($l), 0, 120),
                preg_split('/\R/', (string) $in['default_phases'])
            )));
            if (!$lines) {
                throw new HttpError(422, 'Indica almeno una fase', ['default_phases' => 'Almeno una fase']);
            }
            self::set('default_phases', implode("\n", array_slice($lines, 0, 30)));
        }
        return self::all();
    }

    /** @return list<string> */
    public static function defaultPhases(): array
    {
        return array_values(array_filter(preg_split('/\R/', self::get('default_phases'))));
    }
}
