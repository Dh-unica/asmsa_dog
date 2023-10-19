<?php

namespace Drupal\dog\EventSubscriber;

use Drupal\views\Plugin\views\pager\Full;
use Drupal\views\ResultRow;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\dog\Service\ResourceFetcherInterface;
use Drupal\views_remote_data\Events\RemoteDataQueryEvent;
use function Amp\Iterator\filter;

/**
 * Defines the OmekaResourceSubscriber class.
 *
 * @package Drupal\dog\EventSubscriber
 */
class OmekaResourceSubscriber implements EventSubscriberInterface {

  use LoggerChannelTrait;
  use MessengerTrait;

  /**
   * The fetcher service.
   *
   * @var \Drupal\dog\Service\ResourceFetcherInterface
   */
  protected $fetcher;

  /**
   * Construct new OmekaResourceSubscriber instance.
   *
   * @param \Drupal\dog\Service\ResourceFetcherInterface $fetcher
   *   The fetcher service.
   */
  public function __construct(ResourceFetcherInterface $fetcher) {
    $this->fetcher = $fetcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RemoteDataQueryEvent::class => 'onQuery',
    ];
  }

  /**
   * Subscribes to populate the view results.
   *
   * @param \Drupal\views_remote_data\Events\RemoteDataQueryEvent $event
   *   The event.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function onQuery(RemoteDataQueryEvent $event): void {

    $supported_bases = ['views_remote_data_dog_omeka_resource'];
    $base_tables = array_keys($event->getView()->getBaseTables());


    if (count(array_intersect($supported_bases, $base_tables)) > 0) {
      $conditions = $event->getConditions();
      ksort($conditions);

      $resource_type = 'items';
      $parameters = [];

      foreach ($conditions as $group) {
        foreach ($group['conditions'] as $group_condition) {

          $field_name = $group_condition['field'];
          $field_name = array_filter($field_name);
          $field_name = reset($field_name);

          $parameters[$field_name] = $group_condition['value'];
        }
      }

      // Retrieve the page selected and items_per_page.
      if (!$event->getView()->getPager() instanceof Full) {
        $this->messenger()
          ->addWarning("Pager %pager_class not supported!", [
            '%pager_class' => get_class($event->getView()->getPager()),
          ]);

        return;
      }
      $items_per_page = $event->getLimit();
      $page = $event->getOffset() / $items_per_page;

      try {

        // Variable used for retrieve the total results from API.
        $total_results = 0;

        // Use API to search the data.
        $data = $this->fetcher->search($resource_type, $parameters, $page + 1, $items_per_page, $total_results);

        // Inject results.
        foreach ($data as $item) {
          $event->addResult(new ResultRow((array) $item));
        }

        // Update total items for pager.
        $event
          ->getView()
          ->getPager()->total_items = $total_results;
      }
      catch (\Exception $exception) {
        $this->getLogger('dog')->error($exception->getMessage());
      }
    }
  }

}
