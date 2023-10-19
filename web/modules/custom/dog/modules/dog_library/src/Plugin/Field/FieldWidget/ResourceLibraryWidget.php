<?php

namespace Drupal\dog_library\Plugin\Field\FieldWidget;

use Drupal\dog\Plugin\Field\FieldType\OmekaResourceItem;
use Drupal\dog\Service\ResourceFetcherInterface;
use Drupal\dog\Service\ResourceViewBuilderInterface;
use Drupal\dog_library\ResourceLibraryState;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AnnounceCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Defines the ResourceLibraryWidget class.
 *
 * @package Drupal\dog_library\Plugin\Field\FieldWidget
 *
 * @FieldWidget(
 *   id = "dog_omeka_resource_library",
 *   label = @Translation("Omeka Resource Libary"),
 *   field_types = {"dog_omeka_resource"},
 *   multiple_values = TRUE,
 * )
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 */
class ResourceLibraryWidget extends WidgetBase implements TrustedCallbackInterface {

  /**
   * The fetcher for omeka resource.
   *
   * @var \Drupal\dog\Service\ResourceFetcherInterface
   */
  protected $fetcher;

  /**
   * The view builder service for omeka resource.
   *
   * @var \Drupal\dog\Service\ResourceViewBuilderInterface
   */
  protected $viewBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $fetcher = $container->get('dog.omeka_resource_fetcher');
    assert($fetcher instanceof ResourceFetcherInterface);
    $instance->fetcher = $fetcher;

    $view_builder = $container->get('dog.omeka_resource_view_builder');
    assert($view_builder instanceof ResourceViewBuilderInterface);
    $instance->viewBuilder = $view_builder;

