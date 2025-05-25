<?php

/**
 * Metodo batch per processare un chunk specifico di items.
 * 
 * @param array $chunk
 *   Array con gli ID degli items da processare in questo batch.
 * @param int $chunk_index
 *   Indice del chunk corrente (per reporting).
 * @param int $total_chunks
 *   Numero totale di chunks (per reporting).
 * @param array $context
 *   Contesto del batch.
 */
function batchProcessItemsChunk(array $chunk, $chunk_index, $total_chunks, &$context) {
  // Ottiene il servizio cache per gli items
  $cache_service = \Drupal::service('dog.omeka_cache');
  $logger = \Drupal::logger('dog');
  
  // Inizializza i risultati se è la prima chiamata
  if (!isset($context['results']['processed'])) {
    $context['results'] = [
      'processed' => 0,
      'errors' => 0,
      'configuration_error' => FALSE,
    ];
  }
  
  // Recupera il bin di cache corretto direttamente per verificare
  $cache_factory = \Drupal::service('cache_factory');
  $direct_cache = $cache_factory->get('omeka_resources');
  
  $logger->notice('Elaborazione chunk @current di @total (@count items)', [
    '@current' => $chunk_index,
    '@total' => $total_chunks,
    '@count' => count($chunk),
  ]);
  
  // Processa ogni item in questo chunk
  $items_processed = 0;
  $items_errored = 0;
  
  foreach ($chunk as $item_id) {
    try {
      // Log della chiamata API per l'elemento corrente
      $logger->notice('Recupero elemento items/@id', ['@id' => $item_id]);
      
      // Recupera direttamente l'elemento singolo
      $resource_data = $cache_service->fetchResource('items', $item_id, TRUE);
      
      if ($resource_data) {
        // Cache l'elemento con i tag appropriati
        $cache_key = "omeka_resource:items:{$item_id}";
        // Uso tag più specifici che non verranno invalidati da altre operazioni di Drupal
        $cache_tags = [
          "dog_omeka_resource", // Tag base specifico del modulo
          "dog_omeka_resource:items",
          "dog_omeka_resource:items:{$item_id}"
        ];
        
        // Salva nella cache
        $direct_cache->set(
          $cache_key,
          $resource_data,
          time() + 2592000, // 30 giorni
          $cache_tags
        );
        
        // Verifica dopo il salvataggio
        $verify_cache = $direct_cache->get($cache_key);
        if ($verify_cache) {
          $items_processed++;
          $context['results']['processed']++;
        } else {
          $items_errored++;
          $context['results']['errors']++;
          $logger->error('Elemento @id non trovato in cache dopo il salvataggio!', ['@id' => $item_id]);
        }
      } else {
        $items_errored++;
        $context['results']['errors']++;
        $logger->warning('Impossibile recuperare elemento @id', ['@id' => $item_id]);
      }
    } catch (\Exception $e) {
      $items_errored++;
      $context['results']['errors']++;
      $logger->error('Errore durante elaborazione elemento @id: @message', [
        '@id' => $item_id,
        '@message' => $e->getMessage(),
      ]);
    }
  }
  
  // Aggiorna lo stato della cache al termine dell'elaborazione
  if ($chunk_index == $total_chunks) {
    // Aggiorna il timestamp dell'ultimo aggiornamento
    \Drupal::state()->set('omeka_cache.last_update', time());
    // Aggiorna il conteggio degli elementi in cache
    \Drupal::state()->set('omeka_cache.cached_items', $context['results']['processed']);
  }
  
  $logger->notice('Chunk @current/@total completato: elaborati @processed items, @errors errori', [
    '@current' => $chunk_index,
    '@total' => $total_chunks,
    '@processed' => $items_processed,
    '@errors' => $items_errored,
  ]);
  
  // Imposta il messaggio di stato
  $context['message'] = t('Elaborazione chunk @chunk/@total: @processed items processati', [
    '@chunk' => $chunk_index,
    '@total' => $total_chunks,
    '@processed' => $context['results']['processed'],
  ]);
}

