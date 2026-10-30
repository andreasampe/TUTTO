"""Riga di comando del prototipo.

    python -m domus_cad leggi    pianta.dxf
    python -m domus_cad elabora  pianta.dxf --uscita lavori/progetto --scala 50
"""
from __future__ import annotations

import argparse
import json
import sys
from dataclasses import asdict

from pathlib import Path

from . import lettura, profilo
from .elabora import elabora


def main(argv: list[str] | None = None) -> int:
    p = argparse.ArgumentParser(prog="domus_cad", description="DOMUS — prototipo CAD: pianta quotata da una planimetria DXF")
    sub = p.add_subparsers(dest="comando", required=True)
    l = sub.add_parser("leggi", help="analizza il disegno senza modificarlo")
    l.add_argument("file")
    e = sub.add_parser("elabora", help="genera la pianta quotata")
    e.add_argument("file")
    e.add_argument("--uscita", required=True, help="cartella del progetto (originali, elaborazioni)")
    e.add_argument("--unita", choices=["mm", "cm", "m"], help="obbligatoria se il file non dichiara le unità")
    e.add_argument("--scala", type=int, help="scala di stampa, es. 50 per 1:50 (predefinita: quella del profilo)")
    e.add_argument("--profilo", help="profilo dello studio (predefinito: profili/studio.json)")
    e.add_argument("--formato", choices=["cm", "m", "mm"], default="m", help="unità del testo delle quote (predefinito: metri)")
    e.add_argument("--layer-muri", nargs="*", help="layer dei muri (predefinito: quelli del profilo, altrimenti tutti)")
    e.add_argument("--layer-aperture", nargs="*", help="layer di porte e finestre (predefinito: profilo o riconosciuti dal nome)")
    e.add_argument("--layer-arredi", nargs="*", help="layer degli arredi da quotare (predefinito: quelli del profilo)")
    e.add_argument("--layer-limiti", nargs="*", help="linee che chiudono zone aperte, es. garage e recinzioni (predefinito: profilo)")
    e.add_argument("--senza-interne", action="store_true", help="solo le catene esterne, niente quote dentro i locali")
    e.add_argument("--luce-minima", type=float, default=0.5, help="luce minima tra muri da quotare, in metri")
    e.add_argument("--luce-arredi", type=float, default=0.3, help="distanza minima degli arredi da quotare, in metri")
    r = sub.add_parser("profilo", help="ricava il profilo dello studio da un disegno di riferimento")
    r.add_argument("file")
    r.add_argument("--layer", help='layer delle quote, es. "01Cotas 1" (predefinito: riconosciuto in automatico)')
    r.add_argument("--stile", help="stile di quota, es. 50 (predefinito: riconosciuto in automatico)")
    r.add_argument("--uscita", required=True, help="file JSON da scrivere")
    e.add_argument("--progetto", default="")
    e.add_argument("--studio", default="")
    e.add_argument("--elaborato", default="Pianta quotata - stato di fatto")
    a = p.parse_args(argv)

    try:
        if a.comando == "leggi":
            doc = lettura.apri(a.file)
            print(json.dumps(asdict(lettura.leggi(doc, a.file)), indent=2, ensure_ascii=False))
            return 0
        if a.comando == "profilo":
            rif = lettura.apri(a.file)
            dati = profilo.rileva(rif)
            if a.layer or a.stile:  # scelta manuale di layer o stile
                dati.update(profilo.estrai(rif, a.layer or dati["layer_quote"]["name"], a.stile or dati["stile_quote"]["name"]))
            Path(a.uscita).parent.mkdir(parents=True, exist_ok=True)
            Path(a.uscita).write_text(json.dumps(dati, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
            print("\n".join(f"  - {m}" for m in dati["rilevato"]))
            print(f"Profilo scritto in {a.uscita}: controlla i ruoli dei layer.")
            return 0
        insieme = lambda v: set(v) if v is not None else None  # noqa: E731
        esito = elabora(a.file, a.uscita, unita=a.unita, scala=a.scala, formato=a.formato,
                        profilo=a.profilo, layer_muri=insieme(a.layer_muri), layer_aperture=insieme(a.layer_aperture),
                        layer_arredi=insieme(a.layer_arredi), layer_limiti=insieme(a.layer_limiti),
                        quote_interne=not a.senza_interne, luce_minima=a.luce_minima, luce_arredi=a.luce_arredi,
                        valori_cartiglio={"PROGETTO": a.progetto, "STUDIO": a.studio, "ELABORATO": a.elaborato})
    except (ValueError, OSError) as err:
        print(f"Errore: {err}", file=sys.stderr)
        return 2
    for c in esito["controlli"]:
        print(f"  [{c['esito']:>13}] {c['test']:>2}. {c['nome']}: {c['dettaglio']}")
    print(f"\nElaborato: {esito['elaborato']}")
    return 0 if esito["superato"] else 1


if __name__ == "__main__":
    sys.exit(main())
