"""Quote interne: luci nette tra le facce dei muri affacciate (punto 8.1 della specifica).

Riferimento: le quote del layer `01Cotas 1` nei disegni dello studio. Dentro ogni locale
si misura da muro a muro, con la linea di quota che attraversa la stanza.

Si quotano:
- le luci tra muri (anche tra un muro e una linea di limite, per esempio il garage aperto);
- le distanze tra arredi e muri e tra arredi, come nel riferimento (0.90 tra tavolo e cucina).

Metodo deterministico, senza AI:
1. si prendono i segmenti dei layer di muri, limiti e arredi (linee, polilinee, blocchi, archi spezzati);
2. da ogni faccia si lanciano raggi perpendicolari, ogni 5 cm, verso entrambi i lati;
   il primo muro colpito, se è parallelo alla faccia (entro 15°), dà una luce netta;
3. i raggi consecutivi che colpiscono lo stesso muro formano un tratto: la quota va
   a metà del tratto più lungo;
4. si scartano:
   - le luci più corte della luce minima (spessori dei muri);
   - i tratti troppo brevi (spalle delle porte);
   - le quote che passano da una porta e misurano due locali insieme. Per riconoscerle,
     il disegno viene rasterizzato e i varchi stretti vengono chiusi (chiusura morfologica);
   le zone aperte (garage, giardini) si quotano comunque, come sono disegnate;
5. le misure uguali tra le stesse due linee nello stesso locale si contano una volta sola.

Le misure vengono SOLO dalle coordinate del disegno.
"""
from __future__ import annotations

import math

import numpy as np
from ezdxf import path as dxfpath
from ezdxf.document import Drawing

from .profilo import aggiungi_quota
from .quote import Quota

LIBERO, OSTACOLO, ESTERNO = 0, 1, 2


def geometria(doc: Drawing, layer: set[str] | None, tolleranza: float, m_per_u: float = 1.0
              ) -> tuple[np.ndarray, list[list[tuple[float, float]]]]:
    """Segmenti (N, 4) x1 y1 x2 y2 delle entità sui layer indicati e masse murarie piene.

    Le curve vengono spezzate. Sono masse piene:
    - le campiture (HATCH), perché nei disegni dello studio i muri esterni sono campiti;
    - le polilinee chiuse sottili (area / perimetro < 0,25 m, cioè muri spessi fino a 50 cm):
      un contorno di stanza disegnato chiuso non viene riempito.
    """
    segmenti: list[tuple[float, float, float, float]] = []
    masse: list[list[tuple[float, float]]] = []
    for e in _entita(doc.modelspace(), layer):
        try:
            percorsi = dxfpath.from_hatch(e) if e.dxftype() == "HATCH" else [dxfpath.make_path(e)]
            for percorso in percorsi:
                punti = [(v.x, v.y) for v in percorso.flattening(tolleranza)]
                if len(punti) < 2:
                    continue
                chiuso = e.dxftype() == "HATCH" or math.dist(punti[0], punti[-1]) < tolleranza
                if e.dxftype() == "HATCH" and punti[0] != punti[-1]:
                    punti.append(punti[0])
                segmenti += [(a[0], a[1], b[0], b[1]) for a, b in zip(punti, punti[1:]) if a != b]
                if chiuso and len(punti) > 3 and (e.dxftype() == "HATCH" or _sottile(punti, 0.25 / m_per_u)):
                    masse.append(punti)
        except (TypeError, ValueError, AttributeError):
            continue
    return np.array(segmenti, dtype=float).reshape(-1, 4), masse


def ingombri(doc: Drawing, layer: set[str], m_per_u: float = 1.0, area_max: float = 6.0) -> np.ndarray:
    """Rettangoli d'ingombro (K, 4) xmin ymin xmax ymax di ogni entità sui layer (es. ogni arredo).

    Si escludono gli oggetti più grandi di `area_max` m² (contorni del lotto, recinzioni):
    sono limiti, non oggetti da non attraversare."""
    from ezdxf import bbox

    cache = bbox.Cache()
    out = []
    for e in doc.modelspace():
        if e.dxf.layer in layer and e.dxftype() not in ("TEXT", "MTEXT", "DIMENSION"):
            try:
                b = bbox.extents([e], cache=cache)
            except Exception:
                continue
            if b.has_data and b.size.x * b.size.y * m_per_u ** 2 <= area_max:
                out.append((b.extmin.x, b.extmin.y, b.extmax.x, b.extmax.y))
    return np.array(out, dtype=float).reshape(-1, 4)


