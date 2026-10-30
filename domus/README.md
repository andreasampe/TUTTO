# DOMUS

Webapp per lo studio di architettura: **progetti** (commesse) con fasi, **clienti**, **scadenze** e **diario di commessa**.

- È un'applicazione PHP **autonoma**: non usa WordPress e non usa il database dell'hosting.
- I dati stanno in un **file SQLite** (`domus.sqlite`) nella cartella `domus-data`, fuori dalla parte pubblica del sito.
- Da *Impostazioni → Backup* si scarica tutto in un file **JSON** leggibile.

## Requisiti

- PHP **8.1** o più recente, con l'estensione **pdo_sqlite** (inclusa in Local e quasi sempre negli hosting).
- Nient'altro: nessun database da creare, nessun pacchetto da installare.

## Installazione in Local

Il sito **DOMUS** creato con Local ha questa struttura:

```
Local Sites/DOMUS/
└── app/
    ├── public/            ← cartella pubblica del sito (WordPress)
    │   └── domus/         ← QUI va la cartella di DOMUS
    └── domus-data/        ← creata da sola al primo avvio: database (NON pubblica)
```

1. Copia la cartella `domus` dentro `Local Sites/DOMUS/app/public/`.
2. In Local avvia il sito (**Start site**).
3. Apri **http://domus.local/domus/** nel browser.
4. Al primo avvio crea l'account: nome dello studio, il tuo nome, email e password (almeno 10 caratteri).

## Uso

| Sezione | A cosa serve |
|---|---|
| **Panoramica** | progetti in corso con avanzamento, scadenze superate e prossime, ultime note del diario |
| **Progetti** | elenco con ricerca e filtri per stato. Ogni progetto ha codice progressivo (`2026-001`), dati catastali, onorario, fasi, scadenze e diario |
| **Clienti** | anagrafica (privati, aziende, enti) con i rispettivi progetti |
| **Scadenze** | tutte le cose da fare: superate, entro 7 giorni, fatte |
| **Impostazioni** | nome dello studio, fasi standard dei nuovi progetti, profilo e password, backup JSON |

**Fasi:** cliccando il pallino, lo stato passa da *da fare* a *in corso* a *completata*. Il menu a tendina permette anche *non prevista*.

## Spostarlo su un hosting

Si copiano due cartelle: `domus` (nella parte pubblica) e `domus-data` (un livello sopra, fuori dalla parte pubblica). Se sull'hosting la struttura è diversa, indica il percorso dei dati in `config.php` (`data_dir`).

**Importante:** `domus-data` non deve mai stare dentro la cartella pubblica.

## Backup

- **Completo:** copia la cartella `domus-data` (con il sito fermo, oppure subito dopo un salvataggio).
- **Leggibile:** *Impostazioni → Scarica backup JSON*.

## Test (per lo sviluppo)

```bash
bash tests/run.sh
```

Avvia DOMUS con il server PHP integrato su un database temporaneo e prova tutta l'API: accesso, permessi, clienti, progetti, fasi, scadenze, diario, impostazioni, backup e protezioni.
