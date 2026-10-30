# Test

## Test automatici

```bash
python3 -m unittest discover -s tests
```

10 test, tutti superati il 30/10/2026 (l'esempio ora include un cerchio) (Python 3.13, ezdxf 1.4.2). Usano `esempi/esempio_artificiale.dxf`, un appartamento **inventato** di 12 × 9 m in metri: **non sono una prova su file reali**.

| Test | Cosa verifica |
|---|---|
| flusso completo | nessun controllo fallito; esistono elaborato, anteprima, log e rapporto |
| quote totali | 12,00 / 9,00 / 5,00 m come nel disegno; le catene parziali sommano al totale |
| testo in centimetri | fattore di conversione 100 nello stile di quota |
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

**REF.OPCION 2, quotato solo sui layer `01Seccionado albañilería` e `01Seccionado albanileria`** (lo stesso layer scritto in due modi):
- test 1-7, 9 e 11 **ok**: 583 entità originali intatte, 28 quote uguali alla geometria, riapertura senza errori;
- test 8 (AutoCAD) **da verificare**.

**Errore trovato grazie al file reale e corretto:** il controllo di conservazione falliva sui CERCHI, che nell'esempio artificiale non c'erano. Ora l'esempio contiene un pilastro circolare e il test lo copre.

**Valutazione architettonica del risultato** (punto 19: un disegno convincente non prova la correttezza):
- le catene esterne e le quote totali sono coerenti con la pianta;
- le quote corte (30, 34, 60–10 cm) hanno i testi accavallati;
- sul muro inclinato si quota la proiezione orizzontale e verticale, non la lunghezza vera del muro;
- il lato sinistro quota solo il corpo edificato, perché il garage non ha muri sul layer quotato.

## Controlli ancora necessari

- [x] **Planimetria reale** (punto 21): `REF.OPCION 2.dxf`, vedi sopra.
- [ ] **Test 8 in AutoCAD**, aprendo `prove/vivienda-moya-opcion2/REF.OPCION 2 - quotato.dxf` (lo stesso file compresso, più facile da scaricare da GitHub: `opcion2_quotato.zip`) e controllando:
  - si apre senza messaggi di errore né richieste di recupero;
  - i layer `DOMUS_QUOTE`, `DOMUS_NOTE`, `DOMUS_CARTIGLIO` esistono e si possono accendere e spegnere;
  - le quote si selezionano come quote (non come linee esplose) e la misura coincide con il comando DIST;
  - il cartiglio è un blocco: con un doppio clic si modificano i campi;
  - *Salva con nome → DWG* funziona, e riaprendo il DWG non manca niente.
- [ ] Test su file con unità in millimetri e in centimetri.
- [ ] Test su file con layer 0, blocchi annidati e riferimenti esterni.
