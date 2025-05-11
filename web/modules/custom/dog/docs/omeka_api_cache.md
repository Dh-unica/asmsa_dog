# Sistema di Cache per le API Omeka

Questo documento descrive il sistema di cache implementato per le chiamate alle API Omeka nel modulo `dog`. Il sistema è progettato per migliorare significativamente le performance riducendo le chiamate alle API esterne e memorizzando i dati in una cache persistente.

## Indice
1. [Panoramica del sistema](#panoramica-del-sistema)
2. [Struttura della cache](#struttura-della-cache)
3. [Metodi con cache](#metodi-con-cache)
4. [Gestione della cache](#gestione-della-cache)
5. [Warming della cache](#warming-della-cache)
6. [Invalidazione della cache](#invalidazione-della-cache)
7. [Troubleshooting](#troubleshooting)

## Panoramica del sistema

Il sistema di cache per le API Omeka è composto da:

- **Cache bin personalizzato**: Un contenitore di cache dedicato (`cache.omeka_api`) che memorizza i dati in modo persistente
- **Tabella di database dedicata**: Una tabella `cache_omeka_api` che non viene svuotata con `drush cr`
- **Implementazione nei metodi principali**: Cache implementata nei tre metodi principali che interagiscono con le API Omeka

Il sistema è progettato per:
- Ridurre il carico sul server Omeka
- Velocizzare il rendering delle pagine che utilizzano dati Omeka
- Mantenere i dati in cache anche dopo un cache rebuild completo (`drush cr`)
- Utilizzare tag di cache per l'invalidazione selettiva quando necessario

## Struttura della cache

### Cache bin personalizzato

I dati delle API Omeka vengono memorizzati in un cache bin dedicato (`cache.omeka_api`) che utilizza una tabella di database separata (`cache_omeka_api`). Questo approccio garantisce che i dati rimangano persistenti anche dopo un cache rebuild completo.

### Chiavi di cache

Le chiavi di cache sono generate in base al tipo di operazione:

1. **Per singole risorse**:
   ```
   resource:[resource_type]:[id]
   ```
   Esempio: `resource:items:123`

2. **Per ricerche**:
   ```
   search:[resource_type]:[hash_dei_parametri]
   ```
   Esempio: `search:items:a1b2c3d4e5f6g7h8i9j0`

3. **Per set di elementi**:
   ```
   item_sets
   ```

### Tag di cache

I dati in cache sono taggati con:

1. **Per singole risorse**:
   - `omeka_api_resource`: Tag globale per tutte le risorse
   - `omeka_api_resource_[resource_type]_[id]`: Tag specifico per ogni risorsa

2. **Per ricerche**:
   - `omeka_api_search`: Tag globale per tutte le ricerche
   - `omeka_api_search_[resource_type]`: Tag specifico per tipo di risorsa

3. **Per set di elementi**:
   - `omeka_api_item_sets`: Tag specifico per i set di elementi

## Metodi con cache

### 1. retrieveResource(string $id, string $resource_type): ?array

Questo metodo recupera una singola risorsa Omeka per ID e tipo.

**Implementazione della cache**:
- Verifica se la risorsa è già in cache
- Se presente, restituisce i dati dalla cache
- Se assente, recupera i dati dall'API e li memorizza in cache con durata permanente

**Esempio di utilizzo**:
```php
$resource = $omeka_resource_fetcher->retrieveResource('123', 'items');
```

### 2. search(string $resource_type, array $parameters, int $page, int $items_per_page, int &$total_results): array

Questo metodo esegue una ricerca di risorse Omeka con parametri di filtro e paginazione.

**Implementazione della cache**:
- Genera una chiave di cache basata sui parametri di ricerca
- Se i risultati sono già in cache, li restituisce
- Se assenti, esegue la ricerca tramite API e memorizza i risultati in cache
- Memorizza anche il numero totale di risultati

**Esempio di utilizzo**:
```php
$total_results = 0;
$items = $omeka_resource_fetcher->search('items', ['property' => 'value'], 0, 10, $total_results);
```

### 3. getItemSets(): array

Questo metodo recupera tutti i set di elementi da Omeka.

**Implementazione della cache**:
- Verifica se i set di elementi sono già in cache
- Se presenti, li restituisce dalla cache
- Se assenti, li recupera dall'API e li memorizza in cache

**Esempio di utilizzo**:
```php
$item_sets = $omeka_resource_fetcher->getItemSets();
```

## Gestione della cache

Il modulo `dog` fornisce un'interfaccia di amministrazione completa per gestire la cache delle API Omeka. Questa interfaccia è accessibile tramite il menu di amministrazione.

### Accesso all'interfaccia di gestione

- **Percorso menu**: Amministrazione > Configurazione > Sistema > Gestione Cache Omeka API
- **URL**: `/admin/config/services/dog-cache`
- **Permessi richiesti**: "administer site configuration"

### Funzionalità dell'interfaccia

1. **Statistiche sulla cache**:
   - Numero totale di elementi in cache
   - Dimensione totale della cache (in formato leggibile)
   - Data e ora dell'ultimo aggiornamento della cache

2. **Pulizia della cache**:
   - Pulsante per pulire tutta la cache
   - Pulsanti per pulire selettivamente:
     - Cache delle risorse
     - Cache delle ricerche
     - Cache dei set di elementi

3. **Warming della cache**:
   - Configurazione del numero di elementi per batch
   - Pulsante per avviare il processo di warming

## Warming della cache

Il warming della cache è un processo che precalcola e memorizza i dati delle API Omeka per migliorare le performance del sito. Questo processo può essere avviato manualmente dall'interfaccia di amministrazione.

### Processo di warming

Il warming della cache viene eseguito in batch per evitare timeout e sovraccarichi del server. Il processo è suddiviso in tre fasi:

1. **Precaricamento dei set di elementi**:
   - Recupera e memorizza in cache tutti i set di elementi disponibili in Omeka

2. **Determinazione del numero totale di elementi**:
   - Esegue una query per determinare il numero totale di elementi da precaricare

3. **Precaricamento delle risorse**:
   - Recupera e memorizza in cache tutte le risorse Omeka, elaborandole in batch
   - Il numero di elementi per batch è configurabile dall'interfaccia

### Avvio del warming

1. Accedi all'interfaccia di gestione della cache
2. Configura il numero di elementi per batch (default: 10)
   - Un valore più alto elabora più elementi per batch, ma richiede più memoria
   - Un valore più basso è più sicuro ma richiede più tempo per completare il processo
3. Clicca sul pulsante "Preriscalda tutta la cache"
4. Segui l'avanzamento del processo nella barra di progresso

### Programmazione del warming

Per automatizzare il warming della cache, puoi utilizzare Drush e cron:

```php
// Esempio di implementazione in un modulo custom
function mymodule_cron() {
  // Esegui il warming ogni settimana
  $last_run = \Drupal::state()->get('mymodule.last_cache_warming', 0);
  if ((time() - $last_run) > 604800) { // 7 giorni in secondi
    $batch = [
      'title' => t('Preriscaldamento della cache delle API Omeka'),
      'operations' => [
        ['\Drupal\dog\Form\CacheManagementForm::batchWarmItemSets', []],
        ['\Drupal\dog\Form\CacheManagementForm::batchGetTotalItems', []],
        ['\Drupal\dog\Form\CacheManagementForm::batchWarmResources', [10]],
      ],
      'finished' => '\Drupal\dog\Form\CacheManagementForm::batchFinished',
    ];
    batch_set($batch);
    \Drupal::state()->set('mymodule.last_cache_warming', time());
  }
}
```

## Invalidazione della cache

La cache può essere invalidata in diversi modi:

### 1. Invalidazione selettiva

Per invalidare la cache di una singola risorsa:
```php
\Drupal\Core\Cache\Cache::invalidateTags(['omeka_api_resource_items_123']);
```

Per invalidare la cache di tutte le ricerche di un tipo:
```php
\Drupal\Core\Cache\Cache::invalidateTags(['omeka_api_search_items']);
```

Per invalidare la cache di tutti i set di elementi:
```php
\Drupal\Core\Cache\Cache::invalidateTags(['omeka_api_item_sets']);
```

### 2. Invalidazione globale

Per invalidare tutta la cache delle API Omeka:
```php
\Drupal\Core\Cache\Cache::invalidateTags([
  'omeka_api_resource',
  'omeka_api_search',
  'omeka_api_item_sets'
]);
```

### 3. Comportamento con drush cr

Importante: La cache **non** viene invalidata quando si esegue `drush cr`. Questo è un comportamento intenzionale per mantenere le performance del sito.

Se è necessario invalidare la cache dopo un `drush cr`, utilizzare uno dei metodi sopra descritti.

## Troubleshooting

### La cache non viene generata

Possibili cause:
- Il modulo `dog` non è stato reinstallato dopo l'implementazione della cache
- Errori di connessione al server Omeka

Soluzione:
1. Eseguire gli aggiornamenti del database: `drush updb`
2. Verificare che la tabella `cache_omeka_api` esista: `drush sql-query "SHOW TABLES LIKE 'cache_omeka_api'"`
3. Controllare i log: `drush watchdog-show --type=dog`
4. Utilizzare la pagina di gestione della cache per verificare le statistiche

### La cache non persiste dopo drush cr

Possibili cause:
- Il modulo è stato installato incorrettamente
- La tabella `cache_omeka_api` non è stata creata

Soluzione:
1. Eseguire gli aggiornamenti del database: `drush updb`
2. Verificare che la tabella esista: `drush sql-query "SHOW TABLES LIKE 'cache_omeka_api'"`
3. Controllare se ci sono errori nei log: `drush watchdog-show --type=dog`

### Errori durante il warming della cache

Possibili cause:
- Timeout del server durante l'elaborazione batch
- Errori di connessione al server Omeka
- Memoria insufficiente per elaborare il batch

Soluzione:
1. Ridurre il numero di elementi per batch nell'interfaccia di gestione
2. Controllare i log per errori specifici: `drush watchdog-show --type=dog`
3. Verificare che il server Omeka sia accessibile
4. Aumentare i limiti di memoria e tempo di esecuzione in PHP

### Performance insufficienti

Se le performance non migliorano significativamente:

1. Verificare che la cache sia effettivamente utilizzata:
   ```
   drush sql-query "SELECT COUNT(*) FROM cache_omeka_api"
   ```
2. Controllare la dimensione dei dati in cache:
   ```
   drush sql-query "SELECT SUM(LENGTH(data)) FROM cache_omeka_api"
   ```
3. Utilizzare la pagina di gestione della cache per avviare un warming completo
4. Considerare l'ottimizzazione degli indici del database per la tabella `cache_omeka_api`
5. Verificare le statistiche nella pagina di gestione della cache per assicurarsi che i dati siano effettivamente in cache
