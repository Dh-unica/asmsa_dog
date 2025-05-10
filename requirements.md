# Requisiti del progetto "Ottimizzazione performance sito"

## Descrizione del problema
Il modulo `dog` recupera risorse Omeka da un'API esterna REST per consentire ai redattori di costruire pagine con questi oggetti. Tuttavia, esistono problemi di performance quando il numero di oggetti supera i 20. Attualmente, ogni volta che viene richiesta una risorsa, viene invocato un metodo che chiama in tempo reale l'API per recuperare le informazioni.
Le pagine sono costruite attraverso l'uso del modulo layout builder che consente di includere facilmente blocchi realizzati con il modulo pragraph. In particolare le pagine più lente sono quelle costruite con i blocchi "Omeka map" e "Omeka map timeline" che usano rispettivamente il template block--omeka-map.html.twig e block--omeka-map-timeline.html.twig. 

## Obiettivo
Migliorare sensibilmente le performance del sito sia nella visualizzazione delle pagine che contengono i blocchi omeka map e omeka map timeline che usano rispettivamente il template block--omeka-map.html.twig e block--omeka-map-timeline.html.twig. sia nella redazione della pagina stessa attarversio layout builder e la selezione dei singoli oggetti omeka nel blocchi sopra citati.


## Piano di lavoro (DA MIGLIOARRE)

Implementare un sistema di caching che:
- Esegua il prefetch di tutte le risorse in un momento separato
- Utilizzi sempre la cache invece di chiamare l'API in tempo reale
- Fornisca feedback agli utenti quando i dati non sono disponibili in cache

### 1. Cache Batch Update
Creare un processo che si esegue una volta al giorno:
- Recupererà tutti i dati da Omeka
- Li memorizzerà in una cache persistente
- Esegue un aggiornamento completo ogni 24 ore

### 2. Cache Storage
Utilizzare la tabella cache di Drupal con la seguente struttura:
- `dog:resource:{type}:{id}` per risorse singole
- `dog:all_resources:{type}` per l'elenco completo di risorse

### 3. Modifiche al ResourceFetcher
Modificare `OmekaResourceFetcher` per:
- RICERCA SEMPRE SOLO NELLA CACHE
- Se non trovato, RESTITUISCE NULL
- MAI CHIAMARE LA RISORSA ESTERNA IN TEMPO REALE

### 4. Cron Job
Implementare un `hook_cron` che esegue l'aggiornamento completo, configurabile tramite `settings.yml` per:
- Orario di esecuzione
- Tipi di risorse da aggiornare
- Batch size per evitare timeout

### 5. Cache Invalidation
Aggiungere un meccanismo per forzare l'aggiornamento della cache con:
- Interfaccia di amministrazione per trigger manuale
- Possibilità di invalidazione parziale o totale

### 6. Gestione Errori
Se la cache è vuota (prima esecuzione o problemi):
- Restituire un messaggio di errore
- Non cercare di recuperare i dati in tempo reale
- Suggestere di attendere il prossimo aggiornamento

### 7. Monitoraggio
Aggiungere logging per monitorare:
- Successo/fallimento degli aggiornamenti
- Tempo di esecuzione
- Numero di risorse processate

### 8. Configurazione
Aggiungere variabili di configurazione per:
- Orario di esecuzione
- Tipi di risorse da monitorare
- Batch size
- Timeout massimo per l'aggiornamento

### 9. Interfaccia Ammin
Aggiungere una pagina di amministrazione per:
- Vedere lo stato della cache
- Forzare un aggiornamento manuale
- Vedere gli ultimi log

### 10. Backup
Implementare un sistema di backup della cache con:
- Possibilità di ripristinare una cache precedente in caso di problemi
- Storico delle versioni della cache

## Risultati attesi
Questa versione:
- Garantisce sempre risposta dalla cache
- Mai chiamata in tempo reale alla risorsa esterna
- Gestisce correttamente gli errori
- Fornisce strumenti di monitoraggio e amministrazione

## Approvazione
Vuoi che proceda con l'implementazione di questa strategia?