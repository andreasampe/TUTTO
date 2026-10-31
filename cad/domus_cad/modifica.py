"""Ritocchi alle planimetrie dall'area "Planimetrie" della webapp (Fase 6).

Vale per QUALUNQUE planimetria esecutiva in DXF, non solo per quelle prodotte da DOMUS:
- `modello(doc)` descrive il disegno per il visualizzatore del browser:
  - geometria semplificata (polilinee);
  - testi;
  - quote modificabili;
  - cartigli (blocchi con attributi);
- `applica(doc, modifiche, profilo)` applica i ritocchi scelti nel browser:
  - aggiungere una quota tra due punti, sul layer e con lo stile dello studio;
  - eliminare una quota o un testo;
  - spostare la linea di quota (la misura non cambia: cambia solo dove è disegnata);
  - spostare il testo della quota, o rimetterlo al centro;
  - cambiare un testo o i campi di un cartiglio.

Tutto il resto del disegno non viene toccato e lo controlla `applica` stessa.
"""
from __future__ import annotations

import math

from ezdxf import disassemble
from ezdxf.document import Drawing

from . import profilo as profili
from .profilo import _override, aggiungi_quota, valori_resa

TIPI_QUOTA = {0: "lineare", 1: "allineata"}  # tipi che si possono spostare; gli altri solo eliminare


def _p(v, cifre: int = 4) -> list[float]:
    return [round(float(v[0]), cifre), round(float(v[1]), cifre)]


def _testo_quota(doc: Drawing, dim) -> tuple[str, list[float] | None, float | None]:
    """Testo come lo disegna il blocco della quota (es. "1.50"), sua posizione e altezza."""
    blocco = doc.blocks.get(dim.dxf.get("geometry", "")) if dim.dxf.hasattr("geometry") else None
    for e in (blocco or []):
        if e.dxftype() == "MTEXT":
            return e.plain_text().strip(), _p(e.dxf.insert), round(float(e.dxf.get("char_height", 0) or 0), 4) or None
        if e.dxftype() == "TEXT":
            return e.dxf.text, _p(e.dxf.insert), round(float(e.dxf.get("height", 0) or 0), 4) or None
    return "", None, None


def modello(doc: Drawing, tolleranza: float = 0.005) -> dict:
    """Disegno semplificato per il browser: coordinate del disegno, y verso l'alto."""
    msp = doc.modelspace()
    spenti = {l.dxf.name for l in doc.layers if l.is_off() or l.is_frozen()}
    geometria, testi, quote, cartigli = [], [], [], []
    punti_x, punti_y = [], []

    def aggiungi_testo(e, modificabile: bool) -> None:
        t = e.dxftype()
        try:
            testo = e.plain_text() if t == "MTEXT" else e.dxf.text
            altezza = e.dxf.char_height if t == "MTEXT" else e.dxf.height
            rotazione = e.dxf.get("rotation", 0.0)
        except Exception:
            return
        if not str(testo).strip():
            return
        testi.append({"h": e.dxf.handle if modificabile else None, "l": e.dxf.layer, "t": str(testo),
                      "p": _p(e.dxf.insert), "s": round(float(altezza or 0.1), 4), "a": round(float(rotazione), 2),
                      "mtext": t == "MTEXT"})

    for e in msp:
        if e.dxf.layer in spenti:
            continue
        t = e.dxftype()
        if t == "DIMENSION":
            testo, posizione, altezza = _testo_quota(doc, e)
            q = {"h": e.dxf.handle, "l": e.dxf.layer, "stile": e.dxf.dimstyle, "tipo": TIPI_QUOTA.get(e.dimtype, "altro"),
                 "testo": testo, "pt": posizione, "ht": altezza}
            try:
                q.update({"p1": _p(e.dxf.defpoint2), "p2": _p(e.dxf.defpoint3), "base": _p(e.dxf.defpoint),
                          "ang": round(float(e.dxf.get("angle", 0.0)), 4), "misura": round(float(e.get_measurement()), 6)})
                if e.dimtype == 1:  # allineata: la direzione è quella dei due punti
                    q["ang"] = round(math.degrees(math.atan2(e.dxf.defpoint3.y - e.dxf.defpoint2.y,
                                                             e.dxf.defpoint3.x - e.dxf.defpoint2.x)), 4)
            except Exception:
                q["tipo"] = "altro"
            quote.append(q)
            continue
        if t in ("TEXT", "MTEXT"):
            aggiungi_testo(e, True)
            continue
        if t == "INSERT":
            attributi = [{"h": a.dxf.handle, "tag": a.dxf.tag, "valore": a.dxf.text} for a in e.attribs]
            if attributi:
                cartigli.append({"h": e.dxf.handle, "nome": e.dxf.name, "l": e.dxf.layer, "p": _p(e.dxf.insert), "attr": attributi})
                for a in e.attribs:
                    aggiungi_testo(a, False)
        sorgenti = disassemble.recursive_decompose([e]) if t == "INSERT" else [e]
        for v in sorgenti:
            if v.dxftype() in ("TEXT", "MTEXT", "ATTRIB"):
                aggiungi_testo(v, False)
                continue
            if v.dxftype() in ("DIMENSION", "POINT", "VIEWPORT"):
                continue
            try:
                primitiva = disassemble.make_primitive(v, max_flattening_distance=tolleranza)
                percorso = primitiva.path
            except Exception:
                continue
            if percorso is None:
                continue
            pieno = v.dxftype() == "HATCH" and bool(v.dxf.get("solid_fill", 0))
            for sotto in (percorso.sub_paths() if percorso.has_sub_paths else [percorso]):
                pts = [_p(p) for p in sotto.flattening(tolleranza)]
                pulito = [pts[0]] if pts else []
                for q in pts[1:]:
                    if q != pulito[-1]:
                        pulito.append(q)
                if len(pulito) < 2:
                    continue
                geometria.append({"l": e.dxf.layer, "p": pulito, "f": pieno})
                punti_x += [q[0] for q in pulito]
                punti_y += [q[1] for q in pulito]

    return {"geometria": geometria, "testi": testi, "quote": quote, "cartigli": cartigli,
            "estensione": _estensione(punti_x, punti_y), "layer": sorted({l.dxf.name for l in doc.layers})}


