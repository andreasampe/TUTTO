<?php
// DOMUS — configurazione. È l'unico file da toccare quando si sposta l'app.
declare(strict_types=1);

return [
    // Nome mostrato nell'interfaccia (modificabile anche da Impostazioni).
    'app_name' => 'DOMUS',

    // Cartella dei dati (database e, in futuro, documenti). Deve stare FUORI dalla
    // cartella pubblica del sito, così nessuno può scaricarla dal browser.
    // Con Local: .../Local Sites/DOMUS/app/public/domus  →  dati in .../Local Sites/DOMUS/app/domus-data
    'data_dir' => getenv('DOMUS_DATA_DIR') ?: dirname(__DIR__, 2) . '/domus-data',

    // Durata della sessione di accesso, in secondi (12 ore).
    'session_lifetime' => 12 * 3600,

    // Fuso orario dello studio.
    'timezone' => 'Europe/Rome',
];
