# DOG (Drupal Omeka Geonode) Module

## Overview
DOG is a Drupal module that integrates Omeka resources into Drupal, providing a seamless way to fetch, display and manage Omeka resources within Drupal. The module is composed of several submodules that handle different aspects of the integration.

## Architecture

### Core Module (dog)
- **Purpose**: Core functionality for Omeka integration
- **Key Components**:
  - ResourceFetcherInterface/OmekaResourceFetcher: Handles API communication with Omeka
  - OmekaResourceViewBuilder: Renders Omeka resources 
  - OmekaUrlService: Manages URL transformations between API and public URLs
  - Event Subscribers for Views integration with remote data

### Submodules

#### dog_library
- **Purpose**: Provides a media library-style UI for browsing and selecting Omeka resources
- **Key Components**:
  - ResourceLibraryState: Manages the state of the resource library dialog
  - ResourceLibraryUiBuilder: Builds the UI for resource selection
  - Views integration for resource browsing
  - Widget for entity reference fields

#### dog_ckeditor5
- **Purpose**: CKEditor 5 integration for inserting Omeka resources into content
- **Key Components**:
  - Custom CKEditor 5 plugin
  - Resource embedding filter
  - Dialog integration with dog_library

## Performance Considerations

### Current Performance Bottlenecks

1. **API Communication**:
   - All resource fetching goes through OmekaResourceFetcher
   - No built-in caching for API responses
   - Sequential fetching of resources

2. **Views Integration**:
   - Remote data queries may be inefficient
   - Limited pagination support
   - No result caching

3. **Resource Rendering**:
   - Each resource render requires fresh API call
   - No render caching strategy

### Optimization Recommendations

1. **Implement Caching Layer**:
```php
// Example implementation in OmekaResourceFetcher:
protected function getFromCache($id, $type) {
  $cid = "dog:resource:{$type}:{$id}";
  if ($cached = \Drupal::cache()->get($cid)) {
    return $cached->data;
  }
  return NULL;
}
```

2. **Batch Processing**:
   - Implement batch fetching for multiple resources
   - Use Promise/Pool pattern for parallel requests
   - Consider implementing queue for large imports

3. **Views Optimization**:
   - Add result caching
   - Implement proper pagination headers
   - Consider local storage for frequently accessed resources

4. **Resource Rendering**:
   - Implement render caching
   - Add view mode specific caching
   - Consider implementing lazy loading

## Configuration Best Practices

1. **API Settings**:
   ```yaml
   dog.settings:
     base_url: 'https://omeka-instance.com/api'
     key_identity: 'your-key'
     key_credential: 'your-secret'
     cache_ttl: 3600  # Add cache time to live
     batch_size: 50   # Add batch processing size
   ```

2. **Performance Settings**:
   - Configure proper cache backends
   - Set appropriate cache tags
   - Configure batch processing limits

## Gestione delle coordinate geografiche

### Flusso di recupero delle coordinate

Le coordinate geografiche degli oggetti Omeka seguono questo percorso:

1. **Origine in Omeka S**: 
   - Le coordinate sono memorizzate nell'API di Omeka S nel campo `o-module-mapping:feature` di ciascun oggetto
   - Specificamente nel sotto-array `o-module-mapping:geography-coordinates` in formato `[longitude, latitude]`
   - Esempio: `"o-module-mapping:geography-coordinates": [8.4507977559954, 39.069830365657]`

