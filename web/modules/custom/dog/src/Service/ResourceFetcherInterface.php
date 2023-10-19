<?php

namespace Drupal\dog\Service;

use GuzzleHttp\ClientInterface;

/**
 * Defines the ResourceFetcherInterface trait.
 *
 * @package Drupal\dog\Service
 */
interface ResourceFetcherInterface {

  /**
   * Retrieve the API client.
   *
   * @param bool $reset_client
   *   If want to re-instance the http client.
   *
   * @return \GuzzleHttp\ClientInterface
   *   The HTTP client.
   */
  public function getApiClient(bool $reset_client = FALSE): ClientInterface;

  /**
   * Retrieve the item sets information.
   *
   * @return array
   *   An array contains the data.
   */
  public function getItemSets(): array;

  /**
   * Retrieve the types of omeka resource.
   *
   * @return array
   *   An array with each element contains "machine_name" and "label".
   */
  public function getTypes(): array;

  /**
   * Retrieve the resource data.
   *
   * @param string $id
   *   The ID filter.
   * @param string $resource_type
   *   The resource type.
   *
   * @return object|null
   *   An object that contains 'ID', 'type' and other properties for the
   *   resource.
   */
  public function retrieveResource(string $id, string $resource_type): ?array;

  /**
   * Search method.
   *
   * @param string $resource_type
   *   The resource type.
   * @param array $parameters
   *   The filters.
   * @param int $page
   *   The number page request.
   * @param int $items_per_page
   *   Items per page.
   * @param int $total_results
   *   This field is used for pass the total results return from API.
   *
   * @return array An object that contains 'rows' and 'pager' properties.
   *   An object that contains 'rows' and 'pager' properties.
   *   Each element(object) in 'rows' has ID, type and other properties.
   */
  public function search(string $resource_type, array $parameters = [], int $page = 0, int $items_per_page = 10, int &$total_results = 0): array;

}
