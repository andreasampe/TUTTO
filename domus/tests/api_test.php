<?php
// Test end-to-end dell'API di DOMUS. Si lancia con: bash tests/run.sh
declare(strict_types=1);

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8765', '/');
date_default_timezone_set($argv[2] ?? 'Europe/Rome'); // lo stesso fuso del server
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

// ================= fase 2 =================
function upload(int $projectId, array $files, string $category = 'documento'): array
{
    global $base, $jar, $csrf;
    $ch = curl_init("$base/api.php?r=projects/$projectId/files");
    $post = ['category' => $category];
    foreach ($files as $i => [$path, $name]) {
        $post["files[$i]"] = new CURLFile($path, '', $name);
    }
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['X-Requested-With: domus', "X-CSRF-Token: $csrf"], CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    $raw = (string) curl_exec($ch);
    return [curl_getinfo($ch, CURLINFO_RESPONSE_CODE), json_decode($raw, true)];
}
function download(int $id, bool $inline = false): array
{
    global $base, $jar;
    $ch = curl_init("$base/api.php?r=files/$id" . ($inline ? '&inline=1' : ''));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    $raw = (string) curl_exec($ch);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    return [curl_getinfo($ch, CURLINFO_RESPONSE_CODE), substr($raw, 0, $hs), substr($raw, $hs)];
}

[$s, $r] = call('POST', 'projects', ['title' => 'Casa sul lago', 'fee' => '20000', 'city' => 'Bracciano']);
$p2id = $r['id'];

