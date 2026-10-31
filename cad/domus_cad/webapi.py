"""Funzioni chiamate dal browser (Pyodide) nell'area "Planimetrie" della webapp.

Ricevono percorsi di file nel file system virtuale del browser e restituiscono testo JSON.
Le stesse funzioni si possono usare da riga di comando o nei test.
"""
from __future__ import annotations

import json
import time
from pathlib import Path

from . import lettura, modifica, profilo


def _json(dati) -> str:
    return json.dumps(dati, ensure_ascii=False, default=str)


def leggi(percorso: str) -> str:
    """Modello del disegno per il visualizzatore."""
    doc = lettura.apri(percorso)
    rapporto = lettura.leggi(doc, percorso)
    dati = modifica.modello(doc)
    dati["unita"] = rapporto.unita
    dati["versione"] = rapporto.versione
    return _json(dati)


def ritocca(origine: str, destinazione: str, modifiche_json: str, profilo_json: str = "") -> str:
    """Applica i ritocchi e salva una nuova versione; l'originale resta com'è.

    `profilo_json`: profilo dello studio per le quote aggiunte (vuoto = profilo predefinito)."""
    doc = lettura.apri(origine)
    resoconto = modifica.applica(doc, json.loads(modifiche_json), json.loads(profilo_json) if profilo_json else None)
    profilo.fissa_stili(doc)  # gli stili di quota restano come li vede AutoCAD
    doc.saveas(destinazione)
    lettura.apri(destinazione)  # deve riaprirsi senza errori
    resoconto["controlli"].append({"nome": "Riapertura", "ok": True, "dettaglio": "il file salvato si riapre senza errori"})
    return _json(resoconto)


def quota(origine: str, cartella: str, profilo_json: str, opzioni_json: str = "{}") -> str:
    """Pianta quotata automatica con il profilo dello studio."""
    from .elabora import elabora

    opzioni = json.loads(opzioni_json or "{}")
    prof = json.loads(profilo_json) if profilo_json else None
    t = time.time()
    esito = elabora(origine, cartella, profilo=prof, ora="browser", **opzioni)
    esito["secondi"] = round(time.time() - t, 1)
    esito["percorso"] = str(Path(cartella) / "elaborazioni" / "browser" / "elaborato.dxf")
    esito.pop("lettura", None)
    return _json({k: esito[k] for k in ("controlli", "log", "superato", "secondi", "percorso")})


def ricava_profilo(percorso: str) -> str:
    """Profilo dello studio dal suo file di riferimento (disegno già quotato a mano)."""
    return _json(profilo.rileva(lettura.apri(percorso)))
