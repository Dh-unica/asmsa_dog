/**
 * @file
 * JavaScript behaviors for the Omeka Cache Statistics block.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Behavior for the Omeka cache statistics block.
   */
  Drupal.behaviors.omekaCacheStats = {
    attach: function (context, settings) {
      // Aggiorna l'aspetto delle statistiche basato sui valori
      $('.omeka-cache-stats-block', context).once('omekaCacheStats').each(function () {
        // Applica classi per il ratio di cache hit
        $('[data-hit-ratio]', this).each(function () {
          var ratio = parseFloat($(this).attr('data-hit-ratio'));
          if (ratio >= 90) {
            $(this).addClass('cache-stats-good');
          } else if (ratio >= 70) {
            $(this).addClass('cache-stats-medium');
          } else {
            $(this).addClass('cache-stats-bad');
          }
        });

        // Applica classi per il tempo di rendering
        $('[data-render-time]', this).each(function () {
          var time = parseFloat($(this).attr('data-render-time'));
          if (time < 500) {
            $(this).addClass('cache-stats-good');
          } else if (time < 1500) {
            $(this).addClass('cache-stats-medium');
          } else {
            $(this).addClass('cache-stats-bad');
          }
        });
      });

      // Aggiorna in tempo reale quando possibile
      if (typeof EventSource !== 'undefined' && settings.dogCacheDebug && settings.dogCacheDebug.refreshEndpoint) {
        var evtSource = new EventSource(settings.dogCacheDebug.refreshEndpoint);
        evtSource.onmessage = function(event) {
          var data = JSON.parse(event.data);
          
          // Aggiorna i valori visualizzati
          if (data.stats) {
            for (var key in data.stats) {
              var target = $('.omeka-cache-stat-' + key);
              if (target.length) {
                target.text(data.stats[key]);
              }
            }
          }
        };
      }
    }
  };

})(jQuery, Drupal);
