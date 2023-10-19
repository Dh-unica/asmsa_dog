import {Plugin} from 'ckeditor5/src/core';
import {ButtonView} from 'ckeditor5/src/ui';
import icon from '../../../../icons/drupalOmekaResource.svg';

/**
 * Provides the toolbar button to insert a drupal omeka resource element.
 */
export default class DrupalOmekaResourceUI extends Plugin {
  init() {
    const editor = this.editor;
    const options = this.editor.config.get('drupalOmekaResource');
    if (!options) {
      return;
    }

    const {libraryURL, openDialog, dialogSettings = {}} = options;
    if (!libraryURL || typeof openDialog !== 'function') {
      return;
    }

    // This will register the toolbar button.
    editor.ui.componentFactory.add('drupalOmekaResource', (locale) => {
      const command = editor.commands.get('insertDrupalOmekaResource');
      const buttonView = new ButtonView(locale);

      // Create the toolbar button.
      buttonView.set({
        label: Drupal.t('Insert Drupal Omeka Resource'),
        icon: icon,
        tooltip: true,
      });

      // Bind the state of the button to the command.
      buttonView.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');

      // Execute the command when the button is clicked (executed).
      this.listenTo(buttonView, 'execute', () => {
        openDialog(
          libraryURL,
          ({attributes}) => {
            editor.execute('insertDrupalOmekaResource', attributes);
          },
          dialogSettings,
        );
      });

      return buttonView;
    });
  }
}
