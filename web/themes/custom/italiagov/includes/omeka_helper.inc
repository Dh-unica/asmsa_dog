<?php

/**
 * @file
 * Contiene funzioni helper per l'integrazione con Omeka.
 */

/**
 * Estrae le informazioni sulla data da un elemento Omeka.
 *
 * @param mixed $item
 *   L'elemento Omeka (può essere un oggetto o un array).
 *
 * @return array|null
 *   Informazioni sulla data o null se non disponibili.
 */
function italiagov_extract_date_info($item) {
  $date_info = null;
  
  if (is_object($item) && isset($item->{'dcterms:date'})) {
    $date_info = $item->{'dcterms:date'};
  } elseif (is_array($item) && isset($item['dcterms:date'])) {
    $date_info = $item['dcterms:date'];
  }
  
  // Converti in un formato semplificato
  if ($date_info) {
    if (is_array($date_info) && !empty($date_info[0])) {
      if (is_string($date_info[0])) {
        return $date_info[0];
      } elseif (is_object($date_info[0]) && isset($date_info[0]->{'@value'})) {
        return $date_info[0]->{'@value'};
      } elseif (is_array($date_info[0]) && isset($date_info[0]['@value'])) {
        return $date_info[0]['@value'];
      }
    }
  }
  
  return null;
}

/**
 * Estrae l'URL della thumbnail da un elemento Omeka.
 *
 * @param mixed $item
 *   L'elemento Omeka (può essere un oggetto o un array).
 *
 * @return string|null
 *   URL della thumbnail o null se non disponibile.
 */
function italiagov_extract_thumbnail_url($item) {
  // L'oggetto Omeka contiene thumbnail o URL immagine
  $thumbnail_url = null;
  
  // Estrai prima dalla proprietà thumbnail_url, se disponibile
  if (is_object($item) && isset($item->{'thumbnail_url'})) {
    return $item->{'thumbnail_url'};
  } elseif (is_array($item) && isset($item['thumbnail_url'])) {
    return $item['thumbnail_url'];
  }
  
  // In alternativa, cerca nella proprietà "thumbnail_display_urls"
  if (is_object($item) && isset($item->{'thumbnail_display_urls'})) {
    if (isset($item->{'thumbnail_display_urls'}->{'medium'})) {
      return $item->{'thumbnail_display_urls'}->{'medium'};
    }
  } elseif (is_array($item) && isset($item['thumbnail_display_urls'])) {
    if (isset($item['thumbnail_display_urls']['medium'])) {
      return $item['thumbnail_display_urls']['medium'];
    }
  }
  
  // Se non troviamo nulla, restituisci null
  return null;
}

/**
 * Estrae la classe di risorsa da un elemento Omeka.
 *
 * @param mixed $item
 *   L'elemento Omeka (può essere un oggetto o un array).
 *
 * @return string|null
 *   Classe di risorsa o null se non disponibile.
 */
function italiagov_extract_resource_class($item) {
  if (is_object($item) && isset($item->{'o:resource_class'})) {
    $resource_class = $item->{'o:resource_class'};
    if (is_object($resource_class) && isset($resource_class->{'o:label'})) {
      return $resource_class->{'o:label'};
    } elseif (is_array($resource_class) && isset($resource_class['o:label'])) {
      return $resource_class['o:label'];
    }
  } elseif (is_array($item) && isset($item['o:resource_class'])) {
    $resource_class = $item['o:resource_class'];
    if (is_array($resource_class) && isset($resource_class['o:label'])) {
      return $resource_class['o:label'];
    }
  }
  
  return null;
}
