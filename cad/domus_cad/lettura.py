"""Lettura del disegno (punto 4.A della specifica).

Apre un DXF, conta entità e layer, legge unità ed estensione, e segnala i problemi
che rendono il disegno poco affidabile (unità assenti, linee nulle, doppioni, xref…).
Non modifica nulla: restituisce solo un rapporto.
"""
from __future__ import annotations

from collections import Counter
from dataclasses import dataclass, field

import ezdxf
from ezdxf import recover
from ezdxf import bbox, units
from ezdxf.document import Drawing

# $INSUNITS → nome leggibile (solo le unità usate in architettura)
UNITA = {0: None, 1: "pollici", 2: "piedi", 4: "mm", 5: "cm", 6: "m"}
# metri per unità di disegno
METRI = {"mm": 0.001, "cm": 0.01, "m": 1.0, "pollici": 0.0254, "piedi": 0.3048}


@dataclass
class Rapporto:
    file: str
    versione: str
    unita: str | None
    entita: dict[str, int]
    layer: dict[str, int]
    estensione: tuple[tuple[float, float], tuple[float, float]] | None
    blocchi: int
    riferimenti_esterni: list[str]
    problemi: list[str] = field(default_factory=list)


def apri(percorso: str) -> Drawing:
    """Apre un DXF; se è danneggiato prova il recupero e lo segnala."""
    try:
        return ezdxf.readfile(percorso)
    except ezdxf.DXFStructureError:
        doc, auditor = recover.readfile(percorso)
        if auditor.has_errors:
            raise ValueError(f"Il file {percorso} è danneggiato e non recuperabile: {len(auditor.errors)} errori")
        return doc


def leggi(doc: Drawing, percorso: str) -> Rapporto:
    msp = doc.modelspace()
    tipi = Counter(e.dxftype() for e in msp)
    per_layer = Counter(e.dxf.layer for e in msp)
    unita = UNITA.get(doc.header.get("$INSUNITS", 0))
    ext = bbox.extents(msp, fast=True)
    estensione = ((ext.extmin.x, ext.extmin.y), (ext.extmax.x, ext.extmax.y)) if ext.has_data else None
    xref = [b.name for b in doc.blocks if b.block_record.is_xref]

    problemi: list[str] = []
    if unita is None:
        problemi.append("Unità di misura non indicate nel file ($INSUNITS = 0): vanno confermate prima di quotare.")
    if not estensione:
        problemi.append("Lo spazio modello è vuoto.")
    if xref:
        problemi.append(f"Riferimenti esterni non inclusi nel file: {', '.join(xref)}.")
    nulle = [e.dxf.handle for e in msp.query("LINE") if e.dxf.start.isclose(e.dxf.end)]
    if nulle:
        problemi.append(f"{len(nulle)} linee di lunghezza zero.")
    viste: set = set()
    doppie = 0
    for e in msp.query("LINE"):
        chiave = tuple(sorted([tuple(round(c, 6) for c in e.dxf.start), tuple(round(c, 6) for c in e.dxf.end)]))
        doppie += chiave in viste
        viste.add(chiave)
    if doppie:
        problemi.append(f"{doppie} linee sovrapposte (doppioni).")
    sconosciute = sum(n for t, n in tipi.items() if t in ("ACAD_PROXY_ENTITY", "UNKNOWN"))
    if sconosciute:
        problemi.append(f"{sconosciute} entità proxy o sconosciute: potrebbero non essere conservate.")
    if per_layer.get("0", 0) > 0.5 * max(1, sum(per_layer.values())):
        problemi.append("Più della metà delle entità sta sul layer 0: i layer non aiutano a riconoscere gli elementi.")

    return Rapporto(
        file=percorso,
        versione=doc.dxfversion,
        unita=unita,
        entita=dict(tipi.most_common()),
        layer=dict(per_layer.most_common()),
        estensione=estensione,
        blocchi=sum(1 for b in doc.blocks if not b.name.startswith("*")),
        riferimenti_esterni=xref,
        problemi=problemi,
    )


def metri_per_unita(unita: str) -> float:
    if unita not in METRI:
        raise ValueError(f"Unità non gestita: {unita}. Usa mm, cm o m.")
    return METRI[unita]


__all__ = ["Rapporto", "apri", "leggi", "metri_per_unita", "units"]
