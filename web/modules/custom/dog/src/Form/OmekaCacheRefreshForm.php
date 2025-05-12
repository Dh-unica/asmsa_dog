<?php

namespace Drupal\dog\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\dog\Service\OmekaCacheService;
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
   * Constructs a new OmekaCacheRefreshForm.
   *
   * @param \Drupal\dog\Service\OmekaCacheService $cache_service
   *   The cache service.
   */
  public function __construct(OmekaCacheService $cache_service) {
    $this->cacheService = $cache_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dog.omeka_cache')
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
      '#title' => $this->t('Cache Information'),
      '#open' => TRUE,
    ];
    
    // Recupera le statistiche della cache
    $stats = $this->cacheService->getCacheStatistics();
    
    // Ottieni l'ultimo orario di aggiornamento già formattato
    $last_update_time = $stats['last_update'];
    $last_update_formatted = $last_update_time ? date('Y-m-d H:i:s', $last_update_time) : $this->t('Never');
    
    // Informazioni base sulla cache
    $form['cache_info']['last_update'] = [
      '#markup' => '<div class="field"><label>' . $this->t('Last cache update') . '</label><div>' . $last_update_formatted . '</div></div>',
    ];
    
    // Statistiche degli elementi nella cache
    $form['cache_info']['stats'] = [
      '#markup' => '<div class="field"><label>' . $this->t('Cache Statistics') . '</label>' . 
                  '<div class="statistics-container">' .
                  '<div class="statistics-item"><strong>' . $this->t('Total API Items') . ':</strong> ' . $stats['total_items'] . '</div>' .
                  '<div class="statistics-item"><strong>' . $this->t('Cached Items') . ':</strong> ' . $stats['cached_items'] . '</div>' .
                  '<div class="statistics-item"><strong>' . $this->t('Failed Items') . ':</strong> ' . $stats['error_items'] . '</div>' .
                  '<div class="statistics-item"><strong>' . $this->t('Cache Coverage') . ':</strong> ' . 
                      ($stats['total_items'] > 0 ? round(($stats['cached_items'] / $stats['total_items']) * 100, 1) . '%' : '0%') .
                  '</div>' .
                  '</div></div>',
    ];

    $form['options'] = [
      '#type' => 'details',
      '#title' => $this->t('Refresh Options'),
      '#open' => TRUE,
    ];

    $form['options']['batch_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Batch size'),
      '#description' => $this->t('Number of items to process in each batch operation.'),
      '#default_value' => 50,
      '#min' => 10,
      '#max' => 100,
      '#required' => TRUE,
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

    // Set up the batch process.
    $batch_builder = new BatchBuilder();
    $batch_builder
      ->setTitle($this->t('Refreshing Omeka Resources Cache'))
      ->setInitMessage($this->t('Starting cache refresh...'))
      ->setProgressMessage($this->t('Processed @current out of @total.'))
      ->setErrorMessage($this->t('An error occurred during processing'))
      ->setFinishCallback([$this, 'batchFinished'])
      ->addOperation([$this, 'processBatch'], [$batch_size]);

    batch_set($batch_builder->toArray());
  }

  /**
   * Batch operation callback.
   *
   * @param int $batch_size
   *   The batch size.
   * @param array $context
   *   The batch context.
   */
  public function processBatch(int $batch_size, array &$context) {
    $this->cacheService->updateCache($batch_size, $context);
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
      $link = \Drupal\Core\Link::fromTextAndUrl($this->t('Configure Omeka API settings'), $url)->toString();
      $this->messenger()->addWarning($this->t('Please @link to set up the API connection.', ['@link' => $link]));
      return;
    }

    if ($success) {
      $processed = $results['processed'] ?? 0;
      $errors = $results['errors'] ?? 0;
      $total_items = $results['total_items'] ?? 0;
      
      // Salva le statistiche nel sistema state
      \Drupal::state()->set('dog.omeka_cache.cached_items', $processed);
      \Drupal::state()->set('dog.omeka_cache.error_items', $errors);
      
      // Visualizza il messaggio appropriato
      if ($errors > 0) {
        $message = $this->t('Cache refresh completed with @processed items processed and @errors errors. See logs for details.', [
          '@processed' => $processed,
          '@errors' => $errors,
        ]);
        $this->messenger()->addWarning($message);
      }
      else {
        $message = $this->t('Cache refresh completed successfully with @processed items processed.', [
          '@processed' => $processed,
        ]);
        $this->messenger()->addStatus($message);
      }
    }
    else {
      $this->messenger()->addError($this->t('An error occurred while refreshing the cache. See logs for details.'));
    }
  }

}
