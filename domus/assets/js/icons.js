// DOMUS — icone a linea (24×24, tratto 1.5), disegnate apposta: nessuna libreria esterna.
// Uso: D.icon('home') restituisce un elemento <svg>.
'use strict';

(() => {
  const P = {
    home: 'M3.5 11 12 4l8.5 7M5.5 9.5V20h13V9.5M10 20v-5.5h4V20',
    projects: 'M4 20V9.5L9 5l5 4.5V20M14 9.5h2.5a6.5 6.5 0 0 1 0 13',
    clients: 'M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M16 4.5a3.5 3.5 0 0 1 0 6.5M18 14.5c1.8.8 3 2.8 3 5.5',
    tasks: 'M4 5.5h16v14H4zM8 12l2.5 2.5L16 9',
    calendar: 'M4 6h16v14H4zM4 10h16M8.5 3.5v4M15.5 3.5v4',
    settings: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM19.4 13.5l1.6 1.2-2 3.5-1.9-.7a7 7 0 0 1-2.1 1.2l-.3 2h-4l-.3-2a7 7 0 0 1-2.1-1.2l-1.9.7-2-3.5 1.6-1.2a7 7 0 0 1 0-3l-1.6-1.2 2-3.5 1.9.7a7 7 0 0 1 2.1-1.2l.3-2h4l.3 2a7 7 0 0 1 2.1 1.2l1.9-.7 2 3.5-1.6 1.2a7 7 0 0 1 0 3Z',
    search: 'M10.5 17a6.5 6.5 0 1 0 0-13 6.5 6.5 0 0 0 0 13ZM15.5 15.5 20 20',
    plus: 'M12 5v14M5 12h14',
    arrow: 'M5 12h14M13 6l6 6-6 6',
    back: 'M19 12H5M11 6l-6 6 6 6',
    chevron: 'M9 6l6 6-6 6',
    logout: 'M14 4h5v16h-5M10 8l-4 4 4 4M6 12h10',
    file: 'M6 3.5h8l4 4V20.5H6zM14 3.5V8h4',
    journal: 'M5 4.5h11a3 3 0 0 1 3 3v12H8a3 3 0 0 1-3-3zM5 16.5a3 3 0 0 1 3-3h11M9 8.5h6',
    permit: 'M7 3.5h10v17H7zM10 8h4M10 11.5h4M10 15a2 2 0 1 0 4 0 2 2 0 0 0-4 0Z',
    euro: 'M17.5 6.5A7 7 0 1 0 17.5 17.5M4.5 10.5h9M4.5 13.5h9',
    clock: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM12 7v5l3.5 2',
    print: 'M7 9V4h10v5M7 17H4.5V9.5h15V17H17M7 14h10v6H7z',
    edit: 'M4 20h4L19 9l-4-4L4 16zM13.5 6.5l4 4',
    trash: 'M4.5 7h15M9.5 7V4.5h5V7M6.5 7l1 13h9l1-13M10 11v5M14 11v5',
    upload: 'M12 15V4M7.5 8.5 12 4l4.5 4.5M4.5 15v5h15v-5',
    download: 'M12 4v11M7.5 10.5 12 15l4.5-4.5M4.5 15v5h15v-5',
    pin: 'M12 21s-6.5-6.2-6.5-11a6.5 6.5 0 0 1 13 0c0 4.8-6.5 11-6.5 11ZM12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z',
    grid: 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
    list: 'M8.5 6.5H20M8.5 12H20M8.5 17.5H20M4 6.5h.5M4 12h.5M4 17.5h.5',
    x: 'M6 6l12 12M18 6 6 18',
    image: 'M4 5h16v14H4zM4 15.5l4.5-4.5 4 4 2.5-2.5L20 17.5M15.5 9.5h.01',
    check: 'M5 12.5 10 17.5 19 7',
    layers: 'M12 4 3.5 8.5 12 13l8.5-4.5zM3.5 12.5 12 17l8.5-4.5M3.5 16 12 20.5l8.5-4.5',
    user: 'M12 11.5a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4.5 20.5c0-4 3.4-6.5 7.5-6.5s7.5 2.5 7.5 6.5',
    star: 'M12 4l2.4 5 5.4.6-4 3.7 1.1 5.4L12 16l-4.9 2.7 1.1-5.4-4-3.7 5.4-.6z',
  };

  D.icon = function (name, size = 18, extraClass = '') {
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('width', size);
    svg.setAttribute('height', size);
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '1.5');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    svg.setAttribute('aria-hidden', 'true');
    svg.classList.add('ico');
    if (extraClass) svg.classList.add(extraClass);
    const path = document.createElementNS(ns, 'path');
    path.setAttribute('d', P[name] || P.file);
    svg.append(path);
    return svg;
  };

  // Marchio DOMUS (casa + D) come elemento in linea, colore del testo.
  D.logo = function (size = 28) {
    const img = document.createElement('img');
    img.src = 'assets/logo-white.svg';
    img.width = size;
    img.height = size;
    img.alt = '';
    img.className = 'logo-mark';
    return img;
  };
})();
