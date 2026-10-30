// DOMUS — schermate di sintesi: Studio (statistiche), Galleria, Bacheca.
// Grafici in SVG disegnati qui, monocromi come il resto dell'interfaccia (vedi STILE.md).
'use strict';

(() => {
  const { h } = D;
  const NS = 'http://www.w3.org/2000/svg';
  const MONTHS = ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];

  const svg = (tag, attrs, ...kids) => {
    const n = document.createElementNS(NS, tag);
    for (const [k, v] of Object.entries(attrs || {})) n.setAttribute(k, v);
    kids.flat().forEach((k) => k && n.append(k));
    return n;
  };
  const title = (t) => { const n = document.createElementNS(NS, 'title'); n.textContent = t; return n; };

  const card = (head, action, ...body) => h('section', { class: 'card' }, h('div', { class: 'card-head' }, h('h2', {}, head), action || null), ...body);

  // Istogramma a colonne: values (numeri), labels, formattazione del valore.
  function bars(values, labels, fmt, { highlightLast = true, height = 210 } = {}) {
    const W = 640;
    const H = height;
    const pad = { l: 8, r: 8, t: 24, b: 26 };
    const max = Math.max(1, ...values);
    const bw = (W - pad.l - pad.r) / values.length;
    const g = svg('svg', { viewBox: `0 0 ${W} ${H}`, class: 'chart', role: 'img' });
    [0.25, 0.5, 0.75, 1].forEach((f) => {
      const y = pad.t + (H - pad.t - pad.b) * (1 - f);
      g.append(svg('line', { x1: pad.l, x2: W - pad.r, y1: y, y2: y, class: 'grid' }));
    });
    values.forEach((v, i) => {
      const bh = (H - pad.t - pad.b) * (v / max);
      const x = pad.l + i * bw + bw * 0.22;
      const y = H - pad.b - bh;
      const last = highlightLast && i === values.length - 1;
      g.append(svg('rect', { x, y, width: bw * 0.56, height: Math.max(bh, v ? 1.5 : 0), rx: 2, class: last ? 'bar on' : 'bar' }, title(`${labels[i]}: ${fmt(v)}`)));
      if (v && (last || v === max)) g.append(svg('text', { x: x + bw * 0.28, y: y - 8, class: 'val', 'text-anchor': 'middle' }, document.createTextNode(fmt(v))));
      g.append(svg('text', { x: x + bw * 0.28, y: H - 8, class: 'lab', 'text-anchor': 'middle' }, document.createTextNode(labels[i])));
    });
    return g;
  }

  // Barre orizzontali con etichetta e valore.
  function hbars(rows, fmt, hrefOf) {
    const max = Math.max(1, ...rows.map((r) => r.value));
    return h('ul', { class: 'hbars' }, rows.map((r) => h('li', {},
      h(hrefOf ? 'a' : 'div', { class: 'hbar-label', href: hrefOf ? hrefOf(r) : null }, r.label, r.meta ? h('span', { class: 'code' }, r.meta) : null),
      h('div', { class: 'hbar-track' }, h('i', { style: `width:${Math.max(2, (r.value / max) * 100)}%` })),
      h('span', { class: 'num' }, fmt(r.value)))));
  }

  // Anello per la distribuzione degli stati dei progetti.
  function ring(parts) {
    const total = parts.reduce((s, p) => s + p.value, 0) || 1;
    const R = 54;
    const C = 2 * Math.PI * R;
    let off = 0;
    const g = svg('svg', { viewBox: '0 0 140 140', class: 'ring-chart', role: 'img' });
    g.append(svg('circle', { cx: 70, cy: 70, r: R, class: 'ring-bg' }));
    parts.forEach((p) => {
      const len = (p.value / total) * C;
      g.append(svg('circle', { cx: 70, cy: 70, r: R, class: `ring-seg ${p.cls}`, 'stroke-dasharray': `${Math.max(len - 2, 0)} ${C}`, 'stroke-dashoffset': -off, transform: 'rotate(-90 70 70)' }, title(`${p.label}: ${p.value}`)));
      off += len;
    });
    g.append(svg('text', { x: 70, y: 68, 'text-anchor': 'middle', class: 'ring-n' }, document.createTextNode(total)));
    g.append(svg('text', { x: 70, y: 86, 'text-anchor': 'middle', class: 'ring-l' }, document.createTextNode('PROGETTI')));
    return g;
  }

  // ---------- Studio ----------

  D.route('/studio', async (page) => {
    const r = await D.get('reports');
    const labels = r.months.map((m) => MONTHS[Number(m.slice(5)) - 1]);
    const k = (v) => (v >= 1000 ? `${(v / 1000).toLocaleString('it-IT', { maximumFractionDigits: 1 })}k` : String(Math.round(v)));
    const statusParts = [
      ['in_corso', 'In corso', 's1'], ['preventivo', 'Preventivi', 's2'], ['sospeso', 'Sospesi', 's3'], ['concluso', 'Conclusi', 's4'], ['archiviato', 'Archivio', 's5'],
    ].map(([key, label, cls]) => ({ label, cls, value: Number((r.status.find((s) => s.status === key) || {}).n || 0) }));
    const sumPaid = r.paid ? r.paid.reduce((a, b) => a + b, 0) : 0;
    const sumHours = r.hours_month.reduce((a, b) => a + b, 0);

    page.append(
      h('div', { class: 'stats' },
        r.totals ? h('div', { class: 'stat' }, h('div', { class: 'n' }, D.money(sumPaid)), h('div', { class: 'l' }, 'Incassato in 12 mesi')) : null,
        r.totals ? h('div', { class: 'stat' }, h('div', { class: 'n' }, D.money(r.totals.to_collect)), h('div', { class: 'l' }, 'Da incassare')) : null,
        h('div', { class: 'stat' }, h('div', { class: 'n' }, D.hours(sumHours)), h('div', { class: 'l' }, 'Ore in 12 mesi')),
        h('div', { class: 'stat' }, h('div', { class: 'n' }, r.permits.avg_days ? `${r.permits.avg_days} gg` : '—'), h('div', { class: 'l' }, 'Tempo medio pratiche'),
          h('div', { class: 'item-meta' }, `${r.permits.waiting || 0} in attesa · ${r.permits.approved || 0} approvate`))),
      h('div', { class: 'grid-2' },
        h('div', {},
          r.paid ? card('Incassi per mese', h('span', { class: 'item-meta' }, 'ultimi 12 mesi'), bars(r.paid, labels, (v) => `€ ${k(v)}`)) : null,
          card('Ore lavorate per mese', h('span', { class: 'item-meta' }, 'tutto lo studio'), bars(r.hours_month, labels, (v) => `${Math.round(v)} h`, { height: 180 }))),
        h('div', {},
          card('Progetti per stato', null, h('div', { class: 'ring-wrap' }, ring(statusParts),
            h('ul', { class: 'legend-list' }, statusParts.map((p) => h('li', {}, h('i', { class: p.cls }), p.label, h('b', {}, p.value)))))),
          card('Dove va il tempo', h('span', { class: 'item-meta' }, 'ultimi 90 giorni'),
            r.hours_projects.length ? hbars(r.hours_projects.map((p) => ({ label: p.title, meta: p.code, value: Number(p.hours), id: p.id })), (v) => D.hours(v), (row) => `#/progetti/${row.id}/ore`)
              : h('p', { class: 'muted small' }, 'Nessuna ora registrata.')),
          card('Squadra', h('span', { class: 'item-meta' }, 'ultimi 30 giorni'),
            r.hours_people.length ? hbars(r.hours_people.map((u) => ({ label: u.name, value: Number(u.hours) })), (v) => D.hours(v))
              : h('p', { class: 'muted small' }, 'Nessuna ora registrata.')))));
    return { title: 'Studio', nav: 'studio' };
  });

  // ---------- Galleria ----------

  D.route('/galleria', async (page) => {
    const photos = await D.get('gallery');
    if (!photos.length) {
      page.append(h('div', { class: 'card' }, h('div', { class: 'empty' }, 'Le foto caricate nei progetti compariranno qui.')));
      return { title: 'Galleria', nav: 'galleria' };
    }
    const projects = [...new Map(photos.map((p) => [p.project_id, p])).values()];
    let filter = 'tutti';
    const grid = h('div', { class: 'gallery' });
    const chips = h('div', { class: 'tabs' });
    function draw() {
      chips.replaceChildren(...[['tutti', `Tutte (${photos.length})`], ...projects.map((p) => [String(p.project_id), p.code])].map(([k, l]) =>
        h('button', { type: 'button', class: filter === k ? 'on' : '', onclick: () => { filter = k; draw(); } }, l)));
      const list = photos.filter((p) => filter === 'tutti' || String(p.project_id) === filter);
      grid.replaceChildren(...list.map((p, i) => h('button', { type: 'button', class: `shot${i % 7 === 0 ? ' wide' : ''}`, onclick: () => lightbox(list, i) },
        h('img', { src: `api.php?r=files/${p.id}&inline=1`, alt: p.name, loading: 'lazy' }),
        h('span', { class: 'shot-cap' }, h('b', {}, p.title), h('span', {}, `${p.code}${p.city ? ` · ${p.city}` : ''}`)))));
    }
    draw();
    page.append(h('div', { class: 'toolbar' }, chips), grid);
    return { title: 'Galleria', nav: 'galleria' };
  });

  function lightbox(list, start) {
    let i = start;
    const img = h('img', { alt: '' });
    const cap = h('div', { class: 'lb-cap' });
    const show = () => {
      const p = list[i];
      img.src = `api.php?r=files/${p.id}&inline=1`;
      img.alt = p.name;
      cap.replaceChildren(h('span', { class: 'eyebrow' }, `${p.code} · ${i + 1} / ${list.length}`), h('div', {}, p.title), h('a', { class: 'cta', href: `#/progetti/${p.project_id}/documenti`, onclick: () => close() }, h('span', {}, 'Apri il progetto'), h('span', { class: 'ring' }, D.icon('arrow', 15))));
    };
    const step = (d) => { i = (i + d + list.length) % list.length; show(); };
    const onKey = (e) => { if (e.key === 'Escape') close(); if (e.key === 'ArrowRight') step(1); if (e.key === 'ArrowLeft') step(-1); };
    const close = () => { box.remove(); document.removeEventListener('keydown', onKey); };
    const box = h('div', { class: 'lightbox', role: 'dialog', 'aria-modal': 'true', onclick: (e) => { if (e.target === box) close(); } },
      h('button', { class: 'btn icon lb-x', 'aria-label': 'Chiudi', onclick: close }, D.icon('x', 18)),
      h('button', { class: 'btn icon lb-prev', 'aria-label': 'Precedente', onclick: () => step(-1) }, D.icon('back', 18)),
      h('figure', {}, img, cap),
      h('button', { class: 'btn icon lb-next', 'aria-label': 'Successiva', onclick: () => step(1) }, D.icon('arrow', 18)));
    document.body.append(box);
    document.addEventListener('keydown', onKey);
    show();
  }

  // ---------- Bacheca: progetti per stato, si spostano trascinandoli ----------

  D.route('/bacheca', async (page) => {
    const list = await D.get('projects');
    const COLS = [['preventivo', 'Preventivi'], ['in_corso', 'In corso'], ['sospeso', 'Sospesi'], ['concluso', 'Conclusi']];
    const board = h('div', { class: 'board' });
    let dragged = null;

    const mini = (p) => h('a', {
      class: 'bcard', href: `#/progetti/${p.id}`, draggable: 'true',
      ondragstart: (e) => { dragged = p; e.dataTransfer.effectAllowed = 'move'; e.currentTarget.classList.add('dragging'); },
      ondragend: (e) => e.currentTarget.classList.remove('dragging'),
    },
    p.cover_id ? h('img', { src: `api.php?r=files/${p.cover_id}&inline=1`, alt: '', loading: 'lazy' }) : null,
    h('div', { class: 'bcard-body' },
      h('span', { class: 'code' }, p.code),
      h('div', { class: 'item-title' }, p.title),
      h('div', { class: 'item-meta' }, [p.client_name, p.city].filter(Boolean).join(' · ')),
      h('div', { class: 'pcard-foot' }, h('div', { class: 'progress' }, h('i', { style: `width:${p.phases_total ? Math.round((p.phases_done / p.phases_total) * 100) : 0}%` })),
        h('span', { class: 'item-meta' }, p.current_phase || ''))));

    COLS.forEach(([status, label]) => {
      const items = list.filter((p) => p.status === status);
      const col = h('section', { class: 'bcol',
        ondragover: (e) => { e.preventDefault(); col.classList.add('over'); },
        ondragleave: () => col.classList.remove('over'),
        ondrop: async (e) => {
          e.preventDefault();
          col.classList.remove('over');
          if (!dragged || dragged.status === status) return;
          const ok = await D.run(null, () => D.put(`projects/${dragged.id}/status`, { status }), `${dragged.code} → ${label}`);
          if (ok !== undefined) D.render();
        } },
      h('header', {}, h('span', { class: 'eyebrow' }, label), h('span', { class: 'tab-count' }, items.length)),
      h('div', { class: 'bcol-list' }, items.length ? items.map(mini) : h('div', { class: 'bcol-empty' }, 'Trascina qui un progetto')));
      board.append(col);
    });
    page.append(h('p', { class: 'muted small', style: 'margin:0 0 14px' }, 'Trascina un progetto in un\'altra colonna per cambiarne lo stato.'), board);
    return { title: 'Bacheca', nav: 'bacheca' };
  });
})();
