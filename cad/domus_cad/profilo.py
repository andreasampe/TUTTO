"""Profilo dello studio: dove e come disegnare le quote (punto 8.1, "stile dello studio").

Il profilo è un file JSON con:
- il layer delle quote e le sue proprietà (es. `01Cotas 1`, colore 220);
- lo stile di quota (es. "50") e lo stile di testo che usa (es. FUTURA), copiati da un disegno di riferimento;
- le modifiche che lo studio applica a ogni quota (es. DIMSCALE 1);
- i layer di muri, aperture, arredi e limiti.

Se il disegno da elaborare ha già quel layer e quello stile, si usano così come sono:
DOMUS non li modifica. Altrimenti si creano con le proprietà del profilo.

Ogni studio carica il suo file di riferimento (un disegno già quotato a mano) e DOMUS
ricava il profilo IN AUTOMATICO (`rileva`):
- layer delle quote = il layer con più quote; stile = lo stile più usato su quel layer;
- modifiche per quota = quelle più frequenti;
- ruolo degli altri layer (muri, porte e finestre, arredi, limiti) dal nome, in italiano,
  spagnolo, inglese, francese e tedesco, e dalle campiture piene per i muri;
- scala dal nome dello stile ("50") o dall'altezza del testo (2-2,5 mm stampati).
Il profilo è un file JSON leggibile: lo studio può correggerlo.

    python -m domus_cad profilo 03_BASICO.dxf --uscita profili/studio.json
"""
from __future__ import annotations

import json
import re
from collections import Counter
from pathlib import Path

from ezdxf.document import Drawing

PREDEFINITO = Path(__file__).resolve().parent.parent / "profili" / "studio.json"
_ESCLUSI = {"handle", "owner", "name", "plotstyle_handle", "material_handle", "unknown1"}


def carica(percorso: str | Path | None = None) -> dict:
    return json.loads(Path(percorso or PREDEFINITO).read_text(encoding="utf-8"))


# parole che indicano il ruolo di un layer (minuscole, senza accenti)
RUOLI = {
    "layer_muri": ("mur", "wall", "albanil", "fabrica", "tabique", "tramezz", "parete", "pareti", "cloison",
                   "wand", "trama solid", "campitur", "poche", "a-wall"),
    "layer_aperture": ("carpint", "porta", "porte", "finestr", "serrament", "infiss", "door", "window", "puerta",
                       "ventana", "fenetre", "tuer", "fenster", "a-door", "a-glaz"),
    "layer_arredi": ("mobil", "arred", "furn", "mueble", "sanitar", "equip", "a-furn", "a-flor-fixt", "einricht"),
    "layer_limiti": ("visto", "vista", "recinz", "limite", "lotto", "boundary", "fence", "cerca", "valla", "a-site"),
}
# layer di testi e quote: non hanno un ruolo geometrico anche se il nome contiene "carpinteria" o simili
NON_GEOMETRICI = ("text", "testo", "texto", "txt", "cota", "quot", "dim", "anno", "etichet", "rotul")
SCALE = (10, 20, 25, 50, 100, 200, 500, 1000)


def _semplice(nome: str) -> str:
    tabella = str.maketrans("áàäâéèëêíìïîóòöôúùüûñç", "aaaaeeeeiiiioooouuuunc")
    return nome.lower().translate(tabella)


def rileva(doc: Drawing) -> dict:
    """Profilo ricavato in automatico da un disegno di riferimento dello studio."""
    from .lettura import UNITA, metri_per_unita

    quote = [d for d in doc.modelspace().query("DIMENSION") if "|" not in d.dxf.layer]
    if not quote:
        raise ValueError("Il file di riferimento non contiene quote: serve un disegno già quotato dallo studio.")
    layer = Counter(d.dxf.layer for d in quote).most_common(1)[0][0]
    stile = Counter(d.dxf.dimstyle for d in quote if d.dxf.layer == layer).most_common(1)[0][0]
    profilo = estrai(doc, layer, stile)
    motivi = [f"layer delle quote '{layer}': {sum(d.dxf.layer == layer for d in quote)} quote su {len(quote)}",
              f"stile di quota '{stile}': il più usato su quel layer"]

    trovati, spiegazioni = ruoli(doc, escludi={layer})
    profilo.update(trovati)
    motivi += spiegazioni

    # scala: dal nome dello stile ("50", "1-50") o dall'altezza del testo (2-2,5 mm stampati)
    numero = re.search(r"(\d+)\D*$", stile)
    scala = int(numero.group(1)) if numero and int(numero.group(1)) in SCALE else None
    if scala is None:
        unita = UNITA.get(doc.header.get("$INSUNITS", 0))
        altezza_m = profilo["stile_quote"].get("dimtxt", 0) * (metri_per_unita(unita) if unita else 1)
        for mm in (2.0, 2.5, 1.8, 3.0):
            if (s := round(altezza_m * 1000 / mm)) in SCALE:
                scala = s
                break
    profilo["scala"] = scala or 100
    motivi.append(f"scala 1:{profilo['scala']}")
    return {"descrizione": "Profilo ricavato in automatico dal file di riferimento dello studio: controllare i ruoli dei layer.",
            "rilevato": motivi, **profilo}


