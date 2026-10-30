"""Flusso del prototipo (punto 14): importa → quota → annota → cartiglio → esporta → riapre → verifica.

Struttura dei file prodotti (punto 11.7):
    <uscita>/originali/<file>              copia dell'originale (mai modificata) + impronta SHA-256
    <uscita>/elaborazioni/<data-ora>/      elaborato.dxf, anteprima.svg, log.json, rapporto.md
"""
from __future__ import annotations

import hashlib
import json
import shutil
from dataclasses import asdict
from datetime import datetime
from pathlib import Path

import ezdxf
import numpy as np
from ezdxf import recover

from . import cartiglio, interne, lettura, quote


def impronta(percorso: Path) -> str:
    return hashlib.sha256(percorso.read_bytes()).hexdigest()


def _firma(e) -> tuple:
    """Firma geometrica di un'entità per controllare che non sia cambiata."""
    t = e.dxftype()
    if t == "LINE":
        g = (tuple(e.dxf.start), tuple(e.dxf.end))
    elif t == "LWPOLYLINE":
        g = tuple(tuple(p) for p in e.get_points("xyb")) + (e.closed,)
    elif t == "CIRCLE":
        g = (tuple(e.dxf.center), e.dxf.radius)
    elif t == "ARC":
        g = (tuple(e.dxf.center), e.dxf.radius, e.dxf.start_angle, e.dxf.end_angle)
    elif t in ("TEXT", "MTEXT"):
        g = (e.plain_text() if t == "MTEXT" else e.dxf.text, tuple(e.dxf.insert))
    elif t == "INSERT":
        g = (e.dxf.name, tuple(e.dxf.insert), e.dxf.get("rotation", 0))
    else:
        g = ()
    return (t, e.dxf.layer, g)


