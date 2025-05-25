<?php

namespace Drupal\dog\EventSubscriber;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber che si occupa di preservare le cache personalizzate.
 *
 * Questa classe salva e ripristina le cache dog anche dopo un drush cr.
 */
class CachePersistenceSubscriber implements EventSubscriberInterface {

  /**
   * Il servizio di connessione al database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Il servizio di logging.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Il file di stato delle cache.
   *
   * @var string
   */
  protected $cacheStateFile;

  /**
   * Costruttore per CachePersistenceSubscriber.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Il servizio di connessione al database.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   La factory dei logger.
   */
  public function __construct(Connection $database, LoggerChannelFactoryInterface $logger_factory) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dog_cache');
    // File in sites/default/files per salvare lo stato della cache
    $this->cacheStateFile = DRUPAL_ROOT . '/../sites/default/files/dog_cache_state.json';
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Alta priorità per eseguire il ripristino prima che altri eventi vengano processati
    $events[KernelEvents::REQUEST][] = ['checkAndRestoreCache', 1000];
    return $events;
  }

  /**
   * Verifica se ci sono cache da ripristinare.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   L'evento della richiesta.
   */
  public function checkAndRestoreCache(RequestEvent $event) {
    if (!$event->isMainRequest()) {
      return;
    }

    // Verifica se esiste il file di stato
    if (!file_exists($this->cacheStateFile)) {
      // Salva lo stato attuale delle cache (per il prossimo rebuild)
      $this->saveCacheState();
      return;
    }

    // Carica il file di stato
    $cache_data = file_get_contents($this->cacheStateFile);
    $cache_state = json_decode($cache_data, TRUE);
    
    if (empty($cache_state)) {
      $this->logger->warning('File di stato cache trovato ma vuoto o non valido');
      return;
    }

    // Ripristina le cache
    $this->restoreCaches($cache_state);
    
    // Rimuovi il file di stato dopo il ripristino
    unlink($this->cacheStateFile);
    
    $this->logger->info('Cache DOG ripristinate con successo dopo cache rebuild');
  }

  /**
   * Salva lo stato attuale delle cache.
   */
  protected function saveCacheState() {
    $cache_state = [];
    
    // Salva le cache delle risorse Omeka
    try {
      $query = $this->database->select('cache_omeka_resources', 'c')
        ->fields('c')
        ->execute();
      
      foreach ($query as $row) {
        $cache_state['omeka_resources'][] = [
          'cid' => $row->cid,
          'data' => base64_encode($row->data),
          'expire' => $row->expire,
          'created' => $row->created,
          'serialized' => $row->serialized,
          'tags' => $row->tags,
          'checksum' => $row->checksum,
        ];
      }
      
      $this->logger->info('Salvati @count elementi dalla cache omeka_resources', [
        '@count' => count($cache_state['omeka_resources'] ?? []),
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Errore nel salvare lo stato della cache omeka_resources: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
    
    // Salva le cache dei dati geografici
    try {
      $query = $this->database->select('cache_omeka_geo_data', 'c')
        ->fields('c')
        ->execute();
      
      foreach ($query as $row) {
        $cache_state['omeka_geo_data'][] = [
          'cid' => $row->cid,
          'data' => base64_encode($row->data),
          'expire' => $row->expire,
          'created' => $row->created,
          'serialized' => $row->serialized,
          'tags' => $row->tags,
          'checksum' => $row->checksum,
        ];
      }
      
      $this->logger->info('Salvati @count elementi dalla cache omeka_geo_data', [
        '@count' => count($cache_state['omeka_geo_data'] ?? []),
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Errore nel salvare lo stato della cache omeka_geo_data: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
    
    // Salva il file di stato
    try {
      file_put_contents($this->cacheStateFile, json_encode($cache_state));
      $this->logger->info('Stato delle cache DOG salvato con successo');
    }
    catch (\Exception $e) {
      $this->logger->error('Errore nel salvare il file di stato delle cache: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Ripristina le cache dai dati salvati.
   *
   * @param array $cache_state
   *   I dati delle cache da ripristinare.
   */
  protected function restoreCaches(array $cache_state) {
    // Ripristina le cache delle risorse Omeka
    if (!empty($cache_state['omeka_resources'])) {
      foreach ($cache_state['omeka_resources'] as $item) {
        try {
          $this->database->merge('cache_omeka_resources')
            ->key(['cid' => $item['cid']])
            ->fields([
              'data' => base64_decode($item['data']),
              'expire' => $item['expire'],
              'created' => $item['created'],
              'serialized' => $item['serialized'],
              'tags' => $item['tags'],
              'checksum' => $item['checksum'],
            ])
            ->execute();
        }
        catch (\Exception $e) {
          $this->logger->error('Errore nel ripristinare elemento cache omeka_resources @cid: @error', [
            '@cid' => $item['cid'],
            '@error' => $e->getMessage(),
          ]);
        }
      }
      
      $this->logger->info('Ripristinati @count elementi nella cache omeka_resources', [
        '@count' => count($cache_state['omeka_resources']),
      ]);
    }
    
    // Ripristina le cache dei dati geografici
    if (!empty($cache_state['omeka_geo_data'])) {
      foreach ($cache_state['omeka_geo_data'] as $item) {
        try {
          $this->database->merge('cache_omeka_geo_data')
            ->key(['cid' => $item['cid']])
            ->fields([
              'data' => base64_decode($item['data']),
              'expire' => $item['expire'],
              'created' => $item['created'],
              'serialized' => $item['serialized'],
              'tags' => $item['tags'],
              'checksum' => $item['checksum'],
            ])
            ->execute();
        }
        catch (\Exception $e) {
          $this->logger->error('Errore nel ripristinare elemento cache omeka_geo_data @cid: @error', [
            '@cid' => $item['cid'],
            '@error' => $e->getMessage(),
          ]);
        }
      }
      
      $this->logger->info('Ripristinati @count elementi nella cache omeka_geo_data', [
        '@count' => count($cache_state['omeka_geo_data']),
      ]);
    }
  }

}
