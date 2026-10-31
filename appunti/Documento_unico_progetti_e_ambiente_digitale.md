**DOCUMENTO UNICO\
PROGETTI, SITI, APPLICAZIONI\
E AMBIENTE DI SVILUPPO**

*Roadmap operativa, specifiche tecniche e configurazione AI locale*

Documento master • Versione unificata • 30 ottobre 2026

# INDICE E STRUTTURA

-   PARTE I --- Roadmap generale di siti, applicazioni e progetti

-   PARTE II --- Ambiente di sviluppo web su Mac e utilizzo di Claude
    Code

-   PARTE III --- Ambiente di intelligenza artificiale locale per
    contenuti multimediali

-   APPENDICE --- Indicazioni di gestione e aggiornamento del documento

Questo documento riunisce i quattro materiali forniti in un unico
riferimento. I contenuti sono stati accorpati per argomento, mantenendo
requisiti, esempi, prompt, vincoli tecnici e modalità operative. Le
parti ripetute sono state ricondotte alla sezione più pertinente,
evitando di replicare più volte la stessa attività.

# PARTE I --- ROADMAP GENERALE: SITI, APPLICAZIONI E PROGETTI

Questa parte raccoglie le attività operative, le migliorie e gli
sviluppi futuri dei progetti digitali.

# I.1 --- Roadmap dei progetti

## ROADMAP PROGETTI

Attività operative, miglioramenti e sviluppi futuri

Documento di lavoro aggiornabile

Questo documento raccoglie le attività da svolgere, i miglioramenti da
implementare e le idee per lo sviluppo futuro dei siti e delle
applicazioni.

Le attività sono organizzate per progetto, con indicazione degli
obiettivi, delle operazioni da eseguire e delle relative priorità.

1\. MINU

1.1 --- Sviluppo della funzione «Carica collezione intera»

Obiettivo: aggiungere al gestionale una nuova modalità per importare
intere collezioni di prodotti da siti e-commerce, senza dover inserire
manualmente il link di ogni singolo articolo.

Situazione attuale

Il gestionale funziona già correttamente e permette di inserire il link
di un prodotto, visitare la relativa pagina e recuperare automaticamente
le informazioni necessarie per importarlo.

Attualmente, però, per caricare più prodotti è necessario inserire
manualmente i singoli link.

L'obiettivo è semplificare questo processo, permettendo di inserire un
unico link relativo a una collezione o categoria e lasciare che il
sistema individui automaticamente tutti i prodotti contenuti.

Indicazioni per lo sviluppo

IMPORTANTE: il gestionale esistente funziona già bene. Non deve essere
riscritto, rifatto o modificato nelle sue funzionalità attuali, salvo
quando strettamente necessario. La nuova funzione deve integrarsi con il
sistema esistente, riutilizzando il più possibile il codice e le
procedure di importazione già funzionanti.

La nuova funzionalità dovrà:

Permettere di inserire il link di una collezione o categoria di un sito
e-commerce.

Individuare automaticamente i prodotti presenti nella collezione.

Recuperare i link delle singole pagine prodotto.

Passare i link individuati al sistema di importazione già esistente.

Recuperare le informazioni dei prodotti attraverso le procedure
attualmente utilizzate.

Gestire, per quanto possibile, strutture e-commerce differenti, senza
essere progettata esclusivamente per un singolo sito.

In pratica, il sistema dovrà comportarsi come una funzione di
inserimento multiplo: invece di fornire manualmente tutti i link dei
prodotti, sarà sufficiente inserire quello della collezione, dal quale
il gestionale ricaverà automaticamente i singoli articoli.

Organizzazione dell'interfaccia

Valutare una riorganizzazione dell'area di importazione, suddividendola
in due sezioni principali:

1\. Carica singolo prodotto

Inserimento del link di un singolo articolo.

Utilizzo della procedura di importazione attualmente esistente.

2\. Carica più prodotti

Possibilità di inserire più link contemporaneamente.

Possibilità di inserire il link di un'intera collezione o categoria.

Individuazione automatica dei prodotti e successivo avvio
dell'importazione.

L'interfaccia dovrà essere semplice, intuitiva e coerente con il
gestionale attuale.

DOVREMO MODIFICARE QUESTO SOPRA IN PRATICA.

Conferma per le collezioni numerose

Quando una collezione contiene più di 20 prodotti, il sistema dovrà
mostrare un messaggio di conferma prima di procedere.

Esempio:

