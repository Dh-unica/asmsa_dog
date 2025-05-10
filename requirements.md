# Requisiti del progetto "Ottimizzazione performance sito"

## Descrizione del problema
Il sito è costituito da una serie di pagine che contengono blocchi realizzati con il modulo pragraph. In particolare le pagine più lente sono quelle costruite con i blocchi "Omeka map" e "Omeka map timeline" che usano rispettivamente il template block--omeka-map.html.twig e block--omeka-map-timeline.html.twig. 
Un'altra azione molto lenta è la costruzione della pagina con layout builder e la selezione dei singoli oggetti omeka nel blocchi sopra citati. In questo caso viene invocato un metodo che chiama in tempo reale l'API per recuperare le informazioni, rallentando moltissimo tutta l'interfaccia e l'interazione con la stessa. Il template usati sono:
container--resource-library-content.html.twig
container--resource-library-widget-selection.html.twig
dog-omeka-resource--library.html.twig
fieldset--resource-library-widget.html.twig
resource-library-item.html.twig
resource-library-wrapper.html.twig
views-view--resource-library.html.twig

Contenuti in web/modules/custom/dog/modules/dog_library/templates


Il modulo `dog` `dog_library` e `dog_omeka_utils` recuperano le risorse Omeka da un'API esterna REST per consentire ai redattori di costruire pagine con questi oggetti. L'indirizzo base per l'API è https://<base_url>/api/. A volte il server risponde all'indirizzo http://storia.dh.unica.it/risorse e quindi va tenuto conto che la base della url è quella indicata e lo /api va aggiunto senza altre trasformazioni. Tuttavia, esistono problemi di performance quando il numero di oggetti supera i 20 oggetti omeka. Attualmente, ogni volta che viene richiesta una risorsa, sia nella fase di costruzione della pagina attraverso la selezione di singoli oggetti omeka sia nella fase di visualizzazione dei nodi con blocchi nei quali sono stati aggiunti oggetti omeka, viene invocato un metodo che chiama in tempo reale l'API per recuperare le informazioni, rallentando moltissimo tutta l'interfaccia e l'interazione con la stessa.

## Obiettivo
Migliorare sensibilmente le performance del sito sia nella visualizzazione delle pagine che contengono i blocchi omeka map e omeka map timeline che usano rispettivamente il template block--omeka-map.html.twig e block--omeka-map-timeline.html.twig, sia nella redazione della pagina stessa attraverso layout builder e la selezione dei singoli oggetti omeka nei blocchi sopra citati.

---

## Piano di lavoro per l'ottimizzazione delle performance

### 1. Analisi tecnica dettagliata
- **Mappatura delle chiamate API**: Identificare tutti i punti del codice (moduli dog, dog_library, dog_omeka_utils) in cui vengono effettuate chiamate sincrone all'API Omeka.
- **Analisi dei template Twig**: Analizzare i template coinvolti per individuare eventuali colli di bottiglia nella fase di rendering.
- **Elenco puntuale delle informazioni API richieste dai template:**
  - **block--omeka-map.html.twig**
    - ID oggetto Omeka
    - Titolo
    - Descrizione
    - Coordinate geografiche (latitudine, longitudine)
    - Immagine principale
    - Eventuali metadati aggiuntivi (es. autore, data)
  - **block--omeka-map-timeline.html.twig**
    - ID oggetto Omeka
    - Titolo
    - Intervallo temporale/eventi per timeline
    - Descrizione
    - Coordinate geografiche (se presenti)
    - Immagine principale
    - Metadati specifici per timeline (es. data inizio/fine, tipo evento)
  - **container--resource-library-content.html.twig**
    - Lista oggetti Omeka selezionati
    - Titolo e descrizione sintetica
    - Thumbnail/immagine
    - Stato pubblicazione
  - **container--resource-library-widget-selection.html.twig**
    - Lista completa degli oggetti Omeka disponibili per la selezione
    - Titolo, descrizione breve, thumbnail
    - Eventuali filtri/metadata per la ricerca
  - **dog-omeka-resource--library.html.twig**
    - Dettaglio oggetto Omeka selezionato
    - Tutti i metadati principali: titolo, descrizione, immagini, autore, data, link a risorsa originale
  - **fieldset--resource-library-widget.html.twig**
    - Informazioni di riepilogo sugli oggetti selezionati
    - Titolo, stato, thumbnail
  - **resource-library-item.html.twig**
    - Titolo, descrizione, thumbnail, link dettagliato
  - **resource-library-wrapper.html.twig**
    - Aggregazione di più item: titoli, immagini, descrizione sintetica
  - **views-view--resource-library.html.twig**
    - Lista oggetti Omeka filtrati o paginati
    - Titolo, descrizione, thumbnail, eventuali metadati di filtro (autore, data, categoria)
