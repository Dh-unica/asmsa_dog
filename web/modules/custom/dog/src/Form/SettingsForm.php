<?php

namespace Drupal\dog\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Http\ClientFactory;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the SettingsForm class.
 *
 * @package Drupal\dog\Form
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected $factory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);

    $factory = $container->get('http_client_factory');
    assert($factory instanceof ClientFactory);
    $instance->factory = $factory;

    return $instance;
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
  public function buildForm(array $form, FormStateInterface $form_state) {
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

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    try {
      $http_client = $this->factory->fromOptions([
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('dog.settings')
      ->set('base_url', $form_state->getValue('base_url'))
      ->set('key_identity', $form_state->getValue('key_identity'))
      ->set('key_credential', $form_state->getValue('key_credential'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
