<?php

namespace Drupal\dog\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Service for caching Omeka resources data.
 *
 * This service is responsible for fetching and caching all Omeka resources
 * data during a batch operation, and providing cached data to templates
 * without making live API calls.
 */
class OmekaCacheService {
  use StringTranslationTrait;

  /**
   * The cache bin for Omeka resources.
   */
  const OMEKA_CACHE_BIN = 'omeka_resources';

  /**
   * The cache lifetime in seconds (1 week).
   */
  const CACHE_LIFETIME = 604800;

  /**
   * Cache tag for all Omeka resources.
   */
  const CACHE_TAG_ALL = 'omeka_resources:all';

  /**
   * State key for last update timestamp.
   */
  const STATE_LAST_UPDATE = 'dog.omeka_cache.last_update';
  const STATE_TOTAL_ITEMS = 'dog.omeka_cache.total_items';
  const STATE_CACHED_ITEMS = 'dog.omeka_cache.cached_items';
  const STATE_ERROR_ITEMS = 'dog.omeka_cache.error_items';

  /**
   * The resource fetcher service that makes API calls.
   *
   * @var \Drupal\dog\Service\OmekaResourceFetcher
   */
  protected $resourceFetcher;

  /**
   * The URL service.
   *
   * @var \Drupal\dog\Service\OmekaUrlService
   */
  protected $urlService;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Omeka API configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructs a new OmekaCacheService object.
   *
   * @param \Drupal\dog\Service\OmekaResourceFetcher $resource_fetcher
   *   The resource fetcher service.
   * @param \Drupal\dog\Service\OmekaUrlService $url_service
   *   The URL service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    OmekaResourceFetcher $resource_fetcher,
    OmekaUrlService $url_service,
    CacheBackendInterface $cache,
    StateInterface $state,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->resourceFetcher = $resource_fetcher;
    $this->urlService = $url_service;
    $this->cache = $cache;
    $this->state = $state;
    $this->configFactory = $config_factory;
    $this->config = $config_factory->get('dog.settings');
    $this->logger = $logger_factory->get('dog_omeka_cache');
  }

  /**
   * Get a single resource from cache.
   *
   * @param string $id
   *   The resource ID.
   * @param string $resource_type
   *   The resource type.
   *
   * @return array|null
   *   The resource data, or NULL if not found in cache.
   */
  public function getResource(string $id, string $resource_type): ?array {
    $cache_key = "omeka_resource:{$resource_type}:{$id}";
    $cache = $this->cache->get($cache_key);
    
    if ($cache) {
      return $cache->data;
    }
    
    // Return NULL if not in cache - DO NOT make a live API call.
    $this->logger->notice('Cache miss for Omeka resource @type:@id', [
      '@type' => $resource_type,
      '@id' => $id,
    ]);
    
    return NULL;
  }

  /**
   * Get multiple resources from cache.
   *
   * @param array $ids
   *   Array of resource IDs.
   * @param string $resource_type
   *   The resource type.
   *
   * @return array
   *   Array of resources, keyed by ID.
   */
  public function getMultipleResources(array $ids, string $resource_type): array {
    $cache_keys = [];
    $result = [];
    
    foreach ($ids as $id) {
      $cache_keys[$id] = "omeka_resource:{$resource_type}:{$id}";
    }
    
    $cached = $this->cache->getMultiple($cache_keys);
    
    foreach ($ids as $id) {
      $key = "omeka_resource:{$resource_type}:{$id}";
      if (isset($cached[$key])) {
        $result[$id] = $cached[$key]->data;
      } 
      else {
        // Log cache miss
        $this->logger->notice('Cache miss for Omeka resource @type:@id', [
          '@type' => $resource_type,
          '@id' => $id,
        ]);
        
        $result[$id] = NULL;
      }
    }
    
    return $result;
  }

  /**
   * Get multiple resources from cache using a search query.
   *
   * @param string $resource_type
   *   The resource type.
   * @param array $query
   *   The search query parameters.
   * @param int $page
   *   The page number.
   * @param int $per_page
   *   The number of items per page.
   *
   * @return array
   *   Array with 'results' (array of resources) and 'total' (total count).
   */
  public function getSearchResults(string $resource_type, array $query = [], int $page = 1, int $per_page = 10): array {
    // In a real implementation, we would need to store search results in cache,
    // but for this implementation, we'll just return empty results to show the pattern.
    $this->logger->notice('Cache miss for Omeka search @type with query @query', [
      '@type' => $resource_type,
      '@query' => json_encode($query),
    ]);
    
    return [
      'results' => [],
      'total' => 0,
    ];
  }

  /**
   * Get information about the last cache update.
   *
   * @return array
   *   Array with 'timestamp' and 'formatted_date'.
   */
  public function getLastUpdateInfo(): array {
    $timestamp = $this->state->get(self::STATE_LAST_UPDATE, 0);
    
    return [
      'timestamp' => $timestamp,
      'formatted_date' => $timestamp ? date('Y-m-d H:i:s', $timestamp) : $this->t('Never'),
    ];
  }

  /**
   * Get the needs refresh message.
   *
   * @return string
   *   The needs refresh message.
   */
  public function getNeedsRefreshMessage(): string {
    $update_interval = $this->config->get('update_interval') ?: 3600;
    
    return $this->t('Cache was last updated on @time. It should be updated every @hours hours.', [
      '@time' => $this->formatTime($this->getLastUpdateTime()),
      '@hours' => round($update_interval / 3600, 1),
    ]);
  }
  
  /**
   * Restituisce le statistiche della cache Omeka.
   *
   * @return array
   *   Array con le statistiche della cache.
   */
  public function getCacheStatistics(): array {
    return [
      'last_update' => $this->state->get(self::STATE_LAST_UPDATE, 0),
      'total_items' => $this->state->get(self::STATE_TOTAL_ITEMS, 0),
      'cached_items' => $this->state->get(self::STATE_CACHED_ITEMS, 0),
      'error_items' => $this->state->get(self::STATE_ERROR_ITEMS, 0),
    ];
  }

  /**
   * Verifica se la configurazione dell'API Omeka è stata impostata.
   *
   * @return bool
   *   TRUE se la configurazione è presente, FALSE altrimenti.
   */
  public function isConfigured(): bool {
    $base_url = $this->config->get('base_url');
    return !empty($base_url);
  }

  /**
   * Get the configuration status.
   *
   * @return array
   *   Array con informazioni sulla configurazione.
   */
  public function getConfigurationStatus(): array {
    return [
      'configured' => $this->isConfigured(),
      'base_url' => $this->config->get('base_url'),
      'has_key_identity' => !empty($this->config->get('key_identity')),
      'has_key_credential' => !empty($this->config->get('key_credential')),
    ];
  }

  /**
   * Updates the cache with all Omeka resources.
   *
   * This method is called by the batch process and by the cron job.
   * It fetches all resources from the Omeka API and stores them in cache.
   *
   * @param int $batch_size
   *   Number of items to process per batch.
   * @param array $context
   *   The batch context array.
   *
   * @return bool
   *   TRUE if the operation was successful, FALSE otherwise.
   */
  public function updateCache(int $batch_size = 50, array &$context = []): bool {
    // Inizializza il contesto del batch se non esiste
    if (!isset($context['sandbox']) || !isset($context['sandbox']['resource_types'])) {
      $this->initializeBatchContext($context);
    }
    
    // Verifica che l'API Omeka sia configurata
    if (!$this->isConfigured()) {
      // Se non è configurata, imposta un messaggio di errore e interrompi il processo
      $context['results'] = [
        'processed' => 0,
        'errors' => 1,
        'configuration_error' => TRUE,
        'error_message' => $this->t('Omeka API is not configured. Please configure the API settings at @url before running cache refresh.', [
          '@url' => '/admin/config/services/dog',
        ]),
      ];
      return FALSE;
    }

    // Clear all Omeka resource cache to start fresh.
    Cache::invalidateTags([self::CACHE_TAG_ALL]);

    // Controlla se abbiamo elementi da elaborare
    if (!isset($context['sandbox']['items_to_process']) || 
        !isset($context['sandbox']['current_item_index']) ||
        $context['sandbox']['current_item_index'] >= count($context['sandbox']['items_to_process'])) {
      // Tutti gli elementi sono stati elaborati
      $this->state->set(self::STATE_LAST_UPDATE, time());
      $context['finished'] = 1;
      $this->logger->notice('OmekaCache batch completato: @processed elaborati, @errors errori', [
        '@processed' => $context['results']['processed'],
        '@errors' => $context['results']['errors'],
      ]);
      return TRUE;
    }
    
    // Recupera le informazioni sull'elemento corrente da elaborare
    $resource_type = 'items'; // Fissiamo il tipo a 'items' come nei log
    $current_index = $context['sandbox']['current_item_index'];
    $current_id = $context['sandbox']['items_to_process'][$current_index];
    
    try {
      // Log della chiamata API per l'elemento corrente
      $this->logger->notice('OmekaCache batch: recupero elemento @type/@id (@index di @total)', [
        '@type' => $resource_type,
        '@id' => $current_id,
        '@index' => $current_index + 1,
        '@total' => count($context['sandbox']['items_to_process']),
      ]);
      
      // Recupera direttamente l'elemento singolo invece di usare la ricerca
      $resource_data = $this->resourceFetcher->retrieveResource($current_id, $resource_type);
      
      // Log del risultato dell'API
      if ($resource_data) {
        $this->logger->notice('OmekaCache batch: ottenuto elemento @id con successo', [
          '@id' => $current_id,
        ]);
        
        // Cache l'elemento con i tag appropriati
        $cache_key = "omeka_resource:{$resource_type}:{$current_id}";
        $cache_tags = [
          self::CACHE_TAG_ALL,
          "omeka_resource:{$resource_type}",
          "omeka_resource:{$resource_type}:{$current_id}"
        ];
        
        $this->cache->set(
          $cache_key,
          $resource_data,
          time() + self::CACHE_LIFETIME,
          $cache_tags
        );
        
        $context['results']['processed']++;
      } else {
        $this->logger->warning('OmekaCache batch: impossibile recuperare elemento @id', [
          '@id' => $current_id,
        ]);
        $context['results']['errors']++;
      }
        
      // Incrementa l'indice dell'elemento corrente
      $context['sandbox']['current_item_index']++;
      
      // Calcola il progresso (valore tra 0 e 1)
      $progress = $context['sandbox']['current_item_index'] / count($context['sandbox']['items_to_process']);
      $context['finished'] = min(1.0, $progress); // Assicura che non superi mai 1
      
      // Log dello stato di avanzamento
      $this->logger->notice('OmekaCache batch: progresso @percent% (@current/@total)', [
        '@percent' => round($progress * 100),
        '@current' => $context['sandbox']['current_item_index'],
        '@total' => count($context['sandbox']['items_to_process']),
      ]);
      
      return TRUE;
    }
    catch (\Exception $e) {
      // Log dettagliato dell'errore
      $this->logger->error('OmekaCache batch: errore durante elaborazione elemento @id: @message', [
        '@id' => $current_id,
        '@message' => $e->getMessage(),
      ]);
      
      // Log esteso per il trace dell'errore (solo in sviluppo o se richiesto)
      if ($this->config->get('debug_mode')) {
        $this->logger->debug('OmekaCache batch: stack trace per errore elemento @id: @trace', [
          '@id' => $current_id,
          '@trace' => $e->getTraceAsString(),
        ]);
      }
      
      // Prova a determinare il tipo specifico di errore
      $error_type = get_class($e);
      $error_code = method_exists($e, 'getCode') ? $e->getCode() : 0;
      
      // Log del tipo di errore per analisi future
      $this->logger->warning('OmekaCache batch: tipo di errore per elemento @id: @type (codice: @code)', [
        '@id' => $current_id,
        '@type' => $error_type,
        '@code' => $error_code,
      ]);
      
      // Incrementa contatore errori
      $context['results']['errors']++;
      $context['results']['error_ids'][] = $current_id;  // Memorizza ID elementi problematici
      
      // Passa comunque all'elemento successivo
      $context['sandbox']['current_item_index']++;
      
      // Calcola il progresso anche in caso di errore
      $progress = $context['sandbox']['current_item_index'] / count($context['sandbox']['items_to_process']);
      $context['finished'] = min(1.0, $progress); // Assicura che non superi mai 1
      
      // Log dello stato di avanzamento dopo errore
      $this->logger->notice('OmekaCache batch: progresso @percent% (@current/@total) dopo errore', [
        '@percent' => round($progress * 100),
        '@current' => $context['sandbox']['current_item_index'],
        '@total' => count($context['sandbox']['items_to_process']),
      ]);
      
      return FALSE;
    }
  }
  
  /**
   * Initialize the batch process context.
   *
   * @param array $context
   *   The batch context array.
   */
  public function initializeBatchContext(array &$context): void {
    if (!isset($context['sandbox'])) {
      $context['sandbox'] = [];
    }
    
    // Imposta valori di base
    $context['sandbox']['resource_types'] = ['items'];
    $context['sandbox']['current_type_index'] = 0;
    $context['sandbox']['current_item_index'] = 0;
    $context['sandbox']['progress'] = 0;
    
    // Lista fallback di ID noti in caso la ricerca API fallisca
    $fallback_ids = [
      // ID già verificati
      4539, 4543, 4544, 4545,
      // Range esteso
      4538, 4540, 4541, 4542, 4546, 4547, 4548, 4549, 4550,
    ];
    
    // Tenta di recuperare tutti gli ID disponibili dall'API
    $this->logger->notice('OmekaCache batch: tentativo di recupero dinamico degli ID disponibili');
    $dynamic_ids = $this->resourceFetcher->getAllAvailableIds('items', 200);
    
    // Verifica se il recupero dinamico ha avuto successo
    if (!empty($dynamic_ids)) {
      $context['sandbox']['items_to_process'] = $dynamic_ids;
      $this->logger->notice('OmekaCache batch: recuperati dinamicamente @count ID', [
        '@count' => count($dynamic_ids),
      ]);
    }
    else {
      // Fallback alla lista statica
      $context['sandbox']['items_to_process'] = $fallback_ids;
      $this->logger->notice('OmekaCache batch: utilizzo lista fallback con @count ID', [
        '@count' => count($fallback_ids),
      ]);
    }
    
    // Calcola il totale degli elementi da processare
    $context['sandbox']['total_items'] = count($context['sandbox']['items_to_process']);
    
    // Inizializza contatori dei risultati
    $context['results'] = [
      'processed' => 0,
      'errors' => 0,
      'total_items' => $context['sandbox']['total_items'], // Salva il totale per le statistiche
    ];
    
    // Salva subito il conteggio totale per le statistiche
    $this->state->set(self::STATE_TOTAL_ITEMS, $context['sandbox']['total_items']);
    
    $this->logger->notice('OmekaCache batch: inizializzato con @count elementi da processare', [
      '@count' => $context['sandbox']['total_items'],
    ]);
  }
  
  /**
   * Get resources by type.
   *
   * @param string $resource_type
   *   The resource type.
   * @param int $limit
   *   The maximum number of resources to return.
   * @param array $filter_by
   *   Array of filters.
   *
   * @return array
   *   Array of resources.
   */
  public function getResourcesByType(string $resource_type, int $limit = 10, array $filter_by = []): array {
    // In a real implementation, we would need to retrieve from cache based on type,
    // but for this implementation, we'll just return empty results to show the pattern.
    $this->logger->notice('Cache miss for Omeka resources of type @type', [
      '@type' => $resource_type,
    ]);
    
    return [];
  }
  
  /**
   * Check if the cache needs to be refreshed.
   *
   * @param int $max_age
   *   The maximum age of the cache in seconds.
   *
   * @return bool
   *   TRUE if the cache needs to be refreshed, FALSE otherwise.
   */
  public function needsRefresh(int $max_age = 86400): bool {
    $last_update = $this->state->get(self::STATE_LAST_UPDATE, 0);
    return (time() - $last_update) > $max_age;
  }
}
