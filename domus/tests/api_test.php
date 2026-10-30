<?php
// Test end-to-end dell'API di DOMUS. Si lancia con: bash tests/run.sh
declare(strict_types=1);

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8765', '/');
$jar = tempnam(sys_get_temp_dir(), 'domus');
$csrf = '';
$passed = 0;
$failed = 0;

function call(string $method, string $route, ?array $body = null, array $headers = [], bool $withCsrf = true): array
{
    global $base, $jar, $csrf;
    $ch = curl_init("$base/api.php?r=$route");
    $h = ['X-Requested-With: domus', ...$headers];
    if ($withCsrf && $method !== 'GET') {
        $h[] = "X-CSRF-Token: $csrf";
    }
    if ($body !== null) {
        $h[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $h,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
    ]);
    $raw = (string) curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    return [$status, json_decode($raw, true) ?? ['raw' => $raw]];
}

function check(string $name, bool $ok, mixed $detail = null): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  ✔ $name\n";
    } else {
        $failed++;
        echo "  ✘ $name\n" . ($detail !== null ? '    ' . json_encode($detail, JSON_UNESCAPED_UNICODE) . "\n" : '');
    }
}

echo "DOMUS — test dell'API\n";

// --- primo avvio ---
[$s, $me] = call('GET', 'auth/me');
check('primo avvio: serve la configurazione', $s === 200 && $me['needs_setup'] === true && $me['user'] === null, $me);
$csrf = $me['csrf'];

[$s] = call('GET', 'dashboard');
check('senza accesso i dati sono protetti (401)', $s === 401);

[$s, $r] = call('POST', 'auth/setup', ['name' => 'Chiara', 'email' => 'chiara@studio.it', 'password' => 'corta']);
check('password troppo corta rifiutata', $s === 422 && isset($r['fields']['password']), $r);

// senza X-Requested-With: simuliamo un modulo inviato da un altro sito
$ch = curl_init("$base/api.php?r=auth/setup");
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => '{}', CURLOPT_RETURNTRANSFER => true]);
curl_exec($ch);
check('setup da un altro sito bloccato (403)', curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 403);

[$s, $r] = call('POST', 'auth/setup', ['name' => 'Chiara Pucci', 'email' => 'Chiara@Studio.it', 'password' => 'unapasswordlunga', 'studio_name' => 'Studio Pucci']);
check('creazione amministratore', $s === 200 && $r['user']['email'] === 'chiara@studio.it', $r);
$csrf = $r['csrf'];

[$s] = call('POST', 'auth/setup', ['name' => 'Altro', 'email' => 'a@b.it', 'password' => 'unapasswordlunga']);
check('il setup non si può ripetere', $s === 409);

// --- CSRF ---
[$s] = call('POST', 'clients', ['name' => 'Senza token'], [], false);
check('modifica senza token CSRF rifiutata (403)', $s === 403);

// --- clienti ---
[$s, $r] = call('POST', 'clients', ['name' => '', 'email' => 'non-una-email']);
check('cliente: errori per campo', $s === 422 && isset($r['fields']['name']), $r);
[$s, $r] = call('POST', 'clients', ['name' => 'Mario Rossi', 'kind' => 'privato', 'tax_code' => 'rssmra80a01h501u', 'phone' => '333 1234567', 'city' => 'Firenze']);
check('cliente creato', $s === 200 && $r['id'] > 0, $r);
$clientId = $r['id'];
[$s, $r] = call('GET', "clients/$clientId");
check('codice fiscale in maiuscolo', $r['tax_code'] === 'RSSMRA80A01H501U', $r);
[$s, $r] = call('GET', 'clients&q=firen');
check('ricerca clienti', count($r) === 1);

// --- progetti ---
[$s, $r] = call('GET', 'projects/next-code');
$year = date('Y');
check('codice progressivo', $r['code'] === "$year-001", $r);

[$s, $r] = call('POST', 'projects', ['title' => 'Ristrutturazione via Roma', 'client_id' => $clientId, 'city' => 'Firenze', 'fee' => '12.500,50', 'due_date' => '2026-02-30']);
check('data inesistente rifiutata', $s === 422 && isset($r['fields']['due_date']), $r);

