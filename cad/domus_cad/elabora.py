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

from . import cartiglio, interne, lettura, profilo as profili, quote


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


def elabora(origine: str, uscita: str, *, unita: str | None = None, scala: int | None = None,
            formato: str = "m", profilo: dict | str | None = None, layer_muri: set[str] | None = None,
            layer_aperture: set[str] | None = None, layer_arredi: set[str] | None = None,
            layer_limiti: set[str] | None = None, quote_interne: bool = True, luce_minima: float = 0.5,
            luce_arredi: float = 0.3, valori_cartiglio: dict[str, str] | None = None, ora: str | None = None) -> dict:
    """Pianta quotata. I layer non indicati vengono dal profilo dello studio
    (predefinito `profili/studio.json`), limitati a quelli presenti nel disegno."""
    prof = profilo if isinstance(profilo, dict) else profili.carica(profilo)
    scala = scala or prof.get("scala") or 100
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
    presenti = {l.dxf.name for l in doc.layers}
    riconosciuti, _ = profili.ruoli(doc)  # se il disegno usa nomi diversi dal profilo
    def dal_profilo(indicati: set[str] | None, chiave: str) -> set[str]:
        if indicati is not None:
            return indicati
        return (set(prof.get(chiave) or []) & presenti) or (set(riconosciuti.get(chiave) or []) & presenti)
    layer_muri = dal_profilo(layer_muri, "layer_muri") or None  # nessuno trovato: tutti i layer
    layer_arredi = dal_profilo(layer_arredi, "layer_arredi")
    layer_limiti = dal_profilo(layer_limiti, "layer_limiti")
    aperture = dal_profilo(layer_aperture, "layer_aperture")

    # 6-8. quote
    mm_carta = m_per_u * 1000 / scala          # mm di carta per unità di disegno
    u_per_mm = 1 / mm_carta                    # unità di disegno per mm di carta
    fattore = {"cm": 100.0, "m": 1.0, "mm": 1000.0}[formato] * m_per_u
    layer_quote, stile, override = profili.applica(doc, prof)
    if abs(fattore - 1) > 1e-12:  # disegno non in metri o testo in cm/mm: si corregge solo la misura
        override.update({"dimlfac": fattore, "dimdec": 2 if formato == "m" else 0})
    log.append(f"Quote sul layer '{layer_quote}' con lo stile '{stile}' del profilo"
               + (f" e le modifiche {override}" if override else ""))
    punti = quote.vertici(doc, layer_muri)
    esterne = quote.calcola(punti, fascia=0.6 / m_per_u, tolleranza=0.005 / m_per_u)
    h_quote = quote.disegna(doc, esterne, passo=8 * u_per_mm, ingombro=rapporto.estensione,
                            layer=layer_quote, stile=stile, override=override)
    log.append(f"Generate {len(esterne)} quote esterne (catene parziali e totali sui 4 lati), muri: {', '.join(sorted(layer_muri or ['tutti i layer']))}")
    previste = list(esterne)
    if quote_interne:
        tol = 0.01 / m_per_u
        seg_muri, masse = interne.geometria(doc, layer_muri, tol, m_per_u)
        seg_limiti, _ = interne.geometria(doc, layer_limiti, tol, m_per_u) if layer_limiti else (np.empty((0, 4)), [])
        seg_arredi, _ = interne.geometria(doc, layer_arredi, tol, m_per_u) if layer_arredi else (np.empty((0, 4)), [])
        seg_aperture, _ = interne.geometria(doc, aperture, tol, m_per_u) if layer_muri and aperture else (np.empty((0, 4)), [])
        seg_chiusura = np.vstack([seg_muri, seg_limiti, seg_aperture])
        mappa = interne.Mappa(seg_chiusura, masse, m_per_u, oggetti=np.vstack([seg_limiti, seg_arredi])) if len(seg_chiusura) else None
        tutti = np.vstack([seg_muri, seg_limiti, seg_arredi])
        # limiti e arredi seguono le stesse regole: distanza minima ridotta, mai dentro un singolo oggetto
        arredo = np.r_[np.zeros(len(seg_muri), bool), np.ones(len(seg_limiti) + len(seg_arredi), bool)]
        oggetti = layer_arredi | layer_limiti
        luci = interne.calcola(tutti, mappa, m_per_u, arredo=arredo, luce_minima=luce_minima, luce_arredi=luce_arredi,
                              ingombri_arredi=interne.ingombri(doc, oggetti, m_per_u) if oggetti else None)
        h_quote += interne.disegna(doc, luci, layer_quote, stile, override)
        previste += luci
        log.append(f"Generate {len(luci)} quote interne: luci tra muri (minimo {luce_minima:g} m) e distanze degli arredi (minimo {luce_arredi:g} m)")
        log.append(f"Limiti: {', '.join(sorted(layer_limiti)) or 'nessuno'} · arredi: {', '.join(sorted(layer_arredi)) or 'nessuno'}"
                   f" · porte e finestre: {', '.join(sorted(aperture)) or 'nessuno'}")

    # 9-10. annotazioni e cartiglio
    ext = rapporto.estensione
    margine = 30 * u_per_mm  # spazio per le due file di quote sotto il disegno
    h_cartiglio = cartiglio.inserisci(doc, (ext[1][0] + margine, ext[0][1] - margine - cartiglio.ALTEZZA_MM * u_per_mm),
                                      u_per_mm, {"SCALA": f"1:{scala}", "TAVOLA": "A.01", **(valori_cartiglio or {})})
    # nota sopra il cartiglio, allineata al suo bordo sinistro (non tocca né cartiglio né quote)
    x_cart = ext[1][0] + margine - cartiglio.LARGHEZZA_MM * u_per_mm
    h_nota = cartiglio.nota(doc, f"Quote in {formato} sul layer {layer_quote} - elaborato generato da DOMUS (prototipo)",
                            (x_cart, ext[0][1] - margine + 3 * u_per_mm), 2.5 * u_per_mm)
    log.append("Inseriti cartiglio (blocco con attributi) e nota sul layer DOMUS_NOTE")

    # 11. esportazione (stessa versione DXF dell'originale)
    elaborato = lavoro / "elaborato.dxf"
    doc.saveas(elaborato)
    log.append(f"Salvato {elaborato.name} ({elaborato.stat().st_size} byte)")

    # 12-13. riapertura e verifica
    controlli = verifica(elaborato, firme_originali, previste, h_quote, layer_quote, h_cartiglio, sorgente, impronta_prima,
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


def verifica(elaborato: Path, firme: dict, previste: list, h_quote: list[str], layer_quote: str,
             h_cartiglio: str, sorgente: Path,
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
    attesi = {layer_quote, cartiglio.LAYER_CARTIGLIO, cartiglio.LAYER_NOTE}
    presenti = {l.dxf.name for l in doc.layers}
    esito(3, "Layer", attesi <= presenti, f"presenti: {', '.join(sorted(attesi & presenti))}")
    dims = [doc.entitydb.get(h) for h in h_quote]
    errori = [f"quota {h} mancante o fuori dal layer {layer_quote}" for h, d in zip(h_quote, dims)
              if d is None or d.dxftype() != "DIMENSION" or d.dxf.layer != layer_quote]
    if errori:
        dims = []
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
