"""Posizione dei testi delle quote: niente testi che si toccano (punto 8.1).

Di norma il testo sta al centro della linea di quota. Se toccherebbe il testo di un'altra
quota, si prova nell'ordine:
1. sopra o sotto la linea, al centro;
2. spostato lungo la linea, anche oltre le estremità;
3. combinando le due cose.
Si prende la prima posizione libera. Se nessuna è libera, quella che si sovrappone di meno.
Le quote più lunghe si sistemano per prime e tengono il testo al centro; le quote corte
cedono il posto. Le misure non cambiano: si sposta solo la scritta.
"""
from __future__ import annotations

import math

LARGHEZZA_CARATTERE = 0.9   # larghezza media di una cifra rispetto all'altezza (con margine per i caratteri larghi)
MARGINE = 0.3               # spazio libero attorno al testo, rispetto all'altezza


def testo(misura: float, fattore: float, decimali: int) -> str:
    return f"{misura * fattore:.{decimali}f}"


def _scatola(cx: float, cy: float, w: float, h: float, angolo: float) -> tuple[float, float, float, float]:
    """Rettangolo d'ingombro allineato agli assi di un testo w × h ruotato di `angolo` gradi."""
    a = math.radians(angolo)
    ew = abs(w * math.cos(a)) + abs(h * math.sin(a))
    eh = abs(w * math.sin(a)) + abs(h * math.cos(a))
    return cx - ew / 2, cy - eh / 2, cx + ew / 2, cy + eh / 2


def _sovrapposizione(a, b) -> float:
    dx = min(a[2], b[2]) - max(a[0], b[0])
    dy = min(a[3], b[3]) - max(a[1], b[1])
    return dx * dy if dx > 0 and dy > 0 else 0.0


def disponi(quote: list[dict], altezza: float, orizzontale: bool, fattore_larghezza: float = 1.0
            ) -> list[tuple[float, float] | None]:
    """Per ogni quota {"base", "p1", "p2", "angolo", "testo"} restituisce la posizione del
    testo, oppure None se resta al centro (posizione automatica di AutoCAD)."""
    h = altezza
    margine = MARGINE * h
    ordine = sorted(range(len(quote)), key=lambda k: -math.dist(quote[k]["p1"], quote[k]["p2"]))
    occupate: list[tuple] = []
    risultato: list[tuple[float, float] | None] = [None] * len(quote)
    for k in ordine:
        q = quote[k]
        a = math.radians(q["angolo"])
        u, n = (math.cos(a), math.sin(a)), (-math.sin(a), math.cos(a))
        bx, by = q["base"]
        t1 = (q["p1"][0] - bx) * u[0] + (q["p1"][1] - by) * u[1]
        t2 = (q["p2"][0] - bx) * u[0] + (q["p2"][1] - by) * u[1]
        tmin, tmax = min(t1, t2), max(t1, t2)
        w = len(q["testo"]) * LARGHEZZA_CARATTERE * h * fattore_larghezza
        rotazione = 0.0 if orizzontale else q["angolo"]
        scat = _scatola(0, 0, w, h, rotazione)
        lungo_linea = abs((scat[2] - scat[0]) * u[0]) + abs((scat[3] - scat[1]) * u[1])  # ingombro lungo la linea
        di_lato = abs((scat[2] - scat[0]) * n[0]) + abs((scat[3] - scat[1]) * n[1])     # ingombro di traverso
        centro = (tmin + tmax) / 2
        passo = lungo_linea * 0.6
        lungo = [0.0] + [s * i * passo for i in range(1, 8) for s in (1, -1)]
        lato = [0.0, di_lato / 2 + 0.4 * h, -(di_lato / 2 + 0.4 * h)]
        prove = [(0.0, 0.0), (0.0, lato[1]), (0.0, lato[2])] + \
                [(t, o) for t in lungo[1:] for o in lato
                 if tmin - lungo_linea <= centro + t <= tmax + lungo_linea]
        migliore, minimo = None, math.inf
        for t, o in prove:
            cx = bx + u[0] * (centro + t) + n[0] * o
            cy = by + u[1] * (centro + t) + n[1] * o
            s = _scatola(cx, cy, w + 2 * margine, h + 2 * margine, rotazione)
            area = sum(_sovrapposizione(s, x) for x in occupate)
            if area < minimo:
                migliore, minimo = ((cx, cy), s, (t, o)), area
            if area == 0:
                break
        (cx, cy), s, (t, o) = migliore
        occupate.append(s)
        risultato[k] = None if (t, o) == (0.0, 0.0) else (cx, cy)
    return risultato
