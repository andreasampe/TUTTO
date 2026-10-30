# Contesto per Claude — repository TUTTO

Leggi questo file all'inizio di ogni sessione. Contiene chi è l'utente, come vuole lavorare e lo stato dei progetti.

## L'utente

- Si chiama Andrea, scrive in **italiano**: rispondi sempre in italiano.
- Lavora su **Mac** con **Local** (di WP Engine). I siti stanno in `/Users/andreagiacometti/Local Sites/` (il percorso ha uno spazio: nei comandi va tra virgolette).
- Cartelle in `Local Sites`: `www.sterzetto.it`, `www.minulaboutique.com`, `www.chiarapucciarch.it` (siti WordPress), `DOMUS` (webapp da costruire da zero) e `_VARIO` (plugin, skill e materiale utile).
- Il documento di riferimento generale è *Documento_unico_progetti_e_ambiente_digitale.docx*. Nel documento, **TOTUM** = area privata di chiarapucciarch.it.
- Da questa sessione cloud **non si può accedere al suo Mac**: il codice passa da GitHub (questo repository) e lui lo copia in Local.

### Come vuole essere guidato

Indicazioni sue, dal documento master:
- **un passo alla volta**: spiega brevemente, poi di' esattamente dove cliccare o quale comando eseguire;
- comandi da Terminale **completi e pronti da copiare**;
- **aspetta il suo risultato** prima del passo successivo, soprattutto durante installazioni e configurazioni;
- niente liste di dieci operazioni tutte insieme, niente spiegazioni troppo tecniche;
- non fargli ripetere operazioni già fatte e non toccare configurazioni che funzionano;
- un'attività è completata solo dopo una verifica concreta;
- **mai comandi che cancellano** (`rm -rf`…) senza spiegarlo chiaramente prima. Una volta si è spaventato.

### Preferenze tecniche

- **Non vuole appoggiarsi ai database dell'hosting** (MySQL): usare SQLite su file oppure file JSON.
- App **autonome e trasferibili**, con il minimo di dipendenze tra un progetto e l'altro.
- Estetica molto curata.

## DOMUS (cartella `domus/`)

Webapp gestionale per lo studio di architettura (Chiara Pucci, architetto). Leggi `domus/README.md` per installazione e uso.

### Architettura

- **PHP 8.1+ senza framework né Composer**, autonomo (non è un plugin WordPress). In Local sta in `Local Sites/DOMUS/app/public/domus/` e si apre su `http://domus.local/domus/`.
- **Dati**: SQLite in `domus-data/domus.sqlite`, fuori dalla cartella pubblica (percorso in `config.php`, sovrascrivibile con la variabile d'ambiente `DOMUS_DATA_DIR`). Esiste un export JSON completo (`api.php?r=export`).
- **Migrazioni**: `src/Db.php`, costante `MIGRATIONS`. Usano `PRAGMA user_version`. Non modificare le migrazioni esistenti, aggiungerne di nuove in coda.
- **API**: unico punto d'ingresso `api.php?r=risorsa/id`. Si usa il parametro `r` per non dipendere da regole di riscrittura degli URL (Local usa nginx). Le rotte sono nel `match` di `api.php`.
- **Logica**: una classe per area in `src/` (`Auth`, `Clients`, `Projects` con fasi e diario, `Tasks`, `Dashboard`, `Settings`, `Backup`, `Validate`). Gli errori sono `HttpError(status, messaggio, [campo => errore])`: l'interfaccia evidenzia il campo sbagliato.
- **Sicurezza**:
  - sessione con cookie `HttpOnly` e `SameSite=Strict`;
  - token CSRF nell'intestazione `X-CSRF-Token` per ogni modifica;
  - login e setup accettati solo con `X-Requested-With: domus`;
  - password con `password_hash` (minimo 10 caratteri);
  - blocco dopo 8 tentativi falliti in 15 minuti;
  - Content-Security-Policy rigida (niente script inline né risorse esterne).
- **Interfaccia**: pagina unica senza build.
  - `index.php` è lo scheletro HTML.
  - `assets/js/core.js` contiene l'oggetto globale `D`: API, `D.h()` per creare elementi (sempre testo, mai `innerHTML` con dati), router a hash, modali, moduli con errori per campo.
  - `assets/js/views.js` contiene le pagine, registrate con `D.route(regex, view)`.
  - `assets/css/domus.css` contiene lo stile.
- **Stile** (preso dall'icona `assets/icon.svg`):
  - bianco e nero da tavola tecnica, linee sottili, griglia di costruzione leggera sullo sfondo, nessun colore d'accento;
  - colori come variabili CSS in `:root`, con tema scuro automatico (`prefers-color-scheme`);
  - titoli delle schede in maiuscoletto spaziato.
  - Se l'utente fornisce il file originale dell'icona, sostituire `icon.svg` mantenendo il nome o aggiornando `index.php`.

### Dati gestiti (fase 1, completata)

- **Clienti**: privato / azienda / ente, codice fiscale, partita IVA, contatti.
- **Progetti**:
  - codice progressivo annuale `AAAA-NNN`;
  - stato: preventivo, in corso, sospeso, concluso, archiviato;
  - indirizzo, comune, dati catastali, tipo di intervento, date, onorario;
  - **fasi**: create da un elenco standard modificabile in Impostazioni; stati da fare, in corso, completata, non prevista;
  - **scadenze**: anche generali di studio, senza progetto;
  - **diario di commessa**: nota, sopralluogo, riunione, telefonata, email, pratica.

### Come provare

- `bash domus/tests/run.sh` avvia il server PHP integrato su un database temporaneo ed esegue i test dell'API in `tests/api_test.php` (51 controlli, tutti verdi alla consegna).
- Per l'interfaccia: `php -S` su una cartella che contiene `domus/` (per simulare il percorso `/domus/` di Local) e Playwright (preinstallato nell'ambiente cloud).
- In questo ambiente cloud **non si può avviare WordPress** (il codice esterno è bloccato) e **non c'è accesso ai siti esterni**.

### Prossimi passi possibili (da concordare con l'utente)

1. **Documenti e allegati** per progetto (tavole, foto dei sopralluoghi, PDF), salvati in `domus-data/files/` e serviti solo dopo il login.
2. **Pratiche edilizie** (CILA, SCIA, Permesso di costruire): tipo, protocollo, date di deposito e rilascio.
3. **Preventivi e parcelle**: voci, acconti, incassi; stato dei pagamenti per progetto.
4. **Ore lavorate** per progetto e collaboratore.
5. **Collaboratori** con ruoli (oggi esiste solo l'amministratore creato al primo avvio).
6. **Calendario** di scadenze e sopralluoghi; stampa/PDF della scheda progetto.

## Storico del repository

- Prima di DOMUS qui c'era FINDER (motore di ricerca locale). L'utente l'ha fatto cancellare e resta solo nella cronologia Git.
- **Branch di lavoro**: `claude/ciao-kds7b5`. Su `main` non c'è ancora DOMUS.