def _entita(entita, layer: set[str] | None, profondita: int = 0):
    """Entità geometriche sui layer indicati; i blocchi vengono esplosi (solo in memoria)."""
    for e in entita:
        if layer and e.dxf.layer not in layer:
            continue
        if e.dxftype() == "INSERT" and profondita < 4:
            try:
                yield from _entita(e.virtual_entities(), None, profondita + 1)
            except Exception:  # blocco non esplodibile: si ignora, non si inventa geometria
                continue
        elif e.dxftype() in ("LINE", "LWPOLYLINE", "POLYLINE", "ARC", "CIRCLE", "SPLINE", "ELLIPSE", "HATCH"):
            yield e


def _sottile(punti: list[tuple[float, float]], soglia: float) -> bool:
    area = abs(sum(a[0] * b[1] - b[0] * a[1] for a, b in zip(punti, punti[1:] + punti[:1]))) / 2
    perimetro = sum(math.dist(a, b) for a, b in zip(punti, punti[1:] + punti[:1]))
    return perimetro > 0 and area / perimetro < soglia


def _dilata(maschera: np.ndarray, raggio: int) -> np.ndarray:
    """Dilatazione con un quadrato di lato 2*raggio+1 (massimi a finestra, per righe e colonne)."""
    out = maschera
    for asse in (0, 1):
        finestra = 2 * raggio + 1
        m = np.pad(out, [(raggio, raggio) if a == asse else (0, 0) for a in (0, 1)])
        acc, k = m.copy(), 1
        while 2 * k <= finestra:  # acc[i] = max di m[i : i + 2k]
            acc[tuple(slice(None, -k) if a == asse else slice(None) for a in (0, 1))] |= \
                acc[tuple(slice(k, None) if a == asse else slice(None) for a in (0, 1))]
            k *= 2
        resto = finestra - k
        n = out.shape[asse]
        primo = acc[tuple(slice(0, n) if a == asse else slice(None) for a in (0, 1))]
        secondo = acc[tuple(slice(resto, resto + n) if a == asse else slice(None) for a in (0, 1))]
        out = primo | secondo
    return out


