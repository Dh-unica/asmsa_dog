import {Plugin} from 'ckeditor5/src/core';
import {toWidget, Widget} from 'ckeditor5/src/widget';
import InsertDrupalOmekaResourceCommand from './drupalomekaresourcecommand';
import {getPreviewContainer} from './utils';

/**
 * Handles the transformation from the CKEditor 5 UI to Drupal-specific markup.
 *
 * CKEditor 5 plugins do not work directly with the DOM. They are defined as
 * plugin-specific data models that are then converted to markup that
 * is inserted in the DOM.
 */
export default class DrupalOmekaResourceEditing extends Plugin {

  /**
   * @inheritdoc
   */
  static get requires() {
    return [Widget];
  }

  /**
   * @inheritdoc
   */
  init() {
    this.attrs = {
      drupalOmekaResourceEntityType: 'data-entity-type',
      drupalOmekaResourceEntityId: 'data-entity-id',
      drupalOmekaResourceEntityBundle: 'data-entity-bundle',
      drupalOmekaResourceViewMode: 'data-view-mode',
    };
    this.converterAttributes = [
      'drupalOmekaResourceEntityId',
      'drupalOmekaResourceEntityBundle',
      'drupalOmekaResourceEntityType',
    ];
    const options = this.editor.config.get('drupalOmekaResource');
    if (!options) {
      return;
    }
    const {previewURL, themeError} = options;
    this.previewUrl = previewURL;
    this.labelError = Drupal.t('Preview failed');
    this.themeError =
      themeError ||
      `
      <p>${Drupal.t(
        'An error occurred while trying to preview the omeka resource. Please save your work and reload this page.',
      )}<p>
    `;

    this._defineSchema();
    this._defineConverters();
    // this._defineListeners();

    this.editor.commands.add(
      'insertDrupalOmekaResource',
      new InsertDrupalOmekaResourceCommand(this.editor),
    );
  }

  /**
   * Fetches preview from the server.
   *
   * @param {module:engine/model/element~Element} modelElement
   *   The model element which preview should be loaded.
   * @return {Promise<{preview: string, label: string}>}
   *   A promise that returns an object.
   *
   * @private
   */
  async _fetchPreview(modelElement) {
    const query = {
      text: this._renderElement(modelElement),
      id: modelElement.getAttribute('drupalOmekaResourceEntityId'),
      bundle: modelElement.getAttribute('drupalOmekaResourceEntityBundle'),
    };

    const response = await fetch(
      `${this.previewUrl}?${new URLSearchParams(query)}`,
      {
        headers: {
          'X-Drupal-OmekaResourcePreview-CSRF-Token':
          this.editor.config.get('drupalOmekaResource').previewCsrfToken,
        },
      },
    );
    if (response.ok) {
      const label = query.id;
      const preview = await response.text();
      return {label, preview};
    }

    return {label: this.labelError, preview: this.themeError};
  }


  /**
   * Registers drupalOmekaResource as a block element in the DOM converter.
   */
  _defineSchema() {
    const schema = this.editor.model.schema;
    schema.register('drupalOmekaResource', {
      allowWhere: '$block',
      isObject: true,
      isContent: true,
      isBlock: true,
      allowAttributes: Object.keys(this.attrs),
    });
    // Register `<drupal-omeka-resource>` as a block element in the DOM
    // converter. This ensures that the DOM converter knows to handle the
    // `<drupal-omeka-resource>` as a block element.
    this.editor.editing.view.domConverter.blockElements.push('drupal-omeka-resource');
  }

