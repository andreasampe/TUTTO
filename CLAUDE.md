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

## OBIETTIVO VERO DI DOMUS: software CAD per architetti (cartella `cad/`)

**Leggi per prima cosa `cad/SPECIFICA.txt`**: è la specifica di Andrea ed è vincolante.
- Obiettivo: partire da una planimetria reale (DWG/DXF di AutoCAD) e generare elaborati tecnici CAD: piante quotate, stato di fatto e di progetto, demolizioni e costruzioni, impianti, tavole PDF.
- Vincoli assoluti:
  - zero costi;
  - solo open source con licenze verificate;
  - niente API AI a pagamento;
  - niente dati inventati;
  - procedere per fasi;
  - non dichiarare completato ciò che non è testato su file reali.
- **Stato (30/10/2026):**
  - Fase 0 fatta (`cad/ANALISI_TECNICA.md`);
  - Fase 1, prototipo in `cad/domus_cad/` con Python e ezdxf: 22 test (`python -m unittest discover -s tests`) e **prova su una pianta reale** (`REF.OPCION 2`, progetto spagnolo Vivienda Moya; DWG e DXF di Andrea sono sul ramo `main`). Risultato in `cad/prove/`;
  - la prima versione si apre in AutoCAD, ma ad Andrea le quote non andavano bene. Le vuole come il layer `01Cotas 1` del file `03_BASICO`: in metri, con pallino, e anche **dentro le stanze**;
  - fatta la seconda versione (`domus_cad/interne.py`: luci nette da muro a muro, muri campiti come massa, porte chiuse con una chiusura morfologica);
  - terza versione, su richiesta di Andrea:
    - quote sul **layer e con lo stile dello studio**, senza modificarli;
    - distanze degli arredi;
    - zone aperte (garage) quotate come sono;
  - **niente nomi fissi**: ogni studio ha un file di riferimento da cui `domus_cad profilo` ricava in automatico layer delle quote, stile, scala e ruoli dei layer (dal nome in più lingue, poi dalla geometria). Il profilo dello studio Pucci è in `cad/profili/studio.json`. Nella webapp lo studio caricherà il file di riferimento al momento della registrazione;
  - quarta versione: moduli uguali (cucina 60×60) quotati una volta per locale; testi spostati se si toccano (`domus_cad/testi.py`);
  - quinta versione, dalle osservazioni di Andrea in AutoCAD:
    - niente quote ripetute vicine;
    - letto + cuscino = una quota;
    - quote perpendicolari ai muri inclinati;
    - linea spostata lungo il tratto;
    - testo mai oltre i pallini;
  - sesta versione:
    - stanze con muro inclinato: solo quota minima e massima;
    - pallini `_DOT` con il trattino verso l'interno (la linea "sbordava" quando i punti erano invertiti);
    - testi che evitano anche il disegno esistente.

    Da riprovare in AutoCAD;
  - manca l'xref `00 REF_VIVIENDA MOYA`;
  - il file principale ha molte tavole affiancate: serve la scelta della zona;
  - avanzamento in `cad/ROADMAP.md`.
- **Area "Planimetrie" nella webapp (30/10/2026).** Andrea vuole vedere e ritoccare tutte le planimetrie esecutive dal sito.
  - Ha scelto **solo ritocchi** agli elaborati: quote, testi, cartiglio. Non un CAD completo.
  - Idea: il motore gira nel browser con Pyodide.
  - Prova di fattibilità `cad/web/prova_browser.html` **riuscita** sul Mac di Andrea: Chrome, 6,4 s, stesso risultato della riga di comando. Il motore gira nel browser. Piano in `cad/ROADMAP.md`.
- Flusso DWG scelto: AutoCAD *Salva con nome → DXF 2018*, poi elaborazione DOMUS, poi controllo in AutoCAD e salvataggio in DWG. CADCraft (MIT/Apache) è stato analizzato ma oggi è immaturo nel salvataggio: va rivalutato su file reali.
- Decisioni di Andrea (30/10/2026):
  - **niente database dell'hosting**: dati su file (SQLite/JSON), anche se la specifica direbbe il contrario;
  - l'hosting è probabilmente **Aruba condiviso** (dettagli sconosciuti), quindi si presume che l'elaborazione CAD giri sul Mac e il sito gestisca solo progetti e file;
  - ha mandato il primo progetto reale (Vivienda Moya) il 30/10/2026.
