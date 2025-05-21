<?php

/**
 * @file
 * Tema e preprocessore per le funzionalità del modulo dog_cache_debug.
 */

/**
 * Prepara le variabili per il template del blocco delle statistiche della cache Omeka.
 *
 * Default template: omeka-cache-stats-block.html.twig.
 *
 * @param array $variables
 *   Un array di variabili passate al template.
 */
function template_preprocess_omeka_cache_stats_block(array &$variables) {
  // Assicurati che tutte le statistiche siano disponibili nel template
  $variables['#attached']['library'][] = 'dog_cache_debug/debug_styles';
}
