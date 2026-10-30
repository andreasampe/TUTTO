"""Quote automatiche (punto 8.1 della specifica): catene esterne e quote totali.

Metodo deterministico, senza AI:
1. si raccolgono i vertici di linee e polilinee sui layer scelti;
2. per ogni lato dell'edificio si prendono i vertici vicini a quel lato (una fascia
   pari allo spessore massimo dei muri);
3. si genera una catena di quote tra punti consecutivi, fuori dal disegno, e sotto
   una quota totale. Le quote stanno su un layer dedicato e non toccano la geometria.

Le misure vengono SOLO dalle coordinate del disegno: niente valori stimati.
"""
from __future__ import annotations

from dataclasses import dataclass

from ezdxf.document import Drawing
from ezdxf.math import Vec2

LAYER_QUOTE = "DOMUS_QUOTE"
STILE = "DOMUS_ARCH"


@dataclass(frozen=True)
class Quota:
    lato: str            # basso, alto, sinistra, destra
    livello: int         # 1 = catena parziale, 2 = totale
    p1: tuple[float, float]
    p2: tuple[float, float]
    misura: float        # in unità di disegno, dalla geometria


def vertici(doc: Drawing, layer: set[str] | None) -> list[Vec2]:
    msp = doc.modelspace()
    punti: list[Vec2] = []
    for e in msp.query("LINE LWPOLYLINE POLYLINE"):
        if layer and e.dxf.layer not in layer:
            continue
        if e.dxftype() == "LINE":
            punti += [Vec2(e.dxf.start), Vec2(e.dxf.end)]
        elif e.dxftype() == "LWPOLYLINE":
            punti += [Vec2(p[0], p[1]) for p in e.get_points("xy")]
        else:
            punti += [Vec2(v.dxf.location) for v in e.vertices]
    return punti


def _unici(valori: list[float], tolleranza: float) -> list[float]:
    out: list[float] = []
    for v in sorted(valori):
        if not out or v - out[-1] > tolleranza:
            out.append(v)
    return out


def calcola(punti: list[Vec2], fascia: float, tolleranza: float) -> list[Quota]:
    """Restituisce le quote previste per i quattro lati."""
    if len(punti) < 2:
        return []
    xmin = min(p.x for p in punti); xmax = max(p.x for p in punti)
    ymin = min(p.y for p in punti); ymax = max(p.y for p in punti)
    quote: list[Quota] = []
    lati = {
        "basso": ([p.x for p in punti if p.y <= ymin + fascia], ymin, "x"),
        "alto": ([p.x for p in punti if p.y >= ymax - fascia], ymax, "x"),
        "sinistra": ([p.y for p in punti if p.x <= xmin + fascia], xmin, "y"),
        "destra": ([p.y for p in punti if p.x >= xmax - fascia], xmax, "y"),
    }
    for lato, (valori, bordo, asse) in lati.items():
        tappe = _unici(valori, tolleranza)
        if len(tappe) < 2:
            continue
        def punto(v: float) -> tuple[float, float]:
            return (v, bordo) if asse == "x" else (bordo, v)
        for a, b in zip(tappe, tappe[1:]):
            quote.append(Quota(lato, 1, punto(a), punto(b), round(b - a, 9)))
        quote.append(Quota(lato, 2, punto(tappe[0]), punto(tappe[-1]), round(tappe[-1] - tappe[0], 9)))
    return quote


def prepara_stile(doc: Drawing, altezza_testo: float, fattore_misura: float, decimali: int) -> None:
    """Stile di quota architettonico: tratti obliqui, testo sopra la linea."""
    if LAYER_QUOTE not in doc.layers:
        doc.layers.add(LAYER_QUOTE, color=4)  # ciano, come da consuetudine
    if STILE in doc.dimstyles:
        return
    doc.dimstyles.new(STILE, dxfattribs={
        "dimtxt": altezza_testo,
        "dimasz": altezza_testo * 0.8,
        "dimtsz": altezza_testo * 0.6,   # tick obliqui al posto delle frecce
        "dimexe": altezza_testo * 0.6,
        "dimexo": altezza_testo * 0.4,
        "dimgap": altezza_testo * 0.3,
        "dimtad": 1,                     # testo sopra la linea
        "dimdec": decimali,
        "dimlfac": fattore_misura,       # es. da metri a centimetri
        "dimzin": 8,
        "dimclrd": 4, "dimclre": 4, "dimclrt": 7,
    })


def disegna(doc: Drawing, quote: list[Quota], passo: float,
            ingombro: tuple[tuple[float, float], tuple[float, float]]) -> list[str]:
    """Aggiunge le quote fuori dal disegno; restituisce gli handle creati.

    Le linee di quota partono oltre l'ingombro di TUTTO il disegno (testi compresi),
    così non si sovrappongono a geometrie o scritte esistenti.
    """
    msp = doc.modelspace()
    (xmin, ymin), (xmax, ymax) = ingombro
    handles: list[str] = []
    for q in quote:
        distanza = passo * q.livello
        if q.lato == "basso":
            base, angolo = (q.p1[0], min(ymin, q.p1[1]) - distanza), 0
        elif q.lato == "alto":
            base, angolo = (q.p1[0], max(ymax, q.p1[1]) + distanza), 0
        elif q.lato == "sinistra":
            base, angolo = (min(xmin, q.p1[0]) - distanza, q.p1[1]), 90
        else:
            base, angolo = (max(xmax, q.p1[0]) + distanza, q.p1[1]), 90
        dim = msp.add_linear_dim(base=base, p1=q.p1, p2=q.p2, angle=angolo, dimstyle=STILE,
                                 dxfattribs={"layer": LAYER_QUOTE})
        dim.render()
        handles.append(dim.dimension.dxf.handle)
    return handles
