<?php

namespace Drupal\dog\Plugin\views\filter;

use Drupal\dog\Plugin\views\argument\ResourceType as ArgumentResourceType;
use Drupal\dog\Service\ResourceFetcherInterface;
use Drupal\views\Plugin\views\filter\InOperator;
use Drupal\views\Plugin\views\HandlerBase;
use Drupal\views_remote_data\Plugin\views\query\RemoteDataQuery;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the ResourceCollection class.
 *
 * @ViewsFilter("dog_omeka_resource_collection")
 *
 * @package Drupal\dog\Plugin\views\filter
 */
class ResourceCollection extends InOperator {

  /**
   * {@inheritdoc}
   */
  protected $valueFormType = 'select';

  /**
   * The resource fetcher service.
   *
   * @var \Drupal\dog\Service\ResourceFetcherInterface
   */
  protected $fetcher;

  /**
   * Construct new ResourceCollection instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\dog\Service\ResourceFetcherInterface $fetcher
   *   The fetcher resource.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ResourceFetcherInterface $fetcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fetcher = $fetcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {

    $fetcher = $container->get('dog.omeka_resource_fetcher');
    assert($fetcher instanceof ResourceFetcherInterface);

    return new static($configuration, $plugin_id, $plugin_definition, $fetcher);
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    foreach ($this->fetcher->getItemSets() as $item_set) {
      $this->valueOptions[$item_set['o:id']] = $item_set['o:title'];
    }

    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function operators() {
    return [
      'in' => [
        'title' => $this->t('Is one of'),
        'short' => $this->t('in'),
        'short_single' => $this->t('='),
        'method' => 'opSimple',
        'values' => 1,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE): void {
    assert($this->query instanceof RemoteDataQuery);
    $this->query->addWhere(
      $this->options['group'],
      'item_set_id',
      $this->value,
      '='
    );
  }

}
