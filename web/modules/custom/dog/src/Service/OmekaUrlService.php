<?php

namespace Drupal\dog\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Service for handling Omeka-S URL transformations.
 */
class OmekaUrlService {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Cache lifetime in seconds (5 minutes).
   *
   * @var int
   */
  protected const CACHE_LIFETIME = 300;

  /**
   * Constructs a new OmekaUrlService.
   */
  public function __construct(
    ClientInterface $http_client,
    CacheBackendInterface $cache
  ) {
    $this->httpClient = $http_client;
    $this->cache = $cache;
  }

  /**
   * Transforms an Omeka-S API URL to a public URL.
   *
   * @param string $api_url
   *   The original API URL (e.g., https://s[base-url]/api/media/3422).
   *
   * @return string
   *   The transformed public URL or the original API URL if transformation fails.
   */
  public function transformApiUrl($api_url) {
    // Extract media ID from the API URL.
    if (!preg_match('#/api/media/(\d+)#', $api_url, $matches)) {
      return $api_url;
    }
    $media_id = $matches[1];

    // Try to get from cache first.
    $cache_key = 'dog:omeka_url:' . $media_id;
    if ($cache = $this->cache->get($cache_key)) {
      return $cache->data;
    }

    try {
      // 1. Get parent item ID from media.
      $media_data = $this->makeApiRequest($api_url);
      if (!isset($media_data['o:item']['@id'])) {
        return $api_url;
      }

      // 2. Get site ID from item.
      $item_data = $this->makeApiRequest($media_data['o:item']['@id']);
      if (!isset($item_data['o:site']) || !is_array($item_data['o:site']) || empty($item_data['o:site'])) {
        return $api_url;
      }

      // 3. Get site slug.
      $site_data = $this->makeApiRequest($item_data['o:site'][0]['@id']);
      if (!isset($site_data['o:slug'])) {
        return $api_url;
      }

      // Construct the public URL.
      $base_url = preg_replace('#^(https?://[^/]+/[^/]+).*$#', '$1', $api_url);
      $public_url = sprintf(
        '%s/s/%s/media/%s',
        $base_url,
        $site_data['o:slug'],
        $media_id
      );

      // Cache the result for 5 minutes
      $this->cache->set($cache_key, $public_url, time() + static::CACHE_LIFETIME);

      return $public_url;
    }
    catch (GuzzleException $e) {
      return $api_url;
    }
  }

  /**
   * Makes an API request to Omeka-S.
   *
   * @param string $url
   *   The API URL.
   *
   * @return array
   *   The decoded JSON response.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function makeApiRequest($url) {
    $response = $this->httpClient->request('GET', $url);
    return json_decode((string) $response->getBody(), TRUE);
  }

}
