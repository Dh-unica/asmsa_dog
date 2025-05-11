<?php

namespace Drupal\italiagov_cache_warmer\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service per il warming della cache dei blocchi Omeka.
 */
class CacheWarmerService {

  /**
   * L'entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * La connessione al database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Il servizio di cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Il servizio Omeka utils.
   *
   * @var \Drupal\omeka_utils\Utils
   */
  protected $omekaUtils;

  /**
   * Il servizio logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Costruttore.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   L'entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   La connessione al database.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   Il servizio di cache.
   * @param object $omeka_utils
   *   Il servizio Omeka utils.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Il servizio logger factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Connection $database,
    CacheBackendInterface $cache,
    $omeka_utils,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->cache = $cache;
    $this->omekaUtils = $omeka_utils;
    $this->logger = $logger_factory->get('italiagov_cache_warmer');
  }

  /**
   * Esegue il warming della cache per un singolo blocco Omeka Map.
   *
   * @param \Drupal\block_content\Entity\BlockContent $block
   *   Il blocco per cui ricostruire la cache.
   *
   * @return bool
   *   TRUE se la cache è stata ricostruita con successo, FALSE altrimenti.
   */
  public function warmCacheForBlock($block) {
    try {
      // Recupera i dati Omeka dal blocco.
      if (!$block->hasField('field_omeka_map')) {
        $this->logger->warning('Il blocco ID: @id non ha il campo field_omeka_map', ['@id' => $block->id()]);
        return FALSE;
      }

      $omeka_map = $block->get('field_omeka_map')->referencedEntities();
      if (empty($omeka_map)) {
        $this->logger->warning('Il blocco ID: @id non ha riferimenti field_omeka_map', ['@id' => $block->id()]);
        return FALSE;
      }

      $omeka_map = $omeka_map[0];
      $omeka_items = $omeka_map->get('field_omeka_item')->getValue();
      
      if (empty($omeka_items)) {
        $this->logger->warning('Il blocco ID: @id non ha item Omeka', ['@id' => $block->id()]);
        return FALSE;
      }

      // Elabora gli item Omeka e genera la cache.
      $full_items = [];
      $items_ids = [];

      foreach ($omeka_items as $omeka_item) {
        $omeka_id = $omeka_item['id'];
        $omeka_item_full = $this->omekaUtils->getItem($omeka_id);
        $marker_key = 'o-module-mapping:feature';
        
        if (!empty($omeka_item_full->$marker_key)) {
          $items_ids[] = $omeka_id;
          $marker = $omeka_item_full->{'o-module-mapping:feature'};
          
          $full_items[$omeka_item['id']]['full_item'] = $omeka_item_full;
          $marker_object = $this->omekaUtils->getLocation($omeka_item_full);
          $full_items[$omeka_item['id']]['location'] = $marker_object;
          $full_items[$omeka_item['id']]['absolute_url'] = $this->omekaUtils->getItemUrl($omeka_item_full);

          // Extract coordinates
          if (!empty($marker[0]->{'o-module-mapping:geography-coordinates'})) {
            $coordinates = $marker[0]->{'o-module-mapping:geography-coordinates'};
            $full_items[$omeka_item['id']]['latitude'] = $coordinates[1];
            $full_items[$omeka_item['id']]['longitude'] = $coordinates[0];
          }
        }
      }

      // Salva i dati in cache con durata permanente e tag specifici.
      $cache_key = 'italiagov:omeka_map:' . $block->id() . ':' . md5(serialize($omeka_items));
      $this->cache->set(
        $cache_key,
        ['full_items' => $full_items, 'items_ids' => $items_ids],
        \Drupal\Core\Cache\Cache::PERMANENT,
        // Utilizziamo tag specifici che non vengono invalidati da drush cr
        ['omeka_map_persistent', 'omeka_map_block_' . $block->id()]
      );

      $this->logger->info('Cache generata per il blocco Omeka Map ID: @id', ['@id' => $block->id()]);
      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Errore durante il warming della cache per il blocco ID: @id. Errore: @error', [
        '@id' => $block->id(),
        '@error' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Esegue il warming della cache per tutti i blocchi Omeka Map.
   *
   * @return int
   *   Il numero di blocchi elaborati con successo.
   */
  public function warmCache() {
    // Recupera tutti i blocchi di tipo omeka_map.
    $blocks = $this->entityTypeManager->getStorage('block_content')
      ->loadByProperties(['type' => 'omeka_map']);

    $processed = 0;
    $this->logger->notice('Inizio processo di cache warming per @count blocchi Omeka Map', ['@count' => count($blocks)]);

    foreach ($blocks as $block) {
      if ($this->warmCacheForBlock($block)) {
        $processed++;
      }
    }

    $this->logger->notice('Cache warming completato. Elaborati @processed blocchi su @total', [
      '@processed' => $processed,
      '@total' => count($blocks),
    ]);

    return $processed;
  }

}
