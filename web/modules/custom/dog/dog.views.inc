<?php

/**
 * @file
 * Provide views data for dog.
 */

/**
 * Implements hook_views_data().
 */
function dog_views_data() {
  $data = [];

  $data['views_remote_data_dog_omeka_resource']['table']['group'] = 'dog';
  $data['views_remote_data_dog_omeka_resource']['table']['base'] = [
    'title' => 'Omeka Resource',
    'query_id' => 'views_remote_data_query',
  ];

  $data['views_remote_data_dog_omeka_resource']['dog_omeka_resource_type'] = [
    'title' => t('Omeka Resource Type'),
    'help' => t('Provides a filter for filtering Omeka Resource entities.'),
    'argument' => [
      'id' => 'dog_omeka_resource_type',
    ],
  ];

  $data['views_remote_data_dog_omeka_resource']['dog_omeka_resource_collection'] = [
    'title' => t('Omeka Resource Item Sets'),
    'help' => t('Provides a filter for filtering Omeka Resource entities.'),
    'filter' => [
      'id' => 'dog_omeka_resource_collection',
    ],
  ];

  $data['views_remote_data_dog_omeka_resource']['dog_omeka_resource_fulltext_search'] = [
    'title' => t('Fulltext search'),
    'help' => t('Provides a filter for filtering Omeka Resource entities.'),
    'field' => 'fulltext_search',
    'filter' => [
      'field' => 'fulltext_search',
      'id' => 'string',
    ],
  ];

  return $data;
}
