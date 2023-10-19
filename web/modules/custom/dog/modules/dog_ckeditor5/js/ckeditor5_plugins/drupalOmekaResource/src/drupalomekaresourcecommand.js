import {Command} from 'ckeditor5/src/core';

/**
 * The insert drupal Omeka Resource command.
 *
 * The command is registered by the `DrupalOmekaResourceEditing` plugin as `insertDrupalOmekaResource`.
 *
 * In order to insert resource at the current selection position, execute the
 * command and pass the attributes desired in the drupal-omeka-resource element:
 *
 * @example
 *    editor.execute('insertDrupalOmekaResource', {
 *      'data-entity-type': 'omeka_resource',
 *      'data-entity-bundle': 'items',
 *      'data-entity-uuid': 'id',
 *      'data-view-mode': 'default',
 *    });
 *
 * @private
 */
export default class InsertDrupalOmekaResourceCommand extends Command {
  execute(attributes) {
    const drupalOmekaResourceEditing = this.editor.plugins.get('drupalOmekaResourceEditing');

    // Create object that contains supported data-attributes in view data by
    // flipping `DrupalOmekaResourceEditing.attrs` object (i.e. keys from object become
    // values and values from object become keys).
    const dataAttributeMapping = Object.entries(drupalOmekaResourceEditing.attrs).reduce(
      (result, [key, value]) => {
        result[value] = key;
        return result;
      },
      {},
    );

    // This converts data-attribute keys to keys used in model.
    const modelAttributes = Object.keys(attributes).reduce(
      (result, attribute) => {
        if (dataAttributeMapping[attribute]) {
          result[dataAttributeMapping[attribute]] = attributes[attribute];
        }
        return result;
      },
      {},
    );

    this.editor.model.change((writer) => {
      // Insert at the current selection position
      // in a way that will result in creating a valid model structure.
      this.editor.model.insertContent(
        createDrupalOmekaResource(writer, modelAttributes),
      );
    });
  }

  refresh() {
    const model = this.editor.model;
    const selection = model.document.selection;

    // Determine if the cursor (selection) is in a position where adding a
    // simpleBox is permitted. This is based on the schema of the model(s)
    // currently containing the cursor.
    const allowedIn = model.schema.findAllowedParent(
      selection.getFirstPosition(),
      'drupalOmekaResource',
    );

    // If the cursor is not in a location where a resource can be added, return
    // null so the addition doesn't happen.
    this.isEnabled = allowedIn !== null;
  }

}

/**
 * Create the resource.
 */
function createDrupalOmekaResource(writer, attributes) {
  return writer.createElement('drupalOmekaResource', attributes);
}
