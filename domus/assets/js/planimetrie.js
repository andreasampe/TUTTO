// DOMUS — area "Planimetrie": tutte le planimetrie esecutive dello studio (DXF), con ritocchi nel browser.
// Si vede la pianta, si aggiungono, spostano o eliminano le quote, si spostano i loro testi, si correggono testi e
// cartiglio, si salva una nuova versione. Il lavoro sul disegno lo fa il motore DOMUS CAD (assets/js/cad.js),
// che non tocca il resto del file e controlla il risultato prima di salvarlo.
'use strict';

(() => {
  const { h } = D;
  const NS = 'http://www.w3.org/2000/svg';
  let KINDS = null;

  const tipi = async () => (KINDS ||= (await D.get('meta')).plan_kinds);

  // Elemento SVG (D.h crea solo elementi HTML).
  const s = (tag, attrs = {}, ...children) => {
    const n = document.createElementNS(NS, tag);
    for (const [k, v] of Object.entries(attrs)) if (v !== null && v !== undefined && v !== false) n.setAttribute(k, v);
    for (const c of children.flat()) if (c !== null && c !== undefined) n.append(c instanceof Node ? c : document.createTextNode(String(c)));
    return n;
  };

  const versione = (pl) => (pl.version ? `v${pl.version}` : '');

  // ---------- elenco ----------

  D.route('/planimetrie', async (page) => {
    const kinds = await tipi();
    let filtro = 'tutte';
    let elenco = await D.get('plans');
    const lista = h('div', { class: 'plans' });
    const chips = h('div', { class: 'tabs' });

    function disegna() {
      const presenti = Object.keys(kinds).filter((k) => elenco.some((p) => p.kind === k));
      chips.replaceChildren(...['tutte', ...presenti].map((k) => h('button', { type: 'button', class: filtro === k ? 'on' : '', onclick: () => { filtro = k; disegna(); } },
        k === 'tutte' ? `Tutte (${elenco.length})` : `${kinds[k]} (${elenco.filter((p) => p.kind === k).length})`)));
      chips.hidden = !elenco.length;
      const visibili = elenco.filter((p) => filtro === 'tutte' || p.kind === filtro);
      lista.replaceChildren(...(visibili.length ? visibili.map(riga) : [h('div', { class: 'card' }, h('div', { class: 'empty' },
        h('div', {}, 'Qui trovi tutte le planimetrie esecutive dello studio: piante quotate, stato di fatto e di progetto, demolizioni e costruzioni, impianti.'),
        h('div', { class: 'mt muted small' }, 'Carica un DXF esportato da AutoCAD (Salva con nome → DXF 2018): potrai quotarlo in automatico e ritoccare quote, testi e cartiglio.'),
        h('div', { class: 'mt' }, nuovaBtn())))]));
    }

    const riga = (p) => h('a', { class: 'plan-row card', href: `#/planimetrie/${p.id}` },
      h('div', { class: 'plan-ico' }, D.icon('layers', 22)),
      h('div', { class: 'item-main' },
        h('div', { class: 'item-title' }, p.title),
        h('div', { class: 'item-meta' }, `${p.code} · ${p.project_title}`)),
      h('span', { class: 'badge' }, kinds[p.kind] || p.kind),
      h('div', { class: 'plan-ver' }, h('b', {}, versione(p)), h('small', {}, p.version_at ? D.date(p.version_at) : '')),
      D.icon('chevron', 16));

    const nuovaBtn = () => h('button', { class: 'btn primary', onclick: () => nuovaPlanimetria() }, D.icon('plus', 15), 'Nuova planimetria');
    disegna();
    page.append(h('div', { class: 'toolbar' }, chips, h('div', { style: 'flex:1' }), elenco.length ? nuovaBtn() : null), lista);
    return { title: 'Planimetrie', nav: 'planimetrie' };
  });

  // ---------- nuova planimetria ----------

  async function nuovaPlanimetria(progetto = null) {
    const kinds = await tipi();
    const progetti = progetto ? [progetto] : await D.get('projects');
    if (!progetti.length) {
      D.toast('Crea prima un progetto: la planimetria appartiene a un progetto.', true);
      return;
    }
    const scelto = progetto || progetti[0];
    const file = h('input', { type: 'file', accept: '.dxf', name: 'dxf' });
    const esistenti = D.select('file_id', { '': 'Carica un nuovo DXF…' }, '');
    const campoFile = D.field('File DXF', file, { hint: 'Da AutoCAD: Salva con nome → DXF 2018' });
    const titolo = D.input('title', '', { required: true, placeholder: 'es. Pianta piano terra' });
    const progSel = D.select('project_id', Object.fromEntries(progetti.map((p) => [p.id, `${p.code} · ${p.title}`])), scelto.id, { disabled: !!progetto });

    async function dxfDelProgetto(id) {
      const p = await D.get(`projects/${id}`);
      const dxf = p.files.filter((f) => /\.dxf$/i.test(f.name));
      esistenti.replaceChildren(h('option', { value: '' }, 'Carica un nuovo DXF…'), ...dxf.map((f) => h('option', { value: f.id }, `Dai documenti: ${f.name}`)));
      esistenti.closest('.field').hidden = !dxf.length;
    }
    progSel.addEventListener('change', () => dxfDelProgetto(progSel.value));
    esistenti.addEventListener('change', () => { campoFile.hidden = !!esistenti.value; });
    file.addEventListener('change', () => { if (!titolo.value && file.files[0]) titolo.value = file.files[0].name.replace(/\.dxf$/i, ''); });

    const form = h('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      const out = await D.submit(form, async (d) => {
        const projectId = progetto ? progetto.id : Number(progSel.value);
        let fileId = Number(esistenti.value) || 0;
        if (!fileId) {
          const f = file.files[0];
          if (!f) throw Object.assign(new Error('Scegli il file DXF'), { fields: { dxf: 'Scegli il file' } });
          if (!/\.dxf$/i.test(f.name)) throw Object.assign(new Error('Serve un DXF: da AutoCAD Salva con nome → DXF 2018'), { fields: { dxf: 'Serve un file .dxf' } });
          const fd = new FormData();
          fd.append('files[]', f, f.name);
          fd.append('category', 'tavola');
          fileId = (await D.upload(`projects/${projectId}/files`, fd)).ids[0];
        }
        return D.post('plans', { project_id: projectId, title: d.title, kind: d.kind, file_id: fileId });
      }, 'Planimetria creata');
      if (out) { chiudi(); D.go(`/planimetrie/${out.id}`); }
    } },
    D.field('Progetto', progSel, { required: true }),
    D.field('Titolo', titolo, { required: true }),
    D.field('Tipo', D.select('kind', kinds, 'pianta_quotata')),
    D.field('Disegno', esistenti),
    campoFile,
    h('div', { class: 'form-actions' }, h('button', { class: 'btn primary', type: 'submit' }, 'Crea', D.icon('arrow', 15))));
    const chiudi = D.modal('Nuova planimetria', form);
    dxfDelProgetto(scelto.id);
  }
  D.nuovaPlanimetria = nuovaPlanimetria;

  // ---------- scheda "Planimetrie" nel progetto ----------

  D.planimetrieProgetto = function (p, id) {
    const box = h('div', {}, h('p', { class: 'muted small' }, 'Caricamento…'));
    (async () => {
      const kinds = await tipi();
      const elenco = await D.get(`plans&project=${id}`);
      const nuova = h('button', { class: 'btn primary', onclick: () => nuovaPlanimetria(p) }, D.icon('plus', 15), 'Nuova planimetria');
      box.replaceChildren(elenco.length
        ? h('div', {}, h('div', { class: 'toolbar' }, h('div', { style: 'flex:1' }), nuova),
          h('div', { class: 'plans' }, elenco.map((pl) => h('a', { class: 'plan-row card', href: `#/planimetrie/${pl.id}` },
            h('div', { class: 'plan-ico' }, D.icon('layers', 22)),
            h('div', { class: 'item-main' }, h('div', { class: 'item-title' }, pl.title), h('div', { class: 'item-meta' }, kinds[pl.kind] || pl.kind)),
            h('div', { class: 'plan-ver' }, h('b', {}, versione(pl)), h('small', {}, pl.version_at ? D.date(pl.version_at) : '')),
            D.icon('chevron', 16)))))
        : h('div', { class: 'card' }, h('div', { class: 'empty' }, h('div', {}, 'Nessuna planimetria: carica un DXF per quotarlo e ritoccarlo.'), h('div', { class: 'mt' }, nuova))));
    })().catch((err) => box.replaceChildren(h('p', { class: 'empty' }, err.message)));
    return box;
  };

  // ---------- editor ----------

  D.route('/planimetrie/(\\d+)', async (page, id) => {
    const kinds = await tipi();
    let pl = await D.get(`plans/${id}`);
    let versioneAperta = pl.versions[0];
    let byte = null;        // file DXF della versione aperta
    let modello = null;     // disegno letto dal motore
    let selezione = null;   // { tipo: 'quota' | 'testo', h }
    let aggiunta = null;    // quota nuova in costruzione: { p1, p2, c } (punti scelti e cursore)
    let orientamento = 'auto';
    let nuove = 0;
    let punti = [];         // vertici del disegno a cui si aggancia il cursore
    const modifiche = new Map(); // chiave "handle|tipo" → modifica per domus_cad/modifica.py
    const ordine = [];           // per "Annulla l'ultima"

    // --- struttura della pagina ---
    const svg = s('svg', { class: 'plan-svg', role: 'img', 'aria-label': 'Planimetria' });
    const strati = { geo: s('g', { class: 'g-geo' }), testi: s('g', { class: 'g-testi' }), quote: s('g', { class: 'g-quote' }) };
    strati.nuove = s('g', { class: 'g-nuove' });
    const radice = s('g', { transform: 'scale(1,-1)' }, strati.geo, strati.testi, strati.quote, strati.nuove);
    svg.append(radice);
    const avviso = h('div', { class: 'plan-overlay' }, h('div', { class: 'spinner' }), h('div', { class: 'plan-overlay-text' }, 'Avvio del motore CAD…'));
    const tela = h('div', { class: 'plan-canvas' }, svg, avviso,
      h('div', { class: 'plan-zoom' },
        h('button', { class: 'btn icon small', type: 'button', title: 'Ingrandisci', 'aria-label': 'Ingrandisci', onclick: () => zoom(0.7) }, D.icon('plus', 14)),
        h('button', { class: 'btn icon small', type: 'button', title: 'Riduci', 'aria-label': 'Riduci', onclick: () => zoom(1 / 0.7) }, h('span', { style: 'font-size:16px;line-height:1' }, '−')),
        h('button', { class: 'btn icon small', type: 'button', title: 'Tutto il disegno', 'aria-label': 'Tutto il disegno', onclick: () => adatta() }, D.icon('grid', 14))));
    const pannello = h('aside', { class: 'plan-panel' });
    page.append(h('div', { class: 'plan-editor' }, tela, pannello));

    const messaggio = (testo) => { avviso.hidden = !testo; avviso.querySelector('.plan-overlay-text').textContent = testo || ''; };
    const smetti = D.cad.ascolta((t) => { if (!modello) messaggio(t); });
    window.addEventListener('hashchange', smetti, { once: true });

    // --- vista: zoom e spostamento ---
    let vista = { x: 0, y: 0, w: 1, h: 1 };
    const applicaVista = () => svg.setAttribute('viewBox', `${vista.x} ${vista.y} ${vista.w} ${vista.h}`);
    function adatta() {
      if (!modello) return;
      const [x0, y0, x1, y1] = modello.estensione;
      vista = { x: x0, y: -y1, w: x1 - x0, h: y1 - y0 };
      applicaVista();
    }
    const puntoSvg = (ev) => {
      const p = svg.createSVGPoint();
      p.x = ev.clientX; p.y = ev.clientY;
      return p.matrixTransform(svg.getScreenCTM().inverse());
    };
    const puntoDisegno = (ev) => { const p = puntoSvg(ev); return [p.x, -p.y]; };
    function zoom(f, centro) {
      const c = centro || { x: vista.x + vista.w / 2, y: vista.y + vista.h / 2 };
      vista = { x: c.x - (c.x - vista.x) * f, y: c.y - (c.y - vista.y) * f, w: vista.w * f, h: vista.h * f };
      applicaVista();
    }
    svg.addEventListener('wheel', (ev) => { ev.preventDefault(); zoom(ev.deltaY > 0 ? 1.15 : 1 / 1.15, puntoSvg(ev)); }, { passive: false });

    // --- trascinamento: sfondo = sposta la vista; quota = sposta linea o testo ---
    let trascina = null;
    svg.addEventListener('pointerdown', (ev) => {
      if (!modello || ev.button === 2) return; // tasto destro: menu dei comandi
      chiudiMenu();
      const g = ev.target.closest('[data-h]');
      svg.setPointerCapture(ev.pointerId);
      if (aggiunta) { // si può sempre spostare la vista; un clic senza trascinare sceglie il punto
        trascina = { tipo: 'vista', da: [ev.clientX, ev.clientY], vista: { ...vista }, clic: true };
        return;
      }
      if (g && g.dataset.k === 'quota') {
        const q = quota(g.dataset.h);
        seleziona({ tipo: 'quota', h: q.h });
        const parte = ev.target.classList.contains('q-testo') ? 'testo' : 'linea';
        trascina = { tipo: q.tipo === 'altro' ? null : parte, h: q.h, da: puntoDisegno(ev), base: [...q.base || [0, 0]], pt: [...posTesto(q)], mosso: false };
      } else if (g && g.dataset.k === 'testo') {
        seleziona({ tipo: 'testo', h: g.dataset.h });
        trascina = null;
      } else {
        seleziona(null);
        trascina = { tipo: 'vista', da: [ev.clientX, ev.clientY], vista: { ...vista } };
      }
    });
    svg.addEventListener('pointermove', (ev) => {
      if (aggiunta && !(trascina && Math.hypot(ev.clientX - trascina.da[0], ev.clientY - trascina.da[1]) > 4)) {
        aggiunta.c = aggiunta.p2 ? puntoDisegno(ev) : aggancia(puntoDisegno(ev));
        disegnaNuove();
        return;
      }
      if (!trascina) return;
      if (trascina.tipo === 'vista') {
        const k = vista.w / svg.clientWidth;
        vista = { ...trascina.vista, x: trascina.vista.x - (ev.clientX - trascina.da[0]) * k, y: trascina.vista.y - (ev.clientY - trascina.da[1]) * k };
        applicaVista();
        return;
      }
      if (!trascina.tipo) return;
      const p = puntoDisegno(ev);
      const d = [p[0] - trascina.da[0], p[1] - trascina.da[1]];
      const q = quota(trascina.h);
      if (trascina.tipo === 'linea') {
        const n = normale(q);
        const t = d[0] * n[0] + d[1] * n[1]; // la linea si sposta solo di traverso: la misura non cambia
        anteprima.set(q.h, { base: [trascina.base[0] + n[0] * t, trascina.base[1] + n[1] * t], pt: q.ptLibero ? [trascina.pt[0] + n[0] * t, trascina.pt[1] + n[1] * t] : null });
      } else {
        anteprima.set(q.h, { pt: [trascina.pt[0] + d[0], trascina.pt[1] + d[1]] });
      }
      trascina.mosso = Math.hypot(d[0], d[1]) > vista.w / 400;
      disegnaQuota(q);
    });
    svg.addEventListener('pointerup', (ev) => {
      const t = trascina;
      trascina = null;
      if (aggiunta && t?.clic && Math.hypot(ev.clientX - t.da[0], ev.clientY - t.da[1]) <= 4) { clicAggiunta(ev); return; }
      if (!t || t.tipo === 'vista' || !t.tipo) return;
      const a = anteprima.get(t.h);
      anteprima.delete(t.h);
      const nuova = modifiche.get(`${t.h}|aggiungi`);
      if (t.mosso && a && nuova) { // quota non ancora salvata: si cambia la quota stessa
        const q = quota(t.h);
        if (t.tipo === 'linea') { nuova.base = a.base; q.base = a.base; if (a.pt) { nuova.pos = a.pt; q.pt = a.pt; } }
        else { nuova.pos = a.pt; q.pt = a.pt; q.ptLibero = true; }
      } else if (t.mosso && a) {
        if (t.tipo === 'linea') registra(`${t.h}|linea`, { h: t.h, azione: 'sposta_linea', base: a.base }, 'Linea di quota spostata');
        else registra(`${t.h}|testo`, { h: t.h, azione: 'sposta_testo', pos: a.pt }, 'Testo di quota spostato');
      }
      ridisegnaQuote();
      disegnaPannello();
    });

    // --- quote nuove: due punti agganciati al disegno, poi la posizione della linea ---
    const fattoreMetri = () => ({ m: 1, cm: 0.01, mm: 0.001 })[modello?.unita] ?? 1;
    function aggancia(p) {
      const raggio = 12 * vista.w / svg.clientWidth;
      let migliore = null, d0 = raggio;
      for (const q of punti) {
        const d = Math.abs(q[0] - p[0]) + Math.abs(q[1] - p[1]);
        if (d < d0) { d0 = d; migliore = q; }
      }
      return migliore ? { p: migliore, agganciato: true } : { p, agganciato: false };
    }
    // Direzione della quota nuova: come in AutoCAD, orizzontale se il cursore è sopra o sotto i due punti,
    // verticale se è di lato; oppure quella scelta.
    function angoloNuova(p1, p2, c) {
      if (orientamento === 'orizzontale') return 0;
      if (orientamento === 'verticale') return 90;
      const allineata = Math.atan2(p2[1] - p1[1], p2[0] - p1[0]) * 180 / Math.PI;
      if (orientamento === 'allineata') return allineata;
      // automatica: due punti in obliquo (es. su un muro inclinato) e linea messa accanto al tratto
      // → quota allineata; cursore sopra, sotto o di lato → orizzontale o verticale, come in AutoCAD
      const obliqua = Math.min(((allineata % 90) + 90) % 90, 90 - ((allineata % 90) + 90) % 90) > 2;
      if (obliqua) {
        const L2 = (p2[0] - p1[0]) ** 2 + (p2[1] - p1[1]) ** 2;
        const t = ((c[0] - p1[0]) * (p2[0] - p1[0]) + (c[1] - p1[1]) * (p2[1] - p1[1])) / L2;
        if (t > 0.05 && t < 0.95) return allineata;
      }
      const fuoriX = Math.max(Math.min(p1[0], p2[0]) - c[0], c[0] - Math.max(p1[0], p2[0]), 0);
      const fuoriY = Math.max(Math.min(p1[1], p2[1]) - c[1], c[1] - Math.max(p1[1], p2[1]), 0);
      if (Math.abs(p2[0] - p1[0]) < 1e-9) return 90;
      if (Math.abs(p2[1] - p1[1]) < 1e-9) return 0;
      return fuoriX > fuoriY ? 90 : 0;
    }
    function quotaNuova(p1, p2, base, angolo) {
      const a = angolo * Math.PI / 180, u = [Math.cos(a), Math.sin(a)];
      const t = (p) => (p[0] - base[0]) * u[0] + (p[1] - base[1]) * u[1];
      const e1 = [base[0] + u[0] * t(p1), base[1] + u[1] * t(p1)], e2 = [base[0] + u[0] * t(p2), base[1] + u[1] * t(p2)];
      return { e1, e2, misura: Math.abs(t(p2) - t(p1)) };
    }
    function disegnaQuotaNuova(p1, p2, base, angolo, cls) {
      const { e1, e2, misura } = quotaNuova(p1, p2, base, angolo);
      const ht = altezzaTesto, mezzo = [(e1[0] + e2[0]) / 2, (e1[1] + e2[1]) / 2];
      const rot = ((angolo % 180) + 180) % 180 > 90 ? angolo + 180 : angolo;
      return s('g', { class: `q ${cls}` },
        s('line', { class: 'q-aiuto', x1: p1[0], y1: p1[1], x2: e1[0], y2: e1[1] }),
        s('line', { class: 'q-aiuto', x1: p2[0], y1: p2[1], x2: e2[0], y2: e2[1] }),
        s('line', { class: 'q-linea', x1: e1[0], y1: e1[1], x2: e2[0], y2: e2[1] }),
        s('circle', { class: 'q-punto', cx: e1[0], cy: e1[1], r: ht * 0.35 }),
        s('circle', { class: 'q-punto', cx: e2[0], cy: e2[1], r: ht * 0.35 }),
        testoSvg(mezzo[0], mezzo[1], (misura * fattoreMetri()).toFixed(2), ht, rot, 'q-testo', { 'text-anchor': 'middle', 'dominant-baseline': 'middle', 'stroke-width': ht * 0.45 }));
    }
    function disegnaNuove() {
      const figli = [];
      if (aggiunta?.c) {
        const r = 6 * vista.w / svg.clientWidth;
        for (const p of [aggiunta.p1, aggiunta.p2].filter(Boolean)) figli.push(s('circle', { class: 'q-scelto', cx: p[0], cy: p[1], r }));
        if (!aggiunta.p2) {
          const c = aggiunta.c;
          figli.push(s('circle', { class: c.agganciato ? 'q-aggancio' : 'q-cursore', cx: c.p[0], cy: c.p[1], r }));
          if (aggiunta.p1) figli.push(s('line', { class: 'q-aiuto', x1: aggiunta.p1[0], y1: aggiunta.p1[1], x2: c.p[0], y2: c.p[1] }));
        } else {
          figli.push(disegnaQuotaNuova(aggiunta.p1, aggiunta.p2, aggiunta.c, angoloNuova(aggiunta.p1, aggiunta.p2, aggiunta.c), 'sel'));
        }
      }
      strati.nuove.replaceChildren(...figli);
    }
    function clicAggiunta(ev) {
      if (!aggiunta.p1) { aggiunta.p1 = aggancia(puntoDisegno(ev)).p; disegnaNuove(); disegnaPannello(); return; }
      if (!aggiunta.p2) {
        const p2 = aggancia(puntoDisegno(ev)).p;
        if (Math.hypot(p2[0] - aggiunta.p1[0], p2[1] - aggiunta.p1[1]) < 1e-9) return;
        aggiunta.p2 = p2; aggiunta.c = puntoDisegno(ev); disegnaNuove(); disegnaPannello(); return;
      }
      const base = puntoDisegno(ev);
      const angolo = angoloNuova(aggiunta.p1, aggiunta.p2, base);
      const { misura } = quotaNuova(aggiunta.p1, aggiunta.p2, base, angolo);
      if (misura < 1e-9) { D.toast('I due punti coincidono in questa direzione: sposta il cursore o cambia orientamento', true); return; }
      nuove += 1;
      const hq = `nuova${nuove}`, testo = (misura * fattoreMetri()).toFixed(2);
      registra(`${hq}|aggiungi`, { azione: 'aggiungi', p1: aggiunta.p1, p2: aggiunta.p2, base, angolo }, `Quota aggiunta (${testo})`);
      // da subito è una quota come le altre: si seleziona, si trascina, si elimina
      modello.quote.push({ h: hq, nuova: true, l: 'nuova', stile: 'dello studio', tipo: 'lineare', testo, ht: altezzaTesto,
        p1: aggiunta.p1, p2: aggiunta.p2, base, ang: angolo, pt: null, ptLibero: false });
      aggiunta = { c: null }; // pronta per un'altra quota
      disegnaQuota(quota(hq)); disegnaNuove(); disegnaPannello();
    }
    function iniziaAggiunta() { seleziona(null); aggiunta = { c: null }; svg.classList.add('aggiunta'); disegnaPannello(); }
    function fineAggiunta() { aggiunta = null; svg.classList.remove('aggiunta'); disegnaNuove(); disegnaPannello(); }
    const conEsc = (ev) => { if (ev.key === 'Escape' && aggiunta) fineAggiunta(); };
    document.addEventListener('keydown', conEsc);
    window.addEventListener('hashchange', () => document.removeEventListener('keydown', conEsc), { once: true });

    // --- geometria delle quote ---
    const anteprima = new Map(); // posizioni durante il trascinamento
    const quota = (hq) => modello.quote.find((q) => q.h === hq);
    const direzione = (q) => {
      if (q.tipo === 'allineata') { const L = Math.hypot(q.p2[0] - q.p1[0], q.p2[1] - q.p1[1]) || 1; return [(q.p2[0] - q.p1[0]) / L, (q.p2[1] - q.p1[1]) / L]; }
      const a = (q.ang || 0) * Math.PI / 180;
      return [Math.cos(a), Math.sin(a)];
    };
    const normale = (q) => { const u = direzione(q); return [-u[1], u[0]]; };
    // Stato attuale di una quota: dati del disegno + modifiche in sospeso + trascinamento in corso.
    function attuale(q) {
      const linea = modifiche.get(`${q.h}|linea`);
      const testo = modifiche.get(`${q.h}|testo`);
      const centro = modifiche.get(`${q.h}|centro`);
      const ant = anteprima.get(q.h) || {};
      const base = ant.base || linea?.base || q.base;
      let pt = ant.pt || testo?.pos || null;
      if (!pt && linea && q.ptLibero) { const d = [linea.base[0] - q.base[0], linea.base[1] - q.base[1]]; pt = [q.pt[0] + d[0], q.pt[1] + d[1]]; }
      if (!pt && !centro && q.ptLibero) pt = q.pt;
      return { base, pt, eliminata: modifiche.has(`${q.h}|elimina`) };
    }
    function estremi(q, base) {
      const u = direzione(q);
      if (q.tipo === 'allineata') {
        const n = normale(q);
        const off = (base[0] - q.p1[0]) * n[0] + (base[1] - q.p1[1]) * n[1];
        return [[q.p1[0] + n[0] * off, q.p1[1] + n[1] * off], [q.p2[0] + n[0] * off, q.p2[1] + n[1] * off]];
      }
      const t = (p) => (p[0] - base[0]) * u[0] + (p[1] - base[1]) * u[1];
      return [[base[0] + u[0] * t(q.p1), base[1] + u[1] * t(q.p1)], [base[0] + u[0] * t(q.p2), base[1] + u[1] * t(q.p2)]];
    }
    function posTesto(q) {
      const a = attuale(q);
      if (a.pt) return a.pt;
      if (!q.base) return q.pt || [0, 0];
      const [e1, e2] = estremi(q, a.base);
      return [(e1[0] + e2[0]) / 2, (e1[1] + e2[1]) / 2];
    }

    const testoSvg = (x, y, testo, alt, rot, cls, extra = {}) => s('text', {
      class: cls, transform: `translate(${x} ${y}) scale(1 -1) rotate(${-(rot || 0)})`, 'font-size': alt, ...extra }, testo);

    function disegnaQuota(q) {
      const vecchio = strati.quote.querySelector(`[data-h="${q.h}"]`);
      const a = attuale(q);
      const cls = ['q', selezione?.h === q.h ? 'sel' : '', [...modifiche.keys()].some((k) => k.startsWith(`${q.h}|`)) ? 'mod' : ''].filter(Boolean).join(' ');
      const g = s('g', { class: cls, 'data-h': q.h, 'data-k': 'quota' });
      if (!a.eliminata) {
        const ht = q.ht || altezzaTesto;
        if (q.base && q.tipo !== 'altro') {
          const [e1, e2] = estremi(q, a.base);
          g.append(s('line', { class: 'q-presa', x1: e1[0], y1: e1[1], x2: e2[0], y2: e2[1] }),
            s('line', { class: 'q-linea', x1: e1[0], y1: e1[1], x2: e2[0], y2: e2[1] }),
            s('circle', { class: 'q-punto', cx: e1[0], cy: e1[1], r: ht * 0.35 }),
            s('circle', { class: 'q-punto', cx: e2[0], cy: e2[1], r: ht * 0.35 }));
        }
        const [tx, ty] = posTesto(q);
        const rot = q.tipo === 'allineata' ? Math.atan2(direzione(q)[1], direzione(q)[0]) * 180 / Math.PI : 0;
        g.append(testoSvg(tx, ty, q.testo || '?', ht, rot > 90 || rot < -90 ? rot + 180 : rot, 'q-testo', { 'text-anchor': 'middle', 'dominant-baseline': 'middle', 'stroke-width': ht * 0.45 }));
      }
      if (vecchio) vecchio.replaceWith(g); else strati.quote.append(g);
    }
    const ridisegnaQuote = () => modello && modello.quote.forEach(disegnaQuota);
    let altezzaTesto = 0.1;

    function disegnaTutto() {
      const [x0, y0, x1, y1] = modello.estensione;
      altezzaTesto = (() => { const v = modello.quote.map((q) => q.ht).filter(Boolean).sort((a, b) => a - b); return v.length ? v[v.length >> 1] : Math.max(x1 - x0, y1 - y0) / 150; })();
      // geometria: un percorso per layer (veloce anche con molti elementi), campiture piene a parte
      const perLayer = new Map();
      for (const el of modello.geometria) {
        const k = `${el.l}|${el.f ? 1 : 0}`;
        const d = `M${el.p.map((p) => `${p[0]} ${p[1]}`).join('L')}${el.f ? 'Z' : ''}`;
        perLayer.set(k, (perLayer.get(k) || '') + d);
      }
      strati.geo.replaceChildren(...[...perLayer].map(([k, d]) => s('path', { d, class: k.endsWith('|1') ? 'geo-pieno' : 'geo', 'data-layer': k.split('|')[0] })));
      strati.testi.replaceChildren(...modello.testi.map((t) => {
        const valore = modifiche.get(`${t.h}|valore`)?.valore ?? t.t;
        const el = testoSvg(t.p[0], t.p[1], valore.split('\n')[0], t.s, t.a, `t${t.h ? ' t-mod' : ''}${selezione?.h === t.h && t.h ? ' sel' : ''}`);
        if (t.h) { el.dataset.h = t.h; el.dataset.k = 'testo'; }
        return el;
      }));
      strati.quote.replaceChildren();
      ridisegnaQuote();
      disegnaNuove();
    }

    // --- modifiche in sospeso ---
    function registra(chiave, modifica, etichetta) {
      modifiche.set(chiave, { ...modifica, etichetta });
      const i = ordine.indexOf(chiave);
      if (i >= 0) ordine.splice(i, 1);
      ordine.push(chiave);
      if (chiave.endsWith('|centro')) { modifiche.delete(chiave.replace('|centro', '|testo')); }
      if (chiave.endsWith('|testo')) { modifiche.delete(chiave.replace('|testo', '|centro')); }
    }
    function annulla(chiave) {
      modifiche.delete(chiave);
      const i = ordine.indexOf(chiave);
      if (i >= 0) ordine.splice(i, 1);
      if (chiave.endsWith('|aggiungi') && modello) { // quota nuova: sparisce del tutto
        const hq = chiave.split('|')[0];
        modello.quote = modello.quote.filter((q) => q.h !== hq);
        strati.quote.querySelector(`[data-h="${hq}"]`)?.remove();
        if (selezione?.h === hq) selezione = null;
      }
    }
    function annullaTutte() { [...ordine].forEach(annulla); modifiche.clear(); ordine.length = 0; }
    function annullaQuota(hq) { [...ordine].filter((k) => k.startsWith(`${hq}|`)).forEach(annulla); }
    const elencoModifiche = () => {
      const lista = [];
      for (const k of ordine) {
        const m = modifiche.get(k);
        if (!m) continue;
        const h0 = k.split('|')[0];
        if (!k.endsWith('|elimina') && modifiche.has(`${h0}|elimina`)) continue; // eliminata: il resto non serve
        const { etichetta, ...dati } = m;
        if (k.endsWith('|valore')) lista.push({ h: dati.h, azione: 'testo', valore: dati.valore });
        else lista.push(dati);
      }
      return lista;
    };

    function seleziona(sel) {
      selezione = sel;
      strati.quote.querySelectorAll('.q.sel').forEach((g) => g.classList.remove('sel'));
      strati.testi.querySelectorAll('.sel').forEach((t) => t.classList.remove('sel'));
      if (sel) (sel.tipo === 'quota' ? strati.quote : strati.testi).querySelector(`[data-h="${sel.h}"]`)?.classList.add('sel');
      disegnaPannello();
    }

    // --- comandi su quote e testi (pannello e menu col tasto destro) ---
    const dopo = () => { ridisegnaQuote(); disegnaPannello(); };
    function azioniQuota(q) {
      const a = attuale(q);
      const voci = [];
      if (q.tipo !== 'altro' && !a.eliminata && (a.pt || q.ptLibero)) {
        voci.push(['Testo al centro', () => {
          if (q.nuova) { delete modifiche.get(`${q.h}|aggiungi`).pos; q.pt = null; q.ptLibero = false; }
          else registra(`${q.h}|centro`, { h: q.h, azione: 'testo_al_centro' }, 'Testo rimesso al centro');
          dopo();
        }]);
      }
      if (!q.nuova && !a.eliminata && ordine.some((k) => k.startsWith(`${q.h}|`))) voci.push(['Annulla le modifiche di questa quota', () => { annullaQuota(q.h); dopo(); }]);
      if (a.eliminata) voci.push(['Ripristina quota', () => { annulla(`${q.h}|elimina`); dopo(); }]);
      else if (q.nuova) voci.push(['Elimina quota', () => { annulla(`${q.h}|aggiungi`); dopo(); }, true]);
      else voci.push(['Elimina quota', () => { registra(`${q.h}|elimina`, { h: q.h, azione: 'elimina' }, 'Quota eliminata'); dopo(); }, true]);
      return voci;
    }

    const menu = h('div', { class: 'plan-menu', role: 'menu', hidden: true });
    tela.append(menu);
    function chiudiMenu() { menu.hidden = true; }
    function apriMenu(ev, titolo, voci) {
      const r = tela.getBoundingClientRect();
      menu.replaceChildren(h('div', { class: 'plan-menu-title' }, titolo), ...voci.map(([etichetta, fn, pericolo]) =>
        h('button', { type: 'button', role: 'menuitem', class: pericolo ? 'danger' : '', onclick: () => { chiudiMenu(); fn(); } }, etichetta)));
      menu.hidden = false;
      const x = Math.min(ev.clientX - r.left, r.width - menu.offsetWidth - 8);
      const y = Math.min(ev.clientY - r.top, r.height - menu.offsetHeight - 8);
      menu.style.left = `${Math.max(8, x)}px`;
      menu.style.top = `${Math.max(8, y)}px`;
      menu.querySelector('button')?.focus();
    }
    svg.addEventListener('contextmenu', (ev) => {
      ev.preventDefault();
      if (!modello) return;
      if (aggiunta) fineAggiunta();
      const g = ev.target.closest('[data-h]');
      if (g && g.dataset.k === 'quota' && quota(g.dataset.h)) {
        const q = quota(g.dataset.h);
        seleziona({ tipo: 'quota', h: q.h });
        apriMenu(ev, `Quota ${q.testo || ''}`, azioniQuota(q));
      } else if (g && g.dataset.k === 'testo') {
        seleziona({ tipo: 'testo', h: g.dataset.h });
        apriMenu(ev, 'Testo', [['Modifica testo', () => pannello.querySelector('textarea')?.focus()]]);
      } else {
        const punto = puntoDisegno(ev);
        const voci = [
          ['Aggiungi quota da qui', () => { iniziaAggiunta(); aggiunta.p1 = aggancia(punto).p; disegnaNuove(); disegnaPannello(); }],
          ['Aggiungi quota', iniziaAggiunta],
          ['Tutto il disegno', adatta],
        ];
        const ultima = [...ordine].reverse().find((k) => modifiche.has(k));
        if (ultima) voci.push([`Annulla: ${modifiche.get(ultima).etichetta.toLowerCase()}`, () => { annulla(ultima); disegnaTutto(); disegnaPannello(); }]);
        apriMenu(ev, 'Pianta', voci);
      }
    });
    document.addEventListener('pointerdown', (ev) => { if (!menu.hidden && !menu.contains(ev.target)) chiudiMenu(); });
    document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') chiudiMenu(); });

    // --- pannello laterale ---
    const statoMotore = h('div', { class: 'item-meta plan-engine' });
    D.cad.ascolta((t) => { statoMotore.textContent = t; });

    function disegnaPannello() {
      const parti = [];
      parti.push(h('div', { class: 'plan-head' },
        h('div', { class: 'eyebrow' }, `${pl.code} · ${kinds[pl.kind] || pl.kind}`),
        h('h2', {}, pl.title),
        h('div', { class: 'item-meta' }, h('a', { href: `#/progetti/${pl.project_id}` }, pl.project_title), ` · versione ${versioneAperta?.number ?? '?'}`),
        statoMotore));

      // selezione
      if (selezione?.tipo === 'quota' && modello && quota(selezione.h)) {
        const q = quota(selezione.h);
        parti.push(h('section', { class: 'plan-box' },
          h('div', { class: 'eyebrow' }, q.nuova ? 'Quota nuova · non ancora salvata' : 'Quota selezionata'),
          h('div', { class: 'plan-misura' }, q.testo || '—'),
          h('div', { class: 'item-meta' }, q.nuova ? 'layer e stile dello studio' : `${q.l} · stile ${q.stile}${q.tipo === 'altro' ? ' · si può solo eliminare' : ''}`),
          q.tipo !== 'altro' ? h('p', { class: 'small muted' }, 'Trascina la linea per spostarla (la misura non cambia) oppure il numero per spostare solo il testo. Tasto destro per gli altri comandi.') : null,
          h('div', { class: 'plan-actions' }, azioniQuota(q).map(([etichetta, fn, pericolo]) =>
            h('button', { class: `btn small${pericolo ? ' danger' : ''}`, onclick: fn }, pericolo ? D.icon('trash', 13) : null, etichetta)))));
      } else if (selezione?.tipo === 'testo' && modello) {
        const t = modello.testi.find((x) => x.h === selezione.h);
        const area = D.textarea('valore', modifiche.get(`${t.h}|valore`)?.valore ?? t.t, { rows: 3 });
        parti.push(h('section', { class: 'plan-box' },
          h('div', { class: 'eyebrow' }, 'Testo selezionato'),
          h('div', { class: 'item-meta' }, `layer ${t.l}`),
          area,
          h('div', { class: 'plan-actions' }, h('button', { class: 'btn small primary', onclick: () => {
            registra(`${t.h}|valore`, { h: t.h, valore: area.value }, 'Testo modificato');
            disegnaTutto(); disegnaPannello();
          } }, 'Applica'))));
      } else if (aggiunta) {
        const passo = !aggiunta.p1 ? 'Clicca il primo punto: il cursore si aggancia agli spigoli del disegno. Con «Automatica» la quota è orizzontale o verticale, oppure allineata se metti la linea accanto a un tratto inclinato.'
          : !aggiunta.p2 ? 'Clicca il secondo punto.'
            : 'Muovi il mouse per scegliere dove mettere la linea e clicca.';
        parti.push(h('section', { class: 'plan-box' },
          h('div', { class: 'eyebrow' }, 'Nuova quota'),
          h('p', { class: 'small', style: 'margin:0' }, passo),
          h('div', { class: 'tabs' }, [['auto', 'Automatica'], ['orizzontale', 'Orizzontale'], ['verticale', 'Verticale'], ['allineata', 'Allineata']].map(([k, l]) =>
            h('button', { type: 'button', class: orientamento === k ? 'on' : '', onclick: () => { orientamento = k; disegnaNuove(); disegnaPannello(); } }, l))),
          h('div', { class: 'plan-actions' }, h('button', { class: 'btn small', onclick: fineAggiunta }, 'Fine'),
            h('span', { class: 'muted small' }, 'Esc per finire · trascina per muovere la vista'))));
      } else {
        parti.push(h('section', { class: 'plan-box muted small' }, modello
          ? 'Clicca una quota per spostarla, spostarne il testo o eliminarla; clicca un testo per correggerlo. Trascina lo sfondo per muoverti, rotella per lo zoom.'
          : 'Il disegno si sta caricando.',
        modello ? h('div', { class: 'plan-actions' }, h('button', { class: 'btn small primary', onclick: iniziaAggiunta }, D.icon('plus', 14), 'Aggiungi quota')) : null));
      }

      // modifiche e salvataggio
      const n = elencoModifiche().length;
      const nota = D.input('nota', '', { placeholder: 'Nota della versione (facoltativa)' });
      parti.push(h('section', { class: 'plan-box' },
        h('div', { class: 'eyebrow' }, n ? `${n} modific${n === 1 ? 'a' : 'he'} da salvare` : 'Nessuna modifica'),
        n ? h('ul', { class: 'plan-mods' }, ordine.filter((k) => modifiche.has(k)).map((k) => h('li', {}, modifiche.get(k).etichetta,
          h('button', { class: 'btn ghost icon small', title: 'Annulla', 'aria-label': 'Annulla questa modifica', onclick: () => { annulla(k); disegnaTutto(); disegnaPannello(); } }, D.icon('x', 12))))) : null,
        n ? nota : null,
        h('div', { class: 'plan-actions' },
          h('button', { class: 'btn primary small', disabled: !n || !modello, onclick: (e) => salva(e.currentTarget, nota.value) }, D.icon('check', 14), 'Salva nuova versione'),
          n ? h('button', { class: 'btn ghost small', onclick: () => { annullaTutte(); disegnaTutto(); disegnaPannello(); } }, 'Annulla tutte') : null)));

      // azioni sulla planimetria
      parti.push(h('section', { class: 'plan-box' },
        h('div', { class: 'eyebrow' }, 'Planimetria'),
        h('div', { class: 'plan-actions' },
          h('button', { class: 'btn small', disabled: !modello, title: 'Nuova versione con le quote calcolate da DOMUS, sul layer e con lo stile dello studio', onclick: (e) => quotaAutomatica(e.currentTarget) }, D.icon('sparkle', 14), 'Quota in automatico'),
          versioneAperta ? h('a', { class: 'btn small', href: `api.php?r=files/${versioneAperta.file_id}`, download: '' }, D.icon('download', 14), 'Scarica DXF') : null,
          h('button', { class: 'btn small', onclick: () => modificaPlanimetria() }, D.icon('edit', 14), 'Modifica'),
          h('button', { class: 'btn ghost small danger', onclick: async (e) => {
            if (!D.confirm(`Togliere la planimetria "${pl.title}"? I file delle versioni restano nei Documenti del progetto.`)) return;
            if (await D.run(e.currentTarget, () => D.del(`plans/${id}`), 'Planimetria tolta') !== undefined) D.go('/planimetrie');
          } }, 'Togli'))));

      // cartigli
      for (const c of modello?.cartigli || []) {
        const valori = modifiche.get(`${c.h}|attributi`)?.valori || {};
        const campi = c.attr.map((a) => D.field(a.tag, D.input(a.tag, valori[a.tag] ?? a.valore)));
        const form = h('form', { class: 'form plan-box', onsubmit: (e) => {
          e.preventDefault();
          const d = D.formData(form);
          const cambiati = Object.fromEntries(Object.entries(d).filter(([k, v]) => c.attr.find((a) => a.tag === k)?.valore !== v));
          if (Object.keys(cambiati).length) registra(`${c.h}|attributi`, { h: c.h, azione: 'attributi', valori: cambiati }, 'Cartiglio aggiornato');
          else annulla(`${c.h}|attributi`);
          disegnaPannello();
        } }, h('div', { class: 'eyebrow' }, `Cartiglio · ${c.nome}`), campi,
        h('div', { class: 'plan-actions' }, h('button', { class: 'btn small', type: 'submit' }, 'Applica al cartiglio')));
        parti.push(form);
      }

      // storico
      parti.push(h('section', { class: 'plan-box' },
        h('div', { class: 'eyebrow' }, 'Versioni'),
        h('ul', { class: 'plan-versions' }, pl.versions.map((v) => h('li', { class: v.id === versioneAperta?.id ? 'on' : '' },
          h('button', { type: 'button', class: 'plan-v', onclick: () => apri(v), title: 'Apri questa versione' },
            h('b', {}, `v${v.number}`),
            h('span', {}, v.note || v.file_name),
            h('small', {}, `${D.date(v.created_at)}${v.user_name ? ` · ${v.user_name}` : ''}`)),
          v.report ? h('span', { class: `badge ${v.report.superato ? 'concluso' : 'sospeso'}`, title: (v.report.controlli || []).map((c) => `${c.nome}: ${c.dettaglio}`).join('\n') }, v.report.superato ? 'controlli ok' : 'da verificare') : null,
          h('a', { class: 'btn ghost icon small', href: `api.php?r=files/${v.file_id}`, download: '', title: 'Scarica', 'aria-label': `Scarica la versione ${v.number}` }, D.icon('download', 13)))))));

      pannello.replaceChildren(...parti);
    }

    // --- caricamento di una versione ---
    async function apri(v) {
      if (elencoModifiche().length && !D.confirm('Ci sono modifiche non salvate: aprire comunque un\'altra versione?')) return;
      versioneAperta = v;
      modifiche.clear(); ordine.length = 0; selezione = null;
      modello = null;
      disegnaPannello();
      messaggio(D.cad.stato === 'pronto' ? 'Leggo il disegno…' : 'Avvio del motore CAD…');
      try {
        const res = await fetch(`api.php?r=files/${v.file_id}`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('File della versione non trovato');
        byte = await res.arrayBuffer();
        const m = await D.cad.modello(byte);
        messaggio('Leggo il disegno…');
        for (const q of m.quote) q.ptLibero = false;
        modello = m;
        // testo in posizione libera se non è a metà della linea
        for (const q of modello.quote) {
          if (!q.pt || !q.base || q.tipo === 'altro') continue;
          const [e1, e2] = estremi(q, q.base);
          q.ptLibero = Math.hypot(q.pt[0] - (e1[0] + e2[0]) / 2, q.pt[1] - (e1[1] + e2[1]) / 2) > (q.ht || 0.1) * 0.6;
        }
        punti = [];
        for (const el of modello.geometria) for (const q of el.p) punti.push(q);
        for (const q of modello.quote) if (q.p1) punti.push(q.p1, q.p2);
        disegnaTutto();
        adatta();
        messaggio('');
      } catch (err) {
        messaggio(`Errore: ${err.message}`);
      }
      disegnaPannello();
    }

    async function nuovaVersione(dati, nota, resoconto) {
      const fd = new FormData();
      const numero = (pl.versions[0]?.number || 0) + 1;
      fd.append('file', new Blob([dati], { type: 'application/dxf' }), `${pl.title} v${numero}.dxf`);
      fd.append('note', nota);
      fd.append('report', JSON.stringify(resoconto));
      await D.upload(`plans/${id}/versions`, fd);
      pl = await D.get(`plans/${id}`);
      modifiche.clear(); ordine.length = 0;
      await apri(pl.versions[0]);
    }

    async function salva(btn, nota) {
      const lista = elencoModifiche();
      await D.run(btn, async () => {
        messaggio('Applico le modifiche al disegno…');
        const profilo = lista.some((m) => m.azione === 'aggiungi') ? (await D.get('settings')).cad_profile || '' : '';
        const { byte: nuovo, resoconto } = await D.cad.ritocca(byte, lista, profilo);
        messaggio('');
        if (!resoconto.superato && !D.confirm(`Alcuni controlli non sono superati:\n${resoconto.controlli.filter((c) => !c.ok).map((c) => `• ${c.nome}: ${c.dettaglio}`).join('\n')}\n\nSalvare comunque la nuova versione?`)) return;
        const riepilogo = Object.entries(resoconto.riepilogo).map(([k, n]) => `${n} ${ETICHETTE[k] || k}`).join(', ');
        await nuovaVersione(nuovo, nota || `Ritocchi: ${riepilogo}`, { superato: resoconto.superato, controlli: resoconto.controlli.map((c) => ({ nome: c.nome, esito: c.ok ? 'ok' : 'fallito', dettaglio: c.dettaglio })) });
      }, 'Nuova versione salvata');
    }

    async function quotaAutomatica(btn) {
      if (!D.confirm(`Creare una nuova versione con le quote automatiche a partire dalla versione ${versioneAperta.number}?`)) return;
      await D.run(btn, async () => {
        messaggio('Calcolo le quote…');
        const profilo = (await D.get('settings')).cad_profile || '';
        const { byte: nuovo, esito } = await D.cad.quota(byte, profilo, { valori_cartiglio: { STUDIO: D.studioName, PROGETTO: `${pl.code} · ${pl.project_title}`, ELABORATO: kinds[pl.kind] || pl.title } });
        messaggio('');
        const numero = (re) => (esito.log.find((r) => re.test(r)) || '').match(/\d+/)?.[0] || '0';
        await nuovaVersione(nuovo, `Quotatura automatica: ${numero(/quote esterne/)} quote esterne e ${numero(/quote interne/)} interne (${esito.secondi} s)`,
          { superato: esito.superato, controlli: esito.controlli.map((c) => ({ nome: c.nome, esito: c.esito, dettaglio: c.dettaglio })) });
      }, 'Quote calcolate: nuova versione salvata');
      messaggio('');
    }

    function modificaPlanimetria() {
      const form = h('form', { class: 'form', onsubmit: async (e) => {
        e.preventDefault();
        const out = await D.submit(form, (d) => D.put(`plans/${id}`, d), 'Planimetria aggiornata');
        if (out) { pl = out; chiudi(); disegnaPannello(); }
      } },
      D.field('Titolo', D.input('title', pl.title, { required: true }), { required: true }),
      D.field('Tipo', D.select('kind', kinds, pl.kind)),
      D.field('Note', D.textarea('notes', pl.notes || '', { rows: 3 })),
      h('div', { class: 'form-actions' }, h('button', { class: 'btn primary', type: 'submit' }, 'Salva')));
      const chiudi = D.modal('Modifica planimetria', form);
    }

    const ETICHETTE = { aggiungi: 'quote aggiunte', elimina: 'quote eliminate', sposta_linea: 'linee spostate', sposta_testo: 'testi spostati', testo_al_centro: 'testi al centro', testo: 'testi modificati', attributi: 'cartigli aggiornati' };

    window.addEventListener('beforeunload', (e) => { if (elencoModifiche().length) e.preventDefault(); });
    disegnaPannello();
    if (versioneAperta) apri(versioneAperta); else messaggio('Questa planimetria non ha versioni.');
    return { title: pl.title, nav: 'planimetrie', back: ['/planimetrie', 'Planimetrie'], hideTitle: true };
  });

  // ---------- profilo CAD dello studio (Impostazioni) ----------

  D.profiloCadCard = function (impostazioni) {
    const corpo = h('div', {});
    const scegli = h('input', { type: 'file', accept: '.dxf', hidden: true, onchange: () => ricava(scegli.files[0]) });
    const stato = h('span', { class: 'muted small' });
    D.cad.ascolta((t) => { if (stato.isConnected && stato.dataset.attivo) stato.textContent = t; });

    function disegna(json) {
      let p = null;
      try { p = json ? JSON.parse(json) : null; } catch { p = null; }
      const righe = p ? [
        ['Layer delle quote', `${p.layer_quote.name}${p.layer_quote.color !== undefined ? ` (colore ${p.layer_quote.color})` : ''}`],
        ['Stile di quota', `${p.stile_quote.name}${p.stile_testo ? ` · testo ${p.stile_testo.name}` : ''}`],
        ['Scala', p.scala ? `1:${p.scala}` : '—'],
        ['Muri', (p.layer_muri || []).join(', ') || '—'],
        ['Porte e finestre', (p.layer_aperture || []).join(', ') || '—'],
        ['Arredi', (p.layer_arredi || []).join(', ') || '—'],
        ['Limiti', (p.layer_limiti || []).join(', ') || '—'],
      ] : null;
      corpo.replaceChildren(
        h('p', { class: 'small', style: 'margin:0 0 12px;color:var(--ink-2)' }, p
          ? 'DOMUS quota le planimetrie sul layer e con lo stile dello studio, ricavati dal file di riferimento.'
          : 'Carica un disegno dello studio già quotato a mano (DXF): DOMUS ricava da solo il layer e lo stile delle quote, la scala e i layer di muri, porte, arredi e limiti. Finché non lo carichi si usa il profilo dimostrativo.'),
        righe ? h('dl', { class: 'facts' }, righe.map(([k, v]) => [h('dt', {}, k), h('dd', {}, v)])) : null,
        h('div', { class: 'form-actions', style: 'justify-content:flex-start;margin-top:16px' },
          h('button', { class: 'btn primary small', type: 'button', onclick: () => scegli.click() }, D.icon('upload', 14), p ? 'Carica un altro riferimento' : 'Carica il file di riferimento'),
          p ? h('button', { class: 'btn ghost small', type: 'button', onclick: (e) => salva(e.currentTarget, '') }, 'Usa il profilo dimostrativo') : null,
          stato),
        scegli);
    }

    async function salva(btn, json) {
      const out = await D.run(btn, () => D.put('settings', { cad_profile: json }), json ? 'Profilo CAD salvato' : 'Profilo CAD tolto');
      if (out) disegna(out.cad_profile);
    }

    async function ricava(file) {
      if (!file) return;
      stato.dataset.attivo = '1';
      stato.textContent = 'Leggo il file di riferimento…';
      try {
        const p = await D.cad.profilo(await file.arrayBuffer());
        stato.textContent = (p.rilevato || []).slice(0, 2).join(' · ');
        await salva(null, JSON.stringify(p));
      } catch (err) {
        stato.textContent = '';
        D.toast(err.message, true);
      }
      delete stato.dataset.attivo;
      scegli.value = '';
    }

    disegna(impostazioni.cad_profile);
    return corpo;
  };
})();
