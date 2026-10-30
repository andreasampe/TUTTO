"""Cartiglio e annotazioni (punto 8.1): blocco con attributi, su layer dedicati.

Il cartiglio è un BLOCCO con attributi (studio, progetto, elaborato, scala, data, tavola),
così in AutoCAD si modifica con un doppio clic. Le dimensioni sono in millimetri di carta
e vengono convertite in unità di disegno con la scala scelta.
"""
from __future__ import annotations

from datetime import date

from ezdxf.document import Drawing

LAYER_CARTIGLIO = "DOMUS_CARTIGLIO"
LAYER_NOTE = "DOMUS_NOTE"
BLOCCO = "DOMUS_CARTIGLIO"

# Cartiglio su carta: 180 × 55 mm
LARGHEZZA_MM = 180.0
ALTEZZA_MM = 55.0
CAMPI = [  # (etichetta, tag, x mm, y mm, altezza testo mm)
    ("STUDIO", "STUDIO", 4, 46, 3.5),
    ("PROGETTO", "PROGETTO", 4, 33, 3.0),
    ("ELABORATO", "ELABORATO", 4, 20, 3.0),
    ("SCALA", "SCALA", 4, 7, 2.5),
    ("DATA", "DATA", 64, 7, 2.5),
    ("TAVOLA", "TAVOLA", 124, 7, 2.5),
]


def _blocco(doc: Drawing) -> None:
    if BLOCCO in doc.blocks:
        return
    b = doc.blocks.new(BLOCCO)
    w, h = LARGHEZZA_MM, ALTEZZA_MM
    b.add_lwpolyline([(0, 0), (w, 0), (w, h), (0, h)], close=True, dxfattribs={"const_width": 0.5})
    for y in (14, 27, 40):
        b.add_line((0, y), (w, y))
    for x in (60, 120):
        b.add_line((x, 0), (x, 14))
    for etichetta, tag, x, y, hh in CAMPI:
        b.add_text(etichetta, height=1.6, dxfattribs={"insert": (x, y + hh + 1.6)})
        b.add_attdef(tag, insert=(x, y), height=hh)


def inserisci(doc: Drawing, angolo_dx_basso: tuple[float, float], unita_per_mm: float, valori: dict[str, str]) -> str:
    """Inserisce il cartiglio con l'angolo in basso a destra nel punto indicato."""
    for nome, colore in ((LAYER_CARTIGLIO, 7), (LAYER_NOTE, 2)):
        if nome not in doc.layers:
            doc.layers.add(nome, color=colore)
    _blocco(doc)
    x, y = angolo_dx_basso
    origine = (x - LARGHEZZA_MM * unita_per_mm, y)
    ref = doc.modelspace().add_blockref(BLOCCO, origine, dxfattribs={
        "layer": LAYER_CARTIGLIO, "xscale": unita_per_mm, "yscale": unita_per_mm})
    dati = {"DATA": date.today().strftime("%d/%m/%Y"), **valori}
    ref.add_auto_attribs({tag: dati.get(tag, "") for _, tag, *_ in CAMPI})
    for a in ref.attribs:
        a.dxf.layer = LAYER_CARTIGLIO
    return ref.dxf.handle


def nota(doc: Drawing, testo: str, punto: tuple[float, float], altezza: float) -> str:
    t = doc.modelspace().add_text(testo, height=altezza, dxfattribs={"layer": LAYER_NOTE, "insert": punto})
    return t.dxf.handle
