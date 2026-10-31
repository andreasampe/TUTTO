# Roadmap e avanzamento

File di avanzamento del progetto (punto 17.6): va aggiornato alla fine di ogni fase.

| Fase | Obiettivo | Stato |
|---|---|---|
| **0 — Ricognizione** | ambiente, CADCraft, alternative, licenze, strategia DWG | **completata** per quanto verificabile da qui: vedi [ANALISI_TECNICA.md](ANALISI_TECNICA.md). Restano hosting e Mac (sezione 6) |
| **1 — Prototipo CAD** | importare, quotare, annotare, cartiglio, esportare, riaprire, verificare | **implementato, testato** (13 test automatici) **e provato su una pianta reale** (`REF.OPCION 2`, Vivienda Moya). La prima versione si apre in AutoCAD; la seconda aggiunge le quote interne in metri nello stile dello studio. **Manca:** il test 8 sulla seconda versione |
| 2 — Motore di elaborazione | moduli, gestione file, controlli, test | in parte già impostata (cartelle, log, rapporto) |
| 3 — Modello dell'edificio | locali, pareti, porte, finestre, superfici, correzioni manuali | da iniziare |
| 4 — Elaborati architettonici | stato di fatto/progetto, demolizioni e costruzioni, layer e cartigli configurabili | da iniziare |
| 5 — Impianti | elettrico, idrico-sanitario, scarichi | da iniziare: **solo dopo** la Fase 1 su file reali |
| 6 — Integrazione web | collegare il motore a DOMUS (sito); **area "Planimetrie"** per vedere e ritoccare tutte le planimetrie esecutive | DOMUS web esiste già (gestione dello studio, cartella `../domus`); il collegamento è da fare. **Richiesta di Andrea:** quando uno studio si registra carica il suo file di riferimento; DOMUS ne ricava il profilo (`domus_cad profilo`) e lo usa per tutti i suoi elaborati, con una pagina per controllare e correggere i ruoli dei layer |
| 7 — Evoluzione | computi, quantità, AI locale | futuro |

## Prossime attività (in ordine)

1. ~~Planimetria reale~~: arrivata il 30/10/2026 (Vivienda Moya), elaborata `REF.OPCION 2`.
2. ~~Quote in metri, interne ed esterne, nello stile di `01Cotas 1`~~: fatto il 30/10/2026 (`domus_cad/interne.py`).
   Terza versione (30/10/2026):
   - quote sul layer e con lo stile dello studio;
   - distanze degli arredi;
   - zone aperte quotate;
   - **profilo ricavato in automatico dal file di riferimento** (`domus_cad profilo`).

   **Andrea:** riaprire in AutoCAD e dire se va bene.
3. **Andrea:** inviare il riferimento esterno mancante `00 REF_VIVIENDA MOYA` (DXF).
4. **Scelta della zona** nei file con più tavole affiancate (come il file principale):
   - riconoscere automaticamente le cornici delle tavole;
   - in alternativa, `--finestra x1,y1,x2,y2`.
5. Quote migliori:
   - distanze tra arredi e muri (come 0.90 tra tavolo e cucina nel riferimento), dai layer dell'arredo;
   - quote interne che evitano i nomi dei locali;
   - testo spostato fuori per le quote corte;
   - quote allineate ai muri inclinati;
   - catene per ogni tratto di facciata (facciate a gradoni);
   - quote delle aperture (finestre e porte) se stanno su layer riconoscibili.
6. Layout di stampa (spazio carta A3 con viewport in scala) ed esportazione PDF.
7. Rivalutare CADCraft e LibreDWG su file reali, per accettare i DWG direttamente dal sito.

## Problemi aperti

- ~~Database~~: deciso il 30/10/2026, **niente database dell'hosting**, dati su file (SQLite/JSON).
- Hosting: probabilmente Aruba condiviso (Andrea non conosce i dettagli). Ipotesi di lavoro: il sito gestisce file e progetti, l'elaborazione CAD gira sul Mac. Da verificare dal pannello Aruba quando servirà la Fase 6.

## Area "Planimetrie" nella webapp (richiesta di Andrea, 30/10/2026)

**Scelta di Andrea:** nell'app servono **ritocchi sugli elaborati DOMUS**, cioè quote, testi e cartiglio. Non serve disegnare muri o arredi, quindi niente CAD completo per ora. Deve valere per **tutte le planimetrie esecutive**:
- piante quotate;
- stato di fatto e di progetto;
- demolizioni e costruzioni;
- impianti.

Serve un'area dedicata del sito da cui modificarle tutte.

Architettura proposta:
- **visualizzatore** nel browser con modifiche mirate:
  - spostare o togliere una quota, spostarne il testo;
  - correggere i campi del cartiglio;
  - rielaborare;
- le modifiche le applica il **motore DOMUS (ezdxf)**, che non tocca il resto del disegno;
- il motore dovrebbe girare **dentro il browser con Pyodide** (Python in WebAssembly, MPL-2.0). Così funziona uguale in Local e sull'hosting Aruba, senza Python sul server e senza il Mac acceso.

**Passo 1, prova di fattibilità:** `web/prova_browser.html`, generata da `web/genera_prova.py`.
- Verificato qui: Pyodide 0.27.7 parte nel browser e contiene tutte le librerie necessarie (numpy 2.0.2, Pillow 10.2.0, pyparsing, fonttools, typing-extensions; ezdxf da PyPI).
- Non verificabile qui: l'elaborazione completa, perché il CDN di Pyodide è bloccato in questo ambiente cloud.
- **Verificato da Andrea sul Mac, in Chrome (30/10/2026):**
  - motore pronto in 2,3 s;
  - `REF.OPCION 2.dxf` elaborato in **6,4 s**, esito superato;
  - stesso risultato della versione da riga di comando: 28 quote esterne e 74 interne, 35 testi spostati, controlli 1-7, 9 e 11 ok.

  **Il motore funziona nel browser:** l'area Planimetrie può fare a meno di Python sul server.

**Passo 2, area Planimetrie costruita (31/10/2026)**, nella webapp `domus/`:
- voce di menu, scheda nel progetto, editor e profilo CAD in Impostazioni;
- versioni salvate come documenti del progetto;
- motore in `domus/assets/cad/` (`web/esporta_per_domus.py`);
- funzioni `modifica.py` e `webapi.py`.

Provato qui con la webapp vera (PHP, database, interfaccia) e il motore Python eseguito fuori dal browser, perché il CDN di Pyodide qui è bloccato:
- creazione;
- quotatura automatica (102 quote);
- spostamento ed eliminazione di quote, modifica del cartiglio;
- nuova versione con i controlli superati;
- profilo ricavato dal `03_BASICO`.

**Andrea:** provare in Local sul Mac, dove il motore si carica dal CDN.

CADCraft (CAD completo nel browser) resta da rivalutare se un giorno servirà modificare il disegno vero. Oggi perde dati al salvataggio (vedi ANALISI_TECNICA.md).

### Editor delle planimetrie: richieste di Andrea del 31/10/2026, fatte

1. Le quote appena aggiunte si selezionano, si trascinano (linea e testo) e si eliminano come le altre, anche prima di salvare.
2. Menu col tasto destro sulla pianta:
   - su una quota: Elimina, Ripristina, Testo al centro, Annulla le sue modifiche;
   - su un testo: Modifica testo;
   - sul vuoto: Aggiungi quota da qui, Tutto il disegno, Annulla l'ultima modifica.
3. Quote nuove **allineate** in automatico quando i due punti sono in obliquo (muro inclinato) e la linea va accanto al tratto.
