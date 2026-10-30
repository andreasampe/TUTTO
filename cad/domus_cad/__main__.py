"""Riga di comando del prototipo.

    python -m domus_cad leggi    pianta.dxf
    python -m domus_cad elabora  pianta.dxf --uscita lavori/progetto --scala 100 --formato cm
"""
from __future__ import annotations

import argparse
import json
import sys
from dataclasses import asdict

from . import lettura
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
    e.add_argument("--scala", type=int, default=100, help="scala di stampa, es. 100 per 1:100")
    e.add_argument("--formato", choices=["cm", "m", "mm"], default="cm", help="unità del testo delle quote")
    e.add_argument("--layer-muri", nargs="*", help="layer da quotare (predefinito: tutti)")
    e.add_argument("--progetto", default="")
    e.add_argument("--studio", default="")
    e.add_argument("--elaborato", default="Pianta quotata - stato di fatto")
    a = p.parse_args(argv)

    try:
        if a.comando == "leggi":
            doc = lettura.apri(a.file)
            print(json.dumps(asdict(lettura.leggi(doc, a.file)), indent=2, ensure_ascii=False))
            return 0
        esito = elabora(a.file, a.uscita, unita=a.unita, scala=a.scala, formato=a.formato,
                        layer_muri=set(a.layer_muri) if a.layer_muri else None,
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
