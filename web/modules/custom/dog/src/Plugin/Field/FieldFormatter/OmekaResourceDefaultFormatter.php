<?php

namespace Drupal\dog\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\dog\Service\ResourceViewBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the OmekaResourceDefaultFormatter class.
 *
 * @FieldFormatter(
 *   id = "dog_omeka_resource_default",
 *   label = @Translation("Default"),
 *   field_types = {"dog_omeka_resource"}
 * )
 * @package Drupal\dog\Plugin\Field\FieldFormatter
 */
class OmekaResourceDefaultFormatter extends FormatterBase {

  /**
   * The view builder for entity.
   *
   * @var \Drupal\dog\Service\ResourceViewBuilderInterface
   */
  protected $viewBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $fetcher = $container->get('dog.omeka_resource_view_builder');
    assert($fetcher instanceof ResourceViewBuilderInterface);
    $instance->viewBuilder = $fetcher;

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      $element[$delta] = $this->viewBuilder->view([
        'id' => $item->id,
        'type' => $item->type,
      ]);
    }

    return $element;
  }

}
