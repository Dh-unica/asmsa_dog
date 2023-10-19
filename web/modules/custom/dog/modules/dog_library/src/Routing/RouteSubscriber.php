<?php

namespace Drupal\dog_library\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines the RouteSubscriber class.
 *
 * @package Drupal\dog_library\Routing
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {

    // Add the resource library UI access checks to the widget displays of the
    // resource library view.
    if ($route = $collection->get('view.resource_library.widget_table')) {
      $route->addRequirements(['_custom_access' => 'dog_library.ui_builder:checkAccess']);
    }
  }

}
