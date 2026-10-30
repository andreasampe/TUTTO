"""Copia il motore DOMUS CAD nella webapp: domus/assets/cad/.

L'area "Planimetrie" della webapp esegue il motore nel browser (Pyodide). Questo script prepara:
- motore.json: i sorgenti di `domus_cad` e il profilo predefinito dello studio (profili/studio.json);
- il pacchetto ezdxf (MIT), servito dal sito stesso invece che da PyPI.

Va rilanciato ogni volta che si modifica `domus_cad`:
    python web/esporta_per_domus.py [percorso del pacchetto ezdxf .whl]
Un test controlla che motore.json sia aggiornato.
"""
import json
import shutil
import sys
from pathlib import Path

QUI = Path(__file__).resolve().parent
CAD = QUI.parent
DESTINAZIONE = CAD.parent / "domus" / "assets" / "cad"
RUOTA = "ezdxf-1.4.2-py3-none-any.whl"


def contenuto() -> dict:
    sorgenti = {f"domus_cad/{p.name}": p.read_text(encoding="utf-8") for p in sorted((CAD / "domus_cad").glob("*.py"))}
    sorgenti["profili/studio.json"] = (CAD / "profili" / "studio.json").read_text(encoding="utf-8")
    return {"versione_ezdxf": "1.4.2", "file": sorgenti}


def esporta(ruota: str | None = None) -> Path:
    DESTINAZIONE.mkdir(parents=True, exist_ok=True)
    uscita = DESTINAZIONE / "motore.json"
    uscita.write_text(json.dumps(contenuto(), ensure_ascii=False, sort_keys=True), encoding="utf-8")
    if ruota:
        shutil.copy2(ruota, DESTINAZIONE / RUOTA)
    return uscita


if __name__ == "__main__":
    print(esporta(sys.argv[1] if len(sys.argv) > 1 else None))
