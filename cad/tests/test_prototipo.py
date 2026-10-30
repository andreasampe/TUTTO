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

from domus_cad import profilo  # noqa: E402
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

    def test_quote_sul_layer_e_con_lo_stile_dello_studio(self):
        esito = self.elabora()
        doc = ezdxf.readfile(esito["elaborato"])
        dims = list(doc.modelspace().query("DIMENSION"))
        self.assertEqual(len(dims), len(esito["quote"]))
        self.assertTrue(all(d.dxf.layer == "01Cotas 1" and d.dxf.dimstyle == "50" for d in dims))
        self.assertEqual(doc.layers.get("01Cotas 1").dxf.color, 220)   # proprietà del layer dello studio
        stile = doc.dimstyles.get("50")
        self.assertEqual(stile.dxf.dimtxt, 0.1)
        self.assertEqual(stile.dxf.dimblk, "_DOT")
        self.assertEqual(stile.dxf.dimtxsty, "FUTURA")
        # su ogni quota solo la modifica usata dallo studio (DIMSCALE 1), come nel riferimento
        self.assertEqual(profilo._override(dims[0]), {"dimscale": 1.0})

    def test_testo_in_metri_con_punto_e_due_decimali(self):
        esito = self.elabora()
        doc = ezdxf.readfile(esito["elaborato"])
        testi = [t.dxf.text for d in doc.modelspace().query("DIMENSION")
                 for t in doc.blocks.get(d.dxf.geometry).query("MTEXT TEXT")]
        self.assertIn("12.00", [t.split(";")[-1].strip("}") for t in testi])

    def test_testo_delle_quote_in_centimetri(self):
        esito = self.elabora(formato="cm")
        doc = ezdxf.readfile(esito["elaborato"])
        d = next(iter(doc.modelspace().query("DIMENSION")))
        self.assertEqual(profilo._override(d)["dimlfac"], 100.0)

    def test_quote_interne_delle_stanze(self):
        esito = self.elabora()
        luci = [q for q in esito["quote"] if q["lato"] == "interno"]
        misure = sorted(round(q["misura"], 2) for q in luci)
        # luci nette dell'esempio: soggiorno e camera larghi 4,90; soggiorno 4,20 e camera 4,10 di profondità
        for attesa in (4.90, 4.20, 4.10):
            self.assertIn(attesa, misure)
        self.assertEqual(misure.count(4.90), 2)        # stessa larghezza, ma due locali diversi
        self.assertTrue(all(m >= 0.5 for m in misure))  # niente spessori di muro
        # a sinistra nessuna quota attraversa il tramezzo tra soggiorno e camera (a destra
        # invece cucina e bagno dell'esempio non sono divisi da un muro: 8,40 lì è giusta)
        self.assertFalse([q for q in luci if q["p1"][0] < 5.2 and round(q["misura"], 2) == 8.40])

    def test_distanze_degli_arredi(self):
        esito = self.elabora()  # il layer ARREDI viene riconosciuto dal nome
        misure = [round(q["misura"], 2) for q in esito["quote"] if q["lato"] == "interno"]
        self.assertIn(1.20, misure)   # tavolo - muro a sinistra
        self.assertIn(0.70, misure)   # tavolo - muro in basso

    def test_moduli_uguali_quotati_una_volta(self):
        esito = self.elabora()
        da_60 = [q for q in esito["quote"] if q["lato"] == "interno" and round(q["misura"], 2) == 0.60]
        direzioni = sorted(round(q["angolo"]) % 180 for q in da_60)
        self.assertEqual(direzioni, [0, 90])   # tre moduli 60 x 60: una larghezza e una profondità

    def test_testi_che_si_toccherebbero_vengono_spostati(self):
        from domus_cad import testi
        # due quote parallele vicinissime: i testi al centro si sovrapporrebbero
        quote = [{"alternative": [{"base": (0, 0), "p1": (0, 0), "p2": (2, 0), "angolo": 0}], "testo": "2.00"},
                 {"alternative": [{"base": (0, 0.05), "p1": (0, 0.05), "p2": (1.9, 0.05), "angolo": 0}], "testo": "1.90"}]
        scelte = testi.disponi(quote, altezza=0.1, orizzontale=True)
        self.assertEqual(scelte[0], (0, None))   # la più lunga resta al centro
        x, y = scelte[1][1]                       # l'altra si sposta, ma resta tra i suoi pallini
        self.assertGreater(abs(x - 0.95) + abs(y - 0.05), 0.1)
        self.assertTrue(0 < x < 1.9)

    def test_letto_con_cuscino_una_sola_quota(self):
        esito = self.elabora()
        letto = [round(q["misura"], 2) for q in esito["quote"] if q["lato"] == "interno"
                 and round(q["angolo"]) % 180 == 90 and 1.0 <= q["p1"][0] <= 2.4]
        self.assertIn(2.00, letto)       # letto intero, cuscino compreso
        self.assertNotIn(0.40, letto)    # niente quota del solo cuscino

    def test_profilo_riconosciuto_in_automatico(self):
        rif = ezdxf.new("R2018", setup=True)
        rif.header["$INSUNITS"] = 6
        rif.layers.add("A-ANNO-DIMS", color=3)
        rif.dimstyles.new("ARCH 100", dxfattribs={"dimtxt": 0.2, "dimasz": 0.1})
        msp = rif.modelspace()
        for i in range(5):
            msp.add_linear_dim(base=(0, -1), p1=(i, 0), p2=(i + 1, 0), dimstyle="ARCH 100",
                               dxfattribs={"layer": "A-ANNO-DIMS"}).render()
        msp.add_linear_dim(base=(0, -2), p1=(0, 0), p2=(5, 0), dimstyle="Standard").render()  # layer 0: minoranza
        for nome in ("A-WALL", "A-FURN", "A-DOOR"):
            msp.add_line((0, 0), (1, 0), dxfattribs={"layer": nome})
        p = profilo.rileva(rif)
        self.assertEqual(p["layer_quote"]["name"], "A-ANNO-DIMS")
        self.assertEqual(p["layer_quote"]["color"], 3)
        self.assertEqual(p["stile_quote"]["name"], "ARCH 100")
        self.assertEqual(p["scala"], 100)
        self.assertEqual((p["layer_muri"], p["layer_arredi"], p["layer_aperture"]), (["A-WALL"], ["A-FURN"], ["A-DOOR"]))

    def test_ruoli_dei_layer_dalla_geometria(self):
        doc = ezdxf.readfile(self.dxf)  # stessi disegni, ma con nomi di layer che non dicono niente
        doc.layers.add("L1"); doc.layers.add("L2")
        for e in doc.modelspace():
            e.dxf.layer = {"MURI": "L1", "TRAMEZZI": "L1", "ARREDI": "L2"}.get(e.dxf.layer, "L9")
        for x, y in ((6, 1), (7, 1), (6, 6), (8, 6)):
            doc.modelspace().add_lwpolyline([(x, y), (x + 0.8, y), (x + 0.8, y + 0.6), (x, y + 0.6)], close=True,
                                            dxfattribs={"layer": "L2"})
        ruoli, motivi = profilo.ruoli(doc)
        self.assertEqual(ruoli["layer_muri"], ["L1"], motivi)
        self.assertEqual(ruoli["layer_arredi"], ["L2"], motivi)

    def test_senza_quote_interne(self):
        esito = self.elabora(quote_interne=False)
        self.assertFalse([q for q in esito["quote"] if q["lato"] == "interno"])

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
