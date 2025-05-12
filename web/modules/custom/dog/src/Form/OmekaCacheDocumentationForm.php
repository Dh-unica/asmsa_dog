<?php

namespace Drupal\dog\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\dog\Service\OmekaCacheService;

/**
 * Provides a documentation form for Omeka cache system.
 */
class OmekaCacheDocumentationForm extends FormBase {

  /**
   * The cache service.
   *
   * @var \Drupal\dog\Service\OmekaCacheService
   */
  protected $cacheService;

  /**
   * Constructs a new OmekaCacheDocumentationForm.
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
    return 'dog_omeka_cache_documentation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get information about the last cache update.
    $last_update = $this->cacheService->getLastUpdateInfo();

    $form['intro'] = [
      '#markup' => '<p>' . $this->t('This page provides documentation about the Omeka cache system.') . '</p>',
    ];

    $form['status'] = [
      '#type' => 'details',
      '#title' => $this->t('Cache Status'),
      '#open' => TRUE,
    ];

    $form['status']['last_update'] = [
      '#type' => 'item',
      '#title' => $this->t('Last cache update'),
      '#markup' => $last_update['formatted_date'],
    ];

    $refresh_url = Url::fromRoute('dog.omeka_cache_refresh');
    $refresh_link = Link::fromTextAndUrl($this->t('Refresh Cache'), $refresh_url)->toString();

    $form['usage'] = [
      '#type' => 'details',
      '#title' => $this->t('Usage Instructions'),
      '#open' => TRUE,
    ];

    $form['usage']['content'] = [
      '#markup' => '<p>' . $this->t('The Omeka cache system improves performance by storing all Omeka resources locally instead of making live API calls. This documentation will help you understand and manage the cache system.') . '</p>'
      . '<h3>' . $this->t('How It Works') . '</h3>'
      . '<p>' . $this->t('Instead of making API calls to Omeka in real-time, all the necessary data is pre-loaded and stored in the Drupal cache system through a batch process. This significantly improves the performance and responsiveness of the website.') . '</p>'
      . '<h3>' . $this->t('Automatic Updates') . '</h3>'
      . '<p>' . $this->t('The cache is automatically refreshed once a day through a cron job. This ensures that the data remains relatively up-to-date without manual intervention.') . '</p>'
      . '<h3>' . $this->t('Manual Refresh') . '</h3>'
      . '<p>' . $this->t('You can manually trigger a cache refresh by visiting the @refresh_link page. This is useful after making changes to Omeka resources that you want to reflect immediately on the website.', ['@refresh_link' => $refresh_link]) . '</p>'
      . '<h3>' . $this->t('Troubleshooting') . '</h3>'
      . '<p>' . $this->t('If users encounter missing resources or error messages stating that resources are not available in the cache, it means those resources are not present in the local cache. Run a manual cache refresh to resolve this issue.') . '</p>'
      . '<p>' . $this->t('If the cache refresh process takes too long or times out, try refreshing again. The batch process is designed to handle large amounts of data incrementally.') . '</p>',
      '#allowed_tags' => ['p', 'h2', 'h3', 'a', 'em', 'strong', 'ul', 'ol', 'li'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This is a documentation form with no submission handling.
  }

}
