function convertToTimelineFormat(data) {
  const events = data.map(item => {
    // check if date exists and is not empty
    if (!item.date) {
      return;
    }
      const [day, month, year] = item.date.split('/').map(Number); // Split and convert the date string
      const dateObj = new Date(year, month - 1, day); // Construct a date object

      return {
          start_date: {
              year: dateObj.getFullYear(),
              month: dateObj.getMonth() + 1,
              day: dateObj.getDate()
          },
          text: {
              headline: item.title,
              text: item.date // This can be further styled or formatted
          }
      };
  });

  return {
      events: events
  };
}

function formatDate(dateObj) {
  var year = dateObj.getFullYear();
  var month = ('0' + (dateObj.getMonth() + 1)).slice(-2); // Add 1 because month is 0-indexed
  var day = ('0' + dateObj.getDate()).slice(-2);
  return year + '-' + month + '-' + day;
}


const convertDateFormat = (dateStr) => {
  // if there are only four digits, it's a year, so return 01/01/YYYY
  if (dateStr.length === 4) {
      return `01/01/${dateStr}`;
  }
  if (!dateStr) return null;
  const [year, month, day] = dateStr.split('-');
  if (year && month && day) {
      return `${day}/${month}/${year}`;
  }
  return dateStr;  // Return original if it doesn't match the expected format.
}



// create a closure to store the map_selector and callback
// this is needed because the init_omeka_map function is called
function init_omeka_timeline_closure(timeline_selector, store, callback) {
  return function(finalData) {
    init_omeka_timeline(finalData, timeline_selector, store, callback);
  }
}

function init_omeka_timeline(finalData, timeline_selector, store, callback) {
  store.timelineData = convertToTimelineFormat(finalData); // assuming markersData is your data
  // filter out element without date
  store.timelineData.events = store.timelineData.events.filter(item => item !== undefined);

  store.timeline = new TL.Timeline(
      timeline_selector,
      store.timelineData
  );

  store.timeline.on('change', function(data, altro) {
    // Get the currently selected slide's data
    const selectedData = data.target.config.event_dict[data.unique_id];

    // Find the corresponding marker on the map
    let selectedMarker; //
    store.markers.eachLayer(function(marker) {
        if (marker.options.title === selectedData.text.headline) {
            selectedMarker = marker;
        }
    });

    if (selectedMarker) {
        // Center the map on the marker
        if (selectedMarker) {
          store.map.setView(selectedMarker.getLatLng());
          store.markers.zoomToShowLayer(selectedMarker, function() {
            selectedMarker.openPopup();
          });
      }


        // Open the marker's popup
        selectedMarker.openPopup();
    }


    var selectedDateStr = formatDate(selectedData.start_date.data.date_obj);
    var matchingLayer = null;


    store.confs.wms.forEach(function(wmsLayer) {
        if (selectedDateStr >= wmsLayer.layer_start && selectedDateStr <= wmsLayer.layer_end) {
            matchingLayer = wmsLayer;
        }
    });

    // First, remove all WMS layers from the map
    for (var layerName in store.wmsLayers) {
      store.map.removeLayer(store.wmsLayers[layerName]);
    }

    // Now, add the matching layer to the map
    if (matchingLayer) {
      store.wmsLayers[matchingLayer.layer_name].addTo(store.map);
    }



  });
}

