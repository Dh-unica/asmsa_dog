# Omeka Utils Module

## Overview
Omeka Utils è un modulo Drupal che fornisce un'interfaccia semplificata per l'accesso ai contenuti provenienti da Omeka-S. Il modulo è progettato per lavorare in sinergia con il modulo DOG (Drupal Omeka Geonode), sfruttando le sue capacità di cache e prefetch per ottimizzare le performance delle chiamate API.

## Architettura

### Componenti principali

- **Utils**: Classe principale che fornisce metodi per l'accesso e la manipolazione dei dati Omeka
  - Interfaccia con il modulo DOG tramite il servizio `dog.omeka_resource_fetcher`
  - Gestione cache specifica per le risorse Omeka
  - Metodi di utilità per estrarre informazioni specifiche (titoli, descrizioni, immagini)

### Servizi

- **omeka_utils.utils**: Servizio principale che espone i metodi della classe Utils
- **cache.omeka**: Bin di cache dedicato per le risorse Omeka

## Integrazione con il modulo DOG

### Connessione tramite Service
Il modulo `omeka_utils` si integra con `dog` attraverso l'iniezione dei servizi:

```php
// Ottenere i servizi DOG nel costruttore della classe Utils
$this->resourceFetcher = \Drupal::service('dog.omeka_resource_fetcher');
$cacheManager = \Drupal::service('dog.omeka_resource_cache_manager');

// Configurare il cache manager nel resource fetcher
if (method_exists($this->resourceFetcher, 'setCacheManager')) {
  $this->resourceFetcher->setCacheManager($cacheManager);
}
```

### Strategia di Fallback
Il modulo implementa una strategia di fallback per garantire la resilienza del sistema:

1. Prima tenta di utilizzare il servizio `dog.omeka_resource_fetcher` per sfruttare il sistema di cache avanzato
2. In caso di fallimento, utilizza un sistema di cache locale più semplice
3. Utilizza l'HTTP client di Drupal anziché `file_get_contents()` per una migliore gestione degli errori

## Funzionalità Principali

### Recupero delle risorse
```php
// Esempio di utilizzo del servizio
$omeka = \Drupal::service('omeka_utils.utils');
$omeka_item = $omeka->getItem($id);
```

### Estrazione dei dati
Il modulo fornisce metodi specializzati per estrarre informazioni specifiche dalle risorse Omeka:

- `getTitle()`: Estrae il titolo della risorsa
- `getDescription()`: Estrae la descrizione in base al template della risorsa
- `getImage()`: Recupera le immagini con diverse dimensioni (small, medium, large, square)
- `getResourceTemplate()`: Identifica il tipo di template utilizzato
- `getLatLon()`: Estrae le coordinate geografiche
- `getLocation()`: Recupera dati di localizzazione completi
- `getItemUrl()`: Genera l'URL pubblico per visualizzare l'elemento su Omeka

## Performance

### Ottimizzazioni
1. **Sistema di cache a due livelli**:
   - Utilizza il sistema di cache avanzato del modulo DOG quando disponibile
   - Fornisce un sistema di cache di fallback

2. **Logging delle performance**:
   - Monitoraggio dei tempi di esecuzione per ogni operazione
   - Tracking di hit/miss della cache
   - Logging dettagliato per identificare colli di bottiglia

3. **Gestione efficiente delle connessioni HTTP**:
   - Utilizzo dell'HTTP client di Drupal
   - Configurazione dei timeout
   - Gestione degli errori

## Casi d'uso tipici

### Integrazione nei template Twig
```php
{# Esempio di utilizzo in un template Twig #}
{% set omeka = omeka_utils.utils %}
{% set omeka_item = omeka.getItem(id) %}
{% set title = omeka.getTitle(omeka_item) %}
{% set description = omeka.getDescription(omeka_item) %}
{% set image = omeka.getImage(omeka_item, 'large') %}
```

### Integrazione con ECK
Il modulo supporta l'integrazione con entity personalizzate:
```php
// Esempio di recupero dell'ID Omeka da un'entità ECK
$omeka = \Drupal::service('omeka_utils.utils');
$entity = $vars['eck_entity'];
$omeka_id = $omeka->getIdFromEck($entity);
$omeka_item = $omeka->getItem($omeka_id);
```

## Risoluzione dei problemi

### Problemi comuni
1. **Cache non aggiornata**: 
   - La cache di Omeka potrebbe contenere dati obsoleti
   - Soluzione: svuotare la cache `drush cache-rebuild` o specificamente `drush cr omeka`

2. **Errori di connessione**:
   - Verificare la configurazione dell'URL base nel modulo DOG
   - Controllare i log di errore per dettagli sulla connessione

3. **Problemi di performance**:
   - Controllare i log per identificare operazioni lente
   - Verificare che il prefetch del modulo DOG sia configurato correttamente

## Sviluppi futuri

1. **Miglioramento dell'integrazione con DOG**:
   - Dipendenza esplicita dal modulo DOG
   - Migrazione completa verso l'utilizzo dei servizi DOG

2. **Miglioramento della gestione degli errori**:
   - Implementazione di retry automatici
   - Circuit breaker per prevenire failure a cascata

3. **Documentazione migliorata**:
   - Esempi più dettagliati di integrazione
   - Tutorial per casi d'uso comuni
