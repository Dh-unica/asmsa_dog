<?php

/**
 * @file
 * Drush hooks per il modulo dog.
 */

/**
 * Implements hook_drush_command_alter().
 *
 * Questo hook permette di alterare i comandi drush.
 */
function dog_drush_command_alter(&$command) {
  // Aggiungiamo un post-callback al comando 'cache-rebuild' per preservare le nostre cache
  if (isset($command['command']) && $command['command'] == 'cache-rebuild') {
    $command['post-callback'][] = 'dog_post_cache_rebuild';
  }
}

/**
 * Callback eseguito dopo che il comando 'cache-rebuild' è stato completato.
 */
function dog_post_cache_rebuild() {
  // Carica il database service
  $connection = \Drupal::database();
  
  // Backup file delle tabelle cache
  $backup_file = DRUPAL_ROOT . '/../sites/default/files/dog_cache_backup.php';
  
  // Se esiste un backup precedente, lo carichiamo e ripristiniamo i dati
  if (file_exists($backup_file)) {
    include $backup_file;
    
    // Ripristina la tabella cache_omeka_resources
    if (!empty($dog_cache_backup['omeka_resources'])) {
      foreach ($dog_cache_backup['omeka_resources'] as $row) {
        try {
          $connection->merge('cache_omeka_resources')
            ->key(['cid' => $row['cid']])
            ->fields([
              'data' => $row['data'],
              'expire' => $row['expire'],
              'created' => $row['created'],
              'serialized' => $row['serialized'],
              'tags' => $row['tags'],
              'checksum' => $row['checksum'],
            ])
            ->execute();
        }
        catch (\Exception $e) {
          drush_log('Errore nel ripristinare elemento cache omeka_resources ' . $row['cid'] . ': ' . $e->getMessage(), 'error');
        }
      }
      
      drush_log('Ripristinati ' . count($dog_cache_backup['omeka_resources']) . ' elementi nella cache omeka_resources', 'success');
    }
    
    // Ripristina la tabella cache_omeka_geo_data
    if (!empty($dog_cache_backup['omeka_geo_data'])) {
      foreach ($dog_cache_backup['omeka_geo_data'] as $row) {
        try {
          $connection->merge('cache_omeka_geo_data')
            ->key(['cid' => $row['cid']])
            ->fields([
              'data' => $row['data'],
              'expire' => $row['expire'],
              'created' => $row['created'],
              'serialized' => $row['serialized'],
              'tags' => $row['tags'],
              'checksum' => $row['checksum'],
            ])
            ->execute();
        }
        catch (\Exception $e) {
          drush_log('Errore nel ripristinare elemento cache omeka_geo_data ' . $row['cid'] . ': ' . $e->getMessage(), 'error');
        }
      }
      
      drush_log('Ripristinati ' . count($dog_cache_backup['omeka_geo_data']) . ' elementi nella cache omeka_geo_data', 'success');
    }
    
    drush_log('Cache DOG ripristinate con successo dopo cache rebuild', 'success');
  }
  else {
    drush_log('Nessun backup delle cache DOG trovato', 'warning');
  }
  
  // Creiamo un nuovo backup per il prossimo rebuild
  try {
    // Array per memorizzare i dati di backup
    $dog_cache_backup = [];
    
    // Salva le cache delle risorse Omeka
    $result = $connection->select('cache_omeka_resources', 'c')
      ->fields('c')
      ->execute();
    
    foreach ($result as $row) {
      $dog_cache_backup['omeka_resources'][] = [
        'cid' => $row->cid,
        'data' => $row->data,
        'expire' => $row->expire,
        'created' => $row->created,
        'serialized' => $row->serialized,
        'tags' => $row->tags,
        'checksum' => $row->checksum,
      ];
    }
    
    // Salva le cache dei dati geografici
    $result = $connection->select('cache_omeka_geo_data', 'c')
      ->fields('c')
      ->execute();
    
    foreach ($result as $row) {
      $dog_cache_backup['omeka_geo_data'][] = [
        'cid' => $row->cid,
        'data' => $row->data,
        'expire' => $row->expire,
        'created' => $row->created,
        'serialized' => $row->serialized,
        'tags' => $row->tags,
        'checksum' => $row->checksum,
      ];
    }
    
    // Crea il file di backup come array PHP
    $backup_content = "<?php\n\n/**\n * @file\n * Backup automatico delle cache DOG.\n * Generato il " . date('Y-m-d H:i:s') . "\n */\n\n";
    $backup_content .= '$dog_cache_backup = ' . var_export($dog_cache_backup, TRUE) . ";\n";
    
    file_put_contents($backup_file, $backup_content);
    drush_log('Nuovo backup delle cache DOG creato con successo', 'success');
  }
  catch (\Exception $e) {
    drush_log('Errore nel creare il backup delle cache DOG: ' . $e->getMessage(), 'error');
  }
}
