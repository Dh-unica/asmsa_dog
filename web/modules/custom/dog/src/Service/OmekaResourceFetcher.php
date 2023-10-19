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
    // Run request.
    $uri = sprintf("api/%s/%s", $resource_type, $id);
    $result = $this->request('GET', $uri);

    if ($result === FALSE) {
      return NULL;
    }

    // Build the return data.
    $data = $result->getContent();
    $data['id'] = $id;
    $data['type'] = $resource_type;

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