class Mappa:
    """Raster del disegno: libero, ostacolo (muri e serramenti) o esterno.

    L'esterno è ciò che si raggiunge dal bordo dopo aver chiuso i varchi stretti
    (porte e finestre fino a 2 × `varco` metri), anche se i serramenti non sono disegnati.
    I locali stretti (corridoi) restano interni, perché la chiusura serve solo a trovare l'esterno.
    """

    def __init__(self, seg: np.ndarray, masse: list[list[tuple[float, float]]], m_per_u: float, varco: float = 0.6,
                 oggetti: np.ndarray | None = None, cella_max: float = 1.2):
        from PIL import Image, ImageDraw

        margine = (varco + 1.0) / m_per_u
        self.x0 = seg[:, [0, 2]].min() - margine
        self.y0 = seg[:, [1, 3]].min() - margine
        larg = seg[:, [0, 2]].max() + margine - self.x0
        alt = seg[:, [1, 3]].max() + margine - self.y0
        self.passo = max(0.02 / m_per_u, max(larg, alt) / 3000)
        w, h = int(larg / self.passo) + 2, int(alt / self.passo) + 2
        img = Image.new("L", (w, h), LIBERO)
        disegno = ImageDraw.Draw(img)
        spessore = max(2, round(0.03 / m_per_u / self.passo))
        for poligono in masse:
            disegno.polygon([self._px(x, y) for x, y in poligono], fill=OSTACOLO)
        for x1, y1, x2, y2 in seg:
            disegno.line([self._px(x1, y1), self._px(x2, y2)], fill=OSTACOLO, width=spessore)
        ostacoli = np.asarray(img) == OSTACOLO
        r = max(1, round(varco / m_per_u / self.passo))
        chiuso = ~_dilata(~_dilata(ostacoli, r), r)  # chiusura morfologica: sigilla i varchi
        allagato = Image.fromarray(np.where(chiuso, OSTACOLO, LIBERO).astype(np.uint8)).copy()  # copia scrivibile
        ImageDraw.floodfill(allagato, (0, 0), ESTERNO)
        esterno = (np.asarray(allagato) == ESTERNO) & ~ostacoli
        self.dati = np.where(ostacoli, OSTACOLO, np.where(esterno, ESTERNO, LIBERO)).astype(np.uint8)
        self.varchi = chiuso & ~ostacoli  # porte, passaggi e locali più stretti di 2 × varco
        self.stanze = self._stanze(~chiuso)
        # celle piccole chiuse da arredi e limiti (moduli della cucina, ripiani, gradini): dentro non si quota
        self.dentro_oggetti = np.zeros(ostacoli.shape, bool)
        if oggetti is not None and len(oggetti):
            for x1, y1, x2, y2 in oggetti:
                disegno.line([self._px(x1, y1), self._px(x2, y2)], fill=OSTACOLO, width=spessore)
            celle = self._stanze(np.asarray(img) == LIBERO)
            numeri, aree = np.unique(celle[celle > 0], return_counts=True)
            piccole = numeri[aree * (self.passo * m_per_u) ** 2 <= cella_max]
            self.dentro_oggetti = np.isin(celle, piccole)

    @staticmethod
    def _stanze(libero: np.ndarray) -> np.ndarray:
        """Numera i locali: zone interne separate da muri e varchi chiusi (-1 = nessun locale)."""
        from PIL import Image, ImageDraw

        img = Image.fromarray(np.where(libero, 0, -1).astype(np.int32)).copy()  # modo "I", copia scrivibile
        n = 0
        for r, c in np.argwhere(libero):
            if img.getpixel((int(c), int(r))) == 0:  # pixel libero non ancora numerato
                n += 1
                ImageDraw.floodfill(img, (int(c), int(r)), n)
        etichette = np.asarray(img).astype(np.int32)
        etichette[etichette <= 0] = -1
        return etichette

    def _px(self, x: float, y: float) -> tuple[int, int]:
        return int((x - self.x0) / self.passo), int((y - self.y0) / self.passo)

    def valore(self, x: float, y: float) -> int:
        c, r = self._px(x, y)
        h, w = self.dati.shape
        return int(self.dati[r, c]) if 0 <= r < h and 0 <= c < w else ESTERNO

    def in_oggetto(self, x: float, y: float) -> bool:
        c, r = self._px(x, y)
        h, w = self.dentro_oggetti.shape
        return bool(0 <= r < h and 0 <= c < w and self.dentro_oggetti[r, c])

    def locale(self, p: tuple[float, float], q: tuple[float, float]) -> int | None:
        """Locale attraversato dalla linea p-q, oppure None se la quota va scartata perché:
        - tocca l'esterno;
        - corre dentro un muro;
        - passa da una porta e misura due locali insieme.
        Le zone aperte (garage, giardini) sono valide: si quota ciò che è disegnato.
        Un locale stretto (corridoio) dà -1: è valido ma non numerato."""
        h, w = self.dati.shape
        valori, varchi, stanze = [], [], []
        for f in np.linspace(0.05, 0.95, 19):
            c, r = self._px(p[0] + (q[0] - p[0]) * f, p[1] + (q[1] - p[1]) * f)
            if not (0 <= r < h and 0 <= c < w):
                return None
            valori.append(int(self.dati[r, c])); varchi.append(bool(self.varchi[r, c])); stanze.append(int(self.stanze[r, c]))
        if valori.count(OSTACOLO) > 0.4 * len(valori):
            return None
        quota_varchi = sum(varchi) / len(varchi)
        if 0.03 < quota_varchi < 0.7:
            return None
        numerate = [s for s in stanze if s >= 0]
        if len(set(numerate)) > 1:
            return None
        return numerate[0] if numerate else -1

    @property
    def quota_esterna(self) -> float:
        return float((self.dati == ESTERNO).mean())


def _raggi(seg: np.ndarray, origini: np.ndarray, direzioni: np.ndarray, massimo: float) -> tuple[np.ndarray, np.ndarray]:
    """Per ogni raggio: indice del primo segmento colpito (-1 se nessuno) e distanza."""
    a, e = seg[:, :2], seg[:, 2:] - seg[:, :2]
    indici = np.full(len(origini), -1)
    distanze = np.full(len(origini), np.inf)
    for i0 in range(0, len(origini), 2000):  # a blocchi, per non usare troppa memoria
        o, d = origini[i0:i0 + 2000, None, :], direzioni[i0:i0 + 2000, None, :]
        den = d[..., 0] * e[None, :, 1] - d[..., 1] * e[None, :, 0]
        ao = a[None] - o
        with np.errstate(divide="ignore", invalid="ignore"):
            t = (ao[..., 0] * e[None, :, 1] - ao[..., 1] * e[None, :, 0]) / den
            u = (ao[..., 0] * d[..., 1] - ao[..., 1] * d[..., 0]) / den
        valido = (np.abs(den) > 1e-12) & (t > 1e-9) & (t <= massimo) & (u >= -1e-9) & (u <= 1 + 1e-9)
        t = np.where(valido, t, np.inf)
        j = t.argmin(axis=1)
        tmin = t[np.arange(len(j)), j]
        colpito = np.isfinite(tmin)
        indici[i0:i0 + len(j)] = np.where(colpito, j, -1)
        distanze[i0:i0 + len(j)] = tmin
    return indici, distanze


