/**
 * @file resource_library.widget.js
 */
(($, Drupal, Sortable) => {

  /**
   * Allows users to re-order their selection with drag+drop.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches behavior to re-order selected resource items.
   */
  Drupal.behaviors.ResourceLibraryWidgetSortable = {
    attach(context) {
      // Allow resource items to be re-sorted with drag+drop in the widget.
      const selection = context.querySelectorAll('.js-resource-library-selection');
      selection.forEach((widget) => {
        Sortable.create(widget, {
          draggable: '.js-resource-library-item',
          handle: '.js-resource-library-item-preview',
          onEnd: () => {
            $(widget)
              .children()
              .each((index, child) => {
                $(child).find('.js-resource-library-item-weight')[0].value = index;
              });
          },
        });
      });
    },
  };

  /**
   * Allows selection order to be set without drag+drop for accessibility.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches behavior to toggle the weight field for resource items.
   */
  Drupal.behaviors.ResourceLibraryWidgetToggleWeight = {
    attach(context) {
      const strings = {
        show: Drupal.t('Show resource item weights'),
        hide: Drupal.t('Hide resource item weights'),
      };
      const resourceLibraryToggle = once(
        'resource-library-toggle',
        '.js-resource-library-widget-toggle-weight',
        context,
      );
      $(resourceLibraryToggle).on('click', (e) => {
        e.preventDefault();
        const $target = $(e.currentTarget);
        e.currentTarget.textContent = $target.hasClass('active')
          ? strings.show
          : strings.hide;
        $target
          .toggleClass('active')
          .closest('.js-resource-library-widget')
          .find('.js-resource-library-item-weight')
          .parent()
          .toggle();
      });
      resourceLibraryToggle.forEach((item) => {
        item.textContent = strings.show;
      });

      $(once('resource-library-toggle', '.js-resource-library-item-weight', context))
        .parent()
        .hide();
    },
  };

  /**
   * Disable the open button when the user is not allowed to add more items.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches behavior to disable the resource library open button.
   */
  Drupal.behaviors.ResourceLibraryWidgetDisableButton = {
    attach(context) {
      // When the user returns from the modal to the widget, we want to shift
      // the focus back to the open button. If the user is not allowed to add
      // more items, the button needs to be disabled. Since we can't shift the
      // focus to disabled elements, the focus is set back to the open button
      // via JavaScript by adding the 'data-disabled-focus' attribute.
      once(
        'resource-library-disable',
        '.js-resource-library-open-button[data-disabled-focus="true"]',
        context,
      ).forEach((button) => {
        $(button).focus();

        // There is a small delay between the focus set by the browser and the
        // focus of screen readers. We need to give screen readers time to shift
        // the focus as well before the button is disabled.
        setTimeout(() => {
          $(button).attr('disabled', 'disabled');
        }, 50);
      });
    },
  };

})(jQuery, Drupal, Sortable);
