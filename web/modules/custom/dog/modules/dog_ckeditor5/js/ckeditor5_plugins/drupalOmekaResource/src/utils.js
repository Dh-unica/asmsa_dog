/**
 * Gets the preview container element from the omeka resource element.
 *
 * @param {Iterable.<module:engine/view/element~Element>} children
 *   The child elements.
 * @return {null|module:engine/view/element~Element}
 *   The preview child element if available.
 */
export function getPreviewContainer(children) {
  for (const child of children) {
    if (child.hasAttribute('data-drupal-omeka-resource-preview')) {
      return child;
    }

    if (child.childCount) {
      const recursive = getPreviewContainer(child.getChildren());
      // Return only if preview container was found within this element's children.
      if (recursive) {
        return recursive;
      }
    }
  }

  return null;
}
