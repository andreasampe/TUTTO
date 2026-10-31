# Test

## Test automatici

```bash
python3 -m unittest discover -s tests
```

32 test (22 del prototipo e 10 dei ritocchi), tutti superati il 31/10/2026 (Python 3.13, ezdxf 1.4.2). Usano `esempi/esempio_artificiale.dxf`, un appartamento **inventato** di 12 × 9 m in metri: **non sono una prova su file reali**.

| Test | Cosa verifica |
|---|---|
| flusso completo | nessun controllo fallito; esistono elaborato, anteprima, log e rapporto |
| quote totali | 12,00 / 9,00 / 5,00 m come nel disegno; le catene parziali sommano al totale |
| layer e stile dello studio | tutte le quote su `01Cotas 1` con lo stile `50`, layer di colore 220, testo FUTURA, solo DIMSCALE 1 sulla quota |
| testo in metri | il testo disegnato è "12.00" (punto e due decimali, come in AutoCAD) |
| testo in centimetri | con `formato="cm"` la quota ha DIMLFAC 100 |
| distanze degli arredi | tavolo dell'esempio a 1,20 e 0,70 dai muri |
| profilo automatico | da un riferimento con layer `A-ANNO-DIMS` e stile `ARCH 100` riconosce layer, stile, scala 1:100 e ruoli |
| ruoli dalla geometria | con layer chiamati L1 e L2 riconosce muri e arredi dalla geometria |
| moduli uguali | tre moduli da 60×60: una sola quota di larghezza e una di profondità |
| testi spostati | due quote vicinissime: la più lunga tiene il testo al centro, l'altra lo sposta restando tra i suoi pallini |
| letto con cuscino | una sola quota di 2,00 per il letto, nessuna per il solo cuscino |
| pallini | il trattino del blocco `_DOT` punta dentro la quota (escluse le quote sotto i 30 cm, con i pallini fuori) |
| muro inclinato | stanza larga 4 m in basso e 5 m in alto: solo due quote orizzontali, la minima e la massima |
| quote interne | luci nette 4,90 / 4,20 / 4,10 dei locali; niente spessori di muro; nessuna quota attraversa il tramezzo |
| senza quote interne | l'opzione le disattiva |
| originale intatto | SHA-256 dell'originale e della sua copia invariati |
| ripetibile | due elaborazioni producono le stesse quote e le stesse misure (punto 15, test 10) |
| quote e testi | le quote in alto stanno sopra i testi esistenti |
| nota e cartiglio | la nota non si sovrappone al cartiglio |
| unità mancanti | senza `$INSUNITS` si ferma e chiede le unità; con `--unita m` procede |
| unità in conflitto | se l'unità indicata è diversa da quella del file, si ferma |
| file DWG | chiede il DXF esportato da AutoCAD |

## Prova sull'esempio artificiale

Risultato in [esempi/rapporto_esempio.md](esempi/rapporto_esempio.md), anteprima in [esempi/anteprima_esempio.png](esempi/anteprima_esempio.png):
- test 1-7, 9 e 11 **ok**;
- test 8 (AutoCAD) e test 10 (ripetibilità, coperto dai test automatici) **da verificare**.

## Prova su file reali (30/10/2026)

Andrea ha fornito 3 file di un progetto reale, *"Vivienda Moya"* (Spagna, normativa CTE), esportati da AutoCAD in DXF 2018 (AC1032), in metri. Si trovano sul ramo `main` del repository.

| File | Contenuto | Esito |
|---|---|---|
| `REF.OPCION 2.dxf` | pianta della casa, opzione 2: 583 entità, 13 layer, muri inclinati | **elaborato**, risultato in [prove/vivienda-moya-opcion2](prove/vivienda-moya-opcion2/) |
| `REF.OPCION 3.dxf` | pianta dell'opzione 3, struttura analoga | letto senza errori, non ancora elaborato |
| `03_BASICO_VIVIENDA MOYA OPCION 2-3.dxf` | insieme di tavole affiancate (impianti, abitabilità, DB-SI, DB-SUA): 1.863 entità, 267 quote esistenti, 7 tabelle, campi (FIELD) | letto. **Non elaborabile così**: va scelta la zona (vedi la Roadmap). Richiama l'xref `00 REF_VIVIENDA MOYA`, **non fornito** |

**REF.OPCION 2, prima versione** (quotati i layer `01Seccionado albañilería` e `01Seccionado albanileria`):
- 28 quote esterne in cm;
- **Andrea l'ha aperta in AutoCAD senza errori** (test 8, 30/10/2026);
- il suo giudizio: *"le quote così non vanno bene"*. Le vuole:
  - in metri, come il layer `01Cotas 1` del file `03_BASICO`;
  - sui muri perimetrali e su quelli interni;
  - con le distanze dentro le stanze.

**REF.OPCION 2, seconda versione** (layer dei muri e campitura `01Trama solido`, scala 1:50):
- 28 quote esterne e **22 quote interne**, in metri, nello stile dello studio (stile "50");
- test 1-7, 9 e 11 **ok**;
- **confronto con `01Cotas 1`**: le quote interne 7.20, 4.00, 3.50, 1.50, 1.40, 1.00 e 0.90 coincidono con quelle disegnate dallo studio;
- mancano ancora:
  - le distanze tra arredi e muri (es. 0.90 tra tavolo e cucina);
  - alcune profondità delle camere;
  - il garage, che sul layer dei muri è aperto;
