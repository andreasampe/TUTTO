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
| 3 | Layer | ok | presenti: 01Cotas 1, DOMUS_CARTIGLIO, DOMUS_NOTE |
| 4 | Quote | ok | 97 quote, misure uguali alla geometria (tolleranza 1e-6), testo = misura × 1 |
| 5 | Cartiglio | ok | blocco DOMUS_CARTIGLIO con attributi, in basso a destra fuori dal disegno |
| 6 | Esportazione | ok | elaborato.dxf, 767404 byte, DXF AC1032 |
| 7 | Riapertura | ok | riaperto con ezdxf, audit: 0 errori, 0 correzioni |
| 8 | Compatibilità AutoCAD | da verificare | da aprire in AutoCAD sul Mac: in questo ambiente AutoCAD non c'è |
| 9 | Integrità dell'originale | ok | SHA-256 del file originale invariato |
| 10 | Ripetibilità | da verificare | verificata dai test automatici (due elaborazioni identiche) |
| 11 | Costo | ok | solo software open source gratuito: Python, ezdxf (MIT), Pillow (MIT-CMU), NumPy (BSD) |

**Esito complessivo:** superato (i controlli 'da verificare' richiedono AutoCAD).

## Registro

- Origine: main: REF.OPCION 2.dxf (SHA-256 0432e5a8951cd9da…)
- Letto DXF AC1032: 583 entità, 13 layer
- Quote sul layer '01Cotas 1' con lo stile '50' del profilo e le modifiche {'dimscale': 1.0}
- Generate 28 quote esterne (catene parziali e totali sui 4 lati), muri: 01Seccionado albanileria, 01Seccionado albañilería, 01Trama solido
- Generate 69 quote interne: luci tra muri (minimo 0.5 m) e distanze degli arredi (minimo 0.3 m)
- Limiti: 01Visto · arredi: 01Mobiliario · porte e finestre: 01Seccionado carpintería
- Testi spostati per non toccarsi: 18 su 97
- Inseriti cartiglio (blocco con attributi) e nota sul layer DOMUS_NOTE
- Salvato elaborato.dxf (767404 byte)
- Creata anteprima.svg

## Comando usato

```bash
python -m domus_cad elabora "REF.OPCION 2.dxf" --uscita lavori/vivienda-moya --progetto "Vivienda Moya - opción 2"
```

Layer, stile e scala vengono dal profilo `profili/studio.json`, ricavato in automatico dal file di riferimento
`03_BASICO_VIVIENDA MOYA OPCION 2-3.dxf` con `python -m domus_cad profilo`.

## Confronto con le quote dello studio (layer `01Cotas 1` di `03_BASICO`)

- Stesso layer (`01Cotas 1`), stesso stile (`50`), stessa modifica per quota (DIMSCALE 1): lo stile del file non viene toccato.
- Quote uguali al riferimento: 7.20, 4.00, 3.50, 2.20, 1.60, 1.50, 1.40, 1.30, 1.00, 0.90 (tavolo-cucina), 0.80, e le misure del tavolo 1.50 e 0.80.
- Differenze ancora visibili:
  - nel giardino in basso compaiono quote muro-recinzione (2.85) che lo studio non mette;
  - qualche testo spostato resta vicino a linee del disegno (si evitano solo gli altri testi).
- Misure dentro gli arredi: una sola quota per misura e per locale (moduli 0.60); parti diverse attaccate unite (letto + cuscino = 1.95 e 2.00).
- Niente quote ripetute: stessa misura e direzione a meno di 2 m nello stesso locale = una quota.
- Quote verso un muro inclinato: perpendicolari a quel muro (2.09, 2.11, 1.35, garage 3.32).
- Disposizione: la linea si sposta lungo il tratto misurato e il testo resta tra i suoi pallini o subito accanto (18 testi spostati su 97).