def ruoli(doc: Drawing, escludi: set[str] = frozenset()) -> tuple[dict[str, list[str]], list[str]]:
    """Ruolo dei layer (muri, porte e finestre, arredi, limiti), senza conoscere lo studio.

    1. dal nome, in più lingue. Valgono anche i layer dei riferimenti esterni
       ("REF.OPCION 2|01Mobiliario" diventa "01Mobiliario");
    2. se il nome non basta, dalla geometria:
       - muri = layer con molte coppie di linee parallele a 5-50 cm, oppure con campiture piene;
       - arredi = layer con molti oggetti piccoli chiusi o blocchi (lato da 30 cm a 3 m).
    """
    from .lettura import UNITA, metri_per_unita

    unita = UNITA.get(doc.header.get("$INSUNITS", 0))
    m_per_u = metri_per_unita(unita) if unita else 1.0
    msp = doc.modelspace()
    # layer davvero usati, più quelli dei riferimenti esterni (la geometria sta nel file collegato)
    usati = {e.dxf.layer for e in msp} | {l.dxf.name for l in doc.layers if "|" in l.dxf.name}
    nomi = sorted(n for n in {u.split("|")[-1] for u in usati} - {"0", "DEFPOINTS", "Defpoints", *escludi}
                  if not any(p in _semplice(n) for p in NON_GEOMETRICI))
    pieni = Counter(h.dxf.layer.split("|")[-1] for h in msp.query("HATCH") if h.dxf.solid_fill)
    out: dict[str, list[str]] = {}
    motivi: list[str] = []
    for ruolo, parole in RUOLI.items():
        out[ruolo] = [n for n in nomi if any(p in _semplice(n) for p in parole)]
        if ruolo == "layer_muri":  # campiture piene dei muri: "trama solido", "poche"…
            out[ruolo] += [n for n in nomi if pieni[n] and "solid" in _semplice(n) and n not in out[ruolo]]
        if out[ruolo]:
            motivi.append(f"{ruolo}: {', '.join(out[ruolo])} (dal nome)")

    if not out["layer_muri"] or not out["layer_arredi"]:
        coppie, oggetti = _geometria_layer(doc, m_per_u, set(nomi))
        if not out["layer_muri"]:
            muri = [n for n, (c, tot) in coppie.items() if c >= 10 and c >= 0.3 * tot] + \
                   [n for n, k in pieni.items() if k >= 5 and n in nomi]
            out["layer_muri"] = sorted(set(muri))
            motivi.append(f"layer_muri: {', '.join(out['layer_muri']) or 'nessuno'} (dalla geometria: linee parallele a 5-50 cm, campiture piene)")
        if not out["layer_arredi"]:
            esclusi = set(out["layer_muri"]) | set(out["layer_aperture"]) | set(out["layer_limiti"])
            candidati = [(k, n) for n, k in oggetti.items() if k >= 5 and n not in esclusi]
            out["layer_arredi"] = [max(candidati)[1]] if candidati else []
            motivi.append(f"layer_arredi: {', '.join(out['layer_arredi']) or 'nessuno'} (dalla geometria: oggetti chiusi da 30 cm a 3 m)")
    for ruolo in ("layer_aperture", "layer_limiti"):
        if not out[ruolo]:
            motivi.append(f"{ruolo}: nessuno")
    return out, motivi