- test 8 sulla seconda versione: **da verificare** in AutoCAD.

Andrea sulla seconda versione:
- le quote devono stare sul layer `01Cotas 1`, con le stesse proprietà;
- vanno quotate anche le distanze tra arredi e muri;
- il garage va quotato anche se è aperto;
- layer e arredi vanno riconosciuti in automatico dal file di riferimento di ogni studio.

**REF.OPCION 2, terza versione** (profilo ricavato in automatico da `03_BASICO`):
- 28 quote esterne e 50 interne, tutte sul layer `01Cotas 1` con lo stile `50` già presente nel file (non modificato);
- quote uguali al riferimento:
  - 7.20, 4.00, 3.50, 2.20, 1.60, 1.50, 1.40, 1.30, 1.00, 0.80;
  - 0.90 tra tavolo e cucina;
  - il garage è quotato (3.33);
- test 1-7, 9 e 11 **ok**; test 8 **da verificare** in AutoCAD;
- Andrea: moduli uguali con una sola quota, testi spostati se si toccano. Fatto: 76 quote interne, 11 testi spostati su 104;
- Andrea, dopo la prova in AutoCAD:
  - quote ripetute vicine;
  - testo lontano dalla sua quota (1.60 / 0.80 del tavolo);
  - moduli attaccati con quote uguali;
  - linea oltre il pallino;
  - quote verso il muro inclinato da inclinare;
  - letto e cuscino con due quote.

  Fatto: 69 quote interne, nessun doppione vicino, letto 1.95 e 2.00 con una quota, quote inclinate verso i muri inclinati. Il testo non va mai oltre i pallini;
- Andrea:
  - nelle stanze con muri inclinati solo la quota minima e la massima;
  - la linea sborda ancora oltre il pallino;
  - migliorare anche i punti rimasti aperti.

  Fatto:
  - quote minima e massima;
  - **causa della linea che sborda trovata e corretta**: il blocco `_DOT` ha un trattino che con i punti invertiti usciva dal pallino;
  - testi che evitano anche il disegno esistente;
  - ripetizioni fino a 3 m;
- **problema tecnico risolto:** nel DXF AutoCAD non scrive i valori predefiniti dello stile (separatore decimale, zeri, testo orizzontale) ed ezdxf li sostituiva con quelli ISO ("0,6" invece di "0.60"). Ora la grafica della quota usa i predefiniti di AutoCAD, senza modificare lo stile.

**Errore trovato grazie al file reale e corretto:** il controllo di conservazione falliva sui CERCHI, che nell'esempio artificiale non c'erano. Ora l'esempio contiene un pilastro circolare e il test lo copre.

**Valutazione architettonica del risultato** (punto 19: un disegno convincente non prova la correttezza):
- le catene esterne e le quote totali sono coerenti con la pianta;
- le quote corte (30, 34, 60–10 cm) hanno i testi accavallati;
- sul muro inclinato si quota la proiezione orizzontale e verticale, non la lunghezza vera del muro;
- il lato sinistro quota solo il corpo edificato, perché il garage non ha muri sul layer quotato.

## Stili di quota conservati (31/10/2026)

**Errore trovato provando l'aggiunta di quote:**
- salvando un DXF, ezdxf scriveva negli stili di quota tutti i valori che AutoCAD aveva omesso, ma con i suoi predefiniti ISO: virgola, zeri tolti, testo sopra la linea;
- succedeva anche senza alcuna modifica, quindi lo stile `50` dello studio cambiava;
- le quote già disegnate restavano giuste, perché AutoCAD mostra la grafica salvata, ma una quota rigenerata o nuova usciva "3,2" invece di "3.20".

**Correzione:**
- `profilo.fissa_stili` scrive i predefiniti di AutoCAD prima di ogni salvataggio;
- la tabella è verificata sullo stile `50`: tutti i valori omessi da AutoCAD coincidono;
- test `test_stili_di_quota_dello_studio_non_cambiano`.

Il file di prova `REF.OPCION 2 - quotato.dxf` è stato rigenerato.

## Motore nel browser (Pyodide)

`web/prova_browser.html`, provata da Andrea sul Mac in Chrome il 30/10/2026:
- motore pronto in 2,3 s;
- `REF.OPCION 2.dxf` elaborato in 6,4 s, esito superato;
- risultato identico a quello della riga di comando.

## Controlli ancora necessari

- [x] **Planimetria reale** (punto 21): `REF.OPCION 2.dxf`, vedi sopra.
- [ ] **Test 8 in AutoCAD sulla seconda versione** (la prima si apriva senza errori), aprendo `prove/vivienda-moya-opcion2/REF.OPCION 2 - quotato.dxf` (lo stesso file compresso, più facile da scaricare da GitHub: `opcion2_quotato.zip`) e controllando:
  - si apre senza messaggi di errore né richieste di recupero;
  - i layer `DOMUS_QUOTE`, `DOMUS_QUOTE_INTERNE`, `DOMUS_NOTE`, `DOMUS_CARTIGLIO` esistono e si possono accendere e spegnere;
  - le quote si selezionano come quote (non come linee esplose) e la misura coincide con il comando DIST;
  - il cartiglio è un blocco: con un doppio clic si modificano i campi;
  - *Salva con nome → DWG* funziona, e riaprendo il DWG non manca niente.
- [ ] Test su file con unità in millimetri e in centimetri.
- [ ] Test su file con layer 0, blocchi annidati e riferimenti esterni.
