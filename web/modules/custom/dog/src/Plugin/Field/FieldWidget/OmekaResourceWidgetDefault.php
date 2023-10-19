<?php

namespace Drupal\dog\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dog\Service\ResourceFetcherInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Defines the OmekaResourceWidgetDefault class.
 *
 * @FieldWidget(
 *   id = "dog_omeka_resource_default",
 *   label = @Translation("Omeka Resource"),
 *   field_types = {"dog_omeka_resource"},
 * )
 * @package Drupal\dog\Plugin\Field\FieldWidget
 */
class OmekaResourceWidgetDefault extends WidgetBase {

  /**
   * Form element validation handler for Omeka Resource form element.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateFormElement(array &$element, FormStateInterface $form_state) {
    $id = rtrim(trim($element['id']['#value']), " ");
    $type = rtrim(trim($element['type']['#value']), " ");

    if ($id !== '' && $type !== '') {
      $form_state->setValueForElement($element['id'], $id);
      $form_state->setValueForElement($element['type'], $type);

      $fetcher = \Drupal::service('dog.omeka_resource_fetcher');
      assert($fetcher instanceof ResourceFetcherInterface);

      $data = $fetcher->retrieveResource($id, $type);
      if (empty($data)) {
        $form_state->setError($element['id'], t('Omeka Resource not found.'));

        return;
      }

      if (!(isset($data['@id']) && isset($data['@type']))) {
        $form_state->setError($element['id'], t('Omeka Resource does not have the required fields.'));

        return;
      }

      $form_state->set('omeka_resource.' . $element['#delta'], $data);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element += [
      '#element_validate' => [[static::class, 'validateFormElement']],
    ];

    $item = $items[$delta];
    $element['id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ID'),
      '#description' => $this->t('The ID of Omeka resource.'),
      '#attributes' => ['placeholder' => 'Example: 671'],
      '#default_value' => $item->id,
      '#size' => 32,
    ];

    $element['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#description' => $this->t('The resource type.'),
      // @todo complete options https://omeka.org/s/docs/developer/api/#resources.
      '#options' => [
        'items' => $this->t('Item'),
        'item_sets' => $this->t('Item set'),
        'media' => $this->t('Media'),
      ],
      // @todo remove force default value and enable field.
      '#default_value' => 'items',
      '#disabled' => TRUE,
    ];

    $element['#theme_wrappers'] = ['container', 'form_element'];
    $element['#attributes']['class'][] = 'container-inline';
    $element['#attributes']['class'][] = 'dog-omeka-resource-elements';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $violation, array $form, FormStateInterface $form_state) {
    return $element['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $delta => $value) {
      if (!empty($value['id']) && !empty($data = $form_state->get('omeka_resource.' . $delta))) {
        $values[$delta] = [
          'id' => $value['id'],
          'type' => $value['type'],
        ];
      }
      else {
        $values[$delta] = [
          'id' => NULL,
          'type' => NULL,
        ];
      }
    }
    return $values;
  }

}
