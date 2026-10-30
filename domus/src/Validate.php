<?php
// Controlli sui dati inviati dai moduli. In caso di errore indicano il campo da correggere.
declare(strict_types=1);

namespace Domus;

final class Validate
{
    public static function text(mixed $v, int $max = 2000): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        return mb_substr($s, 0, $max);
    }

    public static function required(mixed $v, string $field, int $max = 200): string
    {
        $s = self::text($v, $max);
        if ($s === null) {
            throw new HttpError(422, 'Compila i campi obbligatori', [$field => 'Obbligatorio']);
        }
        return $s;
    }

    public static function email(mixed $v, string $field, bool $required = false): ?string
    {
        $s = self::text($v, 160);
        if ($s === null) {
            if ($required) {
                throw new HttpError(422, 'Compila i campi obbligatori', [$field => 'Obbligatorio']);
            }
            return null;
        }
        if (!filter_var($s, FILTER_VALIDATE_EMAIL)) {
            throw new HttpError(422, 'Indirizzo email non valido', [$field => 'Email non valida']);
        }
        return mb_strtolower($s);
    }

    // Data nel formato AAAA-MM-GG (quello dei campi data del browser).
    public static function date(mixed $v, string $field): ?string
    {
        $s = self::text($v, 10);
        if ($s === null) {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $s);
        if (!$d || $d->format('Y-m-d') !== $s) {
            throw new HttpError(422, 'Data non valida', [$field => 'Data non valida']);
        }
        return $s;
    }

    public static function money(mixed $v, string $field): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        // Accetta "1.250,50" (italiano) e "1250.50".
        $s = str_replace(['€', ' '], '', (string) $v);
        if (preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$/', $s) || str_contains($s, ',')) {
            $s = str_replace(['.', ','], ['', '.'], $s);
        }
        if (!is_numeric($s) || (float) $s < 0) {
            throw new HttpError(422, 'Importo non valido', [$field => 'Importo non valido']);
        }
        return round((float) $s, 2);
    }

    /** @param list<string> $allowed */
    public static function oneOf(mixed $v, array $allowed, string $field, ?string $default = null): string
    {
        $s = self::text($v, 40) ?? $default;
        if ($s === null || !in_array($s, $allowed, true)) {
            throw new HttpError(422, 'Valore non valido', [$field => 'Valore non valido']);
        }
        return $s;
    }

    public static function id(mixed $v, string $table, string $field, bool $required = false): ?int
    {
        if ($v === null || $v === '' || $v === 0 || $v === '0') {
            if ($required) {
                throw new HttpError(422, 'Compila i campi obbligatori', [$field => 'Obbligatorio']);
            }
            return null;
        }
        $id = filter_var($v, FILTER_VALIDATE_INT);
        if ($id === false || !Db::value("SELECT 1 FROM $table WHERE id = ?", [$id])) {
            throw new HttpError(422, 'Elemento collegato inesistente', [$field => 'Non trovato']);
        }
        return $id;
    }
}
