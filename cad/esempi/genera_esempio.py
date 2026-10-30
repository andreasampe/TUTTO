"""Genera esempi/esempio_artificiale.dxf: un appartamento INVENTATO per collaudare il flusso.

Non è un rilievo e non va usato come prova sui file reali (specifica, punto 21):
serve solo finché non arriva una planimetria vera da AutoCAD.
Unità: metri. Muri esterni 30 cm, tramezzi 10 cm, finestre e porte su layer separati.
"""
from pathlib import Path

import ezdxf


def genera(percorso: Path) -> Path:
    doc = ezdxf.new("R2018", setup=True)
    doc.header["$INSUNITS"] = 6  # metri
    for nome, colore in (("MURI", 7), ("TRAMEZZI", 8), ("FINESTRE", 5), ("PORTE", 3), ("TESTI", 2)):
        doc.layers.add(nome, color=colore)
    msp = doc.modelspace()
    esterno = [(0, 0), (12, 0), (12, 5), (9.5, 5), (9.5, 9), (0, 9)]
    interno = [(0.3, 0.3), (11.7, 0.3), (11.7, 4.7), (9.2, 4.7), (9.2, 8.7), (0.3, 8.7)]
    msp.add_lwpolyline(esterno, close=True, dxfattribs={"layer": "MURI"})
    msp.add_lwpolyline(interno, close=True, dxfattribs={"layer": "MURI"})
    # tramezzi da 10 cm
    for (x1, y1, x2, y2) in ((5.2, 0.3, 5.2, 3.6), (5.3, 0.3, 5.3, 3.6), (5.2, 4.5, 5.2, 8.7), (5.3, 4.5, 5.3, 8.7),
                             (0.3, 4.5, 4.2, 4.5), (0.3, 4.6, 4.2, 4.6), (5.3, 3.6, 5.3, 4.5)):
        msp.add_line((x1, y1), (x2, y2), dxfattribs={"layer": "TRAMEZZI"})
    # finestre (sul filo esterno) e porte
    for (x1, y1, x2, y2) in ((1.6, 0, 3.0, 0), (7.4, 0, 9.0, 0), (12, 1.8, 12, 3.2), (2.0, 9, 3.6, 9), (6.6, 9, 8.0, 9)):
        msp.add_line((x1, y1), (x2, y2), dxfattribs={"layer": "FINESTRE"})
    msp.add_arc((4.2, 4.55), 0.8, 0, 90, dxfattribs={"layer": "PORTE"})
    msp.add_arc((5.25, 3.6), 0.8, 90, 180, dxfattribs={"layer": "PORTE"})
    for testo, x, y in (("SOGGIORNO", 1.5, 2.2), ("CAMERA", 1.5, 6.5), ("CUCINA", 7.5, 2.2), ("BAGNO", 6.6, 6.5)):
        msp.add_text(testo, height=0.25, dxfattribs={"layer": "TESTI", "insert": (x, y)})
    msp.add_text("ESEMPIO ARTIFICIALE - NON E' UN RILIEVO", height=0.35, dxfattribs={"layer": "TESTI", "insert": (0, 10.2)})
    doc.saveas(percorso)
    return percorso


if __name__ == "__main__":
    print(genera(Path(__file__).with_name("esempio_artificiale.dxf")))
