// DOMUS — basi dell'interfaccia: chiamate al server, costruzione degli elementi, router, avvisi.
// I dati vengono sempre inseriti come testo, mai come HTML.
'use strict';

const D = (window.D = {});

// ---------- API ----------

D.csrf = '';

D.api = async function (method, route, body) {
  const opts = { method, headers: { 'X-Requested-With': 'domus' }, credentials: 'same-origin' };
  if (method !== 'GET') opts.headers['X-CSRF-Token'] = D.csrf;
  if (body !== undefined) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(body);
  }
  let res;
  try {
    res = await fetch(`api.php?r=${route}`, opts);
  } catch {
    throw new Error('Server non raggiungibile: il sito in Local è avviato?');
  }
  const data = await res.json().catch(() => ({}));
  if (res.status === 401 && !route.startsWith('auth/')) {
    D.user = null;
    D.boot();
  }
  if (!res.ok) {
    const err = new Error(data.error || `Errore ${res.status}`);
    err.fields = data.fields || {};
    throw err;
  }
  return data;
};
// Caricamento di file (multipart): stesso controllo CSRF, niente JSON.
D.upload = async function (route, formData) {
  let res;
  try {
    res = await fetch(`api.php?r=${route}`, { method: 'POST', body: formData, credentials: 'same-origin', headers: { 'X-Requested-With': 'domus', 'X-CSRF-Token': D.csrf } });
  } catch {
    throw new Error('Server non raggiungibile durante il caricamento');
  }
  const data = await res.json().catch(() => ({}));
  if (res.status === 413) throw new Error(`File troppo grande per il server (limite ${D.limits?.max_post || '?'})`);
  if (!res.ok) throw new Error(data.error || `Errore ${res.status}`);
  return data;
};

D.get = (r) => D.api('GET', r);
D.post = (r, b) => D.api('POST', r, b || {});
D.put = (r, b) => D.api('PUT', r, b || {});
D.del = (r) => D.api('DELETE', r);

// ---------- elementi ----------

D.h = function h(tag, attrs, ...children) {
  const node = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs || {})) {
    if (v === null || v === undefined || v === false) continue;
    if (k.startsWith('on') && typeof v === 'function') node.addEventListener(k.slice(2), v);
    else if (k === 'class') node.className = v;
    else if (k === 'value') node.value = v;
    else if (k === 'checked' || k === 'selected' || k === 'disabled' || k === 'hidden') node[k] = !!v;
    else if (k === 'style') node.style.cssText = v;
    else node.setAttribute(k, v === true ? '' : v);
  }
  for (const c of children.flat(Infinity)) {
    if (c === null || c === undefined || c === false || c === '') continue;
    node.append(c instanceof Node ? c : document.createTextNode(String(c)));
  }
  return node;
};

// ---------- formati ----------

const MONTHS = ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];

D.today = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

D.date = (iso) => {
  if (!iso) return '';
  const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
  return `${d} ${MONTHS[m - 1]} ${y}`;
};

// "oggi", "domani", "tra 3 giorni", "2 giorni fa"…
D.relDate = (iso) => {
  if (!iso) return '';
  const diff = Math.round((new Date(iso.slice(0, 10)) - new Date(D.today())) / 86400000);
  if (diff === 0) return 'oggi';
  if (diff === 1) return 'domani';
  if (diff === -1) return 'ieri';
  if (diff > 1 && diff < 14) return `tra ${diff} giorni`;
  if (diff < -1 && diff > -14) return `${-diff} giorni fa`;
  return D.date(iso);
};

D.isLate = (iso) => !!iso && iso.slice(0, 10) < D.today();

D.money = (n) => (n === null || n === undefined || n === '' ? '' : Number(n).toLocaleString('it-IT', { style: 'currency', currency: 'EUR', useGrouping: 'always' }));

D.STATUS = { preventivo: 'Preventivo', in_corso: 'In corso', sospeso: 'Sospeso', concluso: 'Concluso', archiviato: 'Archiviato' };
D.PHASE_STATUS = { da_fare: 'Da fare', in_corso: 'In corso', completata: 'Completata', non_prevista: 'Non prevista' };
D.JOURNAL_KIND = { nota: 'Nota', sopralluogo: 'Sopralluogo', riunione: 'Riunione', telefonata: 'Telefonata', email: 'Email', pratica: 'Pratica' };
D.CLIENT_KIND = { privato: 'Privato', azienda: 'Azienda', ente: 'Ente pubblico' };

