/**
 * Caricamento progressivo degli elementi Omeka sulla mappa.
 * 
 * Questo script implementa una strategia di caricamento progressivo
 * per gestire mappe con molti elementi senza sovraccaricare il browser
 * o il server.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.omekaMapLoader = {
    attach: function (context, settings) {
      // Controlliamo se siamo in una pagina con mappa Omeka
      if (!drupalSettings.omeka_map || !drupalSettings.omeka_chunk_loading) {
        return;
      }

      // Per ogni blocco mappa
      Object.keys(drupalSettings.omeka_map).forEach(function(mapId) {
        const mapConfig = drupalSettings.omeka_map[mapId];
        
        // Verifichiamo se ci sono elementi da caricare
        if (!mapConfig.remaining_chunks || !mapConfig.remaining_chunks.length) {
          return;
        }

        const totalChunks = mapConfig.remaining_chunks.length;
        const apiBaseUrl = mapConfig.api_base_url;
        const mapStore = window.mapStores[mapId];
        
        // Mostriamo un indicatore di progresso
        const progressContainer = $('<div class="omeka-map-progress"></div>');
        const progressBar = $('<div class="progress-bar"></div>');
        const progressText = $('<div class="progress-text">Caricamento elementi: 0%</div>');
        
        progressContainer.append(progressBar);
        progressContainer.append(progressText);
        $('#' + mapId).prepend(progressContainer);
        
        // Funzione per aggiornare la barra di progresso
        function updateProgress(loaded, total) {
          const percent = Math.round((loaded / total) * 100);
          progressBar.css('width', percent + '%');
          progressText.text('Caricamento elementi: ' + percent + '%');
          
          if (loaded === total) {
            // Nascondiamo la barra di progresso dopo 1 secondo
            setTimeout(function() {
              progressContainer.fadeOut();
            }, 1000);
          }
        }
        
        // Carica progressivamente i chunk di elementi
        function loadChunks(chunkIndex = 0) {
          if (chunkIndex >= mapConfig.remaining_chunks.length) {
            updateProgress(totalChunks, totalChunks);
            return;
          }
          
          const chunk = mapConfig.remaining_chunks[chunkIndex];
          const chunkIds = chunk.map(id => 'items/' + id);
          
          // Carica gli elementi in batch
          Promise.all(chunkIds.map(id => 
            fetch(apiBaseUrl + '/api/' + id)
              .then(response => response.json())
              .catch(error => {
                console.error('Errore caricamento elemento:', error);
                return null;
              })
          )).then(items => {
            // Aggiungi i nuovi marker alla mappa
            items.filter(item => item !== null).forEach(item => {
              // Prepara i dati per il marker
              const locationData = getLocationFromItem(item);
              if (!locationData) return;
              
              const markerData = {
                id: item['o:id'],
                title: item['dcterms:title'] ? item['dcterms:title'][0]['@value'] : 'Senza titolo',
                date: item['dcterms:date'] ? item['dcterms:date'][0]['@value'] : null,
                latitude: locationData.latitude,
                longitude: locationData.longitude,
                type: "omeka",
                thumbnail: {
                  large: item['thumbnail_display_urls']?.large || null,
                  medium: item['thumbnail_display_urls']?.medium || null,
                  square: item['thumbnail_display_urls']?.square || null,
                },
                absolute_url: drupalSettings.omeka_map[mapId].site_url + '/item/' + item['o:id']
              };
              
              // Crea e aggiungi il marker
              addMarkerToMap(markerData, mapStore);
            });
            
            // Aggiorna la progress bar
            updateProgress(chunkIndex + 1, totalChunks);
            
            // Carica il prossimo chunk dopo un breve intervallo
            setTimeout(() => loadChunks(chunkIndex + 1), 200);
          });
        }
        
        // Estrae le coordinate geografiche dall'elemento
        function getLocationFromItem(item) {
          if (!item || !item['o-module-mapping:feature']) return null;
          
          try {
            const feature = item['o-module-mapping:feature'][0];
            const coordinates = feature['o-module-mapping:geography-coordinates'];
            if (coordinates && coordinates.length >= 2) {
              return {
                latitude: coordinates[1],
                longitude: coordinates[0]
              };
            }
          } catch (e) {
            console.error('Errore estrazione coordinate:', e);
          }
          return null;
        }
        
        // Aggiunge un marker alla mappa
        function addMarkerToMap(item, store) {
          // Crea un nuovo marker
          let marker = L.circleMarker([item.latitude, item.longitude], {
            radius: 10,
            fillColor: "#ff0000",
            color: "#000000",
            weight: 1,
            opacity: 1,
            fillOpacity: 0.8,
            title: item.title,
          });
          
          // Crea il popup
          let popupContent = `
            <a href="${item.absolute_url}" target="_blank">
            <strong>${item.title}</strong><br>
            <img src="${item.thumbnail.square || ''}" alt="${item.title}" style="width:200px;height:auto;"></a>
          `;
          
          // Aggiungi il popup al marker
          marker.bindPopup(popupContent);
          
          // Aggiungi il marker al cluster
          store.markers.addLayer(marker);
        }
        
        // Inizia il caricamento dopo 1 secondo dalla renderizzazione iniziale
        setTimeout(() => loadChunks(), 1000);
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
