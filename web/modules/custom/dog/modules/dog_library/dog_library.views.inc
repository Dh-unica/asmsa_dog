<?php

/**
 * @file
 * Provide views data for dog_library.
 */

/**
 * Implements hook_views_data().
 */
function dog_library_views_data(): array {
  $data = [];

  $data['views_remote_data_dog_omeka_resource']['dog_library_omeka_resource_select_form'] = [
    'title' => t('Select Resource'),
    'help' => t('Provides a field for selecting resource entities in our Omeka Resource library view.'),
    'field' => [
      'id' => 'dog_library_omeka_resource_select_form',
    ],
  ];

  return $data;
}
