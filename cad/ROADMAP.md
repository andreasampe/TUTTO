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
4. **Snap come in AutoCAD** per scegliere i punti:
   - Fine (quadrato);
   - Punto medio (triangolo);
   - Perpendicolare (⊥);
   - Vicino (clessidra).
3. Quote nuove **allineate** in automatico quando i due punti sono in obliquo (muro inclinato) e la linea va accanto al tratto.

## Tutte le tavole esecutive del file di riferimento (richiesta di Andrea, 31/10/2026)

Richiesta:
- generare in automatico tutte le tavole che ci sono nel file di riferimento;
- renderle modificabili dall'app, con sezioni dedicate;
- alla fine racchiudere tutto in un'unica planimetria finale, come `03_BASICO`.

Inventario di `03_BASICO_VIVIENDA MOYA OPCION 2-3.dxf`, analizzato il 31/10/2026: una serie per l'opzione 2 e una per l'opzione 3. Ogni tavola è la pianta `REF.OPCION` inserita come riferimento esterno, più i layer della tavola.

| Tavola | Layout | Layer | Contenuto | Automatizzabile senza inventare dati |
|---|---|---|---|---|
| Quote | PB_COT | 01Cotas 1, 01Texto 4 - Carpinterías | quote, sigle dei serramenti (P1a, V1, PV1, PG+PA, CV1…) | quote **fatte**; sigle dei serramenti: riconoscimento e numerazione automatica, tipi da confermare |
| Superfici utili | PB_SUP | 01Sup. útiles, 01Texto 2 - útiles | nome e superficie dei locali | superfici **sì** (locali già riconosciuti dal motore); nomi da confermare nell'app (proposta dagli arredi) |
| Abitabilità | PB_HABI | 05Habitabilidad | arredi minimi e legenda | serve la norma di riferimento |
| DB-SUA | PB_SUA | 05DB-SUA | classe di scivolosità C1/C2/C3 per locale | sì, dalla destinazione del locale (regole CTE DB-SUA 1) |
| DB-SI | PB_SI | 05DB-SI | settori, percorsi di evacuazione, S.E.E.S | percorsi misurabili; uscite da indicare |
| Muratura | ALB_PB | 02Albañilería | tipi di muro | i tipi sono scelte di progetto: da assegnare nell'app |
| Finiture | ACAB_PB | 02Acabados | finiture | scelte di progetto: da assegnare nell'app |
| Idrico e scarichi | FONT_PB, SAN_PB | 03fontanería, 03Saneamiento | tubi, diametri, aerotermia, allaccio | serve la norma e le regole (punto 9 della specifica) |
| Elettrico e illuminazione | ELECT_PB, ILUM_PB | 03electricidad… | prese, luci, altezze | serve la norma (REBT / CEI 64-8) e le regole |
| Prospetti e sezioni | ALZ…, SECCION… | — | 4 prospetti, 2 sezioni | servono altezze e dati 3D che la pianta non contiene |
| Lamina base | 00_LÁMINA BASE, PLANOS | — | impaginazione con cartiglio | sì: layout di stampa con le tavole affiancate e PDF |

**Decisioni di Andrea (31/10/2026):**
1. **Normativa italiana**, con regole che cambiano da comune a comune.
   - Serve un archivio interno dei comuni, che applichi le regole giuste in base al comune del progetto.
   - Le regole comunali non si possono inventare. Struttura a due livelli:
     - regole **nazionali** verificabili (DM 5/7/1975 sui requisiti igienico-sanitari, DM 236/89 sull'accessibilità…);
     - **regole del comune**, inserite dallo studio o importate da fonti ufficiali, sopra quelle nazionali.
   - Elenco dei comuni dal file aperto ISTAT.
2. **Ordine:**
   - superfici utili;
   - sigle dei serramenti;
   - tavola finale unica (impaginazione con cartiglio, PDF);
   - poi le altre tavole.
3. **Impianti: proposta automatica + ritocchi**, con le regole di Andrea:
   - camera: 2 prese ai lati del letto, h 60 cm;
   - cucina: prese di forno, frigo, piano cottura e microonde a h 40 cm, più 2 prese sul piano di lavoro a h 110 cm;
   - per il resto, buon senso e il riferimento progettuale (tavole ELECT/ILUM/FONT/SAN del file `03_BASICO`).
4. **Prospetti e sezioni:** con le altezze inserite nell'app (locali, davanzali, architravi, tetto).

## Prossimi passi, in ordine (stato al 31/10/2026, sessione in pausa per crediti)

Già fatto e pubblicato sul ramo `claude/ciao-kds7b5`:
- quote nuove modificabili;
- menu col tasto destro;
- quote allineate sui tratti inclinati;
- snap come in AutoCAD (fine, punto medio, perpendicolare, vicino).

1. **Superfici utili**, tavola PB_SUP. Lavoro appena iniziato: stavo leggendo dal `03_BASICO` come lo studio disegna le superfici (layer `01Sup. útiles` e `01Texto 2 - útiles`, testi tipo "SALÓN e COCINA SUP = 28.46 m²"). Da fare:
   - `domus_cad/superfici.py`: locali dalla `Mappa` di `interne.py` (zone interne separate da muri e porte) e area netta, con un raster fine e la correzione del bordo, oppure contorno → poligono agganciato ai muri;
   - **verifica sui valori reali** dell'opzione 2: SALÓN e COCINA 28.46, DORMITORIO PRINCIPAL 17.85, DORMITORIO INDIVIDUAL 11.07, DORMITORIO INDIVIDUAL 2 11.6x, BAÑO 6.43, ENTRADA 5.74, DISTRIBUIDOR 1.80, PATIO 2 10.75, Zona Aparcamiento 20.01;
   - nomi dei locali: dai testi già presenti nel locale, altrimenti da confermare nell'app (servono anche alle regole normative);
   - layer e formato del testo dal profilo: aggiungere a `profilo.RUOLI` i layer delle superfici;
   - nell'app: pulsante "Superfici utili", nomi modificabili, nuova versione.
2. **Sigle dei serramenti**, tavola PB_COT: riconoscere porte e finestre dal layer delle aperture, numerarle per tipo e dimensione (P1a, V1, PV1…), con un abaco.
3. **Tavola finale unica**: impaginazione delle tavole affiancate in layout con cartiglio (come la lamina base del `03_BASICO`) ed esportazione PDF.
4. **Archivio normativo italiano**:
   - regole nazionali (DM 5/7/1975, DM 236/89);
   - comuni dal file ISTAT;
   - regole comunali inserite dallo studio, mai inventate;
   - verifiche su superfici, altezze e aeroilluminazione.
5. **Impianti**: proposta automatica con le regole di Andrea, poi ritocchi nell'editor (simboli spostabili). Regole:
   - camera: 2 prese ai lati del letto a h 60;
   - cucina: forno, frigo, piano cottura e microonde a h 40, più 2 prese sul piano di lavoro a h 110.
6. **Altre tavole in pianta:** abitabilità, scivolosità dei pavimenti, antincendio, muratura, finiture.
7. **Prospetti e sezioni**, con le altezze inserite nell'app.
8. Ancora aperto:
   - Andrea deve provare DOMUS in Local (sostituire la cartella `domus` con quella dello ZIP aggiornato);
   - test 8 in AutoCAD sull'ultima versione;
   - attività separata sul fuso orario di SQLite.

