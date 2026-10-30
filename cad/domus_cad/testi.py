"""Disposizione delle quote: linee e testi che si accavallano il meno possibile (punto 8.1).

Ogni quota interna può stare in più posizioni lungo il tratto che misura (`Quota.alternative`):
la linea si può spostare, per esempio più vicina a un'estremità del tavolo invece che al centro.
Per ogni posizione della linea il testo può stare:
1. al centro della linea (posizione normale, gestita da AutoCAD);
2. lungo la linea, ma sempre TRA i due pallini. Oltre i pallini AutoCAD allungherebbe la
   linea fino al testo, e la linea andrebbe oltre il punto;
3. subito sopra o sotto la linea, accanto ad essa, così si capisce a quale quota appartiene.

Si sceglie la combinazione con meno sovrapposizioni con testi e linee già disposti. Le quote
più lunghe si dispongono per prime. Le misure non cambiano: cambia solo dove si disegnano.
"""
from __future__ import annotations

import math

LARGHEZZA_CARATTERE = 0.9   # larghezza media di una cifra rispetto all'altezza (con margine per i caratteri larghi)
MARGINE = 0.3               # spazio libero attorno al testo, rispetto all'altezza
PALLINO = 0.8               # spazio lasciato libero alle estremità per i pallini, rispetto all'altezza


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


def _taglia(seg, box) -> bool:
    """Vero se il segmento attraversa il rettangolo (algoritmo di Liang-Barsky)."""
    (x1, y1), (x2, y2) = seg
    dx, dy = x2 - x1, y2 - y1
    t0, t1 = 0.0, 1.0
    for p, q in ((-dx, x1 - box[0]), (dx, box[2] - x1), (-dy, y1 - box[1]), (dy, box[3] - y1)):
        if p == 0:
            if q < 0:
                return False
        else:
            r = q / p
            if p < 0:
                t0 = max(t0, r)
            else:
                t1 = min(t1, r)
            if t0 > t1:
                return False
    return True


def _linea(base, p1, p2, angolo):
    """Estremi della linea di quota: proiezioni dei punti misurati sulla retta per `base`."""
    a = math.radians(angolo)
    u = (math.cos(a), math.sin(a))
    t1 = (p1[0] - base[0]) * u[0] + (p1[1] - base[1]) * u[1]
    t2 = (p2[0] - base[0]) * u[0] + (p2[1] - base[1]) * u[1]
    return (base[0] + u[0] * t1, base[1] + u[1] * t1), (base[0] + u[0] * t2, base[1] + u[1] * t2)


def disponi(quote: list[dict], altezza: float, orizzontale: bool, fattore_larghezza: float = 1.0
            ) -> list[tuple[int, tuple[float, float] | None]]:
    """Per ogni quota {"alternative": [{"base", "p1", "p2", "angolo"}, ...], "testo"} restituisce
    (indice della posizione scelta, posizione del testo oppure None se il testo resta al centro)."""
    h = altezza
    margine = MARGINE * h
    lunghezza = lambda q: math.dist(q["alternative"][0]["p1"], q["alternative"][0]["p2"])  # noqa: E731
    ordine = sorted(range(len(quote)), key=lambda k: -lunghezza(quote[k]))
    testi_messi: list[tuple] = []
    linee_messe: list[tuple] = []
    risultato: list[tuple[int, tuple[float, float] | None]] = [(0, None)] * len(quote)
    for k in ordine:
        q = quote[k]
        w = len(q["testo"]) * LARGHEZZA_CARATTERE * h * fattore_larghezza
        migliore, minimo = None, math.inf
        for ia, alt in enumerate(q["alternative"]):
            a = math.radians(alt["angolo"])
            u, n = (math.cos(a), math.sin(a)), (-math.sin(a), math.cos(a))
            e1, e2 = _linea(alt["base"], alt["p1"], alt["p2"], alt["angolo"])
            lung = math.dist(e1, e2)
            centro = ((e1[0] + e2[0]) / 2, (e1[1] + e2[1]) / 2)
            rotazione = 0.0 if orizzontale else alt["angolo"]
            sc = _scatola(0, 0, w, h, rotazione)
            lungo_linea = abs((sc[2] - sc[0]) * u[0]) + abs((sc[3] - sc[1]) * u[1])
            di_lato = abs((sc[2] - sc[0]) * n[0]) + abs((sc[3] - sc[1]) * n[1])
            libero = (lung - lungo_linea) / 2 - PALLINO * h  # quanto il testo può scorrere dal centro
            entra = libero >= 0
            scorrimenti = [0.0] + ([s * f * libero for f in (0.5, 1.0) for s in (1, -1)] if entra else [])
            accanto = di_lato / 2 + 0.25 * h
            prove = [(t, o) for t in scorrimenti for o in ((0.0, accanto, -accanto) if entra else (accanto, -accanto))]
            # la linea non deve attraversare testi già messi
            pen_linea = sum(5 for b in testi_messi if _taglia((e1, e2), b))
            for t, o in prove:
                cx = centro[0] + u[0] * t + n[0] * o
                cy = centro[1] + u[1] * t + n[1] * o
                s = _scatola(cx, cy, w + 2 * margine, h + 2 * margine, rotazione)
                costo = pen_linea
                costo += 10 * sum(_sovrapposizione(s, b) for b in testi_messi) / (h * h)
                costo += 3 * sum(1 for l in linee_messe if _taglia(l, s))
                costo += 0.4 * ia + 0.2 * abs(t) / max(lung, 1e-9) + (0.3 if o else 0.0)
                if costo < minimo:
                    migliore, minimo = (ia, (cx, cy), s, (e1, e2), (t, o)), costo
            if minimo < 0.5:  # già una posizione pulita: inutile provare le altre
                break
        ia, pos, s, linea, (t, o) = migliore
        testi_messi.append(s)
        linee_messe.append(linea)
        risultato[k] = (ia, None if (t, o) == (0.0, 0.0) else pos)
    return risultato