2. **Recupero via PHP**:
   - Il servizio `OmekaResourceFetcher` (in `dog/src/Service/OmekaResourceFetcher.php`) recupera l'intero oggetto Omeka seguendo un flusso ottimizzato:
     - **Priorità alla cache**: Il sistema verifica *sempre* prima la presenza dell'oggetto in cache
     - **Struttura della cache**: Utilizza due cache bin separati e ottimizzati:
       - `@cache.omeka`: Per gli oggetti completi (pattern: `omeka_resource:{type}:{id}`)
       - `@cache.omeka_geo_data`: Specifico per i dati geografici (pattern: `omeka_geo_data:feature:{id}`)
     - **Strategia di fallback**: Solo in caso di cache miss, viene effettuata una chiamata API live
     - **Persistenza**: I dati vengono immediatamente salvati in cache dopo ogni chiamata API
     - **Dependency Injection**: Il servizio utilizza correttamente `CacheBackendInterface` e `StateInterface`
   - **Efficienza del sistema**:
     - Evita richieste HTTP ridondanti grazie alla strategia di cache
     - Accede direttamente alle coordinate nell'oggetto Omeka tramite `o-module-mapping:feature[0]->o-module-mapping:geography-coordinates`
     - Non effettua più chiamate HTTP separate per recuperare le coordinate geografiche
     - Implementa gestione degli errori avanzata con logging dettagliato
     - Utilizza una TTL configurabile per bilanciare freschezza dei dati e performance
   - **Esempio di chiave di cache**: `omeka_resource:items:3389` o `omeka_geo_data:feature:7`

3. **Elaborazione delle coordinate**:
   - La classe `Utils` (in `omeka_utils/src/Utils.php`) con il metodo `getLocation()` estrae le coordinate
   - Converte il formato da `[longitude, latitude]` (formato GeoJSON) a un array associativo `['latitude' => y, 'longitude' => x]`
   - Gestisce vari casi di errore e fornisce valori di fallback quando necessario
   - Supporta sia l'estrazione diretta dalle proprietà dell'oggetto che il recupero tramite URL di feature

4. **Passaggio al frontend**:
   - Nel file `italiagov.theme`, l'hook di preprocess `italiagov_preprocess_block` aggiunge le coordinate a `drupalSettings.omeka_map`
   - Le coordinate vengono rese disponibili come proprietà `latitude` e `longitude` dell'oggetto `store.confs.omeka_items[itemID]`
   - Il file JavaScript `omeka-map.js` utilizza queste coordinate per creare marker sulla mappa Leaflet

### Funzionamento della cache

Il sistema utilizza due cache bin distinti:

1. **cache.omeka**: Cache per le risorse Omeka (items, media)
   - Pattern della chiave: `omeka_resource:{resource_type}:{id}`
   - Esempio: `omeka_resource:items:3389`

2. **cache.omeka_geo_data**: Cache per i dati geografici
   - Pattern della chiave: `omeka_geo_data:feature:{id}`
   - Esempio: `omeka_geo_data:feature:7`

Questo approccio a doppia cache permette di gestire in modo efficiente sia i contenuti che le informazioni geografiche, riducendo le chiamate API e migliorando le performance.

## Development Guidelines

1. **Adding New Resource Types**:
   - Extend ResourceFetcherInterface
   - Implement proper view builders
   - Add necessary Views plugins

2. **Custom Renderers**:
   - Use theme suggestions
   - Implement proper cache contexts
   - Follow Drupal render API best practices

3. **API Extensions**:
   - Follow OmekaApiResponse pattern
   - Implement proper error handling
   - Add retry mechanisms

## Testing and Monitoring

1. **Performance Testing**:
   - Monitor API response times
   - Track cache hit/miss ratios
   - Profile resource rendering

2. **Error Handling**:
   - Implement proper logging
   - Monitor API failures
   - Set up alerts for critical issues

## Future Improvements

1. **Caching Layer**:
   - Implement advanced caching strategies
   - Add cache warmers
   - Consider implementing cache tags from Omeka

2. **API Communication**:
   - Add retry mechanisms
   - Implement circuit breaker
   - Add request queuing

3. **Resource Management**:
   - Add local resource indexing
   - Implement resource synchronization
   - Add resource validation

4. **User Interface**:
   - Improve resource browser performance
   - Add drag-and-drop support
   - Implement better preview mechanisms

## Contributing

When contributing to this module:

1. Follow Drupal coding standards
2. Add proper documentation
3. Include performance considerations
4. Add appropriate tests
5. Update cache implementations