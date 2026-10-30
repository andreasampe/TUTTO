# DOMUS — guida di stile

**Design. Organize. Model. Unify. Simplify.**

Riferimento: le tavole di presentazione di DOMUS (nero profondo, fotografia architettonica in cemento, linee di costruzione) e la favicon (la D che accoglie la casa, su griglia). Tutti i valori sono variabili CSS in `assets/css/domus.css`, sezione `:root`.

## Principi

1. **Il nero è lo spazio.** Lo sfondo è quasi nero e le superfici si distinguono appena, come piani di cemento in ombra.
2. **La fotografia è protagonista.** Le immagini di architettura aprono la panoramica, la scheda progetto e l'accesso, e sfumano nel nero da sinistra.
3. **Linee sottili, mai pesanti.** Bordi da 1 px, barre di avanzamento da 2 px, icone a tratto da 1,5 px.
4. **Testo leggero e grande per i titoli, piccolo e spaziato per le etichette.**
5. **Il colore è un segnale, non una decorazione.** Verde = fatto o attivo, ambra = in attesa, rosso = scaduto o errore. Tutto il resto è in scala di grigi.

## Colori

| Variabile | Valore | Uso |
|---|---|---|
| `--bg` | `#0a0a0a` | sfondo della pagina |
| `--surface` | `#101010` | schede |
| `--surface-2` / `--surface-3` | `#161616` / `#1d1d1d` | passaggio del mouse, elemento attivo del menu |
| `--line` / `--line-2` / `--line-3` | `#1f1f1f` / `#2c2c2c` / `#3d3d3d` | divisori, bordi dei campi, bordi dei pulsanti |
| `--ink` | `#f2f1ee` | testo principale (bianco caldo, non puro) |
| `--ink-2` / `--ink-3` / `--ink-4` | `#a9a7a1` / `#75736d` / `#4f4e4a` | testo secondario, etichette, testo spento |
| `--green` | `#4fc77d` | completato, incassato, approvato, utente attivo |
| `--amber` | `#d4a654` | preventivo, integrazioni richieste |
| `--red` | `#e5625e` | scadenze superate, errori, eliminazione |

## Tipografia

- **Carattere:** Inter (variabile, incluso in `assets/fonts/`, licenza OFL). Non serve Internet.
- **Titoli grandi** (`.display`): 34–52 px, peso **300**, interlinea 1,04, spaziatura −0,035 em. Esempio: "Dall'idea / al progetto."
- **Titoli di pagina:** 26 px, peso 300.
- **Etichette** (`.eyebrow`, titoli delle schede, intestazioni delle tabelle): 10–10,5 px, **MAIUSCOLO**, spaziatura 0,22–0,26 em, grigio.
- **Testo:** 14 px, peso 400. **Testo introduttivo** (`.lead`): 15 px, grigio, massimo 46 caratteri per riga.
- **Numeri** dei riepiloghi: grandi e sottili (peso 200–300), cifre tabellari.
- **Codici e protocolli:** carattere a larghezza fissa, piccolo e grigio.

## Componenti

- **Pulsanti a pillola** (`.btn`): bordo 1 px, raggio 999 px, sfondo trasparente.
  - `.primary`: bordo bianco, che al passaggio del mouse si riempie di bianco.
  - `.ghost`: solo testo.
  - `.icon`: tondo, solo icona.
  - `.danger`: rosso.
- **Invito "Scopri di più"** (`.cta`): testo sottolineato da una linea sottile, seguito da un cerchio con la freccia.
- **Schede** (`.card`): sfondo `--surface`, bordo `--line`, raggio 12 px. Il titolo è un'etichetta maiuscola.
- **Indicatori** (`.stats`): celle affiancate separate da una linea di 1 px, con l'etichetta sopra e il numero grande sotto.
- **Pillole di stato** (`.badge`): bordo sottile, pallino del colore dello stato, testo 11 px.
- **Copertine** (`.pcard`): foto 16:10 con la pillola di stato in alto a destra. Sotto: titolo, "tipo · comune · anno", codice e avanzamento.
  - In assenza di foto: griglia di costruzione e marchio in trasparenza.
- **Testate con immagine** (`.hero`, `.project-hero`): foto a destra che sfuma nel nero verso sinistra, con l'etichetta, il titolo leggero e i dati chiave.
- **Fasi:** cerchi uniti da una linea verticale.
  - fatta: cerchio pieno bianco;
  - in corso: cerchio con alone;
  - da fare: cerchio vuoto.
- **Campi:** sfondo nero, bordo `--line-2`, raggio 8 px, etichetta maiuscola sopra. I menu a tendina hanno una freccia sottile.
- **Icone:** 24×24, tratto 1,5, estremità arrotondate (`assets/js/icons.js`). Mai icone piene.

## Marchio

La geometria è misurata sul file originale `domus_logo.png` (1254 px), con una corrispondenza dei tratti del 95%:
- **D:** barra orizzontale a y 317,5 da x 319 a 700, poi mezza ellisse con centro (700, 632) e semiassi 272 × 314,5, che si chiude a (748, 941,6);
- **casa:** parete sinistra a x 413, tetto con colmo in (592, 513), parete destra a x 747;
- **tratto:** 43,5 px;
- **griglia di costruzione:** linee a x 262 / 318 / 625 / 985 e a y 226 / 632 / 984, cerchio con centro (625, 632) e raggio 310, crocini agli incroci.

File:
- `assets/icon.svg`: favicon. Quadrato nero arrotondato con leggera sfumatura, griglia di costruzione e la D che accoglie la casa.
- `assets/apple-touch-icon.png`: la stessa icona in PNG 180 px, per la schermata Home dell'iPhone.
- `assets/logo.svg` / `logo-white.svg`: solo il marchio a linea, per il menu e la pagina di accesso.
- **Motto:** *Design · Organize · Model · Unify · Simplify*, in verticale, maiuscolo spaziato, con le iniziali in evidenza (classi `.motto`, `.acronym`, `.hero-side`).

## Immagini

| File | Dove |
|---|---|
| `assets/img/home.jpg` | panoramica: la D in cemento che accoglie la casa |
| `assets/img/login.jpg` | pagina di accesso: la casa in fil di ferro che diventa cemento |
| `assets/img/brand.jpg` | testata dei progetti senza copertina: la villa in fil di ferro |
| `demo/*.jpg` | foto dei progetti dimostrativi |

Sono le immagini fornite da Andrea, a 1376 px di larghezza. Per sostituirle basta tenere lo stesso nome del file.

**Motto:**
- in inglese, per il marchio: **D**esign · **O**rganize · **M**odel · **U**nify · **S**implify;
- in italiano, per i testi: *Disegna. Organizza. Modella. Unifica. Semplifica.*;
- firma: *DOMUS · Intelligent Building Design*.

## Movimento

Transizioni brevi (0,15–0,25 s) con curva morbida. Le schede dei progetti si sollevano di 2 px e la foto si ingrandisce appena. Le finestre compaiono salendo di 10 px. Niente animazioni decorative.
