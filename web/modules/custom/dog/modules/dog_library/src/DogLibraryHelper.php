<?php

namespace Drupal\dog_library;

use Drupal\dog\Service\OmekaCacheService;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helper class for dog_library module.
 *
 * Provides easy access to the Omeka cache service and common utilities.
 */
class DogLibraryHelper implements ContainerInjectionInterface {

  /**
   * The Omeka cache service.
   *
   * @var \Drupal\dog\Service\OmekaCacheService
   */
  protected $cacheService;

  /**
   * Creates a new DogLibraryHelper instance.
   *
   * @param \Drupal\dog\Service\OmekaCacheService $cache_service
   *   The Omeka cache service.
   */
  public function __construct(OmekaCacheService $cache_service) {
    $this->cacheService = $cache_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dog.omeka_cache')
    );
  }

  /**
   * Gets a resource from cache.
   *
   * @param string $id
   *   The resource ID.
   * @param string $resource_type
   *   The resource type.
   *
   * @return array|null
   *   The resource data, or NULL if not in cache.
   */
  public function getResource(string $id, string $resource_type): ?array {
    return $this->cacheService->getResource($id, $resource_type);
  }

  /**
   * Gets multiple resources from cache.
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
    return $this->cacheService->getMultipleResources($ids, $resource_type);
  }

  /**
   * Gets search results from cache.
   *
   * @param string $resource_type
   *   The resource type.
   * @param array $parameters
   *   Search parameters.
   *
   * @return array
   *   Array of resources matching the search criteria.
   */
  public function getSearchResults(string $resource_type, array $parameters = []): array {
    return $this->cacheService->getSearchResults($resource_type, $parameters);
  }

  /**
   * Provides static access to the helper.
   *
   * @return \Drupal\dog_library\DogLibraryHelper
   *   The helper instance.
   */
  public static function getInstance() {
    return \Drupal::service('dog_library.helper');
  }

}
