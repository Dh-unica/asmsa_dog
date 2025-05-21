<?php

namespace Drupal\dog_cache_debug\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller per il debug della cache Omeka.
 */
class CacheDebugController extends ControllerBase {

  /**
   * Pagina principale di debug.
   */
  public function debugPage() {
    $build = [];
    
    // Definizione dei contenuti usando esclusivamente markup HTML
    $form_html = '<div class="cache-debug-container">';
    $form_html .= '<p>' . $this->t('Questo strumento permette di verificare se una specifica risorsa Omeka è presente nella cache e con quale chiave.') . '</p>';
    
    // Form semplice con HTML diretto
    $form_html .= '<div class="form-item js-form-item form-wrapper">';
    $form_html .= '<label for="cache-debug-id">' . $this->t('ID risorsa') . '</label>';
    $form_html .= '<input type="text" id="cache-debug-id" name="id" class="form-text" />';
    $form_html .= '</div>';
    
    $form_html .= '<div class="form-item js-form-item form-wrapper">';
    $form_html .= '<label for="cache-debug-type">' . $this->t('Tipo risorsa') . '</label>';
    $form_html .= '<select id="cache-debug-type" name="type" class="form-select">';
    $form_html .= '<option value="items">' . $this->t('Items') . '</option>';
    $form_html .= '<option value="item_sets">' . $this->t('Item Sets') . '</option>';
    $form_html .= '<option value="media">' . $this->t('Media') . '</option>';
    $form_html .= '</select>';
    $form_html .= '</div>';
    
    $form_html .= '<div class="form-actions js-form-wrapper form-wrapper">';
    $form_html .= '<button onclick="checkCache(); return false;" class="button button--primary js-form-submit form-submit">' . $this->t('Verifica') . '</button>';
    $form_html .= '</div>';
    
    $form_html .= '</div>';
    
    $build['form'] = ['#markup' => $form_html];
    
    // Div per i risultati.
    $build['results'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'cache-results',
        'class' => ['cache-debug-results'],
      ],
    ];
    
    // Aggiungi JavaScript per gestire la chiamata AJAX.
    $build['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $build['#attached']['html_head'][] = [
      [
        '#type' => 'html_tag',
        '#tag' => 'script',
        '#value' => "
          function checkCache() {
            var id = document.querySelector('input[name=\"id\"]').value;
            var type = document.querySelector('select[name=\"type\"]').value;
            
            if (!id) {
              alert('Inserisci un ID valido');
              return;
            }
            
            var url = '/admin/config/services/dog/cache-debug/api/' + id + '/' + type;
            fetch(url)
              .then(response => response.json())
              .then(data => {
                var resultsDiv = document.getElementById('cache-results');
                var html = '<h3>Risultati verifica cache</h3>';
                
                // Statistiche generali
                html += '<div class=\"cache-stats\"><h4>Statistiche Cache</h4>';
                html += '<p>Ultimo aggiornamento: ' + data.cache_statistics.last_update_formatted + '</p>';
                html += '<p>Elementi totali: ' + data.cache_statistics.total_items + '</p>';
                html += '<p>Elementi in cache: ' + data.cache_statistics.cached_items + '</p>';
                html += '</div>';
                
                // Risultati per bin
                html += '<div class=\"cache-bins\"><h4>Verifica nei bin di cache</h4>';
                
                for (var binName in data.bins) {
                  html += '<div class=\"cache-bin\">';
                  html += '<h5>Bin: ' + binName + '</h5>';
                  
                  if (data.bins[binName].resource_found) {
                    html += '<p class=\"success\">✅ Risorsa ' + id + ' trovata con chiave ' + data.bins[binName].cache_key_checked + '</p>';
                  } else {
                    html += '<p class=\"error\">❌ Risorsa ' + id + ' NON trovata con chiave ' + data.bins[binName].cache_key_checked + '</p>';
                    
                    // Verifica chiavi alternative
                    if (data.bins[binName].found_with_alternative_key) {
                      html += '<p class=\"warning\">⚠️ Risorsa trovata con chiave alternativa: ' + data.bins[binName].found_with_alternative_key + '</p>';
                    } else {
                      html += '<p class=\"info\">ℹ️ Chiavi alternative verificate: ' + data.bins[binName].alternative_keys_checked.join(', ') + '</p>';
                    }
                  }
                  
                  html += '</div>';
                }
                
                html += '</div>';
                
                // Eventuali errori
                if (data.errors && data.errors.length > 0) {
                  html += '<div class=\"cache-errors\"><h4>Errori</h4>';
                  data.errors.forEach(function(error) {
                    html += '<p class=\"error\">' + error + '</p>';
                  });
                  html += '</div>';
                }
                
                resultsDiv.innerHTML = html;
              })
              .catch(error => {
                console.error('Errore:', error);
                document.getElementById('cache-results').innerHTML = '<p class=\"error\">Si è verificato un errore durante la verifica della cache.</p>';
              });
          }
        ",
      ],
      'cache-debug-js',
    ];
    
    // Aggiungi un po' di stile CSS.
    $build['#attached']['html_head'][] = [
      [
        '#type' => 'html_tag',
        '#tag' => 'style',
        '#value' => "
          .cache-debug-results { margin-top: 20px; padding: 15px; border: 1px solid #ddd; }
          .cache-bin { margin-bottom: 15px; padding: 10px; border: 1px solid #eee; }
          .success { color: green; }
          .error { color: red; }
          .warning { color: orange; }
          .info { color: blue; }
        ",
      ],
      'cache-debug-css',
    ];
    
    return $build;
  }
  
  /**
   * Endpoint API per ottenere informazioni sulla cache in formato JSON.
   */
  public function apiResponse($id = NULL, $type = 'items') {
    // Verifico se la funzione di debug esiste.
    if (function_exists('dog_cache_debug_check_status')) {
      $result = dog_cache_debug_check_status($id, $type, TRUE, FALSE);
    }
    else {
      $result = [
        'error' => 'Funzione dog_cache_debug_check_status non disponibile',
      ];
    }
    
    return new JsonResponse($result);
  }

  /**
   * Gestisce la richiesta di refresh della cache Omeka.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   Una redirezione alla pagina di debug dopo il refresh.
   */
  public function refreshCache() {
    try {
      // Verifica se il servizio omeka_cache esiste
      $omeka_cache = \Drupal::service('dog.omeka_cache');
      
      // Esegui l'aggiornamento della cache
      $batch_size = 50;
      $context = [];
      
      // Inizializza il batch
      $omeka_cache->updateCache($batch_size, $context);
      
      // Aggiungi un messaggio di conferma
      $this->messenger()->addStatus($this->t('Omeka cache refresh started. This process may take some time to complete.'));
      
      // Aggiungi dettagli sul processo in corso
      if (isset($context['sandbox']) && isset($context['sandbox']['total'])) {
        $progress = isset($context['finished']) ? round($context['finished'] * 100) : 0;
        $this->messenger()->addStatus(
          $this->t('Cache refresh in progress: @progress% (@current/@total items).',
          [
            '@progress' => $progress,
            '@current' => $context['sandbox']['progress'] ?? 0,
            '@total' => $context['sandbox']['total'],
          ])
        );
      }
    }
    catch (\Exception $e) {
      // In caso di errore, mostra un messaggio
      $this->messenger()->addError(
        $this->t('Error while refreshing Omeka cache: @error',
        ['@error' => $e->getMessage()])
      );
    }
    
    // Redirect alla pagina di debug
    return $this->redirect('dog_cache_debug.debug');
  }
}