function refreshItemsCache_nuovo() {
  $batch_size = 50;
  
  // Prima otteniamo il numero totale di elementi disponibili dall'API
  $cache_service = \Drupal::service('dog.omeka_cache');
  $total_items = $cache_service->getTotalItemsFromApi();
  
  // Se non ci sono elementi, mostra un messaggio e termina
  if ($total_items <= 0) {
    \Drupal::messenger()->addWarning(t('Nessun elemento disponibile nell\'API Omeka.'));
    return;
  }
  
  // Ottieni tutti gli ID degli items che dobbiamo elaborare
  $items_to_process = $cache_service->getAllItemIds();
  
  if (empty($items_to_process)) {
    \Drupal::messenger()->addError(t('Impossibile ottenere la lista degli ID degli items.'));
    return;
  }
  
  // Calcola quante operazioni batch dobbiamo eseguire
  // Vogliamo che ogni operazione elabori al massimo $batch_size elementi
  $total_items = count($items_to_process);
  $operations = [];
  
  // Suddividi gli elementi in blocchi di $batch_size
  $chunks = array_chunk($items_to_process, $batch_size);
  
  // Crea un'operazione batch per ogni blocco di elementi
  foreach ($chunks as $index => $chunk) {
    $operations[] = [
      '\Drupal\dog\Form\OmekaCacheRefreshForm::batchProcessItemsChunk',
      [$chunk, $index + 1, count($chunks)],
    ];
  }
  
  // Configura il batch con tutte le operazioni necessarie
  $batch = [
    'title' => t('Aggiornamento cache Items Omeka'),
    'init_message' => t('Inizializzazione processo di aggiornamento...'),
    'progress_message' => t('Elaborazione del blocco @current su @total.'),
    'error_message' => t('Si è verificato un errore durante l\'aggiornamento della cache.'),
    'operations' => $operations,
    'finished' => 'Drupal\dog\Form\OmekaCacheRefreshForm::batchProcessFinished',
    // Reindirizzamento a fine processo batch
    'url' => \Drupal\Core\Url::fromRoute('dog.omeka_cache_refresh'),
    'progressive' => TRUE,
  ];
  
  // Informazione iniziale
  \Drupal::messenger()->addStatus(t('Avvio aggiornamento cache per @total items suddivisi in @chunks blocchi', [
    '@total' => $total_items,
    '@chunks' => count($chunks),
  ]));
  
  batch_set($batch);
}

/**
 * Metodo batch per processare un chunk specifico di mapping features.
 */
function batchProcessMappingFeaturesChunk(array $chunk, $chunk_index, $total_chunks, &$context) {
  // Ottiene il servizio cache per le mapping features
  $geo_cache_service = \Drupal::service('dog.omeka_geo_cache');
  $logger = \Drupal::logger('dog');
  
  // Inizializza i risultati se è la prima chiamata
  if (!isset($context['results']['processed'])) {
    $context['results'] = [
      'processed' => 0,
      'errors' => 0,
      'configuration_error' => FALSE,
    ];
  }
  
  $logger->notice('Elaborazione chunk @current di @total (@count mapping features)', [
    '@current' => $chunk_index,
    '@total' => $total_chunks,
    '@count' => count($chunk),
  ]);
  
  // Processa ogni mapping feature in questo chunk
  $features_processed = 0;
  $features_errored = 0;
  
  foreach ($chunk as $feature_id) {
    try {
      // Log della chiamata API per la feature corrente
      $logger->notice('Elaborazione mapping feature ID @id', ['@id' => $feature_id]);
      
      // Recupera la feature specifica dall'API e salvala in cache
      $feature_url = rtrim($geo_cache_service->getConfig()->get('base_url'), '/') . '/api/mapping_features/' . $feature_id;
      
      $client = $geo_cache_service->getHttpClient();
      $response = $client->get($feature_url);
      $feature_data = json_decode((string) $response->getBody(), TRUE);
      
      if (!empty($feature_data) && isset($feature_data['o:id'])) {
        // Verifica se la feature ha coordinate
        if (!empty($feature_data['o-module-mapping:geography-coordinates'])) {
          // Salva in cache
          $result = $geo_cache_service->cacheFeature($feature_data);
          if ($result) {
            $features_processed++;
            $context['results']['processed']++;
          } else {
            $features_errored++;
            $context['results']['errors']++;
          }
        } else {
          $logger->info('Feature @id senza coordinate, ignorata', ['@id' => $feature_id]);
        }
      } else {
        $features_errored++;
        $context['results']['errors']++;
        $logger->warning('Feature @id non valida o non trovata', ['@id' => $feature_id]);
      }
    } catch (\Exception $e) {
      $features_errored++;
      $context['results']['errors']++;
      $logger->error('Errore durante recupero feature @id: @message', [
        '@id' => $feature_id,
        '@message' => $e->getMessage(),
      ]);
    }
  }
  
  // Aggiorna lo stato della cache al termine dell'elaborazione
  if ($chunk_index == $total_chunks) {
    // Aggiorna il timestamp dell'ultimo aggiornamento
    \Drupal::state()->set('omeka_geo_cache.last_update', time());
    // Aggiorna il conteggio delle features in cache
    \Drupal::state()->set('omeka_geo_cache.cached_items', $context['results']['processed']);
  }
  
  $logger->notice('Chunk @current/@total completato: elaborate @processed features, @errors errori', [
    '@current' => $chunk_index,
    '@total' => $total_chunks,
    '@processed' => $features_processed,
    '@errors' => $features_errored,
  ]);
  
  // Imposta il messaggio di stato
  $context['message'] = t('Elaborazione chunk @chunk/@total: @processed mapping features processate', [
    '@chunk' => $chunk_index,
    '@total' => $total_chunks,
    '@processed' => $context['results']['processed'],
  ]);
}

