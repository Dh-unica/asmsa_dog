<?php

namespace Drupal\omeka_utils;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dog\Service\OmekaCacheService;
use Drupal\dog\Service\OmekaGeoDataCacheService;

#[\AllowDynamicProperties]
class Utils {

  /**
   * URL base di Omeka.
   *
   * @var string
   */
  protected $base_url;

  /**
   * URL delle API Omeka.
   *
   * @var string
   */
  protected $url;

  /**
   * Il factory della configurazione.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Il logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Il logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Il servizio di cache per le risorse Omeka.
   *
   * @var \Drupal\dog\Service\OmekaCacheService
   */
  protected $omekaCacheService;

  /**
   * Il servizio di cache per i dati geografici Omeka.
   *
   * @var \Drupal\dog\Service\OmekaGeoDataCacheService
   */
  protected $omekaGeoDataCacheService;

  /**
   * Costruttore della classe Utils.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Il factory della configurazione.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Il logger channel factory.
   * @param \Drupal\dog\Service\OmekaCacheService $omeka_cache_service
   *   Il servizio di cache per le risorse Omeka.
   * @param \Drupal\dog\Service\OmekaGeoDataCacheService $omeka_geo_data_cache_service
   *   Il servizio di cache per i dati geografici Omeka.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    OmekaCacheService $omeka_cache_service = NULL,
    OmekaGeoDataCacheService $omeka_geo_data_cache_service = NULL
  ) {
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->logger = $logger_factory->get('omeka_utils');
    $this->omekaCacheService = $omeka_cache_service;
    $this->omekaGeoDataCacheService = $omeka_geo_data_cache_service;
    
    $config = $config_factory->get('dog.settings');
    $this->base_url = $config->get('base_url');
    
    // Correzione del formato URL: assicurarsi che ci sia sempre /api/
    // Rimuovi eventuali slash finali e aggiungi sempre /api/items/
    $this->url = rtrim($this->base_url, '/') . '/api/items/';
    
    // Log dell'URL configurato per il debugging
    $this->logger->notice('Omeka Utils: URL configurato @url', [
      '@url' => $this->url,
    ]);
  }
  
  /**
   * Imposta il logger factory.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Il logger channel factory.
   */
  public function setLoggerFactory(LoggerChannelFactoryInterface $logger_factory) {
    $this->loggerFactory = $logger_factory;
    $this->logger = $logger_factory->get('omeka_utils');
  }

  function getSocialMetaTags($item_id) {
    // Get item
    $item = $this->getItem($item_id);
    $base_url = \Drupal::request()->getSchemeAndHttpHost();
    $title = $this->getTitle($item);
    $metadata = [];
    $metadata['title'] = $title;
    $metadata['abstract'] = $this->getAbstract($item);
    $metadata['img'] = $this->getImage($item);
    $metadata['url'] = $base_url . '/omeka/' . $item_id;

    // Set type to "website"
    $metadata['type'] = 'website';

    // Metadati specifici per l'applicazione
    $metadata['app_name'] = 'Omeka Collection';
    $metadata['app_id'] = '123456789012345';

    return $metadata;
  }

