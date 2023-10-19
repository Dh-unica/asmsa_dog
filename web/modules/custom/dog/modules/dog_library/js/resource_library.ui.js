/**
 * @file resource_library.ui.js
 */
(($, Drupal, window) => {

  /**
   * Wrapper object for the current state of the resource library.
   */
  Drupal.ResourceLibrary = {

    /**
     * When a user interacts with the resource library we want the selection to
     * persist as long as the resource library modal is opened. We temporarily
     * store the selected items while the user filters the resource library
     * view.
     */
    currentSelection: [],
  };

  /**
   * Command to update the current resource library selection.
   *
   * @param {Drupal.Ajax} [ajax]
   *   The Drupal Ajax object.
   * @param {object} response
   *   Object holding the server response.
   * @param {number} [status]
   *   The HTTP status code.
   */
  Drupal.AjaxCommands.prototype.updateResourceLibrarySelection = function (
    ajax,
    response,
    status,
  ) {
    Object.values(response.resourceIds).forEach((value) => {
      Drupal.ResourceLibrary.currentSelection.push(value);
    });
  };

  /**
   * Load resource library displays through AJAX.
   *
   * Standard AJAX links (using the 'use-ajax' class) replace the entire library
   * dialog. When navigating to a resource library views display, we only want
   * to load the changed views display content. This is not only more efficient,
   * but also provides a more accessible user experience for screen readers.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches behavior to content in the resource library.
   */
  Drupal.behaviors.ResourceLibraryViewsDisplay = {
    attach(context) {
      const $view = $(context).hasClass('.js-resource-library-view')
        ? $(context)
        : $('.js-resource-library-view', context);

      // Add a class to the view to allow it to be replaced via AJAX.
      $view
        .closest('.views-element-container')
        .attr('id', 'resource-library-view');

      // We would ideally use a generic JavaScript specific class to detect the
      // display links. Since we have no good way of altering display links yet,
      // this is the best we can do for now.
      $(
        once(
          'resource-library-views-display-link',
          '.views-display-link-widget, .views-display-link-widget_table',
          context,
        ),
      ).on('click', (e) => {
        e.preventDefault();
        e.stopPropagation();

        const $link = $(e.currentTarget);

        // Add a loading and display announcement for screen reader users.
        let loadingAnnouncement =  Drupal.t('Loading table view.');
        let displayAnnouncement = Drupal.t('Changed to table view.');
        let focusSelector = '.views-display-link-widget_table';

        // Replace the library view.
        const ajaxObject = Drupal.ajax({
          wrapper: 'resource-library-view',
          url: e.currentTarget.href,
          dialogType: 'ajax',
          progress: {
            type: 'fullscreen',
            message: loadingAnnouncement || Drupal.t('Please wait...'),
          },
        });

        // Override the AJAX success callback to announce the updated content
        // to screen readers.
        if (displayAnnouncement || focusSelector) {
          const success = ajaxObject.success;
          ajaxObject.success = function (response, status) {
            success.bind(this)(response, status);
            // The AJAX link replaces the whole view, including the clicked
            // link. Move the focus back to the clicked link when the view is
            // replaced.
            if (focusSelector) {
              $(focusSelector).focus();
            }
            // Announce the new view is loaded to screen readers.
            if (displayAnnouncement) {
              Drupal.announce(displayAnnouncement);
            }
          };
        }

        ajaxObject.execute();

        // Announce the new view is being loaded to screen readers.
        if (loadingAnnouncement) {
          Drupal.announce(loadingAnnouncement);
        }
      });
    },
  };

  /**
   * Update the resource library selection when loaded or resource items are
   * selected.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches behavior to select resource items.
   */
  Drupal.behaviors.ResourceLibraryItemSelection = {
    attach(context, settings) {
      const $form = $(
        '.js-resource-library-views-form',
        context,
      );
      const currentSelection = Drupal.ResourceLibrary.currentSelection;

      if (!$form.length) {
        return;
      }

      const $resourceItems = $(
        '.js-resource-library-item input[type="checkbox"]',
        $form,
      );

      /**
       * Disable resource items.
       *
       * @param {jQuery} $items
       *   A jQuery object representing the resource items that should be
       *   disabled.
       */
      function disableItems($items) {
        $items
          .prop('disabled', true)
          .closest('.js-resource-library-item')
          .addClass('resource-library-item--disabled');
      }

      /**
       * Enable resource items.
       *
       * @param {jQuery} $items
       *   A jQuery object representing the resource items that should be
       *   enabled.
       */
      function enableItems($items) {
        $items
          .prop('disabled', false)
          .closest('.js-resource-library-item')
          .removeClass('resource-library-item--disabled');
      }

      /**
       * Update the number of selected items in the button pane.
       *
       * @param {number} remaining
       *   The number of remaining slots.
       */
      function updateSelectionCount(remaining) {
        // When the remaining number of items is a negative number, we allow an
        // unlimited number of items. In that case we don't want to show the
        // number of remaining slots.
        const selectItemsText =
          remaining < 0
            ? Drupal.formatPlural(
              currentSelection.length,
              '1 item selected',
              '@count items selected',
            )
            : Drupal.formatPlural(
              remaining,
              '@selected of @count item selected',
              '@selected of @count items selected',
              {
                '@selected': currentSelection.length,
              },
            );
        // The selected count div could have been created outside of the
        // context, so we unfortunately can't use context here.
        $('.js-resource-library-selected-count').html(selectItemsText);
      }

      // Update the selection array and the hidden form field when a resource
      // item is selected.
      $(once('resource-item-change', $resourceItems)).on('change', (e) => {
        const id = e.currentTarget.value;

        // Update the selection.
        const position = currentSelection.indexOf(id);
        if (e.currentTarget.checked) {
          // Check if the ID is not already in the selection and add if needed.
          if (position === -1) {
            currentSelection.push(id);
          }
        } else if (position !== -1) {
          // Remove the ID when it is in the current selection.
          currentSelection.splice(position, 1);
        }

        const resourceLibraryModalSelection = document.querySelector(
          '#resource-library-modal-selection',
        );

        if (resourceLibraryModalSelection) {
          // Set the selection in the hidden form element.
          resourceLibraryModalSelection.value = currentSelection.join();
          $(resourceLibraryModalSelection).trigger('change');
        }

        // Set the selection in the resource library add form. Since the form is
        // not necessarily loaded within the same context, we can't use the
        // context here.
        document
          .querySelectorAll('.js-resource-library-add-form-current-selection')
          .forEach((item) => {
            item.value = currentSelection.join();
          });
      });

      // The hidden selection form field changes when the selection is updated.
      $(
        once(
          'resource-library-selection-change',
          $form.find('#resource-library-modal-selection'),
        ),
      ).on('change', (e) => {
        updateSelectionCount(settings.resource_library.selection_remaining);

        // Prevent users from selecting more items than allowed.
        if (
          currentSelection.length === settings.resource_library.selection_remaining
        ) {
          disableItems($resourceItems.not(':checked'));
          enableItems($resourceItems.filter(':checked'));
        } else {
          enableItems($resourceItems);
        }
      });

      // Apply the current selection to the resource library view. Changing the
      // checkbox values triggers the change event for the resource items. The
      // change event handles updating the hidden selection field for the form.
      currentSelection.forEach((value) => {
        $form
          .find(`input[type="checkbox"][value="${value}"]`)
          .prop('checked', true)
          .trigger('change');
      });

      // Add the selection count to the button pane when a resource library
      // dialog is created.
      if (!once('resource-library-selection-info', 'html').length) {
        return;
      }
      $(window).on('dialog:aftercreate', () => {
        // Since the dialog HTML is not part of the context, we can't use
        // context here.
        const $buttonPane = $(
          '.resource-library-widget-modal .ui-dialog-buttonpane',
        );
        if (!$buttonPane.length) {
          return;
        }
        $buttonPane.append(Drupal.theme('resourceLibrarySelectionCount'));
        updateSelectionCount(settings.resource_library.selection_remaining);
      });
    },
  };

  /**
   * Clear the current selection.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches behavior to clear the selection when the library modal closes.
   */
  Drupal.behaviors.ResourceLibraryModalClearSelection = {
    attach() {
      if (!once('resource-library-clear-selection', 'html').length) {
        return;
      }
      $(window).on('dialog:afterclose', () => {
        Drupal.ResourceLibrary.currentSelection = [];
      });
    },
  };

  /**
   * Theme function for the selection count.
   *
   * @return {string}
   *   The corresponding HTML.
   */
  Drupal.theme.resourceLibrarySelectionCount = function () {
    return `<div class="resource-library-selected-count js-resource-library-selected-count" role="status" aria-live="polite" aria-atomic="true"></div>`;
  };

})(jQuery, Drupal, window);