«La collezione contiene N prodotti. Sei sicuro di voler LEGGERLI tutti?
L'operazione potrebbe richiedere più tempo rispetto all'importazione di
singoli articoli.»

L'utente dovrà poter confermare oppure annullare l'operazione.

Esempi di link da supportare

Propaganda Clothing

https://www.propagandaclothing.it/negozio/?\_collezione=fw26

Dolly Noire

https://www.dollynoire.com/it/collections/denari

Questi link rappresentano esempi concreti delle tipologie di collezioni
che il sistema dovrà poter analizzare.

Risultato atteso

Una nuova modalità di importazione che consenta di caricare intere
collezioni in modo semplice e automatico, mantenendo inalterato il
funzionamento delle procedure già esistenti.

Priorità: da realizzare.

1.2 --- Revisione dello scorrimento e della navigazione delle pagine

Obiettivo: verificare se lo scorrimento attuale delle pagine sia
corretto e intuitivo.

Analizzare il comportamento della navigazione e valutare se l'esperienza
d'uso possa essere migliorata.

Lascio libertà di valutazione tecnica per individuare la soluzione più
efficace, dando priorità alla semplicità di utilizzo e alla fluidità
della navigazione.

Prima di apportare modifiche, verificare il comportamento attuale e
individuare eventuali problemi concreti.

Priorità: da valutare.

1.3 --- Realizzazione dei video Entry

Obiettivo: realizzare tutti i video Entry previsti e integrarli nel
sito.

I video dovranno essere verificati sui principali dispositivi:

Smartphone.

Tablet.

Desktop.

Occorre assicurarsi che la visualizzazione, le proporzioni e
l'esperienza complessiva siano corrette su tutte le versioni del sito.

Priorità: da fare.

Stato: da iniziare.

2\. TOTUM --- Riorganizzazione delle applicazioni

2.1 --- Creazione di una struttura più indipendente e modulare

Obiettivo: riorganizzare l'ecosistema attuale di siti e applicazioni,
superando l'impostazione in cui numerosi progetti dipendono gli uni
dagli altri.

Vorrei che ogni applicazione avesse un proprio spazio autonomo e potesse
essere spostata, trasferita o riutilizzata in progetti differenti con il
minimo possibile di dipendenze.

L'obiettivo non è creare un unico sistema in cui tutte le applicazioni
condividono obbligatoriamente temi, codice e componenti, ma costruire
una struttura ordinata in cui ogni progetto possa funzionare
autonomamente.

Nuova organizzazione

Valutare la possibilità di rinominare l'attuale percorso /private in
/totum.

All'interno di TOTUM vorrei distinguere due categorie principali di
applicazioni.

A. Applicazioni private

Applicazioni destinate esclusivamente al mio utilizzo personale,
progettate come strumenti per semplificare e velocizzare le attività
quotidiane.

Queste applicazioni dovranno:

Essere raccolte in un'area privata e ordinata.

Avere ciascuna una propria identità e una propria organizzazione.

Essere accessibili tramite nome utente e password.

Poter essere sviluppate e aggiornate senza dipendere inutilmente dalle
altre applicazioni.

Essere trasferibili o riutilizzabili in altri progetti, quando
necessario.

B. Applicazioni autonome destinate ad altri utenti

Alcuni progetti devono invece essere completamente separati dall'area
privata e avere una propria gestione degli utenti e delle regole di
accesso.

In questa categoria rientrano, ad esempio:

The Village.

AstaLive, Draft Arena e League Arena, verificando l'organizzazione
attuale di questi progetti.

Travel, da ricollocare nella struttura che verrà definita.

Queste applicazioni dovranno poter gestire autonomamente le proprie
modalità di registrazione, gli utenti e le eventuali regole di accesso.

Indicazioni tecniche

Prima di intervenire, analizzare l'architettura attuale e individuare i
collegamenti tra i diversi progetti.

La riorganizzazione dovrà seguire alcuni principi:

Evitare dipendenze non necessarie tra applicazioni differenti.

Separare codice, funzionalità e componenti che non devono essere
condivisi.

Mantenere eventuali componenti comuni solo quando sono realmente utili.

Consentire a ogni applicazione di essere aggiornata e mantenuta
autonomamente.

Evitare che una modifica a un progetto comprometta il funzionamento
degli altri.

Pianificare gli interventi in modo graduale, senza compromettere le
applicazioni già funzionanti.

