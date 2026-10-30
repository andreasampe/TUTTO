"""Test dei ritocchi dall'area "Planimetrie" (modifica.py e webapi.py).

    python -m unittest discover -s tests
"""
import json
import shutil
import sys
import tempfile
import unittest
from pathlib import Path

import ezdxf

QUI = Path(__file__).resolve().parent
sys.path.insert(0, str(QUI.parent))
sys.path.insert(0, str(QUI.parent / "esempi"))

from domus_cad import webapi  # noqa: E402
from domus_cad.elabora import elabora  # noqa: E402
from genera_esempio import genera  # noqa: E402


class Ritocchi(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.dir = Path(tempfile.mkdtemp(prefix="domus-ritocchi-"))
        esito = elabora(str(genera(cls.dir / "pianta.dxf")), str(cls.dir / "p"), ora="x")
        cls.elaborato = esito["elaborato"]
        cls.modello = json.loads(webapi.leggi(cls.elaborato))

    @classmethod
    def tearDownClass(cls):
        shutil.rmtree(cls.dir, ignore_errors=True)

    def ritocca(self, modifiche):
        uscita = str(self.dir / "ritoccato.dxf")
        resoconto = json.loads(webapi.ritocca(self.elaborato, uscita, json.dumps(modifiche)))
        return resoconto, json.loads(webapi.leggi(uscita)), uscita

    def test_modello_per_il_visualizzatore(self):
        m = self.modello
        self.assertGreater(len(m["geometria"]), 10)
        self.assertTrue(all(q["testo"] for q in m["quote"]))   # ogni quota ha il suo testo, es. "4.90"
        self.assertIn("4.90", [q["testo"] for q in m["quote"]])
        self.assertEqual(m["cartigli"][0]["nome"], "DOMUS_CARTIGLIO")
        self.assertIn("SOGGIORNO", [t["t"] for t in m["testi"]])
        x0, y0, x1, y1 = m["estensione"]
        self.assertTrue(x0 < 0 < 12 < x1 and y0 < 0 < 9 < y1)

    def test_sposta_linea_e_testo_senza_cambiare_la_misura(self):
        q = next(q for q in self.modello["quote"] if q["tipo"] == "lineare" and q["misura"] > 1)
        r = next(x for x in self.modello["quote"] if x["tipo"] == "lineare" and x["h"] != q["h"] and x["misura"] > 1)
        res, m, _ = self.ritocca([
            {"h": q["h"], "azione": "sposta_linea", "base": [q["base"][0] + 0.5, q["base"][1] + 0.5]},
            {"h": r["h"], "azione": "sposta_testo", "pos": [r["p1"][0], r["p1"][1] + 0.3]},
        ])
        self.assertTrue(res["superato"], res)
        nuove = {x["h"]: x for x in m["quote"]}
        self.assertAlmostEqual(nuove[q["h"]]["misura"], q["misura"], places=9)
        self.assertNotEqual(nuove[q["h"]]["base"], q["base"])
        self.assertEqual(nuove[r["h"]]["testo"], r["testo"])        # stesso testo, es. "4.20"
        self.assertNotEqual(nuove[r["h"]]["pt"], r["pt"])            # ma in un altro punto

    def test_elimina_quota_e_cambia_cartiglio(self):
        q = self.modello["quote"][0]
        c = self.modello["cartigli"][0]
        res, m, uscita = self.ritocca([
            {"h": q["h"], "azione": "elimina"},
            {"h": c["h"], "azione": "attributi", "valori": {"TAVOLA": "A.02"}},
        ])
        self.assertTrue(res["superato"], res)
        self.assertNotIn(q["h"], [x["h"] for x in m["quote"]])
        self.assertEqual(len(m["quote"]), len(self.modello["quote"]) - 1)
        self.assertEqual({a["tag"]: a["valore"] for a in m["cartigli"][0]["attr"]}["TAVOLA"], "A.02")
        # il blocco grafico della quota eliminata non resta nel file
        self.assertNotIn(q["h"], [e.dxf.handle for e in ezdxf.readfile(uscita).modelspace()])

    def test_cambia_un_testo(self):
        t = next(t for t in self.modello["testi"] if t["t"] == "CUCINA")
        res, m, _ = self.ritocca([{"h": t["h"], "azione": "testo", "valore": "CUCINA ABITABILE"}])
        self.assertTrue(res["superato"], res)
        self.assertIn("CUCINA ABITABILE", [x["t"] for x in m["testi"]])

    def test_modifica_impossibile_segnalata(self):
        res, _, _ = self.ritocca([{"h": "FFFFFF", "azione": "elimina"}])
        self.assertFalse(res["superato"])
        self.assertIn("non trovata", res["errori"][0])

    def test_quota_automatica_e_profilo_dal_browser(self):
        esito = json.loads(webapi.quota(str(self.dir / "pianta.dxf"), str(self.dir / "q"), ""))
        self.assertTrue(esito["superato"])
        self.assertTrue(Path(esito["percorso"]).exists())
        p = json.loads(webapi.ricava_profilo(self.elaborato))   # l'elaborato contiene quote: fa da riferimento
        self.assertEqual(p["layer_quote"]["name"], "01Cotas 1")



class MotoreNellaWebapp(unittest.TestCase):
    def test_motore_json_aggiornato(self):
        # se fallisce: python web/esporta_per_domus.py
        sys.path.insert(0, str(QUI.parent / "web"))
        from esporta_per_domus import DESTINAZIONE, contenuto
        salvato = json.loads((DESTINAZIONE / "motore.json").read_text(encoding="utf-8"))
        self.assertEqual(salvato, contenuto())


if __name__ == "__main__":
    unittest.main()