- Errore da non ripetere: la webapp `domus/` è stata costruita **prima** di avere questa specifica, interpretando una sola riga del documento master. **Chiedere sempre lo scopo prima di costruire.**

## DOMUS web (cartella `domus/`)

Webapp gestionale per lo studio di architettura (Chiara Pucci, architetto), nata prima della specifica CAD. Nella specifica corrisponde alla futura **Fase 6 (integrazione web)**: progetti, file e utenti, che poi richiameranno il motore `cad/`. Leggi `domus/README.md` per installazione e uso. L'installazione in Local è stata **sospesa** in attesa di decidere come procedere.

### Architettura

- **PHP 8.1+ senza framework né Composer**, autonomo (non è un plugin WordPress). In Local sta in `Local Sites/DOMUS/app/public/domus/` e si apre su `http://domus.local/domus/`.
- **Dati**: SQLite in `domus-data/domus.sqlite`, fuori dalla cartella pubblica (percorso in `config.php`, sovrascrivibile con la variabile d'ambiente `DOMUS_DATA_DIR`). Esiste un export JSON completo (`api.php?r=export`).
- **Migrazioni**: `src/Db.php`, costante `MIGRATIONS`. Usano `PRAGMA user_version`. Non modificare le migrazioni esistenti, aggiungerne di nuove in coda.
- **Date**: mai `date('now')`/`datetime('now')`/`'localtime'` nelle query. Passare `App::today($giorni)` o `App::now()` come parametri (fuso di `config.php`). `created_at`/`updated_at` li imposta `Db::insert`. Dettagli nel README, sezione *Date e fuso orario*.
- **API**: unico punto d'ingresso `api.php?r=risorsa/id`. Si usa il parametro `r` per non dipendere da regole di riscrittura degli URL (Local usa nginx). Le rotte sono nel `match` di `api.php`.
- **Logica**: una classe per area in `src/` (`Auth`, `Users`, `Clients`, `Projects` con fasi e diario, `Tasks`, `Files`, `Permits`, `Payments`, `Hours`, `Calendar`, `Search`, `Dashboard`, `Settings`, `Backup`, `Validate`). Gli errori sono `HttpError(status, messaggio, [campo => errore])`: l'interfaccia evidenzia il campo sbagliato.
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
- **Stile**: **leggi `domus/STILE.md` prima di toccare l'interfaccia.**
  - Riferimento: le tavole di presentazione che Andrea ha fornito, nero profondo con fotografia architettonica in cemento.
  - Tema **solo scuro**; la stampa è chiara.
  - Titoli Inter Light grandi, etichette maiuscole spaziate, pulsanti a pillola con bordo sottile, icone a linea (`assets/js/icons.js`, `D.icon(nome)`).
  - Il carattere Inter è incluso in `assets/fonts/`, nessun servizio esterno.
- **Marchio**:
  - `assets/icon.svg` (favicon: la D che accoglie la casa, su griglia di costruzione, ricostruita in vettoriale dalla favicon di Andrea);
  - `assets/logo-white.svg` (solo marchio a linea);
  - motto: **D**esign · **O**rganize · **M**odel · **U**nify · **S**implify.
- **Immagini** in `assets/img/`: `home.jpg` (panoramica, arco in cemento a forma di D con la casa), `login.jpg` (villa), `brand.jpg` (dettaglio in cemento, testata dei progetti senza copertina). Sono ritagli a bassa risoluzione dalle tavole: Andrea fornirà gli originali con lo stesso nome.
- **Copertina dei progetti**: è la foto scelta con la stellina in *Documenti* (`projects.cover_file_id`, migrazione 3), altrimenti la prima immagine caricata.

### Dati gestiti

**Fase 1 (completata)**

- **Clienti**: privato / azienda / ente, codice fiscale, partita IVA, contatti.
- **Progetti**:
  - codice progressivo annuale `AAAA-NNN`;
  - stato: preventivo, in corso, sospeso, concluso, archiviato;
  - indirizzo, comune, dati catastali, tipo di intervento, date, onorario;
  - **fasi**: create da un elenco standard modificabile in Impostazioni; stati da fare, in corso, completata, non prevista;
  - **scadenze**: anche generali di studio, senza progetto;
  - **diario di commessa**: nota, sopralluogo, riunione, telefonata, email, pratica.

**Fase 2 (completata)**
- **documenti** con categorie (tavole, foto, documenti, pratiche, contratti) e anteprima delle immagini;
- **pratiche edilizie**: CILA, SCIA, PdC, paesaggistica, sismica, agibilità…, con ente, protocollo, deposito, esito, scadenza e stato;
- **economia**: rate dell'onorario con fattura e incasso, riepilogo per progetto e per studio (da incassare, incassato nell'anno);
- **ore lavorate** per persona;
- **calendario** mensile;
- **ricerca rapida** ⌘K;
- **collaboratori** con ruoli;
- **stampa** della scheda progetto.

