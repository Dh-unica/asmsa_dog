<?php

namespace Drupal\dog_library\LibraryOpener;

use Drupal\dog_library\ResourceLibraryState;

/**
 * Defines an interface for resource library openers.
 *
 * Resource library opener services allow modules to check access to the
 * resource library selection dialog and respond to selections. Example use
 * cases that require different handling:
 * - when used in a field widget;
 * - when used in a text editor.
 *
 * Openers that require additional parameters or metadata should retrieve them
 * from the ResourceLibraryState object.
 *
 * @package Drupal\dog_library\LibraryOpener
 */
interface ResourceLibraryOpenerInterface {

  /**
   * Generates a response after selecting resource items in the resource
   * library.
   *
   * @param \Drupal\dog_library\ResourceLibraryState $state
   *   The state the resource library was in at the time of selection, allowing
   *   the response to be customized based on that state.
   * @param int[] $selected_ids
   *   The IDs of the selected resource items.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response to update the page after selecting resource.
   */
  public function getSelectionResponse(ResourceLibraryState $state, array $selected_ids);

}
