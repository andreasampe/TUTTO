// DOMUS — pagine: Panoramica, Progetti, Progetto, Clienti, Cliente, Scadenze, Impostazioni.
'use strict';

(() => {
  const { h } = D;

  // ---------- pezzi riutilizzati ----------

  const card = (title, action, ...body) => h('section', { class: 'card' },
    h('div', { class: 'card-head' }, h('h2', {}, title), action || null), ...body);

  const empty = (text, action) => h('div', { class: 'empty' }, h('div', {}, text), action ? h('div', { class: 'mt' }, action) : null);

  const progress = (done, total) => h('div', { class: 'progress', title: `${done} di ${total} fasi completate` },
    h('i', { style: `width:${total ? Math.round((done / total) * 100) : 0}%` }));

  const dueLabel = (iso) => (iso ? h('span', { class: `date${D.isLate(iso) ? ' late' : ''}` }, D.relDate(iso)) : null);

  // Riga di una scadenza con casella "fatta".
  function taskRow(t, { showProject = true, onChange } = {}) {
    const box = h('input', { type: 'checkbox', checked: !!Number(t.done), 'aria-label': 'Fatta', onchange: async () => {
      const ok = await D.run(box, () => D.put(`tasks/${t.id}`, { done: box.checked }));
      if (ok === undefined) { box.checked = !box.checked; return; }
      await D.refreshOverdue();
      onChange ? onChange() : D.render();
    } });
    return h('li', { class: `task${Number(t.done) ? ' done' : ''}` },
      box,
      h('div', { class: 'item-main' },
        h('div', { class: 'item-title' }, t.title),
        h('div', { class: 'item-meta' },
          showProject && t.project_id ? h('a', { href: `#/progetti/${t.project_id}` }, `${t.project_code} · ${t.project_title}`) : (showProject ? 'Studio' : null),
          t.notes ? ` — ${t.notes}` : null)),
      dueLabel(t.due_date),
      h('button', { class: 'btn ghost small x', type: 'button', title: 'Elimina', 'aria-label': 'Elimina scadenza', onclick: async (e) => {
        if (!D.confirm(`Eliminare la scadenza "${t.title}"?`)) return;
        if (await D.run(e.currentTarget, () => D.del(`tasks/${t.id}`)) !== undefined) { await D.refreshOverdue(); onChange ? onChange() : D.render(); }
      } }, '✕'));
  }

  function taskForm(projectId, onDone) {
    const form = h('form', { class: 'inline-form', onsubmit: async (e) => {
      e.preventDefault();
      const out = await D.submit(form, (d) => D.post('tasks', { ...d, project_id: projectId || d.project_id || null }), 'Scadenza aggiunta');
      if (out) { form.reset(); await D.refreshOverdue(); onDone(); }
    } },
    h('input', { name: 'title', type: 'text', placeholder: 'Nuova scadenza o cosa da fare…', required: true, 'aria-label': 'Descrizione' }),
    h('input', { name: 'due_date', type: 'date', 'aria-label': 'Data' }),
    h('button', { class: 'btn', type: 'submit' }, 'Aggiungi'));
    return form;
  }

  // ---------- Panoramica ----------

  function greeting() {
    const hour = new Date().getHours();
    return hour < 13 ? 'Buongiorno' : hour < 18 ? 'Buon pomeriggio' : 'Buonasera';
  }

  D.route('/', async (page) => {
    const d = await D.get('dashboard');
    const stat = (n, label, href, hint) => h('a', { class: 'stat', href: `#${href}` }, h('div', { class: 'n' }, n), h('div', { class: 'l' }, label), hint ? h('div', { class: 'item-meta' }, hint) : null);

    const deadlines = [...d.overdue, ...d.upcoming];
    const f = d.finance;
    page.append(
      h('div', { class: 'stats' },
        stat(d.counts.in_corso || 0, 'Progetti in corso', '/progetti', d.counts.preventivo ? `${d.counts.preventivo} preventivi aperti` : null),
        stat(d.overdue.length, 'Scadenze superate', '/scadenze', d.upcoming.length ? `${d.upcoming.length} nei prossimi 14 giorni` : null),
        f ? stat(D.money(f.to_collect), 'Da incassare', '/progetti', f.to_invoice ? `${D.money(f.to_invoice)} da fatturare` : null)
          : stat(d.permits.length, 'Pratiche in attesa', '/progetti'),
        f ? stat(D.money(f.paid_year), `Incassato ${new Date().getFullYear()}`, '/progetti')
          : stat(D.hours(d.hours_week), 'Le tue ore', '/progetti', 'questa settimana')),
      h('div', { class: 'grid-2' },
        h('div', {},
          card('Progetti in corso', h('a', { class: 'btn ghost small', href: '#/progetti' }, 'Tutti →'),
            d.active.length
              ? h('ul', { class: 'list' }, d.active.map((p) => h('li', {}, h('a', { class: 'item', href: `#/progetti/${p.id}` },
                h('div', { class: 'item-main' },
                  h('div', { class: 'item-title' }, p.title),
                  h('div', { class: 'item-meta' }, h('span', { class: 'code' }, p.code), p.client_name ? ` · ${p.client_name}` : '', p.current_phase ? ` · ${p.current_phase}` : '')),
                progress(Number(p.phases_done), Number(p.phases_total))))))
              : empty('Nessun progetto in corso.', h('button', { class: 'btn primary', onclick: () => projectModal() }, 'Nuovo progetto'))),
          d.permits.length ? card('Pratiche in attesa di esito', null,
            h('ul', { class: 'list' }, d.permits.map((pe) => h('li', {}, h('a', { class: 'item', href: `#/progetti/${pe.project_id}/pratiche` },
              h('div', { class: 'item-main' },
                h('div', { class: 'item-title' }, `${pe.kind}${pe.protocol ? ` · prot. ${pe.protocol}` : ''}`),
                h('div', { class: 'item-meta' }, `${pe.project_code} · ${pe.project_title}`)),
              h('span', { class: `badge permit-${pe.status}` }, D.PERMIT_STATUS[pe.status]),
              h('span', { class: 'date', title: 'Giorni dal deposito' }, pe.days_waiting > 0 ? `${pe.days_waiting} gg` : 'oggi')))))) : null,
          card('Diario recente', null,
            d.journal.length
              ? h('ul', { class: 'list' }, d.journal.map((j) => h('li', {}, h('a', { class: 'item', href: `#/progetti/${j.project_id}/diario` },
                h('div', { class: 'item-main' },
                  h('div', { class: 'item-meta' }, `${D.date(j.entry_date)} · ${D.JOURNAL_KIND[j.kind] || j.kind} · ${j.project_code}`),
                  h('div', { class: 'item-title', style: 'white-space:nowrap' }, j.text))))))
              : empty('Le note dei sopralluoghi, delle riunioni e delle telefonate compariranno qui.'))),
        h('div', {},
          card('Prossime scadenze', h('a', { class: 'btn ghost small', href: '#/scadenze' }, 'Tutte →'),
            deadlines.length
              ? h('ul', { class: 'list' }, deadlines.map((t) => taskRow(t)))
              : empty('Nessuna scadenza nei prossimi 14 giorni.')),
          f && f.overdue.length ? card('Fatture da incassare', null,
            h('ul', { class: 'list' }, f.overdue.map((r) => h('li', {}, h('a', { class: 'item', href: `#/progetti/${r.project_id}/economia` },
              h('div', { class: 'item-main' }, h('div', { class: 'item-title' }, r.description), h('div', { class: 'item-meta' }, `${r.project_code} · fatturata il ${D.date(r.invoiced_at)}`)),
              h('b', { class: 'num' }, D.money(r.amount))))))) : null)));

    return {
      title: `${greeting()}, ${D.user.name.split(' ')[0]}`,
      nav: 'home',
      actions: h('button', { class: 'btn primary', onclick: () => projectModal() }, '+ Nuovo progetto'),
    };
  });

  // ---------- Progetti ----------

  D.route('/progetti(?:\\?(\\w+))?', async (page, initial) => {
    let status = initial || 'attivi';
    let q = '';
    const tbody = h('tbody');
    const wrap = h('div', {});

    async function load() {
      const list = await D.get(`projects&status=${encodeURIComponent(status === 'tutti' ? '' : status)}&q=${encodeURIComponent(q)}`);
      tbody.replaceChildren(...list.map((p) => h('tr', { onclick: () => D.go(`/progetti/${p.id}`), tabindex: 0, onkeydown: (e) => { if (e.key === 'Enter') D.go(`/progetti/${p.id}`); } },
        h('td', {}, h('span', { class: 'code' }, p.code)),
        h('td', {}, h('div', { class: 'item-title' }, p.title), h('div', { class: 'item-meta' }, [p.intervention, p.city].filter(Boolean).join(' · '))),
        h('td', { class: 'hide-sm' }, p.client_name || h('span', { class: 'muted' }, '—')),
        h('td', { class: 'hide-sm' }, h('div', { style: 'display:flex;align-items:center;gap:8px' }, progress(Number(p.phases_done), Number(p.phases_total)),
          h('span', { class: 'item-meta' }, p.current_phase || ''))),
        h('td', {}, D.statusBadge(p.status)),
        h('td', { class: 'hide-sm' }, dueLabel(p.next_deadline)))));
      wrap.replaceChildren(list.length
        ? h('div', { class: 'card table-wrap', style: 'padding:14px 8px 6px' }, h('table', {},
          h('thead', {}, h('tr', {}, ['Codice', 'Progetto', 'Cliente', 'Avanzamento', 'Stato', 'Prossima scadenza'].map((t, i) => h('th', { class: [2, 3, 5].includes(i) ? 'hide-sm' : '' }, t)))),
          tbody))
        : h('div', { class: 'card' }, empty(q || status !== 'attivi' ? 'Nessun progetto corrisponde ai filtri.' : 'Ancora nessun progetto.',
          h('button', { class: 'btn primary', onclick: () => projectModal() }, 'Crea il primo progetto'))));
    }

    const tabs = h('div', { class: 'tabs', role: 'tablist' });
    const TABS = { attivi: 'Attivi', in_corso: 'In corso', preventivo: 'Preventivi', sospeso: 'Sospesi', concluso: 'Conclusi', archiviato: 'Archivio', tutti: 'Tutti' };
    const drawTabs = () => tabs.replaceChildren(...Object.entries(TABS).map(([k, l]) =>
      h('button', { type: 'button', class: status === k ? 'on' : '', role: 'tab', 'aria-selected': String(status === k), onclick: () => { status = k; drawTabs(); load(); } }, l)));
    drawTabs();

    let timer;
    page.append(
      h('div', { class: 'toolbar' },
        h('input', { class: 'search', type: 'search', placeholder: 'Cerca per titolo, codice, comune, cliente…', 'aria-label': 'Cerca', oninput: (e) => { clearTimeout(timer); q = e.target.value.trim(); timer = setTimeout(load, 200); } }),
        tabs),
      wrap);
    await load();
    return { title: 'Progetti', nav: 'progetti', actions: h('button', { class: 'btn primary', onclick: () => projectModal() }, '+ Nuovo progetto') };
  });

  async function projectModal(existing, preset = {}) {
    const p = existing || preset;
    const clients = await D.get('clients');
    const code = existing ? p.code : (await D.get('projects/next-code')).code;
    const clientOpts = { '': '— Nessuno —', ...Object.fromEntries(clients.map((c) => [c.id, c.name])), __new: '+ Nuovo cliente…' };
    const clientSel = D.select('client_id', clientOpts, p.client_id ?? '');
    const newClient = h('div', { hidden: true }, D.field('Nome del nuovo cliente', D.input('new_client_name', '', { placeholder: 'es. Mario Rossi' })));
    clientSel.addEventListener('change', () => { newClient.hidden = clientSel.value !== '__new'; });

    const form = h('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      const out = await D.submit(form, async (d) => {
        if (d.client_id === '__new') {
          if (!d.new_client_name.trim()) throw Object.assign(new Error('Scrivi il nome del nuovo cliente'), { fields: { new_client_name: 'Obbligatorio' } });
          d.client_id = (await D.post('clients', { name: d.new_client_name })).id;
        }
        delete d.new_client_name;
        return existing ? D.put(`projects/${p.id}`, d) : D.post('projects', d);
      }, existing ? 'Progetto salvato' : 'Progetto creato');
      if (!out) return;
      close();
      if (existing) D.render(); else D.go(`/progetti/${out.id}`);
    } },
    h('div', { class: 'row' },
      D.field('Titolo', D.input('title', p.title, { required: true, placeholder: 'es. Ristrutturazione appartamento via Roma' }), { required: true })),
    h('div', { class: 'row' },
      D.field('Codice', D.input('code', code, { required: true })),
      D.field('Stato', D.select('status', D.STATUS, p.status || 'in_corso')),
      D.field('Cliente', clientSel)),
    newClient,
    h('div', { class: 'row' },
      D.field('Tipo di intervento', D.input('intervention', p.intervention, { placeholder: 'es. Ristrutturazione, Nuova costruzione…', list: 'interventions' })),
      D.field('Onorario', D.input('fee', p.fee ?? '', { inputmode: 'decimal', placeholder: 'es. 12.500' }), { hint: 'In euro' })),
    h('datalist', { id: 'interventions' }, ['Ristrutturazione', 'Manutenzione straordinaria', 'Nuova costruzione', 'Ampliamento', 'Cambio di destinazione d\'uso', 'Restauro', 'Interior design', 'Pratica edilizia', 'Consulenza'].map((v) => h('option', { value: v }))),
    h('div', { class: 'row' },
      D.field('Indirizzo', D.input('address', p.address, { placeholder: 'Via e numero civico' })),
      D.field('Comune', D.input('city', p.city))),
    D.field('Dati catastali', D.input('cadastral', p.cadastral, { placeholder: 'es. Foglio 12, Particella 345, Sub 6' })),
    h('div', { class: 'row' },
      D.field('Inizio', D.input('start_date', p.start_date, { type: 'date' })),
      D.field('Consegna prevista', D.input('due_date', p.due_date, { type: 'date' }))),
    D.field('Note', D.textarea('notes', p.notes, { rows: 3 })),
    existing ? null : h('p', { class: 'item-meta', style: 'margin:0' }, 'Al progetto vengono aggiunte le fasi standard (modificabili in Impostazioni).'),
    h('div', { class: 'form-actions' },
      h('button', { class: 'btn ghost', type: 'button', onclick: () => close() }, 'Annulla'),
      h('button', { class: 'btn primary', type: 'submit' }, existing ? 'Salva' : 'Crea progetto')));
    const close = D.modal(existing ? 'Modifica progetto' : 'Nuovo progetto', form);
  }

  // ---------- Dettaglio progetto ----------
  // Indirizzi: #/progetti/12 (riepilogo) e #/progetti/12/<scheda>

  const TABS = [
    ['riepilogo', 'Riepilogo'],
    ['diario', 'Diario'],
    ['documenti', 'Documenti'],
    ['pratiche', 'Pratiche'],
    ['economia', 'Economia', true],
    ['ore', 'Ore'],
  ];

  D.route('/progetti/(\\d+)(?:/(\\w+))?', async (page, id, tabName) => {
    const p = await D.get(`projects/${id}`);
    const tabs = TABS.filter(([, , adminOnly]) => !adminOnly || D.isAdmin());
    let current = tabs.some(([k]) => k === tabName) ? tabName : 'riepilogo';
    const body = h('div', { class: 'tab-body' });
    const bar = h('nav', { class: 'tabbar', role: 'tablist' });

    const counts = {
      diario: p.journal.length,
      documenti: p.files.length,
      pratiche: p.permits.length,
      ore: p.hours.total ? D.hours(p.hours.total) : 0,
    };
    // Ricarica solo i dati e ridisegna la scheda attuale, senza tornare in cima alla pagina.
    const reload = async () => {
      Object.assign(p, await D.get(`projects/${id}`));
      counts.diario = p.journal.length; counts.documenti = p.files.length; counts.pratiche = p.permits.length;
      counts.ore = p.hours.total ? D.hours(p.hours.total) : 0;
      show(current);
    };

    function show(name) {
      current = name;
      history.replaceState(null, '', `#/progetti/${id}${name === 'riepilogo' ? '' : `/${name}`}`);
      bar.replaceChildren(...tabs.map(([k, label]) => h('button', {
        type: 'button', role: 'tab', class: k === name ? 'on' : '', 'aria-selected': String(k === name), onclick: () => show(k),
      }, label, counts[k] ? h('span', { class: 'tab-count' }, counts[k]) : null)));
      body.replaceChildren(TAB_VIEWS[name](p, id, reload));
    }

    page.append(
      h('div', { class: 'project-head' },
        h('div', {},
          h('div', {}, D.statusBadge(p.status)),
          h('h2', {}, p.title),
          h('div', { class: 'sub' }, [p.client_name, [p.address, p.city].filter(Boolean).join(', ')].filter(Boolean).join(' · '))),
        h('div', { class: 'head-actions' },
          h('button', { class: 'btn', onclick: () => window.print(), title: 'Stampa la scheda del progetto' }, 'Stampa'),
          h('button', { class: 'btn', onclick: () => projectModal(p) }, 'Modifica'),
          h('button', { class: 'btn danger', onclick: async (e) => {
            if (!D.confirm(`Eliminare definitivamente il progetto ${p.code} con fasi, scadenze, note, documenti e pratiche?`)) return;
            if (await D.run(e.currentTarget, () => D.del(`projects/${id}`), 'Progetto eliminato') !== undefined) { await D.refreshOverdue(); D.go('/progetti'); }
          } }, 'Elimina'))),
      bar,
      body);
    show(current);
    return { title: p.code, back: ['/progetti', 'Progetti'], nav: 'progetti' };
  });

  const facts = (rows) => {
    const list = rows.filter(([, v]) => v !== null && v !== undefined && v !== '');
    return list.length ? h('dl', { class: 'facts' }, list.flatMap(([k, v]) => [h('dt', {}, k), h('dd', {}, v)])) : h('p', { class: 'muted' }, 'Nessun dato.');
  };

  const TAB_VIEWS = {
    // ----- Riepilogo: fasi, dati, scadenze -----
    riepilogo(p, id, reload) {
      const activePhases = p.phases.filter((f) => f.status !== 'non_prevista');
      const done = activePhases.filter((f) => f.status === 'completata').length;
      const cycle = { da_fare: 'in_corso', in_corso: 'completata', completata: 'da_fare', non_prevista: 'da_fare' };
      const phaseList = h('ol', { class: 'phases' }, p.phases.map((f, i) => {
        const sel = D.select('status', D.PHASE_STATUS, f.status, { 'aria-label': `Stato di ${f.name}` });
        sel.addEventListener('change', async () => {
          if (await D.run(sel, () => D.put(`phases/${f.id}`, { status: sel.value })) !== undefined) reload();
        });
        return h('li', { class: `phase ${f.status}` },
          h('button', { class: 'dot', type: 'button', title: 'Cambia stato', 'aria-label': `${f.name}: ${D.PHASE_STATUS[f.status]}. Cambia stato`, onclick: async (e) => {
            if (await D.run(e.currentTarget, () => D.put(`phases/${f.id}`, { status: cycle[f.status] })) !== undefined) reload();
          } }, f.status === 'completata' ? '✓' : i + 1),
          h('div', {}, h('div', { class: 'name' }, f.name),
            f.completed_at ? h('div', { class: 'when' }, `completata il ${D.date(f.completed_at)}`) : null),
          sel);
      }));
      const addPhase = h('form', { class: 'inline-form no-print', onsubmit: async (e) => {
        e.preventDefault();
        if (await D.submit(addPhase, (d) => D.post(`projects/${id}/phases`, d), 'Fase aggiunta')) reload();
      } }, h('input', { name: 'name', type: 'text', placeholder: 'Aggiungi una fase…', required: true, 'aria-label': 'Nome fase' }), h('button', { class: 'btn', type: 'submit' }, 'Aggiungi'));

      const lastPermit = p.permits[0];
      return h('div', { class: 'grid-2' },
        h('div', {},
          card(`Fasi · ${done} di ${activePhases.length}`, progress(done, activePhases.length), phaseList, addPhase),
          p.notes ? card('Note', null, h('div', { class: 'entry-text' }, p.notes)) : null),
        h('div', {},
          card('Dati', null, facts([
            ['Codice', h('span', { class: 'code' }, p.code)],
            ['Cliente', p.client_id ? h('a', { href: `#/clienti/${p.client_id}` }, p.client_name) : null],
            ['Telefono', p.client_phone ? h('a', { href: `tel:${p.client_phone}` }, p.client_phone) : null],
            ['Email', p.client_email ? h('a', { href: `mailto:${p.client_email}` }, p.client_email) : null],
            ['Indirizzo', [p.address, p.city].filter(Boolean).join(', ') || null],
            ['Catasto', p.cadastral],
            ['Intervento', p.intervention],
            ['Inizio', p.start_date ? D.date(p.start_date) : null],
            ['Consegna', p.due_date ? h('span', { class: D.isLate(p.due_date) && !['concluso', 'archiviato'].includes(p.status) ? 'date late' : '' }, D.date(p.due_date)) : null],
            ['Onorario', p.fee ? D.money(p.fee) : null],
            ['Pratica', lastPermit ? `${lastPermit.kind} · ${D.PERMIT_STATUS[lastPermit.status]}` : null],
            ['Ore', p.hours.total ? D.hours(p.hours.total) : null],
          ])),
          card('Scadenze', null,
            p.tasks.length ? h('ul', { class: 'list' }, p.tasks.map((t) => taskRow(t, { showProject: false, onChange: reload }))) : h('p', { class: 'muted small' }, 'Nessuna scadenza.'),
            taskForm(id, reload))));
    },

    // ----- Diario di commessa -----
    diario(p, id, reload) {
      const form = h('form', { class: 'form', onsubmit: async (e) => {
        e.preventDefault();
        if (await D.submit(form, (d) => D.post('journal', { ...d, project_id: id }), 'Nota salvata')) reload();
      } },
      D.field('Nuova nota', D.textarea('text', '', { rows: 3, required: true, placeholder: 'Cosa è successo? Sopralluogo, telefonata, decisioni prese…' })),
      h('div', { class: 'inline-form', style: 'margin:0' },
        D.select('kind', D.JOURNAL_KIND, 'nota', { 'aria-label': 'Tipo' }),
        h('input', { name: 'entry_date', type: 'date', value: D.today(), 'aria-label': 'Data' }),
        h('div', { style: 'flex:1' }),
        h('button', { class: 'btn primary', type: 'submit' }, 'Salva nota')));
      return h('div', { class: 'narrow' }, card('Diario di commessa', null, form,
        p.journal.length
          ? h('div', { class: 'timeline' }, p.journal.map((j) => h('div', { class: 'entry' },
            h('div', { class: 'entry-head' },
              h('b', {}, D.JOURNAL_KIND[j.kind] || j.kind), h('span', {}, D.date(j.entry_date)), j.user_name ? h('span', {}, `· ${j.user_name}`) : null,
              h('button', { class: 'btn ghost small x', type: 'button', 'aria-label': 'Elimina nota', onclick: async (e) => {
                if (D.confirm('Eliminare questa nota?') && await D.run(e.currentTarget, () => D.del(`journal/${j.id}`)) !== undefined) reload();
              } }, '✕')),
            h('div', { class: 'entry-text' }, j.text))))
          : h('p', { class: 'muted small mt' }, 'Nessuna nota ancora.')));
    },

    // ----- Documenti -----
    documenti(p, id, reload) {
      let filter = 'tutti';
      const grid = h('div', { class: 'files' });
      const catSel = D.select('category', D.FILE_CATEGORY, 'documento', { 'aria-label': 'Categoria' });
      const picker = h('input', { type: 'file', multiple: true, hidden: true, onchange: () => send(picker.files) });
      const status = h('span', { class: 'muted small' });

      async function send(fileList) {
        const files = [...fileList];
        if (!files.length) return;
        const fd = new FormData();
        files.forEach((f) => fd.append('files[]', f, f.name));
        fd.append('category', catSel.value);
        status.textContent = `Caricamento di ${files.length} file…`;
        drop.classList.add('busy');
        const out = await D.run(null, () => D.upload(`projects/${id}/files`, fd), `${files.length} file caricat${files.length === 1 ? 'o' : 'i'}`);
        drop.classList.remove('busy');
        status.textContent = '';
        picker.value = '';
        if (out) reload();
      }

      const drop = h('div', { class: 'dropzone',
        ondragover: (e) => { e.preventDefault(); drop.classList.add('over'); },
        ondragleave: () => drop.classList.remove('over'),
        ondrop: (e) => { e.preventDefault(); drop.classList.remove('over'); send(e.dataTransfer.files); } },
      h('div', { class: 'dz-title' }, 'Trascina qui tavole, foto e documenti'),
      h('div', { class: 'dz-actions' },
        h('button', { class: 'btn primary', type: 'button', onclick: () => picker.click() }, 'Scegli file'),
        catSel, status),
      h('div', { class: 'muted small' }, `PDF, immagini, DWG/DXF, IFC, documenti Office, ZIP… · massimo ${D.phpSize(D.limits?.max_file)} per file`),
      picker);

      const chips = h('div', { class: 'tabs' });
      function draw() {
        const present = Object.keys(D.FILE_CATEGORY).filter((c) => p.files.some((f) => f.category === c));
        chips.replaceChildren(...['tutti', ...present].map((c) => h('button', { type: 'button', class: filter === c ? 'on' : '', onclick: () => { filter = c; draw(); } },
          c === 'tutti' ? `Tutti (${p.files.length})` : `${D.FILE_CATEGORY[c]} (${p.files.filter((f) => f.category === c).length})`)));
        chips.hidden = p.files.length === 0;
        const list = p.files.filter((f) => filter === 'tutti' || f.category === filter);
        grid.replaceChildren(...(list.length ? list.map(fileCard) : [h('p', { class: 'muted small' }, p.files.length ? 'Nessun file in questa categoria.' : 'Ancora nessun documento.')]));
      }

      function fileCard(f) {
        const ext = (f.name.split('.').pop() || '').toUpperCase();
        const url = `api.php?r=files/${f.id}`;
        const sel = D.select('category', D.FILE_CATEGORY, f.category, { 'aria-label': 'Categoria', class: 'mini' });
        sel.addEventListener('change', async () => { if (await D.run(sel, () => D.put(`files/${f.id}`, { category: sel.value })) !== undefined) reload(); });
        return h('figure', { class: 'file' },
          h('a', { class: 'file-thumb', href: `${url}&inline=1`, target: '_blank', rel: 'noopener', title: 'Apri' },
            f.preview ? h('img', { src: `${url}&inline=1`, alt: f.name, loading: 'lazy' }) : h('span', { class: 'file-ext' }, ext)),
          h('figcaption', {},
            h('div', { class: 'file-name', title: f.name }, f.name),
            h('div', { class: 'item-meta' }, `${D.size(f.size)} · ${D.date(f.created_at)}${f.user_name ? ` · ${f.user_name}` : ''}`),
            h('div', { class: 'file-actions' },
              sel,
              h('a', { class: 'btn small', href: url, download: '' }, 'Scarica'),
              h('button', { class: 'btn ghost small', type: 'button', 'aria-label': `Elimina ${f.name}`, onclick: async (e) => {
                if (D.confirm(`Eliminare "${f.name}"?`) && await D.run(e.currentTarget, () => D.del(`files/${f.id}`), 'File eliminato') !== undefined) reload();
              } }, '✕'))));
      }

      draw();
      return h('div', {}, h('div', { class: 'card' }, drop), h('div', { class: 'toolbar mt' }, chips), grid);
    },

    // ----- Pratiche edilizie -----
    pratiche(p, id, reload) {
      const add = h('button', { class: 'btn primary', onclick: () => permitModal(id, null, reload) }, '+ Nuova pratica');
      if (!p.permits.length) {
        return h('div', { class: 'card' }, empty('Nessuna pratica per questo progetto: CILA, SCIA, Permesso di costruire, paesaggistica…', add));
      }
      return h('div', {},
        h('div', { class: 'toolbar' }, h('div', { style: 'flex:1' }), add),
        h('div', { class: 'permits' }, p.permits.map((pe) => h('article', { class: `card permit ${pe.status}` },
          h('div', { class: 'card-head' },
            h('div', {}, h('h3', {}, pe.kind), pe.authority ? h('div', { class: 'item-meta' }, pe.authority) : null),
            h('span', { class: `badge permit-${pe.status}` }, D.PERMIT_STATUS[pe.status])),
          facts([
            ['Protocollo', pe.protocol ? h('span', { class: 'code' }, pe.protocol) : null],
            ['Depositata', pe.filed_at ? D.date(pe.filed_at) : null],
            ['Esito', pe.approved_at ? D.date(pe.approved_at) : null],
            ['Scadenza', pe.expires_at ? h('span', { class: D.isLate(pe.expires_at) ? 'date late' : '' }, D.date(pe.expires_at)) : null],
          ]),
          pe.notes ? h('p', { class: 'entry-text small mt' }, pe.notes) : null,
          h('div', { class: 'form-actions mt no-print' },
            h('button', { class: 'btn ghost small', onclick: async (e) => {
              if (D.confirm(`Eliminare la pratica ${pe.kind}?`) && await D.run(e.currentTarget, () => D.del(`permits/${pe.id}`), 'Pratica eliminata') !== undefined) reload();
            } }, 'Elimina'),
            h('button', { class: 'btn small', onclick: () => permitModal(id, pe, reload) }, 'Modifica'))))));
    },

    // ----- Economia (solo amministratore) -----
    economia(p, id, reload) {
      const { items, summary: s } = p.payments;
      const pct = (v) => (s.fee ? Math.min(100, Math.round((v / s.fee) * 100)) : 0);
      const stat = (label, value, hint) => h('div', { class: 'stat small' }, h('div', { class: 'n' }, D.money(value)), h('div', { class: 'l' }, label), hint ? h('div', { class: 'item-meta' }, hint) : null);
      const add = h('button', { class: 'btn primary', onclick: () => paymentModal(id, null, reload) }, '+ Nuova rata');

      const rows = items.map((r) => {
        const state = r.paid_at ? ['Incassata', 'concluso'] : r.invoiced_at ? ['Fatturata', 'preventivo'] : ['Da fatturare', 'sospeso'];
        const late = !r.invoiced_at && D.isLate(r.due_date);
        return h('tr', { onclick: () => paymentModal(id, r, reload) },
          h('td', {}, h('div', { class: 'item-title' }, r.description), r.invoice_no ? h('div', { class: 'item-meta' }, `Fattura ${r.invoice_no}`) : null),
          h('td', { class: 'num' }, D.money(r.amount)),
          h('td', { class: 'hide-sm' }, r.due_date ? h('span', { class: `date${late ? ' late' : ''}` }, D.date(r.due_date)) : '—'),
          h('td', {}, h('span', { class: `badge ${state[1]}` }, state[0])),
          h('td', { class: 'no-print', onclick: (e) => e.stopPropagation() },
            r.paid_at ? null : h('button', { class: 'btn small', onclick: async (e) => {
              const patch = r.invoiced_at ? { paid_at: D.today() } : { invoiced_at: D.today() };
              if (await D.run(e.currentTarget, () => D.put(`payments/${r.id}`, patch), r.invoiced_at ? 'Segnata come incassata' : 'Segnata come fatturata') !== undefined) reload();
            } }, r.invoiced_at ? 'Incassata' : 'Fatturata')));
      });

      return h('div', {},
        h('div', { class: 'stats five' },
          stat('Onorario', s.fee, s.fee ? null : 'Da indicare in Modifica'),
          stat('Pianificato', s.planned, s.unplanned ? `${D.money(s.unplanned)} ancora da ripartire` : null),
          stat('Fatturato', s.invoiced, s.fee ? `${pct(s.invoiced)}% dell'onorario` : null),
          stat('Incassato', s.paid, s.fee ? `${pct(s.paid)}% dell'onorario` : null),
          stat('Da incassare', s.to_collect)),
        s.fee ? h('div', { class: 'money-bar', title: 'Incassato / fatturato / onorario' },
          h('i', { class: 'paid', style: `width:${pct(s.paid)}%` }), h('i', { class: 'inv', style: `width:${pct(s.invoiced - s.paid)}%` })) : null,
        h('div', { class: 'toolbar mt' }, h('h3', { class: 'section-title', style: 'margin:0;flex:1' }, 'Rate e fatture'), add),
        items.length
          ? h('div', { class: 'card table-wrap', style: 'padding:14px 8px 6px' }, h('table', {},
            h('thead', {}, h('tr', {}, h('th', {}, 'Voce'), h('th', { class: 'num' }, 'Importo'), h('th', { class: 'hide-sm' }, 'Prevista'), h('th', {}, 'Stato'), h('th', { class: 'no-print' }, ''))),
            h('tbody', {}, rows)))
          : h('div', { class: 'card' }, empty('Dividi l\'onorario in rate (acconto, stati di avanzamento, saldo) per tenere traccia di fatture e incassi.')));
    },

    // ----- Ore lavorate -----
    ore(p, id, reload) {
      const form = h('form', { class: 'inline-form', onsubmit: async (e) => {
        e.preventDefault();
        if (await D.submit(form, (d) => D.post('hours', { ...d, project_id: id }), 'Ore registrate')) reload();
      } },
      h('input', { name: 'work_date', type: 'date', value: D.today(), 'aria-label': 'Data' }),
      h('input', { name: 'hours', type: 'text', inputmode: 'decimal', placeholder: 'Ore (es. 1,5 o 1:30)', required: true, 'aria-label': 'Ore', style: 'max-width:170px' }),
      h('input', { name: 'description', type: 'text', placeholder: 'Su cosa hai lavorato?', 'aria-label': 'Descrizione' }),
      h('button', { class: 'btn primary', type: 'submit' }, 'Registra'));
      const { items, total, by_user: byUser } = p.hours;
      return h('div', { class: 'grid-2' },
        h('div', {}, card('Registro ore', null, form,
          items.length
            ? h('ul', { class: 'list mt' }, items.map((r) => h('li', { class: 'item' },
              h('span', { class: 'date', style: 'width:92px' }, D.date(r.work_date)),
              h('div', { class: 'item-main' }, h('div', { class: 'item-title' }, r.description || h('span', { class: 'muted' }, '—')), h('div', { class: 'item-meta' }, r.user_name || '')),
              h('b', { class: 'num' }, D.hours(r.hours)),
              (D.isAdmin() || r.user_id === D.user.id) ? h('button', { class: 'btn ghost small', 'aria-label': 'Elimina', onclick: async (e) => {
                if (D.confirm('Eliminare questa registrazione?') && await D.run(e.currentTarget, () => D.del(`hours/${r.id}`)) !== undefined) reload();
              } }, '✕') : null)))
            : h('p', { class: 'muted small mt' }, 'Nessuna ora registrata.'))),
        h('div', {}, card('Totale', null,
          h('div', { class: 'big-number' }, D.hours(total)),
          byUser.length ? h('ul', { class: 'list mt' }, byUser.map((u) => h('li', { class: 'item' }, h('div', { class: 'item-main' }, u.user_name), h('b', { class: 'num' }, D.hours(u.hours))))) : null,
          D.isAdmin() && p.fee && total ? h('p', { class: 'item-meta mt' }, `Onorario per ora lavorata: ${D.money(p.fee / total)}`) : null)));
    },
  };

  async function permitModal(projectId, existing, reload) {
    const pe = existing || {};
    const meta = await D.get('meta');
    const form = h('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      const out = await D.submit(form, (d) => (existing ? D.put(`permits/${pe.id}`, d) : D.post('permits', { ...d, project_id: projectId })), 'Pratica salvata');
      if (out) { close(); reload(); }
    } },
    h('div', { class: 'row' },
      D.field('Tipo di pratica', D.select('kind', Object.fromEntries(meta.permit_kinds.map((k) => [k, k])), pe.kind || 'CILA'), { required: true }),
      D.field('Stato', D.select('status', D.PERMIT_STATUS, pe.status || 'in_preparazione'))),
    h('div', { class: 'row' },
      D.field('Ente', D.input('authority', pe.authority, { placeholder: 'es. Comune di Firenze — SUE' })),
      D.field('Numero di protocollo', D.input('protocol', pe.protocol))),
    h('div', { class: 'row' },
      D.field('Data di deposito', D.input('filed_at', pe.filed_at, { type: 'date' })),
      D.field('Data dell\'esito', D.input('approved_at', pe.approved_at, { type: 'date' })),
      D.field('Scadenza', D.input('expires_at', pe.expires_at, { type: 'date' }), { hint: 'es. fine lavori, validità del titolo' })),
    D.field('Note', D.textarea('notes', pe.notes, { rows: 3 })),
    h('div', { class: 'form-actions' },
      h('button', { class: 'btn ghost', type: 'button', onclick: () => close() }, 'Annulla'),
      h('button', { class: 'btn primary', type: 'submit' }, existing ? 'Salva' : 'Aggiungi pratica')));
    const close = D.modal(existing ? 'Modifica pratica' : 'Nuova pratica', form);
  }

  function paymentModal(projectId, existing, reload) {
    const r = existing || {};
    const form = h('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      const out = await D.submit(form, (d) => (existing ? D.put(`payments/${r.id}`, d) : D.post('payments', { ...d, project_id: projectId })), 'Rata salvata');
      if (out) { close(); reload(); }
    } },
    h('div', { class: 'row' },
      D.field('Voce', D.input('description', r.description, { required: true, placeholder: 'es. Acconto 30% alla firma', list: 'pay-voices' }), { required: true }),
      D.field('Importo (€)', D.input('amount', r.amount ?? '', { required: true, inputmode: 'decimal', placeholder: 'es. 3.500' }), { required: true })),
    h('datalist', { id: 'pay-voices' }, ['Acconto alla firma', 'Progetto preliminare', 'Progetto definitivo', 'Deposito pratica', 'Stato avanzamento lavori', 'Saldo a fine lavori'].map((v) => h('option', { value: v }))),
    h('div', { class: 'row' },
      D.field('Data prevista', D.input('due_date', r.due_date, { type: 'date' })),
      D.field('Numero fattura', D.input('invoice_no', r.invoice_no))),
    h('div', { class: 'row' },
      D.field('Fatturata il', D.input('invoiced_at', r.invoiced_at, { type: 'date' })),
      D.field('Incassata il', D.input('paid_at', r.paid_at, { type: 'date' }))),
    D.field('Note', D.textarea('notes', r.notes, { rows: 2 })),
    h('div', { class: 'form-actions' },
      existing ? h('button', { class: 'btn danger', type: 'button', style: 'margin-right:auto', onclick: async (e) => {
        if (D.confirm('Eliminare questa rata?') && await D.run(e.currentTarget, () => D.del(`payments/${r.id}`), 'Rata eliminata') !== undefined) { close(); reload(); }
      } }, 'Elimina') : null,
      h('button', { class: 'btn ghost', type: 'button', onclick: () => close() }, 'Annulla'),
      h('button', { class: 'btn primary', type: 'submit' }, existing ? 'Salva' : 'Aggiungi')));
    const close = D.modal(existing ? 'Modifica rata' : 'Nuova rata', form);
  }

  // ---------- Clienti ----------

  D.route('/clienti', async (page) => {
    let q = '';
    const wrap = h('div');
    async function load() {
      const list = await D.get(`clients&q=${encodeURIComponent(q)}`);
      wrap.replaceChildren(list.length
        ? h('div', { class: 'card' }, h('ul', { class: 'list' }, list.map((c) => h('li', {}, h('a', { class: 'item', href: `#/clienti/${c.id}` },
          h('div', { class: 'item-main' },
            h('div', { class: 'item-title' }, c.name),
            h('div', { class: 'item-meta' }, [D.CLIENT_KIND[c.kind], c.city, c.phone, c.email].filter(Boolean).join(' · '))),
          h('span', { class: 'item-meta' }, `${c.projects_count} progett${Number(c.projects_count) === 1 ? 'o' : 'i'}`))))))
        : h('div', { class: 'card' }, empty(q ? 'Nessun cliente trovato.' : 'Ancora nessun cliente.', q ? null : h('button', { class: 'btn primary', onclick: () => clientModal() }, 'Aggiungi il primo cliente'))));
    }
    let timer;
    page.append(
      h('div', { class: 'toolbar' }, h('input', { class: 'search', type: 'search', placeholder: 'Cerca per nome, comune, codice fiscale…', 'aria-label': 'Cerca', oninput: (e) => { clearTimeout(timer); q = e.target.value.trim(); timer = setTimeout(load, 200); } })),
      wrap);
    await load();
    return { title: 'Clienti', nav: 'clienti', actions: h('button', { class: 'btn primary', onclick: () => clientModal() }, '+ Nuovo cliente') };
  });

  function clientModal(existing) {
    const c = existing || {};
    const form = h('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      const out = await D.submit(form, (d) => (existing ? D.put(`clients/${c.id}`, d) : D.post('clients', d)), existing ? 'Cliente salvato' : 'Cliente aggiunto');
      if (!out) return;
      close();
      if (existing) D.render(); else D.go(`/clienti/${out.id}`);
    } },
    h('div', { class: 'row' },
      D.field('Nome e cognome / Ragione sociale', D.input('name', c.name, { required: true }), { required: true }),
      D.field('Tipo', D.select('kind', D.CLIENT_KIND, c.kind || 'privato'))),
    h('div', { class: 'row' },
      D.field('Codice fiscale', D.input('tax_code', c.tax_code, { maxlength: 16, autocapitalize: 'characters' })),
      D.field('Partita IVA', D.input('vat', c.vat, { maxlength: 20 }))),
    h('div', { class: 'row' },
      D.field('Telefono', D.input('phone', c.phone, { type: 'tel' })),
      D.field('Email', D.input('email', c.email, { type: 'email' }))),
    h('div', { class: 'row' },
      D.field('Indirizzo', D.input('address', c.address)),
      D.field('Comune', D.input('city', c.city))),
    D.field('Note', D.textarea('notes', c.notes, { rows: 3 })),
    h('div', { class: 'form-actions' },
      h('button', { class: 'btn ghost', type: 'button', onclick: () => close() }, 'Annulla'),
      h('button', { class: 'btn primary', type: 'submit' }, existing ? 'Salva' : 'Aggiungi cliente')));
    const close = D.modal(existing ? 'Modifica cliente' : 'Nuovo cliente', form);
  }

  D.route('/clienti/(\\d+)', async (page, id) => {
    const c = await D.get(`clients/${id}`);
    const facts = [
      ['Tipo', D.CLIENT_KIND[c.kind]],
      ['Telefono', c.phone ? h('a', { href: `tel:${c.phone}` }, c.phone) : null],
      ['Email', c.email ? h('a', { href: `mailto:${c.email}` }, c.email) : null],
      ['Indirizzo', [c.address, c.city].filter(Boolean).join(', ') || null],
      ['Codice fiscale', c.tax_code ? h('span', { class: 'code' }, c.tax_code) : null],
      ['Partita IVA', c.vat ? h('span', { class: 'code' }, c.vat) : null],
    ].filter(([, v]) => v);

    page.append(
      h('div', { class: 'project-head' },
        h('div', {}, h('h2', {}, c.name), h('div', { class: 'sub' }, [D.CLIENT_KIND[c.kind], c.city].filter(Boolean).join(' · '))),
        h('div', { style: 'display:flex;gap:8px' },
          h('button', { class: 'btn', onclick: () => clientModal(c) }, 'Modifica'),
          h('button', { class: 'btn danger', onclick: async (e) => {
            const msg = c.projects.length
              ? `Eliminare il cliente "${c.name}"? I suoi ${c.projects.length} progetti restano, senza cliente collegato.`
              : `Eliminare il cliente "${c.name}"?`;
            if (D.confirm(msg) && await D.run(e.currentTarget, () => D.del(`clients/${id}`), 'Cliente eliminato') !== undefined) D.go('/clienti');
          } }, 'Elimina'))),
      h('div', { class: 'grid-2' },
        h('div', {}, card('Progetti', h('button', { class: 'btn small', onclick: () => projectModal(null, { client_id: c.id }) }, '+ Nuovo'),
          c.projects.length
            ? h('ul', { class: 'list' }, c.projects.map((p) => h('li', {}, h('a', { class: 'item', href: `#/progetti/${p.id}` },
              h('div', { class: 'item-main' }, h('div', { class: 'item-title' }, p.title), h('div', { class: 'item-meta' }, h('span', { class: 'code' }, p.code), p.city ? ` · ${p.city}` : '')),
              D.statusBadge(p.status)))))
            : h('p', { class: 'muted', style: 'margin:0;font-size:14px' }, 'Nessun progetto per questo cliente.'))),
        h('div', {},
          card('Contatti e dati', null, facts.length ? h('dl', { class: 'facts' }, facts.flatMap(([k, v]) => [h('dt', {}, k), h('dd', {}, v)])) : h('p', { class: 'muted' }, 'Nessun dato.')),
          c.notes ? card('Note', null, h('div', { class: 'entry-text' }, c.notes)) : null)));
    return { title: c.name, back: ['/clienti', 'Clienti'], nav: 'clienti' };
  });

  // ---------- Scadenze ----------

  D.route('/scadenze', async (page) => {
    let filter = 'aperte';
    const list = h('div');
    const tabs = h('div', { class: 'tabs', role: 'tablist' });
    const TABS = { aperte: 'Da fare', scadute: 'Superate', settimana: 'Entro 7 giorni', fatte: 'Fatte' };
    async function load() {
      tabs.replaceChildren(...Object.entries(TABS).map(([k, l]) => h('button', { type: 'button', class: filter === k ? 'on' : '', role: 'tab', 'aria-selected': String(filter === k), onclick: () => { filter = k; load(); } }, l)));
      const tasks = await D.get(`tasks&filter=${filter}`);
      list.replaceChildren(h('div', { class: 'card' },
        tasks.length ? h('ul', { class: 'list' }, tasks.map((t) => taskRow(t, { onChange: load }))) : empty(filter === 'fatte' ? 'Nessuna scadenza completata.' : 'Niente da fare qui. 👌'),
        filter === 'fatte' ? null : taskForm(null, load)));
    }
    page.append(h('div', { class: 'toolbar' }, tabs), list);
    await load();
    return { title: 'Scadenze', nav: 'scadenze' };
  });

  // ---------- Calendario ----------

  const EVENT_LABEL = { task: 'Scadenza', phase: 'Fase', delivery: 'Consegna', permit: 'Pratica', payment: 'Rata' };
  const WEEKDAYS = ['lun', 'mar', 'mer', 'gio', 'ven', 'sab', 'dom'];
  const MONTH_NAMES = ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
  const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

  D.route('/calendario(?:/(\\d{4})-(\\d{2}))?', async (page, y, m) => {
    const now = new Date();
    const year = y ? Number(y) : now.getFullYear();
    const month = m ? Number(m) - 1 : now.getMonth();
    const first = new Date(year, month, 1);
    const last = new Date(year, month + 1, 0);
    // La griglia parte dal lunedì della prima settimana e finisce la domenica dell'ultima.
    const start = new Date(first); start.setDate(1 - ((first.getDay() + 6) % 7));
    const end = new Date(last); end.setDate(last.getDate() + (6 - ((last.getDay() + 6) % 7)));
    const events = await D.get(`calendar&from=${iso(start)}&to=${iso(end)}`);
    const byDay = {};
    events.forEach((e) => { (byDay[e.date] ||= []).push(e); });

    const shift = (n) => { const d = new Date(year, month + n, 1); return `/calendario/${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`; };
    const chip = (e) => h('a', { class: `ev ev-${e.type}${e.done ? ' done' : ''}`, href: e.project_id ? `#/progetti/${e.project_id}` : '#/scadenze', title: `${EVENT_LABEL[e.type]}: ${e.title}${e.code ? ` (${e.code})` : ''}` },
      e.code ? h('span', { class: 'ev-code' }, e.code.slice(5)) : null, e.title, e.amount ? ` · ${D.money(e.amount)}` : '');

    const cells = [];
    for (const d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
      const key = iso(d);
      const list = byDay[key] || [];
      cells.push(h('div', { class: `day${d.getMonth() !== month ? ' out' : ''}${key === D.today() ? ' today' : ''}` },
        h('div', { class: 'day-n' }, d.getDate()),
        list.slice(0, 4).map(chip),
        list.length > 4 ? h('div', { class: 'item-meta' }, `+ altri ${list.length - 4}`) : null));
    }

    // Elenco per giorno: comodo sul telefono e per la stampa.
    const monthEvents = events.filter((e) => e.date >= iso(first) && e.date <= iso(last));
    const agenda = monthEvents.length
      ? h('div', { class: 'agenda' }, Object.entries(monthEvents.reduce((acc, e) => { (acc[e.date] ||= []).push(e); return acc; }, {})).map(([day, list]) =>
        h('div', { class: 'agenda-day' }, h('div', { class: `date${day < D.today() ? '' : ''}` }, D.relDate(day)), h('div', {}, list.map(chip)))))
      : h('p', { class: 'muted small' }, 'Nessun evento in questo mese.');

    page.append(
      h('div', { class: 'toolbar' },
        h('div', { class: 'cal-nav' },
          h('a', { class: 'btn', href: `#${shift(-1)}`, 'aria-label': 'Mese precedente' }, '←'),
          h('a', { class: 'btn', href: '#/calendario' }, 'Oggi'),
          h('a', { class: 'btn', href: `#${shift(1)}`, 'aria-label': 'Mese successivo' }, '→')),
        h('h2', { class: 'cal-title' }, `${MONTH_NAMES[month]} ${year}`),
        h('div', { class: 'legend' }, Object.entries(EVENT_LABEL).filter(([k]) => k !== 'payment' || D.isAdmin()).map(([k, l]) => h('span', { class: `ev ev-${k}` }, l)))),
      h('div', { class: 'card calendar' },
        h('div', { class: 'cal-grid cal-head' }, WEEKDAYS.map((w) => h('div', {}, w))),
        h('div', { class: 'cal-grid' }, cells)),
      h('section', { class: 'card agenda-card' }, h('div', { class: 'card-head' }, h('h2', {}, 'Elenco del mese')), agenda));
    return { title: 'Calendario', nav: 'calendario' };
  });

  // ---------- Impostazioni ----------

  D.route('/impostazioni', async (page) => {
    const admin = D.isAdmin();
    const s = await D.get('settings');

    const profile = h('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      const out = await D.submit(profile, (d) => D.put('auth/profile', d), 'Profilo aggiornato');
      if (out) { D.user = out.user; profile.reset(); D.render(); }
    } },
    D.field('Il tuo nome', D.input('name', D.user.name, { required: true })),
    h('div', { class: 'row' },
      D.field('Password attuale', D.input('current_password', '', { type: 'password', autocomplete: 'current-password' })),
      D.field('Nuova password', D.input('new_password', '', { type: 'password', autocomplete: 'new-password', minlength: 10 }), { hint: 'Lascia vuoto per non cambiarla' })),
    h('div', { class: 'form-actions' }, h('button', { class: 'btn primary', type: 'submit' }, 'Aggiorna profilo')));

    if (!admin) {
      page.append(h('div', { class: 'narrow' }, card('Profilo', null, h('p', { class: 'item-meta', style: 'margin:-6px 0 14px' }, `${D.user.email} · ${D.ROLE[D.user.role]}`), profile)));
      return { title: 'Impostazioni', nav: 'impostazioni' };
    }

    const studio = h('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      const out = await D.submit(studio, (d) => D.put('settings', d), 'Impostazioni salvate');
      if (out) { D.studioName = out.studio_name; D.render(); }
    } },
    D.field('Nome dello studio', D.input('studio_name', s.studio_name)),
    D.field('Fasi standard di un nuovo progetto', D.textarea('default_phases', s.default_phases, { rows: 9 }), { hint: 'Una fase per riga. Vale per i progetti creati da ora in poi.' }),
    h('div', { class: 'form-actions' }, h('button', { class: 'btn primary', type: 'submit' }, 'Salva')));

    const users = await D.get('users');
    const team = h('ul', { class: 'list' }, users.map((u) => h('li', { class: `item${Number(u.active) ? '' : ' inactive'}` },
      h('div', { class: 'avatar', 'aria-hidden': 'true' }, u.name.split(' ').map((x) => x[0]).slice(0, 2).join('').toUpperCase()),
      h('div', { class: 'item-main' },
        h('div', { class: 'item-title' }, u.name, u.id === D.user.id ? h('span', { class: 'muted' }, ' (tu)') : null),
        h('div', { class: 'item-meta' }, `${u.email} · ${D.ROLE[u.role]}${Number(u.active) ? '' : ' · disattivato'}${Number(u.hours_month) ? ` · ${D.hours(u.hours_month)} questo mese` : ''}`)),
      h('button', { class: 'btn small', onclick: () => userModal(u) }, 'Modifica'))));

    page.append(h('div', { class: 'grid-2' },
      h('div', {},
        card('Studio', null, studio),
        card('Collaboratori', h('button', { class: 'btn small', onclick: () => userModal(null) }, '+ Aggiungi'),
          h('p', { class: 'item-meta', style: 'margin:-6px 0 8px' }, 'I collaboratori vedono progetti, clienti, documenti e scadenze. Non vedono l\'economia e le impostazioni.'),
          team)),
      h('div', {},
        card('Profilo', null, h('p', { class: 'item-meta', style: 'margin:-6px 0 14px' }, D.user.email), profile),
        card('Backup', null,
          h('p', { class: 'small', style: 'margin:0 0 12px;color:var(--ink-2)' }, 'Scarica tutti i dati in un file JSON leggibile. I documenti caricati stanno nella cartella domus-data/files: per un backup completo copia l\'intera cartella domus-data.'),
          h('a', { class: 'btn', href: 'api.php?r=export', download: '' }, 'Scarica backup JSON')))));
    return { title: 'Impostazioni', nav: 'impostazioni' };
  });

  function userModal(existing) {
    const u = existing || {};
    const form = h('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      const out = await D.submit(form, (d) => {
        if (existing) {
          const body = { name: d.name, role: d.role, active: d.active === '1' };
          if (d.password) body.password = d.password;
          return D.put(`users/${u.id}`, body);
        }
        return D.post('users', d);
      }, existing ? 'Utente aggiornato' : 'Collaboratore aggiunto');
      if (out) { close(); D.render(); }
    } },
    h('div', { class: 'row' },
      D.field('Nome e cognome', D.input('name', u.name, { required: true }), { required: true }),
      existing ? D.field('Email', D.input('email_ro', u.email, { disabled: true })) : D.field('Email', D.input('email', '', { type: 'email', required: true }), { required: true })),
    h('div', { class: 'row' },
      D.field('Ruolo', D.select('role', D.ROLE, u.role || 'collaboratore')),
      existing ? D.field('Stato', D.select('active', { 1: 'Attivo', 0: 'Disattivato' }, Number(u.active ?? 1))) : null),
    D.field(existing ? 'Nuova password' : 'Password iniziale', D.input('password', '', { type: 'password', autocomplete: 'new-password', minlength: 10, required: !existing }),
      { required: !existing, hint: existing ? 'Lascia vuoto per non cambiarla' : 'Almeno 10 caratteri: comunicala al collaboratore, potrà cambiarla dal suo profilo' }),
    h('div', { class: 'form-actions' },
      h('button', { class: 'btn ghost', type: 'button', onclick: () => close() }, 'Annulla'),
      h('button', { class: 'btn primary', type: 'submit' }, existing ? 'Salva' : 'Aggiungi')));
    const close = D.modal(existing ? `Modifica ${u.name}` : 'Nuovo collaboratore', form);
  }
})();
