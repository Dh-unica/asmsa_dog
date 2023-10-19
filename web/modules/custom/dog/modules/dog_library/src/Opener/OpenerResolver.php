<?php

namespace Drupal\dog_library\Opener;

use Drupal\dog_library\ResourceLibraryState;
use Drupal\dog_library\LibraryOpener\ResourceLibraryOpenerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Defines a class to get resource library openers from the container.
 *
 * @package Drupal\dog_library\Opener
 */
class OpenerResolver implements OpenerResolverInterface {

  use ContainerAwareTrait;

  /**
   * {@inheritdoc}
   */
  public function get(ResourceLibraryState $state) {
    $service_id = $state->getOpenerId();

    $service = $this->container->get($service_id);
    if ($service instanceof ResourceLibraryOpenerInterface) {
      return $service;
    }
    throw new \RuntimeException("$service_id must be an instance of " . ResourceLibraryOpenerInterface::class);
  }

}
