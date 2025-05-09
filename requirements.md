Requisiti del progetto

@dog Il modulo dog recupera resource omeka da una API esterna di tipo rest per consentire ai redattori di costruire pagine con questi oggetti. Ho però grossi problemi di performance quando gli oggetti sono più di 20. Ogni volta viene invocato il metodo/classe che chiama in tempo reale la API e recupera le informazioni. Analizza come il modulo è fatto e implementa un sistema che metta in cache tutte le chiamate in un momento separato e fai in modo che anziché chiamare in tempo reale ogni volta la risorsa esterna venga chiamata la cache. Leggi il documento @README.md per avere chiaro in che modo intervenire. Prima dimimplememtare qualcosa spiega quello che intedi fare e aspetta la mia conferma


Cache Batch Update:
Creare un processo che si esegue una volta al giorno
Questo processo:
Recupererà tutti i dati da Omeka
Li memorizzerà in una cache persistente
Aggiornamento completo ogni 24 ore
Cache Storage:
Utilizzare la tabella cache di Drupal
Struttura della chiave cache:
dog:resource:{type}:{id} per risorse singole
dog:all_resources:{type} per l'elenco completo di risorse
Modifiche al ResourceFetcher:
Modificare OmekaResourceFetcher per:
RICERCA SEMPRE SOLO NELLA CACHE
Se non trovato, RESTITUISCE NULL
MAI CHIAMARE LA RISORSA ESTERNA IN TEMPO REALE
Cron Job:
Implementare un hook_cron che esegue l'aggiornamento completo
Configurabile tramite settings.yml per:
Orario di esecuzione
Tipi di risorse da aggiornare
Batch size per evitare timeout
Cache Invalidation:
Aggiungere un meccanismo per forzare l'aggiornamento della cache
Possibile interfaccia di amministrazione per trigger manuale
Gestione Errori:
Se la cache è vuota (prima esecuzione o problemi):
Restituire un messaggio di errore
Non cercare di recuperare i dati in tempo reale
Suggerire di attendere il prossimo aggiornamento
Monitoraggio:
Aggiungere logging per monitorare:
Successo/fallimento degli aggiornamenti
Tempo di esecuzione
Numero di risorse processate
Configurazione:
Aggiungere variabili di configurazione per:
Orario di esecuzione
Tipi di risorse da monitorare
Batch size
Timeout massimo per l'aggiornamento
Interfaccia Ammin:
Aggiungere una pagina di amministrazione per:
Vedere lo stato della cache
Forzare un aggiornamento manuale
Vedere gli ultimi log
Backup:
Implementare un sistema di backup della cache
Possibilità di ripristinare una cache precedente in caso di problemi
Questa versione:

Garantisce sempre risposta dalla cache
Mai chiamata in tempo reale alla risorsa esterna
Gestisce correttamente gli errori
Fornisce strumenti di monitoraggio e amministrazione
Vuoi che proceda con l'implementazione di questa strategia?