<?php

namespace Drupal\dog_library\Service;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\dog_library\ResourceLibraryState;
use Drupal\views\ViewExecutableFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Defines the ResourceLibraryUiBuilder class.
 *
 * @package Drupal\dog_library\Service
 */
class ResourceLibraryUiBuilder implements ResourceLibraryUiBuilderInterface {

  use StringTranslationTrait;

  /**
   * The currently active request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The views executable factory.
   *
   * @var \Drupal\views\ViewExecutableFactory
   */
  protected $viewsExecutableFactory;

  /**
   * Construct new ResourceLibraryUiBuilder instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\views\ViewExecutableFactory $views_executable_factory
   *   The views executable factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack, ViewExecutableFactory $views_executable_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->request = $request_stack->getCurrentRequest();
    $this->viewsExecutableFactory = $views_executable_factory;
  }

  /**
   * {@inheritDoc}
   */
  public function buildUi(ResourceLibraryState $state = NULL) {
    if (!$state) {
      $state = ResourceLibraryState::fromRequest($this->request);
    }

    return [
      '#theme' => 'resource_library_wrapper',
      '#attributes' => [
        'id' => 'resource-library-wrapper',
      ],
      'content' => [
        '#type' => 'container',
        '#theme_wrappers' => [
          'container__resource_library_content',
        ],
        '#attributes' => [
          'id' => 'resource-library-content',
        ],
        'view' => $this->buildResourceLibraryView($state),
      ],
      // Attach the JavaScript for the resource library UI. The number of
      // available slots needs to be added to make sure users can't select
      // more items than allowed.
      '#attached' => [
        'library' => ['dog_library/ui'],
        'drupalSettings' => [
          'resource_library' => [
            'selection_remaining' => $state->getAvailableSlots(),
          ],
        ],
      ],
    ];
  }

  /**
   * Get the resource library view.
   *
   * @param \Drupal\dog_library\ResourceLibraryState $state
   *   The current state of the resource library, derived from the current
   *   request.
   *
   * @return array
   *   The render array for the resource library view.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function buildResourceLibraryView(ResourceLibraryState $state) {
    $view = $this->entityTypeManager->getStorage('view')
      ->load('resource_library');
    $view_executable = $this->viewsExecutableFactory->get($view);
    $display_id = $state->get('views_display_id', 'widget_table');

    // Make sure the state parameters are set in the request so the view can
    // pass the parameters along in the pager, filters etc.
    $view_request = $view_executable->getRequest();
    $view_request->query->add($state->all());
    $view_executable->setRequest($view_request);

    // Preselected filter.
    $args = [implode('+', $state->getAllowedTypeIds())];

    // Make sure the state parameters are set in the request so the view can
    // pass the parameters along in the pager, filters etc.
    $request = $view_executable->getRequest();
    $request->query->add($state->all());
    $view_executable->setRequest($request);

    $view_executable->setDisplay($display_id);
    $view_executable->preExecute($args);
    $view_executable->execute($display_id);

    return $view_executable->buildRenderable($display_id, $args, FALSE);
  }

  /**
   * {@inheritDoc}
   */
  public function checkAccess(AccountInterface $account, ResourceLibraryState $state = NULL) {
    if (!$state) {

      try {
        ResourceLibraryState::fromRequest($this->request);
      }
      catch (BadRequestHttpException $e) {
        return AccessResult::forbidden($e->getMessage());
      }
      catch (\InvalidArgumentException $e) {
        return AccessResult::forbidden($e->getMessage());
      }

    }

    // Deny access if the view or display are removed.
    $view = $this->entityTypeManager->getStorage('view')
      ->load('resource_library');

    if (!$view) {
      return AccessResult::forbidden('The resource library view does not exist.')
        ->setCacheMaxAge(0);
    }

    if (!$view->getDisplay('widget_table')) {
      return AccessResult::forbidden('The resource library widget display does not exist.')
        ->addCacheableDependency($view);
    }

    return AccessResult::allowed();
  }


}
