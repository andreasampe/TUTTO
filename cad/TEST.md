# Test

## Test automatici

```bash
python3 -m unittest discover -s tests
```

10 test, tutti superati il 30/10/2026 (Python 3.13, ezdxf 1.4.2). Usano `esempi/esempio_artificiale.dxf`, un appartamento **inventato** di 12 × 9 m in metri: **non sono una prova su file reali**.

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

## Controlli ancora necessari

- [ ] **Planimetria reale** (punto 21): elaborare un DXF vero dello studio.
- [ ] **Test 8 in AutoCAD**, aprendo `esempi/elaborato_esempio.dxf` (e poi quello reale) e controllando:
  - si apre senza messaggi di errore né richieste di recupero;
  - i layer `DOMUS_QUOTE`, `DOMUS_NOTE`, `DOMUS_CARTIGLIO` esistono e si possono accendere e spegnere;
  - le quote si selezionano come quote (non come linee esplose) e la misura coincide con il comando DIST;
  - il cartiglio è un blocco: con un doppio clic si modificano i campi;
  - *Salva con nome → DWG* funziona, e riaprendo il DWG non manca niente.
- [ ] Test su file con unità in millimetri e in centimetri.
- [ ] Test su file con layer 0, blocchi annidati e riferimenti esterni.
