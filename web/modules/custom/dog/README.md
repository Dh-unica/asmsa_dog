# Modulo Custom "Dog" per Integrazione Omeka

## Panoramica

Il modulo `dog` fornisce un'integrazione avanzata tra un sito Drupal 10 e un'istanza Omeka S. Le sue funzionalità principali includono:

- **Recupero di risorse**: Fetch di "items" e "mapping features" dall'API di Omeka.
- **Cache Persistente**: Un sistema di caching robusto per minimizzare le chiamate API e accelerare il caricamento delle pagine.
- **Pannello di Controllo**: Un'interfaccia amministrativa per gestire le credenziali API e la cache.
- **Processi Batch**: Meccanismi per popolare e aggiornare la cache in modo massivo senza timeout.

## Configurazione Iniziale

Perché il modulo funzioni, è necessario configurare il backend di cache persistente e le credenziali API.

### 1. Cache Persistente (PCB)

Assicurarsi che il modulo **Persistent Cache Block (PCB)** sia installato e che il seguente codice sia presente nel file `settings.php` del sito:

```php
// Abilita la cache persistente per i dati Omeka
$settings['cache']['bins']['omeka'] = 'cache.backend.permanent_database';
```

Questo definisce un *cache bin* chiamato `omeka` che non viene svuotato durante le normali operazioni di pulizia della cache di Drupal.

### 2. Credenziali API

1.  Navigare alla pagina di configurazione del modulo:
    `/admin/config/services/dog-settings`
2.  Inserire i seguenti dati:
    - **Omeka API Base URL**: L'URL base dell'API Omeka (es. `https://your-omeka-site.com/api`).
    - **Omeka API Key Identity**: La `key_identity` per l'autenticazione.
    - **Omeka API Key Credential**: La `key_credential` (secret) per l'autenticazione.
3.  Salvare la configurazione.

## Pannello di Controllo della Cache

La pagina di configurazione (`/admin/config/services/dog-settings`) è anche il centro di comando per la gestione della cache.

### Statistiche della Cache

Il pannello mostra le seguenti statistiche, basate sui dati salvati nella State API di Drupal:

- **Ultimo aggiornamento**: La data e l'ora dell'ultimo avvio di un processo batch.
- **Cached items**: Il numero di "items" Omeka attualmente presenti in cache.
- **Cached mapping features**: Il numero di "features" geografiche attualmente in cache.

### Azioni Disponibili

- **Refresh Omeka Items Cache**: Avvia un processo batch che scarica (o aggiorna) tutti gli "items" dall'API di Omeka e li salva nella cache persistente. Utile per il popolamento iniziale o per un aggiornamento completo.
- **Refresh Omeka Mapping Features Cache**: Avvia un processo batch simile al precedente, ma specifico per le "mapping features".
- **Clear Cache and Statistics**: Questa è l'azione di manutenzione più importante.
    - **Svuota la cache**: Esegue un `TRUNCATE` diretto sulla tabella `cache_omeka` del database, garantendo la rimozione completa di tutti i dati cachati.
    - **Azzera le statistiche**: Resetta i contatori e la data di ultimo aggiornamento nella State API.

## Architettura e Dettagli Tecnici

### Servizi Principali

- `dog.omeka_resource_fetcher`: Il servizio cuore del modulo. Gestisce tutta la logica di comunicazione con l'API Omeka, il recupero dei dati e il salvataggio/lettura dalla cache.

### Gestione della Cache

- **Cache Bin**: Viene utilizzato un unico bin persistente, `cache_omeka`.
- **Chiavi di Cache**: Le risorse vengono salvate con chiavi strutturate per evitare collisioni:
    - Items: `omeka_resource:items:{ID}`
    - Features: `omeka_geo_data:feature:{ID}`
- **Pulizia della Cache**: A causa di un'anomalia nel modo in cui Drupal interagisce con il backend PCB in un contesto di richiesta web, la pulizia della cache dal pannello di controllo non usa l'API `CacheBackendInterface::deleteAll()`. Invece, per garantire la massima affidabilità, esegue una query `TRUNCATE` diretta sulla tabella del database `cache_omeka`. Questo approccio, sebbene più diretto, si è rivelato l'unico funzionante al 100% in questo scenario.