Non è necessario eliminare ogni forma di comunicazione tra applicazioni:
quando serve, potranno continuare a scambiarsi dati o utilizzare servizi
condivisi. L'obiettivo è evitare che questa comunicazione diventi una
dipendenza obbligatoria.

Risultato atteso

Creare un ecosistema ordinato e modulare, nel quale le applicazioni
private siano facilmente accessibili da TOTUM e gli altri progetti
mantengano la propria autonomia.

Priorità: progetto strutturale da pianificare per il futuro.

3\. THE VILLAGE --- Sviluppo, test e miglioramenti

3.1 --- Portare l'applicazione a uno stato operativo

Obiettivo: iniziare a utilizzare concretamente The Village, verificando
che tutte le funzionalità principali siano implementate e funzionino
correttamente.

Funzionalità da verificare

Registrazione degli utenti

Implementare o completare la registrazione.

Verificare che gli utenti possano creare e utilizzare correttamente il
proprio account.

Punteggi e classifiche

Verificare che i punteggi vengano calcolati e salvati correttamente.

Assicurarsi che le classifiche vengano aggiornate e mantenute nel tempo.

Controllare che i dati rimangano disponibili anche dopo aver chiuso e
riaperto l'applicazione.

Simulazione delle partite

Simulare diverse partite e situazioni.

Verificare il corretto funzionamento delle regole.

Controllare che i risultati vengano registrati correttamente.

Individuare e correggere eventuali errori.

Miglioramento estetico

Una volta verificato il funzionamento delle principali funzionalità,
lavorare sulla qualità visiva dell'applicazione.

L'obiettivo è portare l'esperienza estetica a un livello molto elevato,
valutando:

Animazioni curate.

Contenuti visivi dedicati.

Maggiore attenzione alle transizioni e alle interazioni.

Miglioramenti generali all'interfaccia e all'esperienza utente.

Le modifiche estetiche dovranno valorizzare il progetto senza
compromettere la semplicità d'uso o il corretto funzionamento delle
funzionalità esistenti.

Risultato atteso

Un'applicazione stabile, testata e pronta per essere utilizzata, con
un'interfaccia visivamente curata e un'esperienza completa.

Priorità: da definire.

Stato: da avviare e testare.

4\. GOOGLE FLOW --- Realizzazione dei video Entry

4.1 --- Animazione dell'adesivo HANGAR

Strumento: Google Flow.

Obiettivo: creare un'animazione realistica nella quale l'adesivo nero
HANGAR si stacca autonomamente dal muro, mostrando il retro bianco
consumato e lasciando visibile la parete sottostante.

Prompt completo

Starting from the exact original image, animate the black HANGAR sticker
peeling off the wall by itself, starting from the top edge, then falling
naturally under gravity. No hands or people.

The sticker bends and curls naturally as it detaches, but must not roll
up or crumple excessively. When it falls, it must remain mostly
extended, with a slight natural rotation.

Preserve the original black front, white HANGAR lettering, and exact
sticker design. All six letters must remain visible and unchanged
whenever the front faces the camera. Never make letters disappear or
change.

The back of the sticker must be white, slightly dirty and worn, like
aged paper. Reveal this white backside naturally as the sticker bends.

As the sticker detaches, reconstruct the wall underneath it, matching
the surrounding turquoise plaster, scratches, cracks, dirt, and
weathered texture seamlessly.

Keep the rest of the image completely static. Do not modify the
graffiti, surrounding stickers, lighting, or camera position. You may
extend the image beyond its original boundaries if necessary,
maintaining the same visual style.

Photorealistic, natural movement, realistic paper physics.

Risultato desiderato

L'animazione dovrà rispettare tutte le seguenti condizioni:

L'adesivo si stacca autonomamente, partendo dal bordo superiore.

Non devono comparire mani o persone.

Il movimento deve rispettare la gravità e il comportamento fisico della
carta.

L'adesivo deve rimanere prevalentemente disteso durante la caduta, senza
arrotolarsi o accartocciarsi eccessivamente.

Il retro deve essere bianco, leggermente sporco e consumato, come un
adesivo di carta invecchiato.

La parete sottostante deve essere ricostruita fedelmente, riproducendo
l'intonaco turchese, i graffi, le crepe e le tracce di sporco presenti
nella zona circostante.

Non devono rimanere vuoti, superfici artificiali o aree mancanti nel
muro.

Le sei lettere di HANGAR devono rimanere inalterate e riconoscibili ogni
volta che il lato frontale dell'adesivo è visibile.

