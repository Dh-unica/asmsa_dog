import {Plugin} from 'ckeditor5/src/core';
import DrupalOmekaResourceEditing from './drupalomekaresourceediting';
import DrupalOmekaResourceUI from './drupalomekaresourceui';

/**
 * Main entry point to the drupal omeka resource.
 */
export default class DrupalOmekaResource extends Plugin {

  /**
   * @inheritdoc
   */
  static get requires() {
    return [DrupalOmekaResourceEditing, DrupalOmekaResourceUI];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'drupalOmekaResource';
  }

}
