# Rapporto di elaborazione DOMUS (prototipo)

- **Origine:** `main:REF.OPCION 2.dxf`
- **Elaborato:** `lavoro/elaborazioni/20261030-214240/elaborato.dxf`
- **Unità:** m · **Scala:** 1:100 · **DXF:** AC1032
- **Entità:** 583 (LWPOLYLINE 229, ARC 209, LINE 77, CIRCLE 39, HATCH 25, INSERT 4)
- **Layer:** 01Mobiliario, 01Seccionado carpintería, 01Vegetacion2, 01Vegetacion1, 01Personas, 01Proyectado, 01Visto, 01Seccionado albanileria, 01Trama solido, 01Proyectado 1, 01Seccionado albañilería, 02Acabados, 0

## Problemi rilevati nel disegno

- Nessuno

## Controlli (punto 15 della specifica)

| # | Controllo | Esito | Dettaglio |
|---|---|---|---|
| 1 | Lettura | ok | 583 entità originali lette |
| 2 | Conservazione | ok | tutte le entità originali presenti e invariate |
| 3 | Layer | ok | presenti: DOMUS_CARTIGLIO, DOMUS_NOTE, DOMUS_QUOTE |
| 4 | Quote | ok | 28 quote, misure uguali alla geometria (tolleranza 1e-6), testo = misura × 100 |
| 5 | Cartiglio | ok | blocco DOMUS_CARTIGLIO con attributi, in basso a destra fuori dal disegno |
| 6 | Esportazione | ok | elaborato.dxf, 638254 byte, DXF AC1032 |
| 7 | Riapertura | ok | riaperto con ezdxf, audit: 0 errori, 0 correzioni |
| 8 | Compatibilità AutoCAD | da verificare | da aprire in AutoCAD sul Mac: in questo ambiente AutoCAD non c'è |
| 9 | Integrità dell'originale | ok | SHA-256 del file originale invariato |
| 10 | Ripetibilità | da verificare | verificata dai test automatici (due elaborazioni identiche) |
| 11 | Costo | ok | solo software open source gratuito: Python, ezdxf (MIT) |

**Esito complessivo:** superato (i controlli 'da verificare' richiedono AutoCAD).

## Registro

- Origine: main:REF.OPCION 2.dxf (SHA-256 0432e5a8951cd9da…)
- Letto DXF AC1032: 583 entità, 13 layer
- Generate 28 quote su DOMUS_QUOTE (catene parziali e totali sui 4 lati)
- Inseriti cartiglio (blocco con attributi) e nota sul layer DOMUS_NOTE
- Salvato elaborato.dxf (638254 byte)
- Creata anteprima.svg