def calcola(seg_muri: np.ndarray, mappa: Mappa | None, m_per_u: float, *, arredo: np.ndarray | None = None,
            ingombri_arredi: np.ndarray | None = None,
            passo: float = 0.05, luce_minima: float = 0.5, luce_arredi: float = 0.3, tratto_minimo: float = 0.3,
            angolo_max: float = 15.0) -> list[Quota]:
    """Luci nette e distanze degli arredi, in unità di disegno. I parametri sono in metri.

    `arredo[i]` è vero se il segmento i è un arredo: le misure che lo toccano hanno
    come minimo `luce_arredi` invece di `luce_minima`.
    Le misure DENTRO un arredo (un modulo della cucina, il tavolo, un ripiano) si quotano
    una volta sola per locale: moduli uguali, per esempio 60x60, hanno una sola quota."""
    if len(seg_muri) == 0:
        return []
    passo_u, minima_u, tratto_u = passo / m_per_u, luce_minima / m_per_u, tratto_minimo / m_per_u
    arredo = np.zeros(len(seg_muri), bool) if arredo is None else arredo
    arredi_u = luce_arredi / m_per_u
    lunghezze = np.hypot(seg_muri[:, 2] - seg_muri[:, 0], seg_muri[:, 3] - seg_muri[:, 1])
    unitari = (seg_muri[:, 2:] - seg_muri[:, :2]) / np.where(lunghezze > 0, lunghezze, 1)[:, None]
    seno_max = math.sin(math.radians(angolo_max))

    # raggi: per ogni faccia abbastanza lunga, campioni ogni 'passo' e due versi
    origini, direzioni, faccia, verso, posizione = [], [], [], [], []
    for i, (l, u) in enumerate(zip(lunghezze, unitari)):
        if l < tratto_u:
            continue
        n = max(2, int(l / passo_u))
        s = np.linspace(passo_u / 2, l - passo_u / 2, n)
        punti = seg_muri[i, :2] + s[:, None] * u
        for segno in (1, -1):
            normale = np.array([-u[1], u[0]]) * segno
            origini.append(punti + normale * 1e-6 / m_per_u)
            direzioni.append(np.repeat(normale[None], n, axis=0))
            faccia += [i] * n; verso += [segno] * n; posizione.append(s)
    if not origini:
        return []
    origini, direzioni = np.vstack(origini), np.vstack(direzioni)
    faccia, verso, posizione = np.array(faccia), np.array(verso), np.concatenate(posizione)
    colpito, distanza = _raggi(seg_muri, origini, direzioni, massimo=40 / m_per_u)

    # tratti: raggi consecutivi della stessa faccia e verso che colpiscono lo stesso muro parallelo
    candidati: list[tuple] = []
    inizio = 0
    for k in range(1, len(origini) + 1):
        if k < len(origini) and faccia[k] == faccia[inizio] and verso[k] == verso[inizio] and colpito[k] == colpito[inizio]:
            continue
        j, i = int(colpito[inizio]), int(faccia[inizio])
        gruppo = slice(inizio, k)
        inizio = k
        if j < 0 or abs(float(unitari[i][0] * unitari[j][1] - unitari[i][1] * unitari[j][0])) > seno_max:
            continue
        lunghezza_tratto = posizione[gruppo][-1] - posizione[gruppo][0] + passo_u
        if lunghezza_tratto < tratto_u:
            continue
        m = gruppo.start + (gruppo.stop - gruppo.start) // 2
        d = float(distanza[m])
        minimo = arredi_u if arredo[i] or arredo[j] else minima_u
        if d < minimo:
            continue
        p = origini[m]
        q = p + direzioni[m] * d
        p, q = (float(p[0]), float(p[1])), (float(q[0]), float(q[1]))
        dentro = False
        if arredo[i] or arredo[j]:
            cx, cy, eps = (p[0] + q[0]) / 2, (p[1] + q[1]) / 2, 0.01 / m_per_u
            g = ingombri_arredi
            dentro = (mappa is not None and mappa.in_oggetto(cx, cy)) or bool(
                g is not None and len(g) and np.any((g[:, 0] + eps < cx) & (cx < g[:, 2] - eps) & (g[:, 1] + eps < cy) & (cy < g[:, 3] - eps)))
        locale = mappa.locale(p, q) if mappa is not None else -1
        if locale is None:
            continue
        angolo = math.degrees(math.atan2(direzioni[m][1], direzioni[m][0])) % 180
        if angolo > 90.5:  # testo sempre leggibile: da sinistra a destra o dal basso in alto
            angolo -= 180
        # punteggio: tratto lungo, e a parità meglio una quota orizzontale o verticale
        punteggio = lunghezza_tratto * (1.2 if min(angolo % 90, 90 - angolo % 90) < 0.5 else 1.0)
        candidati.append((punteggio, locale, _linea(seg_muri[i]), _linea(seg_muri[j]), Quota("interno", 0, p, q, round(d, 9), angolo), minimo, dentro))

    # lo spazio attraversato deve essere largo almeno la luce minima anche di traverso:
    # altrimenti la quota corre dentro un muro disegnato a due linee
    if candidati:
        centri = np.array([_centro(c[4]) for c in candidati])
        trasversali = np.array([[-math.sin(math.radians(c[4].angolo)), math.cos(math.radians(c[4].angolo))] for c in candidati])
        _, avanti = _raggi(seg_muri, centri, trasversali, massimo=40 / m_per_u)
        _, indietro = _raggi(seg_muri, centri, -trasversali, massimo=40 / m_per_u)
        candidati = [c for c, larghezza in zip(candidati, avanti + indietro) if larghezza >= c[5]]

    # stessa coppia di linee di muro nello stesso locale = una sola quota (la più rappresentativa)
    scelte: list[tuple] = []
    moduli: set[tuple] = set()  # misure dentro gli arredi già quotate: (misura, direzione, locale)
    for c in sorted(candidati, key=lambda c: -c[0]):
        if c[6]:
            modulo = (round(c[4].misura * m_per_u, 2), round(c[4].angolo) % 180, c[1])
            if modulo in moduli:
                continue
            moduli.add(modulo)
        if not any(c[1] == s[1] and _stessa_coppia(c[2:4], s[2:4], 0.02 / m_per_u)
                   and (c[1] >= 0 or math.dist(_centro(c[4]), _centro(s[4])) < 1.5 / m_per_u) for s in scelte):
            scelte.append(c)
    return sorted((c[4] for c in scelte), key=lambda q: (q.angolo, round(q.p1[1], 6), round(q.p1[0], 6)))


