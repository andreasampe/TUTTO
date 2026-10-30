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
    const stat = (n, label, href) => h('a', { class: 'stat', href: `#${href}` }, h('div', { class: 'n' }, n || 0), h('div', { class: 'l' }, label));

    const deadlines = [...d.overdue, ...d.upcoming];
    page.append(
      h('div', { class: 'stats' },
        stat(d.counts.in_corso, 'In corso', '/progetti'),
        stat(d.counts.preventivo, 'Preventivi', '/progetti?preventivo'),
        stat(d.overdue.length, 'Scadenze superate', '/scadenze'),
        stat(d.clients, 'Clienti', '/clienti')),
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
          card('Diario recente', null,
            d.journal.length
              ? h('ul', { class: 'list' }, d.journal.map((j) => h('li', {}, h('a', { class: 'item', href: `#/progetti/${j.project_id}` },
                h('div', { class: 'item-main' },
                  h('div', { class: 'item-meta' }, `${D.date(j.entry_date)} · ${D.JOURNAL_KIND[j.kind] || j.kind} · ${j.project_code}`),
                  h('div', { class: 'item-title', style: 'white-space:nowrap' }, j.text))))))
              : empty('Le note dei sopralluoghi, delle riunioni e delle telefonate compariranno qui.'))),
        h('div', {},
          card('Prossime scadenze', h('a', { class: 'btn ghost small', href: '#/scadenze' }, 'Tutte →'),
            deadlines.length
              ? h('ul', { class: 'list' }, deadlines.map((t) => taskRow(t)))
              : empty('Nessuna scadenza nei prossimi 14 giorni.')))));

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

  D.route('/progetti/(\\d+)', async (page, id) => {
    const p = await D.get(`projects/${id}`);
    const reload = () => D.render();

    const facts = [
      ['Cliente', p.client_id ? h('a', { href: `#/clienti/${p.client_id}` }, p.client_name) : null],
      ['Telefono', p.client_phone ? h('a', { href: `tel:${p.client_phone}` }, p.client_phone) : null],
      ['Email', p.client_email ? h('a', { href: `mailto:${p.client_email}` }, p.client_email) : null],
      ['Indirizzo', [p.address, p.city].filter(Boolean).join(', ') || null],
      ['Catasto', p.cadastral],
      ['Intervento', p.intervention],
      ['Inizio', p.start_date ? D.date(p.start_date) : null],
      ['Consegna', p.due_date ? h('span', { class: D.isLate(p.due_date) && !['concluso', 'archiviato'].includes(p.status) ? 'date late' : '' }, D.date(p.due_date)) : null],
      ['Onorario', D.money(p.fee) || null],
    ].filter(([, v]) => v);

    // Fasi
    const activePhases = p.phases.filter((f) => f.status !== 'non_prevista');
    const done = activePhases.filter((f) => f.status === 'completata').length;
    const phaseList = h('ol', { class: 'phases' }, p.phases.map((f, i) => {
      const sel = D.select('status', D.PHASE_STATUS, f.status, { 'aria-label': `Stato di ${f.name}` });
      sel.addEventListener('change', async () => {
        if (await D.run(sel, () => D.put(`phases/${f.id}`, { status: sel.value })) !== undefined) reload();
      });
      const cycle = { da_fare: 'in_corso', in_corso: 'completata', completata: 'da_fare', non_prevista: 'da_fare' };
      return h('li', { class: `phase ${f.status}` },
        h('button', { class: 'dot', type: 'button', title: 'Cambia stato', 'aria-label': `${f.name}: ${D.PHASE_STATUS[f.status]}. Cambia stato`, onclick: async (e) => {
          if (await D.run(e.currentTarget, () => D.put(`phases/${f.id}`, { status: cycle[f.status] })) !== undefined) reload();
        } }, f.status === 'completata' ? '✓' : i + 1),
        h('div', {}, h('div', { class: 'name' }, f.name),
          f.completed_at ? h('div', { class: 'when' }, `completata il ${D.date(f.completed_at)}`) : null),
        sel);
    }));
    const addPhase = h('form', { class: 'inline-form', onsubmit: async (e) => {
      e.preventDefault();
      if (await D.submit(addPhase, (d) => D.post(`projects/${id}/phases`, d), 'Fase aggiunta')) reload();
    } }, h('input', { name: 'name', type: 'text', placeholder: 'Aggiungi una fase…', required: true, 'aria-label': 'Nome fase' }), h('button', { class: 'btn', type: 'submit' }, 'Aggiungi'));

    // Diario
    const journalForm = h('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      if (await D.submit(journalForm, (d) => D.post('journal', { ...d, project_id: id }), 'Nota salvata')) reload();
    } },
    D.field('Nuova nota', D.textarea('text', '', { rows: 3, required: true, placeholder: 'Cosa è successo? Sopralluogo, telefonata, decisioni prese…' })),
    h('div', { class: 'inline-form', style: 'margin:0' },
      D.select('kind', D.JOURNAL_KIND, 'nota', { 'aria-label': 'Tipo' }),
      h('input', { name: 'entry_date', type: 'date', value: D.today(), 'aria-label': 'Data' }),
      h('div', { style: 'flex:1' }),
      h('button', { class: 'btn primary', type: 'submit' }, 'Salva nota')));

    const journal = p.journal.length
      ? h('div', {}, p.journal.map((j) => h('div', { class: 'entry' },
        h('div', { class: 'entry-head' },
          h('b', {}, D.JOURNAL_KIND[j.kind] || j.kind), h('span', {}, D.date(j.entry_date)), j.user_name ? h('span', {}, `· ${j.user_name}`) : null,
          h('button', { class: 'btn ghost small x', type: 'button', 'aria-label': 'Elimina nota', onclick: async (e) => {
            if (D.confirm('Eliminare questa nota?') && await D.run(e.currentTarget, () => D.del(`journal/${j.id}`)) !== undefined) reload();
          } }, '✕')),
        h('div', { class: 'entry-text' }, j.text))))
      : h('p', { class: 'muted', style: 'margin:12px 0 0;font-size:14px' }, 'Nessuna nota ancora.');

    page.append(
      h('div', { class: 'project-head' },
        h('div', {},
          h('div', {}, D.statusBadge(p.status)),
          h('h2', {}, p.title),
          h('div', { class: 'sub' }, [p.client_name, p.city].filter(Boolean).join(' · '))),
        h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' },
          h('button', { class: 'btn', onclick: () => projectModal(p) }, 'Modifica'),
          h('button', { class: 'btn danger', onclick: async (e) => {
            if (!D.confirm(`Eliminare definitivamente il progetto ${p.code} con fasi, scadenze e note?`)) return;
            if (await D.run(e.currentTarget, () => D.del(`projects/${id}`), 'Progetto eliminato') !== undefined) { await D.refreshOverdue(); D.go('/progetti'); }
          } }, 'Elimina'))),
      h('div', { class: 'grid-2' },
        h('div', {},
          card(`Fasi · ${done} di ${activePhases.length}`, progress(done, activePhases.length), phaseList, addPhase),
          card('Diario di commessa', null, journalForm, journal)),
        h('div', {},
          card('Dati', null, facts.length ? h('dl', { class: 'facts' }, facts.flatMap(([k, v]) => [h('dt', {}, k), h('dd', {}, v)])) : h('p', { class: 'muted' }, 'Nessun dato.')),
          p.notes ? card('Note', null, h('div', { class: 'entry-text' }, p.notes)) : null,
          card('Scadenze', null,
            p.tasks.length ? h('ul', { class: 'list' }, p.tasks.map((t) => taskRow(t, { showProject: false }))) : h('p', { class: 'muted', style: 'margin:0;font-size:14px' }, 'Nessuna scadenza.'),
            taskForm(id, reload)))));

    return { title: p.code, back: ['/progetti', 'Progetti'], nav: 'progetti' };
  });

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

  // ---------- Impostazioni ----------

  D.route('/impostazioni', async (page) => {
    const s = await D.get('settings');
    const studio = h('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      const out = await D.submit(studio, (d) => D.put('settings', d), 'Impostazioni salvate');
      if (out) { D.studioName = out.studio_name; D.render(); }
    } },
    D.field('Nome dello studio', D.input('studio_name', s.studio_name)),
    D.field('Fasi standard di un nuovo progetto', D.textarea('default_phases', s.default_phases, { rows: 9 }), { hint: 'Una fase per riga. Vale per i progetti creati da ora in poi.' }),
    h('div', { class: 'form-actions' }, h('button', { class: 'btn primary', type: 'submit' }, 'Salva')));

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

    page.append(h('div', { class: 'grid-2' },
      h('div', {}, card('Studio', null, studio)),
      h('div', {},
        card('Profilo', null, h('p', { class: 'item-meta', style: 'margin:-6px 0 14px' }, D.user.email), profile),
        card('Backup', null,
          h('p', { style: 'margin:0 0 12px;font-size:14px;color:var(--ink-2)' }, 'Scarica tutti i dati (clienti, progetti, fasi, scadenze, diario) in un file JSON leggibile. Conservalo in un posto sicuro.'),
          h('a', { class: 'btn', href: 'api.php?r=export', download: '' }, 'Scarica backup JSON')))));
    return { title: 'Impostazioni', nav: 'impostazioni' };
  });
})();