**Fase 3 (completata)**
- **marchio vettoriale** ricalcato sul PNG originale (`domus_logo.png`, caricato da Andrea su `main` insieme alle immagini). Le misure sono in `STILE.md`;
- **studio dimostrativo** (`src/Demo.php`): 7 progetti, foto da `domus/demo/`, PDF generati da `src/Pdf.php`. Si carica solo con l'archivio vuoto;
- **Studio** (statistiche con grafici SVG), **Galleria** con visore, **Bacheca** a colonne con trascinamento: tutte in `assets/js/screens.js`;
- **calendario iCal** (`src/Ical.php`), con un codice segreto per utente;
- **preventivo PDF** (`Payments::quotePdf`).

### Come provare

- `bash domus/tests/run.sh` avvia il server PHP integrato su un database temporaneo ed esegue i test dell'API: `tests/api_test.php` (89 controlli), `tests/demo_test.php` (16 controlli: studio dimostrativo, statistiche, galleria, iCal, preventivo) e `tests/fuso_test.php` (9 controlli: date nel fuso dello studio anche quando la data UTC è diversa), tutti verdi alla consegna.
- Schermate aggiornate in `domus/docs/schermate/` (20 immagini).
- Per l'interfaccia: `php -S` su una cartella che contiene `domus/` (per simulare il percorso `/domus/` di Local) e Playwright (preinstallato nell'ambiente cloud).
- In questo ambiente cloud **non si può avviare WordPress** (il codice esterno è bloccato) e **non c'è accesso ai siti esterni**.

### Prossimi passi possibili (da concordare con l'utente)

1. **Fornitori e imprese** (anagrafica separata dai clienti) collegati ai progetti.
2. **Calcolo dell'onorario** secondo il DM 17/06/2016 (parametri), come supporto al preventivo.
3. **Computo metrico** semplice per voci e categorie di lavoro.
4. **Area clienti** in sola lettura, per condividere documenti e avanzamento.
5. **Promemoria via email** delle scadenze (servirebbe un invio SMTP configurato).

## Storico del repository

- Prima di DOMUS qui c'era FINDER (motore di ricerca locale). L'utente l'ha fatto cancellare e resta solo nella cronologia Git.
- **Branch di lavoro**: `claude/ciao-kds7b5`. Su `main` ci sono solo le immagini caricate da Andrea (logo, render, tavole): DOMUS non è ancora unito a `main`.
- **Installazione sul Mac**: non ancora fatta. Andrea ha creato il sito `DOMUS` in Local, ma ha chiesto di completare il prodotto prima di copiarlo. Le istruzioni passo passo sono in `domus/README.md`: copiare `domus/` in `Local Sites/DOMUS/app/public/` e aprire `http://domus.local/domus/`.
