/**
 * @file
 * JavaScript per il modulo omeka_stats_block.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Comportamento per gestire la visualizzazione dei dettagli degli elementi Omeka.
   */
  Drupal.behaviors.omekaStatsDetails = {
    attach: function (context, settings) {
      // Inizializza tutti i dettagli come nascosti.
      $('.item-details', context).once('omekaStatsDetails').hide();

      // Gestisce il click sui toggle.
      $('.toggle-details', context).once('omekaStatsDetails').on('click', function () {
        var targetId = $(this).data('target');
        var $target = $('#' + targetId);
        
        if ($target.is(':visible')) {
          $target.slideUp(200);
          $(this).text(Drupal.t('Mostra dettagli'));
        } else {
          $target.slideDown(200);
          $(this).text(Drupal.t('Nascondi dettagli'));
        }
      });
    }
  };

})(jQuery, Drupal);