def _estensione(xs: list[float], ys: list[float]) -> list[float]:
    """Riquadro del disegno senza gli elementi isolati molto lontani (capita nei file reali)."""
    if not xs:
        return [0.0, 0.0, 1.0, 1.0]
    def taglio(v):
        v = sorted(v)
        lo, hi = v[int(len(v) * 0.01)], v[min(len(v) - 1, int(len(v) * 0.99))]
        margine = (hi - lo) * 0.08 or 1.0
        return lo - margine, hi + margine
    (x0, x1), (y0, y1) = taglio(xs), taglio(ys)
    return [round(x0, 4), round(y0, 4), round(x1, 4), round(y1, 4)]


def ridisegna(doc: Drawing, dim, testo_in=None, testo_al_centro: bool = False) -> None:
    """Rigenera la grafica di una quota come la disegnerebbe AutoCAD con il suo stile.

    Le modifiche per quota già presenti (es. DIMSCALE 1) restano le stesse."""
    vecchio = dim.dxf.get("geometry")
    modifiche = _override(dim)
    resa = valori_resa(doc.dimstyles.get(dim.dxf.dimstyle) if dim.dxf.dimstyle in doc.dimstyles else None)
    if testo_al_centro:
        dim.dxf.dimtype = dim.dxf.dimtype & ~128  # niente posizione libera: testo al centro
    o = dim.override()
    o.update({**resa, **modifiche})
    if testo_in is not None:
        o.set_location(testo_in, leader=False)
    o.render()
    if modifiche:
        dim.set_acad_dstyle(modifiche)
    elif dim.has_xdata("ACAD"):
        dim.discard_xdata("ACAD")
    if vecchio and vecchio != dim.dxf.get("geometry") and vecchio in doc.blocks:
        doc.blocks.delete_block(vecchio, safe=False)


