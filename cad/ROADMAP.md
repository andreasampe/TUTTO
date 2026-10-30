# Roadmap e avanzamento

File di avanzamento del progetto (punto 17.6): va aggiornato alla fine di ogni fase.

| Fase | Obiettivo | Stato |
|---|---|---|
| **0 — Ricognizione** | ambiente, CADCraft, alternative, licenze, strategia DWG | **completata** per quanto verificabile da qui: vedi [ANALISI_TECNICA.md](ANALISI_TECNICA.md). Restano hosting e Mac (sezione 6) |
| **1 — Prototipo CAD** | importare, quotare, annotare, cartiglio, esportare, riaprire, verificare | **implementato, testato** (10 test automatici) **e provato su una pianta reale** (`REF.OPCION 2`, Vivienda Moya: controlli ok). **Manca:** apertura in AutoCAD (test 8) |
| 2 — Motore di elaborazione | moduli, gestione file, controlli, test | in parte già impostata (cartelle, log, rapporto) |
| 3 — Modello dell'edificio | locali, pareti, porte, finestre, superfici, correzioni manuali | da iniziare |
| 4 — Elaborati architettonici | stato di fatto/progetto, demolizioni e costruzioni, layer e cartigli configurabili | da iniziare |
| 5 — Impianti | elettrico, idrico-sanitario, scarichi | da iniziare: **solo dopo** la Fase 1 su file reali |
| 6 — Integrazione web | collegare il motore a DOMUS (sito) | DOMUS web esiste già (gestione dello studio, cartella `../domus`); il collegamento è da fare |
| 7 — Evoluzione | computi, quantità, AI locale | futuro |

## Prossime attività (in ordine)

1. ~~Planimetria reale~~: arrivata il 30/10/2026 (Vivienda Moya), elaborata `REF.OPCION 2`.
2. **Andrea:** aprire `prove/vivienda-moya-opcion2/REF.OPCION 2 - quotato.dxf` in AutoCAD e confermare il test 8. Elenco dei controlli in [TEST.md](TEST.md).
3. **Andrea:** inviare il riferimento esterno mancante `00 REF_VIVIENDA MOYA` (DXF).
4. **Scelta della zona** nei file con più tavole affiancate (come il file principale):
   - riconoscere automaticamente le cornici delle tavole;
   - in alternativa, `--finestra x1,y1,x2,y2`.
5. Quote migliori:
   - testo spostato fuori per le quote corte;
   - quote allineate ai muri inclinati;
   - catene per ogni tratto di facciata (facciate a gradoni);
   - quote delle aperture (finestre e porte) se stanno su layer riconoscibili.
6. Layout di stampa (spazio carta A3 con viewport in scala) ed esportazione PDF.
7. Rivalutare CADCraft e LibreDWG su file reali, per accettare i DWG direttamente dal sito.

## Problemi aperti

- ~~Database~~: deciso il 30/10/2026, **niente database dell'hosting**, dati su file (SQLite/JSON).
- Hosting: probabilmente Aruba condiviso (Andrea non conosce i dettagli). Ipotesi di lavoro: il sito gestisce file e progetti, l'elaborazione CAD gira sul Mac. Da verificare dal pannello Aruba quando servirà la Fase 6.