  /**
   * Recupera un elemento Omeka utilizzando la cache permanente.
   *
   * @param string $item_id
   *   L'ID dell'elemento Omeka da recuperare.
   *
   * @return object|array|false
   *   L'elemento Omeka recuperato, o FALSE in caso di errore.
   */
  function getItem($item_id) {
    $this->logger->debug('Tentativo di recupero elemento Omeka ID: @id', [
      '@id' => $item_id,
    ]);
    
    // 1. Prima verifica nella cache DOG (cache_omeka_resources)
    if ($this->omekaCacheService) {
      $this->logger->debug('Verifico nella cache DOG per ID: @id', [
        '@id' => $item_id,
      ]);
      
      // Utilizziamo il servizio di cache DOG
      $resource_type = 'items'; // Tipo predefinito per gli elementi Omeka
      $cached_item = $this->omekaCacheService->getResource($item_id, $resource_type);
      
      if ($cached_item) {
        $this->logger->debug('Elemento trovato nella cache DOG: @id', [
          '@id' => $item_id,
        ]);
        return (object) $cached_item; // Converte l'array in oggetto per compatibilità
      }
      
      // Se non è in cache, ma abbiamo il servizio, proviamo a caricarlo tramite OmekaCacheService
      $this->logger->debug('Elemento non in cache, tento il recupero tramite OmekaCacheService per ID: @id', [
        '@id' => $item_id,
      ]);
      
      try {
        // Il fetchResource fa sia il recupero che il salvataggio in cache
        $fetched_item = $this->omekaCacheService->fetchResource($resource_type, $item_id, TRUE);
        
        if ($fetched_item) {
          $this->logger->debug('Elemento recuperato e salvato in cache tramite OmekaCacheService: @id', [
            '@id' => $item_id,
          ]);
          return (object) $fetched_item;
        }
      }
      catch (\Exception $e) {
        $this->logger->warning('Errore nel tentativo di recupero tramite OmekaCacheService: @error', [
          '@error' => $e->getMessage(),
        ]);
        // Prosegue con il metodo tradizionale
      }
    }
    
    // 2. Fallback al metodo tradizionale (solo se tutto il resto fallisce)
    $this->logger->warning('Fallback al metodo tradizionale per ID: @id', [
      '@id' => $item_id,
    ]);
    
    try {
      // Utilizziamo Guzzle invece di file_get_contents per una migliore gestione degli errori
      $client = \Drupal::httpClient();
      $response = $client->get($this->url . $item_id, [
        'timeout' => 5,
        'connect_timeout' => 5,
      ]);
      
      $content = (string) $response->getBody();
      $item = json_decode($content);
      
      // Se abbiamo recuperato l'elemento, salviamolo in cache per richieste future
      if ($item && $this->omekaCacheService) {
        try {
          $this->omekaCacheService->setResource('items', $item_id, (array) $item);
          $this->logger->debug('Elemento salvato in cache dopo recupero tramite metodo tradizionale: @id', [
            '@id' => $item_id,
          ]);
        }
        catch (\Exception $e) {
          $this->logger->warning('Impossibile salvare in cache dopo recupero: @error', [
            '@error' => $e->getMessage(),
          ]);
        }
      }
      
      return $item;
    }
    catch (\Exception $e) {
      $this->logger->error('Errore nel recupero elemento: @error', [
        '@error' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Restituisce il titolo dell'elemento.
   *
   * @param mixed $item
   *   L'elemento Omeka.
   *
   * @return string
   *   Il titolo dell'elemento.
   */
  function getTitle($item) {
    if (is_object($item) && isset($item->{'o:title'})) {
      return $item->{'o:title'};
    }
    elseif (is_array($item) && isset($item['o:title'])) {
      return $item['o:title'];
    }
    return '';
  }

  function getAbstract($item) {
    $abstract = '';
    if (isset($item->{'dcterms:description'})) {
      if (is_array($item->{'dcterms:description'}) && isset($item->{'dcterms:description'}[0]->{'@value'})) {
        $abstract = $item->{'dcterms:description'}[0]->{'@value'};
      }
    }
    return $abstract;
  }

  function getImage($item) {
    $image = '';
    if (isset($item->{'o:media'}) && is_array($item->{'o:media'}) && count($item->{'o:media'}) > 0) {
      $url = $item->{'o:media'}[0]->{'@id'};
      $response = file_get_contents($url);
      $media = json_decode($response);
      
      // $image = $media->{'o:source'};
      $image = $item->{'thumbnail_display_urls'}->{'medium'};
    }
    return $image;
  }

  /**
   * Ottiene la posizione geografica di un elemento Omeka.
   *
   * @param mixed $omeka_item_full
   *   L'elemento Omeka completo (può essere un oggetto o un array).
   *
   * @return array
   *   Associative array con 'latitude' e 'longitude' dell'elemento.
   */
  public function getLocation($omeka_item_full) {
    // Ottieni ID per la chiave cache
    $id = $this->getItemId($omeka_item_full);
    
    $this->logger->debug('getLocation - Tentativo di recupero posizione per item @id', [
      '@id' => $id,
    ]);
    
    // 1. Prima verifica nella cache permanente tramite il servizio OmekaGeoDataCacheService
    if ($this->omekaGeoDataCacheService) {
      $this->logger->debug('getLocation - Verifico nella cache geo permanente per ID: @id', [
        '@id' => $id,
      ]);
      
      $geo_data = $this->omekaGeoDataCacheService->getGeoData($id);
      
      if ($geo_data && isset($geo_data['coordinates'])) {
        $this->logger->debug('getLocation - Posizione trovata nella cache geo permanente: @id', [
          '@id' => $id,
        ]);
        
        return [
          'latitude' => $geo_data['coordinates'][1],
          'longitude' => $geo_data['coordinates'][0],
        ];
      }
    }
    
    // 2. Fallback alla cache standard
    $cache_key = 'omeka_location_' . $id;
    
    // Controlla se abbiamo la posizione in cache standard
    if ($cache = \Drupal::cache('omeka')->get($cache_key)) {
      $this->logger->debug('getLocation - Posizione recuperata da cache standard per item @id', [
        '@id' => $id,
      ]);
      return $cache->data;
    }
    
    // 3. Prova a ottenere le coordinate direttamente dall'oggetto
    $coordinates = $this->getCoordinatesDirectly($omeka_item_full);
    
    if (!empty($coordinates)) {
      $location = [
        'latitude' => $coordinates[1],
        'longitude' => $coordinates[0],
      ];
      
      // Salva in entrambe le cache
      $this->saveGeoDataToCache($id, $coordinates, $location);
      return $location;
    }
    
    // 4. Se non abbiamo trovato coordinate direttamente, proviamo con la URL della feature
    $location_url = '';
    $feature = null;
    
    // Estrai la URL della feature
    if (is_object($omeka_item_full) && isset($omeka_item_full->{'o-module-mapping:feature'})) {
      $feature = $omeka_item_full->{'o-module-mapping:feature'};
    } elseif (is_array($omeka_item_full) && isset($omeka_item_full['o-module-mapping:feature'])) {
      $feature = $omeka_item_full['o-module-mapping:feature'];
    }
    
    // Estrai l'URL dalla feature
    if (is_array($feature) && !empty($feature)) {
      if (is_object($feature[0]) && isset($feature[0]->{'@id'})) {
        $location_url = $feature[0]->{'@id'};
      } elseif (is_array($feature[0]) && isset($feature[0]['@id'])) {
        $location_url = $feature[0]['@id'];
      }
    }
    
    // Se non abbiamo URL, restituisci coordinate di default
    if (empty($location_url)) {
      $location = [
        'latitude' => 41.9027835,  // Default: Roma (centro Italia)
        'longitude' => 12.4963655,
      ];
      
      // Salva in cache standard
      \Drupal::cache('omeka')->set($cache_key, $location, strtotime('now +1 week'));
      return $location;
    }
    
    // Recupera i dati dalla URL della feature
    try {
      // Utilizziamo Guzzle invece di file_get_contents per una migliore gestione degli errori
      $client = \Drupal::httpClient();
      $response = $client->get($location_url, [
        'timeout' => 2.0,
        'connect_timeout' => 2.0,
      ]);
      
      $content = (string) $response->getBody();
      $location_json = json_decode($content, TRUE);
      $coordinates = $location_json['o-module-mapping:geography-coordinates'] ?? NULL;
      
      if ($coordinates) {
        $location = [
          'latitude' => $coordinates[1],
          'longitude' => $coordinates[0],
        ];
        
        // Salva in entrambe le cache
        $this->saveGeoDataToCache($id, $coordinates, $location);
        return $location;
      }
    } 
    catch (\Exception $e) {
      $this->logger->warning('getLocation - Errore: @error', ['@error' => $e->getMessage()]);
    }
    
    // Coordinate di default in caso di fallimento totale
    $location = [
      'latitude' => 41.9027835,  // Default: Roma
      'longitude' => 12.4963655,
    ];
    
    // Salva in cache standard
    \Drupal::cache('omeka')->set($cache_key, $location, strtotime('now +1 week'));
    return $location;
  }
  
  /**
   * Salva i dati geografici nelle cache disponibili.
   *
   * @param string $id
   *   L'ID dell'elemento Omeka.
   * @param array $coordinates
   *   Le coordinate [longitudine, latitudine].
   * @param array $location
   *   Associative array con 'latitude' e 'longitude'.
   */
  private function saveGeoDataToCache($id, array $coordinates, array $location) {
    // 1. Salva nella cache standard
    $cache_key = 'omeka_location_' . $id;
    \Drupal::cache('omeka')->set($cache_key, $location, strtotime('now +1 week'));
    
    // 2. Salva nella cache permanente se disponibile
    if ($this->omekaGeoDataCacheService) {
      try {
        // Prepara i dati geografici in formato compatibile
        $geo_data = [
          'id' => $id,
          'coordinates' => $coordinates,
          'type' => 'Point',
          'has_geo_data' => TRUE,
          'title' => 'Posizione elemento ' . $id,
        ];
        
        // Utilizza direttamente il servizio cache fornito da Drupal Core
        $cache_key_permanent = "omeka_geo_data:item:{$id}";
        $cache_tags = ['dog_omeka_geo_data:all', "dog_omeka_geo_data:item:{$id}"];
        
        // Ottieni il backend della cache dai servizi Drupal
        $cache_backend = \Drupal::service('cache.omeka_geo_data');
        $cache_backend->set(
          $cache_key_permanent,
          $geo_data,
          strtotime('now +1 month'),
          $cache_tags
        );
        
        $this->logger->debug('Dati geografici salvati nella cache permanente per ID: @id', [
          '@id' => $id,
        ]);
      }
      catch (\Exception $e) {
        $this->logger->warning('Impossibile salvare i dati geografici nella cache permanente: @error', [
          '@error' => $e->getMessage(),
        ]);
      }
    }
  }
  
  /**
   * Tenta di estrarre le coordinate direttamente dall'elemento Omeka.
   *
   * @param mixed $omeka_item_full
   *   L'elemento Omeka (può essere un oggetto o un array).
   *
   * @return array|null
   *   Le coordinate [longitudine, latitudine] o null se non trovate.
   */
  private function getCoordinatesDirectly($omeka_item_full) {
    if (is_object($omeka_item_full) && isset($omeka_item_full->{'o-module-mapping:feature'})) {
      $feature = $omeka_item_full->{'o-module-mapping:feature'};
      if (is_array($feature) && !empty($feature) && isset($feature[0]->{'o-module-mapping:geography-coordinates'})) {
        return $feature[0]->{'o-module-mapping:geography-coordinates'};
      }
    } elseif (is_array($omeka_item_full) && isset($omeka_item_full['o-module-mapping:feature'])) {
      $feature = $omeka_item_full['o-module-mapping:feature'];
      if (is_array($feature) && !empty($feature) && isset($feature[0]['o-module-mapping:geography-coordinates'])) {
        return $feature[0]['o-module-mapping:geography-coordinates'];
      }
    }
    
    return null;
  }

  public function getResourceName($resource_id) {
    $resources = [
      '1' => 'Base Resource',
      '2' => 'Risorsa fotografica',
      '3' => 'Risorsa archeologica',
      '4' => 'Risorsa cartografica',
      '5' => 'Risorsa documentale',
      '6' => 'Risorsa opera d\'arte',
      '7' => 'Risorsa bibliografica',
      '8' => 'Risorsa audio',
    ];
    return $resources[$resource_id];
  }

  /**
   * Ottiene l'URL dell'elemento Omeka.
   *
   * @param mixed $item
   *   L'elemento Omeka (può essere un oggetto o un array).
   *
   * @return string
   *   L'URL completo dell'elemento.
   */
  public function getItemUrl($item) {
    $id = $this->getItemId($item);
    $base_url = rtrim($this->base_url, '/');
    
    // Estrai lo slug del sito dall'elemento
    // Per la collezione principale di storia, lo slug corretto è 'risorse-storia'
    $site_slug = $this->getSiteSlugFromItem($item);
    
    // Costruisci l'URL completo con il pattern: {base_url}/s/{site-slug}/item/{id}
    return $base_url . '/s/' . $site_slug . '/item/' . $id;
  }
  
  /**
   * Estrae lo slug del sito dall'elemento Omeka.
   * 
   * @param mixed $item
   *   L'elemento Omeka (può essere un oggetto o un array).
   * 
   * @return string
   *   Lo slug del sito Omeka.
   */
  protected function getSiteSlugFromItem($item) {
    // Estrai l'ID del sito, se disponibile
    $site_id = null;
    
    if (is_object($item) && isset($item->{'o:site'}) && is_array($item->{'o:site'}) && !empty($item->{'o:site'})) {
      $site_id = $item->{'o:site'}[0]->{'o:id'} ?? null;
    } elseif (is_array($item) && isset($item['o:site']) && is_array($item['o:site']) && !empty($item['o:site'])) {
      $site_id = $item['o:site'][0]['o:id'] ?? null;
    }
    
    // Mappa gli ID dei siti con gli slug corretti
    // In base all'esempio fornito, lo slug dovrebbe essere 'risorse-storia' per la collezione principale
    $site_slugs = [
      1 => 'risorse-storia',  // Collezione principale (risorse storia)
      2 => 'archivio',        // Archivio storico
      3 => 'patrimonio',      // Patrimonio culturale
      // Aggiungi altri siti come necessario
    ];
    
    // Restituisci lo slug in base all'ID del sito, o quello predefinito se l'ID non è disponibile
    return $site_slugs[$site_id] ?? 'risorse-storia';  // Fallback predefinito
  }

  /**
   * Ottiene l'URL del sito Omeka senza fare chiamate HTTP.
   *
   * @param mixed $item
   *   L'elemento Omeka (può essere un oggetto o un array).
   *
   * @return string
   *   L'URL base del sito Omeka.
   */
  public function getSiteUrl($item) {
    $id = $this->getItemId($item);
    
    // Verifica se l'URL è in cache
    $cacheId = 'omeka_site_' . $id;
    if ($cache = \Drupal::cache('omeka')->get($cacheId)) {
      return $cache->data;
    }
    
    // Per evitare chiamate HTTP, genera un URL base statico senza slug del sito
    // Questo è un grande miglioramento di performance rispetto a prima
    $site_url = rtrim($this->base_url, '/');
    
    // Ottieni l'ID del sito (se disponibile) per essere più specifici
    $site_slug = 'risorse';  // Valore di fallback sempre disponibile
    
    // Cerca di estrarre lo slug direttamente dall'elemento, se presente
    if (is_object($item) && isset($item->{'o:site'})) {
      if (is_array($item->{'o:site'}) && !empty($item->{'o:site'})) {
        // Se abbiamo ID numerici per i siti, possiamo mapparli a slug predefiniti
        // Nota: questo è più affidabile di fare chiamate HTTP per ogni elemento
        $site_id = $item->{'o:site'}[0]->{'o:id'} ?? null;
        if ($site_id) {
          $site_slug = $this->getSiteSlugById($site_id);  
        }
      }
    } elseif (is_array($item) && isset($item['o:site'])) {
      if (is_array($item['o:site']) && !empty($item['o:site'])) {
        $site_id = $item['o:site'][0]['o:id'] ?? null;
        if ($site_id) {
          $site_slug = $this->getSiteSlugById($site_id);
        }
      }
    }
    
    // Costruisci l'URL con lo slug (predefinito o estratto)
    $site_url = $site_url . '/s/' . $site_slug;
    
    // Salva in cache per riutilizzo
    $expire = strtotime('now +1 month');  // Estendi a un mese
    \Drupal::cache('omeka')->set($cacheId, $site_url, $expire);
    return $site_url;
  }
  
  /**
   * Mappa ID siti a nomi slug statici, evitando chiamate HTTP.
   * 
   * @param int $site_id
   *   ID del sito Omeka.
   * 
   * @return string
   *   Lo slug del sito.
   */
  protected function getSiteSlugById($site_id) {
    $siteMap = [
      1 => 'risorse',     // Sito principale
      2 => 'archivio',    // Archivio storico
      3 => 'patrimonio',  // Patrimonio culturale
      // Aggiungi altri siti come necessario
    ];
    
    return $siteMap[$site_id] ?? 'risorse';  // Fallback predefinito
  }
  
  /**
   * Ottiene l'ID di un elemento Omeka, supportando sia oggetti che array.
   *
   * @param mixed $item
   *   L'elemento Omeka (può essere un oggetto o un array).
   *
   * @return string
   *   L'ID dell'elemento Omeka.
   */
  public function getItemId($item) {
    if (is_object($item) && isset($item->{'o:id'})) {
      return $item->{'o:id'};
    } elseif (is_array($item) && isset($item['o:id'])) {
      return $item['o:id'];
    }
    
    // Fallback: se non troviamo un ID, generiamo un warning e restituiamo un valore fittizio
    $this->logger->warning('getItemId - Impossibile determinare ID elemento Omeka');
    return 'unknown';  
  }

  // JavaScript code removed
}
