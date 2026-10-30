# Rapporto di elaborazione DOMUS (prototipo)

- **Origine:** `esempi/esempio_artificiale.dxf`
- **Elaborato:** `esempi/elaborato_esempio.dxf`
- **Unità:** m · **Scala:** 1:50 · **DXF:** AC1032
- **Entità:** 28 (LINE 13, LWPOLYLINE 7, TEXT 5, ARC 2, CIRCLE 1)
- **Layer:** TRAMEZZI, ARREDI, FINESTRE, TESTI, MURI, PORTE

## Problemi rilevati nel disegno

- Nessuno

## Controlli (punto 15 della specifica)

| # | Controllo | Esito | Dettaglio |
|---|---|---|---|
| 1 | Lettura | ok | 28 entità originali lette |
| 2 | Conservazione | ok | tutte le entità originali presenti e invariate |
| 3 | Layer | ok | presenti: 01Cotas 1, DOMUS_CARTIGLIO, DOMUS_NOTE |
| 4 | Quote | ok | 47 quote, misure uguali alla geometria (tolleranza 1e-6), testo = misura × 1 |
| 5 | Cartiglio | ok | blocco DOMUS_CARTIGLIO con attributi, in basso a destra fuori dal disegno |
| 6 | Esportazione | ok | elaborato.dxf, 144233 byte, DXF AC1032 |
| 7 | Riapertura | ok | riaperto con ezdxf, audit: 0 errori, 0 correzioni |
| 8 | Compatibilità AutoCAD | da verificare | da aprire in AutoCAD sul Mac: in questo ambiente AutoCAD non c'è |
| 9 | Integrità dell'originale | ok | SHA-256 del file originale invariato |
| 10 | Ripetibilità | da verificare | verificata dai test automatici (due elaborazioni identiche) |
| 11 | Costo | ok | solo software open source gratuito: Python, ezdxf (MIT), Pillow (MIT-CMU), NumPy (BSD) |

**Esito complessivo:** superato (i controlli 'da verificare' richiedono AutoCAD).

## Registro

- Origine: esempi/esempio_artificiale.dxf (SHA-256 c61604a38c677fb1…)
- Letto DXF AC1032: 28 entità, 6 layer
- Quote sul layer '01Cotas 1' con lo stile '50' del profilo e le modifiche {'dimscale': 1.0}
- Generate 22 quote esterne (catene parziali e totali sui 4 lati), muri: MURI, TRAMEZZI
- Generate 25 quote interne: luci tra muri (minimo 0.5 m) e distanze degli arredi (minimo 0.3 m)
- Limiti: nessuno · arredi: ARREDI · porte e finestre: FINESTRE, PORTE
- Testi spostati per non toccarsi: 13 su 47
- Inseriti cartiglio (blocco con attributi) e nota sul layer DOMUS_NOTE
- Salvato elaborato.dxf (144233 byte)
- Creata anteprima.svg
