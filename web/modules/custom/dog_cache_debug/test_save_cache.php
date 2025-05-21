<?php

/**
 * Script di test per verificare il salvataggio nella cache Omeka
 */

$id = '8677';
$resource_type = 'items';

echo "Test di salvataggio nella cache per risorsa Omeka {$resource_type}:{$id}\n";
echo "------------------------------------------------------------\n\n";

// Recupera i servizi necessari
$cache_factory = \Drupal::service('cache_factory');
$omeka_resources_bin = $cache_factory->get('omeka_resources');
$resource_fetcher = \Drupal::service('dog.omeka_resource_fetcher');
$omeka_cache = \Drupal::service('dog.omeka_cache');

// 1. Verifica che il bin di cache esista
echo "1. Verifica bin di cache 'omeka_resources':\n";
if ($omeka_resources_bin) {
  echo "✅ Bin di cache 'omeka_resources' disponibile\n\n";
} else {
  echo "❌ Bin di cache 'omeka_resources' NON disponibile\n\n";
  exit;
}

// 2. Recupera la risorsa dall'API
echo "2. Recupero risorsa dall'API:\n";
$resource_data = $resource_fetcher->getResource($resource_type, $id);
if (!$resource_data) {
  echo "❌ Impossibile recuperare la risorsa dall'API\n\n";
  exit;
}
echo "✅ Risorsa recuperata dall'API\n\n";

// 3. Prova a salvare manualmente nella cache
echo "3. Salvataggio manuale nella cache:\n";
$cache_key = "omeka_resource:{$resource_type}:{$id}";
$cache_tags = [
  'omeka_resources:all',
  "omeka_resource:{$resource_type}",
  "omeka_resource:{$resource_type}:{$id}"
];

try {
  $omeka_resources_bin->set($cache_key, $resource_data, time() + 3600, $cache_tags);
  echo "✅ Risorsa salvata nella cache con chiave: {$cache_key}\n\n";
} catch (\Exception $e) {
  echo "❌ Errore nel salvataggio: " . $e->getMessage() . "\n\n";
}

// 4. Verifica che sia stata salvata
echo "4. Verifica del salvataggio:\n";
$cache_data = $omeka_resources_bin->get($cache_key);
if (!empty($cache_data)) {
  echo "✅ Risorsa trovata nella cache dopo il salvataggio\n";
  echo "Data creazione: " . date('Y-m-d H:i:s', $cache_data->created) . "\n";
  echo "Data scadenza: " . date('Y-m-d H:i:s', $cache_data->expire) . "\n";
  echo "Dati: " . substr(json_encode($cache_data->data), 0, 150) . "...\n\n";
} else {
  echo "❌ Risorsa NON trovata nella cache dopo il salvataggio manuale\n\n";
}

// 5. Verifica se ora è accessibile tramite il servizio
echo "5. Verifica tramite OmekaCacheService dopo il salvataggio manuale:\n";
$resource = $omeka_cache->getResource($id, $resource_type);
if (!empty($resource)) {
  echo "✅ Risorsa trovata tramite servizio\n";
  echo "Dati: " . substr(json_encode($resource), 0, 150) . "...\n\n";
} else {
  echo "❌ Risorsa NON trovata tramite servizio\n\n";
}
