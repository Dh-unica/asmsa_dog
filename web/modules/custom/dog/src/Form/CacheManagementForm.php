<?php

namespace Drupal\dog\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Batch\BatchBuilder;

/**
 * Form per la gestione della cache delle API Omeka.
 */
class CacheManagementForm extends FormBase {

  /**
   * Il servizio di cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * Il servizio OmekaResourceFetcher.
   *
   * @var \Drupal\dog\Service\OmekaResourceFetcher
   */
  protected $omekaResourceFetcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache.omeka_api'),
      $container->get('dog.omeka_resource_fetcher')
    );
  }

  /**
   * Costruttore.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Il servizio di cache.
   * @param object $omeka_resource_fetcher
   *   Il servizio OmekaResourceFetcher.
   */
  public function __construct(CacheBackendInterface $cache_backend, $omeka_resource_fetcher) {
    $this->cacheBackend = $cache_backend;
    $this->omekaResourceFetcher = $omeka_resource_fetcher;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dog_cache_management_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Ottieni statistiche sulla cache.
    $stats = $this->getCacheStats();

    $form['info'] = [
      '#type' => 'details',
      '#title' => $this->t('Informazioni sulla cache'),
      '#open' => TRUE,
    ];

    $form['info']['stats'] = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('Elementi in cache: @count', ['@count' => $stats['count']]),
        $this->t('Dimensione totale: @size', ['@size' => $this->formatBytes($stats['size'])]),
        $this->t('Ultimo aggiornamento: @time', ['@time' => $stats['last_update']]),
      ],
    ];

    $form['clear_cache'] = [
      '#type' => 'details',
      '#title' => $this->t('Pulizia della cache'),
      '#open' => TRUE,
    ];

    $form['clear_cache']['description'] = [
      '#markup' => '<p>' . $this->t('Puoi pulire tutta la cache delle API Omeka o solo parti specifiche.') . '</p>',
    ];

    $form['clear_cache']['clear_all'] = [
      '#type' => 'submit',
      '#value' => $this->t('Pulisci tutta la cache'),
      '#submit' => ['::clearAllCache'],
      '#button_type' => 'danger',
    ];

    $form['clear_cache']['clear_resources'] = [
      '#type' => 'submit',
      '#value' => $this->t('Pulisci cache risorse'),
      '#submit' => ['::clearResourcesCache'],
    ];

    $form['clear_cache']['clear_searches'] = [
      '#type' => 'submit',
      '#value' => $this->t('Pulisci cache ricerche'),
      '#submit' => ['::clearSearchesCache'],
    ];

    $form['clear_cache']['clear_item_sets'] = [
      '#type' => 'submit',
      '#value' => $this->t('Pulisci cache set di elementi'),
      '#submit' => ['::clearItemSetsCache'],
    ];

    $form['warm_cache'] = [
      '#type' => 'details',
      '#title' => $this->t('Warming della cache'),
      '#open' => TRUE,
    ];

    $form['warm_cache']['description'] = [
      '#markup' => '<p>' . $this->t('Puoi precaricare la cache con i dati delle API Omeka. Questo processo verrà eseguito in background e potrebbe richiedere del tempo.') . '</p>',
    ];

    $form['warm_cache']['warm_all'] = [
      '#type' => 'submit',
      '#value' => $this->t('Preriscalda tutta la cache'),
      '#submit' => ['::warmAllCache'],
      '#button_type' => 'primary',
    ];

    $form['warm_cache']['items_per_batch'] = [
      '#type' => 'number',
      '#title' => $this->t('Elementi per batch'),
      '#description' => $this->t('Numero di elementi da elaborare in ogni operazione batch.'),
      '#default_value' => 10,
      '#min' => 1,
      '#max' => 50,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Il form utilizza submit handlers personalizzati, quindi questo metodo non fa nulla.
  }

  /**
   * Submit handler per pulire tutta la cache.
   */
  public function clearAllCache(array &$form, FormStateInterface $form_state) {
    // Invalida tutti i tag di cache relativi alle API Omeka.
    Cache::invalidateTags([
      'omeka_api_resource',
      'omeka_api_search',
      'omeka_api_item_sets',
    ]);

    $this->messenger()->addStatus($this->t('Tutta la cache delle API Omeka è stata pulita.'));
  }

  /**
   * Submit handler per pulire la cache delle risorse.
   */
  public function clearResourcesCache(array &$form, FormStateInterface $form_state) {
    Cache::invalidateTags(['omeka_api_resource']);
    $this->messenger()->addStatus($this->t('La cache delle risorse Omeka è stata pulita.'));
  }

  /**
   * Submit handler per pulire la cache delle ricerche.
   */
  public function clearSearchesCache(array &$form, FormStateInterface $form_state) {
    Cache::invalidateTags(['omeka_api_search']);
    $this->messenger()->addStatus($this->t('La cache delle ricerche Omeka è stata pulita.'));
  }

  /**
   * Submit handler per pulire la cache dei set di elementi.
   */
  public function clearItemSetsCache(array &$form, FormStateInterface $form_state) {
    Cache::invalidateTags(['omeka_api_item_sets']);
    $this->messenger()->addStatus($this->t('La cache dei set di elementi Omeka è stata pulita.'));
  }

  /**
   * Submit handler per avviare il warming della cache.
   */
  public function warmAllCache(array &$form, FormStateInterface $form_state) {
    // Ottieni il numero di elementi per batch.
    $items_per_batch = $form_state->getValue('items_per_batch');

    // Crea un batch per il warming della cache.
    $batch_builder = new BatchBuilder();
    $batch_builder
      ->setTitle($this->t('Preriscaldamento della cache delle API Omeka'))
      ->setInitMessage($this->t('Inizializzazione del processo di preriscaldamento...'))
      ->setProgressMessage($this->t('Elaborazione in corso...'))
      ->setErrorMessage($this->t('Si è verificato un errore durante il preriscaldamento della cache.'))
      ->setFinishCallback('\Drupal\dog\Form\CacheManagementForm::batchFinished');

    // Aggiungi l'operazione per ottenere i set di elementi.
    $batch_builder->addOperation('\Drupal\dog\Form\CacheManagementForm::batchWarmItemSets', []);

    // Aggiungi l'operazione per ottenere il numero totale di elementi.
    $batch_builder->addOperation('\Drupal\dog\Form\CacheManagementForm::batchGetTotalItems', []);

    // Aggiungi l'operazione per il warming delle risorse.
    $batch_builder->addOperation('\Drupal\dog\Form\CacheManagementForm::batchWarmResources', [$items_per_batch]);

    // Avvia il batch.
    batch_set($batch_builder->toArray());
  }

  /**
   * Batch operation per il warming dei set di elementi.
   */
  public static function batchWarmItemSets(&$context) {
    $omeka_resource_fetcher = \Drupal::service('dog.omeka_resource_fetcher');
    
    // Ottieni i set di elementi (questo li metterà in cache).
    $item_sets = $omeka_resource_fetcher->getItemSets();
    
    // Imposta i risultati.
    $context['results']['item_sets_count'] = count($item_sets);
    $context['message'] = t('Preriscaldati @count set di elementi.', ['@count' => count($item_sets)]);
  }

  /**
   * Batch operation per ottenere il numero totale di elementi.
   */
  public static function batchGetTotalItems(&$context) {
    $omeka_resource_fetcher = \Drupal::service('dog.omeka_resource_fetcher');
    
    // Ottieni il numero totale di elementi.
    $total_results = 0;
    $omeka_resource_fetcher->search('items', [], 0, 1, $total_results);
    
    // Imposta i risultati.
    $context['results']['total_items'] = $total_results;
    $context['message'] = t('Trovati @count elementi totali.', ['@count' => $total_results]);
  }

  /**
   * Batch operation per il warming delle risorse.
   */
  public static function batchWarmResources($items_per_batch, &$context) {
    $omeka_resource_fetcher = \Drupal::service('dog.omeka_resource_fetcher');
    
    // Inizializza il sandbox se non esiste.
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_page'] = 0;
      $context['sandbox']['max'] = $context['results']['total_items'];
    }
    
    // Ottieni gli elementi per la pagina corrente.
    $total_results = 0;
    $items = $omeka_resource_fetcher->search('items', [], $context['sandbox']['current_page'], $items_per_batch, $total_results);
    
    // Elabora ogni elemento.
    foreach ($items as $item) {
      // Ottieni i dettagli dell'elemento (questo lo metterà in cache).
      $omeka_resource_fetcher->retrieveResource($item['id'], $item['type']);
      
      // Aggiorna il progresso.
      $context['sandbox']['progress']++;
      $context['message'] = t('Preriscaldato elemento @current di @total', [
        '@current' => $context['sandbox']['progress'],
        '@total' => $context['sandbox']['max'],
      ]);
    }
    
    // Aggiorna la pagina corrente.
    $context['sandbox']['current_page']++;
    
    // Verifica se il batch è completo.
    if ($context['sandbox']['progress'] >= $context['sandbox']['max']) {
      $context['finished'] = 1;
    }
    else {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Batch finished callback.
   */
  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      $message = t('Preriscaldamento della cache completato con successo. Elaborati @item_sets set di elementi e @items elementi.', [
        '@item_sets' => $results['item_sets_count'],
        '@items' => $results['total_items'],
      ]);
      \Drupal::messenger()->addStatus($message);
    }
    else {
      $message = t('Si è verificato un errore durante il preriscaldamento della cache.');
      \Drupal::messenger()->addError($message);
    }
  }

  /**
   * Ottieni statistiche sulla cache.
   *
   * @return array
   *   Array con le statistiche sulla cache.
   */
  protected function getCacheStats() {
    // Esegui una query per ottenere le statistiche sulla cache.
    $query = \Drupal::database()->select('cache_omeka_api', 'c');
    $query->addExpression('COUNT(*)', 'count');
    $query->addExpression('SUM(LENGTH(data))', 'size');
    $query->addExpression('MAX(created)', 'last_update');
    $result = $query->execute()->fetchAssoc();
    
    // Formatta i risultati.
    return [
      'count' => $result['count'] ?? 0,
      'size' => $result['size'] ?? 0,
      'last_update' => $result['last_update'] ? date('d/m/Y H:i:s', $result['last_update']) : $this->t('Mai'),
    ];
  }

  /**
   * Formatta i byte in una stringa leggibile.
   *
   * @param int $bytes
   *   Numero di byte.
   *
   * @return string
   *   Stringa formattata.
   */
  protected function formatBytes($bytes) {
    if ($bytes <= 0) {
      return '0 B';
    }
    
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $base = 1024;
    $i = floor(log($bytes, $base));
    
    return number_format($bytes / pow($base, $i), 2) . ' ' . $units[$i];
  }

}
