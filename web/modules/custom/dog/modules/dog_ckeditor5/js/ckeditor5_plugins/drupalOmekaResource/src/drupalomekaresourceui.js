import { Plugin } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';
import icon from '../../../../icons/drupalOmekaResource.svg';

/**
 * Provides a toolbar button and contextual balloon for managing Drupal Omeka resource elements.
 */
export default class DrupalOmekaResourceUI extends Plugin {
  static get requires() {
    // Temporarily remove ContextualBalloon requirement
    // return [ContextualBalloon];
    return [];
  }

  init() {
    const editor = this.editor;

    // Fetch plugin configuration options
    const options = editor.config.get('drupalOmekaResource');
    if (!options) return;

    const { libraryURL, openDialog, dialogSettings = {} } = options;
    if (!libraryURL || typeof openDialog !== 'function') return;

    // Register toolbar button
    this._registerToolbarButton(libraryURL, openDialog, dialogSettings);

    // Initialize schema and conversions for alignment attributes
    this._setupSchemaAndConversions();

    // Temporarily disable balloon functionality
    /*
    this._balloon = editor.plugins.get(ContextualBalloon);
    this._createBalloonView();

    // Add event listener to handle element selection and display the balloon
    editor.editing.view.document.on('click', () => this._handleDocumentClick());
    */
  }

  /**
   * Registers the toolbar button for inserting Drupal Omeka resources.
   */
  _registerToolbarButton(libraryURL, openDialog, dialogSettings) {
    const editor = this.editor;

    editor.ui.componentFactory.add('drupalOmekaResource', (locale) => {
      const command = editor.commands.get('insertDrupalOmekaResource');
      const buttonView = new ButtonView(locale);

      buttonView.set({
        label: Drupal.t('Insert Drupal Omeka Resource'),
        icon: icon,
        tooltip: true
      });

      buttonView.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');

      buttonView.on('execute', () => {
        openDialog(
          libraryURL,
          ({ attributes }) => editor.execute('insertDrupalOmekaResource', attributes),
          dialogSettings
        );
      });

      return buttonView;
    });
  }

  /**
   * Extends the schema and sets up conversions for alignment attributes.
   */
  _setupSchemaAndConversions() {
    const editor = this.editor;

    // Extend schema
    editor.model.schema.extend('$block', {
      allowAttributes: ['alignment']
    });

    // Define upcast conversion
    editor.conversion.for('upcast').attributeToAttribute({
      view: {
        name: 'div',
        key: 'class',
        value: /align-(left|right)/
      },
      model: {
        key: 'alignment',
        value: (viewElement) => {
          if (viewElement.hasClass('align-left')) return 'left';
          if (viewElement.hasClass('align-right')) return 'right';
          return null;
        }
      }
    });

    // Define downcast conversion
    editor.conversion.for('downcast').attributeToAttribute({
      model: 'alignment',
      view: (alignment) => {
        if (alignment === 'left') return { key: 'class', value: 'align-left' };
        if (alignment === 'right') return { key: 'class', value: 'align-right' };
        return null;
      }
    });
  }

  /**
   * Creates the balloon view with alignment buttons.
   * Temporarily disabled
   */
  /*
  _createBalloonView() {
    const buttonLeft = this._createAlignmentButton('Float Left', 'left');
    const buttonRight = this._createAlignmentButton('Float Right', 'right');
    const buttonClear = this._createAlignmentButton('Clear Alignment', null);

    this._balloonView = new View();
    this._balloonView.setTemplate({
      tag: 'div',
      attributes: {
        class: 'ck-alignment-balloon'
      },
      children: [buttonLeft, buttonRight, buttonClear]
    });
  }
  */

  /**
   * Creates an alignment button for the balloon view.
   */
  _createAlignmentButton(label, alignment) {
    const editor = this.editor;
    const buttonView = new ButtonView(editor.locale);

    buttonView.set({
      label,
      withText: true,
      tooltip: true
    });

    buttonView.on('execute', () => {
      const model = editor.model;
      const selectedElement = model.document.selection.getSelectedElement();

      model.change((writer) => {
        if (selectedElement) {
          if (alignment) {
            writer.setAttribute('alignment', alignment, selectedElement);
          } else {
            writer.removeAttribute('alignment', selectedElement);
          }
        }
      });

      this._hideBalloon();
    });

    return buttonView;
  }

  /**
   * Handles document click events to display or hide the balloon.
   * Temporarily disabled
   */
  /*
  _handleDocumentClick() {
    const editor = this.editor;
    const viewElement = editor.editing.view.document.selection.getFirstPosition().parent;

    if (viewElement.is('element', 'div')) {
      this._showBalloon(viewElement);
    } else {
      this._hideBalloon();
    }
  }
  */

  /**
   * Shows the balloon panel for a given view element.
   * Temporarily disabled
   */
  /*
  _showBalloon(viewElement) {
    if (!this._balloon.hasView(this._balloonView)) {
      this._balloon.add({
        view: this._balloonView,
        position: this._getBalloonPosition(viewElement)
      });
    } else {
      this._balloon.updatePosition(this._getBalloonPosition(viewElement));
    }
  }
  */

  /**
   * Hides the balloon panel.
   * Temporarily disabled
   */
  /*
  _hideBalloon() {
    if (this._balloon.hasView(this._balloonView)) {
      this._balloon.remove(this._balloonView);
    }
  }
  */

  /**
   * Calculates the position of the balloon relative to the view element.
   * Temporarily disabled
   */
  /*
  _getBalloonPosition(viewElement) {
    const editor = this.editor;
    const domElement = editor.editing.view.domConverter.mapViewToDom(viewElement);

    return { target: domElement };
  }
  */
}
