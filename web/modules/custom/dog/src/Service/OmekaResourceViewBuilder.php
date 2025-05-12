<?php

namespace Drupal\dog\Service;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class which builds renders for remote resource elements.
 * 
 * Modified to use the cache service exclusively, without live API calls.
 */
class OmekaResourceViewBuilder implements ResourceViewBuilderInterface {

  use StringTranslationTrait;

  /**
   * The omeka cache service.
   *
   * @var \Drupal\dog\Service\OmekaCacheService
   */
  protected $cacheService;

  /**
   * Constructs a OmekaResourceViewBuilder object.
   *
   * @param \Drupal\dog\Service\OmekaCacheService $cache_service
   *   The omeka cache service.
   */
  public function __construct(OmekaCacheService $cache_service) {
    $this->cacheService = $cache_service;
  }

  /**
   * {@inheritDoc}
   */
  public function viewMultiple(array $entities = [], string $view_mode = 'full', ?string $langcode = NULL): array {
    $build_list = [
      '#sorted' => TRUE,
    ];
    $weight = 0;
    foreach ($entities as $key => $entity) {
      $build_list[$key] = $this->view($entity, $view_mode);

      $build_list[$key]['#weight'] = $weight++;
    }

    return $build_list;
  }

  /**
   * {@inheritDoc}
   */
  public function view($entity, string $view_mode = 'full', ?string $langcode = NULL): array {
    if (is_object($entity) && property_exists($entity, 'id') && property_exists($entity, 'type')) {
      $id = $entity->id;
      $type = $entity->type;
    }
    elseif (is_array($entity) && !empty($entity['id']) && !empty($entity['type'])) {
      $id = $entity['id'];
      $type = $entity['type'];
    }
    else {
      throw new \InvalidArgumentException("ID and Type for Omeka Resource is required.");
    }

    // Retrieve data only from cache, no live API calls
    $data = $this->cacheService->getResource($id, $type);

    if (empty($data)) {
      $last_update_info = $this->cacheService->getLastUpdateInfo();
      
      return [
        '#theme' => 'omeka_resource_cache_empty',
        '#resource_id' => $id,
        '#resource_type' => $type,
        '#last_cache_update' => $last_update_info['formatted_date'],
        '#cache' => [
          'tags' => [
            'omeka_resource:' . $type . ':' . $id,
            'omeka_resources:all',
          ],
          'contexts' => ['url'],
          'max-age' => 1800, // Cache for 30 minutes
        ],
      ];
    }

    return [
      '#theme' => 'dog_omeka_resource',
      '#omeka_resource_id' => $id,
      '#omeka_resource_type' => $type,
      '#omeka_resource_data' => $data,
      '#view_mode' => $view_mode,
      '#cache' => [
        'tags' => [
          'omeka_resource:' . $type . ':' . $id,
          'omeka_resource:' . $type,
          'omeka_resources:all',
        ],
        'contexts' => ['url'],
        'max-age' => 86400, // Cache for 24 hours
      ],
    ];
  }

}