function refreshMappingFeaturesCache_nuovo() {
  $batch_size = 50;
  
  // Prima otteniamo il numero totale di features disponibili dall'API
  $geo_cache_service = \Drupal::service('dog.omeka_geo_cache');
  $total_features = $geo_cache_service->getTotalMappingFeaturesFromApi();
  
  // Se non ci sono elementi, mostra un messaggio e termina
  if ($total_features <= 0) {
    \Drupal::messenger()->addWarning(t('Nessuna mapping feature disponibile nell\'API Omeka.'));
    return;
  }
  
  // Ottieni tutti gli ID delle mapping features che dobbiamo elaborare
  $features_to_process = $geo_cache_service->getAllMappingFeatureIds();
  
  if (empty($features_to_process)) {
    \Drupal::messenger()->addError(t('Impossibile ottenere la lista degli ID delle mapping features.'));
    return;
  }
  
  // Calcola quante operazioni batch dobbiamo eseguire
  // Vogliamo che ogni operazione elabori al massimo $batch_size elementi
  $total_features = count($features_to_process);
  $operations = [];
  
  // Suddividi gli elementi in blocchi di $batch_size
  $chunks = array_chunk($features_to_process, $batch_size);
  
  // Crea un'operazione batch per ogni blocco di elementi
  foreach ($chunks as $index => $chunk) {
    $operations[] = [
      '\Drupal\dog\Form\OmekaCacheRefreshForm::batchProcessMappingFeaturesChunk',
      [$chunk, $index + 1, count($chunks)],
    ];
  }
  
  // Configura il batch con tutte le operazioni necessarie
  $batch = [
    'title' => t('Aggiornamento cache Mapping Features Omeka'),
    'init_message' => t('Inizializzazione processo di aggiornamento...'),
    'progress_message' => t('Elaborazione del blocco @current su @total.'),
    'error_message' => t('Si è verificato un errore durante l\'aggiornamento della cache.'),
    'operations' => $operations,
    'finished' => 'Drupal\dog\Form\OmekaCacheRefreshForm::batchProcessFinished',
    // Reindirizzamento a fine processo batch
    'url' => \Drupal\Core\Url::fromRoute('dog.omeka_cache_refresh'),
    'progressive' => TRUE,
  ];
  
  // Informazione iniziale
  \Drupal::messenger()->addStatus(t('Avvio aggiornamento cache per @total mapping features suddivise in @chunks blocchi', [
    '@total' => $total_features,
    '@chunks' => count($chunks),
  ]));
  
  batch_set($batch);
}