D.PERMIT_STATUS = { in_preparazione: 'In preparazione', depositata: 'Depositata', integrazioni: 'Integrazioni richieste', approvata: 'Approvata', respinta: 'Respinta', archiviata: 'Archiviata' };
D.FILE_CATEGORY = { tavola: 'Tavole', foto: 'Foto', documento: 'Documenti', pratica: 'Pratiche', contratto: 'Contratti', altro: 'Altro' };
D.ROLE = { admin: 'Amministratore', collaboratore: 'Collaboratore' };
D.isAdmin = () => D.user?.role === 'admin';

// "2M" (formato di PHP) → "2 MB"
D.phpSize = (v) => String(v || '?').replace(/^(\d+)\s*([KMG])$/i, (_, n, u) => `${n} ${u.toUpperCase()}B`);

D.size = (b) => (b < 1024 ? `${b} B` : b < 1048576 ? `${(b / 1024).toFixed(0)} KB` : `${(b / 1048576).toFixed(1).replace('.', ',')} MB`);
D.hours = (h) => {
  const n = Number(h) || 0;
  const hh = Math.floor(n);
  const mm = Math.round((n - hh) * 60);
  return mm ? `${hh} h ${String(mm).padStart(2, '0')}` : `${hh} h`;
};

D.statusBadge = (s) => D.h('span', { class: `badge ${s}` }, D.STATUS[s] || s);

// ---------- avvisi e conferme ----------

D.toast = function (msg, isErr) {
  document.querySelector('.toast')?.remove();
  const t = D.h('div', { class: `toast${isErr ? ' err' : ''}`, role: 'status' }, msg);
  document.body.append(t);
  setTimeout(() => t.remove(), isErr ? 6000 : 3000);
};

// Esegue un'azione disabilitando il pulsante; in caso di errore mostra il messaggio.
D.run = async function (btn, fn, okMsg) {
  if (btn) btn.disabled = true;
  try {
    const out = await fn();
    if (okMsg) D.toast(okMsg);
    return out;
  } catch (err) {
    D.toast(err.message, true);
    return undefined;
  } finally {
    if (btn && btn.isConnected) btn.disabled = false;
  }
};

// ---------- moduli ----------

// field('Titolo', input, { required, hint }) → etichetta + campo + spazio per l'errore
D.field = function (label, control, opts = {}) {
  return D.h('label', { class: 'field', 'data-name': control.name || '' },
    D.h('span', {}, label, opts.required ? D.h('em', {}, ' *') : null),
    control,
    opts.hint ? D.h('small', {}, opts.hint) : null,
    D.h('span', { class: 'err' }));
};

D.input = (name, value, attrs = {}) => D.h('input', { name, value: value ?? '', type: 'text', ...attrs });
D.textarea = (name, value, attrs = {}) => { const t = D.h('textarea', { name, ...attrs }); t.value = value ?? ''; return t; };
D.select = (name, options, selected, attrs = {}) => D.h('select', { name, ...attrs },
  Object.entries(options).map(([v, l]) => D.h('option', { value: v, selected: String(selected ?? '') === String(v) }, l)));

D.formData = (form) => Object.fromEntries(new FormData(form).entries());

D.showErrors = function (form, err) {
  form.querySelectorAll('.field').forEach((f) => { f.classList.remove('invalid'); f.querySelector('.err').textContent = ''; });
  let first = null;
  for (const [name, msg] of Object.entries(err.fields || {})) {
    const f = form.querySelector(`.field[data-name="${name}"]`);
    if (!f) continue;
    f.classList.add('invalid');
    f.querySelector('.err').textContent = msg;
    first = first || f.querySelector('input,select,textarea');
  }
  if (first) first.focus();
  D.toast(err.message, true);
};

// Invia un modulo: gestisce pulsante, errori per campo e messaggio di conferma.
D.submit = async function (form, fn, okMsg) {
  const btn = form.querySelector('button[type=submit]');
  if (btn) btn.disabled = true;
  try {
    const out = await fn(D.formData(form));
    if (okMsg) D.toast(okMsg);
    return out;
  } catch (err) {
    D.showErrors(form, err);
    return undefined;
  } finally {
    if (btn && btn.isConnected) btn.disabled = false;
  }
};

// ---------- finestra modale ----------