[$s, $r] = call('POST', 'projects', ['title' => 'Ristrutturazione via Roma', 'client_id' => $clientId, 'city' => 'Firenze', 'fee' => '12.500,50', 'cadastral' => 'Fg. 12 Part. 345 Sub. 6', 'intervention' => 'Ristrutturazione']);
check('progetto creato', $s === 200 && $r['id'] > 0, $r);
$pid = $r['id'];

[$s, $p] = call('GET', "projects/$pid");
check('fasi standard aggiunte', count($p['phases']) === 8 && $p['phases'][0]['name'] === 'Sopralluogo e rilievo', $p['phases'] ?? $p);
check('onorario in formato italiano', abs($p['fee'] - 12500.50) < 0.001, $p['fee']);
check('codice assegnato', $p['code'] === "$year-001");

[$s, $r] = call('POST', 'projects', ['title' => 'Secondo', 'code' => "$year-001"]);
check('codice duplicato rifiutato', $s === 422 && isset($r['fields']['code']), $r);
[$s, $r] = call('POST', 'projects', ['title' => 'Villa al mare', 'status' => 'preventivo']);
check('secondo progetto: codice successivo', $s === 200);
[$s, $p2] = call('GET', "projects/{$r['id']}");
check('codice 002', $p2['code'] === "$year-002", $p2['code']);

[$s] = call('POST', 'projects', ['title' => 'Cliente inesistente', 'client_id' => 9999]);
check('cliente collegato inesistente rifiutato', $s === 422);

// fasi
$f1 = $p['phases'][0]['id'];
[$s] = call('PUT', "phases/$f1", ['status' => 'completata']);
[$s, $p] = call('GET', "projects/$pid");
check('fase completata con data', $p['phases'][0]['status'] === 'completata' && $p['phases'][0]['completed_at'] === date('Y-m-d'), $p['phases'][0]);
[$s] = call('PUT', "phases/{$p['phases'][1]['id']}", ['status' => 'in_corso']);
[$s] = call('PUT', "phases/$f1", ['status' => 'boh']);
check('stato fase non valido rifiutato', $s === 422);
[$s, $r] = call('POST', "projects/$pid/phases", ['name' => 'Variante in corso d\'opera']);
check('fase aggiunta', $s === 200);
[$s, $list] = call('GET', 'projects&status=attivi');
$row = array_values(array_filter($list, fn ($x) => $x['id'] === $pid))[0] ?? null;
check('elenco: avanzamento e fase attuale', $row && (int) $row['phases_done'] === 1 && (int) $row['phases_total'] === 9 && $row['current_phase'] === 'Studio di fattibilità', $row);
[$s, $list] = call('GET', 'projects&q=villa');
check('ricerca progetti', count($list) === 1 && $list[0]['title'] === 'Villa al mare');

// scadenze
$yesterday = date('Y-m-d', strtotime('-1 day'));
$soon = date('Y-m-d', strtotime('+3 days'));
[$s, $t1] = call('POST', 'tasks', ['title' => 'Depositare CILA', 'project_id' => $pid, 'due_date' => $yesterday]);
[$s, $t2] = call('POST', 'tasks', ['title' => 'Sopralluogo impresa', 'project_id' => $pid, 'due_date' => $soon]);
[$s, $t3] = call('POST', 'tasks', ['title' => 'Rinnovo assicurazione studio', 'due_date' => $soon]);
check('scadenze create', $s === 200 && $t3['id'] > 0);
[$s, $over] = call('GET', 'tasks&filter=scadute');
check('scadenze superate', count($over) === 1 && $over[0]['title'] === 'Depositare CILA', $over);
[$s, $week] = call('GET', 'tasks&filter=settimana');
check('entro 7 giorni', count($week) === 3);
[$s] = call('PUT', "tasks/{$t1['id']}", ['done' => true]);
[$s, $over] = call('GET', 'tasks&filter=scadute');
check('scadenza fatta non è più superata', count($over) === 0);
[$s, $done] = call('GET', 'tasks&filter=fatte');
check('scadenze fatte', count($done) === 1 && $done[0]['done_at'] !== null);

