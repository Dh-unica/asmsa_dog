<?php

namespace Drupal\dog\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\dog\Service\ResourceFetcherInterface;

/**
 * Defines the OmekaResourceItem class.
 *
 * @FieldType(
 *   id = "dog_omeka_resource",
 *   label = @Translation("Omeka Resource"),
 *   category = @Translation("Reference"),
 *   default_widget = "dog_omeka_resource_default",
 *   default_formatter = "dog_omeka_resource_default"
 * )
 * @package Drupal\dog\Plugin\Field\FieldType
 */
class OmekaResourceItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    $settings = ['type' => ['items' => 'items']];
    return $settings + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['id'] = DataDefinition::create('string')
      ->setLabel(t('ID'));
    $properties['type'] = DataDefinition::create('string')
      ->setLabel(t('Type'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'id' => [
          'description' => 'The ID of Resource.',
          'type' => 'varchar',
          'length' => 128,
        ],
        'type' => [
          'description' => 'The Type of Resource.',
          'type' => 'varchar',
          'length' => 128,
        ],
      ],
      'indexes' => [
        'id' => ['id'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    return [
      'id' => NULL,
      'type' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $settings = $this->getSettings();

    $fetcher = \Drupal::service('dog.omeka_resource_fetcher');
    assert($fetcher instanceof ResourceFetcherInterface);

    $element['type'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select resource type'),
      '#description' => $this->t('Choose the type of resource to retrieve.'),
      '#default_value' => $settings['type'] ?? [],
      '#options' => $fetcher->getTypes(),
      '#required' => TRUE,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $id = $this->get('id')->getValue();
    $type = $this->get('type')->getValue();

    return $id === NULL || $id === '' || $type === NULL || $type === '';
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraint_manager = \Drupal::typedDataManager()
      ->getValidationConstraintManager();
    $constraints = parent::getConstraints();

    $constraints[] = $constraint_manager->create('ComplexData', [
      'id' => [
        'Length' => [
          'max' => 128,
          'maxMessage' => $this->t('%name: may not be longer than @max characters.', [
            '%name' => $this->getFieldDefinition()->getLabel(),
            '@max' => 128,
          ]),
        ],
      ],
      'type' => [
        'Length' => [
          'max' => 128,
          'maxMessage' => $this->t('%name: may not be longer than @max characters.', [
            '%name' => $this->getFieldDefinition()->getLabel(),
            '@max' => 128,
          ]),
        ],
      ],
    ]);

    return $constraints;
  }

}
