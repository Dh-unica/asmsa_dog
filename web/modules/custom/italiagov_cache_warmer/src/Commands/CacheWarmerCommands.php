<?php

namespace Drupal\italiagov_cache_warmer\Commands;

use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * Drush commands per il cache warmer.
 */
class CacheWarmerCommands extends DrushCommands implements ContainerInjectionInterface {

  /**
   * Il servizio di cache warmer.
   *
   * @var \Drupal\italiagov_cache_warmer\Service\CacheWarmerService
   */
  protected $cacheWarmer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('italiagov_cache_warmer.warmer')
    );
  }

  /**
   * Costruttore.
   *
   * @param \Drupal\italiagov_cache_warmer\Service\CacheWarmerService $cache_warmer
   *   Il servizio di cache warmer.
   */
  public function __construct($cache_warmer) {
    parent::__construct();
    $this->cacheWarmer = $cache_warmer;
  }

  /**
   * Esegue il warming della cache per i blocchi Omeka Map.
   *
   * @command italiagov:warm-omeka-cache
   * @aliases warm-omeka
   * @usage drush italiagov:warm-omeka-cache
   *   Esegue il warming della cache per tutti i blocchi Omeka Map.
   */
  public function warmCache() {
    $this->output()->writeln('Avvio del processo di cache warming per i blocchi Omeka Map...');
    
    $count = $this->cacheWarmer->warmCache();
    
    $this->output()->writeln('Cache warming completato. Elaborati ' . $count . ' blocchi.');
    
    return 0;
  }

}