// documenti
$tmp = sys_get_temp_dir();
file_put_contents("$tmp/pianta.pdf", "%PDF-1.4 finto");
file_put_contents("$tmp/foto.png", base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
file_put_contents("$tmp/virus.php", '<?php echo 1;');
[$s, $r] = upload($p2id, [["$tmp/pianta.pdf", 'Pianta piano terra.pdf'], ["$tmp/foto.png", 'Sopralluogo 1.png']], 'tavola');
check('caricamento di più documenti', $s === 200 && count($r['ids']) === 2, $r);
[$fid, $imgId] = $r['ids'];
[$s, $r] = upload($p2id, [["$tmp/virus.php", 'virus.php']]);
check('file .php rifiutato', $s === 422, $r);
[$s, $r] = upload($p2id, [["$tmp/pianta.pdf", '../../etc/passwd.pdf']]);
[$s, $p] = call('GET', "projects/$p2id");
check('nome con percorso ripulito', in_array('passwd.pdf', array_column($p['files'], 'name'), true), array_column($p['files'], 'name'));
check('anteprima solo per immagini', count(array_filter($p['files'], fn ($f) => $f['preview'])) === 1);
[$s, $h, $body] = download($fid);
check('download del documento', $s === 200 && $body === '%PDF-1.4 finto' && str_contains($h, 'attachment'), $h);
[$s, $h] = download($imgId, true);
check('immagine in anteprima (inline)', $s === 200 && str_contains($h, 'inline') && str_contains($h, 'image/png'));
[$s] = call('PUT', "files/$fid", ['category' => 'pratica', 'name' => 'Pianta PT']);
[$s, $p] = call('GET', "projects/$p2id");
$f = array_values(array_filter($p['files'], fn ($x) => $x['id'] === $fid))[0];
check('rinomina mantiene l\'estensione', $f['name'] === 'Pianta PT.pdf' && $f['category'] === 'pratica', $f);

// copertina
[$s, $list] = call('GET', 'projects&q=lago');
check('copertina automatica = prima immagine', $list[0]['cover_id'] === $imgId, $list[0]['cover_id'] ?? null);
[$s] = call('PUT', "projects/$p2id/cover", ['file_id' => $fid]);
check('un PDF non può essere copertina', $s === 422);
[$s] = call('PUT', "projects/$p2id/cover", ['file_id' => $imgId]);
[$s, $p] = call('GET', "projects/$p2id");
check('copertina scelta', $s === 200 && $p['cover_id'] === $imgId && $p['cover_file_id'] === $imgId);

// pratiche
[$s, $r] = call('POST', 'permits', ['project_id' => $p2id, 'kind' => 'CILA', 'status' => 'depositata', 'protocol' => '2026/1234', 'authority' => 'Comune di Bracciano']);
check('pratica creata', $s === 200);
$permitId = $r['id'];
[$s] = call('POST', 'permits', ['project_id' => $p2id, 'kind' => 'Inventata']);
check('tipo di pratica non valido', $s === 422);
[$s, $d] = call('GET', 'dashboard');
check('pratiche in attesa in panoramica', count($d['permits']) === 1 && $d['permits'][0]['filed_at'] === date('Y-m-d'), $d['permits']);
[$s] = call('PUT', "permits/$permitId", ['kind' => 'CILA', 'status' => 'approvata', 'protocol' => '2026/1234', 'filed_at' => date('Y-m-d')]);
[$s, $p] = call('GET', "projects/$p2id");
check('pratica approvata con data', $p['permits'][0]['status'] === 'approvata' && $p['permits'][0]['approved_at'] === date('Y-m-d'));

// economia
[$s] = call('POST', 'payments', ['project_id' => $p2id, 'description' => 'Acconto 30%', 'amount' => '6.000', 'due_date' => date('Y-m-d', strtotime('-10 days')), 'invoiced_at' => date('Y-m-d', strtotime('-9 days'))]);
[$s, $pay] = call('POST', 'payments', ['project_id' => $p2id, 'description' => 'Secondo acconto', 'amount' => '6000']);
[$s] = call('PUT', "payments/{$pay['id']}", ['paid_at' => date('Y-m-d')]);
[$s] = call('POST', 'payments', ['project_id' => $p2id, 'description' => 'Zero', 'amount' => '0']);
check('importo zero rifiutato', $s === 422);
[$s, $p] = call('GET', "projects/$p2id");
$sum = $p['payments']['summary'];
check('riepilogo economico', $sum['fee'] == 20000 && $sum['planned'] == 12000 && $sum['invoiced'] == 12000 && $sum['paid'] == 6000 && $sum['to_collect'] == 6000 && $sum['unplanned'] == 8000, $sum);
[$s, $d] = call('GET', 'dashboard');
check('da incassare in panoramica', $d['finance']['to_collect'] == 6000 && $d['finance']['paid_year'] == 6000, $d['finance']);

// ore
[$s] = call('POST', 'hours', ['project_id' => $p2id, 'hours' => '1:30', 'description' => 'Rilievo']);
[$s] = call('POST', 'hours', ['project_id' => $p2id, 'hours' => '2,5']);
[$s] = call('POST', 'hours', ['project_id' => $p2id, 'hours' => '30']);
check('ore oltre 24 rifiutate', $s === 422);
[$s, $p] = call('GET', "projects/$p2id");
check('totale ore', $p['hours']['total'] == 4.0, $p['hours']['total']);

// calendario e ricerca
call('POST', 'tasks', ['title' => 'Verifica lago', 'project_id' => $p2id, 'due_date' => date('Y-m-d')]);
[$s, $ev] = call('GET', 'calendar&from=' . date('Y-m-01') . '&to=' . date('Y-m-t'));
$types = array_unique(array_column($ev, 'type'));
check('calendario con scadenze, pratiche e rate', in_array('task', $types) && in_array('permit', $types) && in_array('payment', $types), $types);
[$s, $res] = call('GET', 'search&q=1234');
check('ricerca per protocollo', count($res) === 1 && $res[0]['type'] === 'pratica', $res);
[$s, $res] = call('GET', 'search&q=lago');
check('ricerca progetti', $res[0]['type'] === 'progetto' && $res[0]['title'] === 'Casa sul lago', $res);

// collaboratori e ruoli
[$s, $r] = call('POST', 'users', ['name' => 'Luca Verdi', 'email' => 'luca@studio.it', 'password' => 'passwordluca1', 'role' => 'collaboratore']);
check('collaboratore creato', $s === 200, $r);
$lucaId = $r['id'];
[$s] = call('POST', 'users', ['name' => 'Doppio', 'email' => 'LUCA@studio.it', 'password' => 'passwordluca1']);
check('email duplicata rifiutata', $s === 422);
[$s, $list] = call('GET', 'users');
check('elenco utenti', count($list) === 2);
$adminId = array_values(array_filter($list, fn ($u) => $u['role'] === 'admin'))[0]['id'];
[$s, $r] = call('PUT', "users/$adminId", ['role' => 'collaboratore']);
check('non si può togliere l\'ultimo amministratore', $s === 422, $r);

// entra come collaboratore
$adminCsrf = $csrf;
call('POST', 'auth/logout');
[$s, $r] = call('POST', 'auth/login', ['email' => 'luca@studio.it', 'password' => 'passwordluca1']);
$csrf = $r['csrf'] ?? '';
check('accesso collaboratore', $s === 200 && $r['user']['role'] === 'collaboratore');
[$s, $p] = call('GET', "projects/$p2id");
check('il collaboratore non vede l\'economia', !isset($p['payments']) && !array_key_exists('fee', $p), array_keys($p));
[$s] = call('POST', 'payments', ['project_id' => $p2id, 'description' => 'X', 'amount' => '10']);
check('il collaboratore non crea rate (403)', $s === 403);
[$s] = call('GET', 'users');
check('il collaboratore non vede gli utenti (403)', $s === 403);
[$s] = call('GET', 'export');
check('il collaboratore non scarica il backup (403)', $s === 403);
[$s, $d] = call('GET', 'dashboard');
check('panoramica senza economia', $d['finance'] === null);
[$s] = call('PUT', "projects/$p2id", ['title' => 'Casa sul lago (rev.)', 'city' => 'Bracciano', 'fee' => '1']);
[$s, $p] = call('GET', "projects/$p2id");
check('il collaboratore modifica il progetto', $p['title'] === 'Casa sul lago (rev.)');
[$s, $hid] = call('POST', 'hours', ['project_id' => $p2id, 'hours' => '3']);
[$s, $p] = call('GET', "projects/$p2id");
$adminHour = array_values(array_filter($p['hours']['items'], fn ($x) => $x['user_name'] === 'Chiara Pucci'))[0]['id'];
[$s] = call('DELETE', "hours/$adminHour");
check('il collaboratore non elimina le ore altrui', $s === 403);
[$s] = call('DELETE', "hours/{$hid['id']}");
check('il collaboratore elimina le proprie ore', $s === 200);

// torna amministratore: l'onorario non è stato toccato dal collaboratore
call('POST', 'auth/logout');
[$s, $r] = call('POST', 'auth/login', ['email' => 'chiara@studio.it', 'password' => 'unapasswordlunga']);
$csrf = $r['csrf'] ?? '';
[$s, $p] = call('GET', "projects/$p2id");
check('onorario protetto dalle modifiche del collaboratore', $p['fee'] == 20000, $p['fee'] ?? null);
[$s] = call('PUT', "users/$lucaId", ['active' => false]);
call('POST', 'auth/logout');
[$s] = call('POST', 'auth/login', ['email' => 'luca@studio.it', 'password' => 'passwordluca1']);
check('utente disattivato non entra', $s === 401);
[$s, $r] = call('POST', 'auth/login', ['email' => 'chiara@studio.it', 'password' => 'unapasswordlunga']);
$csrf = $r['csrf'] ?? '';

// eliminando il progetto spariscono anche i file
[$s] = call('DELETE', "projects/$p2id");
[$s] = download($fid);
check('file eliminati con il progetto', $s === 404);

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
