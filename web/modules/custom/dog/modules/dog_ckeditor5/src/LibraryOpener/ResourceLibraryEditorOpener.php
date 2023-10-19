<?php

namespace Drupal\dog_ckeditor5\LibraryOpener;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dog\Service\ResourceFetcherInterface;
use Drupal\dog_library\LibraryOpener\ResourceLibraryOpenerInterface;
use Drupal\dog_library\ResourceLibraryState;
use Drupal\editor\Ajax\EditorDialogSave;

/**
 * Defines the ResourceLibraryEditorOpener class.
 *
 * @package Drupal\dog_ckeditor5\LibraryOpener
 */
class ResourceLibraryEditorOpener implements ResourceLibraryOpenerInterface {

  /**
   * The resource fetcher.
   *
   * @var \Drupal\dog\Service\ResourceFetcherInterface
   */
  protected $fetcher;

  /**
   * The text format entity storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $filterStorage;

  /**
   * Construct new ResourceLibraryEditorOpener instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\dog\Service\ResourceFetcherInterface $fetcher
   *   The resource fetcher service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ResourceFetcherInterface $fetcher) {
    $this->filterStorage = $entity_type_manager->getStorage('filter_format');
    $this->fetcher = $fetcher;
  }

  /**
   * {@inheritdoc}
   */
  public function getSelectionResponse(ResourceLibraryState $state, array $selected_ids) {
    // @todo add $resource_type.
    $resource_type = 'items';
    $selected_resource = $this->fetcher->retrieveResource(reset($selected_ids), $resource_type);

    $response = new AjaxResponse();
    $values = [
      'attributes' => [
        'data-entity-type' => 'omeka_resource',
        'data-entity-bundle' => $resource_type,
        'data-entity-id' => $selected_resource['id'],
        'data-view-mode' => 'default',
      ],
    ];
    $response->addCommand(new EditorDialogSave($values));

    return $response;
  }

}
