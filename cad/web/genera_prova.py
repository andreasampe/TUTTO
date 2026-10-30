"""Genera web/prova_browser.html: il motore DOMUS che gira DENTRO il browser (Pyodide).

Serve a verificare, prima di costruire la sezione Planimetrie della webapp, che l'elaborazione
funzioni senza Python sul server (hosting Aruba) e senza il Mac acceso.
La pagina contiene i sorgenti di `domus_cad` e il profilo dello studio; dal web scarica solo:
- Pyodide 0.27.7 (MPL-2.0) da cdn.jsdelivr.net, con numpy, Pillow e le altre dipendenze;
- ezdxf 1.4.2 (MIT) da PyPI.

    python web/genera_prova.py
"""
import json
from pathlib import Path

QUI = Path(__file__).resolve().parent
CAD = QUI.parent

PAGINA = r"""<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DOMUS · prova del motore nel browser</title>
<style>
  :root { --fondo: #0d0e10; --carta: #16181b; --linea: #2a2d32; --testo: #e8e6e1; --tenue: #8d8f93; --ok: #7fc59b; --no: #e0796b; }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--fondo); color: var(--testo); font: 15px/1.5 -apple-system, "Inter", "Helvetica Neue", Arial, sans-serif; }
  main { max-width: 920px; margin: 0 auto; padding: 48px 20px 80px; }
  h1 { font-weight: 300; font-size: 34px; letter-spacing: .01em; margin: 0 0 6px; }
  .etichetta { text-transform: uppercase; letter-spacing: .18em; font-size: 11px; color: var(--tenue); }
  section { background: var(--carta); border: 1px solid var(--linea); border-radius: 14px; padding: 20px 22px; margin-top: 18px; }
  button, label.bottone { display: inline-block; background: none; color: var(--testo); border: 1px solid var(--testo); border-radius: 999px; padding: 9px 20px; font: inherit; cursor: pointer; }
  button:disabled { opacity: .35; cursor: default; }
  input[type=file] { display: none; }
  #stato { white-space: pre-line; color: var(--tenue); }
  table { width: 100%; border-collapse: collapse; font-size: 14px; }
  td { border-top: 1px solid var(--linea); padding: 7px 6px; vertical-align: top; }
  .ok { color: var(--ok); } .no { color: var(--no); }
  #anteprima svg { width: 100%; height: auto; background: #fff; border-radius: 8px; }
  a { color: var(--testo); }
</style>
</head>
<body>
<main>
  <div class="etichetta">DOMUS · Design · Organize · Model · Unify · Simplify</div>
  <h1>Prova del motore nel browser</h1>
  <p>Questa pagina elabora una pianta DXF interamente dentro il browser: niente Python sul Mac o sul server. Il file non lascia il computer.</p>

  <section>
    <div class="etichetta">1 · Motore</div>
    <p id="stato">Avvio…</p>
  </section>

  <section>
    <div class="etichetta">2 · Pianta</div>
    <p>Scegli un file DXF (da AutoCAD: <em>Salva con nome → DXF 2018</em>).</p>
    <label class="bottone" id="scegli">Scegli il DXF<input type="file" id="file" accept=".dxf"></label>
    <span id="nome" style="margin-left:12px;color:var(--tenue)"></span>
    <p><button id="elabora" disabled>Elabora</button></p>
  </section>

  <section id="risultato" hidden>
    <div class="etichetta">3 · Risultato</div>
    <p id="riepilogo"></p>
    <p><a id="scarica" download="elaborato.dxf">Scarica l'elaborato DXF</a></p>
    <table id="controlli"></table>
    <div id="anteprima" style="margin-top:16px"></div>
  </section>
</main>
<script>
const SORGENTI = __SORGENTI__;
const stato = document.getElementById("stato");
const scrivi = (t) => { stato.textContent += "\n" + t; };
let pyodide = null, file = null;

async function avvia() {
  const t0 = performance.now();
  stato.textContent = "Scarico Python (Pyodide 0.27.7)…";
  const s = document.createElement("script");
  s.src = "https://cdn.jsdelivr.net/pyodide/v0.27.7/full/pyodide.js";
  await new Promise((ok, no) => { s.onload = ok; s.onerror = () => no(new Error("Pyodide non raggiungibile")); document.head.appendChild(s); });
  pyodide = await loadPyodide();
  scrivi("Carico numpy, Pillow e le dipendenze…");
  await pyodide.loadPackage(["numpy", "pillow", "micropip", "pyparsing", "typing-extensions", "fonttools"]);
  scrivi("Installo ezdxf 1.4.2…");
  await pyodide.runPythonAsync("import micropip\nawait micropip.install('ezdxf==1.4.2')");
  for (const [percorso, testo] of Object.entries(SORGENTI)) {
    pyodide.FS.mkdirTree("/domus/" + percorso.split("/").slice(0, -1).join("/"));
    pyodide.FS.writeFile("/domus/" + percorso, testo);
  }
  scrivi(`Pronto in ${((performance.now() - t0) / 1000).toFixed(1)} secondi.`);
  aggiorna();
}

function aggiorna() { document.getElementById("elabora").disabled = !(pyodide && file); }

document.getElementById("file").addEventListener("change", (e) => {
  file = e.target.files[0] || null;
  document.getElementById("nome").textContent = file ? file.name : "";
  aggiorna();
});

document.getElementById("elabora").addEventListener("click", async () => {
  const bottone = document.getElementById("elabora");
  bottone.disabled = true;
  scrivi(`Elaboro ${file.name}…`);
  try {
    pyodide.FS.mkdirTree("/lavoro");
    pyodide.FS.writeFile("/lavoro/" + file.name, new Uint8Array(await file.arrayBuffer()));
    pyodide.globals.set("nome_file", file.name);
    const risultato = await pyodide.runPythonAsync(`
import sys, json, time, shutil, os
sys.path.insert(0, "/domus")
from domus_cad.elabora import elabora
shutil.rmtree("/lavoro/uscita", ignore_errors=True)
t = time.time()
esito = elabora("/lavoro/" + nome_file, "/lavoro/uscita", ora="browser")
esito["secondi"] = time.time() - t
json.dumps({k: esito[k] for k in ("controlli", "log", "superato", "secondi")}, ensure_ascii=False)
`);
    const e = JSON.parse(risultato);
    const dxf = pyodide.FS.readFile("/lavoro/uscita/elaborazioni/browser/elaborato.dxf");
    document.getElementById("scarica").href = URL.createObjectURL(new Blob([dxf], { type: "application/dxf" }));
    document.getElementById("scarica").download = file.name.replace(/\.dxf$/i, "") + " - quotato.dxf";
    document.getElementById("riepilogo").innerHTML =
      `Elaborazione in <b>${e.secondi.toFixed(1)} secondi</b> · esito: <b class="${e.superato ? "ok" : "no"}">${e.superato ? "superato" : "NON superato"}</b>`;
    const tabella = document.getElementById("controlli");
    tabella.innerHTML = "";
    for (const c of e.controlli) {
      const r = tabella.insertRow();
      r.insertCell().textContent = c.test;
      r.insertCell().textContent = c.nome;
      const x = r.insertCell(); x.textContent = c.esito; x.className = c.esito === "ok" ? "ok" : (c.esito === "fallito" ? "no" : "");
      r.insertCell().textContent = c.dettaglio;
    }
    for (const riga of e.log) { const r = tabella.insertRow(); const c = r.insertCell(); c.colSpan = 4; c.textContent = riga; }
    try {
      const svg = new TextDecoder().decode(pyodide.FS.readFile("/lavoro/uscita/elaborazioni/browser/anteprima.svg"));
      const ant = document.getElementById("anteprima");
      ant.innerHTML = "";
      ant.appendChild(new DOMParser().parseFromString(svg, "image/svg+xml").documentElement);
    } catch (_) { /* anteprima facoltativa */ }
    document.getElementById("risultato").hidden = false;
    scrivi(`Fatto in ${e.secondi.toFixed(1)} secondi.`);
  } catch (err) {
    scrivi("ERRORE: " + err.message);
  }
  bottone.disabled = false;
});

avvia().catch((err) => scrivi("ERRORE: " + err.message));
</script>
</body>
</html>
"""


def genera() -> Path:
    sorgenti = {f"domus_cad/{p.name}": p.read_text(encoding="utf-8") for p in sorted((CAD / "domus_cad").glob("*.py"))}
    sorgenti["profili/studio.json"] = (CAD / "profili" / "studio.json").read_text(encoding="utf-8")
    uscita = QUI / "prova_browser.html"
    dati = json.dumps(sorgenti, ensure_ascii=False).replace("</", "<\\/")  # niente "</script>" dentro lo script
    uscita.write_text(PAGINA.replace("__SORGENTI__", dati), encoding="utf-8")
    return uscita


if __name__ == "__main__":
    print(genera())
