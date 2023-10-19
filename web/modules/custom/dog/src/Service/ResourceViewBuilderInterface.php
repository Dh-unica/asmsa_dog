<?php

namespace Drupal\dog\Service;

/**
 * Defines the ResourceViewBuilderInterface trait.
 *
 * @package Drupal\dog\Service
 */
interface ResourceViewBuilderInterface {

  /**
   * Builds the render array for the provided Omeka resource.
   *
   * @param mixed $entity
   *   The entity (object or array).
   * @param string $view_mode
   *   (optional) The view mode that should be used to render the resource.
   * @param string|null $langcode
   *   (optional) For which language the resource should be rendered, defaults
   *   to the current content language.
   *
   * @return array
   *   A render array for the Omeka resource.
   *
   * @throws \InvalidArgumentException
   *   Can be thrown when the set of parameters is inconsistent.
   */
  public function view($entity, string $view_mode = 'full', ?string $langcode = NULL): array;

  /**
   * Builds the render array for the provided Omeka resources.
   *
   * @param array $entities
   *   An array of entities (object) or ID (string).
   * @param string $view_mode
   *   (optional) The view mode that should be used to render the resource.
   * @param string|null $langcode
   *   (optional) For which language the resource should be rendered, defaults
   *   to the current content language.
   *
   * @return array
   *   A render array for the resources, indexed by the same keys as the
   *   resource array passed in $entities.
   *
   * @throws \InvalidArgumentException
   *   Can be thrown when the set of parameters is inconsistent.
   */
  public function viewMultiple(array $entities = [], string $view_mode = 'full', ?string $langcode = NULL): array;

}