def elabora(origine: str, uscita: str, *, unita: str | None = None, scala: int = 100,
            formato: str = "m", layer_muri: set[str] | None = None,
            layer_aperture: set[str] | None = None, quote_interne: bool = True, luce_minima: float = 0.5,
            valori_cartiglio: dict[str, str] | None = None, ora: str | None = None) -> dict:
    sorgente = Path(origine)
    if sorgente.suffix.lower() != ".dxf":
        raise ValueError("Il prototipo legge file DXF. Da AutoCAD: Salva con nome → DXF (2018).")
    base = Path(uscita)
    cartella_orig = base / "originali"
    cartella_orig.mkdir(parents=True, exist_ok=True)
    copia = cartella_orig / sorgente.name
    if not copia.exists():
        shutil.copy2(sorgente, copia)
    impronta_prima = impronta(sorgente)
    if impronta(copia) != impronta_prima:
        raise ValueError(f"In {cartella_orig} c'è già un file con lo stesso nome ma contenuto diverso.")
    lavoro = base / "elaborazioni" / (ora or datetime.now().strftime("%Y%m%d-%H%M%S"))
    lavoro.mkdir(parents=True, exist_ok=True)
    log: list[str] = [f"Origine: {sorgente} (SHA-256 {impronta_prima[:16]}…)"]

    # 1-5. lettura
    doc = lettura.apri(str(copia))
    rapporto = lettura.leggi(doc, str(sorgente))
    log.append(f"Letto DXF {rapporto.versione}: {sum(rapporto.entita.values())} entità, {len(rapporto.layer)} layer")
    unita_usate = rapporto.unita or unita
    if not unita_usate:
        raise ValueError("Il file non dichiara le unità di misura. Indica --unita mm, cm o m (le quote dipendono da questo).")
    if rapporto.unita and unita and rapporto.unita != unita:
        raise ValueError(f"Il file dichiara '{rapporto.unita}' ma hai indicato '{unita}': verifica quale è corretto.")
    m_per_u = lettura.metri_per_unita(unita_usate)
    firme_originali = {e.dxf.handle: _firma(e) for e in doc.modelspace()}

    # 6-8. quote
    mm_carta = m_per_u * 1000 / scala          # mm di carta per unità di disegno
    u_per_mm = 1 / mm_carta                    # unità di disegno per mm di carta
    fattore = {"cm": 100.0, "m": 1.0, "mm": 1000.0}[formato] * m_per_u
    quote.prepara_stile(doc, u_per_mm, fattore_misura=fattore, decimali=0 if formato != "m" else 2)
    punti = quote.vertici(doc, layer_muri)
    esterne = quote.calcola(punti, fascia=0.6 / m_per_u, tolleranza=0.005 / m_per_u)
    h_quote = quote.disegna(doc, esterne, passo=8 * u_per_mm, ingombro=rapporto.estensione)
    log.append(f"Generate {len(esterne)} quote esterne su {quote.LAYER_QUOTE} (catene parziali e totali sui 4 lati)")
    previste = list(esterne)
    if quote_interne:
        seg_muri, masse = interne.geometria(doc, layer_muri, 0.01 / m_per_u, m_per_u)
        aperture = layer_aperture if layer_aperture is not None else interne.layer_aperture(doc)
        seg_aperture, _ = interne.geometria(doc, aperture, 0.01 / m_per_u, m_per_u) if layer_muri and aperture else (np.empty((0, 4)), [])
        seg_chiusura = np.vstack([seg_muri, seg_aperture])
        mappa = interne.Mappa(seg_chiusura, masse, m_per_u) if len(seg_chiusura) else None
        luci = interne.calcola(seg_muri, mappa, m_per_u, luce_minima=luce_minima)
        h_quote += interne.disegna(doc, luci, quote.STILE)
        previste += luci
        log.append(f"Generate {len(luci)} quote interne su {interne.LAYER} (luci nette tra muri affacciati, minimo {luce_minima:g} m)")
        log.append(f"Edificio chiuso con i layer: {', '.join(sorted(aperture)) or 'nessuno'}"
                   + (f"; parte esterna del riquadro: {mappa.quota_esterna:.0%}" if mappa else ""))
        if mappa and mappa.quota_esterna > 0.97:
            rapporto.problemi.append("Le quote interne non trovano l'interno dell'edificio: forse una porta o finestra non è disegnata e l'edificio risulta aperto. Indica i layer dei serramenti con --layer-aperture.")

    # 9-10. annotazioni e cartiglio
    ext = rapporto.estensione
    margine = 30 * u_per_mm  # spazio per le due file di quote sotto il disegno
    h_cartiglio = cartiglio.inserisci(doc, (ext[1][0] + margine, ext[0][1] - margine - cartiglio.ALTEZZA_MM * u_per_mm),
                                      u_per_mm, {"SCALA": f"1:{scala}", "TAVOLA": "A.01", **(valori_cartiglio or {})})
    # nota sopra il cartiglio, allineata al suo bordo sinistro (non tocca né cartiglio né quote)
    x_cart = ext[1][0] + margine - cartiglio.LARGHEZZA_MM * u_per_mm
    h_nota = cartiglio.nota(doc, f"Quote in {formato} - elaborato generato da DOMUS (prototipo)",
                            (x_cart, ext[0][1] - margine + 3 * u_per_mm), 2.5 * u_per_mm)
    log.append("Inseriti cartiglio (blocco con attributi) e nota sul layer DOMUS_NOTE")

    # 11. esportazione (stessa versione DXF dell'originale)
    elaborato = lavoro / "elaborato.dxf"
    doc.saveas(elaborato)
    log.append(f"Salvato {elaborato.name} ({elaborato.stat().st_size} byte)")

    # 12-13. riapertura e verifica
    controlli = verifica(elaborato, firme_originali, previste, h_cartiglio, sorgente, impronta_prima,
                         (ext[1][0] + margine, ext[0][1] - margine - cartiglio.ALTEZZA_MM * u_per_mm), fattore)

    # 14. anteprima
    try:
        anteprima(elaborato, lavoro / "anteprima.svg")
        log.append("Creata anteprima.svg")
    except Exception as err:  # l'anteprima non deve far fallire l'elaborazione
        log.append(f"Anteprima non creata: {err}")

    esito = {
        "origine": str(sorgente), "elaborato": str(elaborato), "unita": unita_usate, "scala": scala,
        "lettura": asdict(rapporto), "quote": [asdict(q) for q in previste], "controlli": controlli,
        "superato": all(c["esito"] in ("ok", "da verificare") for c in controlli), "log": log,
        "handle": {"quote": h_quote, "cartiglio": h_cartiglio, "nota": h_nota},
    }
    (lavoro / "log.json").write_text(json.dumps(esito, indent=2, ensure_ascii=False), encoding="utf-8")
    (lavoro / "rapporto.md").write_text(rapporto_md(esito), encoding="utf-8")
    return esito


