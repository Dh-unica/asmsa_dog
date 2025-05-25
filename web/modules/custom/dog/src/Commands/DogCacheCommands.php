<?php

namespace Drupal\dog\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drush\Commands\DrushCommands;
use Drupal\Core\Database\Connection;

/**
 * Drush commands per il modulo DOG.
 */
class DogCacheCommands extends DrushCommands {

  /**
   * Il servizio di connessione al database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * DogCacheCommands constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Il servizio di database.
   */
  public function __construct(Connection $database) {
    parent::__construct();
    $this->database = $database;
  }

  /**
   * Backup delle cache DOG prima del cache rebuild.
   *
   * @hook pre-command cache:rebuild
   */
  public function preRebuild() {
    $this->output()->writeln('Creazione backup delle cache DOG...');
    
    // Array per memorizzare i dati di backup
    $dog_cache_backup = [];
    
    try {
      // Salva le cache delle risorse Omeka
      $result = $this->database->select('cache_omeka_resources', 'c')
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
      
      $this->output()->writeln(sprintf('Salvati %d elementi dalla tabella cache_omeka_resources', 
        count($dog_cache_backup['omeka_resources'] ?? [])));
      
      // Salva le cache dei dati geografici
      $result = $this->database->select('cache_omeka_geo_data', 'c')
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
      
      $this->output()->writeln(sprintf('Salvati %d elementi dalla tabella cache_omeka_geo_data', 
        count($dog_cache_backup['omeka_geo_data'] ?? [])));
      
      // Crea il file di backup
      $backup_file = DRUPAL_ROOT . '/../sites/default/files/dog_drush_cache_backup.php';
      $backup_content = "<?php\n\n/**\n * @file\n * Backup automatico delle cache DOG.\n * Generato il " . date('Y-m-d H:i:s') . "\n */\n\n";
      $backup_content .= '$dog_cache_backup = ' . var_export($dog_cache_backup, TRUE) . ";\n";
      
      file_put_contents($backup_file, $backup_content);
      $this->output()->writeln('Backup delle cache DOG completato con successo');
    }
    catch (\Exception $e) {
      $this->logger()->error('Errore nel creare il backup delle cache DOG: ' . $e->getMessage());
    }
  }

  /**
   * Ripristino delle cache DOG dopo il cache rebuild.
   *
   * @hook post-command cache:rebuild
   */
  public function postRebuild() {
    $this->output()->writeln('Ripristino delle cache DOG...');
    
    // Carica il file di backup
    $backup_file = DRUPAL_ROOT . '/../sites/default/files/dog_drush_cache_backup.php';
    
    if (!file_exists($backup_file)) {
      $this->logger()->warning('Nessun file di backup delle cache DOG trovato');
      return;
    }
    
    include $backup_file;
    
    try {
      // Ripristina la tabella cache_omeka_resources
      if (!empty($dog_cache_backup['omeka_resources'])) {
        foreach ($dog_cache_backup['omeka_resources'] as $row) {
          try {
            $this->database->merge('cache_omeka_resources')
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
            $this->logger()->error('Errore nel ripristinare elemento cache omeka_resources ' . $row['cid'] . ': ' . $e->getMessage());
          }
        }
        
        $this->output()->writeln(sprintf('Ripristinati %d elementi nella tabella cache_omeka_resources', 
          count($dog_cache_backup['omeka_resources'])));
      }
      
      // Ripristina la tabella cache_omeka_geo_data
      if (!empty($dog_cache_backup['omeka_geo_data'])) {
        foreach ($dog_cache_backup['omeka_geo_data'] as $row) {
          try {
            $this->database->merge('cache_omeka_geo_data')
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
            $this->logger()->error('Errore nel ripristinare elemento cache omeka_geo_data ' . $row['cid'] . ': ' . $e->getMessage());
          }
        }
        
        $this->output()->writeln(sprintf('Ripristinati %d elementi nella tabella cache_omeka_geo_data', 
          count($dog_cache_backup['omeka_geo_data'])));
      }
      
      $this->output()->writeln('Ripristino delle cache DOG completato con successo');
    }
    catch (\Exception $e) {
      $this->logger()->error('Errore nel ripristinare le cache DOG: ' . $e->getMessage());
    }
  }

}
