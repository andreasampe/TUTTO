# Architettura

## Livelli (punto 11 della specifica)

```
 Interfaccia web (DOMUS, cartella ../domus)        ← Fase 6: progetti, upload, download
            │ richiama da riga di comando
 Motore CAD  domus_cad/                             ← questo pacchetto, indipendente dal web
   ├─ lettura.py     lettura DXF, rapporto e problemi del disegno     (punto 4.A)
   ├─ profilo.py     profilo dello studio: layer e stile delle quote, ruoli dei layer,
   │                 riconosciuti in automatico dal file di riferimento
   ├─ quote.py       catene di quote esterne                          (punto 8.1)
   ├─ interne.py     luci interne e distanze degli arredi             (punto 8.1)
   ├─ cartiglio.py   cartiglio a blocco con attributi, note            (punto 8.1)
   └─ elabora.py     flusso completo, verifiche, anteprima, rapporto   (punti 14-15)
            │ usa
 ezdxf (MIT)                                         ← sostituibile senza toccare il web
```

- Il **modello dell'edificio** (punto 7) non c'è ancora: è la Fase 3. Il prototipo lavora sulla geometria e non pretende di "capire" l'edificio. Un vertice è solo un vertice, non un muro.
- Ogni modulo riceve un documento e dei parametri, e restituisce entità su **layer dedicati** (`DOMUS_QUOTE`, `DOMUS_NOTE`, `DOMUS_CARTIGLIO`). La geometria originale non viene mai toccata.

## Flusso dei file (punto 11.7)

```
<progetto>/
  originali/pianta.dxf                  copia dell'originale, controllata con SHA-256
  elaborazioni/AAAAMMGG-hhmmss/
     elaborato.dxf                      nuovo file (stessa versione DXF dell'originale)
     anteprima.svg                      anteprima A3
     log.json                           dati completi: lettura, quote previste, controlli
     rapporto.md                        rapporto leggibile con l'esito dei test 1-11
```

Ogni elaborazione crea una nuova cartella. Niente viene sovrascritto e l'originale non cambia (test 9).

## Quote: metodo

1. Si raccolgono i vertici di LINE, LWPOLYLINE e POLYLINE, anche solo sui layer indicati con `--layer-muri`.
2. Per ogni lato si prendono i vertici entro una fascia di 60 cm dal bordo esterno, che corrisponde allo spessore massimo atteso dei muri.
3. Si ordinano e si fondono i punti più vicini di 5 mm. Si creano le **catene parziali** (livello 1) e la **quota totale** (livello 2), posizionate oltre l'ingombro di tutto il disegno, testi compresi.
4. Il testo delle quote nasce dalla misura geometrica moltiplicata per un fattore di conversione: per esempio metri × 100 = centimetri. Non si scrive mai un numero a mano.

**Limiti noti:**
- su facciate a gradoni la catena di un lato copre solo la parte più esterna;
- le quote molto corte (10 cm) affollano il testo;
- non ci sono ancora quote interne né quote dei locali.

Sono tutti nella Roadmap.

## Unità e scala

Le unità vengono lette da `$INSUNITS`. Se mancano, l'elaborazione si **ferma** e chiede `--unita` (punto 8.1). Se l'unità indicata non coincide con quella del file, si ferma anche lì. Testi, frecce e cartiglio sono dimensionati in millimetri di carta e convertiti con la scala, quindi alla scala 1:100 un testo di 2,5 mm resta 2,5 mm sulla stampa.