// diario
[$s, $j] = call('POST', 'journal', ['project_id' => $pid, 'kind' => 'sopralluogo', 'text' => "Rilievo completato.\nMisure del bagno da ricontrollare."]);
check('nota di diario', $s === 200);
[$s] = call('POST', 'journal', ['project_id' => $pid, 'text' => '']);
check('nota vuota rifiutata', $s === 422);
[$s, $p] = call('GET', "projects/$pid");
check('diario con autore', count($p['journal']) === 1 && $p['journal'][0]['user_name'] === 'Chiara Pucci', $p['journal']);

// panoramica
[$s, $d] = call('GET', 'dashboard');
check('panoramica', $s === 200 && $d['counts']['in_corso'] === 1 && $d['counts']['preventivo'] === 1 && count($d['upcoming']) === 2 && count($d['journal']) === 1, $d['counts'] ?? $d);

// impostazioni
[$s, $set] = call('PUT', 'settings', ['studio_name' => 'Studio Pucci Architettura', 'default_phases' => "Rilievo\n\nProgetto\nCantiere"]);
check('impostazioni salvate', $set['studio_name'] === 'Studio Pucci Architettura' && $set['default_phases'] === "Rilievo\nProgetto\nCantiere", $set);
[$s, $r] = call('POST', 'projects', ['title' => 'Con fasi nuove']);
[$s, $p3] = call('GET', "projects/{$r['id']}");
check('nuovo progetto usa le fasi personalizzate', array_column($p3['phases'], 'name') === ['Rilievo', 'Progetto', 'Cantiere'], array_column($p3['phases'], 'name'));

// backup
[$s, $b] = call('GET', 'export');
check('backup JSON completo', $b['app'] === 'DOMUS' && count($b['projects']) === 3 && count($b['clients']) === 1, array_keys($b));
check('backup senza password', !isset($b['users'][0]['password_hash']));

// eliminazioni
[$s] = call('DELETE', "clients/$clientId");
[$s, $p] = call('GET', "projects/$pid");
check('eliminare un cliente lascia il progetto', $s === 200 && $p['client_id'] === null, $p['client_id'] ?? $p);
[$s] = call('DELETE', "projects/$pid");
[$s] = call('GET', "projects/$pid");
check('progetto eliminato', $s === 404);
[$s, $all] = call('GET', 'tasks&filter=tutte');
check('le scadenze del progetto spariscono con lui', count($all) === 1 && $all[0]['title'] === 'Rinnovo assicurazione studio', $all);

// profilo
[$s, $r] = call('PUT', 'auth/profile', ['name' => 'Chiara P.', 'current_password' => 'sbagliata', 'new_password' => 'nuovapassword123']);
check('cambio password con password attuale errata rifiutato', $s === 422);
[$s, $r] = call('PUT', 'auth/profile', ['name' => 'Chiara P.', 'current_password' => 'unapasswordlunga', 'new_password' => 'nuovapassword123']);
check('cambio password', $s === 200 && $r['user']['name'] === 'Chiara P.');

// accesso
[$s] = call('POST', 'auth/logout');
[$s] = call('GET', 'dashboard');
check('dopo l\'uscita i dati sono protetti', $s === 401);
[$s, $r] = call('POST', 'auth/login', ['email' => 'chiara@studio.it', 'password' => 'unapasswordlunga']);
check('vecchia password non vale più', $s === 401);
[$s, $r] = call('POST', 'auth/login', ['email' => 'CHIARA@studio.it', 'password' => 'nuovapassword123']);
check('accesso (email senza distinzione maiuscole)', $s === 200, $r);
$csrf = $r['csrf'] ?? '';
[$s] = call('GET', 'dashboard');
check('dopo l\'accesso i dati sono visibili', $s === 200);

// blocco dopo troppi tentativi
call('POST', 'auth/logout');
$codes = [];
for ($i = 0; $i < 9; $i++) {
    [$codes[]] = call('POST', 'auth/login', ['email' => 'chiara@studio.it', 'password' => 'tentativo']);
}
check('blocco dopo troppi tentativi (429)', end($codes) === 429, $codes);

[$s] = call('GET', 'nonesiste');
check('percorso inesistente', $s === 401 || $s === 404);

// file interni non scaricabili dal browser
$ch = curl_init("$base/../domus-data/domus.sqlite");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_exec($ch);
check('database non raggiungibile dal browser', curl_getinfo($ch, CURLINFO_RESPONSE_CODE) !== 200);

echo "\n$passed superati, $failed falliti\n";
exit($failed ? 1 : 0);
