<?php

namespace Drupal\dog\Service;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines the OmekaResourceViewBuilder class.
 *
 * @package Drupal\dog
 */
class OmekaResourceViewBuilder implements ResourceViewBuilderInterface {

  use StringTranslationTrait;

  /**
   * The resource fetcher service.
   *
   * @var \Drupal\dog\Service\ResourceFetcherInterface
   */
  protected $resourceFetcher;

  /**
   * Constructs a OmekaResourceViewBuilder object.
   *
   * @param \Drupal\dog\Service\ResourceFetcherInterface $resource_fetcher
   *   The resource fetcher service.
   */
  public function __construct(ResourceFetcherInterface $resource_fetcher) {
    $this->resourceFetcher = $resource_fetcher;
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

    $data = $this->resourceFetcher->retrieveResource($id, $type);

    if (empty($data)) {
      return [
        '#markup' => '<p>' . $this->t('Omeka Resource not found.') . '</p>',
      ];
    }

    return [
      '#theme' => 'dog_omeka_resource',
      '#omeka_resource_id' => $id,
      '#omeka_resource_type' => $type,
      '#omeka_resource_data' => $data,
      '#view_mode' => $view_mode,
    ];
  }

}