def _geometria_layer(doc: Drawing, m_per_u: float, nomi: set[str]) -> tuple[dict, Counter]:
    """Per ogni layer: (coppie di segmenti paralleli a 5-50 cm, segmenti) e numero di oggetti piccoli chiusi."""
    import math

    import numpy as np
    from ezdxf import bbox

    segmenti: dict[str, list] = {}
    oggetti: Counter = Counter()
    for e in doc.modelspace():
        n = e.dxf.layer
        if n not in nomi:
            continue
        t = e.dxftype()
        if t == "LINE":
            segmenti.setdefault(n, []).append((e.dxf.start.x, e.dxf.start.y, e.dxf.end.x, e.dxf.end.y))
        elif t == "LWPOLYLINE":
            punti = [tuple(p[:2]) for p in e.get_points("xy")]
            if e.closed:
                punti.append(punti[0])
            segmenti.setdefault(n, []).extend((*a, *b) for a, b in zip(punti, punti[1:]))
        if t == "INSERT" or (t == "LWPOLYLINE" and e.closed):
            try:
                b = bbox.extents([e])
            except Exception:
                continue
            lati = sorted((b.size.x * m_per_u, b.size.y * m_per_u)) if b.has_data else (0, 0)
            if 0.3 <= lati[1] <= 3.0 and lati[0] >= 0.05:
                oggetti[n] += 1
    coppie = {}
    for n, lista in segmenti.items():
        a = np.array(lista[:3000], dtype=float) * m_per_u
        d = a[:, 2:] - a[:, :2]
        lung = np.hypot(d[:, 0], d[:, 1])
        a, d, lung = a[lung > 0.3], d[lung > 0.3], lung[lung > 0.3]
        if len(a) < 2:
            coppie[n] = (0, len(lista))
            continue
        u = d / lung[:, None]
        parallele = np.abs(u[:, None, 0] * u[None, :, 1] - u[:, None, 1] * u[None, :, 0]) < math.sin(math.radians(2))
        # distanza del punto medio di j dalla retta di i
        m = (a[:, :2] + a[:, 2:]) / 2
        rel = m[None, :, :] - a[:, None, :2]
        dist = np.abs(rel[..., 0] * u[:, None, 1] - rel[..., 1] * u[:, None, 0])
        lungo = np.abs(rel[..., 0] * u[:, None, 0] + rel[..., 1] * u[:, None, 1])
        vicine = parallele & (dist > 0.05) & (dist < 0.5) & (lungo < lung[:, None])
        coppie[n] = (int(vicine.any(axis=1).sum()), len(lista))
    return coppie, oggetti


def estrai(doc: Drawing, layer: str, stile: str) -> dict:
    """Legge da un disegno di riferimento layer, stile di quota, stile di testo e modifiche per quota."""
    if layer not in doc.layers:
        raise ValueError(f"Il disegno di riferimento non ha il layer '{layer}'.")
    if stile not in doc.dimstyles:
        raise ValueError(f"Il disegno di riferimento non ha lo stile di quota '{stile}'.")
    ds = doc.dimstyles.get(stile)
    attr_stile = {k: v for k, v in ds.dxfattribs().items() if k not in _ESCLUSI}
    testo = None
    if (nome_testo := attr_stile.get("dimtxsty")) and nome_testo in doc.styles:
        st = doc.styles.get(nome_testo)
        famiglia = st.get_xdata("ACAD") if st.has_xdata("ACAD") else []
        testo = {"name": nome_testo, **{k: v for k, v in st.dxfattribs().items() if k not in _ESCLUSI},
                 "xdata_acad": [[t.code, t.value] for t in famiglia]}
    # modifiche per quota (DSTYLE): si tiene quella più frequente tra le quote dello stile
    frequenti: Counter = Counter()
    for d in doc.modelspace().query("DIMENSION"):
        if d.dxf.layer == layer and d.dxf.dimstyle == stile:
            frequenti[json.dumps(_override(d), sort_keys=True)] += 1
    override = json.loads(frequenti.most_common(1)[0][0]) if frequenti else {}
    return {
        "layer_quote": {"name": layer, **{k: v for k, v in doc.layers.get(layer).dxfattribs().items() if k not in _ESCLUSI}},
        "stile_quote": {"name": stile, **attr_stile},
        "stile_testo": testo,
        "override": override,
    }


def _override(d) -> dict:
    try:
        return {k: v for k, v in d.override().dimstyle_attribs.items() if not k.endswith("_handle")}
    except Exception:
        return {}


def applica(doc: Drawing, profilo: dict) -> tuple[str, str, dict]:
    """Prepara layer e stili nel disegno; restituisce (layer, stile, modifiche per quota)."""
    lq, sq, st = profilo["layer_quote"], profilo["stile_quote"], profilo.get("stile_testo")
    if lq["name"] not in doc.layers:
        doc.layers.add(lq["name"], color=lq.get("color", 7), linetype=lq.get("linetype", "Continuous"),
                       lineweight=lq.get("lineweight", -3))
    if st and st["name"] not in doc.styles:
        nuovo = doc.styles.add(st["name"], font=st.get("font") or "arial.ttf")
        for k in ("width", "oblique", "height", "last_height", "generation_flags"):
            if k in st:
                nuovo.dxf.set(k, st[k])
        if st.get("xdata_acad"):  # famiglia del carattere TrueType (es. "Futura Bk BT")
            nuovo.set_xdata("ACAD", [tuple(t) for t in st["xdata_acad"]])
    if sq["name"] not in doc.dimstyles:
        attr = {k: v for k, v in sq.items() if k != "name" and not k.startswith("dimblk") and k != "dimldrblk"}
        # ezdxf scriverebbe i suoi valori predefiniti (ISO: virgola, zeri tolti) per gli attributi
        # assenti: si mettono quelli di AutoCAD, che sono quelli con cui lo studio vede lo stile
        attr = {**{k: v for k, v in PREDEFINITI_AUTOCAD.items() if k not in attr}, **attr}
        nuovo = doc.dimstyles.new(sq["name"], dxfattribs=attr)
        frecce = {k: (sq.get(k) or "").lstrip("_") for k in ("dimblk", "dimblk1", "dimblk2", "dimldrblk")}
        nuovo.set_arrows(blk=frecce["dimblk"], blk1=frecce["dimblk1"], blk2=frecce["dimblk2"], ldrblk=frecce["dimldrblk"])
    return lq["name"], sq["name"], dict(profilo.get("override") or {})


