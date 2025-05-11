<?php

namespace Drupal\italiagov_cache_warmer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\Cache;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

/**
 * Form per ricostruire tutta la cache dei blocchi Omeka Map.
 */
class RebuildAllCacheForm extends FormBase {

  /**
   * Il servizio di cache warmer.
   *
   * @var \Drupal\italiagov_cache_warmer\Service\CacheWarmerService
   */
  protected $cacheWarmer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('italiagov_cache_warmer.warmer')
    );
  }

  /**
   * Costruttore.
   *
   * @param object $cache_warmer
   *   Il servizio di cache warmer.
   */
  public function __construct($cache_warmer) {
    $this->cacheWarmer = $cache_warmer;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'italiagov_cache_warmer_rebuild_all_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#markup' => '<p>' . $this->t('Questa operazione ricostruirà la cache per tutti i blocchi Omeka Map. Potrebbe richiedere del tempo.') . '</p>',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Ricostruisci tutta la cache'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Invalida tutti i tag di cache relativi a Omeka Map.
    Cache::invalidateTags(['omeka_map_persistent']);

    // Ricostruisci tutta la cache.
    $count = $this->cacheWarmer->warmCache();

    $this->messenger()->addStatus($this->t('Cache ricostruita per @count blocchi Omeka Map.', ['@count' => $count]));
    $form_state->setRedirectUrl(Url::fromRoute('italiagov_cache_warmer.report'));
  }

}
