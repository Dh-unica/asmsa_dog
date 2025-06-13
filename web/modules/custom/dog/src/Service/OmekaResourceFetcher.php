<?php

namespace Drupal\dog\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\dog\OmekaApiResponse;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Drupal\dog\Service\OmekaUrlService;

/**
 * Defines the OmekaResourceFetcher class.
 *
 * @package Drupal\dog
 */
class OmekaResourceFetcher implements ResourceFetcherInterface {

  use StringTranslationTrait;

  /**
   * The module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected $factory;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The Omeka URL service.
   *
   * @var \Drupal\dog\Service\OmekaUrlService
   */
  protected $omekaUrlService;

  /**
   * Constructs a OmekaResourceFetcher object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Http\ClientFactory $factory
   *   The client factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\dog\Service\OmekaUrlService $omeka_url_service
   *   The Omeka URL service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ClientFactory $factory,
    LoggerChannelFactoryInterface $logger_factory,
    CacheBackendInterface $cache,
    StateInterface $state = NULL,
    OmekaUrlService $omeka_url_service
  ) {
    $this->config = $config_factory->get('dog.settings');
    $this->factory = $factory;
    $this->logger = $logger_factory->get('dog');
    $this->cache = $cache;
    $this->state = $state;
    $this->omekaUrlService = $omeka_url_service;
  }

  /**
   * Gets the Omeka base URL from configuration.
   *
   * @return string|null
   *   The base URL, or null if not set.
   */
  public function getOmekaBaseUrl(): ?string {
    return $this->config->get('base_url');
  }

