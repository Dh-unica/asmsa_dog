<?php

namespace Drupal\dog\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface; // Added missing use statement
use Drupal\Core\Http\ClientFactory; 
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\ExtensionPathResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the SettingsForm class.
 *
 * @package Drupal\dog\Form
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The HTTP client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected $httpClientFactory;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The extension path resolver.
   *
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected $extensionPathResolver;

  /**
   * Constructs a \Drupal\dog\Form\SettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Http\ClientFactory $http_client_factory // Ensure this is the correct class for type hint, usually it is.

   *   The HTTP client factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Extension\ExtensionPathResolverInterface $extension_path_resolver
   *   The extension path resolver.
   */
  public function __construct(\Drupal\Core\Config\ConfigFactoryInterface $config_factory, \Drupal\Core\Http\ClientFactory $http_client_factory, \Drupal\Core\State\StateInterface $state, \Drupal\Core\Datetime\DateFormatterInterface $date_formatter, \Drupal\Core\Extension\ExtensionPathResolver $extension_path_resolver) {
    parent::__construct($config_factory);
    $this->httpClientFactory = $http_client_factory; // Corrected from $this->factory
    $this->state = $state;
    $this->dateFormatter = $date_formatter;
    $this->extensionPathResolver = $extension_path_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('http_client_factory'),
      $container->get('state'),
      $container->get('date.formatter'),
      $container->get('extension.path.resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dog_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['dog.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $form['base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Base URL '),
      '#description' => $this->t('The base URL to which the system request the resource. Ex. "https://www.digitaliststor.it/risorse/".'),
      '#default_value' => $this->config('dog.settings')->get('base_url'),
      '#required' => TRUE,
    ];
    $form['key_identity'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key identity'),
      '#default_value' => $this->config('dog.settings')->get('key_identity'),
    ];
    $form['key_credential'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key credential'),
      '#default_value' => $this->config('dog.settings')->get('key_credential'),
    ];

    // Section for Omeka Cache Management.
    $form['omeka_cache_management'] = [
      '#type' => 'details',
      '#title' => $this->t('Omeka Cache Management'),
      '#open' => TRUE,
    ];

    // Subsection for Items Cache.
    $form['omeka_cache_management']['items_cache'] = [
      '#type' => 'details',
      '#title' => $this->t('Omeka Items Cache (cache_omeka: omeka_resource:items:*)'),
      '#open' => TRUE,
      '#description' => $this->t('Manage the cached Omeka items.'),
    ];

    $items_last_update = $this->state->get('dog.omeka_items_last_update');
    $items_count = $this->state->get('dog.omeka_items_count', 0);
    $form['omeka_cache_management']['items_cache']['status'] = [
      '#type' => 'item',
      '#markup' => $this->t('Last update: @time<br>Cached items: @count', [
        '@time' => $items_last_update ? $this->dateFormatter->format($items_last_update, 'long') : $this->t('Never'),
        '@count' => $items_count,
      ]),
    ];

    $form['omeka_cache_management']['items_cache']['update_items_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update Items Cache Now'),
      '#name' => 'update_items_cache_submit',
      '#submit' => ['::triggerItemsBatch'], // Custom submit handler for this button
    ];

    // Subsection for Mapping Features Cache.
    // Based on memory 752ba2db-c0f0-4d43-b33c-88386fa10e4a, features are in 'cache_omeka_geo_data'
    // and use pattern 'omeka_geo_data:feature:*'. The API type is likely 'mapping_features' or 'o-module-mapping:features'.
    $form['omeka_cache_management']['features_cache'] = [
      '#type' => 'details',
      '#title' => $this->t('Omeka Mapping Features Cache (cache_omeka: omeka_geo_data:feature:*)'),
      '#open' => TRUE,
      '#description' => $this->t('Manage the cached Omeka mapping features.'),
    ];

    $features_last_update = $this->state->get('dog.omeka_features_last_update');
    $features_count = $this->state->get('dog.omeka_features_count', 0);
    $form['omeka_cache_management']['features_cache']['status'] = [
      '#type' => 'item',
      '#markup' => $this->t('Last update: @time<br>Cached features: @count', [
        '@time' => $features_last_update ? $this->dateFormatter->format($features_last_update, 'long') : $this->t('Never'),
        '@count' => $features_count,
      ]),
    ];

    $form['omeka_cache_management']['features_cache']['update_features_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update Features Cache Now'),
      '#name' => 'update_features_cache_submit',
      '#submit' => ['::triggerFeaturesBatch'], // Custom submit handler for this button
    ];

    // Ensure the main save button is still present and works for config.
    // It will be added by parent::buildForm($form, $form_state) if we call it last.

    $form['maintenance'] = [
      '#type' => 'details',
      '#title' => $this->t('Maintenance'),
      '#open' => TRUE,
    ];

    $form['maintenance']['clear_cache'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear Cache and Statistics'),
      '#submit' => ['::submitClearCache'],
      '#name' => 'clear_cache_submit', // Add a name to identify the button.
      '#button_type' => 'danger',
      '#limit_validation_errors' => [], // Do not validate the form when this button is clicked.
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Custom submission handler for the 'Clear Cache and Statistics' button.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitClearCache(array &$form, FormStateInterface $form_state) {
    // Invalidate the Omeka cache bin.
    // The Cache API is not reliably clearing the persistent cache table from the UI.
    // As a final, robust solution, we perform a direct database query to truncate the table.
    // This guarantees the cache is cleared.
    try {
      $connection = \Drupal::database();
      $connection->truncate('cache_omeka')->execute();
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('An error occurred while trying to clear the cache table: @message', ['@message' => $e->getMessage()]));
    }

    // Delete state variables.
    $this->state->delete('dog.omeka_items_last_update');
    $this->state->delete('dog.omeka_items_count');
    $this->state->delete('dog.omeka_features_last_update');
    $this->state->delete('dog.omeka_features_count');

    $this->messenger()->addStatus($this->t('The Omeka cache and all related statistics have been cleared.'));

    // Prevent the default parent::submitForm() from running.
    $form_state->setRebuild();
  }

  /**
   * Custom submit handler for the 'Update Items Cache Now' button.
   */
  public function triggerItemsBatch(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $batch = [
      'title' => $this->t('Updating Omeka Items Cache...'),
      'operations' => [
        ['_dog_omeka_batch_operation', ['items', 'dog.omeka_items']],
      ],
      'finished' => '_dog_omeka_batch_finished',
      'file' => $this->extensionPathResolver->getPath('module', 'dog') . '/dog.module', // Ensure batch functions are loaded.
    ];
    batch_set($batch);
    $this->messenger()->addStatus($this->t('Omeka items cache update process started.'));
    // No redirect needed, batch API handles it.
    // Prevent regular form submission for config save.
    $form_state->setRebuild(TRUE);
  }

  /**
   * Custom submit handler for the 'Update Features Cache Now' button.
   */
  public function triggerFeaturesBatch(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    // The API resource type for features might be 'mapping_features' or 'o-module-mapping:features'.
    // Let's use 'mapping_features' for now, as used in OmekaResourceFetcher cache key logic.
    // The state key prefix will be 'dog.omeka_features'.
    $batch = [
      'title' => $this->t('Updating Omeka Mapping Features Cache...'),
      'operations' => [
        // Note: The function _dog_omeka_batch_operation is in dog.module, not in this class.
        // We need to call it directly or wrap it.
        // For simplicity, let's assume _dog_omeka_batch_operation is globally available.
        // The batch API needs a callable. We pass the function name as a string.
        ['_dog_omeka_batch_operation', ['mapping_features', 'dog.omeka_features']],
      ],
      'finished' => '_dog_omeka_batch_finished',
      'file' => $this->extensionPathResolver->getPath('module', 'dog') . '/dog.module', // Ensure batch functions are loaded.
    ];
    batch_set($batch);
    $this->messenger()->addStatus($this->t('Omeka mapping features cache update process started.'));
    $form_state->setRebuild(TRUE);
  }

  /**
   * Helper function to be called by the batch API if operations are defined within the form class.
   * This is an alternative way to structure batch operations if they need access to form class methods/properties.
   * However, our _dog_omeka_batch_operation is in dog.module.
   * This function is kept as an example or for future use if needed.
   */
  public static function runBatchOperation($api_resource_type, $state_key_prefix, &$context) {
    // This static method can be a wrapper if needed, or _dog_omeka_batch_operation can be called directly.
    // For now, we assume _dog_omeka_batch_operation is called directly as it's simpler.
    // If _dog_omeka_batch_operation was a static method of a class, it would be ['ClassName', 'methodName'].
    // Since it's a global function in dog.module, just its name is fine for 'operations'.
    // This function is not directly used if _dog_omeka_batch_operation is called directly in batch definition.
    // It's more of a placeholder if we wanted to encapsulate the call within the Form class.
    
    // The actual call is defined directly in triggerItemsBatch / triggerFeaturesBatch
    // using the global function name '_dog_omeka_batch_operation'.
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    // Only validate API credentials if the main 'Save configuration' button was pressed.
    $triggering_element = $form_state->getTriggeringElement();
    if (isset($triggering_element['#name']) && 
        ($triggering_element['#name'] === 'update_items_cache_submit' || 
         $triggering_element['#name'] === 'update_features_cache_submit' || 
         $triggering_element['#name'] === 'clear_cache_submit')) {
      // Skip validation for batch or clear buttons.
      return;
    }
    parent::validateForm($form, $form_state);

    try {
      $http_client = $this->httpClientFactory->fromOptions([
        'base_uri' => $form_state->getValue('base_url'),
        'query' => [
          'key_identity' => $form_state->getValue('key_identity'),
          'key_credential' => $form_state->getValue('key_credential'),
        ],
      ]);

      // @todo update the endpoint for test!.
      $response = $http_client->request('GET', 'api/items');

      $data = json_decode($response->getBody());
      assert(is_array($data), "Response is not an array.");
    }
    catch (\Exception $exception) {
      $element = NULL;
      if ($exception instanceof RequestException && $exception->getResponse()) {
        $element = $exception->getResponse()
          ->getStatusCode() == 403 ? 'key_identity' : 'base_uri';
      }
      $form_state->setErrorByName($element, $this->t((string) $exception->getMessage()));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    // Check which button was pressed.
    $triggering_element = $form_state->getTriggeringElement();

    // If a batch button was pressed, its specific submit handler already took care of it.
    // We just need to prevent the default config save in that case.
    if (isset($triggering_element['#name']) && 
        ($triggering_element['#name'] === 'update_items_cache_submit' || $triggering_element['#name'] === 'update_features_cache_submit')) {
      // The specific batch submit handlers (triggerItemsBatch, triggerFeaturesBatch) are called
      // because we set them in '#submit'. They should handle $form_state->setRebuild().
      return;
    }

    // If it's the main save button, save the configuration.
    $this->config('dog.settings')
      ->set('base_url', $form_state->getValue('base_url'))
      ->set('key_identity', $form_state->getValue('key_identity'))
      ->set('key_credential', $form_state->getValue('key_credential'))
      ->save();
    parent::submitForm($form, $form_state);
  }



}
