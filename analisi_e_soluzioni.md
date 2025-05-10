# Ottimizzazione performance sito Drupal – Analisi e Proposte Tecniche

## Fase 1: Analisi tecnica dettagliata

### 1.1 Mappatura delle chiamate API
- I moduli custom (`dog`, `dog_library`, `dog_omeka_utils`) effettuano chiamate sincrone alle API Omeka per recuperare dati sugli oggetti da visualizzare e selezionare nei blocchi personalizzati.
- Le chiamate sono distribuite sia nella fase di rendering delle pagine pubbliche sia nell’interfaccia di redazione (Layout Builder, selettori di oggetti Omeka).
- Ogni richiesta di visualizzazione o selezione comporta una o più chiamate API, con conseguente rallentamento quando il numero di oggetti supera le 20 unità.

### 1.2 Analisi dei template Twig coinvolti
I template che richiedono dati Omeka sono:
- `block--omeka-map.html.twig`
- `block--omeka-map-timeline.html.twig`
- `container--resource-library-content.html.twig`
- `container--resource-library-widget-selection.html.twig`
- `dog-omeka-resource--library.html.twig`
- `fieldset--resource-library-widget.html.twig`
- `resource-library-item.html.twig`
- `resource-library-wrapper.html.twig`
- `views-view--resource-library.html.twig`

### 1.3 Informazioni API richieste (per template e campi JSON)

- **block--omeka-map.html.twig**
  - `id` (ID oggetto Omeka)
  - `title` (Titolo)
  - `description` (Descrizione)
  - `media[0].original_url` (Immagine principale)
  - `metadata.latitude` (Latitudine)
  - `metadata.longitude` (Longitudine)
  - `metadata.author` (Autore, opzionale)
  - `metadata.date` (Data, opzionale)

- **block--omeka-map-timeline.html.twig**
  - `id`
  - `title`
  - `description`
  - `media[0].original_url`
  - `metadata.timeline.start_date` (Data inizio evento)
  - `metadata.timeline.end_date` (Data fine evento)
  - `metadata.timeline.label` (Etichetta evento)
  - `metadata.latitude` (opzionale)
  - `metadata.longitude` (opzionale)

- **container--resource-library-content.html.twig**
  - `id`
  - `title`
  - `description`
  - `media[0].thumbnail_url` (Thumbnail)
  - `status` (Stato pubblicazione)

- **container--resource-library-widget-selection.html.twig**
  - `id`
  - `title`
  - `description`
  - `media[0].thumbnail_url`
  - `metadata.category` (Categoria)
  - `metadata.tags[]` (Tag)

- **dog-omeka-resource--library.html.twig**
  - `id`
  - `title`
  - `description`
  - `media[]` (Tutte le immagini: `original_url`, `thumbnail_url`)
  - `metadata.author`
  - `metadata.date`
  - `metadata.source_url` (Link risorsa originale)

- **fieldset--resource-library-widget.html.twig**
  - `id`
  - `title`
  - `status`
  - `media[0].thumbnail_url`

- **resource-library-item.html.twig**
  - `id`
  - `title`
  - `description`
  - `media[0].thumbnail_url`
  - `metadata.detail_url` (Link dettagliato)

- **resource-library-wrapper.html.twig**
  - `items[]` (array di oggetti)
    - Per ciascun item: `id`, `title`, `media[0].thumbnail_url`, `description`

- **views-view--resource-library.html.twig**
  - `id`
  - `title`
  - `description`
  - `media[0].thumbnail_url`
  - `metadata.author`
  - `metadata.date`
  - `metadata.category`

### 1.4 Monitoraggio delle performance attuali
- Utilizzo di Devel, XHProf o Blackfire per analizzare tempi di risposta e identificare i colli di bottiglia nelle chiamate API e nella fase di rendering.
- Raccolta di metriche su tempi di caricamento delle pagine e operazioni di redazione.

---

## Fase 2: Proposta di soluzioni tecniche

### 2.1 Servizio di pre-caricamento batch in cache
- **Obiettivo:** Eliminare ogni chiamata API live durante la visualizzazione e la redazione, garantendo che tutti i dati necessari siano già disponibili in cache.
- **Funzionamento:**
  - Un servizio custom (Drupal service) recupera periodicamente (batch schedulato giornaliero) tutte le informazioni necessarie dalle API Omeka e le memorizza nella cache Drupal (cache bin dedicato).
  - È previsto un bottone/manual trigger in backend per avviare manualmente il processo di aggiornamento cache.
  - La struttura della cache sarà progettata per accesso rapido e coerenza dei dati, con chiavi strutturate per ID oggetto e tipo di dato.
- **Gestione scadenza/invalida cache:**
  - La cache viene aggiornata solo tramite batch/manual trigger: nessuna chiamata live o fallback.
  - In caso di dati mancanti, il sistema mostra placeholder o messaggi di errore, MAI effettua chiamate live.

### 2.2 Eliminazione chiamate API in tempo reale
- Tutti i moduli e i template Twig dovranno essere refactorizzati per leggere esclusivamente dalla cache.
- Le funzioni di recupero dati (es. preprocess, servizi) dovranno restituire solo dati presenti in cache.
- In caso di cache mancante, visualizzazione di messaggi di errore/placeholder.

### 2.3 Ottimizzazione template Twig
- Tutta la logica di recupero dati sarà spostata in servizi PHP e preprocess function.
- I template Twig riceveranno solo dati già disponibili, riducendo la complessità e migliorando la cacheabilità.
- Utilizzo di render array e sistemi di cache Drupal per massimizzare la performance.

### 2.4 Gestione errori
- Implementazione di messaggi di errore chiari o placeholder in caso di dati non disponibili in cache.
- Nessun fallback su chiamate live in nessuna circostanza.

### 2.5 Schema dati suggerito per la cache
- Chiave: `omeka_resource:{id}`
- Valori: array associativo con tutti i campi richiesti dai vari template (titolo, descrizione, coordinate, immagini, timeline, metadati, ecc.)
- Possibilità di cache bin separati per tipi di oggetti o per collezioni di oggetti (es. liste per selezione)

---

## Considerazioni finali
- Questa architettura garantisce performance elevate, prevedibilità e assenza di colli di bottiglia dovuti a chiamate esterne.
- La soluzione è allineata alle best practice Drupal 10: caching, separazione logica, sicurezza e manutenibilità.
- Il prossimo step sarà la progettazione tecnica dettagliata del servizio batch, della struttura dati in cache e dell’interfaccia di aggiornamento manuale.
