"""Quote automatiche (punto 8.1 della specifica): catene esterne e quote totali.

Metodo deterministico, senza AI:
1. si raccolgono i vertici di linee e polilinee sui layer scelti;
2. per ogni lato dell'edificio si prendono i vertici vicini a quel lato (una fascia
   pari allo spessore massimo dei muri);
3. si genera una catena di quote tra punti consecutivi, fuori dal disegno, e sotto
   una quota totale. Layer e stile delle quote sono quelli del profilo dello studio
   (`profilo.py`); la geometria esistente non viene toccata.

Le misure vengono SOLO dalle coordinate del disegno: niente valori stimati.
"""
from __future__ import annotations

from dataclasses import dataclass, field

from ezdxf.document import Drawing
from ezdxf.math import Vec2

from .profilo import aggiungi_quota


@dataclass(frozen=True)
class Quota:
    lato: str            # basso, alto, sinistra, destra
    livello: int         # 1 = catena parziale, 2 = totale
    p1: tuple[float, float]
    p2: tuple[float, float]
    misura: float        # in unità di disegno, dalla geometria
    angolo: float = 0.0  # direzione della misura in gradi (quote interne)
    # altre posizioni possibili della stessa quota lungo il tratto misurato: (p1, p2, misura)
    alternative: tuple = field(default=(), compare=False, repr=False)


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


def basi(quote: list[Quota], passo: float,
         ingombro: tuple[tuple[float, float], tuple[float, float]]) -> list[tuple[tuple[float, float], float]]:
    """Punto di passaggio e direzione della linea di ogni quota esterna.

    Le linee di quota stanno oltre l'ingombro di TUTTO il disegno (testi compresi),
    così non si sovrappongono a geometrie o scritte esistenti.
    """
    (xmin, ymin), (xmax, ymax) = ingombro
    out = []
    for q in quote:
        distanza = passo * q.livello
        if q.lato == "basso":
            out.append(((q.p1[0], min(ymin, q.p1[1]) - distanza), 0))
        elif q.lato == "alto":
            out.append(((q.p1[0], max(ymax, q.p1[1]) + distanza), 0))
        elif q.lato == "sinistra":
            out.append(((min(xmin, q.p1[0]) - distanza, q.p1[1]), 90))
        else:
            out.append(((max(xmax, q.p1[0]) + distanza, q.p1[1]), 90))
    return out


def disegna(doc: Drawing, quote: list[Quota], basi_quote: list, posizioni: list, layer: str, stile: str,
            override: dict | None = None) -> list[str]:
    """Aggiunge le quote; restituisce gli handle creati."""
    return [aggiungi_quota(doc, base, q.p1, q.p2, angolo, layer, stile, override or {}, testo_in=pos)
            for q, (base, angolo), pos in zip(quote, basi_quote, posizioni)]