- **Monitoraggio delle performance attuali**: Utilizzare strumenti come Devel, XHProf, o Blackfire per misurare i tempi di caricamento e identificare le query/API più lente.

### 2. Proposte di soluzioni tecniche
- **Servizio di pre-caricamento batch in cache**
  - Progettare e implementare un servizio custom che recupera tutte le informazioni necessarie dai servizi Omeka tramite API e le memorizza nella cache di Drupal (cache bin personalizzati, cache API, cache tags e contexts).
  - Il processo di aggiornamento della cache dovrà essere eseguito esclusivamente tramite:
    - Un batch schedulato (cron job giornaliero)
    - Un bottone/manual trigger accessibile da backend per avvio manuale del processo di aggiornamento cache
  - La struttura della cache dovrà essere pensata per garantire rapidità di accesso e coerenza dei dati.
  - Gestire la scadenza e l’invalidazione della cache solo tramite i processi batch/manuali, senza fallback live.
- **Eliminazione delle chiamate API in tempo reale**
  - Tutte le chiamate alle API Omeka in tempo reale dovranno essere rimosse dal codice dei moduli e dei template.
  - I template e le funzionalità di backend dovranno accedere esclusivamente ai dati già presenti in cache.
  - In caso di cache mancante o dati non aggiornati, visualizzare un messaggio di errore o placeholder, senza effettuare alcuna chiamata live.
- **Ottimizzazione dei template Twig**
  - Minimizzare la logica all’interno dei template, delegando il recupero dati a servizi PHP e preprocess function che leggono esclusivamente dalla cache.
  - Utilizzare render array e cache Drupal per massimizzare il riutilizzo dei dati già caricati.
- **Gestione errori**
  - Gestire in modo chiaro la mancanza di dati in cache con messaggi d’errore o placeholder, senza alcun fallback su chiamate live.

### 3. Roadmap delle attività
1. **Fase 1: Analisi e misurazione**
   - Mappatura delle attuali chiamate API e dei template che le utilizzano
   - Benchmark delle performance attuali e analisi dei dati necessari ai template
2. **Fase 2: Progettazione del servizio di pre-caricamento**
   - Definizione della strategia di caching (bin, tag, contexts) e della struttura dati in cache
   - Progettazione del servizio batch per il recupero e la memorizzazione dei dati Omeka
   - Progettazione dell’interfaccia per avvio manuale del batch (bottone backend)
3. **Fase 3: Implementazione**
   - Sviluppo del servizio batch e del bottone manuale
   - Refactoring dei moduli custom e dei template per eliminare tutte le chiamate API in tempo reale e utilizzare solo la cache
   - Gestione degli errori in caso di dati non disponibili in cache
4. **Fase 4: Test e validazione**
   - Test funzionali, di performance e di coerenza dati
   - Monitoraggio tempi di risposta e carico server
5. **Fase 5: Documentazione e formazione**
   - Aggiornamento documentazione tecnica relativa al nuovo flusso dati
   - Formazione ai redattori e agli amministratori sull’uso del bottone di aggiornamento manuale e sulla gestione dei dati in cache

### 4. Raccomandazioni su test e monitoraggio
- **Test automatizzati**: Implementare test PHPUnit/Behat per le nuove logiche di caching e recupero dati.
- **Monitoraggio continuo**: Integrare strumenti di monitoraggio per rilevare regressioni sulle performance.
- **Revisione periodica**: Prevedere una revisione periodica delle strategie di caching e ottimizzazione, soprattutto in caso di aumento dei dati Omeka.

---

## Considerazioni finali
- Tutte le ottimizzazioni dovranno rispettare le best practice Drupal 10 (security, performance, manutenibilità, DRY, standard di codifica).
- È fondamentale garantire la coerenza dei dati tra Drupal e Omeka, gestendo correttamente cache invalidation e sincronizzazione.
- La collaborazione tra sviluppatori e redattori sarà essenziale per validare le soluzioni proposte e garantire un’esperienza utente ottimale.


