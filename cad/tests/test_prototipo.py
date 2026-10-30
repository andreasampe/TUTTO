"""Test del prototipo (specifica, punto 15). Si lanciano con:  python -m unittest discover -s tests

Usano l'esempio ARTIFICIALE: non sostituiscono la prova su una planimetria reale.
"""
import shutil
import sys
import tempfile
import unittest
from pathlib import Path

import ezdxf

QUI = Path(__file__).resolve().parent
sys.path.insert(0, str(QUI.parent))
sys.path.insert(0, str(QUI.parent / "esempi"))

from domus_cad import quote  # noqa: E402
from domus_cad.elabora import elabora, impronta  # noqa: E402
from genera_esempio import genera  # noqa: E402


class Prototipo(unittest.TestCase):
    def setUp(self):
        self.dir = Path(tempfile.mkdtemp(prefix="domus-cad-"))
        self.dxf = genera(self.dir / "pianta.dxf")

    def tearDown(self):
        shutil.rmtree(self.dir, ignore_errors=True)

    def elabora(self, ora="prova-1", **kw):
        return elabora(str(self.dxf), str(self.dir / "progetto"), ora=ora, **kw)

    def test_flusso_completo_supera_i_controlli(self):
        esito = self.elabora()
        self.assertTrue(esito["superato"], esito["controlli"])
        falliti = [c for c in esito["controlli"] if c["esito"] == "fallito"]
        self.assertEqual(falliti, [])
        cartella = Path(esito["elaborato"]).parent
        for nome in ("elaborato.dxf", "anteprima.svg", "log.json", "rapporto.md"):
            self.assertTrue((cartella / nome).exists(), nome)

    def test_quote_totali_corrispondono_alle_dimensioni_reali(self):
        esito = self.elabora()
        totali = {q["lato"]: q["misura"] for q in esito["quote"] if q["livello"] == 2}
        self.assertAlmostEqual(totali["basso"], 12.0)
        self.assertAlmostEqual(totali["sinistra"], 9.0)
        self.assertAlmostEqual(totali["destra"], 5.0)
        # le catene parziali sommano esattamente al totale
        for lato in ("basso", "alto", "sinistra", "destra"):
            parziali = sum(q["misura"] for q in esito["quote"] if q["lato"] == lato and q["livello"] == 1)
            self.assertAlmostEqual(parziali, totali[lato], places=9)

    def test_testo_delle_quote_in_centimetri(self):
        esito = self.elabora()
        doc = ezdxf.readfile(esito["elaborato"])
        stile = doc.dimstyles.get(quote.STILE)
        self.assertEqual(stile.dxf.dimlfac, 100.0)

    def test_originale_mai_modificato(self):
        prima = impronta(self.dxf)
        self.elabora()
        self.assertEqual(impronta(self.dxf), prima)
        self.assertEqual(impronta(self.dir / "progetto" / "originali" / "pianta.dxf"), prima)

    def test_ripetibile(self):
        a = self.elabora(ora="prova-1")
        b = self.elabora(ora="prova-2")
        self.assertEqual(a["quote"], b["quote"])
        misure = []
        for e in (a, b):
            doc = ezdxf.readfile(e["elaborato"])
            misure.append(sorted(round(d.get_measurement(), 9) for d in doc.modelspace().query("DIMENSION")))
        self.assertEqual(misure[0], misure[1])

    def test_quote_non_si_sovrappongono_ai_testi(self):
        esito = self.elabora()
        doc = ezdxf.readfile(esito["elaborato"])
        ytesti = max(t.dxf.insert.y + t.dxf.height for t in doc.modelspace().query("TEXT[layer=='TESTI']"))
        alto = [d for d in doc.modelspace().query("DIMENSION") if d.dxf.defpoint.y > 9]
        self.assertTrue(alto)
        self.assertTrue(all(d.dxf.defpoint.y > ytesti for d in alto))

    def test_nota_fuori_dal_cartiglio(self):
        esito = self.elabora()
        doc = ezdxf.readfile(esito["elaborato"])
        nota = doc.entitydb.get(esito["handle"]["nota"])
        cart = doc.entitydb.get(esito["handle"]["cartiglio"])
        alto_cartiglio = cart.dxf.insert.y + 55 * cart.dxf.yscale
        self.assertGreater(nota.dxf.insert.y, alto_cartiglio)

    def test_unita_mancanti_vanno_confermate(self):
        doc = ezdxf.readfile(self.dxf)
        doc.header["$INSUNITS"] = 0
        senza = self.dir / "senza_unita.dxf"
        doc.saveas(senza)
        with self.assertRaisesRegex(ValueError, "unità"):
            elabora(str(senza), str(self.dir / "p2"), ora="x")
        esito = elabora(str(senza), str(self.dir / "p3"), ora="x", unita="m")
        self.assertTrue(esito["superato"])

    def test_unita_in_conflitto_rifiutate(self):
        with self.assertRaisesRegex(ValueError, "dichiara"):
            self.elabora(unita="cm")

    def test_dwg_chiede_il_dxf(self):
        finto = self.dir / "pianta.dwg"
        finto.write_bytes(b"AC1032")
        with self.assertRaisesRegex(ValueError, "DXF"):
            elabora(str(finto), str(self.dir / "p4"))


if __name__ == "__main__":
    unittest.main()
