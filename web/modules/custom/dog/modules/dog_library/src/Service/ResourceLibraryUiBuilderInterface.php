<?php

namespace Drupal\dog_library\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\dog_library\ResourceLibraryState;

/**
 * Defines the ResourceLibraryUiBuilderInterface trait.
 *
 * @package Drupal\dog_library\Service
 */
interface ResourceLibraryUiBuilderInterface {

  /**
   * Build the resource library UI.
   *
   * @param \Drupal\dog_library\ResourceLibraryState|null $state
   *   (optional) The current state of the resource library, derived from the
   *   current request.
   *
   * @return array
   *   The render array for the resource library.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function buildUi(ResourceLibraryState $state = NULL);

  /**
   * Check access to the resource library.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\dog_library\ResourceLibraryState|null $state
   *   (optional) The current state of the resource library, derived from the
   *   current request.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function checkAccess(AccountInterface $account, ResourceLibraryState $state = NULL);

}
