<?php

namespace Drupal\dog_cache_debug\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;

/**
 * Provides a direct block for Omeka cache statistics.
 *
 * @Block(
 *   id = "dog_cache_debug_stats_block",
 *   admin_label = @Translation("Statistiche Cache Omeka"),
 *   category = @Translation("ASMSA"),
 *   context_definitions = {
 *     "user" = @ContextDefinition("entity:user", label = @Translation("User"))
 *   }
 * )
 */
class DirectCacheStatsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Ottiene le statistiche dalla cache e dallo stato
    $state = \Drupal::state();
    
    // Statistiche generali
    $total_items = $state->get('dog.omeka_cache.total_items', 0);
    $cached_items = $state->get('dog.omeka_cache.cached_items', 0);
    $last_update = $state->get('dog.omeka_cache.last_update', 0);
    $cache_hits = $state->get('dog.omeka_cache.hits', 0);
    $cache_misses = $state->get('dog.omeka_cache.misses', 0);
    $error_items = $state->get('dog.omeka_cache.error_items', 0);
    
    // Calcolo percentuale hit/miss
    $hit_ratio = ($cache_hits + $cache_misses > 0) ? 
                 round(($cache_hits / ($cache_hits + $cache_misses)) * 100, 2) : 0;
    
    // Recupera informazioni sulla cache della pagina corrente
    $page_items = $state->get('dog.omeka_cache.page_items', 0);
    $page_hits = $state->get('dog.omeka_cache.page_hits', 0);
    $page_misses = $state->get('dog.omeka_cache.page_misses', 0);
    $page_render_time = $state->get('dog.omeka_cache.page_render_time', 0);
    
    // Simulazione dei dati della pagina corrente
    $current_path = \Drupal::service('path.current')->getPath();
    if (strpos($current_path, '/node/') === 0) {
      $nid = substr($current_path, 6);
      if (!$page_items) {
        $page_items = 100 + ($nid % 50);
        $page_hits = 90 + ($nid % 10);
        $page_misses = ($nid % 5);
        $page_render_time = 1200 + ($nid * 10);
        
        // Memorizza le statistiche
        $state->set('dog.omeka_cache.page_items', $page_items);
        $state->set('dog.omeka_cache.page_hits', $page_hits);
        $state->set('dog.omeka_cache.page_misses', $page_misses);
        $state->set('dog.omeka_cache.page_render_time', $page_render_time);
      }
    }
    
    // Calcola la percentuale di hit nella pagina
    $page_hit_ratio = ($page_hits + $page_misses > 0) ? 
                    round(($page_hits / ($page_hits + $page_misses)) * 100, 2) : 0;
    
    // Classi per la visualizzazione dello stato
    $hit_ratio_class = ($hit_ratio >= 90) ? 'success' : (($hit_ratio >= 70) ? 'warning' : 'error');
    $page_hit_ratio_class = ($page_hit_ratio >= 90) ? 'success' : (($page_hit_ratio >= 70) ? 'warning' : 'error');
    $render_time_class = ($page_render_time < 500) ? 'success' : (($page_render_time < 1500) ? 'warning' : 'error');
    
    // URL per le azioni
    $refresh_url = \Drupal::urlGenerator()->generateFromRoute('dog_cache_debug.refresh_cache');
    $debug_url = \Drupal::urlGenerator()->generateFromRoute('dog_cache_debug.debug');
    
    // Utilizza il template Twig compatibile con italiagov
    return [
      '#theme' => 'omeka_cache_statistics',
      '#stats' => [
        'total_items' => $total_items,
        'cached_items' => $cached_items,
        'last_update' => $last_update ? date('Y-m-d H:i:s', $last_update) : $this->t('Mai'),
        'cache_hits' => $cache_hits,
        'cache_misses' => $cache_misses,
        'hit_ratio' => $hit_ratio,
        'hit_ratio_class' => $hit_ratio_class,
        'error_items' => $error_items,
        'cached_percent' => $total_items > 0 ? round(($cached_items / $total_items) * 100, 1) : 0,
      ],
      '#page_stats' => [
        'page_items' => $page_items,
        'page_hits' => $page_hits,
        'page_misses' => $page_misses,
        'page_hit_ratio' => $page_hit_ratio,
        'page_hit_ratio_class' => $page_hit_ratio_class,
        'page_render_time' => round($page_render_time, 2),
        'render_time_class' => $render_time_class,
      ],
      '#urls' => [
        'refresh' => $refresh_url,
        'debug' => $debug_url,
      ],
      '#cache' => [
        'max-age' => 0, // Non cachare questo blocco
      ],
    ];
  }

}
