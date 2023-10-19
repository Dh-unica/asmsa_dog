<?php

namespace Drupal\dog\Plugin\views\argument;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;
use Drupal\views_remote_data\Plugin\views\query\RemoteDataQuery;

/**
 * Defines the ResourceType class.
 *
 * @ViewsArgument("dog_omeka_resource_type")
 * @package Drupal\dog\Plugin\views\argument
 */
class ResourceType extends ArgumentPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    // Allow + for or, , for and.
    $form['break_phrase'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow multiple values'),
      '#description' => $this->t('If selected, users can enter multiple values in the form of 1+2+3 (for OR) or 1,2,3 (for AND).'),
      '#default_value' => !empty($this->options['break_phrase']),
      '#group' => 'options][more',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE): void {
    assert($this->query instanceof RemoteDataQuery);

    $argument = $this->argument;
    if (!empty($this->options['break_phrase'])) {
      $this->unpackArgumentValue();
    }
    else {
      $this->value = [$argument];
      $this->operator = 'or';
    }

    $this->query->addWhere(
      '0',
      'type',
      $this->value,
      '='
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['break_phrase'] = ['default' => FALSE];
    return $options;
  }

}
