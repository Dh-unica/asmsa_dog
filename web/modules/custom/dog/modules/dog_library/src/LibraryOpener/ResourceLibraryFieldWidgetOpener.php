<?php

namespace Drupal\dog_library\LibraryOpener;

use Drupal\dog_library\ResourceLibraryState;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;

/**
 * Defines the ResourceLibraryFieldWidgetOpener class.
 *
 * @package Drupal\dog_library\LibraryOpener
 */
class ResourceLibraryFieldWidgetOpener implements ResourceLibraryOpenerInterface {

  /**
   * {@inheritdoc}
   */
  public function getSelectionResponse(ResourceLibraryState $state, array $selected_ids) {
    $response = new AjaxResponse();

    $parameters = $state->getOpenerParameters();
    if (empty($parameters['field_widget_id'])) {
      throw new \InvalidArgumentException('field_widget_id parameter is missing.');
    }

    // Create a comma-separated list of resource IDs, insert them in the hidden
    // field of the widget, and trigger the field update via the hidden submit
    // button.
    $widget_id = $parameters['field_widget_id'];
    $ids = implode(',', $selected_ids);
    $response
      ->addCommand(new InvokeCommand("[data-resource-library-widget-value=\"$widget_id\"]", 'val', [$ids]))
      ->addCommand(new InvokeCommand("[data-resource-library-widget-update=\"$widget_id\"]", 'trigger', ['mousedown']));

    return $response;
  }

}
