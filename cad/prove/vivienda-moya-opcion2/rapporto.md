# Rapporto di elaborazione DOMUS (prototipo)

- **Origine:** `main: REF.OPCION 2.dxf`
- **Elaborato:** `prove/vivienda-moya-opcion2/REF.OPCION 2 - quotato.dxf` (anche compresso in `opcion2_quotato.zip`)
- **Unità:** m · **Scala:** 1:50 · **DXF:** AC1032
- **Entità:** 583 (LWPOLYLINE 229, ARC 209, LINE 77, CIRCLE 39, HATCH 25, INSERT 4)
- **Layer:** 01Mobiliario, 01Seccionado carpintería, 01Vegetacion2, 01Vegetacion1, 01Personas, 01Proyectado, 01Visto, 01Seccionado albanileria, 01Trama solido, 01Proyectado 1, 01Seccionado albañilería, 02Acabados, 0

## Problemi rilevati nel disegno

- Nessuno

## Controlli (punto 15 della specifica)

| # | Controllo | Esito | Dettaglio |
|---|---|---|---|
| 1 | Lettura | ok | 583 entità originali lette |
| 2 | Conservazione | ok | tutte le entità originali presenti e invariate |
| 3 | Layer | ok | presenti: DOMUS_CARTIGLIO, DOMUS_NOTE, DOMUS_QUOTE, DOMUS_QUOTE_INTERNE |
| 4 | Quote | ok | 50 quote, misure uguali alla geometria (tolleranza 1e-6), testo = misura × 1 |
| 5 | Cartiglio | ok | blocco DOMUS_CARTIGLIO con attributi, in basso a destra fuori dal disegno |
| 6 | Esportazione | ok | elaborato.dxf, 695981 byte, DXF AC1032 |
| 7 | Riapertura | ok | riaperto con ezdxf, audit: 0 errori, 0 correzioni |
| 8 | Compatibilità AutoCAD | da verificare | da aprire in AutoCAD sul Mac: in questo ambiente AutoCAD non c'è |
| 9 | Integrità dell'originale | ok | SHA-256 del file originale invariato |
| 10 | Ripetibilità | da verificare | verificata dai test automatici (due elaborazioni identiche) |
| 11 | Costo | ok | solo software open source gratuito: Python, ezdxf (MIT), Pillow (MIT-CMU), NumPy (BSD) |

**Esito complessivo:** superato (i controlli 'da verificare' richiedono AutoCAD).

## Registro

- Origine: main: REF.OPCION 2.dxf (SHA-256 0432e5a8951cd9da…)
- Letto DXF AC1032: 583 entità, 13 layer
- Generate 28 quote esterne su DOMUS_QUOTE (catene parziali e totali sui 4 lati)
- Generate 22 quote interne su DOMUS_QUOTE_INTERNE (luci nette tra muri affacciati, minimo 0.5 m)
- Edificio chiuso con i layer: 01Seccionado carpintería; parte esterna del riquadro: 60%
- Inseriti cartiglio (blocco con attributi) e nota sul layer DOMUS_NOTE
- Salvato elaborato.dxf (695981 byte)
- Creata anteprima.svg

## Comando usato

```bash
python -m domus_cad elabora "REF.OPCION 2.dxf" --uscita lavori/vivienda-moya --scala 50 \
  --layer-muri "01Seccionado albañilería" "01Seccionado albanileria" "01Trama solido" \
  --progetto "Vivienda Moya - opción 2"
```

## Confronto con le quote dello studio (layer `01Cotas 1` di `03_BASICO`)

Quote interne uguali al riferimento: 7.20, 4.00, 3.50 (due), 1.50, 1.40, 1.00, 0.90.
Mancano rispetto al riferimento:
- le distanze tra arredi e muri (0.90 tra tavolo e cucina, 0.80…), perché qui si misura solo da muro a muro;
- alcune profondità delle camere, quando il muro di fronte è su un layer non quotato o non c'è (garage).