Il resto dell'immagine deve rimanere immobile e invariato, compresi
graffiti, altri adesivi, illuminazione e inquadratura.

Priorità: da fare.

5\. PROGETTI FUTURI

5.1 --- Evoluzione dell'architettura delle applicazioni

Questa attività approfondisce la riorganizzazione strutturale descritta
nella sezione TOTUM e riguarda l'intero ecosistema dei siti e delle
applicazioni.

Attività da svolgere

Analizzare l'architettura attuale dei siti e delle applicazioni.

Individuare i collegamenti e le dipendenze tra i diversi progetti.

Definire una struttura autonoma per ciascuna applicazione.

Separare codice, funzionalità e componenti che non devono
necessariamente essere condivisi.

Valutare quali servizi, dati e componenti comuni sia effettivamente
utile mantenere.

Organizzare ogni applicazione in modo che possa essere sviluppata,
aggiornata e mantenuta autonomamente.

Ridurre il rischio che una modifica a un progetto comprometta gli altri.

Pianificare una migrazione graduale verso la nuova architettura,
verificando il funzionamento a ogni passaggio.

Risultato desiderato

Realizzare un ecosistema nel quale ogni applicazione sia un progetto
autonomo, con una propria organizzazione e un proprio ciclo di sviluppo.

Le applicazioni potranno comunicare tra loro quando necessario, senza
dipendere obbligatoriamente l'una dall'altra.

Priorità: futura.

Stato: da pianificare.

6\. NUOVE ATTIVITÀ E IDEE

Questa sezione è destinata a raccogliere tutte le nuove attività,
migliorie e idee che emergeranno nel tempo.

Ogni nuova voce dovrà essere inserita nella sezione più appropriata,
mantenendo una struttura chiara e facilmente consultabile.

Quando possibile, per ogni attività saranno indicati:

Progetto: sito o applicazione interessata.

Descrizione: cosa deve essere realizzato o modificato.

Obiettivo: risultato che si vuole ottenere.

Priorità: urgenza e importanza dell'attività.

Stato: da iniziare, in corso, in verifica o completata.

Note tecniche: eventuali vincoli, dipendenze o indicazioni utili.

Questa sezione dovrà essere aggiornata progressivamente, evitando di
disperdere idee e richieste tra documenti differenti.

7\. RIEPILOGO GENERALE DELLE PRIORITÀ

AGGIORNAMENTO DEL DOCUMENTO

Questo documento costituisce la roadmap generale dei progetti.

Le nuove richieste, le idee e le attività individuate durante lo
sviluppo dovranno essere aggiunte progressivamente, mantenendo una
distinzione chiara tra:

Attività immediate.

Miglioramenti dei progetti esistenti.

Nuove funzionalità.

Riorganizzazioni strutturali.

Idee e sviluppi futuri.

L'obiettivo è disporre di un unico riferimento aggiornato che permetta
di capire rapidamente cosa deve essere fatto, su quale progetto
intervenire e quali attività hanno la precedenza.

  -----------------------------------------------------------------------
  Priorità          Progetto          Attività          Stato
  ----------------- ----------------- ----------------- -----------------
  Da fare           Video Entry       Realizzare i      Da iniziare
                                      video con Google  
                                      Flow e integrarli 
                                      nel sito,         
                                      verificando       
                                      smartphone,       
                                      tablet e desktop. 

  Da realizzare     MINU              Aggiungere        Da pianificare
                                      l'importazione    
                                      automatica di     
                                      intere collezioni 
                                      e-commerce.       

  Da valutare       MINU              Verificare e      Da valutare
                                      migliorare lo     
                                      scorrimento e la  
                                      navigazione delle 
                                      pagine.           

  Da avviare        The Village       Completare le     Da avviare
                                      funzionalità,     
                                      verificare        
                                      punteggi e        
                                      classifiche e     
                                      simulare partite. 

  Futuro            TOTUM             Riorganizzare le  Da pianificare
                                      applicazioni      
                                      private e         
                                      definire una      
                                      struttura di      
                                      accesso ordinata. 

  Futuro            Ecosistema        Ridurre le        Da pianificare
                    applicazioni      dipendenze e      
                                      rendere i         
                                      progetti autonomi 
                                      e modulari.       
  -----------------------------------------------------------------------

## I.2 --- Nota integrativa: importazione delle collezioni in MINU

Richiesta sintetica originaria: aggiungere al gestionale la possibilità
di inserire un unico link di collezione e recuperare automaticamente
tutti i prodotti, senza dover inserire manualmente i singoli link.

