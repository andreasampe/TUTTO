# DOMUS CAD — prototipo

Motore che trasforma una **planimetria CAD** in un nuovo elaborato: oggi una **pianta quotata** con catene di quote esterne, quote totali, nota e cartiglio, più rapporto dei controlli e anteprima. È la Fase 1 della [specifica](SPECIFICA.txt). Lo stato è in [ROADMAP.md](ROADMAP.md).

![Anteprima dell'esempio artificiale](esempi/anteprima_esempio.png)

*Esempio artificiale (appartamento inventato): la prova su una planimetria reale è ancora da fare.*

## Requisiti

- Python 3.10 o più recente (sul Mac: `python3 --version` nel Terminale).
- Le librerie in `requirements.txt` (ezdxf e Pillow, gratuite e open source).

## Prima installazione sul Mac

```bash
cd "/Users/andreagiacometti/Local Sites/DOMUS/cad"
python3 -m venv .venv
.venv/bin/pip install -r requirements.txt
```

## Uso

1. In AutoCAD apri la planimetria e fai *Salva con nome → DXF AutoCAD 2018*.
2. Analizza il disegno senza modificarlo:
   ```bash
   .venv/bin/python -m domus_cad leggi pianta.dxf
   ```
3. Genera la pianta quotata:
   ```bash
   .venv/bin/python -m domus_cad elabora pianta.dxf --uscita lavori/via-roma --scala 100 --formato cm --progetto "Via Roma 12" --studio "Studio Pucci Architettura"
   ```
4. Apri `lavori/via-roma/elaborazioni/<data-ora>/elaborato.dxf` in AutoCAD, controllalo e salvalo come DWG.

Opzioni utili:

| Opzione | Effetto |
|---|---|
| `--unita m` / `cm` / `mm` | obbligatoria se il file non dichiara le unità |
| `--layer-muri MURI A-WALL` | quota solo i vertici di questi layer |
| `--formato cm` / `m` / `mm` | unità del testo delle quote |

## Test

```bash
.venv/bin/python -m unittest discover -s tests
```

Documenti: [ANALISI_TECNICA.md](ANALISI_TECNICA.md) · [ARCHITETTURA.md](ARCHITETTURA.md) · [TEST.md](TEST.md) · [LICENZE.md](LICENZE.md)