function init_omeka_map(map_selector, store, data, callback) {
          if (!callback) {
            store.confs = data;
          } else {
            store.confs = data;
          }



          // confs.wms => l'url del WMS (in teoria possono essere più di uno, per ora è solo uno)
          // confs.items => array con lat/lon/label per gli oggetti
          // Mappa su tutta la Sardegna come fallback assenza punti
          try {
            // setta la mappa sull'europa occidentale
            store.map = L.map(map_selector).setView([48.69096, 9.140625], 4);
          } catch (error) {
            return;
          }


          // Create a base layer with tiles from Stamen
          var basemap = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            // subdomains: 'abcd',
            // minZoom: 0,
            maxZoom: 18,
            ext: 'png',
          });

          // Create an object to hold the additional WMS layers
          store.wmsLayers = {};

          // confs.wms.push({layer: 'geonode:colonie1', layer_name: 'Pippo', server_link: 'https://geonode.dh.unica.it/geoserver/ows?service=WMS&version=1.1.1&request=GetCapabilities'})

          // Add the WMS layers to the store.wmsLayers object
          if ("wms" in store.confs) {
            for (var i = 0; i < store.confs.wms.length; i++) {
              var wms = store.confs.wms[i];
              if (wms.server_link && wms.layer) {
                store.wmsLayers[wms.layer_name] = L.tileLayer.wms(wms.server_link.split('?')[0], {
                  layers: wms.layer,
                  format: 'image/png',
                  transparent: true, // Use `true` instead of `'true'`
                });
              }
            }
          }

          // Add the base layer to the map
          basemap.addTo(store.map);

          // Create the layer control with the WMS layers only (excluding Terreno)
          store.layerControl = L.control.layers({}, store.wmsLayers).addTo(store.map);
          if (!callback) {
            if ("wms" in store.confs) {
              for (var i = 0; i < store.confs.wms.length; i++) {
                var wms = store.confs.wms[i];
                if (wms.server_link && wms.layer) {
                  store.wmsLayers[wms.layer_name].addTo(store.map);
                }
              }
            }
          }

          const PLACEHOLDER_IMAGE = 'https://placehold.co/200x200';

          let itemIDs = store.confs.items_ids;
          // add id '1226'
          // itemIDs.push('1226');

          const fetchDataForItem = async (id) => {
            // If an opaque response serves your needs, set the request's mode to 'no-cors' to fetch the resource with CORS disabled.

            const itemResponse = await fetch(`${baseURL}items/${id}`);
            const itemData = await itemResponse.json();

            const markerId = itemData["o-module-mapping:marker"][0]["o:id"];
            const markerResponse = await fetch(`${baseURL}mapping_markers/${markerId}`);
            const markerData = await markerResponse.json();

            return [itemData, markerData];
          }


          const markerData = [];

          var finalData = [];
          for (itemID of itemIDs) {
            itemData = store.confs.omeka_items[itemID].full_item;
            locationData = store.confs.omeka_items[itemID].location;
            finalData.push({
              id: itemData["o:id"],
              title: itemData["dcterms:title"] && itemData["dcterms:title"][0]["@value"],
              firstName: itemData["foaf:firstName"] && itemData["foaf:firstName"][0]["@value"],
              surname: itemData["foaf:surname"] && itemData["foaf:surname"][0]["@value"],
              gender: itemData["foaf:gender"] && itemData["foaf:gender"][0]["@value"],
              birthPlace: itemData["person:placeOfBirth"] && itemData["person:placeOfBirth"][0]["@value"],
              birthDate: itemData["dcterms:date"] && itemData["dcterms:date"][0]["@value"],
              country: itemData["edm:country"] && itemData["edm:country"][0]["@value"],
              profession: itemData["san:professione"] && itemData["san:professione"][0]["@value"],
              membership: itemData["foaf:membershipClass"] && itemData["foaf:membershipClass"][0]["@value"],
              researchArea: itemData["vivo:hasResearchArea"] && itemData["vivo:hasResearchArea"][0]["@value"],
              archivalHistory: itemData["http://culturalis.org/oad#:archivalHistory"] && itemData["http://culturalis.org/oad#:archivalHistory"][0]["@value"],
              location: itemData["oc:location"] && itemData["oc:location"][0]["@value"],
              date: convertDateFormat(itemData["dcterms:date"]?.[0]["@value"]) || null,
              latitude: locationData['o-module-mapping:lat'] || null,
              longitude: locationData['o-module-mapping:lng'] || null,
              type: "omeka",
              thumbnail: {
                large: itemData["thumbnail_display_urls"]?.large || null,
                medium: itemData["thumbnail_display_urls"]?.medium || null,
                square: itemData["thumbnail_display_urls"]?.square || null,
              },
              absolute_url: store.confs.omeka_items[itemID].absolute_url
            });
          }

          let drupalsItems = store.confs.drupal_items;

          drupalsItems.forEach((item) => {
            finalData.push({
                id: item.id,
                title: item.title,
                date: item.data ? convertDateFormat(item.data[0].value) : null,
                latitude: item.geoloc && item.geoloc[0].lat,
                longitude: item.geoloc && item.geoloc[0].lon,
                thumbnail: {
                    large: null,
                    medium: null,
                    square: item.image,
                },
                type: 'drupal',
                resource_url: item.resource_url,
                image: item.image,
            });
        });

        let mediaItems = store.confs.media_items;
        mediaItems.forEach(item => {
          finalData.push({
              id: item.id,
              title: item.title && item.title[0].value,
              date: item.data ? convertDateFormat(item.data[0].value) : null,
              latitude: item.geoloc && item.geoloc[0].lat,
              longitude: item.geoloc && item.geoloc[0].lon,
              thumbnail: {
                  large: null,
                  medium: null,
                  square: null,
              },
              type: item.type,
              resource_url: item.resource_url,
              image: item.image,
              video_url: item.video_url && item.video_url[0].value,
              audio_player: item.audio_player,
              url_document: item.url_document,
              description: item.description,
              video_player: item.video_player,
          });
      });



          store.markers = L.markerClusterGroup(
            // {
            //   // zoom levels at which clusters are disabled (prevents clustering at this zoom level and below)
            //   disableClusteringAtZoom: 12,
            //   // radius of each cluster when clustering points (in pixels)
            //   maxClusterRadius: 40,
            // }
          );

          finalData.forEach(item => {
            // Create a new marker using the lat, long data
            let marker = L.circleMarker([item.latitude, item.longitude], {
              radius: 10,
              fillColor: "#c4c4c4",
              color: "#c4c4c4",
              weight: 1,
              opacity: 1,
              fillOpacity: 0.8,
              title: item.title,
          });
          let popupContent = "";
          if (item.type == "omeka") {
            // Construct the popup content
            popupContent = `
                <a href="${item.absolute_url}" target="_blank">
                <strong>${item.title}</strong><br>
                <img src="${item.thumbnail.square || PLACEHOLDER_IMAGE}" alt="${item.title}" style="width:200px;height:auto;"></a>
            `;
          } else if (item.type == "drupal") {
            // Construct the popup content
            popupContent = `
              <a href="${item.resource_url}" target="_blank">
                <strong>${item.title}</strong><br>
                <img src="${item.thumbnail.square || PLACEHOLDER_IMAGE}" alt="${item.title}" style="width:200px;height:auto;">
              </a>
            `;
          } else if (item.type == "audio") {
            // Construct the popup content
            popupContent = `
              <strong>${item.title}</strong><br>
              ${item.audio_player}
            `;
          } else if (item.type == "document") {
            popupContent = `
              <strong>${item.title}</strong><br>
              <a href="${item.url_document}" target="_blank">Scarica documento</a>
            `;
          } else if (item.type == "image") {
            popupContent = `
              <strong>${item.title}</strong><br>
              <img src="${item.image}" alt="${item.title}" style="width:200px;height:auto;">
            `;
          } else if (item.type == "remote_video") {
            popupContent = `
              <strong>${item.title}</strong><br>
            `;
            popupContent += item.video_player;
          }

            if (popupContent) {
              // Bind the popup to the marker
              marker.bindPopup(popupContent);
            }

            // Select timeline slide on marker click
            marker.on('click', function() {
              // check if exists store.timeline
              if (!store.timeline) {
                return;
              }
              // Get the marker's title
              const markerTitle = this.options.title;

              // Find the corresponding slide index in the timeline
              const slideIndex = store.timelineData.events.findIndex(event => event.text.headline === markerTitle);
              const unique_id = store.timelineData.events[slideIndex].unique_id;
              if (slideIndex !== -1) {
                  // Go to the slide (this assumes the 'start_at_slide' option for the timeline starts at 0)
                  store.timeline.goToId(unique_id);
              }

              // if(item.type == "remote_video") {
              //     let popup = document.createElement('div');
              //     popup.innerHTML = `
              //         <div class="custom-popup">
              //             <div class="custom-popup-content">
              //                 <button class="close-btn">X</button>
              //                 <h3 class="video-title">${item.title}</h3>
              //                 <iframe class="video-frame" width="100%" height="315" src="${item.video_url}" frameborder="0" allowfullscreen></iframe>
              //             </div>
              //         </div>
              //     `;
              //     document.body.appendChild(popup);

              //     popup.querySelector('.close-btn').addEventListener('click', function() {
              //         popup.remove();
              //     });
              // }
            });


            // Add the marker to the markers cluster group
            store.markers.addLayer(marker);
        });

        store.map.addLayer(store.markers);
        store.map.fitBounds(store.markers.getBounds());

        // Timeline stuff

        callback && callback(finalData);

}


