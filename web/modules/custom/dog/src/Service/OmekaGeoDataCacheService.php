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
 * Service for caching Omeka geographical data.
 *
 * This service is responsible for preloading and caching all geographical
 * data needed for Omeka maps during a batch operation, ensuring that the
 * first node view is as fast as subsequent views.
 */
class OmekaGeoDataCacheService {
  use StringTranslationTrait;

  /**
   * The cache bin for Omeka geo data.
   */
  const OMEKA_GEO_CACHE_BIN = 'omeka_geo_data';

  /**
   * The cache lifetime in seconds (1 week).
   */
  const CACHE_LIFETIME = 604800;

  /**
   * Cache tag for all Omeka geo data.
   */
  const CACHE_TAG_ALL = 'dog_omeka_geo_data:all';

  /**
   * State key for last update timestamp.
   */
  const STATE_LAST_GEO_UPDATE = 'dog.omeka_geo_cache.last_update';
  const STATE_TOTAL_GEO_ITEMS = 'dog.omeka_geo_cache.total_items';
  const STATE_CACHED_GEO_ITEMS = 'dog.omeka_geo_cache.cached_items';
  const STATE_ERROR_GEO_ITEMS = 'dog.omeka_geo_cache.error_items';

  /**
   * The Omeka cache service.
   *
   * @var \Drupal\dog\Service\OmekaCacheService
   */
  protected $omekaCacheService;
  
