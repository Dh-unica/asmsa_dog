<?php

namespace Drupal\dog\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\dog\Service\OmekaCacheService;
use Drupal\dog\Service\OmekaGeoDataCacheService;
use Drupal\Core\Batch\BatchBuilder;

/**
 * Provides a form to manually trigger the Omeka resources cache refresh.
 */
class OmekaCacheRefreshForm extends FormBase {

  /**
   * The cache service.
   *
   * @var \Drupal\dog\Service\OmekaCacheService
   */
  protected $cacheService;
  
  /**
   * The geo cache service.
   *
   * @var \Drupal\dog\Service\OmekaGeoDataCacheService
   */
  protected $geoCacheService;

  /**
   * Constructs a new OmekaCacheRefreshForm.
   *
   * @param \Drupal\dog\Service\OmekaCacheService $cache_service
   *   The cache service.
   * @param \Drupal\dog\Service\OmekaGeoDataCacheService $geo_cache_service
   *   The geo cache service.
   */
  public function __construct(OmekaCacheService $cache_service, OmekaGeoDataCacheService $geo_cache_service) {
    $this->cacheService = $cache_service;
    $this->geoCacheService = $geo_cache_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dog.omeka_cache'),
      $container->get('dog.omeka_geo_cache')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dog_omeka_cache_refresh_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Information about the last cache update.
    $form['cache_info'] = [
      '#type' => 'details',
      '#title' => $this->t('Informazioni sulla Cache Principale Omeka'),
      '#open' => TRUE,
    ];
    
    // Recupera le statistiche della cache principale
    $stats = $this->cacheService->getCacheStatistics();
    
    // Ottieni l'ultimo orario di aggiornamento già formattato
    $last_update_time = $stats['last_update'];
    $last_update_formatted = $last_update_time ? date('Y-m-d H:i:s', $last_update_time) : $this->t('Never');
    
    // Informazioni base sulla cache
    $form['cache_info']['last_update'] = [
      '#markup' => '<div class="field"><label>' . $this->t('Ultimo aggiornamento cache') . '</label><div>' . $last_update_formatted . '</div></div>',
    ];
    
    // Statistiche degli elementi nella cache
    $form['cache_info']['stats'] = [
      '#markup' => '<div class="field"><label>' . $this->t('Statistiche Cache') . '</label>' . 
                  '<div class="statistics-container">' .
                  '<div class="statistics-item"><strong>' . $this->t('Elementi API Totali') . ':</strong> ' . $stats['total_items'] . '</div>' .
                  '<div class="statistics-item"><strong>' . $this->t('Elementi in Cache') . ':</strong> ' . $stats['cached_items'] . '</div>' .
                  '<div class="statistics-item"><strong>' . $this->t('Elementi Falliti') . ':</strong> ' . $stats['error_items'] . '</div>' .
                  '<div class="statistics-item"><strong>' . $this->t('Copertura Cache') . ':</strong> ' . 
                      ($stats['total_items'] > 0 ? round(($stats['cached_items'] / $stats['total_items']) * 100, 1) . '%' : '0%') .
                  '</div>' .
                  '</div></div>',
    ];
    
    // Aggiungi informazioni sulla cache geografica
    $form['geo_cache_info'] = [
      '#type' => 'details',
      '#title' => $this->t('Informazioni sulla Cache Geografica Omeka'),
      '#open' => TRUE,
    ];
    
    // Recupera le statistiche della cache geografica
    $geo_stats = $this->geoCacheService->getGeoDataCacheStatistics();
    
    // Ottieni l'ultimo orario di aggiornamento della cache geografica
    $geo_last_update_time = $geo_stats['last_update'];
    $geo_last_update_formatted = $geo_last_update_time ? date('Y-m-d H:i:s', $geo_last_update_time) : $this->t('Never');
    
    // Informazioni base sulla cache geografica
    $form['geo_cache_info']['last_update'] = [
      '#markup' => '<div class="field"><label>' . $this->t('Ultimo aggiornamento cache geografica') . '</label><div>' . $geo_last_update_formatted . '</div></div>',
    ];
    
    // Statistiche degli elementi nella cache geografica
    $form['geo_cache_info']['stats'] = [
      '#markup' => '<div class="field"><label>' . $this->t('Statistiche Cache Geografica') . '</label>' . 
                  '<div class="statistics-container">' .
                  '<div class="statistics-item"><strong>' . $this->t('Elementi Totali') . ':</strong> ' . $geo_stats['total_items'] . '</div>' .
                  '<div class="statistics-item"><strong>' . $this->t('Elementi Geografici in Cache') . ':</strong> ' . $geo_stats['cached_items'] . '</div>' .
                  '<div class="statistics-item"><strong>' . $this->t('Elementi Geografici Falliti') . ':</strong> ' . $geo_stats['error_items'] . '</div>' .
                  '<div class="statistics-item"><strong>' . $this->t('Copertura Cache Geografica') . ':</strong> ' . 
                      ($geo_stats['total_items'] > 0 ? round(($geo_stats['cached_items'] / $geo_stats['total_items']) * 100, 1) . '%' : '0%') .
                  '</div>' .
                  '</div></div>',
    ];

    $form['options'] = [
      '#type' => 'details',
      '#title' => $this->t('Opzioni di Aggiornamento'),
      '#open' => TRUE,
    ];

    $form['options']['batch_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Dimensione batch'),
      '#description' => $this->t('Numero di elementi da processare in ogni operazione batch.'),
      '#default_value' => 50,
      '#min' => 10,
      '#max' => 100,
      '#required' => TRUE,
    ];
    
