<?php

/**
 * Script di test diretto per il servizio di cache Omeka.
 * 
 * Questo script può essere eseguito da drush scr per testare
 * direttamente l'accesso al servizio di cache e alle chiavi.
 */

// Verifica se il servizio è disponibile correttamente.
echo "Test del servizio di cache Omeka\n";
echo "--------------------------------\n";

try {
  // Test con il nome corretto
  $omeka_cache = \Drupal::service('dog.omeka_cache');
  echo "✅ Il servizio 'dog.omeka_cache' è disponibile.\n";
  
  // Verifica configurazione
  $config_status = $omeka_cache->getConfigurationStatus();
  echo "Status configurazione: " . json_encode($config_status, JSON_PRETTY_PRINT) . "\n";
  
  // Verifica chiavi di cache
  $id = 3412;
  $resource_type = 'items';
  echo "\nRicerca risorsa: id=$id, tipo=$resource_type\n";
  
  $resource = $omeka_cache->getResource($id, $resource_type);
  if (!empty($resource)) {
    echo "✅ Risorsa trovata con getResource()\n";
    echo "Dati risorsa: " . substr(json_encode($resource), 0, 150) . "...\n";
  } else {
    echo "❌ Risorsa NON trovata con getResource()\n";
  }
  
  // Verifica direttamente nel bin di cache
  $cache_factory = \Drupal::service('cache_factory');
  $cache = $cache_factory->get('default');
  
  // Controlla le possibili chiavi di cache
  $keys_to_check = [
    "omeka_resource:{$resource_type}:{$id}",  // Standard
    "omeka_resource:{$id}",                   // Senza tipo
    "omeka:{$resource_type}:{$id}",           // Prefisso differente
    "dog:{$resource_type}:{$id}",             // Altro prefisso
    "{$resource_type}:{$id}",                 // Solo tipo e id
    "resource:{$resource_type}:{$id}",        // Altro prefisso
  ];
  
  echo "\nVerifica chiavi di cache nel bin 'default':\n";
  foreach ($keys_to_check as $key) {
    $cache_data = $cache->get($key);
    if (!empty($cache_data)) {
      echo "✅ Trovata risorsa con chiave: $key\n";
    } else {
      echo "❌ Nessuna risorsa con chiave: $key\n";
    }
  }
  
  // Verifica informazioni sullo stato della cache
  $state = \Drupal::state();
  echo "\nStatistiche della cache:\n";
  echo "Ultimo aggiornamento: " . date('Y-m-d H:i:s', $state->get('dog.omeka_cache.last_update', 0)) . "\n";
  echo "Elementi totali: " . $state->get('dog.omeka_cache.total_items', 0) . "\n";
  echo "Elementi in cache: " . $state->get('dog.omeka_cache.cached_items', 0) . "\n";
  echo "Elementi con errore: " . $state->get('dog.omeka_cache.error_items', 0) . "\n";
  
  // Prova a creare una nuova istanza del servizio manualmente
  echo "\nCreazione manuale del servizio:\n";
  $fetcher = \Drupal::service('dog.omeka_resource_fetcher');
  $url_service = \Drupal::service('dog.omeka_url');
  $cache_default = \Drupal::service('cache.default');
  $config_factory = \Drupal::service('config.factory');
  $logger_factory = \Drupal::service('logger.factory');
  
  echo "✅ Dipendenze recuperate con successo\n";
  
  // Controlla il codice sorgente
  $reflection = new \ReflectionClass(\Drupal::service('dog.omeka_cache'));
  echo "\nClasse del servizio: " . get_class($omeka_cache) . "\n";
  echo "File sorgente: " . $reflection->getFileName() . "\n";
  echo "Namespace: " . $reflection->getNamespaceName() . "\n";
  
} catch (\Exception $e) {
  echo "❌ Errore: " . $e->getMessage() . "\n";
}
