<?php

namespace Drupal\omeka_utils\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\dog\Service\OmekaUtils;

/**
 * Controller per il caricamento progressivo dei dati della mappa Omeka.
 */
class OmekaMapController extends ControllerBase {

  /**
   * Il servizio OmekaUtils.
   *
   * @var \Drupal\dog\Service\OmekaUtils
   */
  protected $omekaUtils;

  /**
   * Costruttore.
   *
   * @param \Drupal\dog\Service\OmekaUtils $omeka_utils
   *   Il servizio OmekaUtils.
   */
  public function __construct(OmekaUtils $omeka_utils) {
    $this->omekaUtils = $omeka_utils;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dog.omeka_utils')
    );
  }

  /**
   * Endpoint AJAX per il caricamento progressivo dei dati della mappa.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   La richiesta HTTP.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   La risposta JSON.
   */
  public function loadMapData(Request $request) {
    // Parametri attesi: ids (array di ID), offset, limit
    $ids = $request->query->get('ids');
    $offset = (int) $request->query->get('offset', 0);
    $limit = (int) $request->query->get('limit', 15);
    
    if (empty($ids)) {
      return new JsonResponse(['error' => 'Nessun ID fornito'], 400);
    }
    
    // Converti la stringa degli ID in array se necessario
    if (!is_array($ids)) {
      $ids = explode(',', $ids);
    }
    
    // Prendi solo un sottoinsieme di ID basato su offset e limit
    $batch_ids = array_slice($ids, $offset, $limit);
    
    // Ottieni i dati per questo batch di elementi
    $items_data = [];
    $start_time = microtime(true);
    
    foreach ($batch_ids as $omeka_id) {
      // Ottieni l'elemento dalla cache
      $omeka_item_full = $this->omekaUtils->getItem($omeka_id);
      
      if (!$omeka_item_full) {
        continue;
      }
      
      // Verifica della presenza delle feature di mappatura
      $has_marker = FALSE;
      if (is_object($omeka_item_full)) {
        $has_marker = !empty($omeka_item_full->{'o-module-mapping:feature'});
      }
      elseif (is_array($omeka_item_full)) {
        $has_marker = !empty($omeka_item_full['o-module-mapping:feature']);
      }
      
      if (!$has_marker) {
        continue;
      }
      
      // Estrai titolo
      $title = '';
      if (is_object($omeka_item_full) && isset($omeka_item_full->{'o:title'})) {
        $title = $omeka_item_full->{'o:title'};
      }
      elseif (is_array($omeka_item_full) && isset($omeka_item_full['o:title'])) {
        $title = $omeka_item_full['o:title'];
      }
      
      // Ottieni le coordinate
      $location = $this->omekaUtils->getLocation($omeka_item_full);
      
      // Estrai la thumbnail
      $thumbnail_url = $this->extractThumbnailUrl($omeka_item_full);
      
      // Crea una versione minima dell'elemento
      $minimal_full_item = [
        'o:id' => $omeka_id,
        'dcterms:title' => [
          ['@value' => $title],
        ],
        'dcterms:date' => [
          ['@value' => $this->extractDateInfo($omeka_item_full)],
        ],
        'thumbnail_display_urls' => [
          'large' => $thumbnail_url,
          'medium' => $thumbnail_url,
          'square' => $thumbnail_url,
        ],
      ];
      
      // Crea la struttura finale
      $items_data[$omeka_id] = [
        'full_item' => $minimal_full_item,
        'location' => $location,
        'absolute_url' => $this->omekaUtils->getItemUrl($omeka_item_full),
      ];
      
      // Aggiungi il tipo di risorsa se disponibile
      $resource_class = $this->extractResourceClass($omeka_item_full);
      if (!empty($resource_class)) {
        $items_data[$omeka_id]['resource_class'] = $resource_class;
      }
    }
    
    // Registra il tempo di elaborazione del batch
    $execution_time = microtime(true) - $start_time;
    $this->getLogger('omeka_map')->info('Batch AJAX completato in @time sec: @offset-@end di @total elementi', [
      '@time' => round($execution_time, 2),
      '@offset' => $offset,
      '@end' => $offset + count($items_data),
      '@total' => count($ids),
    ]);
    
    // Restituisci i dati come JSON
    return new JsonResponse([
      'items' => $items_data,
      'total' => count($ids),
      'processed' => $offset + count($items_data),
      'remaining' => count($ids) - ($offset + count($items_data)),
      'execution_time' => round($execution_time, 2),
    ]);
  }

  /**
   * Estrae l'URL della thumbnail da un elemento Omeka.
   *
   * @param mixed $item
   *   L'elemento Omeka.
   *
   * @return string
   *   L'URL della thumbnail.
   */
  protected function extractThumbnailUrl($item) {
    $thumbnail_url = '';
    
    if (is_object($item)) {
      if (isset($item->thumbnail_display_urls) && isset($item->thumbnail_display_urls->medium)) {
        $thumbnail_url = $item->thumbnail_display_urls->medium;
      }
    }
    elseif (is_array($item)) {
      if (isset($item['thumbnail_display_urls']) && isset($item['thumbnail_display_urls']['medium'])) {
        $thumbnail_url = $item['thumbnail_display_urls']['medium'];
      }
    }
    
    return $thumbnail_url;
  }

  /**
   * Estrae le informazioni sulla data da un elemento Omeka.
   *
   * @param mixed $item
   *   L'elemento Omeka.
   *
   * @return string
   *   La data.
   */
  protected function extractDateInfo($item) {
    $date = '';
    
    if (is_object($item)) {
      if (isset($item->{'dcterms:date'}) && is_array($item->{'dcterms:date'}) && !empty($item->{'dcterms:date'})) {
        $date = $item->{'dcterms:date'}[0]->{'@value'} ?? '';
      }
    }
    elseif (is_array($item)) {
      if (isset($item['dcterms:date']) && is_array($item['dcterms:date']) && !empty($item['dcterms:date'])) {
        $date = $item['dcterms:date'][0]['@value'] ?? '';
      }
    }
    
    return $date;
  }

  /**
   * Estrae la classe di risorsa da un elemento Omeka.
   *
   * @param mixed $item
   *   L'elemento Omeka.
   *
   * @return string
   *   La classe di risorsa.
   */
  protected function extractResourceClass($item) {
    $resource_class = '';
    
    if (is_object($item)) {
      if (isset($item->{'o:resource_class'}) && isset($item->{'o:resource_class'}->{'o:label'})) {
        $resource_class = $item->{'o:resource_class'}->{'o:label'};
      }
    }
    elseif (is_array($item)) {
      if (isset($item['o:resource_class']) && isset($item['o:resource_class']['o:label'])) {
        $resource_class = $item['o:resource_class']['o:label'];
      }
    }
    
    return $resource_class;
  }

}
