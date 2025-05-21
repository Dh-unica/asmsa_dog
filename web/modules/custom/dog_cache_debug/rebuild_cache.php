<?php

/**
 * Script per ricostruire la cache Omeka nel bin corretto.
 *
 * Questo script verifica che il servizio di cache utilizzi il bin corretto
 * e poi forza un aggiornamento di tutte le risorse nella cache.
 */

// Verifica che il servizio stia usando il bin corretto
$omeka_cache = \Drupal::service('dog.omeka_cache');
$cache_handler = \Drupal::service('dog.omeka_cache_cron_handler');

// Ottieni informazioni sulla configurazione
$config_status = $omeka_cache->getConfigurationStatus();
echo "Configurazione servizio cache: " . json_encode($config_status, JSON_PRETTY_PRINT) . "\n\n";

// Verifica quale bin di cache sta effettivamente utilizzando
$reflection = new \ReflectionClass(\Drupal::service('dog.omeka_cache'));
$cache_prop = $reflection->getProperty('cache');
$cache_prop->setAccessible(true);
$cache_instance = $cache_prop->getValue($omeka_cache);
$cache_reflection = new \ReflectionClass($cache_instance);

echo "Servizio cache sta utilizzando: " . get_class($cache_instance) . "\n";
echo "Definito in: " . $cache_reflection->getFileName() . "\n\n";

// Esegui un aggiornamento completo della cache
echo "Avvio aggiornamento della cache Omeka...\n";

try {
  // Usa il metodo corretto sul servizio cache
  $context = [];
  $batch_size = 50; // batch size più grande per l'aggiornamento manuale
  
  echo "Aggiornamento in corso...\n";
  
  // Forza l'aggiornamento dell'intera cache
  do {
    $result = $omeka_cache->updateCache($batch_size, $context);
    
    $progress = isset($context['finished']) ? round($context['finished'] * 100) : 0;
    echo "Progresso: {$progress}% - Processati: " . ($context['sandbox']['progress'] ?? 0) . 
         " di " . ($context['sandbox']['total'] ?? 'N/A') . "\n";
    
    // Se ci sono molte risorse, potrebbe essere necessaria più di una iterazione
  } while (!empty($context['sandbox']) && (!isset($context['finished']) || $context['finished'] < 1));
  
  echo "\nAggiornamento completato!\n";
  echo "Risultati finali: Cache aggiornata al " . date('Y-m-d H:i:s') . "\n";
} catch (\Exception $e) {
  echo "Errore durante l'aggiornamento: " . $e->getMessage() . "\n";
  
  // In caso di errore, mostra informazioni sulle cause
  echo "\nVerifica chiamate disponibili sul servizio:\n";
  $methods = get_class_methods($omeka_cache);
  echo "Metodi disponibili: " . implode(", ", $methods) . "\n";
  
  $methods = get_class_methods($cache_handler);
  echo "Metodi del cron handler: " . implode(", ", $methods) . "\n";
}

// Verifica se ora la cache contiene la risorsa di test
$id = 3412;
$resource_type = 'items';
echo "\nVerifica risorsa $id dopo l'aggiornamento:\n";
$resource = $omeka_cache->getResource($id, $resource_type);

if (!empty($resource)) {
  echo "✅ Risorsa trovata nella cache!\n";
  echo "Dati risorsa: " . substr(json_encode($resource), 0, 150) . "...\n";
} else {
  echo "❌ Risorsa NON trovata nella cache\n";
  
  // Verifica direttamente nel bin specifico
  $cache_factory = \Drupal::service('cache_factory');
  $omeka_resources_bin = $cache_factory->get('omeka_resources');
  $cache_key = "omeka_resource:{$resource_type}:{$id}";
  $cache_data = $omeka_resources_bin->get($cache_key);
  
  if (!empty($cache_data)) {
    echo "Tuttavia, è stata trovata direttamente nel bin 'omeka_resources'!\n";
  } else {
    echo "Non è stata trovata neanche nel bin 'omeka_resources'\n";
  }
}

// Verifica statistiche finali
$state = \Drupal::state();
echo "\nStatistiche finali della cache:\n";
echo "Ultimo aggiornamento: " . date('Y-m-d H:i:s', $state->get('dog.omeka_cache.last_update', 0)) . "\n";
echo "Elementi totali: " . $state->get('dog.omeka_cache.total_items', 0) . "\n";
echo "Elementi in cache: " . $state->get('dog.omeka_cache.cached_items', 0) . "\n";
echo "Elementi con errore: " . $state->get('dog.omeka_cache.error_items', 0) . "\n";
