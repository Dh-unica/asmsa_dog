# Requisiti del progetto "Ottimizzazione performance sito"

## Descrizione del problema
Il sito è costituito da una serie di pagine che contengono blocchi realizzati con il modulo pragraph. In particolare le pagine più lente sono quelle costruite con i blocchi "Omeka map" e "Omeka map timeline" che usano rispettivamente il template block--omeka-map.html.twig e block--omeka-map-timeline.html.twig. 
Il modulo `dog` recupera risorse Omeka da un'API esterna REST per consentire ai redattori di costruire pagine con questi oggetti. Tuttavia, esistono problemi di performance quando il numero di oggetti supera i 20 oggetti omeka. Attualmente, ogni volta che viene richiesta una risorsa, sia nella fase di costruzione della pagina attraverso la selezione di singoli oggetti omeka sia nella fase di visualizzazione dei nodi con blocchi nei quali sono stati aggiunti oggetti omeka, viene invocato un metodo che chiama in tempo reale l'API per recuperare le informazioni, rallentando moltissimo tutta l'interfaccia e l'interazione con la stessa.

## Obiettivo
Migliorare sensibilmente le performance del sito sia nella visualizzazione delle pagine che contengono i blocchi omeka map e omeka map timeline che usano rispettivamente il template block--omeka-map.html.twig e block--omeka-map-timeline.html.twig. sia nella redazione della pagina stessa attarverso layout builder e la selezione dei singoli oggetti omeka nel blocchi sopra citati.


## Piano di lavoro aggiornato

### 1. Sistema di Caching Multi-Livello

Creare un sistema di caching a tre livelli:
- **Livello 1 (Cache Batch)**: 
  - Processo giornaliero che preleva tutti i dati da Omeka
  - Utilizza il servizio `dog.omeka_resource_fetcher` con cache manager
  - Cache persistente in tabella cache Drupal con chiavi:
    - `dog:resource:{type}:{id}` per risorse singole
    - `dog:all_resources:{type}` per elenchi completi

- **Livello 2 (Cache Views)**:
  - Implementare caching dei risultati delle viste `resource_library`
  - Utilizzare cache tags specifici per le risorse Omeka
  - Supporto per paginazione efficiente

- **Livello 3 (Cache UI)**:
  - Implementare caching lato client per risorse già visualizzate
  - Ridurre chiamate AJAX duplicate
  - Migliorare gestione stati UI per evitare ricaricamenti non necessari

### 2. Ottimizzazione ResourceFetcher

Modificare `OmekaResourceFetcher` per:
- Ricerca SEMPRE nella cache prima di qualsiasi altra operazione
- Se non trovato in cache, RESTITUISCE NULL
- MAI chiamare l'API in tempo reale direttamente
- Implementare cache tags per invalidazione selettiva

### 3. Gestione Cache per Moduli Specifici

- **Modulo DOG Library**:
  - Modificare widget per leggere sempre dalla cache batch
  - Implementare feedback visivi quando la cache è vuota
  - Ottimizzare template per ridurre richieste duplicate

- **Modulo Omeka Utils**:
  - Integrare completamente con sistema di cache DOG
  - Rimuovere fallback a `file_get_contents()`
  - Utilizzare HTTP client di Drupal per gestione efficiente delle connessioni

### 4. Cron Job e Batch Processing

Implementare:
- `hook_cron` configurabile per:
  - Orario di esecuzione
  - Tipi di risorse da aggiornare
  - Batch size per evitare timeout
  - Timeout massimo per l'aggiornamento

- Sistema di queue per grandi importazioni
- Retry mechanism per chiamate fallite
- Circuit breaker per prevenire failure a cascata

### 5. Sistema di Cache Invalidation

Aggiungere:
- Interfaccia di amministrazione per:
  - Vedere lo stato della cache
  - Forzare aggiornamento manuale
  - Invalidazione parziale o totale
  - Monitoraggio hit/miss ratio

- Cache tags specifici per:
  - Tipi di risorse
  - Template di risorse
  - URL pubblici

### 6. Monitoraggio e Logging

Implementare:
- Logging dettagliato per:
  - Successo/fallimento degli aggiornamenti
  - Tempo di esecuzione
  - Numero di risorse processate
  - Stato della cache

- Metriche di performance:
  - Tempi di risposta API
  - Ratio cache hit/miss
  - Utilizzo memoria
  - Tempo di rendering UI

### 7. Configurazione e Tuning

Aggiungere variabili di configurazione per:
- Orario di esecuzione cron
- Tipi di risorse da monitorare
- Batch size
- Timeout massimo
- TTL della cache
- Limite massimo di risorse per batch

### 8. Sistema di Backup Cache

Implementare:
- Backup giornaliero della cache
- Storico versioni
- Ripristino selettivo
- Verifica integrità dati

### 9. Ottimizzazioni UI

Per il modulo `dog_library`:
- Implementare lazy loading per le risorse
- Ottimizzare rendering griglia/tabella
- Ridurre richieste AJAX
- Migliorare feedback utente durante caricamento

### 10. Testing e Verifica

Implementare:
- Test di performance
- Test di carico
- Test di resilienza
- Test di integrazione con tutti i moduli
- Benchmarking prima/dopo implementazione

## Risultati attesi

Questa versione:
- Garantisce sempre risposta dalla cache
- Mai chiamata in tempo reale alla risorsa esterna
- Gestisce correttamente gli errori
- Fornisce strumenti di monitoraggio e amministrazione
- Ottimizza l'interazione UI
- Migliora significativamente i tempi di risposta
- Riduce il carico sul server Omeka
- Fornisce feedback utente più informativo
- Supporta scalabilità per grandi collezioni di risorse

