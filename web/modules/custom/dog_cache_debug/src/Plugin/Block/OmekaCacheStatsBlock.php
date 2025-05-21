<?php

namespace Drupal\dog_cache_debug\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block with Omeka cache statistics.
 *
 * @Block(
 *   id = "omeka_cache_stats_block",
 *   admin_label = @Translation("Omeka Cache Statistics"),
 *   category = @Translation("Diagnostics")
 * )
 */
class OmekaCacheStatsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a new OmekaCacheStatsBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, StateInterface $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    
    // Recupera le statistiche sulla cache
    $last_update = $this->state->get('dog.omeka_cache.last_update', 0);
    $total_items = $this->state->get('dog.omeka_cache.total_items', 0);
    $cached_items = $this->state->get('dog.omeka_cache.cached_items', 0);
    $error_items = $this->state->get('dog.omeka_cache.error_items', 0);
    
    // Recupera altre metriche di performance se disponibili
    $cache_hits = $this->state->get('dog.omeka_cache.hits', 0);
    $cache_misses = $this->state->get('dog.omeka_cache.misses', 0);
    $render_time = $this->state->get('dog.omeka_cache.render_time', 0);
    
    // Incrementa le statistiche su questa pagina
    $this->updatePageStatistics();
    
    // Recupera statistiche relative alla pagina corrente
    $page_items = $this->state->get('dog.omeka_cache.page_items', 0);
    $page_hits = $this->state->get('dog.omeka_cache.page_hits', 0);
    $page_misses = $this->state->get('dog.omeka_cache.page_misses', 0);
    $page_render_time = $this->state->get('dog.omeka_cache.page_render_time', 0);
    
    // Statistiche del modulo Dog
    $build['stats'] = [
      '#type' => 'details',
      '#title' => $this->t('Omeka Cache Statistics'),
      '#open' => TRUE,
    ];
    
    $build['stats']['last_update'] = [
      '#markup' => '<div><strong>' . $this->t('Last Cache Update') . ':</strong> ' . 
                  ($last_update ? date('Y-m-d H:i:s', $last_update) : $this->t('Never')) . '</div>',
    ];
    
    $build['stats']['items'] = [
      '#markup' => '<div><strong>' . $this->t('Total Omeka Items') . ':</strong> ' . $total_items . '</div>',
    ];
    
    $build['stats']['cached'] = [
      '#markup' => '<div><strong>' . $this->t('Items in Cache') . ':</strong> ' . $cached_items . '</div>',
    ];
    
    $build['stats']['errors'] = [
      '#markup' => '<div><strong>' . $this->t('Items with Errors') . ':</strong> ' . $error_items . '</div>',
    ];
    
    $hit_ratio = ($cache_hits + $cache_misses > 0) ? 
                 round(($cache_hits / ($cache_hits + $cache_misses)) * 100, 2) : 0;
    
    $build['stats']['cache_performance'] = [
      '#markup' => '<div><strong>' . $this->t('Cache Hit Ratio') . ':</strong> ' . 
                  $hit_ratio . '% (' . $cache_hits . ' hits, ' . $cache_misses . ' misses)</div>',
    ];
    
    // Statistiche sulla pagina corrente
    $build['page_stats'] = [
      '#type' => 'details',
      '#title' => $this->t('Current Page Statistics'),
      '#open' => TRUE,
    ];
    
    $build['page_stats']['page_items'] = [
      '#markup' => '<div><strong>' . $this->t('Omeka Items on Page') . ':</strong> ' . $page_items . '</div>',
    ];
    
    $page_hit_ratio = ($page_hits + $page_misses > 0) ? 
                      round(($page_hits / ($page_hits + $page_misses)) * 100, 2) : 0;
    
    $build['page_stats']['page_cache'] = [
      '#markup' => '<div><strong>' . $this->t('Page Cache Hit Ratio') . ':</strong> ' . 
                  $page_hit_ratio . '% (' . $page_hits . ' hits, ' . $page_misses . ' misses)</div>',
    ];
    
    $build['page_stats']['page_render'] = [
      '#markup' => '<div><strong>' . $this->t('Page Render Time') . ':</strong> ' . 
                  round($page_render_time, 2) . ' ms</div>',
    ];
    
    // Aggiungi un pulsante per ricaricare la cache
    $build['actions'] = [
      '#type' => 'details',
      '#title' => $this->t('Actions'),
      '#open' => TRUE,
    ];
    
    $build['actions']['refresh'] = [
      '#type' => 'link',
      '#title' => $this->t('Refresh Omeka Cache'),
      '#url' => \Drupal\Core\Url::fromRoute('dog_cache_debug.refresh_cache'),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];
    
    // Aggiungi CSS
    $build['#attached']['library'][] = 'dog_cache_debug/debug_styles';
    
    return $build;
  }
  
  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // Non cachare questo blocco
    return 0;
  }
  
  /**
   * Aggiorna le statistiche relative alla pagina corrente.
   */
  protected function updatePageStatistics() {
    // Normalmente questo verrebbe fatto tramite un hook di page_build o altro meccanismo
    // Per ora impostiamo alcuni valori statici per test
    
    // In una implementazione reale, dovremmo:
    // 1. Contare gli elementi Omeka renderizzati
    // 2. Tracciare hit/miss della cache
    // 3. Misurare il tempo di rendering
    
    // Resetta statistiche pagina a ogni ricarica
    $this->state->set('dog.omeka_cache.page_items', 0);
    $this->state->set('dog.omeka_cache.page_hits', 0);
    $this->state->set('dog.omeka_cache.page_misses', 0); 
    $this->state->set('dog.omeka_cache.page_render_time', 0);
    
    // Recupera gli elementi Omeka nella pagina - implementazione simulata
    // Dovrebbe essere implementata con un hook che intercetta il rendering degli elementi Omeka
    $current_path = \Drupal::service('path.current')->getPath();
    
    // Simula una pagina con molti elementi Omeka
    if (strpos($current_path, '/node/') === 0) {
      $nid = substr($current_path, 6);
      
      // Simula conteggio elementi basato su nid
      $this->state->set('dog.omeka_cache.page_items', 100 + ($nid % 50));
      
      // Simula hit/miss ratio
      $this->state->set('dog.omeka_cache.page_hits', 90 + ($nid % 10));
      $this->state->set('dog.omeka_cache.page_misses', ($nid % 5));
      
      // Simula tempo rendering (ms)
      $this->state->set('dog.omeka_cache.page_render_time', 1200 + ($nid * 10));
    }
  }
}