D.modal = function (title, body) {
  const close = () => { bg.remove(); document.removeEventListener('keydown', onKey); };
  const onKey = (e) => { if (e.key === 'Escape') close(); };
  const bg = D.h('div', { class: 'modal-bg', onmousedown: (e) => { if (e.target === bg) close(); } },
    D.h('div', { class: 'modal', role: 'dialog', 'aria-modal': 'true', 'aria-label': title },
      D.h('div', { class: 'modal-head' }, D.h('h2', {}, title), D.h('button', { class: 'btn ghost', type: 'button', onclick: close, 'aria-label': 'Chiudi' }, '✕')),
      D.h('div', { class: 'modal-body' }, body)));
  document.body.append(bg);
  document.addEventListener('keydown', onKey);
  setTimeout(() => bg.querySelector('input:not([type=hidden]),select,textarea')?.focus(), 30);
  return close;
};

D.confirm = (msg) => window.confirm(msg);

// ---------- router ----------
// Indirizzi: #/  #/progetti  #/progetti/12  #/clienti  #/clienti/3  #/scadenze  #/impostazioni

D.routes = [];
D.route = (pattern, view) => D.routes.push({ re: new RegExp(`^${pattern}$`), view });
D.go = (path) => { location.hash = `#${path}`; };

let renderToken = 0;

