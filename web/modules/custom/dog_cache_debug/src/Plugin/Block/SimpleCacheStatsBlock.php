<?php

namespace Drupal\dog_cache_debug\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a simple block with Omeka cache statistics.
 *
 * @Block(
 *   id = "simple_omeka_cache_stats",
 *   admin_label = @Translation("Simple Omeka Cache Stats"),
 *   category = @Translation("ASMSA")
 * )
 */
class SimpleCacheStatsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $cache = \Drupal::service('dog.omeka_cache');
    $state = \Drupal::state();
    
    // Statistiche di base
    $total_items = $state->get('dog.omeka_cache.total_items', 0);
    $cached_items = $state->get('dog.omeka_cache.cached_items', 0);
    $last_update = $state->get('dog.omeka_cache.last_update', 0);
    $cache_hits = $state->get('dog.omeka_cache.hits', 0);
    $cache_misses = $state->get('dog.omeka_cache.misses', 0);
    
    // Contenuto HTML semplice
    $content = '<div class="omeka-cache-stats">';
    $content .= '<h3>' . $this->t('Omeka Cache Statistics') . '</h3>';
    $content .= '<div><strong>' . $this->t('Last Update') . ':</strong> ' . 
               ($last_update ? date('Y-m-d H:i:s', $last_update) : $this->t('Never')) . '</div>';
    $content .= '<div><strong>' . $this->t('Total Items') . ':</strong> ' . $total_items . '</div>';
    $content .= '<div><strong>' . $this->t('Cached Items') . ':</strong> ' . $cached_items . '</div>';
    
    // Hit ratio
    $hit_ratio = ($cache_hits + $cache_misses > 0) ? 
                 round(($cache_hits / ($cache_hits + $cache_misses)) * 100, 2) : 0;
    
    $content .= '<div><strong>' . $this->t('Cache Hit Ratio') . ':</strong> ' . 
                $hit_ratio . '% (' . $cache_hits . ' hits, ' . $cache_misses . ' misses)</div>';
                
    // Ottieni altre metriche di performance
    $bin = 'omeka_resources';
    try {
      $stats = \Drupal::cache($bin)->getMemoryInfo();
      if ($stats) {
        $content .= '<div><strong>' . $this->t('Cache Memory Usage') . ':</strong> ' . 
                    round($stats['memory'] / 1024 / 1024, 2) . ' MB</div>';
      }
    } 
    catch (\Exception $e) {
      // Ignora errori nella memoria
    }
    
    // Ottieni informazioni sulla cache corrente della pagina
    $content .= '<h4>' . $this->t('Current Page') . '</h4>';
    $content .= '<div id="page-omeka-items">---</div>';
    $content .= '<div id="page-cache-hits">---</div>';
    $content .= '<div id="page-render-time">---</div>';
    
    $content .= '</div>';
    
    // Aggiungi stili CSS inline
    $content .= '<style>
      .omeka-cache-stats { padding: 15px; border: 1px solid #ccc; background: #f5f5f5; }
      .omeka-cache-stats h3 { margin-top: 0; }
      .omeka-cache-stats div { margin: 8px 0; }
    </style>';
    
    return [
      '#markup' => $content,
      '#cache' => [
        'max-age' => 0, // Non cachare questo blocco
      ],
    ];
  }

}
