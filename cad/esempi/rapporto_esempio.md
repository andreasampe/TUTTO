# Rapporto di elaborazione DOMUS (prototipo)

- **Origine:** `esempi/esempio_artificiale.dxf`
- **Elaborato:** `lavoro/elaborazioni/20261030-214310/elaborato.dxf`
- **Unità:** m · **Scala:** 1:100 · **DXF:** AC1032
- **Entità:** 22 (LINE 12, TEXT 5, LWPOLYLINE 2, ARC 2, CIRCLE 1)
- **Layer:** TRAMEZZI, FINESTRE, TESTI, MURI, PORTE

## Problemi rilevati nel disegno

- Nessuno

## Controlli (punto 15 della specifica)

| # | Controllo | Esito | Dettaglio |
|---|---|---|---|
| 1 | Lettura | ok | 22 entità originali lette |
| 2 | Conservazione | ok | tutte le entità originali presenti e invariate |
| 3 | Layer | ok | presenti: DOMUS_CARTIGLIO, DOMUS_NOTE, DOMUS_QUOTE |
| 4 | Quote | ok | 32 quote, misure uguali alla geometria (tolleranza 1e-6), testo = misura × 100 |
| 5 | Cartiglio | ok | blocco DOMUS_CARTIGLIO con attributi, in basso a destra fuori dal disegno |
| 6 | Esportazione | ok | elaborato.dxf, 118032 byte, DXF AC1032 |
| 7 | Riapertura | ok | riaperto con ezdxf, audit: 0 errori, 0 correzioni |
| 8 | Compatibilità AutoCAD | da verificare | da aprire in AutoCAD sul Mac: in questo ambiente AutoCAD non c'è |
| 9 | Integrità dell'originale | ok | SHA-256 del file originale invariato |
| 10 | Ripetibilità | da verificare | verificata dai test automatici (due elaborazioni identiche) |
| 11 | Costo | ok | solo software open source gratuito: Python, ezdxf (MIT) |

**Esito complessivo:** superato (i controlli 'da verificare' richiedono AutoCAD).

## Registro

- Origine: esempi/esempio_artificiale.dxf (SHA-256 5ad163b8bb32becc…)
- Letto DXF AC1032: 22 entità, 5 layer
- Generate 32 quote su DOMUS_QUOTE (catene parziali e totali sui 4 lati)
- Inseriti cartiglio (blocco con attributi) e nota sul layer DOMUS_NOTE
- Salvato elaborato.dxf (118032 byte)
- Creata anteprima.svg
