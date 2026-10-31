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

    def test_aggiungi_quota_sul_layer_dello_studio(self):
        # tra i due spigoli interni del soggiorno: 0,3 → 5,2 = 4,90 m, linea a y = 2
        res, m, uscita = self.ritocca([
            {"azione": "aggiungi", "p1": [5.2, 1.0], "p2": [0.3, 1.0], "base": [2.0, 2.0], "angolo": 0},
            {"azione": "aggiungi", "p1": [0.3, 0.3], "p2": [0.3, 4.5], "base": [1.0, 2.0], "angolo": 90},
        ])
        self.assertTrue(res["superato"], res)
        self.assertEqual(res["riepilogo"]["aggiungi"], 2)
        nuove = [q for q in m["quote"] if q["h"] not in {x["h"] for x in self.modello["quote"]}]
        self.assertEqual(sorted(q["testo"] for q in nuove), ["4.20", "4.90"])
        self.assertTrue(all(q["l"] == "01Cotas 1" and q["stile"] == "50" for q in nuove))
        # pallini con il trattino verso l'interno anche se i punti sono dati al contrario
        doc = ezdxf.readfile(uscita)
        d = next(x for x in doc.modelspace().query("DIMENSION") if x.dxf.handle == next(q["h"] for q in nuove if q["testo"] == "4.90"))
        self.assertLess(d.dxf.defpoint2.x, d.dxf.defpoint3.x)

    def test_quota_con_punti_coincidenti_rifiutata(self):
        res, _, _ = self.ritocca([{"azione": "aggiungi", "p1": [1, 1], "p2": [1, 3], "base": [0, 0], "angolo": 0}])
        self.assertFalse(res["superato"])
        self.assertIn("coincidono", res["errori"][0])

    def test_stili_di_quota_dello_studio_non_cambiano(self):
        # stile come lo scrive AutoCAD: senza i valori predefiniti (separatore, zeri, posizione del testo)
        doc = ezdxf.new("R2018")
        doc.dimstyles.new("STUDIO", dxfattribs={"dimtxt": 0.1, "dimdec": 2})
        sorgente = self.dir / "stile.dxf"
        doc.saveas(sorgente)
        righe = sorgente.read_text().splitlines()
        coppie = [(righe[i], righe[i + 1]) for i in range(0, len(righe) - 1, 2)]
        tolti, dentro, out = 0, False, []
        for codice, valore in coppie:
            if codice.strip() == "0":
                dentro = False
            if codice.strip() == "2" and valore == "STUDIO" and out and out[-1][1] == "AcDbDimStyleTableRecord":
                dentro = True
            if dentro and codice.strip() in ("73", "74", "77", "78", "278"):
                tolti += 1
                continue
            out.append((codice, valore))
        self.assertEqual(tolti, 5)
        sorgente.write_text("\n".join(x for c in out for x in c) + "\n")
        self.assertFalse(ezdxf.readfile(sorgente).dimstyles.get("STUDIO").dxf.hasattr("dimdsep"))
        uscita = self.dir / "stile_out.dxf"
        webapi.ritocca(str(sorgente), str(uscita), "[]")
        ds = ezdxf.readfile(uscita).dimstyles.get("STUDIO")
        # come li intende AutoCAD (1.50 con il punto, zeri visibili, testo al centro e orizzontale), non ISO
        self.assertEqual((ds.dxf.dimdsep, ds.dxf.dimzin, ds.dxf.dimtad, ds.dxf.dimtih, ds.dxf.dimtoh), (ord("."), 0, 0, 1, 1))
        self.assertEqual((ds.dxf.dimtxt, ds.dxf.dimdec), (0.1, 2))

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
