<?php

namespace Drupal\dog\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\dog\Service\OmekaCacheService;
use Drupal\dog\Service\OmekaGeoDataCacheService;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;

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
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a new OmekaCacheRefreshForm.
   *
   * @param \Drupal\dog\Service\OmekaCacheService $cache_service
   *   The cache service.
   * @param \Drupal\dog\Service\OmekaGeoDataCacheService $geo_cache_service
   *   The geo cache service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(OmekaCacheService $cache_service, OmekaGeoDataCacheService $geo_cache_service, ConfigFactoryInterface $config_factory, StateInterface $state) {
    $this->cacheService = $cache_service;
    $this->geoCacheService = $geo_cache_service;
    $this->configFactory = $config_factory;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dog.omeka_cache'),
      $container->get('dog.omeka_geo_cache'),
      $container->get('config.factory'),
      $container->get('state')
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
    // Header: URL Base API
    $base_url = $this->configFactory->get('dog.settings')->get('base_url') ?? 'Non configurato';
    $form['api_info'] = [
      '#type' => 'item',
      '#title' => $this->t('URL API remota'),
      '#markup' => '<strong>' . $base_url . '</strong>',
      '#description' => $this->t('URL base utilizzato per comporre le chiamate API.'),
    ];

    // SEZIONE 1: Cache Items
    $items_stats = $this->getItemsCacheStatistics();
    $form['items_cache'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cache Items'),
      '#description' => $this->t('API Source: /api/items/ | DB Table: cache_omeka_resources'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['items_cache']['stats'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['cache-stats']],
    ];

    $form['items_cache']['stats']['api_total'] = [
      '#type' => 'item',
      '#title' => $this->t('Elementi totali da API'),
      '#markup' => '<strong>' . $items_stats['api_total'] . '</strong>',
      '#description' => $this->t('Numero totale di items disponibili nell\'API remota.'),
    ];

    $form['items_cache']['stats']['cached_count'] = [
      '#type' => 'item',
      '#title' => $this->t('Elementi presenti in cache'),
      '#markup' => '<strong>' . $items_stats['cached_count'] . '</strong>',
      '#description' => $this->t('Numero di items attualmente memorizzati in cache locale.'),
    ];

    $form['items_cache']['stats']['coverage'] = [
      '#type' => 'item',
      '#title' => $this->t('Copertura cache'),
      '#markup' => '<strong>' . $items_stats['coverage'] . '%</strong>',
      '#description' => $this->t('Percentuale di items API memorizzati in cache.'),
    ];

    $form['items_cache']['stats']['last_update'] = [
      '#type' => 'item',
      '#title' => $this->t('Ultimo aggiornamento'),
      '#markup' => '<strong>' . $items_stats['last_update'] . '</strong>',
      '#description' => $this->t('Data dell\'ultimo aggiornamento della cache.'),
    ];

    $form['items_cache']['stats']['errors'] = [
      '#type' => 'item',
      '#title' => $this->t('Errori'),
      '#markup' => '<strong>' . $items_stats['errors'] . '</strong>',
      '#description' => $this->t('Numero di elementi che hanno generato errori.'),
    ];

    // Bottoni Items
    $form['items_cache']['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-actions']],
    ];

    $form['items_cache']['actions']['refresh_items'] = [
      '#type' => 'submit',
      '#value' => $this->t('Refresh Items Cache'),
      '#submit' => ['::refreshItemsCache'],
      '#button_type' => 'primary',
    ];

    $form['items_cache']['actions']['test_items_api'] = [
      '#type' => 'submit',
      '#value' => $this->t('Test Items API'),
      '#submit' => ['::testItemsApiConnectivity'],
      '#button_type' => 'secondary',
    ];

    $form['items_cache']['actions']['update_items_count'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update Items Count'),
      '#submit' => ['::updateItemsCount'],
      '#button_type' => 'secondary',
    ];

    $form['items_cache']['actions']['clear_items'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear Items Cache'),
      '#submit' => ['::clearItemsCache'],
      '#button_type' => 'secondary',
    ];

    $form['items_cache']['actions']['diagnose_items'] = [
      '#type' => 'submit',
      '#value' => $this->t('Diagnose Items'),
      '#submit' => ['::diagnoseItemsCache'],
      '#button_type' => 'secondary',
    ];

    // SEZIONE 2: Cache Mapping Features
    $features_stats = $this->getMappingFeaturesCacheStatistics();
    $form['features_cache'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cache Mapping Features'),
      '#description' => $this->t('API Source: /api/mapping_features | DB Table: cache_omeka_geo_data'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['features_cache']['stats'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['cache-stats']],
    ];

    $form['features_cache']['stats']['api_total'] = [
      '#type' => 'item',
      '#title' => $this->t('Elementi totali da API'),
      '#markup' => '<strong>' . $features_stats['api_total'] . '</strong>',
      '#description' => $this->t('Numero totale di mapping features disponibili nell\'API remota.'),
    ];

    $form['features_cache']['stats']['cached_count'] = [
      '#type' => 'item',
      '#title' => $this->t('Elementi presenti in cache'),
      '#markup' => '<strong>' . $features_stats['cached_count'] . '</strong>',
      '#description' => $this->t('Numero di mapping features attualmente memorizzate in cache locale.'),
    ];

    $form['features_cache']['stats']['coverage'] = [
      '#type' => 'item',
      '#title' => $this->t('Copertura cache'),
      '#markup' => '<strong>' . $features_stats['coverage'] . '%</strong>',
      '#description' => $this->t('Percentuale di mapping features API memorizzate in cache.'),
    ];

    $form['features_cache']['stats']['last_update'] = [
      '#type' => 'item',
      '#title' => $this->t('Ultimo aggiornamento'),
      '#markup' => '<strong>' . $features_stats['last_update'] . '</strong>',
      '#description' => $this->t('Data dell\'ultimo aggiornamento della cache.'),
    ];

    $form['features_cache']['stats']['errors'] = [
      '#type' => 'item',
      '#title' => $this->t('Errori'),
      '#markup' => '<strong>' . $features_stats['errors'] . '</strong>',
      '#description' => $this->t('Numero di elementi che hanno generato errori.'),
    ];

    // Bottoni Mapping Features
    $form['features_cache']['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-actions']],
    ];

    $form['features_cache']['actions']['refresh_features'] = [
      '#type' => 'submit',
      '#value' => $this->t('Refresh Features Cache'),
      '#submit' => ['::refreshMappingFeaturesCache'],
      '#button_type' => 'primary',
    ];

    $form['features_cache']['actions']['test_features_api'] = [
      '#type' => 'submit',
      '#value' => $this->t('Test Features API'),
      '#submit' => ['::testMappingFeaturesApiConnectivity'],
      '#button_type' => 'secondary',
    ];

    $form['features_cache']['actions']['update_features_count'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update Features Count'),
      '#submit' => ['::updateMappingFeaturesCount'],
      '#button_type' => 'secondary',
    ];

    $form['features_cache']['actions']['clear_features'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear Features Cache'),
      '#submit' => ['::clearMappingFeaturesCache'],
      '#button_type' => 'secondary',
    ];

    $form['features_cache']['actions']['diagnose_features'] = [
      '#type' => 'submit',
      '#value' => $this->t('Diagnose Features'),
      '#submit' => ['::diagnoseMappingFeaturesCache'],
      '#button_type' => 'secondary',
    ];

    // SEZIONE 3: Opzioni Globali
    $form['global_options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Opzioni Globali'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['global_options']['batch_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Dimensione batch'),
      '#description' => $this->t('Numero di elementi da processare in ogni operazione batch.'),
      '#default_value' => 50,
      '#min' => 10,
      '#max' => 100,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Metodo non più utilizzato - i form submission sono gestiti dai metodi specifici
    $this->messenger()->addWarning($this->t('Utilizza i bottoni specifici per ogni tipo di cache.'));
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

  /**
   * Test della connettività API legacy - sostituito dai test specifici.
   */
  public function testApiConnectivity(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addWarning($this->t('Utilizza i bottoni "Test Items API" e "Test Features API" per test specifici.'));
  }
  
  /**
   * Aggiorna i conteggi reali legacy - sostituito dai metodi specifici.
   */
  public function refreshRealCounts(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addWarning($this->t('Utilizza i bottoni "Update Count" specifici per ogni sezione.'));
  }

  /**
   * Aggiorna solo la cache degli Items.
   */
  /**
   * Aggiorna solo la cache degli Items usando un approccio a blocchi.
   * Questo metodo crea un'operazione batch separata per ogni blocco di elementi.
   */
  public function refreshItemsCache(array &$form, FormStateInterface $form_state) {
    $batch_size = $form_state->getValue('batch_size') ?? 50;
    
    // Prima otteniamo il numero totale di elementi disponibili dall'API
    $total_items = $this->cacheService->getTotalItemsFromApi();
    
    // Se non ci sono elementi, mostra un messaggio e termina
    if ($total_items <= 0) {
      $this->messenger()->addWarning($this->t('Nessun elemento disponibile nell\'API Omeka.'));
      return;
    }
    
    // Ottieni tutti gli ID degli items che dobbiamo elaborare
    $items_to_process = $this->cacheService->getAllItemIds();
    
    if (empty($items_to_process)) {
      $this->messenger()->addError($this->t('Impossibile ottenere la lista degli ID degli items.'));
      return;
    }
    
    // Calcola quante operazioni batch dobbiamo eseguire
    // Vogliamo che ogni operazione elabori al massimo $batch_size elementi
    $total_items = count($items_to_process);
    $operations = [];
    
    // Suddividi gli elementi in blocchi di $batch_size
    $chunks = array_chunk($items_to_process, $batch_size);
    
    // Crea un'operazione batch per ogni blocco di elementi
    foreach ($chunks as $index => $chunk) {
      $operations[] = [
        '\Drupal\dog\Form\OmekaCacheRefreshForm::batchProcessItemsChunk',
        [$chunk, $index + 1, count($chunks)],
      ];
    }
    
    // Configura il batch con tutte le operazioni necessarie
    $batch = [
      'title' => $this->t('Aggiornamento cache Items Omeka'),
      'init_message' => $this->t('Inizializzazione processo di aggiornamento...'),
      'progress_message' => $this->t('Elaborazione del blocco @current su @total.'),
      'error_message' => $this->t('Si è verificato un errore durante l\'aggiornamento della cache.'),
      'operations' => $operations,
      'finished' => 'Drupal\dog\Form\OmekaCacheRefreshForm::batchProcessFinished',
      // Reindirizzamento a fine processo batch
      'url' => \Drupal\Core\Url::fromRoute('dog.omeka_cache_refresh'),
      'progressive' => TRUE,
    ];
    
    // Informazione iniziale
    $this->messenger()->addStatus($this->t('Avvio aggiornamento cache per @total items suddivisi in @chunks blocchi', [
      '@total' => $total_items,
      '@chunks' => count($chunks),
    ]));
    
    batch_set($batch);
    // Non specificare qui il reindirizzamento, sarà gestito dal processo batch
  }

  /**
   * Testa la connettività API solo per Items.
   */
  public function testItemsApiConnectivity(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->t('🔍 Test connettività API Items...'));
    
    try {
      // Test specifico per endpoint items
      $total_items = $this->cacheService->getTotalItemsFromApi();
      
      if ($total_items > 0) {
        $this->messenger()->addStatus($this->t('✅ API Items connessa correttamente. Totale elementi: @total', [
          '@total' => $total_items,
        ]));
      } else {
        $this->messenger()->addWarning($this->t('⚠️ API Items raggiungibile ma nessun elemento trovato.'));
      }
    } catch (\Exception $e) {
      $this->messenger()->addError($this->t('❌ Errore connessione API Items: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * Aggiorna il conteggio reale degli Items dall'API.
   */
  public function updateItemsCount(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->t('🔢 Aggiornamento conteggio Items...'));
    
    try {
      $total_items = $this->cacheService->getTotalItemsFromApi();
      $this->state->set('omeka_cache.total_items', $total_items);
      
      $this->messenger()->addStatus($this->t('✅ Conteggio Items aggiornato: @total elementi', [
        '@total' => $total_items,
      ]));
    } catch (\Exception $e) {
      $this->messenger()->addError($this->t('❌ Errore aggiornamento conteggio Items: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
    
    // Non specificare qui il reindirizzamento, sarà gestito dal processo batch
  }

  /**
   * Svuota solo la cache degli Items.
   */
  public function clearItemsCache(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->t('🗑️ Svuotamento cache Items...'));
    
    try {
      $database = \Drupal::database();
      $deleted = $database->delete('cache_omeka_resources')
        ->condition('cid', 'omeka_resource:items:%', 'LIKE')
        ->execute();
      
      $this->messenger()->addStatus($this->t('✅ Cache Items svuotata. Eliminati @count elementi.', [
        '@count' => $deleted,
      ]));
    } catch (\Exception $e) {
      $this->messenger()->addError($this->t('❌ Errore svuotamento cache Items: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
    
    // Non specificare qui il reindirizzamento, sarà gestito dal processo batch
  }

  /**
   * Diagnostica solo la cache degli Items.
   */
  public function diagnoseItemsCache(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->t('🔍 Diagnostica cache Items...'));
    
    try {
      $stats = $this->getItemsCacheStatistics();
      
      $this->messenger()->addStatus($this->t('📊 <strong>Report Items:</strong><br>
        • API Totale: @api_total<br>
        • Cache Locale: @cached<br>
        • Copertura: @coverage%<br>
        • Ultimo Aggiornamento: @last_update', [
        '@api_total' => $stats['api_total'],
        '@cached' => $stats['cached_count'],
        '@coverage' => $stats['coverage'],
        '@last_update' => $stats['last_update'],
      ]));
      
      // Raccomandazioni
      if ($stats['coverage'] < 50 && $stats['api_total'] > 0) {
        $this->messenger()->addWarning($this->t('⚠️ Copertura bassa: considera un refresh completo della cache Items.'));
      }
      
      if ($stats['cached_count'] > $stats['api_total'] && $stats['api_total'] > 0) {
        $this->messenger()->addWarning($this->t('⚠️ Cache contiene più elementi dell\'API: possibili duplicati o dati obsoleti.'));
      }
      
      if ($stats['coverage'] > 95) {
        $this->messenger()->addStatus($this->t('✅ Cache Items in ottimo stato!'));
      }
      
    } catch (\Exception $e) {
      $this->messenger()->addError($this->t('❌ Errore diagnostica Items: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * Aggiorna solo la cache delle Mapping Features usando un approccio a blocchi.
   * Questo metodo crea un'operazione batch separata per ogni blocco di elementi.
   */
  public function refreshMappingFeaturesCache(array &$form, FormStateInterface $form_state) {
    $batch_size = $form_state->getValue('batch_size') ?? 50;
    
    // Prima otteniamo il numero totale di elementi disponibili dall'API
    $total_features = $this->geoCacheService->getTotalMappingFeaturesFromApi();
    
    // Se non ci sono elementi, mostra un messaggio e termina
    if ($total_features <= 0) {
      $this->messenger()->addWarning($this->t('Nessuna mapping feature disponibile nell\'API Omeka.'));
      return;
    }
    
    // Ottieni tutti gli ID delle mapping features che dobbiamo elaborare
    $features_to_process = $this->geoCacheService->getAllMappingFeatureIds();
    
    if (empty($features_to_process)) {
      $this->messenger()->addError($this->t('Impossibile ottenere la lista degli ID delle mapping features.'));
      return;
    }
    
    // Calcola quante operazioni batch dobbiamo eseguire
    // Vogliamo che ogni operazione elabori al massimo $batch_size elementi
    $total_features = count($features_to_process);
    $operations = [];
    
    // Suddividi gli elementi in blocchi di $batch_size
    $chunks = array_chunk($features_to_process, $batch_size);
    
    // Crea un'operazione batch per ogni blocco di elementi
    foreach ($chunks as $index => $chunk) {
      $operations[] = [
        '\Drupal\dog\Form\OmekaCacheRefreshForm::batchProcessMappingFeaturesChunk',
        [$chunk, $index + 1, count($chunks)],
      ];
    }
    
    // Configura il batch con tutte le operazioni necessarie
    $batch = [
      'title' => $this->t('Aggiornamento cache Mapping Features Omeka'),
      'init_message' => $this->t('Inizializzazione processo di aggiornamento...'),
      'progress_message' => $this->t('Elaborazione del blocco @current su @total.'),
      'error_message' => $this->t('Si è verificato un errore durante l\'aggiornamento della cache.'),
      'operations' => $operations,
      'finished' => 'Drupal\dog\Form\OmekaCacheRefreshForm::batchProcessFinished',
      // Reindirizzamento a fine processo batch
      'url' => \Drupal\Core\Url::fromRoute('dog.omeka_cache_refresh'),
      'progressive' => TRUE,
    ];
    
    // Informazione iniziale
    $this->messenger()->addStatus($this->t('Avvio aggiornamento cache per @total mapping features suddivise in @chunks blocchi', [
      '@total' => $total_features,
      '@chunks' => count($chunks),
    ]));
    
    batch_set($batch);
  }

  /**
   * Testa la connettività API solo per Mapping Features.
   */
  public function testMappingFeaturesApiConnectivity(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->t('🔍 Test connettività API Mapping Features...'));
    
    try {
      // Test specifico per endpoint mapping_features
      $total_features = $this->geoCacheService->getTotalMappingFeaturesFromApi();
      
      if ($total_features > 0) {
        $this->messenger()->addStatus($this->t('✅ API Mapping Features connessa correttamente. Totale elementi: @total', [
          '@total' => $total_features,
        ]));
      } else {
        $this->messenger()->addWarning($this->t('⚠️ API Mapping Features raggiungibile ma nessun elemento trovato.'));
      }
    } catch (\Exception $e) {
      $this->messenger()->addError($this->t('❌ Errore connessione API Mapping Features: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * Aggiorna il conteggio reale delle Mapping Features dall'API.
   */
  public function updateMappingFeaturesCount(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->t('🔢 Aggiornamento conteggio Mapping Features...'));
    
    try {
      $total_features = $this->geoCacheService->getTotalMappingFeaturesFromApi();
      $this->state->set('omeka_geo_cache.total_features', $total_features);
      
      $this->messenger()->addStatus($this->t('✅ Conteggio Mapping Features aggiornato: @total elementi', [
        '@total' => $total_features,
      ]));
    } catch (\Exception $e) {
      $this->messenger()->addError($this->t('❌ Errore aggiornamento conteggio Mapping Features: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
    
    // Non specificare qui il reindirizzamento, sarà gestito dal processo batch
  }

  /**
   * Svuota solo la cache delle Mapping Features.
   */
  public function clearMappingFeaturesCache(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->t('🗑️ Svuotamento cache Mapping Features...'));
    
    try {
      $database = \Drupal::database();
      $deleted = $database->delete('cache_omeka_geo_data')
        ->condition('cid', 'omeka_geo_data:feature:%', 'LIKE')
        ->execute();
      
      $this->messenger()->addStatus($this->t('✅ Cache Mapping Features svuotata. Eliminati @count elementi.', [
        '@count' => $deleted,
      ]));
    } catch (\Exception $e) {
      $this->messenger()->addError($this->t('❌ Errore svuotamento cache Mapping Features: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }
  
  /**
   * Batch operation per il processo di Items.
   *
   * @param int $batch_size
   *   Dimensione del batch.
   * @param array $context
   *   Contesto del batch.
   */
  public static function batchProcessItems($batch_size, &$context) {
    // Ottiene il servizio cache per gli items
    $cache_service = \Drupal::service('dog.omeka_cache');
    $logger = \Drupal::logger('dog');
    
    // Se è la prima esecuzione, inizializza il contesto
    if (!isset($context['sandbox']) || empty($context['sandbox'])) {
      $context['sandbox'] = [];
      $context['results'] = [
        'processed' => 0,
        'errors' => 0,
        'configuration_error' => FALSE,
      ];
      
      $logger->notice('Inizializzazione batch Items');
      
      // IMPORTANTE: Prima di tutto scarica l'elenco completo di tutti gli ID degli elementi dall'API
      // in modo da poterli elaborare uno per uno nelle chiamate batch successive
      try {
        $total_from_api = $cache_service->getTotalItemsFromApi();
        $logger->notice('Trovati @count items totali nell\'API', ['@count' => $total_from_api]);
        
        if ($total_from_api > 0) {
          // Chiamata API per ottenere tutti gli ID
          $items_list = $cache_service->getAllItemIds();
          if (!empty($items_list)) {
            $context['sandbox']['items_to_process'] = $items_list;
            $context['sandbox']['total_items'] = count($items_list);
            $context['sandbox']['current_item_index'] = 0;
            $logger->notice('Preparato elenco di @count items da elaborare', [
              '@count' => $context['sandbox']['total_items'],
            ]);
          } else {
            $logger->error('Impossibile ottenere lista di ID');
            $context['finished'] = 1;
            return TRUE;
          }
        } else {
          $logger->warning('Nessun item trovato nell\'API');
          $context['finished'] = 1;
          return TRUE;
        }
      } catch (\Exception $e) {
        $logger->error('Errore nell\'inizializzazione del batch: @message', [
          '@message' => $e->getMessage(),
        ]);
        $context['finished'] = 1;
        return FALSE;
      }
    }
    
    // A questo punto abbiamo un elenco di items_to_process, procediamo con l'elaborazione per batch
    // Aggiorna la cache con il contesto del batch
    $result = $cache_service->updateCache($batch_size, $context);
    
    // Il progresso è calcolato in base agli elementi totali e a quelli già elaborati
    if (isset($context['sandbox']['total_items']) && $context['sandbox']['total_items'] > 0) {
      $processed = $context['results']['processed'] ?? 0;
      $total = $context['sandbox']['total_items'];
      $context['finished'] = min(0.99, $processed / $total);
      
      $logger->notice('Batch items: elaborati @processed di @total (@percent%)', [
        '@processed' => $processed,
        '@total' => $total,
        '@percent' => round($context['finished'] * 100, 1),
      ]);
    }
    
    // Il servizio di cache restituisce TRUE quando ha finito
    if ($result === TRUE) {
      $context['finished'] = 1;
      $logger->notice('Batch items completato: elaborati @processed items', [
        '@processed' => $context['results']['processed'] ?? 0,
      ]);
    }
    
    $context['message'] = t('Aggiornamento cache items (@processed di @total)...', [
      '@processed' => $context['results']['processed'] ?? 0,
      '@total' => $context['sandbox']['total_items'] ?? 0,
    ]);
    
    return $result;
  }
  
  /**
   * Batch operation per il processo delle Mapping Features.
   *
   * @param int $batch_size
   *   Dimensione del batch.
   * @param array $context
   *   Contesto del batch.
   */
  public static function batchProcessMappingFeatures($batch_size, &$context) {
    // Ottiene il servizio cache per le mapping features
    $geo_cache_service = \Drupal::service('dog.omeka_geo_cache');
    $logger = \Drupal::logger('dog');
    
    // Se è la prima esecuzione, inizializza il contesto
    if (!isset($context['sandbox']) || empty($context['sandbox'])) {
      $context['sandbox'] = [];
      $context['results'] = [
        'processed' => 0,
        'errors' => 0,
        'configuration_error' => FALSE,
      ];
      
      $logger->notice('Inizializzazione batch Mapping Features');
      
      // IMPORTANTE: Prima di tutto scarica l'elenco completo di tutti gli ID delle mapping features dall'API
      // in modo da poterle elaborare una per una nelle chiamate batch successive
      try {
        $total_from_api = $geo_cache_service->getTotalMappingFeaturesFromApi();
        $logger->notice('Trovate @count mapping features totali nell\'API', ['@count' => $total_from_api]);
        
        if ($total_from_api > 0) {
          // Chiamata API per ottenere tutti gli ID
          $features_list = $geo_cache_service->getAllMappingFeatureIds();
          if (!empty($features_list)) {
            $context['sandbox']['features_to_process'] = $features_list;
            $context['sandbox']['total_features'] = count($features_list);
            $context['sandbox']['current_feature_index'] = 0;
            $context['sandbox']['process_mode'] = 'by_feature'; // Elaborazione per feature
            $logger->notice('Preparato elenco di @count mapping features da elaborare', [
              '@count' => $context['sandbox']['total_features'],
            ]);
          } else {
            $logger->error('Impossibile ottenere lista di feature IDs');
            $context['finished'] = 1;
            return TRUE;
          }
        } else {
          $logger->warning('Nessuna mapping feature trovata nell\'API');
          $context['finished'] = 1;
          return TRUE;
        }
      } catch (\Exception $e) {
        $logger->error('Errore nell\'inizializzazione del batch: @message', [
          '@message' => $e->getMessage(),
        ]);
        $context['finished'] = 1;
        return FALSE;
      }
    }
    
    // A questo punto abbiamo un elenco di features_to_process, procediamo con l'elaborazione per batch
    // Aggiorna la cache con il contesto del batch
    $result = $geo_cache_service->updateGeoCache($batch_size, $context);
    
    // Il progresso è calcolato in base agli elementi totali e a quelli già elaborati
    if (isset($context['sandbox']['total_features']) && $context['sandbox']['total_features'] > 0) {
      $processed = $context['results']['processed'] ?? 0;
      $total = $context['sandbox']['total_features'];
      $context['finished'] = min(0.99, $processed / $total);
      
      $logger->notice('Batch mapping features: elaborate @processed di @total (@percent%)', [
        '@processed' => $processed,
        '@total' => $total,
        '@percent' => round($context['finished'] * 100, 1),
      ]);
    }
    
    // Il servizio di cache restituisce TRUE quando ha finito
    if ($result === TRUE) {
      $context['finished'] = 1;
      $logger->notice('Batch mapping features completato: elaborate @processed features', [
        '@processed' => $context['results']['processed'] ?? 0,
      ]);
    }
    
    $context['message'] = t('Aggiornamento cache mapping features (@processed di @total)...', [
      '@processed' => $context['results']['processed'] ?? 0,
      '@total' => $context['sandbox']['total_features'] ?? 0,
    ]);
    
    return $result;
  }
  
  /**
   * Metodo batch per processare un chunk specifico di mapping features.
   * 
   * @param array $chunk
   *   Array con gli ID delle mapping features da processare in questo batch.
   * @param int $chunk_index
   *   Indice del chunk corrente (per reporting).
   * @param int $total_chunks
   *   Numero totale di chunks (per reporting).
   * @param array $context
   *   Contesto del batch.
   */
  public static function batchProcessMappingFeaturesChunk(array $chunk, $chunk_index, $total_chunks, &$context) {
    // Ottiene il servizio cache per le mapping features
    $geo_cache_service = \Drupal::service('dog.omeka_geo_cache');
    $logger = \Drupal::logger('dog');
    
    // Inizializza i risultati se è la prima chiamata
    if (!isset($context['results']['processed'])) {
      $context['results'] = [
        'processed' => 0,
        'errors' => 0,
        'configuration_error' => FALSE,
      ];
    }
    
    $logger->notice('Elaborazione chunk @current di @total (@count mapping features)', [
      '@current' => $chunk_index,
      '@total' => $total_chunks,
      '@count' => count($chunk),
    ]);
    
    // Processa ogni mapping feature in questo chunk
    $features_processed = 0;
    $features_errored = 0;
    
    foreach ($chunk as $feature_id) {
      try {
        // Log della chiamata API per la feature corrente
        $logger->notice('Elaborazione mapping feature ID @id', ['@id' => $feature_id]);
        
        // Recupera la feature specifica dall'API e salvala in cache
        // Ottieni l'URL base dalle impostazioni di sistema
        $config = \Drupal::config('dog.settings');
        $base_url = $config->get('base_url');
        $feature_url = rtrim($base_url, '/') . '/api/mapping_features/' . $feature_id;
        
        // Usa il client HTTP di Drupal invece di cercare di accedere a un metodo che non esiste
        $client = \Drupal::httpClient();
        $response = $client->get($feature_url);
        $feature_data = json_decode((string) $response->getBody(), TRUE);
        
        if (!empty($feature_data) && isset($feature_data['o:id'])) {
          // Verifica se la feature ha coordinate
          if (!empty($feature_data['o-module-mapping:geography-coordinates'])) {
            // Prepara i dati geografici
            $item_id = NULL;
            if (!empty($feature_data['o:item']['o:id'])) {
              $item_id = $feature_data['o:item']['o:id'];
            }
            
            $coords = $feature_data['o-module-mapping:geography-coordinates'];
            $geo_type = $feature_data['o-module-mapping:geography-type'] ?? 'Point';
            $label = $feature_data['o:label'] ?? '';
            
            // Struttura base dei dati geografici
            $geo_data = [
              'id' => $item_id,
              'feature_id' => $feature_id,
              'title' => $label,
              'type' => $geo_type,
              'has_geo_data' => TRUE,
              'coordinates' => [
                'lng' => (float) $coords[0],
                'lat' => (float) $coords[1],
              ],
            ];
            
            // Aggiungi dati aggiuntivi dall'item associato se disponibile
            if ($item_id) {
              $item_data = \Drupal::service('dog.omeka_cache')->getResource($item_id, 'items');
              if ($item_data) {
                // Integra i dati dell'item
                $geo_data['title'] = $item_data['o:title'] ?? $label;
                
                if (!empty($item_data['dcterms:description'][0]['@value'])) {
                  $geo_data['description'] = $item_data['dcterms:description'][0]['@value'];
                }
                
                if (!empty($item_data['thumbnail_display_urls']['medium'])) {
                  $geo_data['thumbnail_url'] = $item_data['thumbnail_display_urls']['medium'];
                }
                
                if (!empty($item_data['dcterms:type'][0]['@value'])) {
                  $geo_data['type_desc'] = $item_data['dcterms:type'][0]['@value'];
                }
              }
            }
            
            // Salva i dati geografici in cache
            $cache_key = "omeka_geo_data:feature:{$feature_id}";
            $cache_key_item = $item_id ? "omeka_geo_data:item:{$item_id}" : NULL;
            
            // Tag per la cache
            $cache_tags = [
              'dog_omeka_geo_data_all',
              "dog_omeka_geo_data:feature",
              "dog_omeka_geo_data:feature:{$feature_id}",
            ];
            
            // Salva nella cache
            $cache = \Drupal::service('cache.omeka_geo_data');
            $cache->set(
              $cache_key,
              $geo_data,
              time() + 2592000, // 30 giorni
              $cache_tags
            );
            
            // Verifica che la cache sia stata aggiornata
            $verify_cache = $cache->get($cache_key);
            if ($verify_cache) {
              $features_processed++;
              $context['results']['processed']++;
              $logger->info('Mapping feature @id salvata in cache', ['@id' => $feature_id]);
            } else {
              $features_errored++;
              $context['results']['errors']++;
              $logger->error('Errore nel salvataggio della feature @id', ['@id' => $feature_id]);
            }
          } else {
            $logger->info('Feature @id senza coordinate, ignorata', ['@id' => $feature_id]);
          }
        } else {
          $features_errored++;
          $context['results']['errors']++;
          $logger->warning('Feature @id non valida o non trovata', ['@id' => $feature_id]);
        }
      } catch (\Exception $e) {
        $features_errored++;
        $context['results']['errors']++;
        $logger->error('Errore durante recupero feature @id: @message', [
          '@id' => $feature_id,
          '@message' => $e->getMessage(),
        ]);
      }
    }
    
    // Aggiorna lo stato della cache al termine dell'elaborazione
    if ($chunk_index == $total_chunks) {
      // Aggiorna il timestamp dell'ultimo aggiornamento
      \Drupal::state()->set('omeka_geo_cache.last_update', time());
      // Aggiorna il conteggio delle features in cache
      \Drupal::state()->set('omeka_geo_cache.cached_items', $context['results']['processed']);
    }
    
    $logger->notice('Chunk @current/@total completato: elaborate @processed features, @errors errori', [
      '@current' => $chunk_index,
      '@total' => $total_chunks,
      '@processed' => $features_processed,
      '@errors' => $features_errored,
    ]);
    
    // Imposta il messaggio di stato
    $context['message'] = t('Elaborazione chunk @chunk/@total: @processed mapping features processate', [
      '@chunk' => $chunk_index,
      '@total' => $total_chunks,
      '@processed' => $context['results']['processed'],
    ]);
  }
  
  /**
   * Metodo batch per processare un chunk specifico di items.
   * 
   * @param array $chunk
   *   Array con gli ID degli items da processare in questo batch.
   * @param int $chunk_index
   *   Indice del chunk corrente (per reporting).
   * @param int $total_chunks
   *   Numero totale di chunks (per reporting).
   * @param array $context
   *   Contesto del batch.
   */
  public static function batchProcessItemsChunk(array $chunk, $chunk_index, $total_chunks, &$context) {
    // Ottiene il servizio cache per gli items
    $cache_service = \Drupal::service('dog.omeka_cache');
    $logger = \Drupal::logger('dog');
    
    // Inizializza i risultati se è la prima chiamata
    if (!isset($context['results']['processed'])) {
      $context['results'] = [
        'processed' => 0,
        'errors' => 0,
        'configuration_error' => FALSE,
      ];
    }
    
    // Recupera il bin di cache corretto direttamente per verificare
    $cache_factory = \Drupal::service('cache_factory');
    $direct_cache = $cache_factory->get('omeka_resources');
    
    $logger->notice('Elaborazione chunk @current di @total (@count items)', [
      '@current' => $chunk_index,
      '@total' => $total_chunks,
      '@count' => count($chunk),
    ]);
    
    // Processa ogni item in questo chunk
    $items_processed = 0;
    $items_errored = 0;
    
    foreach ($chunk as $item_id) {
      try {
        // Log della chiamata API per l'elemento corrente
        $logger->notice('Recupero elemento items/@id', ['@id' => $item_id]);
        
        // Recupera direttamente l'elemento singolo
        $resource_data = $cache_service->fetchResource('items', $item_id, TRUE);
        
        if ($resource_data) {
          // Cache l'elemento con i tag appropriati
          $cache_key = "omeka_resource:items:{$item_id}";
          // Uso tag più specifici che non verranno invalidati da altre operazioni di Drupal
          $cache_tags = [
            "dog_omeka_resource", // Tag base specifico del modulo
            "dog_omeka_resource:items",
            "dog_omeka_resource:items:{$item_id}"
          ];
          
          // Salva nella cache
          $direct_cache->set(
            $cache_key,
            $resource_data,
            time() + 2592000, // 30 giorni
            $cache_tags
          );
          
          // Verifica dopo il salvataggio
          $verify_cache = $direct_cache->get($cache_key);
          if ($verify_cache) {
            $items_processed++;
            $context['results']['processed']++;
          } else {
            $items_errored++;
            $context['results']['errors']++;
            $logger->error('Elemento @id non trovato in cache dopo il salvataggio!', ['@id' => $item_id]);
          }
        } else {
          $items_errored++;
          $context['results']['errors']++;
          $logger->warning('Impossibile recuperare elemento @id', ['@id' => $item_id]);
        }
      } catch (\Exception $e) {
        $items_errored++;
        $context['results']['errors']++;
        $logger->error('Errore durante elaborazione elemento @id: @message', [
          '@id' => $item_id,
          '@message' => $e->getMessage(),
        ]);
      }
    }
    
    // Aggiorna lo stato della cache al termine dell'elaborazione
    if ($chunk_index == $total_chunks) {
      // Aggiorna il timestamp dell'ultimo aggiornamento
      \Drupal::state()->set('omeka_cache.last_update', time());
      // Aggiorna il conteggio degli elementi in cache
      \Drupal::state()->set('omeka_cache.cached_items', $context['results']['processed']);
    }
    
    $logger->notice('Chunk @current/@total completato: elaborati @processed items, @errors errori', [
      '@current' => $chunk_index,
      '@total' => $total_chunks,
      '@processed' => $items_processed,
      '@errors' => $items_errored,
    ]);
    
    // Imposta il messaggio di stato
    $context['message'] = t('Elaborazione chunk @chunk/@total: @processed items processati', [
      '@chunk' => $chunk_index,
      '@total' => $total_chunks,
      '@processed' => $context['results']['processed'],
    ]);
  }
  
  /**
   * Callback statica per la conclusione del batch.
   *
   * @param bool $success
   *   Indica se il batch è stato completato con successo.
   * @param array $results
   *   Risultati dell'operazione.
   * @param array $operations
   *   Operazioni rimanenti.
   */
  public static function batchProcessFinished($success, $results, $operations) {
    $messenger = \Drupal::messenger();
    
    if ($success) {
      if (isset($results['configuration_error']) && $results['configuration_error']) {
        $messenger->addError($results['error_message']);
        return;
      }
      
      $processed = $results['processed'] ?? 0;
      $errors = $results['errors'] ?? 0;
      
      if ($processed > 0) {
        $messenger->addStatus(t('✅ Cache aggiornata correttamente! Processati @processed elementi.', [
          '@processed' => $processed,
        ]));
      } else {
        $messenger->addWarning(t('⚠️ Nessun elemento processato. Verifica la configurazione dell\'API.'));
      }
      
      if ($errors > 0) {
        $messenger->addWarning(t('⚠️ Attenzione: si sono verificati @errors errori durante l\'aggiornamento.', [
          '@errors' => $errors,
        ]));
      }
    } else {
      $messenger->addError(t('❌ Si è verificato un errore durante l\'aggiornamento della cache.'));
    }
  }
  
  /**
   * Diagnostica solo la cache delle Mapping Features.
   */
  public function diagnoseMappingFeaturesCache(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->t('🔍 Diagnostica cache Mapping Features...'));
    
    try {
      $stats = $this->getMappingFeaturesCacheStatistics();
      
      $this->messenger()->addStatus($this->t('📊 <strong>Report Mapping Features:</strong><br>
        • API Totale: @api_total<br>
        • Cache Locale: @cached<br>
        • Copertura: @coverage%<br>
        • Ultimo Aggiornamento: @last_update', [
        '@api_total' => $stats['api_total'],
        '@cached' => $stats['cached_count'],
        '@coverage' => $stats['coverage'],
        '@last_update' => $stats['last_update'],
      ]));
      
      // Raccomandazioni
      if ($stats['coverage'] < 50 && $stats['api_total'] > 0) {
        $this->messenger()->addWarning($this->t('⚠️ Copertura bassa: considera un refresh completo della cache Mapping Features.'));
      }
      
      if ($stats['cached_count'] > $stats['api_total'] && $stats['api_total'] > 0) {
        $this->messenger()->addWarning($this->t('⚠️ Cache contiene più elementi dell\'API: possibili duplicati o dati obsoleti.'));
      }
      
      if ($stats['coverage'] > 95) {
        $this->messenger()->addStatus($this->t('✅ Cache Mapping Features in ottimo stato!'));
      }
      
    } catch (\Exception $e) {
      $this->messenger()->addError($this->t('❌ Errore diagnostica Mapping Features: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * Ottiene le statistiche della cache Items.
   */
  private function getItemsCacheStatistics(): array {
    $database = \Drupal::database();
    
    // Conta items in cache (pattern: omeka_resource:items:*)
    $cached_query = $database->select('cache_omeka_resources', 'c')
      ->condition('cid', 'omeka_resource:items:%', 'LIKE')
      ->countQuery();
    $cached_count = $cached_query->execute()->fetchField();
    
    // Ultimo aggiornamento
    $last_update_query = $database->select('cache_omeka_resources', 'c')
      ->fields('c', ['created'])
      ->condition('cid', 'omeka_resource:items:%', 'LIKE')
      ->orderBy('created', 'DESC')
      ->range(0, 1);
    $last_update = $last_update_query->execute()->fetchField();
    $last_update_formatted = $last_update ? date('Y-m-d H:i:s', (int) $last_update) : 'Mai';
    
    // Totale da API (recuperato dallo state o calcolo real-time)
    $api_total = $this->state->get('omeka_cache.total_items', 0);
    
    // Calcola copertura
    $coverage = 0;
    if ($api_total > 0) {
      $coverage = round(($cached_count / $api_total) * 100, 1);
    }
    
    // Errori (per ora 0, da implementare se necessario)
    $errors = 0;
    
    return [
      'api_total' => $api_total,
      'cached_count' => $cached_count,
      'coverage' => $coverage,
      'last_update' => $last_update_formatted,
      'errors' => $errors,
    ];
  }

  /**
   * Ottiene le statistiche della cache Mapping Features.
   */
  private function getMappingFeaturesCacheStatistics(): array {
    $database = \Drupal::database();
    
    // Conta mapping features in cache (pattern: omeka_geo_data:feature:*)
    $cached_query = $database->select('cache_omeka_geo_data', 'c')
      ->condition('cid', 'omeka_geo_data:feature:%', 'LIKE')
      ->countQuery();
    $cached_count = $cached_query->execute()->fetchField();
    
    // Ultimo aggiornamento
    $last_update_query = $database->select('cache_omeka_geo_data', 'c')
      ->fields('c', ['created'])
      ->condition('cid', 'omeka_geo_data:feature:%', 'LIKE')
      ->orderBy('created', 'DESC')
      ->range(0, 1);
    $last_update = $last_update_query->execute()->fetchField();
    $last_update_formatted = $last_update ? date('Y-m-d H:i:s', (int) $last_update) : 'Mai';
    
    // Totale da API (recuperato dallo state o calcolo real-time)
    $api_total = $this->state->get('omeka_geo_cache.total_features', 0);
    
    // Calcola copertura
    $coverage = 0;
    if ($api_total > 0) {
      $coverage = round(($cached_count / $api_total) * 100, 1);
    }
    
    // Errori (per ora 0, da implementare se necessario)
    $errors = 0;
    
    return [
      'api_total' => $api_total,
      'cached_count' => $cached_count,
      'coverage' => $coverage,
      'last_update' => $last_update_formatted,
      'errors' => $errors,
    ];
  }
}