    return $instance;
  }

  /**
   * Gets the enabled resource type IDs.
   *
   * @return string[]
   *   The resource type IDs.
   */
  protected function getAllowedResourceTypeIds() {
    // Get the configured from the field storage.
    $settings = $this->getFieldSettings();
    // The types will be blank when saving field storage settings,
    // when first adding a resource reference field.
    $allowed_resource_type_ids = $settings['type'] ?? NULL;
    $allowed_resource_type_ids = array_filter($allowed_resource_type_ids);
    $allowed_resource_type_ids = array_combine($allowed_resource_type_ids, $allowed_resource_type_ids);

    // When there are no allowed resource types, return the empty array.
    if ($allowed_resource_type_ids === []) {
      return $allowed_resource_type_ids;
    }

    // When no target bundles are configured for the field, all are allowed.
    if ($allowed_resource_type_ids === NULL) {
      $allowed_resource_type_ids = array_keys($this->fetcher->getTypes());
    }

    // Make sure the keys are numeric.
    return array_values($allowed_resource_type_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    // Load the items for form rebuilds from the field state.
    $field_state = static::getWidgetState($form['#parents'], $this->fieldDefinition->getName(), $form_state);
    if (isset($field_state['items'])) {
      usort($field_state['items'], [SortArray::class, 'sortByWeightElement']);
      $items->setValue($field_state['items']);
    }

    return parent::form($items, $form, $form_state, $get_delta);
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    parent::extractFormValues($items, $form, $form_state);

    // Update reference to 'items' stored during add or remove to take into
    // account changes to values like 'weight' etc.
    // @see self::addItems
    // @see self::removeItem
    $field_name = $this->fieldDefinition->getName();
    $field_state = static::getWidgetState($form['#parents'], $field_name, $form_state);
    $field_state['items'] = $items->getValue();
    static::setWidgetState($form['#parents'], $field_name, $form_state, $field_state);
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $resource_list = [];
    foreach ($items as $item) {
      assert($item instanceof OmekaResourceItem);
      $resource_list[] = $item->getValue();
    }

    $field_name = $this->fieldDefinition->getName();
    $parents = $form['#parents'];
    // Create an ID suffix from the parents to make sure each widget is unique.
    $id_suffix = $parents ? '-' . implode('-', $parents) : '';
    $field_widget_id = implode(':', array_filter([$field_name, $id_suffix]));
    $wrapper_id = $field_name . '-resource-library-wrapper' . $id_suffix;
    $limit_validation_errors = [array_merge($parents, [$field_name])];

    $settings = $this->getFieldSettings();
    $target_types = $settings['type'] ?? [];
    $target_types = array_filter($target_types);

    $element += [
      '#type' => 'fieldset',
      '#cardinality' => $this->fieldDefinition->getFieldStorageDefinition()
        ->getCardinality(),
      // If no target bundles are specified, all target bundles are allowed.
      '#target_types' => $target_types,
      '#attributes' => [
        'id' => $wrapper_id,
        'class' => [
          'js-resource-library-widget',
        ],
      ],
      '#pre_render' => [
        [$this, 'preRenderWidget'],
      ],
      '#attached' => [
        'library' => ['dog_library/widget'],
      ],
      '#theme_wrappers' => [
        'fieldset__resource_library_widget',
      ],
    ];

    if ($this->fieldDefinition->isRequired()) {
      $element['#element_validate'][] = [static::class, 'validateRequired'];
    }

    // When the list of allowed types in the field configuration is null,
    // ::getAllowedResourceTypeIds() returns all existing resource types. When
    // the list of allowed types is an empty array, we show a message.
    $allowed_resource_type_ids = $this->getAllowedResourceTypeIds();
    if (!$allowed_resource_type_ids) {
      $element['no_types_message'] = [
        '#markup' => $this->t('There are no allowed resource types configured for this field. Please contact the site administrator.'),
      ];
      return $element;
    }

    $multiple_items = FALSE;
    if (empty($resource_list)) {
      $element['#field_prefix']['empty_selection'] = [
        '#markup' => $this->t('No resource items are selected.'),
      ];
    }
    else {
      $multiple_items = count($resource_list) > 1;
      $element['#field_prefix']['weight_toggle'] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('Show resource item weights'),
        '#access' => $multiple_items,
        '#attributes' => [
          'class' => [
            'link',
            'js-resource-library-widget-toggle-weight',
          ],
        ],
      ];
    }

    $element['selection'] = [
      '#type' => 'container',
      '#theme_wrappers' => [
        'container__resource_library_widget_selection',
      ],
      '#attributes' => [
        'class' => [
          'js-resource-library-selection',
        ],
      ],
    ];

    foreach ($resource_list as $delta => $resource_item) {
      $id = $resource_item['id'] ?? NULL;
      $type = $resource_item['type'] ?? NULL;

      if (empty($id) || empty($type)) {
        continue;
      }

      // Build the preview.
      $preview = $this->viewBuilder->view($resource_item,'library');

      // Add wrapper for allow resource items to be re-sorted with
      // drag+drop in the widget.
      $preview['#theme_wrappers'] = [
        'container' => [
          '#attributes' => ['class' => 'js-resource-library-item-preview'],
        ],
      ];

      $element['selection'][$delta] = [
        // Reuse the theme function already exist.
        '#theme' => 'resource_library_item__widget',
        '#attributes' => [
          'class' => [
            'js-resource-library-item',
          ],
          // Add the tabindex '-1' to allow the focus to be shifted to the next
          // resource item when an item is removed. We set focus to the container
          // because we do not want to set focus to the remove button
          // automatically.
          // @see ::updateWidget()
          'tabindex' => '-1',
          // Add a data attribute containing the delta to allow us to easily
          // shift the focus to a specific resource item.
          // @see ::updateWidget()
          'data-resource-library-item-delta' => $delta,
        ],
        'remove_button' => [
          '#type' => 'submit',
          '#name' => $field_name . '-' . $delta . '-resource-library-remove-button' . $id_suffix,
          '#value' => $this->t('Remove'),
          '#resource_id' => $id,
          '#resource_type' => $type,
          '#attributes' => [
            'aria-label' => $this->t('Remove resource'),
          ],
          '#ajax' => [
            'callback' => [static::class, 'updateWidget'],
            'wrapper' => $wrapper_id,
            'progress' => [
              'type' => 'throbber',
              'message' => $this->t('Removing resource.'),
            ],
          ],
          '#submit' => [[static::class, 'removeItem']],
          // Prevent errors in other widgets from preventing removal.
          '#limit_validation_errors' => $limit_validation_errors,
        ],
        'rendered_entity' => $preview,
        'id' => [
          '#type' => 'hidden',
          '#value' => $id,
        ],
        'type' => [
          '#type' => 'hidden',
          '#value' => $type,
        ],
        // This hidden value can be toggled visible for accessibility.
        'weight' => [
          '#type' => 'number',
          '#theme' => 'input__number__resource_library_item_weight',
          '#title' => $this->t('Weight'),
          '#access' => $multiple_items,
          '#default_value' => $delta,
          '#attributes' => [
            'class' => [
              'js-resource-library-item-weight',
            ],
          ],
        ],
      ];
    }

    $cardinality_unlimited = ($element['#cardinality'] === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $remaining = $element['#cardinality'] - count($resource_list);

    // Inform the user of how many items are remaining.
    if (!$cardinality_unlimited) {
      if ($remaining) {
        $cardinality_message = $this->formatPlural($remaining, 'One resource item remaining.', '@count resource items remaining.');
      }
      else {
        $cardinality_message = $this->t('The maximum number of resource items have been selected.');
      }

      // Add a line break between the field message and the cardinality message.
      if (!empty($element['#description'])) {
        $element['#description'] .= '<br />';
      }
      $element['#description'] .= $cardinality_message;
    }

    // Create a new resource library URL with the correct state parameters.
    $remaining = $cardinality_unlimited ? FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED : $remaining;
    // This particular resource library opener needs some extra metadata for its
    // \Drupal\dog_library\LibraryOpenerInterface::getSelectionResponse()
    // to be able to target the element whose 'data-resource-library-widget-value'
    // attribute is the same as $field_widget_id. The entity ID, entity type ID,
    // bundle, field name are used for access checking.
    $entity = $items->getEntity();
    $opener_parameters = [
      'field_widget_id' => $field_widget_id,
      'entity_type_id' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
      'field_name' => $field_name,
    ];
    // Only add the entity ID when we actually have one. The entity ID needs to
    // be a string to ensure that the resource library state generates its
    // tamper-proof hash in a consistent way.
    if (!$entity->isNew()) {
      $opener_parameters['entity_id'] = (string) $entity->id();

      if ($entity->getEntityType()->isRevisionable()) {
        // @phpstan-ignore-next-line
        $opener_parameters['revision_id'] = (string) $entity->getRevisionId();
      }
    }
    $state = ResourceLibraryState::create('dog_library.opener.field_widget', $allowed_resource_type_ids, $remaining, $opener_parameters);

    // Add a button that will load the resource library in a modal using AJAX.
    $element['open_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Add resource'),
      '#name' => $field_name . '-resource-library-open-button' . $id_suffix,
      '#attributes' => [
        'class' => [
          'js-resource-library-open-button',
        ],
      ],
      '#resource_library_state' => $state,
      '#ajax' => [
        'callback' => [static::class, 'openResourceLibrary'],
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Opening resource library.'),
        ],
        // The AJAX system automatically moves focus to the first tabbable
        // element of the modal, so we need to disable refocus on the button.
        'disable-refocus' => TRUE,
      ],
      // Allow the resource library to be opened even if there are form errors.
      '#limit_validation_errors' => [],
    ];

    // When the user returns from the modal to the widget, we want to shift the
    // focus back to the open button. If the user is not allowed to add more
    // items, the button needs to be disabled. Since we can't shift the focus to
    // disabled elements, the focus is set back to the open button via
    // JavaScript by adding the 'data-disabled-focus' attribute.
    // @see Drupal.behaviors.RsourceLibraryWidgetDisableButton
    if (!$cardinality_unlimited && $remaining === 0) {
      $triggering_element = $form_state->getTriggeringElement();
      if ($triggering_element && ($trigger_parents = $triggering_element['#array_parents']) && end($trigger_parents) === 'resource_library_update_widget') {
        // The widget is being rebuilt from a selection change.
        $element['open_button']['#attributes']['data-disabled-focus'] = 'true';
        $element['open_button']['#attributes']['class'][] = 'visually-hidden';
      }
      else {
        // The widget is being built without a selection change, so we can just
        // set the item to disabled now, there is no need to set the focus
        // first.
        $element['open_button']['#disabled'] = TRUE;
        $element['open_button']['#attributes']['class'][] = 'visually-hidden';
      }
    }

    // This hidden field and button are used to add new items to the widget.
    $element['resource_library_selection'] = [
      '#type' => 'hidden',
      '#attributes' => [
        // This is used to pass the selection from the modal to the widget.
        'data-resource-library-widget-value' => $field_widget_id,
      ],
    ];

    // When a selection is made this hidden button is pressed to add new
    // resource items based on the "resource_library_selection" value.
    $element['resource_library_update_widget'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update widget'),
      '#name' => $field_name . '-resource-library-update' . $id_suffix,
      '#ajax' => [
        'callback' => [static::class, 'updateWidget'],
        'wrapper' => $wrapper_id,
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Adding selection.'),
        ],
      ],
      '#attributes' => [
        'data-resource-library-widget-update' => $field_widget_id,
        'class' => ['js-hide'],
      ],
      '#validate' => [[static::class, 'validateItems']],
      '#submit' => [[static::class, 'addItems']],
      // We need to prevent the widget from being validated when no resource
      // items are selected. When a resource field is added in a subform, entity
      // validation is triggered in EntityFormDisplay::validateFormValues().
      // Since the resource item is not added to the form yet, this triggers
      // errors for required resource fields.
      '#limit_validation_errors' => !empty($resource_list) ? $limit_validation_errors : [],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderWidget'];
  }

  /**
   * Prepares the widget's render element for rendering.
   *
   * @param array $element
   *   The element to transform.
   *
   * @return array
   *   The transformed element.
   *
   * @see ::formElement()
   */
  public function preRenderWidget(array $element) {
    if (isset($element['open_button'])) {
      $element['#field_suffix']['open_button'] = $element['open_button'];
      unset($element['open_button']);
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $error, array $form, FormStateInterface $form_state) {
    return $element['id'] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    if (isset($values['selection'])) {
      // Retrieve all properties for all items.
      foreach ($values['selection'] as $delta => &$item) {

        // @todo retrieve the resource type.
        $data = $this->fetcher->retrieveResource($item['id'], 'items');

        if (empty($data)) {
          unset($values['selection'][$delta]);
        }

        $item['type'] = $data['type'];
      }

      // Sort.
      usort($values['selection'], [SortArray::class, 'sortByWeightElement']);

      return $values['selection'];
    }
    return [];
  }

  /**
   * AJAX callback to update the widget when the selection changes.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response to update the selection.
   */
  public static function updateWidget(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $wrapper_id = $triggering_element['#ajax']['wrapper'];

    // This callback is either invoked from the remove button or the update
    // button, which have different nesting levels.
    $is_remove_button = end($triggering_element['#parents']) === 'remove_button';
    $length = $is_remove_button ? -3 : -1;
    if (count($triggering_element['#array_parents']) < abs($length)) {
      throw new \LogicException('The element that triggered the widget update was at an unexpected depth. Triggering element parents were: ' . implode(',', $triggering_element['#array_parents']));
    }
    $parents = array_slice($triggering_element['#array_parents'], 0, $length);
    $element = NestedArray::getValue($form, $parents);

    // Always clear the textfield selection to prevent duplicate additions.
    $element['resource_library_selection']['#value'] = '';

    $field_state = static::getFieldState($element, $form_state);

    // Announce the updated content to screen readers.
    if ($is_remove_button) {
      $announcement = new TranslatableMarkup('Resource has been removed.');
    }
    else {
      $new_items = count(static::getNewResourceItems($element, $form_state));
      $announcement = \Drupal::translation()
        ->formatPlural($new_items, 'Added one resource item.', 'Added @count resource items.');
    }

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand("#$wrapper_id", $element));
    $response->addCommand(new AnnounceCommand($announcement));

    // When the remove button is clicked, shift focus to the next remove button.
    // When the last item is deleted, we no longer have a selection and shift
    // the focus to the open button.
    $removed_last = $is_remove_button && !count($field_state['items']);
    if ($is_remove_button && !$removed_last) {
      // Find the next resource item by weight. The weight of the removed
      // item is added to the field state when it is removed in ::removeItem().
      // If there is no item with a bigger weight, we automatically shift the
      // focus to the previous resource item.
      // @see ::removeItem()
      $removed_item_weight = $field_state['removed_item_weight'];
      $delta_to_focus = 0;
      foreach ($field_state['items'] as $delta => $item_fields) {
        $delta_to_focus = $delta;
        if ($item_fields['weight'] > $removed_item_weight) {
          // Stop directly when we find an item with a bigger weight. We also
          // have to subtract 1 from the delta in this case, since the delta's
          // are renumbered when rebuilding the form.
          $delta_to_focus--;
          break;
        }
      }
      $response->addCommand(new InvokeCommand("#$wrapper_id [data-resource-library-item-delta=$delta_to_focus]", 'focus'));
    }
    // Shift focus to the open button if the user removed the last selected
    // item, or when the user has added items to the selection and is allowed to
    // select more items. When the user is not allowed to add more items, the
    // button needs to be disabled. Since we can't shift the focus to disabled
    // elements, the focus is set via JavaScript by adding the
    // 'data-disabled-focus' attribute and we also don't want to set the focus
    // here.
    // @see Drupal.behaviors.ResourceLibraryWidgetDisableButton
    elseif ($removed_last || (!$is_remove_button && !isset($element['open_button']['#attributes']['data-disabled-focus']))) {
      $response->addCommand(new InvokeCommand("#$wrapper_id .js-resource-library-open-button", 'focus'));
    }

    return $response;
  }

  /**
   * Submit callback for remove buttons.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function removeItem(array $form, FormStateInterface $form_state) {
    // During the form rebuild, formElement() will create field item widget
    // elements using re-indexed deltas, so clear out FormState::$input to
    // avoid a mismatch between old and new deltas. The rebuilt elements will
    // have #default_value set appropriately for the current state of the field,
    // so nothing is lost in doing this.
    // @see self::extractFormValues
    $triggering_element = $form_state->getTriggeringElement();
    $parents = array_slice($triggering_element['#parents'], 0, -2);
    NestedArray::setValue($form_state->getUserInput(), $parents, NULL);

    // Get the parents required to find the top-level widget element.
    if (count($triggering_element['#array_parents']) < 4) {
      throw new \LogicException('Expected the remove button to be more than four levels deep in the form. Triggering element parents were: ' . implode(',', $triggering_element['#array_parents']));
    }
    $parents = array_slice($triggering_element['#array_parents'], 0, -3);
    $element = NestedArray::getValue($form, $parents);

    // Get the field state.
    $path = $element['#parents'];
    $values = NestedArray::getValue($form_state->getValues(), $path);
    $field_state = static::getFieldState($element, $form_state);

    // Get the delta of the item being removed.
    $delta = array_slice($triggering_element['#array_parents'], -2, 1)[0];
    if (isset($values['selection'][$delta])) {
      // Add the weight of the removed item to the field state so we can shift
      // focus to the next/previous item in an easy way.
      $field_state['removed_item_weight'] = $values['selection'][$delta]['weight'];
      $field_state['removed_item_id'] = $triggering_element['#resource_id'];
      unset($values['selection'][$delta]);
      $field_state['items'] = $values['selection'];
      static::setFieldState($element, $form_state, $field_state);
    }

    $form_state->setRebuild();
  }

  /**
   * AJAX callback to open the library modal.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response to open the resource library.
   */
  public static function openResourceLibrary(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $library_ui = \Drupal::service('dog_library.ui_builder')
      ->buildUi($triggering_element['#resource_library_state']);
    return (new AjaxResponse())
      ->addCommand(
        new OpenModalDialogCommand(
          t('Select resource'),
          $library_ui,
          [
            'dialogClass' => 'resource-library-widget-modal',
            'height' => '75%',
            'width' => '75%',
          ]
        )
      );
  }

  /**
   * Validates that newly selected items can be added to the widget.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateItems(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));

    $field_state = static::getFieldState($element, $form_state);
    $resources = static::getNewResourceItems($element, $form_state);
    if (empty($resources)) {
      return;
    }

    // Check if more items were selected than we allow.
    $cardinality_unlimited = ($element['#cardinality'] === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $selection = count($field_state['items']) + count($resources);
    if (!$cardinality_unlimited && ($selection > $element['#cardinality'])) {
      $form_state->setError($element, \Drupal::translation()
        ->formatPlural($element['#cardinality'], 'Only one item can be selected.', 'Only @count items can be selected.'));
    }

    // Validate that each selected resource is of an allowed bundle.
    $fetcher = \Drupal::service('dog.omeka_resource_fetcher');
    assert($fetcher instanceof ResourceFetcherInterface);
    $all_bundles = $fetcher->getTypes();
    $bundle_labels = array_map(function ($bundle) use ($all_bundles) {
      return $all_bundles[$bundle];
    }, $element['#target_types']);

    foreach ($resources as $resource) {
      if ($element['#target_types'] && !in_array($resource['type'], $element['#target_types'], TRUE)) {
        $form_state->setError($element, new TranslatableMarkup('The resource item "@id" is not of an accepted type. Allowed types: @types.', [
          '@id' => $resource['id'],
          '@types' => implode(', ', $bundle_labels),
        ]));
      }
    }
  }

  /**
   * Updates the field state and flags the form for rebuild.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function addItems(array $form, FormStateInterface $form_state) {
    // During the form rebuild, formElement() will create field item widget
    // elements using re-indexed deltas, so clear out FormState::$input to
    // avoid a mismatch between old and new deltas. The rebuilt elements will
    // have #default_value set appropriately for the current state of the field,
    // so nothing is lost in doing this.
    // @see self::extractFormValues
    $button = $form_state->getTriggeringElement();
    $parents = array_slice($button['#parents'], 0, -1);
    $parents[] = 'selection';
    NestedArray::setValue($form_state->getUserInput(), $parents, NULL);

    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));

    $field_state = static::getFieldState($element, $form_state);
    $resources = static::getNewResourceItems($element, $form_state);

    if (!empty($resources)) {
      // Get the weight of the last items and count from there.
      $last_element = end($field_state['items']);
      $weight = $last_element ? $last_element['weight'] : 0;
      foreach ($resources as $resource_item) {
        $field_state['items'][] = [
          'id' => $resource_item['id'],
          'type' => $resource_item['type'],
          'weight' => ++$weight,
        ];
      }
      static::setFieldState($element, $form_state, $field_state);
    }

    $form_state->setRebuild();
  }

  /**
   * Gets newly selected resource items.
   *
   * @param array $element
   *   The wrapping element for this widget.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return object[]
   *   An array of selected resource items.
   */
  protected static function getNewResourceItems(array $element, FormStateInterface $form_state) {
    // Get the new resource IDs passed to our hidden button. We need to use the
    // actual user input, since when #limit_validation_errors is used, the
    // unvalidated user input is not added to the form state.
    // @see FormValidator::handleErrorsWithLimitedValidation()
    $values = $form_state->getUserInput();
    $path = $element['#parents'];
    $value = NestedArray::getValue($values, $path);
    $items = [];

    if (!empty($value['resource_library_selection'])) {
      $ids = explode(',', $value['resource_library_selection']);
      $ids = array_filter($ids, 'is_string');
      if (!empty($ids)) {
        $fetcher = \Drupal::service('dog.omeka_resource_fetcher');
        assert($fetcher instanceof ResourceFetcherInterface);

        foreach ($ids as $id) {

          // @todo retrieve the resource type.
          $resource_type = 'items';
          $data = $fetcher->retrieveResource($id, $resource_type);
          if (empty($data)) {
            continue;
          }

          $items[] = [
            'id' => $id,
            'type' => $resource_type,
          ];
        }

        // @phpstan-ignore-next-line
        return $items;
      }
    }

    return $items;
  }

  /**
   * Gets the field state for the widget.
   *
   * @param array $element
   *   The wrapping element for this widget.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array[]
   *   An array of arrays with the following key/value pairs:
   *   - items: (array) An array of selections.
   *     - id: (int) A resource ID.
   *     - weight: (int) A weight for the selection.
   */
  protected static function getFieldState(array $element, FormStateInterface $form_state) {
    // Default to using the current selection if the form is new.
    $path = $element['#parents'];
    // We need to use the actual user input, since when #limit_validation_errors
    // is used, the unvalidated user input is not added to the form state.
    // @see FormValidator::handleErrorsWithLimitedValidation()
    $values = NestedArray::getValue($form_state->getUserInput(), $path);
    $selection = $values['selection'] ?? [];

    $widget_state = static::getWidgetState($element['#field_parents'], $element['#field_name'], $form_state);
    $widget_state['items'] = $widget_state['items'] ?? $selection;
    return $widget_state;
  }

  /**
   * Sets the field state for the widget.
   *
   * @param array $element
   *   The wrapping element for this widget.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array[] $field_state
   *   An array of arrays with the following key/value pairs:
   *   - items: (array) An array of selections.
   *     - id: (int) A resource ID.
   *     - weight: (int) A weight for the selection.
   */
  protected static function setFieldState(array $element, FormStateInterface $form_state, array $field_state) {
    static::setWidgetState($element['#field_parents'], $element['#field_name'], $form_state, $field_state);
  }

  /**
   * Validates whether the widget is required and contains values.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $form
   *   The form array.
   */
  public static function validateRequired(array $element, FormStateInterface $form_state, array $form) {
    // If a remove button triggered submit, this validation isn't needed.
    if (in_array([
      static::class,
      'removeItem',
    ], $form_state->getSubmitHandlers(), TRUE)) {
      return;
    }

    $field_state = static::getFieldState($element, $form_state);
    // Trigger error if the field is required and no resource is present. Although
    // the Form API's default validation would also catch this, the validation
    // error message is too vague, so a more precise one is provided here.
    if (count($field_state['items']) === 0) {
      $form_state->setError($element, new TranslatableMarkup('@name field is required.', ['@name' => $element['#title']]));
    }
  }

}
