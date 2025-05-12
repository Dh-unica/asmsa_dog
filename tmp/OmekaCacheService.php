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

    // Get the current resource type.
    $resource_types = $context['sandbox']['resource_types'];
    $current_type_index = $context['sandbox']['current_type_index'];
    
    if ($current_type_index >= count($resource_types)) {
      // All resource types processed.
      $this->state->set(self::STATE_LAST_UPDATE, time());
      $context['finished'] = 1;
      return TRUE;
    }
    
    $resource_type = $resource_types[$current_type_index];
    $page = $context['sandbox']['current_page'];
    
    try {
      // Logga la chiamata API
      $this->logger->notice('Omeka cache: chiamata API per @type pagina @page, batch size @size', [
        '@type' => $resource_type,
        '@page' => $context['sandbox']['current_page'],
        '@size' => $batch_size,
      ]);
      
      // Fetch a batch of resources from the API.
      $total_results = 0;
      $resources = $this->resourceFetcher->search(
        $resource_type, 
        [], 
        $context['sandbox']['current_page'], 
        $batch_size, 
        $total_results
      );
      
      // Logga la risposta API
      $resource_count = is_array($resources) ? count($resources) : 0;
      $this->logger->notice('Omeka cache: ricevuti @count oggetti dalla API per @type pagina @page', [
        '@count' => $resource_count,
        '@type' => $resource_type,
        '@page' => $context['sandbox']['current_page'],
      ]);
      
      // Assicurati che $resources sia un array
      if (!is_array($resources)) {
        $resources = [];
      }
      
      // Process each resource and cache it.
      $resource_count = is_array($resources) ? count($resources) : 0;
      foreach ($resources as $resource) {
        // Get the complete resource data.
        $resource_data = $this->resourceFetcher->retrieveResource(
          $resource['id'],
          $resource['type']
        );
        
        if ($resource_data) {
          // Cache the resource with appropriate tags.
          $cache_key = "omeka_resource:{$resource['type']}:{$resource['id']}";
          $cache_tags = [
            self::CACHE_TAG_ALL,
            "omeka_resource:{$resource['type']}",
            "omeka_resource:{$resource['type']}:{$resource['id']}"
          ];
          
          $this->cache->set(
            $cache_key,
            $resource_data,
            time() + self::CACHE_LIFETIME,
            $cache_tags
          );
          
          $context['results']['processed']++;
        }
        else {
          $context['results']['errors']++;
        }
        
        $context['sandbox']['progress']++;
        $context['finished'] = $context['sandbox']['current_type_index'] / count($resource_types);
      }
      
      // Move to the next page or resource type.
      $resource_count = is_array($resources) ? count($resources) : 0;
      if ($resource_count < $batch_size || $resource_count === 0) {
        // Move to the next resource type.
        $context['sandbox']['current_type_index']++;
        $context['sandbox']['current_page'] = 1; // Riparti da 1 per la prossima risorsa
      }
      else {
        // Move to the next page of the current resource type.
        $context['sandbox']['current_page']++;
      }
      
      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Error updating Omeka cache: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      $context['results']['errors']++;
      
      // Move to the next page or resource type.
      $resource_count = is_array($resources) ? count($resources) : 0;
      if ($resource_count < $batch_size || $resource_count === 0) {
        // Move to the next resource type.
        $context['sandbox']['current_type_index']++;
        $context['sandbox']['current_page'] = 1; // Riparti da 1 per la prossima risorsa
      }
      else {
        // Move to the next page of the current resource type.
        $context['sandbox']['current_page']++;
      }
      
      $context['finished'] = $context['sandbox']['current_type_index'] / count($resource_types);
      
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
    
    $context['sandbox']['resource_types'] = ['items', 'collections', 'exhibits'];
    $context['sandbox']['current_type_index'] = 0;
    $context['sandbox']['current_page'] = 1;
    $context['sandbox']['progress'] = 0;
    
    $context['results'] = [
      'processed' => 0,
      'errors' => 0,
    ];
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