    $form['options']['refresh_geo_data'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Aggiorna anche la cache dei dati geografici'),
      '#description' => $this->t('Se selezionato, verranno estratti e memorizzati in cache anche i dati geografici per le mappe.'),
      '#default_value' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Refresh Omeka Cache'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch_size = $form_state->getValue('batch_size');
    $refresh_geo_data = $form_state->getValue('refresh_geo_data');

    // Set up the batch process.
    $batch_builder = new BatchBuilder();
    $batch_builder
      ->setTitle($this->t('Aggiornamento Cache Omeka'))
      ->setInitMessage($this->t('Avvio aggiornamento cache...'))
      ->setProgressMessage($this->t('Elaborati @current su @total.'))
      ->setErrorMessage($this->t('Si è verificato un errore durante elaborazione'))
      ->setFinishCallback([$this, 'batchFinished']);
      
    // Primo batch: aggiornamento cache principale
    $batch_builder->addOperation([$this, 'processBatch'], [$batch_size, 'resources']);
    
    // Se richiesto, aggiungi anche aggiornamento della cache geografica
    if ($refresh_geo_data) {
      $batch_builder->addOperation([$this, 'processBatch'], [$batch_size, 'geo_data']);
    }

    batch_set($batch_builder->toArray());
  }

  /**
   * Batch operation callback.
   *
   * @param int $batch_size
   *   The batch size.
   * @param string $cache_type
   *   Il tipo di cache da aggiornare ('resources' o 'geo_data').
   * @param array $context
   *   The batch context.
   */
  public function processBatch(int $batch_size, string $cache_type, array &$context) {
    switch ($cache_type) {
      case 'resources':
        // Aggiorna la cache principale delle risorse Omeka
        $this->cacheService->updateCache($batch_size, $context);
        break;
        
      case 'geo_data':
        // Aggiorna la cache dei dati geografici
        $this->geoCacheService->updateGeoCache($batch_size, $context);
        break;
        
      default:
        // Log errore per tipo di cache sconosciuto
        \Drupal::logger('dog_omeka_cache')->error('Tipo di cache sconosciuto: @type', [
          '@type' => $cache_type,
        ]);
    }
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Whether the batch completed successfully.
   * @param array $results
   *   The batch results.
   * @param array $operations
   *   The batch operations.
   */
  public function batchFinished($success, array $results, array $operations) {
    // Verifica se c'è un errore di configurazione
    if (isset($results['configuration_error']) && $results['configuration_error']) {
      $error_message = $results['error_message'] ?? $this->t('API Omeka non configurata');
      $this->messenger()->addError($error_message);
      // Aggiungi un link al form di configurazione
      $url = \Drupal\Core\Url::fromRoute('dog.settings');
      $link = \Drupal\Core\Link::fromTextAndUrl($this->t('Configura impostazioni API Omeka'), $url)->toString();
      $this->messenger()->addWarning($this->t('Per favore @link per configurare la connessione API.', ['@link' => $link]));
      return;
    }

    if ($success) {
      // Determina il tipo di operazione completata in base all'ultimo operation processato
      $last_operation = end($operations);
      $operation_type = $last_operation[1][1] ?? 'resources'; // Default al tipo resources se non specificato
      
      // Recupera statistiche dai risultati
      $processed = $results['processed'] ?? 0;
      $errors = $results['errors'] ?? 0;
      $total_items = $results['total_items'] ?? 0;
      
      // Gestione differenziata in base al tipo di operazione
      if ($operation_type == 'geo_data') {
        // Salva le statistiche della cache geografica
        \Drupal::state()->set('dog.omeka_geo_cache.cached_items', $processed);
        \Drupal::state()->set('dog.omeka_geo_cache.error_items', $errors);
        \Drupal::state()->set('dog.omeka_geo_cache.last_update', time());
        
        // Visualizza messaggio per cache geografica
        if ($errors > 0) {
          $message = $this->t('Aggiornamento cache geografica completato con @processed elementi elaborati e @errors errori. Controlla i log per dettagli.', [
            '@processed' => $processed,
            '@errors' => $errors,
          ]);
          $this->messenger()->addWarning($message);
        }
        else {
          $message = $this->t('Aggiornamento cache geografica completato con successo. @processed elementi geografici memorizzati in cache.', [
            '@processed' => $processed,
          ]);
          $this->messenger()->addStatus($message);
        }
      } 
      else {
        // Salva le statistiche della cache principale
        \Drupal::state()->set('dog.omeka_cache.cached_items', $processed);
        \Drupal::state()->set('dog.omeka_cache.error_items', $errors);
        \Drupal::state()->set('dog.omeka_cache.last_update', time());
        
        // Visualizza messaggio per cache principale
        if ($errors > 0) {
          $message = $this->t('Aggiornamento cache risorse completato con @processed elementi elaborati e @errors errori. Controlla i log per dettagli.', [
            '@processed' => $processed,
            '@errors' => $errors,
          ]);
          $this->messenger()->addWarning($message);
        }
        else {
          $message = $this->t('Aggiornamento cache risorse completato con successo. @processed elementi memorizzati in cache.', [
            '@processed' => $processed,
          ]);
          $this->messenger()->addStatus($message);
        }
      }
    }
    else {
      $this->messenger()->addError($this->t('Si è verificato un errore durante aggiornamento della cache. Controlla i log per dettagli.'));
    }
  }

}
