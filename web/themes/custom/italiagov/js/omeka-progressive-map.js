/**
 * @file
 * Implementazione del caricamento progressivo per la mappa Omeka.
 * Questo script carica gli elementi della mappa in piccoli batch tramite AJAX
 * migliorando drasticamente le prestazioni e prevenendo timeout.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.omekaProgressiveMap = {
    attach: function (context, settings) {
      // Per ogni mappa Omeka definita nelle impostazioni
      if (settings.is_omeka_map) {
        $.each(settings.omeka_map, function (mapId, mapData) {
          var mapContainer = $('#omeka-map-' + mapData.block_id, context);
          
          // Se questa mappa è già stata inizializzata, non continuare
          if (mapContainer.hasClass('map-initialized')) {
            return;
          }
          
          // Inizializza la mappa e contrassegnala come inizializzata
          mapContainer.addClass('map-initialized');
          
          // Inizializza la mappa Leaflet
          var map = L.map('omeka-map-' + mapData.block_id).setView([41.9027, 12.4963], 6);
          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
          }).addTo(map);
          
          // Crea un layer group per i marker
          var markers = L.layerGroup().addTo(map);
          
          // Tieni traccia dei marker già aggiunti alla mappa
          var addedMarkers = {};
          var totalMarkers = 0;
          
          // Informazioni sul caricamento progressivo
          var loadingProgress = $('.omeka-map-loading .progress');
          var itemsLoaded = 0;
          var totalItems = mapData.items_ids.length;
          var batchSize = mapData.batch_size || 15;
          
          // Funzione per aggiornare lo stato di caricamento
          function updateLoadingStatus() {
            var percent = Math.round((itemsLoaded / totalItems) * 100);
            loadingProgress.text(percent + '%');
            
            // Nascondi il messaggio di caricamento quando abbiamo finito
            if (itemsLoaded >= totalItems) {
              $('.omeka-map-loading').fadeOut();
            }
          }
          
          // Funzione per aggiungere marker alla mappa
          function addMarkers(items) {
            $.each(items, function(itemId, itemData) {
              // Se questo marker è già stato aggiunto, salta
              if (addedMarkers[itemId]) {
                return;
              }
              
              // Estrai le informazioni sulla posizione
              var location = itemData.location;
              if (!location || !location.latitude || !location.longitude) {
                return;
              }
              
              // Crea il marker
              var marker = L.marker([location.latitude, location.longitude]);
              
              // Crea il popup con le informazioni sull'elemento
              var popupContent = '<div class="omeka-item-popup">';
              
              // Aggiungi immagine se disponibile
              if (itemData.full_item && itemData.full_item.thumbnail_display_urls && itemData.full_item.thumbnail_display_urls.medium) {
                popupContent += '<div class="popup-image"><img src="' + itemData.full_item.thumbnail_display_urls.medium + '" alt=""></div>';
              }
              
              // Aggiungi titolo
              var title = '';
              if (itemData.full_item && itemData.full_item['dcterms:title'] && itemData.full_item['dcterms:title'][0]) {
                title = itemData.full_item['dcterms:title'][0]['@value'];
              }
              popupContent += '<h3>' + title + '</h3>';
              
              // Aggiungi data se disponibile
              if (itemData.full_item && itemData.full_item['dcterms:date'] && itemData.full_item['dcterms:date'][0]) {
                var date = itemData.full_item['dcterms:date'][0]['@value'];
                popupContent += '<p class="date">' + date + '</p>';
              }
              
              // Aggiungi link all'elemento
              if (itemData.absolute_url) {
                popupContent += '<a href="' + itemData.absolute_url + '" class="view-item">Visualizza scheda</a>';
              }
              
              popupContent += '</div>';
              
              // Aggiungi il popup al marker
              marker.bindPopup(popupContent);
              
              // Aggiungi il marker al layer group
              marker.addTo(markers);
              
              // Segna questo marker come aggiunto
              addedMarkers[itemId] = true;
              totalMarkers++;
            });
            
            // Se è il primo batch, centra la mappa sui marker
            if (Object.keys(addedMarkers).length === totalMarkers && totalMarkers > 0) {
              var bounds = markers.getBounds();
              if (bounds.isValid()) {
                map.fitBounds(bounds);
              }
            }
          }
          
          // Funzione per caricare un batch di elementi
          function loadItemsBatch(offset) {
            var idsToLoad = mapData.items_ids.slice(offset, offset + batchSize);
            if (idsToLoad.length === 0) {
              return;
            }
            
            // Mostra il progresso di caricamento
            updateLoadingStatus();
            
            // Carica i dati tramite AJAX
            $.ajax({
              url: '/api/omeka-map-data',
              type: 'GET',
              data: {
                ids: idsToLoad.join(','),
                offset: 0,
                limit: batchSize
              },
              dataType: 'json',
              success: function(response) {
                if (response && response.items) {
                  // Aggiungi i marker alla mappa
                  addMarkers(response.items);
                  
                  // Aggiorna il numero di elementi caricati
                  itemsLoaded += Object.keys(response.items).length;
                  updateLoadingStatus();
                  
                  // Carica il prossimo batch se non abbiamo finito
                  if (offset + batchSize < totalItems) {
                    setTimeout(function() {
                      loadItemsBatch(offset + batchSize);
                    }, 500); // Piccola pausa per non sovraccaricare il server
                  }
                }
              },
              error: function(xhr, status, error) {
                console.error('Errore nel caricamento dei dati della mappa:', error);
                // Riprova dopo un breve timeout in caso di errore
                setTimeout(function() {
                  loadItemsBatch(offset);
                }, 2000);
              }
            });
          }
          
          // Inizia a caricare i dati
          if (mapData.items_ids && mapData.items_ids.length > 0) {
            // Avvia il caricamento progressivo
            loadItemsBatch(0);
          }
          
          // Aggiungi eventuali layer WMS
          if (mapData.wms && mapData.wms.length > 0) {
            for (var i = 0; i < mapData.wms.length; i++) {
              var wmsLayer = L.tileLayer.wms(mapData.wms[i].url, {
                layers: mapData.wms[i].layer,
                format: 'image/png',
                transparent: true
              }).addTo(map);
            }
          }
        });
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
