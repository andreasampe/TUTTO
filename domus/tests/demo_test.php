<?php
// Test dei dati dimostrativi, delle statistiche, della galleria e del calendario iCal (archivio vuoto).
declare(strict_types=1);

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8766', '/');
$jar = tempnam(sys_get_temp_dir(), 'domus');
$csrf = '';
$passed = $failed = 0;

function call(string $method, string $route, ?array $body = null): array
{
    global $base, $jar, $csrf;
    $ch = curl_init("$base/api.php?r=$route");
    $h = ['X-Requested-With: domus', "X-CSRF-Token: $csrf"];
    if ($body !== null) {
        $h[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $h, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    $raw = (string) curl_exec($ch);
    return [curl_getinfo($ch, CURLINFO_RESPONSE_CODE), json_decode($raw, true) ?? ['raw' => $raw]];
}
function check(string $name, bool $ok, mixed $detail = null): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  ✔ ' : '  ✘ ') . $name . "\n" . (!$ok && $detail !== null ? '    ' . json_encode($detail, JSON_UNESCAPED_UNICODE) . "\n" : '');
}

echo "DOMUS — test dati dimostrativi, statistiche, galleria, iCal\n";
[, $me] = call('GET', 'auth/me');
$csrf = $me['csrf'];
[, $r] = call('POST', 'auth/setup', ['name' => 'Chiara Pucci', 'email' => 'chiara@studio.it', 'password' => 'unapasswordlunga']);
$csrf = $r['csrf'];

[$s, $r] = call('GET', 'demo');
check('dati dimostrativi disponibili con archivio vuoto', $r['available'] === true);
[$s, $r] = call('POST', 'demo');
check('dati dimostrativi caricati', $s === 200 && $r['projects'] === 7 && $r['clients'] === 6 && $r['hours'] > 50, $r);
[$s, $r] = call('POST', 'demo');
check('non si caricano due volte', $s === 409);

[, $list] = call('GET', 'projects');
check('copertine solo per i progetti con foto', count($list) === 7 && count(array_filter($list, fn ($p) => $p['cover_id'])) === 5, array_column($list, 'cover_id'));
[, $p] = call('GET', "projects/{$list[count($list) - 1]['id']}");
$pdf = array_values(array_filter($p['files'], fn ($f) => $f['mime'] === 'application/pdf'))[0] ?? null;
check('PDF generati nel progetto', $pdf !== null, array_column($p['files'], 'name'));
$ch = curl_init("$base/api.php?r=files/{$pdf['id']}");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $jar]);
$body = (string) curl_exec($ch);
check('il PDF è valido', str_starts_with($body, '%PDF-1.4') && str_contains($body, '%%EOF') && str_contains($body, 'startxref'));

[$s] = call('PUT', "projects/{$list[0]['id']}/status", ['status' => 'concluso']);
[, $p0] = call('GET', "projects/{$list[0]['id']}");
check('cambio di stato dalla bacheca', $s === 200 && $p0['status'] === 'concluso' && $p0['title'] === $list[0]['title']);
[$s] = call('PUT', "projects/{$list[0]['id']}/status", ['status' => 'boh']);
check('stato non valido rifiutato', $s === 422);

$ch = curl_init("$base/api.php?r=projects/{$list[count($list) - 1]['id']}/quote");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $jar]);
$q = (string) curl_exec($ch);
check('preventivo PDF', curl_getinfo($ch, CURLINFO_CONTENT_TYPE) === 'application/pdf' && str_starts_with($q, '%PDF') && str_contains($q, 'Proposta di parcella'));

[$s, $rep] = call('GET', 'reports');
check('statistiche: 12 mesi e incassi', count($rep['months']) === 12 && array_sum($rep['paid']) > 0 && count($rep['hours_projects']) > 0, array_keys($rep));
check('statistiche: tempi delle pratiche', (int) $rep['permits']['approved'] === 2 && $rep['permits']['avg_days'] > 0, $rep['permits']);
[$s, $gal] = call('GET', 'gallery');
check('galleria con le foto dei progetti', count($gal) === 9, count($gal));

[$s, $t] = call('GET', 'ical');
$ch = curl_init("$base/api.php?r=calendar.ics&token={$t['token']}");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true]);
$ics = (string) curl_exec($ch);
check('calendario iCal leggibile senza sessione', str_starts_with($ics, 'BEGIN:VCALENDAR') && substr_count($ics, 'BEGIN:VEVENT') > 10, substr($ics, 0, 120));
$ch = curl_init("$base/api.php?r=calendar.ics&token=sbagliato");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true]);
curl_exec($ch);
check('iCal con codice sbagliato rifiutato', curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 404);
[, $t2] = call('POST', 'ical');
check('il codice iCal si rigenera', $t2['token'] !== $t['token']);
[, $set] = call('GET', 'settings');
check('i codici iCal non compaiono nelle impostazioni', !array_filter(array_keys($set), fn ($k) => str_starts_with($k, 'ical')));

echo "\n$passed superati, $failed falliti\n";
exit($failed ? 1 : 0);