D.render = async function () {
  if (!D.user) return;
  const path = location.hash.replace(/^#/, '') || '/';
  const match = D.routes.map((r) => ({ r, m: path.match(r.re) })).find((x) => x.m);
  const token = ++renderToken;
  const page = D.h('div', { class: 'page' });
  let meta = { title: 'Pagina non trovata', nav: '' };
  try {
    meta = match ? await match.r.view(page, ...match.m.slice(1)) : (page.append(D.h('p', { class: 'empty' }, 'Pagina non trovata.')), meta);
  } catch (err) {
    page.replaceChildren(D.h('p', { class: 'empty' }, err.message));
    meta = { title: 'Errore', nav: '' };
  }
  if (token !== renderToken) return;
  D.layout(meta, page);
  window.scrollTo(0, 0);
};

D.layout = function (meta, page) {
  const app = document.getElementById('app');
  const navLink = (href, label, key, count) => D.h('a', { href: `#${href}`, class: meta.nav === key ? 'active' : '' },
    label, count ? D.h('span', { class: 'count', title: 'Scadenze superate' }, count) : null);
  const iconUrl = document.querySelector('link[rel=icon]').getAttribute('href');

  app.replaceChildren(D.h('div', { class: 'shell' },
    D.h('aside', { class: 'side' },
      D.h('div', { class: 'brand' }, D.h('img', { src: iconUrl, alt: '' }),
        D.h('div', {}, D.h('b', {}, D.appName), D.studioName ? D.h('small', {}, D.studioName) : null)),
      D.h('nav', { class: 'nav', onclick: () => document.body.classList.remove('nav-open') },
        navLink('/', 'Panoramica', 'home'),
        navLink('/progetti', 'Progetti', 'progetti'),
        navLink('/clienti', 'Clienti', 'clienti'),
        navLink('/scadenze', 'Scadenze', 'scadenze', D.overdue),
        navLink('/calendario', 'Calendario', 'calendario'),
        navLink('/impostazioni', 'Impostazioni', 'impostazioni')),
      D.h('button', { class: 'search-trigger', type: 'button', onclick: () => D.palette() },
        D.h('span', {}, 'Cerca…'), D.h('kbd', {}, navigator.platform.includes('Mac') ? '⌘K' : 'Ctrl K')),
      D.h('div', { class: 'side-foot' },
        D.h('span', {}, D.user.name, D.h('small', {}, D.ROLE[D.user.role] || '')),
        D.h('button', { type: 'button', onclick: D.logout }, 'Esci'))),
    D.h('main', { class: 'main' },
      D.h('header', { class: 'top' },
        D.h('button', { class: 'menu-btn', type: 'button', 'aria-label': 'Menu', onclick: () => document.body.classList.toggle('nav-open') }, '☰'),
        meta.back ? D.h('a', { class: 'crumb', href: `#${meta.back[0]}` }, `← ${meta.back[1]}`) : null,
        D.h('h1', {}, meta.title),
        D.h('div', { class: 'spacer' }),
        meta.actions || null),
      D.h('div', { class: 'print-head' }, D.h('b', {}, D.studioName || D.appName), ` · ${meta.title} · stampato il ${D.date(D.today())}`),
      page)));
  document.title = `${meta.title} · ${D.appName}`;
};

// ---------- accesso ----------

D.boot = async function () {
  const app = document.getElementById('app');
  let me;
  try {
    me = await D.get('auth/me');
  } catch (err) {
    app.replaceChildren(D.h('div', { class: 'auth' }, D.h('p', { class: 'empty' }, err.message)));
    return;
  }
  D.csrf = me.csrf;
  D.appName = me.app_name || 'DOMUS';
  D.studioName = me.studio_name || '';
  D.limits = me.upload || {};
  D.user = me.user;
  app.classList.remove('booting');
  app.removeAttribute('aria-busy');
  if (!D.user) return D.authScreen(me.needs_setup);
  await D.refreshOverdue();
  D.render();
};

D.refreshOverdue = async function () {
  try { D.overdue = (await D.get('tasks&filter=scadute')).length; } catch { D.overdue = 0; }
};

D.logout = async function () {
  await D.run(null, () => D.post('auth/logout'));
  D.user = null;
  location.hash = '';
  D.boot();
};

D.authScreen = function (setup) {
  const app = document.getElementById('app');
  const iconUrl = document.querySelector('link[rel=icon]').getAttribute('href');
  const form = D.h('form', { class: 'form', onsubmit: async (e) => {
    e.preventDefault();
    const out = await D.submit(form, (d) => D.post(setup ? 'auth/setup' : 'auth/login', d));
    if (out) { D.csrf = out.csrf; D.boot(); }
  } },
  setup ? [
    D.field('Nome dello studio', D.input('studio_name', '', { placeholder: 'es. Studio di Architettura Rossi' })),
    D.field('Il tuo nome', D.input('name', '', { autocomplete: 'name', required: true }), { required: true }),
  ] : null,
  D.field('Email', D.input('email', '', { type: 'email', autocomplete: 'username', required: true }), { required: true }),
  D.field('Password', D.input('password', '', { type: 'password', autocomplete: setup ? 'new-password' : 'current-password', required: true, minlength: setup ? 10 : null }),
    { required: true, hint: setup ? 'Almeno 10 caratteri' : null }),
  D.h('button', { class: 'btn primary', type: 'submit', style: 'justify-content:center;padding:10px' }, setup ? 'Crea account e inizia' : 'Accedi'));

  app.replaceChildren(D.h('div', { class: 'auth' }, D.h('div', { class: 'auth-box' },
    D.h('div', { class: 'brand' }, D.h('img', { src: iconUrl, alt: '' }), D.h('b', {}, D.appName)),
    D.h('div', { class: 'card' },
      D.h('h1', {}, setup ? 'Benvenuto' : 'Accedi'),
      D.h('p', {}, setup ? 'Primo avvio: crea l\'account con cui entrerai in DOMUS.' : (D.studioName || 'Area riservata dello studio')),
      form))));
  document.title = D.appName;
  setTimeout(() => form.querySelector('input')?.focus(), 30);
};

// ---------- ricerca rapida (⌘K / Ctrl K) ----------

D.palette = function () {
  if (document.querySelector('.palette')) return;
  const input = D.h('input', { type: 'search', placeholder: 'Cerca progetti, clienti, pratiche, documenti…', 'aria-label': 'Cerca' });
  const list = D.h('ul', { class: 'palette-list', role: 'listbox' });
  let items = [];
  let sel = 0;
  let timer;
  const LABEL = { progetto: 'Progetto', cliente: 'Cliente', pratica: 'Pratica', documento: 'Documento' };
  const draw = () => list.replaceChildren(...(items.length ? items.map((r, i) => D.h('li', {
    class: i === sel ? 'on' : '', role: 'option', 'aria-selected': String(i === sel),
    onmousemove: () => { if (sel !== i) { sel = i; draw(); } }, onclick: () => open(r),
  }, D.h('span', { class: 'pal-type' }, LABEL[r.type] || r.type), D.h('span', { class: 'pal-title' }, r.title), D.h('span', { class: 'pal-meta' }, r.meta || '')))
    : [D.h('li', { class: 'pal-empty' }, input.value.trim().length < 2 ? 'Scrivi almeno due lettere' : 'Nessun risultato')]));
  const open = (r) => { close(); D.go(r.href); };
  input.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(async () => {
      try { items = await D.get(`search&q=${encodeURIComponent(input.value)}`); } catch { items = []; }
      sel = 0; draw();
    }, 150);
  });
  input.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowDown') { e.preventDefault(); sel = Math.min(sel + 1, items.length - 1); draw(); }
    if (e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(sel - 1, 0); draw(); }
    if (e.key === 'Enter' && items[sel]) open(items[sel]);
  });
  const close = D.modal('Cerca', D.h('div', { class: 'palette' }, input, list));
  draw();
};

document.addEventListener('keydown', (e) => {
  if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k' && D.user) { e.preventDefault(); D.palette(); }
});

window.addEventListener('hashchange', () => D.render());
document.addEventListener('DOMContentLoaded', () => D.boot());