(function () {
    'use strict';
    Drupal.behaviors.italiagov = {
      attach: function (context, settings) {

        if (drupalSettings.is_omeka_map) {
          var omeka_map_ids = Object.keys(drupalSettings.omeka_map); // ['omeka_map_18', 'omeka_map_17']
          var omeka_map_wrapper = document.querySelectorAll('.omeka-map-wrapper');
          omeka_map_ids.forEach(function(omeka_map_id, index) {
            // extract the id from the string
            var id = omeka_map_id.split('_')[2];
            var div_id = 'omeka-mappa-' + id;
            // check if the div exists
            if (document.getElementById(div_id)) {
              return;
            }
            // get the wrapper
            var wrapper = omeka_map_wrapper[index];

            // Construct the popup using a plain string
            var popupHTML = '<div class="custom-popup hidden">' +
            '<div class="custom-popup-content">' +
            '<button class="close-btn">X</button>' +
            '<h3 class="video-title">Video Title</h3>' +
            '<iframe class="video-frame" width="100%" height="315" src="" frameborder="0" allowfullscreen></iframe>' +
            '</div>' +
            '</div>';

            // Convert the string to a DOM element
            var div_popup = document.createElement('div');
            div_popup.innerHTML = popupHTML;

            // Append the constructed DOM element to your wrapper
            wrapper.appendChild(div_popup);

            // make a div with the id like <div id="omeka-mappa-ID" style="height: 400px;"></div>

            var div = document.createElement('div');
            div.id = div_id;
            div.style.height = '400px';
            // append the div to the wrapper
            wrapper.appendChild(div);

            // create a nee window object with + id
            window['omeka_map_' + id] = {};
            // init map
            init_omeka_map('omeka-mappa-' + id, window['omeka_map_' + id], drupalSettings.omeka_map[omeka_map_id]);

          });
        }

        if (drupalSettings.is_omeka_timeline) {
          var omeka_map_timeline_ids = Object.keys(drupalSettings.omeka_map_timeline); // ['omeka_map_timeline_18', 'omeka_map_timeline_17']
          var omeka_map_timeline_wrapper = document.querySelectorAll('.omeka-map-timeline-wrapper');
          omeka_map_timeline_ids.forEach(function(omeka_map_timeline_id, index) {
            // extract the id from the string
            var id = omeka_map_timeline_id.split('_')[3];
            var div_id = 'omeka-mappa-timeline-' + id;
            // check if the div exists
            if (document.getElementById(div_id)) {
              return;
            }
            // get the wrapper
            var wrapper = omeka_map_timeline_wrapper[index];

            // Construct the popup using a plain string
            var popupHTML = '<div class="custom-popup hidden">' +
            '<div class="custom-popup-content">' +
            '<button class="close-btn">X</button>' +
            '<h3 class="video-title">Video Title</h3>' +
            '<iframe class="video-frame" width="100%" height="315" src="" frameborder="0" allowfullscreen></iframe>' +
            '</div>' +
            '</div>';

            // Convert the string to a DOM element
            wrapper.innerHTML += popupHTML;

            // make a div with the id like <div id="omeka-mappa-ID" style="height: 400px;"></div>
            var div = document.createElement('div');
            div.id = div_id;
            div.style.height = '400px';
            // append the div to the wrapper
            wrapper.appendChild(div);

            // make a div with the id like <div id="timeline-embed-timeline-ID" style="width: 100%; height: 600px;"></div>
            var div_timeline = document.createElement('div');
            div_timeline.id = 'timeline-embed-timeline-' + id;
            div_timeline.style.width = '100%';
            div_timeline.style.height = '600px';
            // append the div to the wrapper
            wrapper.appendChild(div_timeline);

            // create a nee window object with + id
            window['omeka_map_timeline_' + id] = {};
            // init map
            init_omeka_map(
              'omeka-mappa-timeline-' + id,
              window['omeka_map_timeline_' + id],
              drupalSettings.omeka_map_timeline[omeka_map_timeline_id],
              init_omeka_timeline_closure('timeline-embed-timeline-' + id, window['omeka_map_timeline_' + id])
              );

          });
        }

      }
    };
  })(jQuery, Drupal);
