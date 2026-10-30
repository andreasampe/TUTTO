# Roadmap e avanzamento

File di avanzamento del progetto (punto 17.6): va aggiornato alla fine di ogni fase.

| Fase | Obiettivo | Stato |
|---|---|---|
| **0 — Ricognizione** | ambiente, CADCraft, alternative, licenze, strategia DWG | **completata** per quanto verificabile da qui: vedi [ANALISI_TECNICA.md](ANALISI_TECNICA.md). Restano hosting e Mac (sezione 6) |
| **1 — Prototipo CAD** | importare, quotare, annotare, cartiglio, esportare, riaprire, verificare | **implementato e testato su un esempio artificiale** (10 test automatici). **Manca:** prova su una planimetria reale e apertura in AutoCAD |
| 2 — Motore di elaborazione | moduli, gestione file, controlli, test | in parte già impostata (cartelle, log, rapporto) |
| 3 — Modello dell'edificio | locali, pareti, porte, finestre, superfici, correzioni manuali | da iniziare |
| 4 — Elaborati architettonici | stato di fatto/progetto, demolizioni e costruzioni, layer e cartigli configurabili | da iniziare |
| 5 — Impianti | elettrico, idrico-sanitario, scarichi | da iniziare: **solo dopo** la Fase 1 su file reali |
| 6 — Integrazione web | collegare il motore a DOMUS (sito) | DOMUS web esiste già (gestione dello studio, cartella `../domus`); il collegamento è da fare |
| 7 — Evoluzione | computi, quantità, AI locale | futuro |

## Prossime attività (in ordine)

1. **Andrea:** inviare una planimetria reale, esportata da AutoCAD con *Salva con nome → DXF 2018*.
2. Elaborarla e correggere i problemi che emergono (layer, blocchi, unità, doppioni).
3. **Andrea:** aprire `elaborato.dxf` in AutoCAD e confermare il test 8. Elenco dei controlli in [TEST.md](TEST.md).
4. Quote migliori:
   - catene per ogni tratto di facciata (facciate a gradoni);
   - testo spostato fuori per le quote corte;
   - quote delle aperture (finestre e porte) se stanno su layer riconoscibili.
5. Layout di stampa (spazio carta A3 con viewport in scala) ed esportazione PDF.
6. Rivalutare CADCraft e LibreDWG su file reali, per accettare i DWG direttamente dal sito.

## Problemi aperti

- ~~Database~~: deciso il 30/10/2026, **niente database dell'hosting**, dati su file (SQLite/JSON).
- Hosting: probabilmente Aruba condiviso (Andrea non conosce i dettagli). Ipotesi di lavoro: il sito gestisce file e progetti, l'elaborazione CAD gira sul Mac. Da verificare dal pannello Aruba quando servirà la Fase 6.