  /**
   * Defines handling of drupal resource element in the content lifecycle.
   */
  _defineConverters() {
    const conversion = this.editor.conversion;

    // Upcast Converters: determine how existing HTML is interpreted by the
    // editor. These trigger when an editor instance loads.
    conversion
      .for('upcast')
      .elementToElement({
        view: {
          name: 'drupal-omeka-resource',
        },
        model: 'drupalOmekaResource',
      });

    // Data Downcast Converters: converts stored model data into HTML.
    // These trigger when content is saved.
    conversion
      .for('dataDowncast')
      .elementToElement({
        model: 'drupalOmekaResource',
        view: {
          name: 'drupal-omeka-resource',
        },
      });

    // Editing Downcast Converters. These render the content to the user for
    // editing, i.e. this determines what gets seen in the editor. These trigger
    // after the Data Upcast Converters, and are re-triggered any time there
    // are changes to any of the models' properties.
    conversion
      .for('editingDowncast')
      .elementToElement({
        model: 'drupalOmekaResource',
        view: (modelElement, {writer}) => {

          const container = writer.createContainerElement('figure', {
            class: 'drupal-omeka-resource'
          });

          if (!this.previewUrl) {
            // If preview URL isn't available, insert empty preview element
            // which indicates that preview couldn't be loaded.
            const resourcePreview = writer.createRawElement('div', {
              'data-drupal-omeka-resource-preview': 'unavailable',
            });
            writer.insert(writer.createPositionAt(container, 0), resourcePreview);
          }
          writer.setCustomProperty('drupalOmekaResource', true, container);

          return toWidget(container, writer, {
            label: Drupal.t('Drupal Omeka Resource'),
          });
        },
      })
      .add((dispatcher) => {
        const converter = (event, data, conversionApi) => {
          const viewWriter = conversionApi.writer;
          const modelElement = data.item;
          const container = conversionApi.mapper.toViewElement(data.item);

          // Search for preview container recursively from its children because
          // the preview container could be wrapped with an element such as
          // `<a>`.
          let resource = getPreviewContainer(container.getChildren());

          // Use pre-existing resource preview container if one exists. If the
          // preview element doesn't exist, create a new element.
          if (resource) {
            // Stop processing if resource preview is unavailable or a preview is already loading.
            if (resource.getAttribute('data-drupal-omeka-resource-preview') !== 'ready') {
              return;
            }

            // Preview was ready meaning that a new preview can be loaded.
            // "Change the attribute to loading to prepare for the loading of
            // the updated preview. Preview is kept intact so that it remains
            // interactable in the UI until the new preview has been rendered.
            viewWriter.setAttribute(
              'data-drupal-omeka-resource-preview',
              'loading',
              resource,
            );
          } else {
            resource = viewWriter.createRawElement('div', {
              'data-drupal-omeka-resource-preview': 'loading',
            });
            viewWriter.insert(viewWriter.createPositionAt(container, 0), resource);
          }

          this._fetchPreview(modelElement).then(({label, preview}) => {
            if (!resource) {
              // Nothing to do if associated preview wrapped no longer exist.
              return;
            }
            // CKEditor 5 doesn't support async view conversion. Therefore, once
            // the promise is fulfilled, the editing view needs to be modified
            // manually.
            this.editor.editing.view.change((writer) => {
              const resourcePreview = writer.createRawElement(
                'div',
                {
                  'data-drupal-omeka-resource-preview': 'ready',
                  'aria-label': label
                },
                (domElement) => {
                  domElement.innerHTML = preview;
                },
              );
              // Insert the new preview before the previous preview element to
              // ensure that the location remains same even if it is wrapped
              // with another element.
              writer.insert(writer.createPositionBefore(resource), resourcePreview);
              writer.remove(resource);
            });
          });
        };

        // List all attributes that should trigger re-rendering of the
        // preview.
        this.converterAttributes.forEach((attribute) => {
          dispatcher.on(`attribute:${attribute}:drupalOmekaResource`, converter);
        });

        return dispatcher;
      });

    // Set attributeToAttribute conversion for all supported attributes.
    Object.keys(this.attrs).forEach((modelKey) => {
      const attributeMapping = {
        model: {
          key: modelKey,
          name: 'drupalOmekaResource',
        },
        view: {
          name: 'drupal-omeka-resource',
          key: this.attrs[modelKey],
        },
      };
      // Attributes should be rendered only in dataDowncast to avoid having
      // unfiltered data-attributes on the Drupal Omeka Resource widget.
      conversion.for('dataDowncast').attributeToAttribute(attributeMapping);
      conversion.for('upcast').attributeToAttribute(attributeMapping);
    });
  }

  /**
   * OmekaResourceFilterController::preview requires the saved element.
   *
   * Not previewing data-caption since it does not get updated by new changes.
   */
  _renderElement(modelElement) {
    // Create model document fragment which contains the model element so that
    // it can be stringified using the dataDowncast.
    const modelDocumentFragment = this.editor.model.change((writer) => {
      const modelDocumentFragment = writer.createDocumentFragment();
      // Create shallow clone of the model element to ensure that the original
      // model element remains untouched and that the caption is not rendered
      // into the preview.
      const clonedModelElement = writer.cloneElement(modelElement, false);
      // Remove attributes from the model element to ensure they are not
      // downcast into the preview request. For example, the `linkHref` model
      // attribute would downcast into a wrapping `<a>` element, which the
      // preview endpoint would not be able to handle.
      const attributeIgnoreList = ['linkHref'];
      attributeIgnoreList.forEach((attribute) => {
        writer.removeAttribute(attribute, clonedModelElement);
      });
      writer.append(clonedModelElement, modelDocumentFragment);

      return modelDocumentFragment;
    });

    return this.editor.data.stringify(modelDocumentFragment);
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'drupalOmekaResourceEditing';
  }

}