  /**
   * Convert the value used in API.
   *
   * @param string $original_type
   *   The original type found in response.
   *
   * @return string
   *   A name used for build the uri.
   *
   * @todo complete map!.
   */
  public function mapTypes(string $original_type) {
    switch ($original_type) {
      case 'o:Item':
        return 'items';

      case 'o-module-mapping:Feature':
        return 'mapping_features';

      default:
        $this->logger->warning("Resource type not mapped: {type}", ['type' => $original_type]);
        return null;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function retrieveResource(string $id, string $resource_type): ?array {
    // Definisci la chiave di cache con il pattern corretto in base al tipo di risorsa
    if ($resource_type === 'mapping_features' || $resource_type === 'o-module-mapping:features') {
      // Pattern per features: omeka_geo_data:feature:ID
      $cache_key = "omeka_geo_data:feature:{$id}";
    } else {
      // Pattern per items e altri tipi: omeka_resource:TIPO:ID
      $cache_key = "omeka_resource:{$resource_type}:{$id}";
    }
    
    // Verifica se l'elemento è già in cache
    if ($cache = $this->cache->get($cache_key)) {
      $this->logger->debug('Recuperato elemento @type ID:@id dalla cache permanente', [
        '@type' => $resource_type,
        '@id' => $id,
      ]);
      return $cache->data;
    }
    
    // Se non è in cache, esegui la richiesta API
    $uri = sprintf("api/%s/%s", $resource_type, $id);
    $result = $this->request('GET', $uri);

    if ($result === FALSE) {
      return NULL;
    }

    // Build the return data.
    $data = $result->getContent();
    $data['id'] = $id;
    $data['type'] = $resource_type;
    
    // Salva il risultato nella cache permanente
    // CACHE_PERMANENT indica che l'elemento rimarrà in cache fino a quando non viene esplicitamente cancellato
    $this->cache->set($cache_key, $data, CacheBackendInterface::CACHE_PERMANENT);
    
    $this->logger->debug('Elemento @type ID:@id recuperato da API e salvato in cache permanente', [
      '@type' => $resource_type,
      '@id' => $id,
    ]);
    
    return $data;
  }

  /**
   * {@inheritDoc}
   */
  public function getApiClient(bool $reset_client = FALSE): ClientInterface {
    if (!$reset_client && isset($this->httpClient)) {
      // Reuse the client already instanced.
      return $this->httpClient;
    }

    // Retrieve the base configuration for client.
    $base_url = $this->config->get('base_url');
    if (empty($base_url)) {
      throw new \InvalidArgumentException(sprintf("The base URL is required for use the service %s!", __CLASS__));
    }

    // Add the authentication keys.
    $auth_params = [
      'key_identity' => $this->config->get('key_identity'),
      'key_credential' => $this->config->get('key_credential'),
    ];
    $handler = HandlerStack::create();
    $handler->push(Middleware::mapRequest(function (RequestInterface $request) use ($auth_params) {
      return $request->withUri(Uri::withQueryValues($request->getUri(), $auth_params));
    }));

    // Create http client.
    $this->httpClient = $this->factory->fromOptions([
      'base_uri' => $base_url,
      'handler' => $handler,
    ]);

    return $this->httpClient;
  }

  /**
   * {@inheritDoc}
   */
  public function search(string $resource_type, array $parameters = [], int $page = 0, int $items_per_page = 10, int &$total_results = 0): array {
    // Build the query params.
    foreach ($parameters as $name => $value) {
      $query[$name] = $value;
    }

    $query['page'] = $page;
    $query['per_page'] = $items_per_page;

    // Run request.
    $uri = sprintf("api/%s", $resource_type);
    $result = $this->request('GET', $uri, ['query' => $query]);

    if ($result === FALSE) {
      return [];
    }

    $results = $result->getContent();
    $total_results = $result->getTotalResults();

    if (!is_array($results)) {
      return [];
    }

    foreach ($results as $pos => $item) {

      // We found a items that have more types.
      $type = $item['@type'];
      $type = is_array($type) ? reset($type) : $type;

      // Inject custom values.
      $results[$pos]['id'] = $item["o:id"];

      try {
        $results[$pos]['type'] = $this->mapTypes($type);
      }
      catch (\Exception $exception) {
        $this->logger->warning("Unable to include this element %id in the results: %message.", [
          '%id' => $item['id'],
          '%message' => $exception->getMessage(),
        ]);

        unset($results[$pos]);
      }

    }

    return $results;
  }

  /**
   * {@inheritDoc}
   */
  public function getItemSets(): array {
    // Run request.
    $uri = "api/item_sets";
    $result = $this->request('GET', $uri);

    if ($result === FALSE) {
      return [];
    }

    // Build the return data.
    return $result->getContent();
  }

  /**
   * {@inheritDoc}
   */
  public function getTypes(): array {
    return [
      'items' => "Items",
    ];
  }

  /**
   * Fetches all resources of a given type from Omeka API, caches them,
   * and updates state with statistics.
   *
   * This method is intended to be called by a batch process.
   *
   * @param string $api_resource_type
   *   The resource type slug to be used in API calls (e.g., 'items', 'mapping_features').
   *   This is passed to search() and retrieveResource().
   * @param string $state_key_prefix
   *   The prefix for state keys (e.g., 'dog.omeka_items', 'dog.omeka_features').
   *   Used to store '..._last_update' and '..._count'.
   * @param callable|null $progress_callback
   *   Optional callback for progress updates.
   *   It receives ($processed_in_page, $total_processed_overall, $current_page, $total_api_results).
   * @param int $items_per_page
   *   Number of items to fetch per API call.
   *
   * @return int
   *   The total number of resources processed and cached.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If an API request fails.
   * @throws \InvalidArgumentException
   *   If configuration is missing.
   */
  public function fetchAllResourcesAndCache(string $api_resource_type, string $state_key_prefix, callable $progress_callback = NULL, int $items_per_page = 50): int {
    // DEBUG: Log all'inizio della funzione (temporaneamente INFO per test)
    $this->logger->info('Batch: fetchAllResourcesAndCache START. API Resource Type: @api_type, State Prefix: @state_prefix', [
        '@api_type' => $api_resource_type,
        '@state_prefix' => $state_key_prefix,
    ]);
    $total_processed_overall = 0;
    $current_page = 1; // Omeka API pages are typically 1-indexed.
    $total_api_results = 0; // Will be updated by the first search call.

    if (empty($this->config->get('base_url'))) {
      $this->logger->error('Omeka base URL is not configured. Cannot fetch all resources for type: @type', ['@type' => $api_resource_type]);
      throw new \InvalidArgumentException('Omeka base URL is not configured.');
    }

    $this->logger->info('Starting to fetch all resources for type: @type using state prefix @state_prefix.', ['@type' => $api_resource_type, '@state_prefix' => $state_key_prefix]);

    do {
      $total_api_results_from_search = 0; // Initialize for each call to search
      $results_page = $this->search($api_resource_type, [], $current_page, $items_per_page, $total_api_results_from_search);

      // DEBUG: Log dopo la chiamata a search()
      $this->logger->debug('Batch: Search results for page @page. Count: @count. API Total from search: @api_total_search', [
          '@page' => $current_page,
          '@count' => is_array($results_page) ? count($results_page) : 'N/A (not an array)',
          '@api_total_search' => $total_api_results_from_search,
      ]);

      if ($current_page === 1) {
        $total_api_results = $total_api_results_from_search;
        if ($total_api_results === 0 && empty($results_page)) {
            $this->logger->info('No results found for resource type @type on the first page.', ['@type' => $api_resource_type]);
            break; // No items to process if first page is empty and total is 0.
        }
        $this->logger->info('Total API results for @type: @total', ['@type' => $api_resource_type, '@total' => $total_api_results]);
      }

      if (empty($results_page)) {
        $this->logger->info('No more results for type @type on page @page. Expected total: @total_api, processed so far: @processed', [
            '@type' => $api_resource_type, 
            '@page' => $current_page, 
            '@total_api' => $total_api_results, 
            '@processed' => $total_processed_overall
        ]);
        break; // Exit loop if no items are returned on the current page.
      }

      $processed_in_this_page = 0;
      foreach ($results_page as $item_summary) {
        // DEBUG: Log all'inizio del loop foreach
        $this->logger->debug('Batch: Processing item summary. ID from summary: @item_id_summary', [
            '@item_id_summary' => $item_summary['id'] ?? 'N/A',
        ]);
        if (empty($item_summary['id'])) {
          $this->logger->warning('Item summary missing ID for resource type @type on page @page. Skipping. Item data: @data', ['@type' => $api_resource_type, '@page' => $current_page, '@data' => json_encode($item_summary)]);
          continue;
        }
        $item_id = $item_summary['id'];

        // retrieveResource will fetch from API if not cached, and then cache it.
        $detailed_item = $this->retrieveResource((string) $item_id, $api_resource_type);

        // DEBUG: Log dopo retrieveResource e prima di if ($detailed_item)
        $this->logger->debug('Batch: Result of retrieveResource for ID @item_id. Is detailed_item set? @is_set. Type: @type. Content snippet (if array): @snippet', [
            '@item_id' => $item_id,
            '@is_set' => isset($detailed_item) ? 'Yes' : 'No',
            '@type' => gettype($detailed_item),
            '@snippet' => is_array($detailed_item) ? substr(json_encode(array_keys($detailed_item)), 0, 200) : 'N/A (not an array)',
        ]);

        if ($detailed_item) {
          $total_processed_overall++;
          $processed_in_this_page++;

          // DEBUG: Log resource type and item structure
          $this->logger->debug('Batch: Checking item for URL pre-caching. API Resource Type: @api_type. Item ID: @item_id. Keys in detailed_item: @keys. Has o:media array? @has_media_array', [
            '@api_type' => $api_resource_type,
            '@item_id' => $item_id,
            '@keys' => implode(', ', array_keys($detailed_item)),
            '@has_media_array' => (isset($detailed_item['o:media']) && is_array($detailed_item['o:media'])) ? 'Yes' : 'No',
          ]);
          if (isset($detailed_item['o:media'])) {
             $this->logger->debug('Batch: Type of o:media: @type. Is array? @is_array. Content snippet: @snippet', [
                '@type' => gettype($detailed_item['o:media']),
                '@is_array' => is_array($detailed_item['o:media']) ? 'Yes' : 'No',
                '@snippet' => substr(json_encode($detailed_item['o:media']), 0, 200)
             ]);
          }

          // Pre-cache Omeka URLs if this is an item and omekaUrlService is available.
          if ($api_resource_type === 'items' && isset($this->omekaUrlService) && isset($detailed_item['o:media']) && is_array($detailed_item['o:media'])) {
            foreach ($detailed_item['o:media'] as $media_data) {
              if (isset($media_data['@id'])) {
                $media_api_url = $media_data['@id'];
                $media_id_for_log = $media_data['o:id'] ?? ($media_data['id'] ?? 'N/A');

                // Cache the public URL of the media itself.
                try {
                  $this->omekaUrlService->transformApiUrl($media_api_url);
                } catch (\Exception $e) {
                  $this->logger->warning('Batch: Error pre-caching media URL for media ID @media_id (from item ID @item_id): @error', [
                    '@media_id' => $media_id_for_log,
                    '@item_id' => $item_id,
                    '@error' => $e->getMessage(),
                  ]);
                }
                
                // Cache the public URL of the parent item (derived from media).
                try {
                  $this->omekaUrlService->transformToItemUrl($media_api_url);
                } catch (\Exception $e) {
                  $this->logger->warning('Batch: Error pre-caching item URL for media ID @media_id (from item ID @item_id): @error', [
                    '@media_id' => $media_id_for_log,
                    '@item_id' => $item_id,
                    '@error' => $e->getMessage(),
                  ]);
                }
                
                $this->logger->debug('Batch: Attempted to pre-cache URLs for media ID @media_id (from item ID @item_id).', [
                  '@media_id' => $media_id_for_log,
                  '@item_id' => $item_id, // $item_id is from the outer loop
                ]);
              }
            }
          }
        }
        else {
          $this->logger->warning('Failed to retrieve or cache resource @type with ID @id.', ['@type' => $api_resource_type, '@id' => $item_id]);
        }
      }
      
      $this->logger->debug('Processed @count items from page @page for resource type @type. Overall processed: @overall.', [
        '@count' => $processed_in_this_page,
        '@page' => $current_page,
        '@type' => $api_resource_type,
        '@overall' => $total_processed_overall,
      ]);

      if (is_callable($progress_callback)) {
        call_user_func($progress_callback, $processed_in_this_page, $total_processed_overall, $current_page, $total_api_results);
      }

      if (empty($results_page) || count($results_page) < $items_per_page || ($total_api_results > 0 && $total_processed_overall >= $total_api_results) ) {
        // This was the last page or all expected items processed
        $this->logger->info('Reached end of results for @type. Processed: @overall, API total: @api_total, Page items: @page_count, Per page: @per_page', [
            '@type' => $api_resource_type, 
            '@overall' => $total_processed_overall, 
            '@api_total' => $total_api_results, 
            '@page_count' => count($results_page), 
            '@per_page' => $items_per_page
        ]);
        break;
      }
      
      $current_page++;

    } while (true); // Loop broken internally by conditions above.

    // Update state.
    if ($this->state) {
      // Ensure state is available (it should be, as per constructor type hint, but good practice for robustness)
      $current_time = \Drupal::time()->getRequestTime();
      $this->state->set("{$state_key_prefix}_last_update", $current_time);
      $this->state->set("{$state_key_prefix}_count", $total_processed_overall);
      $this->logger->info('Updated state for @prefix: count = @count, last_update = @time.', [
        '@prefix' => $state_key_prefix,
        '@count' => $total_processed_overall,
        '@time' => $current_time,
      ]);
    }
    else {
         $this->logger->warning('State service not available, cannot save update stats for @prefix.', ['@prefix' => $state_key_prefix]);
    }
    
    $this->logger->info('Finished fetching all resources for type @type. Total processed and cached: @count', ['@type' => $api_resource_type, '@count' => $total_processed_overall]);
    return $total_processed_overall;
  }

  /**
   * Request http client.
   *
   * @param string $method
   *   The HTTP method.
   * @param string $uri
   *   The URI string.
   * @param array $options
   *   The Request options to apply.
   *
   * @return false|\Drupal\dog\OmekaApiResponse
   *   Return the object contains the information of response.
   *   False if found an error.
   */
  protected function request(string $method, string $uri, array $options = []) {
    try {
      // Run request.
      $response = $this->getApiClient()->request($method, $uri, $options);

      // Build the return.
      $return = new OmekaApiResponse($response->getBody());

      if ($response->hasHeader('Omeka-S-Total-Results')) {
        // Include the header information.
        $total_results = $response->getHeader('Omeka-S-Total-Results');
        $return->setTotalResults((int) reset($total_results));
      }


      return $return;
    }
    catch (RequestException $exception) {
      $response = $exception->hasResponse() ?
        (string) $exception->getResponse()->getBody() : '';
      $this->logger->warning("Throw Request Exception when trying to call Omeka system: %request -> %error <- %response.", [
        '%request' => $exception->getRequest()->getRequestTarget(),
        '%error' => $exception->getMessage(),
        '%response' => $response,
      ]);
    }
    catch (GuzzleException $exception) {
      $this->logger->warning("Throw Guzzle Exception when trying to call Omeka system: %error.", [
        '%error' => $exception->getMessage(),
      ]);
    }
    catch (\Exception $exception) {
      $this->logger->warning("Throw generic exception in %request: %message.", [
        '%request' => "{$method}: {$uri}",
        '%message' => $exception->getMessage(),
      ]);
    }
    return FALSE;
  }

}