def _linea(s: np.ndarray) -> tuple[float, float]:
    """Retta di un segmento come (direzione in gradi 0-180, distanza dall'origine)."""
    a = math.atan2(s[3] - s[1], s[2] - s[0]) % math.pi
    return math.degrees(a), -math.sin(a) * s[0] + math.cos(a) * s[1]


def _stessa_retta(a: tuple[float, float], b: tuple[float, float], tolleranza: float) -> bool:
    diff = abs(a[0] - b[0])
    if diff > 90:  # 179° e 1° sono quasi la stessa direzione
        diff, b = 180 - diff, (b[0], -b[1])
    return diff < 1.0 and abs(a[1] - b[1]) < tolleranza


def _stessa_coppia(a, b, tolleranza: float) -> bool:
    return (_stessa_retta(a[0], b[0], tolleranza) and _stessa_retta(a[1], b[1], tolleranza)) or \
           (_stessa_retta(a[0], b[1], tolleranza) and _stessa_retta(a[1], b[0], tolleranza))


def _centro(q: Quota) -> tuple[float, float]:
    return (q.p1[0] + q.p2[0]) / 2, (q.p1[1] + q.p2[1]) / 2


def disegna(doc: Drawing, quote: list[Quota], posizioni: list, layer: str, stile: str,
            override: dict | None = None) -> list[str]:
    """Quote interne con la linea che attraversa il locale, come nei disegni dello studio."""
    return [aggiungi_quota(doc, _centro(q), q.p1, q.p2, q.angolo, layer, stile, override or {}, testo_in=pos)
            for q, pos in zip(quote, posizioni)]
