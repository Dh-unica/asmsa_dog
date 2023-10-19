<?php

namespace Drupal\dog_library\Opener;

use Drupal\dog_library\ResourceLibraryState;

/**
 * Defines an interface to get a resource library opener from the container.
 *
 * @package Drupal\dog_library\Opener
 */
interface OpenerResolverInterface {

  /**
   * Gets a resource library opener service from the container.
   *
   * @param \Drupal\dog_library\ResourceLibraryState $state
   *   A value object representing the state of the resource library.
   *
   * @return \Drupal\dog_library\LibraryOpener\ResourceLibraryOpenerInterface
   *   The resource library opener service.
   *
   * @throws \RuntimeException
   *   If the requested opener service does not implement
   *   ResourceLibraryOpenerInterface.
   */
  public function get(ResourceLibraryState $state);

}
