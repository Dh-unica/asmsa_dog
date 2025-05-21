<?php

/**
 * Script di test specifico per verificare la risorsa 8677
 */

$id = '8677';
$resource_type = 'items';

echo "Test specifico per risorsa Omeka {$resource_type}:{$id}\n";
echo "--------------------------------------------\n\n";

// Test della cache bin dedicata
echo "1. Verifica nella bin di cache 'omeka_resources':\n";
$cache_factory = \Drupal::service('cache_factory');
$omeka_resources_bin = $cache_factory->get('omeka_resources');
$cache_key = "omeka_resource:{$resource_type}:{$id}";
$cache_data = $omeka_resources_bin->get($cache_key);

if (!empty($cache_data)) {
  echo "✅ Risorsa trovata nella cache con chiave: {$cache_key}\n";
  echo "Data creazione: " . date('Y-m-d H:i:s', $cache_data->created) . "\n";
  echo "Data scadenza: " . date('Y-m-d H:i:s', $cache_data->expire) . "\n";
  echo "Dati: " . substr(json_encode($cache_data->data), 0, 150) . "...\n\n";
} else {
  echo "❌ Risorsa NON trovata nella cache con chiave: {$cache_key}\n\n";
  
  // Prova a cercare con chiavi alternative
  $alternative_keys = [
    "omeka_resource:{$id}",
    "omeka:{$resource_type}:{$id}",
    "dog:{$resource_type}:{$id}",
    "{$resource_type}:{$id}",
    "resource:{$resource_type}:{$id}",
  ];
  
  foreach ($alternative_keys as $alt_key) {
    $alt_data = $omeka_resources_bin->get($alt_key);
    if (!empty($alt_data)) {
      echo "✅ Risorsa trovata con chiave alternativa: {$alt_key}\n";
      echo "Dati: " . substr(json_encode($alt_data->data), 0, 150) . "...\n\n";
    }
  }
}

// Test del servizio cache
echo "2. Verifica tramite servizio OmekaCacheService:\n";
try {
  $omeka_cache = \Drupal::service('dog.omeka_cache');
  echo "  ✅ Servizio dog.omeka_cache trovato\n";
  
  // Ottieni la risorsa dal servizio
  $resource = $omeka_cache->getResource($id, $resource_type);
  
  if (!empty($resource)) {
    echo "  ✅ Risorsa trovata tramite servizio\n";
    echo "  Dati: " . substr(json_encode($resource), 0, 150) . "...\n\n";
  } else {
    echo "  ❌ Risorsa NON trovata tramite servizio\n\n";
    
    // Recupera direttamente dall'API
    echo "3. Prova a recuperare direttamente dall'API:\n";
    $resource_fetcher = \Drupal::service('dog.omeka_resource_fetcher');
    $api_resource = $resource_fetcher->getResource($resource_type, $id);
    
    if (!empty($api_resource)) {
      echo "  ✅ Risorsa trovata nell'API\n";
      echo "  Dati API: " . substr(json_encode($api_resource), 0, 150) . "...\n\n";
    } else {
      echo "  ❌ Risorsa NON trovata nell'API\n\n";
    }
  }
  
  // Debug del bin di cache utilizzato
  $reflection = new \ReflectionClass($omeka_cache);
  $cache_prop = $reflection->getProperty('cache');
  $cache_prop->setAccessible(true);
  $cache_instance = $cache_prop->getValue($omeka_cache);
  
  echo "4. Informazioni sul bin di cache utilizzato:\n";
  echo "  Classe: " . get_class($cache_instance) . "\n";
  
  // Prova a elencare le chiavi disponibili
  echo "\n5. Prova a elencare le prime 10 chiavi nel bin 'omeka_resources':\n";
  // Questa parte è più complessa e potrebbe richiedere accesso diretto al DB
  // a seconda dell'implementazione della cache in Drupal
  
} catch (\Exception $e) {
  echo "❌ Errore: " . $e->getMessage() . "\n";
}
