<?php

namespace Drupal\dog_ckeditor5\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\dog\Service\ResourceFetcherInterface;
use Drupal\dog_library\ResourceLibraryState;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the DrupalOmekaResource class.
 *
 * @package Drupal\dog_ckeditor5\Plugin\CKEditor5Plugin
 */
class DrupalOmekaResource extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface {

  /**
   * The resource fetcher.
   *
   * @var \Drupal\dog\Service\ResourceFetcherInterface
   */
  protected $fetcher;

  /**
   * DrupalOmekaResource constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param \Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\dog\Service\ResourceFetcherInterface $fetcher
   *   The resource fetcher service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    CKEditor5PluginDefinition $plugin_definition,
    ResourceFetcherInterface $fetcher
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fetcher = $fetcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {

    $fetcher = $container->get('dog.omeka_resource_fetcher');
    assert($fetcher instanceof ResourceFetcherInterface);

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $fetcher,
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $resource_bundle_ids = array_keys($this->fetcher->getTypes());

    $static_plugin_config['drupalOmekaResource']['previewURL'] = Url::fromRoute('dog_ckeditor5.omeka_resource.filter.preview')
      ->setRouteParameter('filter_format', $editor->getFilterFormat()->id())
      ->toString(TRUE)
      ->getGeneratedUrl();
    $static_plugin_config['drupalOmekaResource']['previewCsrfToken'] = \Drupal::csrfToken()
      ->get('X-Drupal-OmekaResourcePreview-CSRF-Token');

    // Making the title for editor omeka resource embed translatable.
    $static_plugin_config['drupalOmekaResource']['dialogSettings']['title'] = $this->t('Add or select Drupal Omeka Resource');

    if ($editor->hasAssociatedFilterFormat()) {
      $resource_embed_filter = $editor->getFilterFormat()
        ->filters()
        ->get('dog_omeka_resource_embed');
      // Optionally limit the allowed resource types based on the
      // OmekaResourceEmbed setting. If the setting is empty, do not limit
      // the options.
      // TODO: not exist yet this settings?!.
      if (!empty($resource_embed_filter->settings['allowed_resource_types'])) {
        $resource_bundle_ids = array_intersect_key($resource_bundle_ids, $resource_embed_filter->settings['allowed_resource_types']);
      }
    }

    $state = ResourceLibraryState::create(
      'dog_ckeditor5.opener.field_widget',
      $resource_bundle_ids,
      1,
      ['filter_format_id' => $editor->getFilterFormat()->id()],
    );

    $library_url = Url::fromRoute('dog_library.ui')
      ->setOption('query', $state->all())
      ->toString(TRUE)
      ->getGeneratedUrl();

    $dynamic_plugin_config = $static_plugin_config;
    $dynamic_plugin_config['drupalOmekaResource']['libraryURL'] = $library_url;
    return $dynamic_plugin_config;
  }

}