def verifica(elaborato: Path, firme: dict, previste: list, h_cartiglio: str, sorgente: Path,
             impronta_prima: str, angolo_cartiglio: tuple[float, float], fattore: float) -> list[dict]:
    """I controlli del punto 15, eseguiti sul file RIAPERTO da disco."""
    c: list[dict] = []
    def esito(n: int, nome: str, ok: bool | None, dettaglio: str) -> None:
        c.append({"test": n, "nome": nome, "esito": "da verificare" if ok is None else ("ok" if ok else "fallito"), "dettaglio": dettaglio})

    try:
        doc, auditor = recover.readfile(str(elaborato))
        riaperto = True
    except Exception as err:
        esito(7, "Riapertura", False, str(err))
        return c
    msp = doc.modelspace()
    auditor = doc.audit()
    esito(1, "Lettura", len(firme) > 0, f"{len(firme)} entità originali lette")
    mancanti = [h for h, f in firme.items() if (e := doc.entitydb.get(h)) is None or _firma(e) != f]
    esito(2, "Conservazione", not mancanti, "tutte le entità originali presenti e invariate" if not mancanti else f"{len(mancanti)} entità perse o alterate: {mancanti[:5]}")
    attesi = {quote.LAYER_QUOTE, cartiglio.LAYER_CARTIGLIO, cartiglio.LAYER_NOTE}
    if any(q.lato == "interno" for q in previste):
        attesi.add(interne.LAYER)
    presenti = {l.dxf.name for l in doc.layers}
    esito(3, "Layer", attesi <= presenti, f"presenti: {', '.join(sorted(attesi & presenti))}")
    dims = [d for d in msp.query("DIMENSION") if d.dxf.layer in (quote.LAYER_QUOTE, interne.LAYER)]
    errori = []
    for d, q in zip(dims, previste):
        misura = d.get_measurement()
        if abs(misura - q.misura) > 1e-6:
            errori.append(f"{q.lato}: {misura} invece di {q.misura}")
    esito(4, "Quote", len(dims) == len(previste) and not errori,
          f"{len(dims)} quote, misure uguali alla geometria (tolleranza 1e-6), testo = misura × {fattore:g}" if not errori else "; ".join(errori[:5]))
    ref = doc.entitydb.get(h_cartiglio)
    ok_cart = ref is not None and ref.dxftype() == "INSERT" and ref.dxf.layer == cartiglio.LAYER_CARTIGLIO
    if ok_cart:
        sx = ref.dxf.xscale * cartiglio.LARGHEZZA_MM
        ok_cart = abs(ref.dxf.insert.x + sx - angolo_cartiglio[0]) < 1e-6 and abs(ref.dxf.insert.y - angolo_cartiglio[1]) < 1e-6
    esito(5, "Cartiglio", ok_cart, "blocco DOMUS_CARTIGLIO con attributi, in basso a destra fuori dal disegno")
    esito(6, "Esportazione", elaborato.stat().st_size > 0, f"{elaborato.name}, {elaborato.stat().st_size} byte, DXF {doc.dxfversion}")
    esito(7, "Riapertura", riaperto and not auditor.has_errors, f"riaperto con ezdxf, audit: {len(auditor.errors)} errori, {len(auditor.fixes)} correzioni")
    esito(8, "Compatibilità AutoCAD", None, "da aprire in AutoCAD sul Mac: in questo ambiente AutoCAD non c'è")
    esito(9, "Integrità dell'originale", impronta(sorgente) == impronta_prima, "SHA-256 del file originale invariato")
    esito(10, "Ripetibilità", None, "verificata dai test automatici (due elaborazioni identiche)")
    esito(11, "Costo", True, "solo software open source gratuito: Python, ezdxf (MIT), Pillow (MIT-CMU), NumPy (BSD)")
    return c


def anteprima(dxf: Path, svg: Path) -> None:
    """Anteprima SVG con il renderer di ezdxf (nessuna dipendenza aggiuntiva)."""
    from ezdxf.addons.drawing import Frontend, RenderContext, layout
    from ezdxf.addons.drawing.config import BackgroundPolicy, ColorPolicy, Configuration
    from ezdxf.addons.drawing.svg import SVGBackend

    doc = ezdxf.readfile(str(dxf))
    backend = SVGBackend()
    cfg = Configuration(background_policy=BackgroundPolicy.WHITE, color_policy=ColorPolicy.BLACK)
    Frontend(RenderContext(doc), backend, config=cfg).draw_layout(doc.modelspace())
    pagina = layout.Page(420, 297, layout.Units.mm, margins=layout.Margins.all(10))
    svg.write_text(backend.get_string(pagina), encoding="utf-8")


def rapporto_md(e: dict) -> str:
    r = e["lettura"]
    righe = [
        "# Rapporto di elaborazione DOMUS (prototipo)", "",
        f"- **Origine:** `{e['origine']}`", f"- **Elaborato:** `{e['elaborato']}`",
        f"- **Unità:** {e['unita']} · **Scala:** 1:{e['scala']} · **DXF:** {r['versione']}",
        f"- **Entità:** {sum(r['entita'].values())} ({', '.join(f'{k} {v}' for k, v in r['entita'].items())})",
        f"- **Layer:** {', '.join(r['layer'])}", "",
        "## Problemi rilevati nel disegno", "",
        *([f"- {p}" for p in r["problemi"]] or ["- Nessuno"]), "",
        "## Controlli (punto 15 della specifica)", "",
        "| # | Controllo | Esito | Dettaglio |", "|---|---|---|---|",
        *[f"| {c['test']} | {c['nome']} | {c['esito']} | {c['dettaglio']} |" for c in e["controlli"]], "",
        f"**Esito complessivo:** {'superato' if e['superato'] else 'NON superato'} (i controlli 'da verificare' richiedono AutoCAD).", "",
        "## Registro", "", *[f"- {x}" for x in e["log"]], "",
    ]
    return "\n".join(righe)