Esempio indicato: https://www.dollynoire.com/it/collections/denari

La specifica completa, compresi gli esempi Propaganda Clothing e Dolly
Noire, la gestione delle pagine successive, il riutilizzo
dell'importatore esistente e la conferma per collezioni numerose, è
riportata nella sezione MINU della roadmap precedente.

# PARTE II --- OTTIMIZZAZIONE DELL'AMBIENTE DI SVILUPPO WEB SU MAC

# II.1 --- Specifica completa per l'analisi dell'ambiente e Claude Code

# PARTE III --- PROGETTO DI INTELLIGENZA ARTIFICIALE LOCALE

# III.1 --- Configurazione completa dell'ambiente AI locale

### PROGETTO AI LOCALE --- CONFIGURAZIONE COMPLETA

### 1. OBIETTIVO DEL PROGETTO

Voglio configurare sul mio Mac un sistema di intelligenza artificiale
che mi permetta di creare e modificare video, immagini, animazioni,
musica e altri contenuti multimediali, cercando di ottenere il massimo
senza pagare abbonamenti, servizi cloud o crediti aggiuntivi.

Voglio poter partire da immagini che creo io e animarle, generare video
da zero, modificare fotografie, realizzare effetti visivi e
automatizzare le operazioni attraverso Claude Code.

L'obiettivo è costruire un ambiente locale il più completo possibile, in
modo da sfruttare la potenza del mio Mac e non dipendere continuamente
da servizi esterni a pagamento.

Voglio che il sistema sia:

-   Il più completo possibile, compatibilmente con il mio hardware.

-   Gratuito, utilizzando software e modelli con licenze compatibili con
    il mio utilizzo.

-   In grado di funzionare localmente, senza inviare necessariamente i
    miei file a servizi esterni.

-   Utilizzabile per progetti reali, non soltanto per fare prove.

-   Integrabile con Claude Code, per automatizzare le attività e
    organizzare i flussi di lavoro.

-   Facile da avviare e utilizzare anche senza dover digitare
    continuamente comandi nel Terminale.

Importante: non voglio installare programmi inutili o scaricare modelli
a caso. Prima valutiamo cosa serve davvero e quanto spazio occupa.

### 2. IL MIO COMPUTER

Utilizzo un MacBook Pro con queste caratteristiche:

-   Processore: Apple M3 Max.

-   CPU: 14 core.

-   Memoria unificata: 36 GB.

-   Spazio libero sul disco al momento della pianificazione: circa 96
    GB.

-   Sistema operativo: macOS Tahoe.

Tutte le installazioni e le scelte dei modelli devono essere compatibili
con questo hardware.

In particolare, dobbiamo prestare attenzione alla memoria disponibile:
alcuni modelli video richiedono almeno 48 GB di RAM e quindi non sono
adatti alla mia configurazione, anche se il programma permette di
scaricarli.

Non voglio riempire il disco con modelli che non potrò utilizzare
correttamente.

### 3. PROGRAMMA PRINCIPALE: PHOSPHENE

Ho iniziato a installare Phosphene attraverso Pinokio.

Repository ufficiale:

https://github.com/mrbizarro/phosphene

Documentazione:

https://phosphene.video/docs/install/

### FAQ:

https://phosphene.video/docs/faq/

Phosphene sarà il punto di partenza per organizzare le attività di
generazione e modifica dei contenuti multimediali.

Voglio verificare che l'installazione funzioni correttamente e che sia
possibile utilizzare l'interfaccia dal browser, normalmente
all'indirizzo locale:

http://127.0.0.1:8198

L'indirizzo va verificato dopo l'avvio: non dobbiamo presumere che il
programma sia già funzionante soltanto perché è stato installato.

Cosa dobbiamo fare

## 1. Completare l'installazione di Phosphene attraverso Pinokio.

## 2. Verificare che tutti i componenti necessari siano stati scaricati correttamente.

## 3. Avviare il programma.

## 4. Aprire la sua interfaccia nel browser.

## 5. Controllare che non ci siano errori.

## 6. Verificare quali modelli sono già installati e quali mancano.

## 7. Configurare il sistema in base alle possibilità effettive del Mac.

## 8. Fare una prima prova di generazione o modifica di un contenuto.

Non reinstallare tutto se il programma è già installato: prima
controllare lo stato attuale e proseguire da lì.

### 4. GENERAZIONE VIDEO

