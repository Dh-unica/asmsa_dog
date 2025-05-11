# Cache Omeka Map - Documentazione

Questo documento descrive il sistema di cache implementato per i blocchi Omeka Map nel sito Drupal. Il sistema è progettato per migliorare significativamente le performance riducendo le chiamate all'API Omeka e memorizzando i dati in una cache persistente.

## Indice
1. [Panoramica del sistema](#panoramica-del-sistema)
2. [Struttura della cache](#struttura-della-cache)
3. [Metodi di generazione della cache](#metodi-di-generazione-della-cache)
4. [Gestione e monitoraggio](#gestione-e-monitoraggio)
5. [Invalidazione della cache](#invalidazione-della-cache)
6. [Troubleshooting](#troubleshooting)

## Panoramica del sistema

Il sistema di cache per i blocchi Omeka Map è composto da:

- **Cache bin personalizzato**: Un contenitore di cache dedicato (`cache.omeka_map`) che memorizza i dati in modo persistente
- **Servizio di cache warmer**: Un servizio che precalcola e memorizza i dati Omeka
- **Interfaccia di amministrazione**: Una pagina di report e gestione della cache
- **Integrazione con il tema**: Logica nel file `italiagov.theme` per utilizzare i dati in cache

Il sistema è progettato per:
- Ridurre il carico sul server Omeka
- Velocizzare il rendering delle pagine
- Mantenere i dati in cache anche dopo un cache rebuild completo (`drush cr`)
- Fornire strumenti per gestire e monitorare lo stato della cache

## Struttura della cache

### Cache bin personalizzato

I dati Omeka vengono memorizzati in un cache bin dedicato (`cache.omeka_map`) che utilizza una tabella di database separata (`cache_omeka_map`). Questo approccio garantisce che i dati rimangano persistenti anche dopo un cache rebuild completo.

### Chiavi di cache

Le chiavi di cache sono generate in base a:
- ID del blocco Omeka Map
- Hash MD5 degli item Omeka associati al blocco

Esempio di chiave di cache:
```
italiagov:omeka_map:123:a1b2c3d4e5f6g7h8i9j0
```

### Dati memorizzati

Per ogni blocco Omeka Map, vengono memorizzati:
- `full_items`: Array associativo contenente i dati completi degli item Omeka
- `items_ids`: Array contenente gli ID degli item Omeka con coordinate geografiche

### Tag di cache

I dati in cache sono taggati con:
- `omeka_map_persistent`: Tag globale per tutti i dati Omeka
- `omeka_map_block_[ID]`: Tag specifico per ogni blocco (es. `omeka_map_block_123`)

Questi tag permettono l'invalidazione selettiva della cache.

## Metodi di generazione della cache

Esistono diversi modi per generare la cache dei blocchi Omeka Map:

### 1. Generazione automatica durante la visualizzazione

Quando un utente visita una pagina contenente un blocco Omeka Map, il sistema verifica se i dati sono già in cache:
- Se presenti, vengono utilizzati direttamente
- Se assenti, vengono generati e memorizzati in cache

Questo avviene nel file `italiagov.theme` nella funzione `italiagov_preprocess_block()`.

### 2. Generazione tramite cron

Il modulo implementa `hook_cron()` che esegue il warming della cache ogni 12 ore. Questo garantisce che i dati siano sempre aggiornati anche se le pagine non vengono visitate frequentemente.

Per forzare l'esecuzione del cron:
```
docker exec -it asmsa_dog_php sh -c "drush cron"
```

### 3. Generazione tramite Drush

È disponibile un comando Drush per eseguire manualmente il warming della cache:
```
docker exec -it asmsa_dog_php sh -c "drush italiagov:warm-omeka-cache"
```

Questo comando:
- Elabora tutti i blocchi Omeka Map
- Genera la cache per ciascun blocco
- Mostra un report del processo

### 4. Generazione tramite interfaccia di amministrazione

L'interfaccia di amministrazione permette di:
- Ricostruire la cache per un singolo blocco
- Ricostruire la cache per tutti i blocchi

Accesso: `admin/config/services/omeka-cache-report`

## Gestione e monitoraggio

### Pagina di report

La pagina di report (`admin/config/services/omeka-cache-report`) mostra:
- Elenco di tutti i blocchi Omeka Map
- Stato della cache per ciascun blocco
- Numero di nodi associati a ciascun blocco
- Data dell'ultimo aggiornamento della cache
- Pulsanti per ricostruire la cache

### Monitoraggio via log

Il sistema registra informazioni dettagliate nel log di Drupal:
- Inizio e fine del processo di cache warming
- Blocchi elaborati con successo
- Errori durante il processo

Per visualizzare i log:
```
docker exec -it asmsa_dog_php sh -c "drush watchdog-show --type=italiagov_cache_warmer"
```

## Invalidazione della cache

La cache può essere invalidata in diversi modi:

### 1. Invalidazione selettiva

Per invalidare la cache di un singolo blocco:
- Utilizzare il pulsante "Ricostruisci cache" nella pagina di report
- Oppure utilizzare l'API di Drupal:
  ```php
  \Drupal\Core\Cache\Cache::invalidateTags(['omeka_map_block_' . $block_id]);
  ```

### 2. Invalidazione globale

Per invalidare la cache di tutti i blocchi:
- Utilizzare il pulsante "Ricostruisci tutta la cache" nella pagina di report
- Oppure utilizzare l'API di Drupal:
  ```php
  \Drupal\Core\Cache\Cache::invalidateTags(['omeka_map_persistent']);
  ```

### 3. Comportamento con drush cr

Importante: La cache **non** viene invalidata quando si esegue `drush cr`. Questo è un comportamento intenzionale per mantenere le performance del sito.

Se è necessario invalidare la cache dopo un `drush cr`, utilizzare uno dei metodi sopra descritti.

## Troubleshooting

### La cache non viene generata

Possibili cause:
- Il modulo `italiagov_cache_warmer` non è attivo
- I blocchi Omeka Map non hanno item con coordinate geografiche
- Errori di connessione al server Omeka

Soluzione:
1. Verificare che il modulo sia attivo: `drush pm-list | grep italiagov_cache_warmer`
2. Controllare i log: `drush watchdog-show --type=italiagov_cache_warmer`
3. Verificare la connessione al server Omeka

### La cache non persiste dopo drush cr

Possibili cause:
- Il modulo è stato installato incorrettamente
- La tabella `cache_omeka_map` non è stata creata

Soluzione:
1. Reinstallare il modulo: `drush pmu italiagov_cache_warmer -y && drush en italiagov_cache_warmer -y`
2. Verificare che la tabella esista: `drush sql-query "SHOW TABLES LIKE 'cache_omeka_map'"`

### Performance insufficienti

Se le performance non migliorano significativamente:

1. Verificare che la cache sia effettivamente utilizzata:
   ```
   drush sql-query "SELECT COUNT(*) FROM cache_omeka_map"
   ```
2. Controllare la dimensione dei dati in cache:
   ```
   drush sql-query "SELECT SUM(LENGTH(data)) FROM cache_omeka_map"
   ```
3. Considerare l'ottimizzazione degli indici del database per la tabella `cache_omeka_map`
