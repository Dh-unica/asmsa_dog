<?php

namespace Drupal\dog\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\dog\OmekaApiResponse;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;

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
   * Constructs a OmekaResourceFetcher object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Http\ClientFactory $factory
   *   The client factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientFactory $factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->config = $config_factory->get('dog.settings');
    $this->factory = $factory;
    $this->logger = $logger_factory->get('dog');
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

      default:
        throw new \InvalidArgumentException(sprintf("Resource type not mapped: %s", $original_type));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function retrieveResource(string $id, string $resource_type): ?array {
    // Logging iniziale per tracciare la richiesta
    $this->logger->debug('DEBUG OmekaResourceFetcher: Tentativo di recupero risorsa. ID: @id, Type: @type', [
      '@id' => $id,
      '@type' => $resource_type,
    ]);
    
    // Prima verifica se esiste una implementazione del cache manager
    $cacheService = NULL;
    
    try {
      $cacheService = \Drupal::service('dog.omeka_cache');
      $this->logger->debug('DEBUG OmekaResourceFetcher: Servizio cache trovato, verifico in cache prima');
      
      // Controlla se la risorsa è disponibile nella cache
      $cachedData = $cacheService->getResource($id, $resource_type);
      
      // Se abbiamo trovato i dati in cache, li restituiamo immediatamente
      if (!empty($cachedData)) {
        $this->logger->debug('DEBUG OmekaResourceFetcher: Risorsa @id:@type trovata in cache!', [
          '@id' => $id,
          '@type' => $resource_type,
        ]);
        return $cachedData;
      }
      
      $this->logger->debug('DEBUG OmekaResourceFetcher: Risorsa NON trovata in cache, procedo con API');
    }
    catch (\Exception $e) {
      $this->logger->warning('DEBUG OmekaResourceFetcher: Errore nel tentativo di accesso alla cache: @error', [
        '@error' => $e->getMessage(),
      ]);
      // Continua con la chiamata API se c'è un problema con la cache
    }
    
    // Run request.
    // Aggiungiamo il prefisso 'api/' all'URL base
    $uri = sprintf("api/%s/%s", $resource_type, $id);
    $this->logger->debug('DEBUG OmekaResourceFetcher: Chiamata API a URI: @uri', ['@uri' => $uri]);
    
    $result = $this->request('GET', $uri);

    if ($result === FALSE) {
      $this->logger->warning('DEBUG OmekaResourceFetcher: Chiamata API fallita per ID: @id, Type: @type', [
        '@id' => $id,
        '@type' => $resource_type,
      ]);
      return NULL;
    }

    // Build the return data.
    $data = $result->getContent();
    $data['id'] = $id;
    $data['type'] = $resource_type;
    
    $this->logger->debug('DEBUG OmekaResourceFetcher: Risorsa recuperata da API con successo. ID: @id, Type: @type', [
      '@id' => $id,
      '@type' => $resource_type,
    ]);
    
    // Se abbiamo il servizio cache, salviamo la risorsa in cache
    if ($cacheService !== NULL) {
      try {
        // Utilizziamo il metodo fetchResource che gestisce il salvataggio in cache
        $cacheService->fetchResource($resource_type, $id, TRUE);
        $this->logger->debug('DEBUG OmekaResourceFetcher: Risorsa salvata in cache. ID: @id, Type: @type', [
          '@id' => $id,
          '@type' => $resource_type,
        ]);
      }
      catch (\Exception $e) {
        $this->logger->warning('DEBUG OmekaResourceFetcher: Impossibile salvare in cache: @error', [
          '@error' => $e->getMessage(),
        ]);
      }
    }

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
    
    // Assicurati che l'URL base NON termini con una barra (/)
    $base_url = rtrim($base_url, '/');
    
    // Log dell'URL base per debug
    $this->logger->notice('Omeka API base URL configurato: @url', [
      '@url' => $base_url,
    ]);

    // Se l'API Omeka richiede autenticazione, aggiungi i parametri
    $key_identity = $this->config->get('key_identity');
    $key_credential = $this->config->get('key_credential');
    
    // Crea lo stack handler di base
    $handler = HandlerStack::create();
    
    // Aggiungi i parametri di autenticazione solo se sono stati configurati
    if (!empty($key_identity) || !empty($key_credential)) {
      $auth_params = [];
      if (!empty($key_identity)) {
        $auth_params['key_identity'] = $key_identity;
      }
      if (!empty($key_credential)) {
        $auth_params['key_credential'] = $key_credential;
      }
      
      // Aggiungi i parametri solo se c'è effettivamente qualcosa da aggiungere
      if (!empty($auth_params)) {
        $handler->push(Middleware::mapRequest(function (RequestInterface $request) use ($auth_params) {
          return $request->withUri(Uri::withQueryValues($request->getUri(), $auth_params));
        }));
      }
    }

    // Verifica se l'URL base contiene già 'http' o 'https'
    if (!preg_match('~^https?://~i', $base_url)) {
      $base_url = 'https://' . $base_url;
    }
    
    // Create http client con l'URL base specifico configurato
    $this->httpClient = $this->factory->fromOptions([
      'base_uri' => $base_url . '/',  // Aggiungo slash finale per sicurezza
      'handler' => $handler,
      'http_errors' => TRUE,  // Attiva gli errori HTTP per gestire meglio i problemi
      'debug' => FALSE,       // In produzione mantieni debug disattivato
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

    // Log dettagliato dei parametri di ricerca
    $this->logger->notice('Omeka API search: @resource_type con parametri page=@page, per_page=@items_per_page', [
      '@resource_type' => $resource_type,
      '@page' => $page,
      '@items_per_page' => $items_per_page,
    ]);
    
    // Run request.
    // Aggiungiamo il prefisso 'api/' all'URL base
    $uri = sprintf("api/%s", $resource_type);
    $result = $this->request('GET', $uri, ['query' => $query]);
    
    // Log del risultato della ricerca
    if ($result !== FALSE) {
      $this->logger->notice('Omeka API search result: @count risultati trovati', [
        '@count' => count($result->getContent()),
      ]);
    } else {
      $this->logger->error('Omeka API search: nessun risultato o errore nella risposta');
    }

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
          '%id' => $item['o:id'] ?? 'unknown',
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
    // Aggiungiamo il prefisso 'api/' all'URL base
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
   * Recupera un singolo elemento Omeka dall'API.
   *
   * @param string $resource_type
   *   Il tipo di risorsa (es. 'items').
   * @param string|int $id
   *   L'ID della risorsa da recuperare.
   *
   * @return array|null
   *   I dati della risorsa, o NULL se non trovata.
   */
  public function getResource(string $resource_type, $id) {
    $this->logger->notice('Recupero elemento Omeka @type:@id direttamente dall\'API', [
      '@type' => $resource_type,
      '@id' => $id,
    ]);
    
    // URI diretta all'elemento specifico - assicuriamoci di includere il prefisso /api/
    $uri = "api/{$resource_type}/{$id}";
    
    try {
      // Effettua la richiesta API
      $response = $this->request('GET', $uri);
      
      // Verifica la risposta
      if ($response && !$response->hasError()) {
        // Recupera i dati
        $data = $response->getData();
        
        if (!empty($data)) {
          $this->logger->info('Elemento Omeka @type:@id recuperato con successo dall\'API', [
            '@type' => $resource_type,
            '@id' => $id,
          ]);
          
          return $data;
        }
      }
      
      // Log dell'errore se la risposta contiene errori
      if ($response && $response->hasError()) {
        $this->logger->error('Errore API durante il recupero dell\'elemento Omeka @type:@id: @error', [
          '@type' => $resource_type,
          '@id' => $id,
          '@error' => $response->getErrorMessage(),
        ]);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Eccezione durante il recupero dell\'elemento Omeka @type:@id: @error', [
        '@type' => $resource_type,
        '@id' => $id,
        '@error' => $e->getMessage(),
      ]);
    }
    
    return NULL;
  }
  
  /**
   * Recupera tutti gli ID disponibili per un tipo di risorsa.
   *
   * Questo metodo fa una chiamata diretta all'API di Omeka senza usare la configurazione
   * per assicurare che l'URL sia costruito correttamente.
   *
   * @param string $resource_type
   *   Tipo di risorsa (es. 'items', 'collections').
   * @param int $per_page
   *   Numero di elementi per pagina.
   *
   * @return array
   *   Array di ID disponibili, o array vuoto in caso di errore.
   */
  public function getAllAvailableIds(string $resource_type = 'items', int $per_page = 200): array {
    // URL hardcoded per debugging, per assicurare che l'indirizzo sia corretto
    // Utilizziamo l'URL completo con il segmento /api/ esplicito
    $base_url = 'https://storia.dh.unica.it/risorse/api/' . $resource_type;
    
    // Log dell'URL utilizzato
    $this->logger->notice('Omeka API getAllAvailableIds: chiamata diretta a @url', [
      '@url' => $base_url,
    ]);
    
    $all_ids = [];
    $client = \Drupal::httpClient();
    $page = 1;
    $has_more_pages = TRUE;
    $total_processed = 0;
    $total_errors = 0;
    
    // Continua a scaricare pagine fino a quando non ci sono più elementi
    while ($has_more_pages) {
      $this->logger->notice('Omeka API getAllAvailableIds: recupero pagina @page', [
        '@page' => $page,
      ]);
      
      try {
        // Parametri di ricerca con il numero di pagina corrente
        $params = [
          'query' => [
            'page' => $page,
            'per_page' => $per_page,
          ],
        ];
        
        // Esegui la richiesta per la pagina corrente
        $response = $client->request('GET', $base_url, $params);
        
        // Verifica che la risposta sia OK
        if ($response->getStatusCode() == 200) {
          $body = (string) $response->getBody();
          $data = json_decode($body, TRUE);
          
          // Conta i risultati ottenuti in questa pagina
          $items_in_page = is_array($data) ? count($data) : 0;
          $total_processed += $items_in_page;
          
          $this->logger->notice('Omeka API getAllAvailableIds: pagina @page, ricevuti @count risultati', [
            '@page' => $page,
            '@count' => $items_in_page,
          ]);
          
          // Estrai gli ID dalla pagina corrente
          if (is_array($data)) {
            foreach ($data as $item) {
              if (isset($item['o:id'])) {
                $all_ids[] = $item['o:id'];
              }
            }
          }
          
          // Se abbiamo ricevuto meno elementi del massimo per pagina,
          // significa che abbiamo raggiunto l'ultima pagina
          if ($items_in_page < $per_page) {
            $has_more_pages = FALSE;
            $this->logger->notice('Omeka API getAllAvailableIds: ultima pagina raggiunta (@page)', [
              '@page' => $page,
            ]);
          } else {
            // Passa alla pagina successiva
            $page++;
          }
        } else {
          // Errore nella risposta HTTP
          $this->logger->error('Omeka API getAllAvailableIds: risposta HTTP non valida per pagina @page: @code', [
            '@page' => $page,
            '@code' => $response->getStatusCode(),
          ]);
          $total_errors++;
          $has_more_pages = FALSE;  // Interrompi il ciclo in caso di errore
        }
      } catch (\Exception $e) {
        // Errore durante la chiamata API
        $this->logger->error('Omeka API getAllAvailableIds: errore durante la chiamata pagina @page: @error', [
          '@page' => $page,
          '@error' => $e->getMessage(),
        ]);
        $total_errors++;
        $has_more_pages = FALSE;  // Interrompi il ciclo in caso di errore
      }
      
      // Limite di sicurezza: non più di 50 pagine per evitare cicli infiniti
      if ($page > 50) {
        $this->logger->warning('Omeka API getAllAvailableIds: limite massimo di pagine raggiunto (50)');
        $has_more_pages = FALSE;
      }
    }
    
    // Log riassuntivo
    $this->logger->notice('Omeka API getAllAvailableIds: completato con @count ID totali, @errors errori, @pages pagine', [
      '@count' => count($all_ids),
      '@errors' => $total_errors,
      '@pages' => $page,
    ]);
    
    return $all_ids;
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
      // Log dell'URL completo prima della chiamata
      $this->logger->notice('Omeka API request: @method @url', [
        '@method' => $method,
        '@url' => $this->config->get('base_url') . '/' . $uri,
      ]);
      
      // Verifica se l'URI contiene già il segmento /api/
      if (strpos($uri, 'api/') !== 0 && strpos($uri, '/api/') !== 0) {
        // Aggiungi il prefisso /api/ se non è già presente
        $this->logger->notice('Omeka API request: aggiunto prefisso /api/ all\'URI @uri', [
          '@uri' => $uri,
        ]);
        $uri = 'api/' . $uri;
      }
      
      // Run request.
      $response = $this->getApiClient()->request($method, $uri, $options);

      // Log della risposta
      $this->logger->notice('Omeka API response status: @status', [
        '@status' => $response->getStatusCode(),
      ]);
      
      // Build the return.
      $response_body = (string) $response->getBody();
      $this->logger->notice('Omeka API response body: @body', [
        '@body' => substr($response_body, 0, 200) . (strlen($response_body) > 200 ? '...' : ''),
      ]);
      
      $return = new OmekaApiResponse($response_body);

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