La priorità è poter creare video localmente senza dover acquistare
crediti.

Voglio poter:

-   Generare video a partire da descrizioni testuali.

-   Animare immagini statiche.

-   Trasformare una fotografia in una breve sequenza animata.

-   Creare movimenti realistici.

-   Realizzare animazioni pubblicitarie e contenuti per social media.

-   Sperimentare con effetti cinematografici.

-   Creare video partendo da immagini che ho già preparato.

-   Provare a controllare meglio il movimento e la continuità delle
    scene, quando il modello lo consente.

Modello da valutare: LTX-2.5

LTX-2.5 è uno dei modelli da configurare per primo, se la versione
disponibile è compatibile con Phosphene e con il mio Mac.

Dobbiamo:

## 1. Verificare quali versioni del modello sono disponibili.

## 2. Scegliere una configurazione compatibile con 36 GB di memoria.

## 3. Installare soltanto i componenti necessari.

## 4. Provare prima la generazione di un video breve.

## 5. Provare successivamente l'animazione di un'immagine.

6\. Individuare le impostazioni che offrono il miglior equilibrio tra
qualità, velocità e consumo di memoria.

Non dobbiamo dare per scontato che tutte le funzioni del modello siano
utilizzabili. Alcune modalità avanzate possono richiedere più memoria di
quella disponibile sul mio computer.

Inizialmente preferisco ottenere video brevi e funzionanti, piuttosto
che tentare subito generazioni troppo pesanti.

### 5. GENERAZIONE E MODIFICA DELLE IMMAGINI

Voglio avere anche strumenti per creare e modificare immagini,
possibilmente nello stesso ambiente.

Le funzioni desiderate sono:

-   Generare immagini da testo.

-   Modificare fotografie esistenti.

-   Sostituire o modificare parti di un'immagine.

-   Ricostruire elementi mancanti.

-   Cambiare sfondi e ambientazioni.

-   Mantenere il più possibile invariati gli elementi che non devono
    essere modificati.

-   Creare immagini pubblicitarie.

-   Preparare immagini da animare successivamente.

-   Realizzare elaborazioni grafiche per siti web, negozi e progetti
    personali.

Modelli da valutare

### FLUX.2

Da valutare per la generazione di immagini, scegliendo una versione che
sia realmente utilizzabile con il mio hardware.

Qwen-Image-Edit

Da valutare per modificare immagini esistenti e intervenire su elementi
specifici.

Prima di installarli dobbiamo verificare:

-   Quali versioni sono supportate.

-   Quanta memoria richiedono.

-   Quanto spazio occupano.

-   Se funzionano correttamente su Apple Silicon.

-   Se sono già integrati in Phosphene.

-   Se esistono alternative più leggere e adatte alle mie esigenze.

Non scaricare più versioni dello stesso modello senza una ragione
concreta.

### 6. MONTAGGIO VIDEO

Voglio verificare se Phosphene dispone di strumenti integrati per
organizzare e modificare i video generati.

Se possibile, voglio poter:

-   Unire più clip.

-   Tagliare e ordinare le sequenze.

-   Preparare video per social media.

-   Combinare immagini, animazioni e altri contenuti.

-   Esportare i risultati in formati utilizzabili normalmente.

Se l'editor integrato non è sufficiente, valuteremo un programma
gratuito aggiuntivo.

L'obiettivo è evitare di installare software duplicati quando Phosphene
offre già le funzioni necessarie.

### 7. GENERAZIONE MUSICALE E AUDIO

Vorrei valutare anche la possibilità di generare musica e audio
localmente.

Modello da valutare: YuE2

Questo componente è facoltativo e può occupare circa 11 GB, secondo la
configurazione.

Prima di installarlo dobbiamo verificare:

-   Che cosa permette di generare.

-   Se è compatibile con il Mac.

-   Quanta memoria richiede.

-   Quanto spazio occupa realmente.

-   Se offre un vantaggio concreto rispetto alle alternative gratuite.

Lo installeremo soltanto se rimane spazio sufficiente per gli strumenti
principali.

L'audio è una priorità secondaria rispetto a video e immagini.

### 8. INTEGRAZIONE CON CLAUDE CODE

Uso Claude Code per programmare, sviluppare applicazioni e automatizzare
procedure.

Vorrei poterlo utilizzare anche per gestire i flussi di lavoro
multimediali.

Per esempio, vorrei arrivare a chiedergli di:

-   Organizzare le immagini di un progetto.

-   Preparare i prompt per la generazione.

