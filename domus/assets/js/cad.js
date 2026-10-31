// DOMUS — motore CAD nel browser (area "Planimetrie").
// Il motore è il pacchetto Python domus_cad (cartella cad/ del repository) e gira qui dentro con
// Pyodide: niente Python sul server, il disegno non lascia il computer finché non si salva.
// Pyodide (MPL-2.0), numpy, Pillow e le dipendenze arrivano dal CDN jsdelivr la prima volta,
// poi restano nella cache del browser. ezdxf (MIT) e domus_cad sono serviti dal sito.
'use strict';

(() => {
  const PYODIDE = 'https://cdn.jsdelivr.net/pyodide/v0.27.7/full/';
  const PACCHETTI = ['numpy', 'pillow', 'pyparsing', 'typing-extensions', 'fonttools'];
  let avvio = null;
  const ascoltatori = new Set();

  const caricaScript = (src) => new Promise((ok, no) => {
    if (window.loadPyodide) return ok();
    const s = document.createElement('script');
    s.src = src;
    s.onload = ok;
    s.onerror = () => no(new Error('Motore CAD non raggiungibile: serve la connessione a Internet la prima volta.'));
    document.head.append(s);
  });

  const avanza = (testo) => ascoltatori.forEach((f) => f(testo));

  D.cad = {
    stato: 'spento',

    // Avvisa dei passi dell'avvio (per mostrare l'avanzamento). Restituisce la funzione per smettere.
    ascolta(fn) { ascoltatori.add(fn); return () => ascoltatori.delete(fn); },

    // Avvia il motore una volta sola; le chiamate successive aspettano lo stesso avvio.
    avvia() {
      avvio ||= (async () => {
        const t0 = performance.now();
        D.cad.stato = 'avvio';
        avanza('Scarico Python…');
        await caricaScript(`${PYODIDE}pyodide.js`);
        const py = await window.loadPyodide({ indexURL: PYODIDE });
        avanza('Carico le librerie di calcolo…');
        await py.loadPackage(PACCHETTI);
        await py.loadPackage(new URL('assets/cad/ezdxf-1.4.2-py3-none-any.whl', location.href).href);
        avanza('Carico il motore DOMUS…');
        const motore = await (await fetch('assets/cad/motore.json', { credentials: 'same-origin' })).json();
        for (const [percorso, testo] of Object.entries(motore.file)) {
          py.FS.mkdirTree(`/domus/${percorso.split('/').slice(0, -1).join('/')}`);
          py.FS.writeFile(`/domus/${percorso}`, testo);
        }
        py.FS.mkdirTree('/lavoro');
        py.runPython('import sys\nsys.path.insert(0, "/domus")\nfrom domus_cad import webapi');
        D.cad.stato = 'pronto';
        avanza(`Motore pronto in ${((performance.now() - t0) / 1000).toFixed(1)} s`);
        return py;
      })().catch((err) => { avvio = null; D.cad.stato = 'errore'; avanza(err.message); throw err; });
      return avvio;
    },

    // Chiama una funzione di domus_cad/webapi.py con argomenti testuali; restituisce il JSON decodificato.
    async chiama(funzione, ...argomenti) {
      const py = await D.cad.avvia();
      await new Promise((r) => setTimeout(r, 20)); // lascia disegnare l'interfaccia prima del calcolo
      argomenti.forEach((a, i) => py.globals.set(`a${i}`, a));
      try {
        const testo = py.runPython(`webapi.${funzione}(${argomenti.map((_, i) => `a${i}`).join(', ')})`);
        return JSON.parse(testo);
      } catch (err) {
        // messaggio Python: si tiene l'ultima riga, la più utile
        throw new Error(String(err.message || err).trim().split('\n').pop());
      } finally {
        argomenti.forEach((_, i) => py.globals.delete(`a${i}`));
      }
    },

    async scrivi(nome, byte) {
      const py = await D.cad.avvia();
      py.FS.writeFile(`/lavoro/${nome}`, new Uint8Array(byte));
      return `/lavoro/${nome}`;
    },

    async leggiFile(percorso) {
      const py = await D.cad.avvia();
      return py.FS.readFile(percorso);
    },

    // ---------- funzioni dell'area Planimetrie ----------

    async modello(byte) {
      return D.cad.chiama('leggi', await D.cad.scrivi('disegno.dxf', byte));
    },

    // Applica i ritocchi: restituisce { byte, resoconto }.
    // profilo: testo JSON del profilo dello studio, per layer e stile delle quote aggiunte (vuoto = predefinito)
    async ritocca(byte, modifiche, profilo = '') {
      const origine = await D.cad.scrivi('origine.dxf', byte);
      const resoconto = await D.cad.chiama('ritocca', origine, '/lavoro/ritoccato.dxf', JSON.stringify(modifiche), profilo || '');
      return { byte: await D.cad.leggiFile('/lavoro/ritoccato.dxf'), resoconto };
    },

    // Quotatura automatica con il profilo dello studio (testo JSON, vuoto = profilo predefinito).
    // opzioni: argomenti di elabora(), es. { valori_cartiglio: { STUDIO, PROGETTO, ELABORATO } }
    async quota(byte, profilo, opzioni = {}) {
      const origine = await D.cad.scrivi('da_quotare.dxf', byte);
      const esito = await D.cad.chiama('quota', origine, `/lavoro/q${Date.now()}`, profilo || '', JSON.stringify(opzioni));
      return { byte: await D.cad.leggiFile(esito.percorso), esito };
    },

    async profilo(byte) {
      return D.cad.chiama('ricava_profilo', await D.cad.scrivi('riferimento.dxf', byte));
    },
  };
})();