  /**
   * The resource fetcher service.
   *
   * @var \Drupal\dog\Service\OmekaResourceFetcher
   */
  protected $resourceFetcher;

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
   * Constructs a new OmekaGeoDataCacheService object.
   *
   * @param \Drupal\dog\Service\OmekaCacheService $omeka_cache_service
   *   The Omeka cache service.
   * @param \Drupal\dog\Service\OmekaResourceFetcher $resource_fetcher
   *   The resource fetcher service.
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
    OmekaCacheService $omeka_cache_service,
    OmekaResourceFetcher $resource_fetcher,
    CacheBackendInterface $cache,
    StateInterface $state,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->omekaCacheService = $omeka_cache_service;
    $this->resourceFetcher = $resource_fetcher;
    $this->cache = $cache;
    $this->state = $state;
    $this->configFactory = $config_factory;
    $this->config = $config_factory->get('dog.settings');
    $this->logger = $logger_factory->get('dog_omeka_geo_cache');
  }

  /**
   * Get geographical data for a specific Omeka resource from cache.
   *
   * @param string $id
   *   The resource ID.
   *
   * @return array|null
   *   The geographical data, or NULL if not found in cache.
   */
  public function getGeoData(string $id): ?array {
    $cache_key = "omeka_geo_data:item:{$id}";
    
    $this->logger->debug('Tentativo di recupero dati geografici dalla cache per risorsa Omeka ID: @id', [
      '@id' => $id,
    ]);
    
    $cache = $this->cache->get($cache_key);
    
    if ($cache) {
      $this->logger->debug('CACHE HIT per dati geografici Omeka ID: @id', [
        '@id' => $id,
      ]);
      return $cache->data;
    }
    
    $this->logger->warning('CACHE MISS per dati geografici Omeka ID: @id', [
      '@id' => $id,
    ]);
    
    return NULL;
  }

  /**
   * Get geographical data for multiple Omeka resources from cache.
   *
   * @param array $ids
   *   Array of resource IDs.
   *
   * @return array
   *   Array of geographical data, keyed by ID.
   */
  public function getMultipleGeoData(array $ids): array {
    $cache_keys = [];
    $result = [];
    
    foreach ($ids as $id) {
      $cache_keys[$id] = "omeka_geo_data:item:{$id}";
    }
    
    $cached = $this->cache->getMultiple($cache_keys);
    
    foreach ($ids as $id) {
      $key = "omeka_geo_data:item:{$id}";
      if (isset($cached[$key])) {
        $result[$id] = $cached[$key]->data;
      }
      else {
        $this->logger->notice('Cache miss per dati geografici Omeka ID: @id', [
          '@id' => $id,
        ]);
      }
    }
    
    return $result;
  }

  /**
   * Estrae e prepara i dati geografici da un oggetto Omeka.
   *
   * @param array $resource
   *   L'oggetto Omeka con tutti i dati.
   *
   * @return array|null
   *   I dati geografici estratti o NULL se non disponibili.
   */
  public function extractGeoDataFromResource(array $resource): ?array {
    if (empty($resource)) {
      return NULL;
    }
    
    // Inizializza l'array per i dati geografici
    $geo_data = [
      'id' => $resource['o:id'] ?? NULL,
      'title' => $resource['o:title'] ?? NULL,
      'description' => NULL,
      'coordinates' => NULL,
      'address' => NULL,
      'has_geo_data' => FALSE,
      'type' => NULL,
    ];
    
    // Estrai la descrizione se presente
    if (!empty($resource['dcterms:description'][0]['@value'])) {
      $geo_data['description'] = $resource['dcterms:description'][0]['@value'];
    }
    
    // Estrai le coordinate dal modulo mapping
    if (!empty($resource['o-module-mapping:feature'])) {
      foreach ($resource['o-module-mapping:feature'] as $feature) {
        if (!empty($feature['o-module-mapping:geography-coordinates'])) {
          $coords = $feature['o-module-mapping:geography-coordinates'];
          if (is_array($coords) && count($coords) >= 2) {
            // Nota: in questo formato le coordinate sono [long, lat]
            $geo_data['coordinates'] = [
              'lng' => (float) $coords[0],
              'lat' => (float) $coords[1],
            ];
            $geo_data['has_geo_data'] = TRUE;
            
            // Memorizza anche il tipo di geometria (Point, LineString, Polygon, ecc.)
            $geo_data['type'] = $feature['o-module-mapping:geography-type'] ?? 'Point';
            
            // Memorizza l'etichetta della feature se presente
            if (!empty($feature['o:label'])) {
              $geo_data['label'] = $feature['o:label'];
            }
          }
        }
      }
    }
    
    // Se non abbiamo trovato coordinate nella feature mapping, proviamo nei campi geo:lat e geo:long
    if (!$geo_data['has_geo_data']) {
      if (!empty($resource['geo:lat'][0]['@value']) && !empty($resource['geo:long'][0]['@value'])) {
        $geo_data['coordinates'] = [
          'lat' => (float) $resource['geo:lat'][0]['@value'],
          'lng' => (float) $resource['geo:long'][0]['@value'],
        ];
        $geo_data['has_geo_data'] = TRUE;
        $geo_data['type'] = 'Point';
      }
    }
    
    // Estrai l'indirizzo/località se presente
    if (!empty($resource['oc:location'][0]['@value'])) {
      $geo_data['address'] = $resource['oc:location'][0]['@value'];
    }
    
    // Aggiungi immagine principale se presente
    if (!empty($resource['thumbnail_display_urls']['medium'])) {
      $geo_data['thumbnail_url'] = $resource['thumbnail_display_urls']['medium'];
    } elseif (!empty($resource['schema.org:contentUrl'][0]['@value'])) {
      $geo_data['thumbnail_url'] = $resource['schema.org:contentUrl'][0]['@value'];
    }
    
    // Aggiungi altri metadati rilevanti per la mappa
    if (!empty($resource['dcterms:created'][0]['@value'])) {
      $geo_data['date'] = $resource['dcterms:created'][0]['@value'];
    }
    
    if (!empty($resource['dcterms:type'][0]['@value'])) {
      $geo_data['type_desc'] = $resource['dcterms:type'][0]['@value'];
    }
    
    return $geo_data;
  }

  /**
   * Aggiorna la cache dei dati geografici per tutti gli oggetti Omeka.
   *
   * @param int $batch_size
   *   Numero di elementi da processare per ogni batch.
   * @param array $context
   *   Il contesto del batch.
   *
   * @return bool
   *   TRUE se l'operazione ha avuto successo, FALSE altrimenti.
   */
  public function updateGeoCache(int $batch_size = 50, array &$context = []): bool {
    // Inizializza il contesto del batch se non esiste
    if (!isset($context['sandbox']) || !isset($context['sandbox']['current_page'])) {
      $this->initializeBatchContext($context);
    }
    
    // Verifica che l'API Omeka sia configurata
    if (!$this->omekaCacheService->isConfigured()) {
      $context['results'] = [
        'processed' => 0,
        'errors' => 1,
        'configuration_error' => TRUE,
        'error_message' => $this->t('Omeka API is not configured. Please configure the API settings first.'),
      ];
      return FALSE;
    }
    
    // Log informativo per debug
    $this->logger->notice('OmekaGeoCache batch: avvio aggiornamento cache dati geografici dalla API mapping_features');
    
    try {
      // Se siamo in modalità elaborazione per pagina
      if (isset($context['sandbox']['process_mode']) && $context['sandbox']['process_mode'] === 'by_page') {
        $current_page = $context['sandbox']['current_page'];
        
        // Chiamata all'API per ottenere le feature di mappatura
        $this->logger->notice('OmekaGeoCache batch: recupero feature geografiche dalla pagina @page', [
          '@page' => $current_page,
        ]);
        
        // Utilizziamo il nuovo metodo per chiamate dirette all'API
        $api_result = $this->fetchMappingFeatures($current_page, $batch_size);
        $mapping_features = $api_result['data'];
        $total_results = $api_result['total_results'];
        
        // Se abbiamo ricevuto un conteggio totale valido, aggiorniamo la stima
        if ($total_results > 0) {
          $context['sandbox']['estimated_total'] = $total_results;
          $this->state->set(self::STATE_TOTAL_GEO_ITEMS, $total_results);
          $this->logger->notice('OmekaGeoCache batch: aggiornato conteggio totale a @count feature', [
            '@count' => $total_results,
          ]);
        }
        
        if (!empty($mapping_features) && is_array($mapping_features)) {
          $this->logger->notice('OmekaGeoCache batch: trovate @count feature geografiche nella pagina @page (totale: @total)', [
            '@count' => count($mapping_features),
            '@page' => $current_page,
            '@total' => $total_results,
          ]);
          
          $processed = 0;
          $errors = 0;
          
          foreach ($mapping_features as $feature) {
            if (empty($feature['o:id'])) {
              $this->logger->warning('OmekaGeoCache batch: feature senza ID, ignorata');
              continue;
            }
            
            $feature_id = $feature['o:id'];
            
            // Verifica se la feature ha coordinate
            if (empty($feature['o-module-mapping:geography-coordinates'])) {
              $this->logger->info('OmekaGeoCache batch: feature @id senza coordinate, ignorata', [
                '@id' => $feature_id,
              ]);
              continue;
            }
            
            // Prepara i dati geografici
            $item_id = NULL;
            if (!empty($feature['o:item']['o:id'])) {
              $item_id = $feature['o:item']['o:id'];
            }
            
            $coords = $feature['o-module-mapping:geography-coordinates'];
            $geo_type = $feature['o-module-mapping:geography-type'] ?? 'Point';
            $label = $feature['o:label'] ?? '';
            
            // Struttura base dei dati geografici
            $geo_data = [
              'id' => $item_id,
              'feature_id' => $feature_id,
              'title' => $label,
              'type' => $geo_type,
              'has_geo_data' => TRUE,
              'coordinates' => [
                'lng' => (float) $coords[0],
                'lat' => (float) $coords[1],
              ],
            ];
            
            // Aggiungi dati aggiuntivi dall'item associato se disponibile
            if ($item_id) {
              $item_data = $this->omekaCacheService->getResource($item_id, 'items');
              if ($item_data) {
                // Integra i dati dell'item
                $geo_data['title'] = $item_data['o:title'] ?? $label;
                
                if (!empty($item_data['dcterms:description'][0]['@value'])) {
                  $geo_data['description'] = $item_data['dcterms:description'][0]['@value'];
                }
                
                if (!empty($item_data['thumbnail_display_urls']['medium'])) {
                  $geo_data['thumbnail_url'] = $item_data['thumbnail_display_urls']['medium'];
                }
                
                if (!empty($item_data['dcterms:type'][0]['@value'])) {
                  $geo_data['type_desc'] = $item_data['dcterms:type'][0]['@value'];
                }
              }
            }
            
            // Salva i dati geografici in cache
            $cache_key = "omeka_geo_data:feature:{$feature_id}";
            if ($item_id) {
              // Aggiunge anche una chiave per item ID per facilitare il recupero
              $cache_key_item = "omeka_geo_data:item:{$item_id}";
              
              // Salva con entrambe le chiavi
              $cache_tags = [
                self::CACHE_TAG_ALL,
                "dog_omeka_geo_data:feature",
                "dog_omeka_geo_data:feature:{$feature_id}",
                "dog_omeka_geo_data:item:{$item_id}",
              ];
              
              // Salva in cache con entrambe le chiavi
              $this->cache->set(
                $cache_key,
                $geo_data,
                time() + self::CACHE_LIFETIME,
                $cache_tags
              );
              
              $this->cache->set(
                $cache_key_item,
                $geo_data,
                time() + self::CACHE_LIFETIME,
                $cache_tags
              );
            } else {
              // Solo feature senza item associato
              $cache_tags = [
                self::CACHE_TAG_ALL,
                "dog_omeka_geo_data:feature",
                "dog_omeka_geo_data:feature:{$feature_id}",
              ];
              
              $this->cache->set(
                $cache_key,
                $geo_data,
                time() + self::CACHE_LIFETIME,
                $cache_tags
              );
            }
            
            // Verifica che la cache sia stata aggiornata
            $verify_cache = $this->cache->get($cache_key);
            if ($verify_cache) {
              $processed++;
            } else {
              $this->logger->error('OmekaGeoCache batch: errore nel salvataggio della feature @id', [
                '@id' => $feature_id,
              ]);
              $errors++;
            }
          }
          
          // Aggiorna i contatori nel contesto
          $context['results']['processed'] = ($context['results']['processed'] ?? 0) + $processed;
          $context['results']['errors'] = ($context['results']['errors'] ?? 0) + $errors;
          
          // Se questa pagina ha meno feature del batch_size, probabilmente è l'ultima
          $last_page = count($mapping_features) < $batch_size;
          
          if ($last_page) {
            // Fine elaborazione
            $this->state->set(self::STATE_LAST_GEO_UPDATE, time());
            $this->state->set(self::STATE_CACHED_GEO_ITEMS, $context['results']['processed']);
            $this->state->set(self::STATE_ERROR_GEO_ITEMS, $context['results']['errors']);
            $context['finished'] = 1;
            
            $this->logger->notice('OmekaGeoCache batch completato: @processed feature elaborate, @errors errori', [
              '@processed' => $context['results']['processed'],
              '@errors' => $context['results']['errors'],
            ]);
          } else {
            // Passa alla pagina successiva
            $context['sandbox']['current_page']++;
            
            // Calcola una stima del progresso (approssimativo)
            if (isset($context['sandbox']['estimated_total']) && $context['sandbox']['estimated_total'] > 0) {
              $processed_so_far = ($context['sandbox']['current_page'] - 1) * $batch_size + count($mapping_features);
              $progress = min(0.95, $processed_so_far / $context['sandbox']['estimated_total']);
            } else {
              // Non conosciamo il totale, stimiamo in base a quante pagine abbiamo già processato
              $progress = min(0.9, 1 / ($context['sandbox']['current_page'] + 1));
            }
            
            $context['finished'] = $progress;
            
            $this->logger->notice('OmekaGeoCache batch: passaggio alla pagina @page, progresso stimato @progress%', [
              '@page' => $context['sandbox']['current_page'],
              '@progress' => round($progress * 100),
            ]);
          }
        } else {
          // Nessun risultato, fine elaborazione
          $this->state->set(self::STATE_LAST_GEO_UPDATE, time());
          $context['finished'] = 1;
          
          $this->logger->notice('OmekaGeoCache batch completato: nessuna feature trovata nella pagina @page', [
            '@page' => $current_page,
          ]);
        }
      }
      
      return TRUE;
    } catch (\Exception $e) {
      $this->logger->error('OmekaGeoCache batch: errore durante elaborazione: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      // Incrementa contatore errori
      $context['results']['errors'] = ($context['results']['errors'] ?? 0) + 1;
      
      // Passa comunque alla pagina successiva per non bloccare il batch
      if (isset($context['sandbox']['current_page'])) {
        $context['sandbox']['current_page']++;
      }
      
      // Calcola il progresso anche in caso di errore
      $context['finished'] = isset($context['sandbox']['current_page']) ? 
        min(0.9, $context['sandbox']['current_page'] / ($context['sandbox']['current_page'] + 5)) : 
        0.5;
      
      return FALSE;
    }
  }
  
  /**
   * Inizializza il contesto del batch.
   *
   * @param array $context
   *   Il contesto del batch.
   */
  public function initializeBatchContext(array &$context): void {
    if (!isset($context['sandbox'])) {
      $context['sandbox'] = [];
    }
    
    // Imposta la modalità di elaborazione per pagine
    $context['sandbox']['process_mode'] = 'by_page';
    $context['sandbox']['current_page'] = 1;
    $context['sandbox']['progress'] = 0;
    
    // Prova a stimare il numero totale di mapping features
    try {
      // Facciamo una chiamata API con per_page=1 per ottenere i metadati di paginazione
      $this->logger->info('Inizializzazione batch: tentativo di ottenere conteggio totale mapping features...');
      
      $api_result = $this->fetchMappingFeatures(1, 1);
      $test_features = $api_result['data'];
      $total_results = $api_result['total_results'];
      
      $this->logger->info('Risultato chiamata API: features trovate=@features_count, total_results=@total', [
        '@features_count' => is_array($test_features) ? count($test_features) : 0,
        '@total' => $total_results,
      ]);
      
      // Prima priorità: usa il valore total_results se disponibile e valido
      if ($total_results > 0) {
        $estimated_total = $total_results;
        $this->logger->info('Usando conteggio da header API: @count', ['@count' => $estimated_total]);
      }
      // Seconda priorità: se abbiamo almeno una feature, prova a fare una stima
      else if (!empty($test_features) && is_array($test_features)) {
        // Prova con per_page più alto per fare una stima migliore
        $api_result_sample = $this->fetchMappingFeatures(1, 100);
        $sample_features = $api_result_sample['data'];
        $sample_count = is_array($sample_features) ? count($sample_features) : 0;
        
        if ($sample_count >= 100) {
          // Ci sono probabilmente molte più feature, stimiamo almeno 200
          $estimated_total = 200;
          $this->logger->info('Stima basata su sample: almeno @count elementi (trovati @sample in una pagina)', [
            '@count' => $estimated_total,
            '@sample' => $sample_count,
          ]);
        } else {
          // Usiamo il numero effettivo trovato
          $estimated_total = $sample_count;
          $this->logger->info('Usando conteggio effettivo dal sample: @count', ['@count' => $estimated_total]);
        }
      }
      // Ultima risorsa: nessun dato disponibile
      else {
        $estimated_total = 0;
        $this->logger->warning('Nessuna mapping feature trovata nell\'API, impostando conteggio a 0');
      }
      
      $context['sandbox']['estimated_total'] = $estimated_total;
      $this->state->set(self::STATE_TOTAL_GEO_ITEMS, $estimated_total);
      
      $this->logger->info('OmekaGeoCache batch: inizializzato in modalità paginazione, stima di @count elementi', [
        '@count' => $estimated_total,
      ]);
      
      // Inizializza contatori dei risultati
      $context['results'] = [
        'processed' => 0,
        'errors' => 0,
        'total_items' => $estimated_total,
      ];
    } catch (\Exception $e) {
      $this->logger->error('OmekaGeoCache batch: errore durante inizializzazione: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      // Impostiamo un valore predefinito in caso di errore
      $context['sandbox']['estimated_total'] = 100;
      $this->state->set(self::STATE_TOTAL_GEO_ITEMS, 100);
    }
  }
  
  /**
   * Ottiene le statistiche della cache dei dati geografici.
   *
   * @return array
   *   Array con le statistiche della cache.
   */
  public function getGeoDataCacheStatistics(): array {
    // Conta gli elementi nella cache
    $database = \Drupal::database();
    $query = $database->select('cache_omeka_geo_data', 'c')
      ->countQuery();
    $actual_count = $query->execute()->fetchField();
    
    return [
      'last_update' => $this->state->get(self::STATE_LAST_GEO_UPDATE, 0),
      'total_items' => $this->state->get(self::STATE_TOTAL_GEO_ITEMS, 0),
      'cached_items' => $actual_count,
      'error_items' => $this->state->get(self::STATE_ERROR_GEO_ITEMS, 0),
    ];
  }

  /**
   * Fa una chiamata diretta all'API per ottenere le mapping features.
   *
   * @param int $page
   *   Il numero di pagina.
   * @param int $per_page
   *   Il numero di elementi per pagina.
   *
   * @return array
   *   Array con i dati delle features e il conteggio totale.
   */
  protected function fetchMappingFeatures(int $page = 1, int $per_page = 50): array {
    try {
      $base_url = $this->config->get('base_url');
      if (!$base_url) {
        throw new \Exception('URL API Omeka non configurato in dog.settings:base_url');
      }
      
      $this->logger->info('Configurazione Omeka API URL: @url', [
        '@url' => $base_url,
      ]);
      
      // Costruisce l'URL per le mapping features
      $url = rtrim($base_url, '/') . '/api/mapping_features';
      $params = [
        'page' => $page,
        'per_page' => $per_page,
      ];
      
      $query_string = http_build_query($params);
      $full_url = $url . '?' . $query_string;
      
      $this->logger->info('Chiamata diretta all\'API mapping features: @url', [
        '@url' => $full_url,
      ]);
      
      // Fa la richiesta HTTP
      $client = \Drupal::httpClient();
      $response = $client->request('GET', $full_url, [
        'headers' => [
          'Accept' => 'application/json',
        ],
        'timeout' => 30,
      ]);
      
      if ($response->getStatusCode() !== 200) {
        throw new \Exception('Errore HTTP: ' . $response->getStatusCode() . ' - ' . $response->getReasonPhrase());
      }
      
      $response_body = $response->getBody()->getContents();
      $this->logger->info('Response HTTP body sample: @body', [
        '@body' => substr($response_body, 0, 500),
      ]);
      
      $data = json_decode($response_body, TRUE);
      
      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception('Errore parsing JSON: ' . json_last_error_msg());
      }
      
      if (!is_array($data)) {
        throw new \Exception('Risposta API non valida: non è un array');
      }
      
      if (empty($data)) {
        $this->logger->info('API mapping features ha restituito un array vuoto');
      }
      
      // Estrae il conteggio totale dalle header se disponibile
      $total_results = 0;
      $total_header = $response->getHeader('Omeka-S-Total-Results');
      if (!empty($total_header)) {
        $total_results = (int) $total_header[0];
      }
      
      $this->logger->info('API mapping features: ricevuti @count elementi (totale: @total)', [
        '@count' => count($data),
        '@total' => $total_results,
      ]);
      
      return [
        'data' => $data,
        'total_results' => $total_results,
      ];
      
    } catch (\Exception $e) {
      $this->logger->error('Errore nella chiamata API mapping features: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [
        'data' => [],
        'total_results' => 0,
      ];
    }
  }

  /**
   * Ottiene il conteggio effettivo delle mapping features dall'API.
   * 
   * @return int
   *   Il numero totale di mapping features disponibili.
   */
  public function getRealMappingFeaturesCount(): int {
    try {
      $this->logger->info('Tentativo di ottenere conteggio reale mapping features...');
      
      // Prima prova: chiamata con per_page=1 per ottenere l'header
      $api_result = $this->fetchMappingFeatures(1, 1);
      $total_from_header = $api_result['total_results'];
      
      if ($total_from_header > 0) {
        $this->logger->info('Conteggio da header API: @count', ['@count' => $total_from_header]);
        return $total_from_header;
      }
      
      // Seconda prova: chiamata iterativa per contare
      $this->logger->info('Header non disponibile, conteggio iterativo...');
      $page = 1;
      $per_page = 100;
      $total_count = 0;
      
      do {
        $api_result = $this->fetchMappingFeatures($page, $per_page);
        $features = $api_result['data'];
        $current_count = is_array($features) ? count($features) : 0;
        
        $total_count += $current_count;
        $page++;
        
        $this->logger->info('Pagina @page: trovate @count features (totale: @total)', [
          '@page' => $page - 1,
          '@count' => $current_count,
          '@total' => $total_count,
        ]);
        
        // Evita loop infiniti
        if ($page > 50) {
          $this->logger->warning('Interrotto conteggio dopo 50 pagine per evitare loop infiniti');
          break;
        }
        
      } while ($current_count === $per_page);
      
      $this->logger->info('Conteggio finale iterativo: @count', ['@count' => $total_count]);
      return $total_count;
      
    } catch (\Exception $e) {
      $this->logger->error('Errore nel conteggio mapping features: @message', [
        '@message' => $e->getMessage(),
      ]);
      return 0;
    }
  }

  /**
   * Testa la connettività e la risposta delle API Omeka.
   *
   * @return array
   *   Array con i risultati dei test.
   */
  public function testApiConnectivity(): array {
    $results = [
      'items_api' => [
        'status' => 'unknown',
        'message' => '',
        'response_sample' => '',
        'count' => 0,
      ],
      'mapping_features_api' => [
        'status' => 'unknown',
        'message' => '',
        'response_sample' => '',
        'count' => 0,
      ],
    ];
    
    // Test API Items
    try {
      $this->logger->info('TEST API: Tentativo di test dell\'API items...');
      
      // Usa il ResourceFetcher per testare gli items
      $total_results = 0;
      $test_items = $this->resourceFetcher->search('items', [], 1, 1, $total_results);
      
      if (!empty($test_items)) {
        $results['items_api']['status'] = 'success';
        $results['items_api']['message'] = 'API items funzionante';
        $results['items_api']['response_sample'] = json_encode(array_slice($test_items, 0, 1), JSON_PRETTY_PRINT);
        $results['items_api']['count'] = $total_results;
      } else {
        $results['items_api']['status'] = 'warning';
        $results['items_api']['message'] = 'API items restituisce array vuoto';
      }
      
    } catch (\Exception $e) {
      $results['items_api']['status'] = 'error';
      $results['items_api']['message'] = 'Errore API items: ' . $e->getMessage();
    }
    
    // Test API Mapping Features
    try {
      $this->logger->info('TEST API: Tentativo di test dell\'API mapping features...');
      
      $api_result = $this->fetchMappingFeatures(1, 1);
      $test_features = $api_result['data'];
      $total_results = $api_result['total_results'];
      
      if (!empty($test_features)) {
        $results['mapping_features_api']['status'] = 'success';
        $results['mapping_features_api']['message'] = 'API mapping features funzionante';
        $results['mapping_features_api']['response_sample'] = json_encode(array_slice($test_features, 0, 1), JSON_PRETTY_PRINT);
        $results['mapping_features_api']['count'] = $total_results;
      } else {
        $results['mapping_features_api']['status'] = 'warning';
        $results['mapping_features_api']['message'] = 'API mapping features restituisce array vuoto';
      }
      
    } catch (\Exception $e) {
      $results['mapping_features_api']['status'] = 'error';
      $results['mapping_features_api']['message'] = 'Errore API mapping features: ' . $e->getMessage();
    }
    
    return $results;
  }
}
