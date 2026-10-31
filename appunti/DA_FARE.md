# DOMUS — cose da fare

Promemoria aggiornato al 31/10/2026, insieme agli altri appunti in questa cartella.
Il piano completo e tecnico è in `cad/ROADMAP.md` nel repository GitHub `andreasampe/TUTTO`, ramo `claude/ciao-kds7b5`.

## Come continuare il lavoro

Apri una nuova sessione di Claude Code sul repository `andreasampe/TUTTO`. Il file `CLAUDE.md` gli spiega tutto: chi sei, come vuoi essere guidato, a che punto è il progetto.
Per partire scrivi per esempio: *"riprendi dalle superfici utili"*.

## Tavole esecutive: le tue decisioni

**Decisioni di Andrea (31/10/2026):**
1. **Normativa italiana**, con regole che cambiano da comune a comune.
   - Serve un archivio interno dei comuni, che applichi le regole giuste in base al comune del progetto.
   - Le regole comunali non si possono inventare. Struttura a due livelli:
     - regole **nazionali** verificabili (DM 5/7/1975 sui requisiti igienico-sanitari, DM 236/89 sull'accessibilità…);
     - **regole del comune**, inserite dallo studio o importate da fonti ufficiali, sopra quelle nazionali.
   - Elenco dei comuni dal file aperto ISTAT.
2. **Ordine:**
   - superfici utili;
   - sigle dei serramenti;
   - tavola finale unica (impaginazione con cartiglio, PDF);
   - poi le altre tavole.
3. **Impianti: proposta automatica + ritocchi**, con le regole di Andrea:
   - camera: 2 prese ai lati del letto, h 60 cm;
   - cucina: prese di forno, frigo, piano cottura e microonde a h 40 cm, più 2 prese sul piano di lavoro a h 110 cm;
   - per il resto, buon senso e il riferimento progettuale (tavole ELECT/ILUM/FONT/SAN del file `03_BASICO`).
4. **Prospetti e sezioni:** con le altezze inserite nell'app (locali, davanzali, architravi, tetto).

## Prossimi passi, in ordine

Già fatto e pubblicato sul ramo `claude/ciao-kds7b5`:
- quote nuove modificabili;
- menu col tasto destro;
- quote allineate sui tratti inclinati;
- snap come in AutoCAD (fine, punto medio, perpendicolare, vicino).

1. **Superfici utili**, tavola PB_SUP. Lavoro appena iniziato: stavo leggendo dal `03_BASICO` come lo studio disegna le superfici (layer `01Sup. útiles` e `01Texto 2 - útiles`, testi tipo "SALÓN e COCINA SUP = 28.46 m²"). Da fare:
   - `domus_cad/superfici.py`: locali dalla `Mappa` di `interne.py` (zone interne separate da muri e porte) e area netta, con un raster fine e la correzione del bordo, oppure contorno → poligono agganciato ai muri;
   - **verifica sui valori reali** dell'opzione 2: SALÓN e COCINA 28.46, DORMITORIO PRINCIPAL 17.85, DORMITORIO INDIVIDUAL 11.07, DORMITORIO INDIVIDUAL 2 11.6x, BAÑO 6.43, ENTRADA 5.74, DISTRIBUIDOR 1.80, PATIO 2 10.75, Zona Aparcamiento 20.01;
   - nomi dei locali: dai testi già presenti nel locale, altrimenti da confermare nell'app (servono anche alle regole normative);
   - layer e formato del testo dal profilo: aggiungere a `profilo.RUOLI` i layer delle superfici;
   - nell'app: pulsante "Superfici utili", nomi modificabili, nuova versione.
2. **Sigle dei serramenti**, tavola PB_COT: riconoscere porte e finestre dal layer delle aperture, numerarle per tipo e dimensione (P1a, V1, PV1…), con un abaco.
3. **Tavola finale unica**: impaginazione delle tavole affiancate in layout con cartiglio (come la lamina base del `03_BASICO`) ed esportazione PDF.
4. **Archivio normativo italiano**:
   - regole nazionali (DM 5/7/1975, DM 236/89);
   - comuni dal file ISTAT;
   - regole comunali inserite dallo studio, mai inventate;
   - verifiche su superfici, altezze e aeroilluminazione.
5. **Impianti**: proposta automatica con le regole di Andrea, poi ritocchi nell'editor (simboli spostabili). Regole:
   - camera: 2 prese ai lati del letto a h 60;
   - cucina: forno, frigo, piano cottura e microonde a h 40, più 2 prese sul piano di lavoro a h 110.
6. **Altre tavole in pianta:** abitabilità, scivolosità dei pavimenti, antincendio, muratura, finiture.
7. **Prospetti e sezioni**, con le altezze inserite nell'app.
8. Ancora aperto:
   - Andrea deve provare DOMUS in Local (sostituire la cartella `domus` con quella dello ZIP aggiornato);
   - test 8 in AutoCAD sull'ultima versione;
   - attività separata sul fuso orario di SQLite.
