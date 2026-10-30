<?php
// Dati dimostrativi: uno studio di architettura "vivo" per provare DOMUS.
// Si caricano solo se non esistono ancora progetti, così non si mescolano a dati reali.
// Le foto vengono da domus/demo/, i PDF sono generati qui (Pdf::simple).
declare(strict_types=1);

namespace Domus;

final class Demo
{
    public static function available(): bool
    {
        return (int) Db::value('SELECT COUNT(*) FROM projects') === 0;
    }

    /** @return array<string,int> */
    public static function seed(array $admin): array
    {
        if (!self::available()) {
            throw new HttpError(409, 'I dati dimostrativi si possono caricare solo con l\'archivio vuoto');
        }
        $d = static fn (int $days): string => date('Y-m-d', strtotime("$days days"));
        $year = date('Y');

        return Db::transaction(function () use ($admin, $d, $year) {
            // ---------- collaboratori ----------
            $team = [];
            foreach ([['Marco Lombardi', 'marco@studio.demo'], ['Elisa Parisi', 'elisa@studio.demo']] as [$name, $email]) {
                $team[] = (int) (Db::value('SELECT id FROM users WHERE email = ?', [$email]) ?: Db::insert('users', [
                    'name' => $name, 'email' => $email, 'role' => 'collaboratore',
                    'password_hash' => password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT),
                ]));
            }
            $people = [(int) $admin['id'], ...$team];

            // ---------- clienti ----------
            $clients = [];
            foreach ([
                ['Famiglia Bianchi', 'privato', 'BNCLRA78E41D612K', null, 'laura.bianchi@example.it', '333 445 1290', 'Via dei Serragli 42', 'Firenze'],
                ['Immobiliare Arno srl', 'azienda', null, '05814230487', 'tecnico@immobiliarearno.example', '055 210 4471', 'Lungarno Torrigiani 9', 'Firenze'],
                ['Comune di Fiesole', 'ente', null, '01252310485', 'llpp@comune.fiesole.example', '055 596 1200', 'Piazza Mino 24', 'Fiesole'],
                ['Giulia e Tommaso Neri', 'privato', 'NRETMS82C12G999X', null, 'neri.casa@example.it', '348 902 1180', 'Via di Ripoli 118', 'Bagno a Ripoli'],
                ['Cantina Le Querce', 'azienda', null, '06120380481', 'info@lequerce.example', '0577 740 332', 'Località Le Querce 3', 'Castellina in Chianti'],
                ['Andrea Galli', 'privato', 'GLLNDR75T08D612P', null, 'a.galli@example.it', '339 551 0072', 'Viale dei Mille 61', 'Firenze'],
            ] as [$name, $kind, $cf, $vat, $email, $phone, $addr, $city]) {
                $clients[] = Db::insert('clients', ['name' => $name, 'kind' => $kind, 'tax_code' => $cf, 'vat' => $vat, 'email' => $email, 'phone' => $phone, 'address' => $addr, 'city' => $city]);
            }

            // ---------- progetti ----------
            // [titolo, cliente, indirizzo, comune, intervento, stato, inizio, consegna, onorario, fasi completate, fase in corso, foto]
            $projects = [
                ['Villa sulle colline — ampliamento', 1, 'Via delle Forbici 12', 'Fiesole', 'Ampliamento', 'in_corso', -150, 120, 42000, 4, 4, ['villa-colline.jpg', 'modello-isometrico.jpg', 'villa-acqua.jpg']],
                ['Ristrutturazione appartamento via dei Serragli', 0, 'Via dei Serragli 42', 'Firenze', 'Ristrutturazione', 'in_corso', -70, 45, 18400, 3, 3, ['casa-wireframe.jpg', 'dettaglio-cemento.jpg']],
                ['Padiglione d\'ingresso — Cantina Le Querce', 4, 'Località Le Querce 3', 'Castellina in Chianti', 'Nuova costruzione', 'in_corso', -40, 200, 36500, 2, 2, ['padiglione-d.jpg', 'arco-cemento.jpg']],
                ['Biblioteca comunale — riqualificazione', 2, 'Via Portigiani 3', 'Fiesole', 'Restauro', 'preventivo', -10, 260, 58000, 0, 0, []],
                ['Casa unifamiliare a Bagno a Ripoli', 3, 'Via di Ripoli 118', 'Bagno a Ripoli', 'Nuova costruzione', 'in_corso', -25, 300, 31000, 1, 1, ['villa-acqua.jpg']],
                ['Loft ex opificio', 1, 'Via Pistoiese 210', 'Prato', 'Cambio di destinazione d\'uso', 'concluso', -420, -60, 24500, 8, -1, ['dettaglio-cemento.jpg']],
                ['Attico Viale dei Mille', 5, 'Viale dei Mille 61', 'Firenze', 'Interior design', 'sospeso', -90, 90, 9800, 2, -1, []],
            ];
            $phasesTpl = Settings::defaultPhases();
            $ids = [];
            foreach ($projects as $i => [$title, $ci, $addr, $city, $intervention, $status, $start, $due, $fee, $done, $current, $photos]) {
                $pid = Db::insert('projects', [
                    'code' => sprintf('%s-%03d', $year, $i + 1), 'title' => $title, 'client_id' => $clients[$ci],
                    'address' => $addr, 'city' => $city, 'intervention' => $intervention, 'status' => $status,
                    'start_date' => $d($start), 'due_date' => $d($due), 'fee' => $fee,
                    'cadastral' => sprintf('Foglio %d, Particella %d, Sub. %d', 40 + $i * 7, 120 + $i * 31, 2 + $i),
                    'notes' => $i === 0 ? "Vincolo paesaggistico: serve l'autorizzazione prima della SCIA.\nIl cliente desidera mantenere gli olivi esistenti sul fronte sud." : null,
                ]);
                $ids[] = $pid;
                foreach ($phasesTpl as $k => $name) {
                    $st = $k < $done ? 'completata' : ($k === $current ? 'in_corso' : 'da_fare');
                    Db::insert('phases', [
                        'project_id' => $pid, 'position' => $k + 1, 'name' => $name, 'status' => $st,
                        'completed_at' => $st === 'completata' ? $d($start + 12 * ($k + 1)) : null,
                        'due_date' => $k === $current ? $d(10 + 5 * $i) : null,
                    ]);
                }
                foreach ($photos as $n => $photo) {
                    self::addFile($pid, __DIR__ . '/../demo/' . $photo, ucfirst(str_replace('-', ' ', pathinfo($photo, PATHINFO_FILENAME))) . '.jpg', 'foto', (int) $admin['id']);
                }
                // documenti PDF generati
                if ($status !== 'preventivo') {
                    self::addPdf($pid, 'Relazione tecnica illustrativa.pdf', 'documento', $title, $city, (int) $admin['id'], [
                        'Oggetto: ' . $intervention . ' — ' . $title,
                        'Ubicazione: ' . $addr . ', ' . $city,
                        '',
                        'La presente relazione descrive l\'intervento in progetto, i materiali',
                        'previsti e la conformità alle norme urbanistiche ed edilizie vigenti.',
                        '',
                        '1. Stato di fatto',
                        '2. Descrizione dell\'intervento',
                        '3. Materiali e finiture: calcestruzzo a vista, legno di rovere, vetro',
                        '4. Rispetto dei requisiti energetici',
                    ]);
                    self::addPdf($pid, 'Pianta piano terra — stato di progetto.pdf', 'tavola', $title, $city, (int) $admin['id'], [
                        'Tavola A.01 — Pianta piano terra', 'Scala 1:100', '', 'Superficie utile: ' . (90 + $i * 35) . ' m²',
                    ], true);
                }
            }

            // ---------- copertine scelte ----------
            Db::run("UPDATE projects SET cover_file_id = (SELECT id FROM files f WHERE f.project_id = projects.id AND f.mime = 'image/jpeg' ORDER BY f.id LIMIT 1)");

            // ---------- scadenze ----------
            foreach ([
                [1, 'Depositare la CILA in Comune', -2], [1, 'Sopralluogo con l\'impresa edile', 3], [0, 'Inviare il computo metrico al cliente', 6],
                [0, 'Incontro in Soprintendenza', 12], [2, 'Rilievo topografico del lotto', 4], [4, 'Firmare l\'incarico professionale', 1],
                [2, 'Scelta dei campioni di calcestruzzo', 15], [null, 'Rinnovo polizza RC professionale', 9], [null, 'Corso di aggiornamento CFP', 25],
                [3, 'Consegna dello studio di fattibilità', 18],
            ] as [$pi, $title, $days]) {
                Db::insert('tasks', ['project_id' => $pi === null ? null : $ids[$pi], 'title' => $title, 'due_date' => $d($days)]);
            }
            Db::insert('tasks', ['project_id' => $ids[0], 'title' => 'Richiesta preventivi serramenti', 'due_date' => $d(-8), 'done' => 1, 'done_at' => date('Y-m-d H:i:s', strtotime('-9 days'))]);

            // ---------- diario ----------
            foreach ([
                [1, 'sopralluogo', -5, 'Rilievo completato. Il tramezzo tra cucina e soggiorno non è portante: confermato dallo strutturista.'],
                [1, 'telefonata', -1, 'La signora Bianchi preferisce il parquet in rovere naturale, posa a spina di pesce.'],
                [0, 'riunione', -3, 'Incontro con la proprietà: approvato l\'impianto planimetrico, da rivedere la copertura verso valle.'],
                [0, 'pratica', -20, 'Depositata la richiesta di autorizzazione paesaggistica. In attesa del parere della commissione.'],
                [2, 'sopralluogo', -6, 'Sopralluogo al lotto con l\'enologo: il padiglione deve inquadrare il filare principale.'],
                [4, 'email', -2, 'Inviate ai clienti tre varianti di distribuzione del piano terra.'],
            ] as [$pi, $kind, $days, $text]) {
                Db::insert('journal', ['project_id' => $ids[$pi], 'entry_date' => $d($days), 'kind' => $kind, 'text' => $text, 'user_id' => $people[$pi % 3]]);
            }

            // ---------- pratiche ----------
            foreach ([
                [0, 'Autorizzazione paesaggistica', 'integrazioni', 'Comune di Fiesole — Paesaggio', 'PG 2026/18822', -40, null, null],
                [0, 'SCIA', 'in_preparazione', 'Comune di Fiesole — SUE', null, null, null, null],
                [1, 'CILA', 'depositata', 'Comune di Firenze — SUE', 'PG 2026/04411', -2, null, null],
                [5, 'SCIA', 'approvata', 'Comune di Prato — SUE', 'PG 2025/77120', -380, -350, -60],
                [5, 'SCA (agibilità)', 'approvata', 'Comune di Prato — SUE', 'PG 2026/10442', -70, -45, null],
                [2, 'Permesso di costruire', 'depositata', 'Comune di Castellina in Chianti', 'PG 2026/3381', -15, null, 365],
            ] as [$pi, $kind, $status, $auth, $prot, $filed, $approved, $expires]) {
                Db::insert('permits', [
                    'project_id' => $ids[$pi], 'kind' => $kind, 'status' => $status, 'authority' => $auth, 'protocol' => $prot,
                    'filed_at' => $filed === null ? null : $d($filed), 'approved_at' => $approved === null ? null : $d($approved),
                    'expires_at' => $expires === null ? null : $d($expires),
                ]);
            }

            // ---------- economia ----------
            foreach ([
                [0, 'Acconto alla firma (20%)', 8400, -140, -138, -120], [0, 'Progetto definitivo (30%)', 12600, -20, -18, null],
                [0, 'Pratiche e progetto esecutivo (30%)', 12600, 60, null, null], [0, 'Saldo a fine lavori (20%)', 8400, 120, null, null],
                [1, 'Acconto alla firma', 5520, -65, -64, -50], [1, 'Progetto e CILA', 7360, -3, null, null], [1, 'Saldo a fine lavori', 5520, 45, null, null],
                [2, 'Acconto alla firma', 7300, -38, -37, -15], [2, 'Progetto definitivo', 14600, 40, null, null],
                [4, 'Acconto alla firma', 6200, -20, -20, -5],
                [5, 'Acconto', 7350, -410, -409, -390], [5, 'Stato avanzamento lavori', 9800, -200, -199, -170], [5, 'Saldo', 7350, -60, -58, -30],
            ] as [$pi, $desc, $amount, $due, $inv, $paid]) {
                Db::insert('payments', [
                    'project_id' => $ids[$pi], 'description' => $desc, 'amount' => $amount, 'due_date' => $d($due),
                    'invoiced_at' => $inv === null ? null : $d($inv), 'paid_at' => $paid === null ? null : $d($paid),
                    'invoice_no' => $inv === null ? null : sprintf('%d/%s', 10 + $pi * 3, substr($d($inv), 0, 4)),
                ]);
            }

            // ---------- ore: ultime 6 settimane ----------
            $works = ['Rilievo e restituzione grafica', 'Progetto — piante e sezioni', 'Modello 3D e render', 'Computo metrico', 'Pratica edilizia', 'Riunione con il cliente', 'Direzione lavori in cantiere'];
            $hours = 0;
            for ($day = -42; $day <= 0; $day++) {
                if (in_array((int) date('N', strtotime("$day days")), [6, 7], true)) {
                    continue;
                }
                foreach ($people as $k => $uid) {
                    $pi = ($day + $k * 2 + 50) % 5;
                    Db::insert('hours', ['project_id' => $ids[$pi], 'user_id' => $uid, 'work_date' => $d($day), 'hours' => [2, 3.5, 4, 2.5, 6][($day + $k + 60) % 5], 'description' => $works[($day + $k + 70) % count($works)]]);
                    $hours++;
                }
            }

            Settings::set('studio_name', Settings::get('studio_name') ?: 'Studio Pucci Architettura');
            return ['projects' => count($ids), 'clients' => count($clients), 'hours' => $hours];
        });
    }

    private static function addFile(int $projectId, string $source, string $name, string $category, int $userId): void
    {
        if (!is_file($source)) {
            return;
        }
        $dir = App::config('data_dir') . '/files/' . $projectId;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        copy($source, "$dir/$stored");
        Db::insert('files', [
            'project_id' => $projectId, 'name' => $name, 'stored' => $stored,
            'mime' => $ext === 'pdf' ? 'application/pdf' : 'image/jpeg', 'size' => filesize("$dir/$stored"),
            'category' => $category, 'user_id' => $userId,
        ]);
    }

    /** @param list<string> $lines */
    private static function addPdf(int $projectId, string $name, string $category, string $title, string $city, int $userId, array $lines, bool $drawing = false): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'domus') . '.pdf';
        file_put_contents($tmp, Pdf::simple(Settings::get('studio_name') ?: 'Studio di architettura', $title . ' — ' . $city, $lines, $drawing));
        self::addFile($projectId, $tmp, $name, $category, $userId);
        @unlink($tmp);
    }
}
