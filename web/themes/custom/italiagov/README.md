# ItaliGov - Theme Documentation

## Panoramica
Il tema grafico ItaliGov è un child theme basato su Bootstrap Italia, progettato per integrare risorse Omeka-S in un'installazione Drupal. Il tema fornisce componenti specializzati per la visualizzazione di mappe interattive e timeline con dati provenienti dall'API Omeka-S.

## Architettura del Sistema

Il sistema utilizza due moduli distinti per interagire con l'API Omeka-S:

1. Il modulo **dog**: implementa un sistema di cache completo con prefetch dei dati Omeka
2. Il modulo **omeka_utils**: utilizzato direttamente dai template del tema

### Flusso di Dati
1. L'API Omeka-S espone risorse attraverso un'interfaccia REST
2. Il modulo `dog` recupera e memorizza queste risorse nella cache
3. I template del tema utilizzano tali dati per renderizzare mappe e timeline

## Template Principali e Risorse Omeka

### block--omeka-map.html.twig
Questo template visualizza una mappa interattiva con marker rappresentanti risorse Omeka. Per ogni risorsa visualizzata sulla mappa, il template richiede:

- **Risorse Omeka principali**:
  - Metadati degli item (`items/{id}`)
  - Caratteristiche di mappatura (`mapping_features/{id}`)
  - Thumbnail associati alle risorse
  - Coordinate geografiche (latitudine/longitudine)

- **Tipologie di risorse**:
  - Item Omeka standard
  - Contenuti Drupal con coordinate
  - Media items (audio, video, immagini, documenti)

### block--omeka-map-timeline.html.twig
Questo template estende la visualizzazione della mappa con una timeline temporale. Oltre alle risorse richieste dalla mappa base, necessita di:

- **Risorse Omeka aggiuntive**:
  - Metadata temporali (`dcterms:date`) per posizionare gli elementi sulla timeline
  - Livelli WMS (Web Map Service) temporalmente filtrati

- **Interazione**:
  - Sincronizzazione tra timeline e mappa
  - Apertura automatica dei popup correlati
  - Cambio dinamico dei layer WMS in base alla data selezionata

### dog-omeka-resource.html.twig
Template principale per la visualizzazione generica delle risorse Omeka, definito nel modulo `dog` (`/web/modules/custom/dog/templates/dog-omeka-resource.html.twig`). Questo template prevale su eventuali override nel tema secondo la logica dei template Drupal.

Il template si occupa di:
- Renderizzare una singola risorsa Omeka con la sua immagine thumbnail
- Fornire un link all'URL pubblico della risorsa
- Visualizzare il titolo della risorsa
- Rendere le risorse accessibili indipendentemente dalla visualizzazione mappa/timeline

Utilizza le seguenti variabili e risorse Omeka:
- `omeka_resource_data['thumbnail_display_urls']['medium']` per l'immagine thumbnail
- `omeka_resource_data['o:title']` per il titolo della risorsa
- `public_url` o `omeka_resource_data['o:media'][0]['@id']` per il link alla risorsa

## Implementazione JavaScript

Il file `omeka-map.js` contiene la logica per:

1. Recuperare i dati delle risorse Omeka
2. Convertire i dati in formato compatibile con Leaflet e TimelineJS
3. Creare marker interattivi sulla mappa
4. Gestire la sincronizzazione tra mappa e timeline
5. Creare popup informativi per ogni risorsa

### Dati Recuperati

Per ciascuna risorsa Omeka, il JavaScript recupera:

```javascript
{
  id: itemData["o:id"],
  title: itemData["dcterms:title"] && itemData["dcterms:title"][0]["@value"],
  date: convertDateFormat(itemData["dcterms:date"]?.[0]["@value"]) || null,
  latitude: locationData['latitude'] || null,
  longitude: locationData['longitude'] || null,
  type: "omeka",
  thumbnail: {
    large: itemData["thumbnail_display_urls"]?.large || null,
    medium: itemData["thumbnail_display_urls"]?.medium || null,
    square: itemData["thumbnail_display_urls"]?.square || null,
  },
  absolute_url: store.confs.omeka_items[itemID].absolute_url
}
```

## Problematiche di Performance

Come indicato nel file `requirements.md`, il sistema presenta problemi di performance quando il numero di oggetti Omeka supera i 20. Questo accade perché:

1. Ogni risorsa viene recuperata in tempo reale tramite chiamate API separate
2. Non viene utilizzato adeguatamente il sistema di cache implementato nel modulo `dog`

## Ottimizzazioni Consigliate

Per ottimizzare le performance, si consiglia di:

1. **Utilizzare il sistema di cache**:
   - Assicurarsi che il modulo `dog` effettui il prefetch di tutte le risorse necessarie
   - Configurare la cache per aggiornamenti periodici (batch giornaliero)

2. **Integrare correttamente i moduli**:
   - Modificare i template per utilizzare esclusivamente `dog.omeka_resource_fetcher`
   - Evitare chiamate dirette all'API Omeka tramite `omeka_utils`

3. **Ottimizzare le query**:
   - Filtrare le risorse lato server prima di trasferirle al client
   - Implementare paginazione per set di dati molto grandi
   - Ridurre la quantità di metadati richiesti per ogni risorsa

4. **Precaricamento strategico**:
   - Identificare e precariccare solo le risorse necessarie per la visualizzazione iniziale
   - Caricare dati aggiuntivi su richiesta (lazy loading)

5. **Ottimizzazione delle immagini**:
   - Utilizzare formati e dimensioni appropriate per le thumbnail
   - Implementare caricamento progressivo delle immagini

## Risorse Prioritarie per il Caching

Per ottimizzare il sistema, è fondamentale mettere in cache prioritariamente:

1. Tutti gli `items` di Omeka con attributi geografici utilizzati nelle mappe
2. I `mapping_features` associati a ciascun item
3. I metadata temporali per gli elementi visualizzati nella timeline
4. Le thumbnail per le anteprime nei popup

## Integrazione con il Modulo Dog

Il modulo `dog` è stato progettato per gestire efficacemente il caching delle risorse Omeka. I template del tema dovrebbero essere aggiornati per:

1. Utilizzare esclusivamente il servizio `dog.omeka_resource_fetcher` anziché chiamare direttamente l'API
2. Rispettare la struttura delle chiavi cache (`dog:resource:{type}:{id}` e `dog:all_resources:{type}`)
3. Gestire correttamente i casi in cui la risorsa non è presente in cache (fallback appropriati)
