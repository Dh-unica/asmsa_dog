<?php

namespace Drupal\italiagov_cache_warmer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Cache\Cache;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;

/**
 * Controller per il report e la gestione della cache Omeka.
 */
class CacheReportController extends ControllerBase {

  /**
   * Il servizio di cache warmer.
   *
   * @var \Drupal\italiagov_cache_warmer\Service\CacheWarmerService
   */
  protected $cacheWarmer;

  /**
   * Il servizio di cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Il servizio di database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Il servizio Omeka utils.
   *
   * @var object
   */
  protected $omekaUtils;

  /**
   * Il servizio date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('italiagov_cache_warmer.warmer'),
      $container->get('cache.omeka_map'),
      $container->get('database'),
      $container->get('omeka_utils.utils'),
      $container->get('date.formatter')
    );
  }

  /**
   * Costruttore.
   *
   * @param object $cache_warmer
   *   Il servizio di cache warmer.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   Il servizio di cache.
   * @param \Drupal\Core\Database\Connection $database
   *   Il servizio di database.
   * @param object $omeka_utils
   *   Il servizio Omeka utils.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   Il servizio date formatter.
   */
  public function __construct(
    $cache_warmer,
    CacheBackendInterface $cache,
    Connection $database,
    $omeka_utils,
    DateFormatterInterface $date_formatter
  ) {
    $this->cacheWarmer = $cache_warmer;
    $this->cache = $cache;
    $this->database = $database;
    $this->omekaUtils = $omeka_utils;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * Pagina di report della cache.
   *
   * @return array
   *   Render array per la pagina di report.
   */
  public function report() {
    // Recupera tutti i blocchi di tipo omeka_map.
    $blocks = $this->entityTypeManager()
      ->getStorage('block_content')
      ->loadByProperties(['type' => 'omeka_map']);

    // Prepara i dati per la tabella.
    $header = [
      $this->t('ID Blocco'),
      $this->t('Titolo'),
      $this->t('Nodi associati'),
      $this->t('Stato cache'),
      $this->t('Ultimo aggiornamento'),
      $this->t('Azioni'),
    ];

    $rows = [];
    $total_cached = 0;

    foreach ($blocks as $block) {
      // Verifica se il blocco ha la cache.
      $omeka_map = $block->get('field_omeka_map')->referencedEntities();
      if (empty($omeka_map)) {
        continue;
      }

      $omeka_map = $omeka_map[0];
      $omeka_items = $omeka_map->get('field_omeka_item')->getValue();
      
      // Crea la chiave di cache.
      $cache_key = 'italiagov:omeka_map:' . $block->id() . ':' . md5(serialize($omeka_items));
      $cache_data = $this->cache->get($cache_key);
      
      // Ottieni i nodi associati al blocco.
      $nodes_count = 0;
      $query = $this->database->select('block_content__field_omeka_map', 'b');
      $query->join('paragraphs_item_field_data', 'p', 'b.field_omeka_map_target_id = p.id');
      $query->join('node__field_blocks', 'n', 'n.field_blocks_target_id = b.entity_id');
      $query->fields('n', ['entity_id']);
      $query->condition('b.entity_id', $block->id());
      $result = $query->execute()->fetchAll();
      $nodes_count = count($result);

      // Prepara i dati per la riga.
      $status = $cache_data ? $this->t('In cache') : $this->t('Non in cache');
      $last_update = $cache_data ? $this->dateFormatter->format($cache_data->created, 'medium') : $this->t('Mai');
      
      if ($cache_data) {
        $total_cached++;
      }

      // Link per ricostruire la cache.
      $rebuild_url = Url::fromRoute('italiagov_cache_warmer.rebuild', ['block_id' => $block->id()]);
      $rebuild_link = Link::fromTextAndUrl($this->t('Ricostruisci cache'), $rebuild_url)->toRenderable();
      
      $rows[] = [
        $block->id(),
        $block->label(),
        $nodes_count,
        $status,
        $last_update,
        render($rebuild_link),
      ];
    }

    // Pulsante per ricostruire tutta la cache.
    $rebuild_all_form = $this->formBuilder()->getForm('Drupal\italiagov_cache_warmer\Form\RebuildAllCacheForm');

    return [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('Nessun blocco Omeka Map trovato.'),
      '#caption' => $this->t('Blocchi Omeka Map: @cached in cache su @total totali', [
        '@cached' => $total_cached,
        '@total' => count($blocks),
      ]),
      'rebuild_all_form' => $rebuild_all_form,
      '#attached' => [
        'library' => [
          'core/drupal.dialog.ajax',
        ],
      ],
    ];
  }

  /**
   * Ricostruisce la cache per un blocco specifico.
   *
   * @param string $block_id
   *   L'ID del blocco.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect alla pagina di report.
   */
  public function rebuildCache($block_id) {
    // Carica il blocco.
    $block = $this->entityTypeManager()
      ->getStorage('block_content')
      ->load($block_id);

    if (!$block || $block->bundle() != 'omeka_map') {
      $this->messenger()->addError($this->t('Blocco non trovato o non di tipo Omeka Map.'));
      return new RedirectResponse(Url::fromRoute('italiagov_cache_warmer.report')->toString());
    }

    // Invalida la cache per questo blocco.
    Cache::invalidateTags(['omeka_map_persistent', 'omeka_map_block_' . $block_id]);

    // Ricostruisci la cache per questo blocco.
    $this->cacheWarmer->warmCacheForBlock($block);

    $this->messenger()->addStatus($this->t('Cache ricostruita per il blocco ID: @id', ['@id' => $block_id]));
    return new RedirectResponse(Url::fromRoute('italiagov_cache_warmer.report')->toString());
  }

}
