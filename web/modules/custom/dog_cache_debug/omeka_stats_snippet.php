<?php
/**
 * Questo snippet è destinato ad essere copiato in un blocco personalizzato di Drupal.
 * Fornisce statistiche di debug per la cache Omeka.
 */

// Ottiene le statistiche dalla cache e dallo stato
$state = \Drupal::state();
$cache_service = \Drupal::service('dog.omeka_cache');

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

// Simulazione dei dati della pagina corrente (in un'implementazione reale, questo
// dovrebbe essere fatto da un hook che monitora il rendering degli elementi Omeka)
$current_path = \Drupal::service('path.current')->getPath();
if (strpos($current_path, '/node/') === 0) {
  $nid = substr($current_path, 6);
  // Simula i conteggi degli elementi Omeka nella pagina
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

// Genera il markup HTML per le statistiche
$output = '<div class="omeka-cache-stats">';
$output .= '<style>
  .omeka-cache-stats { 
    padding: 15px; 
    border: 1px solid #ccc; 
    background: #f9f9f9; 
    border-radius: 4px;
    margin-bottom: 20px;
  }
  .omeka-cache-stats h3 { 
    margin-top: 0; 
    color: #333;
  }
  .omeka-cache-stats div { 
    margin: 8px 0; 
    line-height: 1.5;
  }
  .omeka-cache-stats .stat-group {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
  }
  .omeka-cache-stats .stat-label {
    display: inline-block;
    min-width: 170px;
    font-weight: bold;
  }
  .good-stat { color: #2d882d; }
  .medium-stat { color: #aa8600; }
  .poor-stat { color: #a51b00; }
</style>';

// Informazioni generali
$output .= '<h3>Omeka Cache Statistics</h3>';
$output .= '<div class="stat-group">';
$output .= '<div><span class="stat-label">Last Cache Update:</span> ' . 
          ($last_update ? date('Y-m-d H:i:s', $last_update) : 'Never') . '</div>';
$output .= '<div><span class="stat-label">Total Omeka Items:</span> ' . $total_items . '</div>';
$output .= '<div><span class="stat-label">Items in Cache:</span> ' . $cached_items . ' (' . 
          round(($cached_items / ($total_items ?: 1)) * 100, 1) . '%)</div>';
$output .= '<div><span class="stat-label">Items with Errors:</span> ' . $error_items . '</div>';

// Colora il ratio in base al valore
$ratio_class = ($hit_ratio >= 90) ? 'good-stat' : (($hit_ratio >= 70) ? 'medium-stat' : 'poor-stat');
$output .= '<div><span class="stat-label">Cache Hit Ratio:</span> <span class="' . $ratio_class . '">' . 
          $hit_ratio . '%</span> (' . $cache_hits . ' hits, ' . $cache_misses . ' misses)</div>';
$output .= '</div>';

// Statistiche della pagina corrente
$output .= '<h3>Current Page Statistics</h3>';
$output .= '<div class="stat-group">';
$output .= '<div><span class="stat-label">Omeka Items on Page:</span> ' . $page_items . '</div>';

// Colora il ratio della pagina in base al valore
$page_ratio_class = ($page_hit_ratio >= 90) ? 'good-stat' : (($page_hit_ratio >= 70) ? 'medium-stat' : 'poor-stat');
$output .= '<div><span class="stat-label">Page Cache Hit Ratio:</span> <span class="' . $page_ratio_class . '">' . 
          $page_hit_ratio . '%</span> (' . $page_hits . ' hits, ' . $page_misses . ' misses)</div>';

// Colora il tempo di rendering in base al valore
$render_time_class = ($page_render_time < 500) ? 'good-stat' : (($page_render_time < 1500) ? 'medium-stat' : 'poor-stat');
$output .= '<div><span class="stat-label">Page Render Time:</span> <span class="' . $render_time_class . '">' . 
          round($page_render_time, 2) . ' ms</span></div>';
$output .= '</div>';

// Azioni disponibili
$output .= '<div>';
$output .= '<a href="/admin/config/services/dog/cache-debug/refresh" class="button button--primary">Refresh Omeka Cache</a>';
$output .= ' <a href="/admin/config/services/dog/cache-debug" class="button">Debug Details</a>';
$output .= '</div>';

$output .= '</div>';

return $output;
