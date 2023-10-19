<?php

namespace Drupal\omeka_utils;
use Drupal\Core\Cache\Cache;
#[\AllowDynamicProperties]

class Utils {
  function __construct() {
    $config = \Drupal::config('dog.settings');
    $this->base_url = $config->get('base_url');
    $this->url = $this->base_url . 'api/items/';
  }

  /**
   *$omeka = \Drupal::service('dh_omeka.utils');
   */
  public function getItem($id) {
    $omeka_item_source = file_get_contents($this->url. $id);
    $omeka_item = json_decode($omeka_item_source);
    return $omeka_item;
  }

  public function getDescription($item) {
    $template = $this->getResourceTemplate($item);
    $description_fields = [
      '2' => 'oad:scopeAndContent',
      '4' => 'oad:scopeAndContent',
      '5' => 'oad:scopeAndContent',
      '6' => 'dcterms:description',
      '7' => 'bibo:content',
    ];

    $description_field = $description_fields[$template];
    $descrizione = $item->{$description_field};
    return $descrizione[0]->{'@value'};
  }

  public function getTitle($item) {
    $title = $item->{'dcterms:title'};
    return $title[0]->{'@value'};
  }

  public function getIdFromEck($entity) {
    $id = $entity->get('field_id')->getValue();
    return $id[0]['value'];
  }

  public function getImage($item, $type = 'large') {
    $medias = $item->{"o:media"};
    if (!empty($medias)) {
      $media_url = $medias[0]->{"@id"};
      $media_source = file_get_contents($media_url);
      $media = json_decode($media_source);
      $image_src = $media->thumbnail_display_urls->{$type};
      return $image_src;
    }
  }

  public function getResourceTemplate($item) {
    $resource_id = $item->{'o:resource_template'};
    return $resource_id->{"o:id"};
  }

  public function getLatLon($item) {
    $marker_url = $item->{'o-module-mapping:marker'};
    $marker_object = file_get_contents($marker_url[0]->{'@id'});
    $marker = json_decode($marker_object);
    $values['lat'] = $marker->{'o-module-mapping:lat'};
    $values['lon'] = $marker->{'o-module-mapping:lng'};
    $values['title'] = $marker->{'o-module-mapping:label'};
    $values['url'] = 'https://risorse.dh.unica.it/s/400-risorse/item/' . $item->{'o:id'};
    $values['image'] = $this->getImage($item);
    return $values;
  }

  public function getResourceName($resource_id) {
    $resources = [
      '1' => 'Base Resource',
      '2' => 'Risorsa fotografica',
      '3' => 'Risorsa archeologica',
      '4' => 'Risorsa cartografica',
      '5' => 'Risorsa documentale',
      '6' => 'Risorsa opera d\'arte',
      '7' => 'Risorsa bibliografica',
      '8' => 'Risorsa audio',
    ];
    return $resources[$resource_id];
  }

  public function getItemUrl($item) {
    $site_url = $this->getSiteUrl($item);
    return $site_url . '/item/' . $item->{'o:id'};
  }

  public function getSiteUrl($item) {
    $cacheId = 'omeka_site_' . $item->{'o:id'};
    if ($cache = \Drupal::cache()->get($cacheId)) {
      return $cache->data;
    } else {
      $cacheId = 'omeka_site_' . $item->{'o:id'};
      $site_id = $item->{'o:site'};
      $site_source = file_get_contents($site_id[0]->{'@id'});
      $site = json_decode($site_source);
      $slug = $site->{'o:slug'};
      $site_url = $this->base_url . 's/' . $slug;
      $expire = '604800'; // One week
      \Drupal::cache()->set($cacheId, $site_url, $expire);
      return $site_url;
    }
  }
}

