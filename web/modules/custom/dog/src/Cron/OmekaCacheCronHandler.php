<?php

namespace Drupal\dog\Cron;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\dog\Service\OmekaCacheService;

/**
 * Handles cron-based Omeka resource cache refreshing.
 */
class OmekaCacheCronHandler {

  /**
   * The state key for the last cron run timestamp.
   */
  const STATE_LAST_CRON_RUN = 'dog.omeka_cache.last_cron_run';

  /**
   * The interval between cache refresh operations (24 hours in seconds).
   */
  const CACHE_REFRESH_INTERVAL = 86400;

  /**
   * The cache service.
   *
   * @var \Drupal\dog\Service\OmekaCacheService
   */
  protected $cacheService;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new OmekaCacheCronHandler.
   *
   * @param \Drupal\dog\Service\OmekaCacheService $cache_service
   *   The cache service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   */
  public function __construct(
    OmekaCacheService $cache_service,
    StateInterface $state,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->cacheService = $cache_service;
    $this->state = $state;
    $this->logger = $logger_factory->get('dog_omeka_cache');
  }

  /**
   * Executes the cache refresh during cron run if needed.
   *
   * This method is called during hook_cron() implementation.
   */
  public function processCache() {
    $last_run = $this->state->get(self::STATE_LAST_CRON_RUN, 0);
    $now = time();

    // Only refresh cache if it's been at least 24 hours since last refresh.
    if (($now - $last_run) >= self::CACHE_REFRESH_INTERVAL) {
      $this->logger->info('Starting cron-based Omeka resource cache refresh');

      // We use a smaller batch size for cron to prevent timeout issues.
      $batch_size = 20;
      $context = [];

      // Process a single batch now. The full refresh will take multiple cron runs.
      $result = $this->cacheService->updateCache($batch_size, $context);

      // Store the resulting context for the next cron run.
      if (isset($context['sandbox'])) {
        $this->state->set('dog.omeka_cache.batch_context', $context);
      }

      // If the batch is finished, mark as completed.
      if (!empty($context['finished']) && $context['finished'] >= 1) {
        $this->logger->info('Cron-based cache refresh completed successfully');
        $this->state->delete('dog.omeka_cache.batch_context');
        $this->state->set(self::STATE_LAST_CRON_RUN, $now);
      }
      else {
        $this->logger->info('Cron-based cache refresh in progress: @progress%', [
          '@progress' => isset($context['finished']) ? round($context['finished'] * 100) : 0,
        ]);
      }

      return $result;
    }

    return TRUE;
  }

}
