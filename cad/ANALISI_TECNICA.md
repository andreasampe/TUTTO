# Analisi tecnica — Fase 0 (ricognizione)

Data: 30/10/2026. Riferimento: [SPECIFICA.txt](SPECIFICA.txt), punti 13 e 21.

Legenda dello stato delle funzioni (punto 17.5): **analizzata** · **implementata** · **testata** (test automatici) · **verificata su file reali** · **sperimentale**.

## 1. Ambiente

| Ambiente | Cosa è stato verificato | Esito |
|---|---|---|
| **Sessione di sviluppo** (cloud, Linux) | Python 3.13, Rust 1.97, accesso a PyPI e crates.io; **niente AutoCAD**, niente accesso diretto ai siti esterni né al Mac | qui si sviluppa e si testa il motore, ma la verifica in AutoCAD si fa sul Mac |
| **Mac di Andrea** (Apple Silicon) | Non accessibile da questa sessione | **da verificare** con i comandi di [README.md](README.md#prima-installazione-sul-mac): versione di Python, spazio libero |
| **Hosting e sito** | Non accessibile da questa sessione | **da verificare** dal pannello di controllo: vedi la sezione 6 |
| **Hosting** | Andrea (30/10/2026): *"credo sia Aruba, hosting Windows e Linux"*, non conosce altri dettagli | probabilmente un hosting condiviso Aruba. Di solito ha PHP e MySQL ma **niente Python né esecuzione di programmi esterni**: è da confermare dal pannello di controllo |
| **Database** | **Deciso da Andrea (30/10/2026): NON usare il database dell'hosting.** Pochi dati, quindi file SQLite e file JSON nelle cartelle di progetto | decisione presa: deroga voluta al punto 11.6 della specifica |

## 2. CADCraft (punto 5.1)

| Voce | Esito |
|---|---|
| Repository | `github.com/cadcraft-desktop/cadcraft`, copia letta il 30/10/2026 (commit `93ed849`, 19/10/2026). Autori dichiarati: "ArtCraft Team and the CADCraft contributors" |
| Licenza | **MIT oppure Apache-2.0** (doppia licenza, file `LICENSE-MIT` e `LICENSE-APACHE`): compatibile con un uso commerciale |
| Stato | Il progetto stesso si dichiara in **"early development"**. Il suo documento `docs/file-format-parity.md` stima la parità dei formati con AutoCAD a circa il 30% |
| Linguaggio | Rust, interfaccia egui; versioni per macOS (anche Apple Silicon), Windows, Linux e web |
| DXF | Lettura dalla R12 alla 2018 (ASCII e binario); **scrittura solo in R2000 ASCII** |
| DWG | Lettura dalla R13 alla 2018 tramite la libreria `acadrust`, e il progetto stesso dice **"untested on real files"**; **scrittura solo in R2000** |
| Perdite al salvataggio | Il suo documento `docs/gaps.md` (punto 1) dice: *"Saving drops whatever we don't model"*. Si perdono MULTILEADER, immagini, gruppi, xdata di altri programmi e altro |
| Prova in AutoCAD | `docs/gaps.md` (punto 2): *"nobody has verified a CADCraft DWG/DXF opens cleanly in AutoCAD"* |
| Quote, layout, cartigli, PDF | Dichiarati: comandi DIM*, layout con viewport, PLOT/EXPORTPDF |
| Automazione | Dichiarate: `cadcraft-cli` (info, convert, run, mcp), canale di controllo JSON e **server MCP** (`docs/mcp.md`) |
| Compilazione qui | **Non possibile**: la copia del repository non contiene i moduli `crates/` (geom, dxf, io, dwg…) a cui rimandano i manifest. Per usarlo serve l'app già compilata dalle release |

**Valutazione:** è il candidato più interessante per il futuro, soprattutto come CAD visuale e per l'automazione via MCP. Oggi però non è adatto a fare da motore del prototipo: è dichiaratamente immaturo nel salvataggio, che è proprio ciò che serve a noi. Va **rivalutato più avanti** con test su file reali (vedi la Roadmap).

## 3. Alternative (punto 5.3)

| Strumento | Tipo | Licenza | Pro | Contro | Ruolo proposto |
|---|---|---|---|---|---|
| **ezdxf** 1.4.2 | libreria Python per DXF | **MIT** | Matura (dal 2011) e mantenuta. Legge e scrive DXF R12–2018, conserva le entità che non conosce. Gestisce quote, blocchi con attributi, layout e anteprime. Funziona su macOS Apple Silicon | Non legge il DWG da sola | **motore del prototipo** (implementata, testata) |
| LibreCAD | CAD desktop 2D | GPL-2.0 | Gratuito, apre i DXF | Pochi strumenti di automazione; DWG solo in lettura e parziale | eventuale visualizzatore gratuito per controllare i DXF |
| FreeCAD | CAD 3D parametrico | LGPL-2.1 | Python integrato, BIM | Pesante, eccessivo per il 2D di questa fase | da valutare per la Fase 3+ (modello dell'edificio) |
| **acadrust** 0.6.1 | libreria Rust DWG/DXF (quella usata da CADCraft) | **MPL-2.0** (copyleft solo sui file modificati della libreria; utilizzabile in un prodotto commerciale) | Legge e scrive DWG R13–2018 senza AutoCAD | Il 30/10/2026 la compilazione di un convertitore di prova è stata **bloccata dalle regole di sicurezza di questo ambiente cloud** (niente esecuzione di codice esterno). Si può riprovare sul Mac | candidato n. 1 per il caricamento diretto dei DWG |
| LibreDWG (`dwg2dxf`) | convertitore DWG↔DXF | GPL-3.0 | Open source | La scrittura DWG è sperimentale. La GPL-3 va valutata se il software sarà distribuito | da provare sul Mac come convertitore |
| ODA File Converter | convertitore DWG↔DXF | **gratuito ma proprietario** (non open source) | Molto affidabile | Licenza da leggere: il punto 2.2 ricorda che "gratuito non significa open source" | **escluso** finché la licenza non è verificata |

## 4. Strategia DWG/DXF (punto 6)

Il flusso proposto non richiede convertitori di terze parti, perché **Andrea ha già AutoCAD**:

1. In AutoCAD: *Salva con nome → DXF AutoCAD 2018*. Questo è il formato di lavoro.
2. DOMUS legge il DXF, lo elabora e scrive un nuovo DXF nella **stessa versione** (2018), lasciando invariato l'originale.
3. In AutoCAD: si apre il DXF elaborato, si controlla e si salva in DWG.

Vantaggi: costo zero, nessun convertitore da validare, compatibilità garantita da AutoCAD stesso.

Più avanti, per caricare direttamente i DWG dal sito, si proveranno LibreDWG e CADCraft (`cadcraft-cli convert`) su file reali, misurando le perdite elencate al punto 6.

## 5. Scelta per il primo prototipo (punto 20.4)

- **Python 3 + ezdxf (MIT) + Pillow (MIT-CMU)** per l'anteprima. Nessun servizio esterno, nessuna AI.
- Il motore è **indipendente dal sito** (punto 11.3): si usa da riga di comando. Più avanti DOMUS web lo richiamerà.
- Le quote si calcolano solo dalle coordinate, in modo deterministico (punto 10.1).

## 6. Verifiche da fare sull'hosting (punto 13.2)

Dal pannello di controllo dell'hosting, senza modificare niente:

1. Sistema operativo e versione di PHP.
2. È disponibile **Python 3**? (comando `python3 --version` da SSH, se c'è).
3. È possibile eseguire programmi esterni (`exec`, `proc_open`) e processi in background (cron)?
4. Limiti di memoria (`memory_limit`), di tempo (`max_execution_time`) e di upload (`upload_max_filesize`, `post_max_size`).
5. Spazio disco disponibile.
6. Tipo e versione del database (solo tipo e versione: **mai** inviare le credenziali).

Se l'hosting non può eseguire Python, vale l'architettura del punto 3 della specifica: il sito gestisce progetti e file, l'elaborazione avviene sul Mac. Con un hosting condiviso Aruba è lo **scenario più probabile**, ed è l'ipotesi di lavoro finché non arriva la verifica.

## 7. Informazioni che mancano davvero

1. **Una planimetria reale in DXF.**
   - Il 30/10/2026 Andrea ha caricato su `main` 3 DWG in formato AC1032 (AutoCAD 2018 o successivi): `03_BASICO_VIVIENDA MOYA OPCION 2-3.dwg` e i probabili riferimenti esterni `REF.OPCION 2.dwg`, `REF.OPCION 3.dwg`.
   - Serve la loro esportazione in DXF da AutoCAD.
2. **La prova del risultato in AutoCAD** (test 8).
3. **Le caratteristiche dell'hosting** (sezione 6).
4. ~~Database~~: **deciso, niente database dell'hosting**: dati su file (SQLite/JSON).
5. **Convenzioni dello studio**: unità dei disegni (metri o centimetri), scale abituali, formato del cartiglio, nomi dei layer usati per muri, porte e finestre.

## 8. Difficoltà tecniche (stima qualitativa)

| Attività | Difficoltà | Perché |
|---|---|---|
| Quote esterne su piante ordinate | bassa (fatta) | geometria esplicita |
| Planimetrie reali "disordinate" (layer 0, blocchi annidati, doppioni) | media | servono controlli e correzioni manuali (punto 7.1) |
| Riconoscimento di locali, porte e finestre | alta | combinazione di geometria, layer e conferma dell'utente |
| DWG diretto senza AutoCAD | media-alta | dipende dagli strumenti open source, da validare su file reali |
| Impianti con verifiche normative | molto alta | regole da documentare una per una (punto 9) |