-   Lanciare operazioni supportate da strumenti locali.

-   Elaborare più immagini in successione.

-   Organizzare i video generati.

-   Automatizzare conversioni e operazioni ripetitive.

-   Preparare cartelle di output ordinate.

-   Integrare i contenuti generati nei miei progetti web.

Dobbiamo distinguere due aspetti:

Generazione: è il modello AI a creare o modificare immagini e video.

Automazione: Claude Code può aiutarmi a programmare e orchestrare le
operazioni, ma non sostituisce automaticamente il modello di
generazione.

Voglio quindi capire come collegare concretamente Claude Code agli
strumenti installati, utilizzando interfacce, API locali, script o altri
metodi realmente supportati.

Non voglio che Claude Code invii automaticamente tutto a servizi a
pagamento.

Prima di creare integrazioni complesse, dobbiamo verificare quali
strumenti Phosphene mette effettivamente a disposizione.

### 9. COSTI E LICENZE

La mia priorità è evitare spese aggiuntive.

Voglio privilegiare:

-   Software gratuito.

-   Modelli utilizzabili localmente.

-   Strumenti open source, quando disponibili.

-   Generazioni senza crediti acquistabili.

-   Soluzioni che non richiedano abbonamenti obbligatori.

Attenzione: software gratuito non significa necessariamente che ogni
modello sia libero da qualsiasi restrizione.

Prima di utilizzare un modello per un progetto commerciale, dobbiamo
controllare la licenza specifica, soprattutto per immagini e video
destinati a pubblicità, siti web o negozi.

Non installare modelli che richiedono servizi cloud a pagamento senza
avermelo spiegato prima.

Non utilizzare modelli con limitazioni geografiche o condizioni d'uso
incompatibili con il mio utilizzo.

### 10. GESTIONE DELLO SPAZIO DISCO

All'inizio del progetto avevo circa 96 GB liberi.

L'installazione di base e i componenti aggiuntivi di Phosphene possono
occupare complessivamente diverse decine di gigabyte, a seconda di ciò
che viene scaricato.

Dobbiamo quindi:

## 1. Controllare lo spazio libero attuale.

## 2. Verificare quanto occupa già Phosphene.

## 3. Individuare i modelli effettivamente installati.

## 4. Calcolare lo spazio necessario prima di ogni nuovo download.

## 5. Evitare duplicati e file inutilizzati.

## 6. Non cancellare file o modelli senza aver verificato che siano superflui.

## 7. Lasciare spazio libero sufficiente per macOS e per il funzionamento dei programmi.

Non voglio installare tutti i componenti opzionali indiscriminatamente.

La configurazione deve essere selettiva: prima video e immagini, poi
eventualmente audio e strumenti secondari.

### 11. ORDINE DI LAVORO

Procediamo in questo ordine.

FASE 1 --- Completare Phosphene

-   Verificare a che punto è l'installazione.

-   Completare gli eventuali download già in corso.

-   Avviare il programma.

-   Aprire l'interfaccia.

-   Verificare che funzioni.

FASE 2 --- Controllare il sistema

-   Controllare lo spazio libero.

-   Verificare i componenti installati.

-   Verificare eventuali errori.

-   Controllare la compatibilità dei modelli con 36 GB di memoria.

FASE 3 --- Configurare i video

-   Installare o configurare LTX-2.5, se compatibile.

-   Generare un primo video breve.

-   Provare l'animazione di una mia immagine.

-   Ottimizzare le impostazioni.

FASE 4 --- Configurare le immagini

-   Installare o configurare un modello adatto alla generazione.

-   Installare o configurare un modello adatto all'editing.

-   Fare prove pratiche con immagini reali.

-   Verificare qualità, tempi e limiti.

FASE 5 --- Configurare gli strumenti complementari

-   Verificare l'editor video.

-   Valutare l'audio.

-   Installare altri strumenti soltanto se utili e compatibili.

FASE 6 --- Integrare Claude Code

-   Individuare le possibilità di automazione.

-   Configurare i collegamenti locali necessari.

-   Creare procedure ripetibili.

-   Organizzare input, output e cartelle di progetto.

FASE 7 --- Test finale

Verificare che io riesca effettivamente a:

-   Generare un video.

-   Animare un'immagine.

-   Generare un'immagine.

-   Modificare un'immagine.

-   Esportare e organizzare i risultati.

-   Automatizzare almeno una procedura tramite Claude Code.

Solo dopo questi test considereremo il sistema configurato.