# Valori che AutoCAD usa quando un attributo manca nella definizione dello stile di quota
# (nel DXF AutoCAD non scrive i valori predefiniti). ezdxf invece userebbe i predefiniti ISO
# (virgola decimale, zeri finali soppressi, testo sopra la linea): diversi da ciò che mostra AutoCAD.
PREDEFINITI_AUTOCAD = {
    "dimtih": 1, "dimtoh": 1, "dimtad": 0, "dimzin": 0, "dimdsep": ord("."), "dimtofl": 0,
    "dimtmove": 0, "dimjust": 0, "dimtvp": 0, "dimtix": 0, "dimsoxd": 0, "dimlfac": 1.0,
    "dimtfac": 1.0, "dimtsz": 0.0, "dimlunit": 2, "dimsah": 0, "dimclrd": 0, "dimclre": 0, "dimclrt": 0,
}


def valori_resa(ds) -> dict:
    """Valori con cui disegnare le quote di uno stile come le disegna AutoCAD.

    - gli attributi assenti dallo stile prendono i predefiniti di AutoCAD;
    - DIMRND = 0 per AutoCAD vuol dire "nessun arrotondamento", mentre ezdxf arrotonderebbe
      all'intero (6.40 diventerebbe 6.00): per il disegno si toglie.
    """
    resa = {k: v for k, v in PREDEFINITI_AUTOCAD.items() if ds is None or not ds.dxf.hasattr(k)}
    if ds is None or not ds.dxf.get("dimrnd", 0):
        resa["dimrnd"] = 1e-9  # in pratica nessun arrotondamento (poi tolto dalla quota)
    return resa


def misura_testo(doc: Drawing, stile: str, override: dict) -> dict:
    """Altezza, larghezza, fattore, decimali e orientamento del testo delle quote, come li vede AutoCAD."""
    ds = doc.dimstyles.get(stile)
    def valore(k, predefinito):
        return override.get(k, ds.dxf.get(k, PREDEFINITI_AUTOCAD.get(k, predefinito)))
    scala = valore("dimscale", 1.0) or 1.0  # 0 = 1 nello spazio modello
    larghezza = 1.0
    if (nome := ds.dxf.get("dimtxsty")) and nome in doc.styles:
        larghezza = doc.styles.get(nome).dxf.get("width", 1.0) or 1.0
    return {"altezza": valore("dimtxt", 2.5) * scala, "larghezza": larghezza, "fattore": valore("dimlfac", 1.0),
            "decimali": int(valore("dimdec", 2)), "orizzontale": bool(valore("dimtih", 1))}


def aggiungi_quota(doc: Drawing, base, p1, p2, angolo: float, layer: str, stile: str, override: dict,
                   testo_in=None) -> str:
    """Quota lineare con layer e stile dello studio; restituisce l'handle.

    La grafica della quota viene generata con i valori che AutoCAD userebbe per lo stile
    (1.50 con il punto, testo orizzontale…). Nella quota resta scritta solo la modifica
    del profilo (es. DIMSCALE 1), come nelle quote disegnate dallo studio.
    `testo_in`: posizione del testo quando non può stare al centro (vedi `testi.py`).
    """
    import math

    # i punti vanno nel verso della quota: il pallino _DOT ha un trattino che deve puntare
    # verso l'interno; con i punti invertiti il trattino esce oltre il pallino ("sborda")
    u = (math.cos(math.radians(angolo)), math.sin(math.radians(angolo)))
    if (p2[0] - p1[0]) * u[0] + (p2[1] - p1[1]) * u[1] < 0:
        p1, p2 = p2, p1
    resa = valori_resa(doc.dimstyles.get(stile))
    dim = doc.modelspace().add_linear_dim(base=base, p1=p1, p2=p2, angle=angolo, dimstyle=stile, location=testo_in,
                                          override={**resa, **override}, dxfattribs={"layer": layer})
    dim.render()
    if override:
        dim.dimension.set_acad_dstyle(override)
    elif dim.dimension.has_xdata("ACAD"):
        dim.dimension.discard_xdata("ACAD")
    return dim.dimension.dxf.handle