def applica(doc: Drawing, modifiche: list[dict], profilo: dict | None = None) -> dict:
    """Applica i ritocchi; restituisce il resoconto con i controlli.

    `profilo`: profilo dello studio per le quote aggiunte (layer, stile); se manca, quello predefinito."""
    msp = doc.modelspace()
    prima = {e.dxf.handle for e in msp}
    toccati: set[str] = set()
    misure: dict[str, float] = {}
    aggiunte: dict[str, float] = {}  # handle della quota nuova → misura attesa
    fatte, errori = [], []
    stile_studio = None
    for m in modifiche:
        h, azione = str(m.get("h", "")), m.get("azione")
        if azione == "aggiungi":
            try:
                if stile_studio is None:
                    stile_studio = profili.applica(doc, profilo or profili.carica())
                layer, stile, override = stile_studio
                p1, p2, base, angolo = m["p1"], m["p2"], m["base"], float(m["angolo"])
                u = (math.cos(math.radians(angolo)), math.sin(math.radians(angolo)))
                attesa = abs((p2[0] - p1[0]) * u[0] + (p2[1] - p1[1]) * u[1])
                if attesa < 1e-9:
                    raise ValueError("i due punti coincidono nella direzione della quota")
                if angolo > 90.5 or angolo <= -89.5:  # testo leggibile: da sinistra a destra o dal basso in alto
                    angolo = angolo - 180 if angolo > 90.5 else angolo + 180
                testo = tuple(m["pos"]) if m.get("pos") else None  # testo spostato prima di salvare
                nuova = aggiungi_quota(doc, tuple(base), tuple(p1), tuple(p2), angolo, layer, stile, dict(override), testo_in=testo)
                aggiunte[nuova] = attesa
                fatte.append(azione)
            except Exception as err:
                errori.append(f"quota nuova: {err}")
            continue
        e = doc.entitydb.get(h)
        if e is None or not e.is_alive:
            errori.append(f"{h}: entità non trovata")
            continue
        try:
            if azione == "elimina":
                if e.dxftype() == "DIMENSION" and e.dxf.get("geometry") in doc.blocks:
                    doc.blocks.delete_block(e.dxf.geometry, safe=False)
                msp.delete_entity(e)
            elif azione == "sposta_linea" and e.dxftype() == "DIMENSION":
                misure.setdefault(h, e.get_measurement())
                nuova = m["base"]
                dx, dy = nuova[0] - e.dxf.defpoint.x, nuova[1] - e.dxf.defpoint.y
                testo = None
                if e.dxf.dimtype & 128:  # testo in posizione libera: si sposta con la linea
                    testo = (e.dxf.text_midpoint.x + dx, e.dxf.text_midpoint.y + dy)
                e.dxf.defpoint = (nuova[0], nuova[1], 0)
                ridisegna(doc, e, testo_in=testo)
            elif azione == "sposta_testo" and e.dxftype() == "DIMENSION":
                misure.setdefault(h, e.get_measurement())
                ridisegna(doc, e, testo_in=tuple(m["pos"]))
            elif azione == "testo_al_centro" and e.dxftype() == "DIMENSION":
                misure.setdefault(h, e.get_measurement())
                ridisegna(doc, e, testo_al_centro=True)
            elif azione == "testo" and e.dxftype() in ("TEXT", "MTEXT"):
                if e.dxftype() == "TEXT":
                    e.dxf.text = str(m["valore"])
                else:
                    e.text = str(m["valore"])
            elif azione == "attributi" and e.dxftype() == "INSERT":
                for a in e.attribs:
                    if a.dxf.tag in m["valori"]:
                        a.dxf.text = str(m["valori"][a.dxf.tag])
            else:
                errori.append(f"{h}: azione '{azione}' non possibile su {e.dxftype()}")
                continue
            toccati.add(h)
            fatte.append(azione)
        except Exception as err:  # una modifica non riuscita non deve fermare le altre
            errori.append(f"{h}: {err}")

    dopo = {e.dxf.handle for e in msp}
    eliminati = {m["h"] for m in modifiche if m.get("azione") == "elimina"}
    persi = sorted((prima - dopo) - eliminati)
    variate = [h for h, v in misure.items() if (e := doc.entitydb.get(h)) is not None and abs(e.get_measurement() - v) > 1e-9]
    variate += [h for h, v in aggiunte.items() if (e := doc.entitydb.get(h)) is None or abs(e.get_measurement() - v) > 1e-9]
    controlli = [
        {"nome": "Conservazione", "ok": not persi, "dettaglio": "nessuna entità persa oltre a quelle eliminate" if not persi else f"perse: {persi[:5]}"},
        {"nome": "Misure", "ok": not variate, "dettaglio": "le quote spostate misurano come prima, le nuove misurano la distanza tra i punti scelti" if not variate else f"misure cambiate: {variate[:5]}"},
        {"nome": "Modifiche", "ok": not errori, "dettaglio": f"{len(fatte)} applicate" + (f", {len(errori)} non riuscite: {errori[:3]}" if errori else "")},
    ]
    riepilogo = {a: fatte.count(a) for a in sorted(set(fatte))}
    return {"applicate": len(fatte), "riepilogo": riepilogo, "errori": errori, "controlli": controlli,
            "superato": all(c["ok"] for c in controlli)}