### 12. COME VOGLIO CHE MI GUIDI

Voglio essere guidato passo dopo passo.

Per ogni operazione:

## 1. Spiegami brevemente che cosa dobbiamo fare.

## 2. Dimmi esattamente dove devo cliccare oppure quale comando devo eseguire.

## 3. Se serve il Terminale, dammi il comando completo, pronto da copiare.

4\. Aspetta che ti comunichi il risultato prima di passare al passaggio
successivo, soprattutto durante installazioni e configurazioni.

## 5. Se compare un errore, analizziamolo prima di tentare altre soluzioni.

## 6. Non farmi ripetere operazioni già completate.

## 7. Non farmi reinstallare programmi che funzionano.

## 8. Non modificare configurazioni funzionanti senza una ragione precisa.

9\. Non darmi elenchi di dieci operazioni da eseguire tutte insieme
quando possiamo procedere gradualmente.

## 10. Evita spiegazioni eccessivamente tecniche se non sono necessarie.

Se devi farmi eseguire un comando, preferisco un blocco di codice
completo e copiabile.

Se devi modificare un file di configurazione o uno script, dammi il
contenuto completo quando è ragionevole farlo, specificando chiaramente
dove inserirlo.

Non dare per scontato che io abbia completato un passaggio solo perché
ne abbiamo parlato.

### 13. STATO DEL PROGETTO DA AGGIORNARE

Quando riprenderemo questa conversazione, aggiorneremo queste
informazioni:

-   Installazione di Phosphene: DA VERIFICARE.

-   Installazione tramite Pinokio: AVVIATA.

-   Download dei componenti: DA VERIFICARE.

-   Avvio di Phosphene: DA VERIFICARE.

-   Apertura dell'interfaccia nel browser: DA VERIFICARE.

-   Modello video LTX-2.5: DA VERIFICARE.

-   Modelli per generare e modificare immagini: DA VERIFICARE.

-   Editor video: DA VERIFICARE.

-   Generazione musicale: NON ANCORA DECISA.

-   Integrazione con Claude Code: DA CONFIGURARE.

-   Spazio libero aggiornato: DA VERIFICARE.

Non considerare completata nessuna attività finché non abbiamo
verificato che funzioni.

### 14. PRIMA COSA DA FARE QUANDO RIPRENDIAMO

Ripartiamo dal punto esatto in cui si è interrotta la configurazione.

Ti comunicherò se:

-   A\) Phosphene sta ancora scaricando o installando componenti.

-   B\) L'installazione è terminata e vedo il pulsante Start in Pinokio.

-   C\) Phosphene è già avviato e riesco ad aprirlo nel browser.

In base alla mia risposta, guidami nel passaggio successivo senza
ricominciare dall'inizio.

Obiettivo finale: avere sul mio Mac un ambiente AI locale gratuito, il
più completo possibile, per generare e modificare video e immagini, con
eventuali strumenti audio e la possibilità di automatizzare i flussi di
lavoro tramite Claude Code, rispettando i limiti hardware e senza
acquistare crediti aggiuntivi.

# APPENDICE --- GESTIONE DEL DOCUMENTO MASTER

-   Questo documento è il riferimento unico per attività, specifiche e
    obiettivi dei progetti descritti.

-   Le nuove richieste dovranno essere inserite nella parte e nella
    sezione del progetto corretti, senza creare duplicati.

-   Per ogni attività, quando possibile, mantenere: progetto, obiettivo,
    descrizione, priorità, stato, note tecniche, dipendenze e modalità
    di verifica.

-   Gli stati devono distinguere chiaramente tra da pianificare, da
    iniziare, in corso, in verifica e completato.

-   Un'attività va considerata completata soltanto dopo una verifica
    concreta del risultato.

-   Le modifiche a siti, applicazioni, configurazioni e ambiente di
    sviluppo devono rispettare le regole di analisi preventiva,
    approvazione e protezione dei dati definite nelle rispettive
    sezioni.

    NOTA:

    -QUANDO SI PARLA DI TOTUM SI FA RIFERIMENTO A CHIARAPUCCI

    \- LA CARTELLA è DIVISA CON I PROGETTI che iniziano in www e DOMUS
    CHE è LA WEBAPP DA ARCHITETTO SU CUI DOVREMO LAVORARE DA ZERO.

    NELLA CARTELLA /Users/andreagiacometti/Local Sites/\_VARIO trovi
    cartella con plugin e skill e altro materiale utile
