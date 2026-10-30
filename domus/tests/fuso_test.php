<?php
// Test del fuso orario: "oggi" deve essere quello dello studio (config.php), non quello UTC di SQLite.
// run.sh avvia il server con TZ=UTC e con DOMUS_TIMEZONE scelto in modo che, in questo momento,
// la data dello studio sia diversa da quella UTC: è la situazione di Roma tra mezzanotte e l'1 (le 2 d'estate).
declare(strict_types=1);

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8767', '/');
date_default_timezone_set($argv[2] ?? 'Europe/Rome');
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

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
echo "DOMUS — test del fuso orario (" . date_default_timezone_get() . ": $today, UTC: " . gmdate('Y-m-d') . ")\n";
check('la data dello studio è diversa da quella UTC (altrimenti il test non prova nulla)', $today !== gmdate('Y-m-d'));

[, $me] = call('GET', 'auth/me');
$csrf = $me['csrf'];
[, $r] = call('POST', 'auth/setup', ['name' => 'Chiara Pucci', 'email' => 'chiara@studio.it', 'password' => 'unapasswordlunga']);
$csrf = $r['csrf'];

[, $r] = call('POST', 'projects', ['title' => 'Casa sul fuso']);
$pid = $r['id'];
call('POST', 'tasks', ['title' => 'Scaduta ieri', 'project_id' => $pid, 'due_date' => $yesterday]);
call('POST', 'tasks', ['title' => 'Scade oggi', 'project_id' => $pid, 'due_date' => $today]);

[, $over] = call('GET', 'tasks&filter=scadute');
check('scadenza di ieri tra le superate, quella di oggi no', array_column($over, 'title') === ['Scaduta ieri'], $over);
[, $d] = call('GET', 'dashboard');
check('panoramica: superate', array_column($d['overdue'], 'title') === ['Scaduta ieri'], $d['overdue']);
check('panoramica: in arrivo comprende oggi', array_column($d['upcoming'], 'title') === ['Scade oggi'], $d['upcoming']);

call('POST', 'permits', ['project_id' => $pid, 'kind' => 'CILA', 'status' => 'depositata']);
call('POST', 'hours', ['project_id' => $pid, 'hours' => 2]);
call('POST', 'payments', ['project_id' => $pid, 'description' => 'Acconto', 'amount' => 1000, 'due_date' => $today]);
[, $d] = call('GET', 'dashboard');
check('pratica depositata oggi: 0 giorni di attesa', ($d['permits'][0]['days_waiting'] ?? null) === 0 && $d['permits'][0]['filed_at'] === $today, $d['permits']);
check('ore di oggi nella settimana', (float) $d['hours_week'] === 2.0, $d['hours_week']);
check('rata che scade oggi da fatturare', (float) $d['finance']['to_invoice'] === 1000.0, $d['finance']);

[, $p] = call('GET', "projects/$pid");
check('progetto creato oggi (created_at nel fuso dello studio)', str_starts_with($p['created_at'], $today), $p['created_at']);
call('PUT', "phases/{$p['phases'][0]['id']}", ['status' => 'in_corso']);
[, $p] = call('GET', "projects/$pid");
check('modifica di una fase: updated_at nel fuso dello studio', str_starts_with($p['updated_at'], $today), $p['updated_at']);

echo "\n$passed superati, $failed falliti\n";
exit($failed ? 1 : 0);
