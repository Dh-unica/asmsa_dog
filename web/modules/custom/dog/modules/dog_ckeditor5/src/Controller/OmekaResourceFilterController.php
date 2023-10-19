<?php

namespace Drupal\dog_ckeditor5\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\dog\Service\ResourceFetcherInterface;
use Drupal\filter\FilterFormatInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines the OmekaResourceFilterController class.
 *
 * @package Drupal\dog_ckeditor5\Controller
 */
class OmekaResourceFilterController implements ContainerInjectionInterface {

  /**
   * The resource fetcher.
   *
   * @var \Drupal\dog\Service\ResourceFetcherInterface
   */
  protected $fetcher;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Construct new OmekaResourceFilterController instance.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\dog\Service\ResourceFetcherInterface $fetcher
   *   The fetcher service.
   */
  public function __construct(RendererInterface $renderer, ResourceFetcherInterface $fetcher) {
    $this->renderer = $renderer;
    $this->fetcher = $fetcher;
  }

  /**
   * Throws an AccessDeniedHttpException if the request fails CSRF validation.
   *
   * This is used instead of \Drupal\Core\Access\CsrfAccessCheck, in order to
   * allow access for anonymous users.
   */
  private static function checkCsrf(Request $request, AccountInterface $account) {
    $header = 'X-Drupal-OmekaResourcePreview-CSRF-Token';

    if (!$request->headers->has($header)) {
      throw new AccessDeniedHttpException();
    }
    if ($account->isAnonymous()) {
      // For anonymous users, just the presence of the custom header is
      // sufficient protection.
      return;
    }
    // For authenticated users, validate the token value.
    $token = $request->headers->get($header);
    if (!\Drupal::csrfToken()->validate($token, $header)) {
      throw new AccessDeniedHttpException();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {

    $renderer = $container->get('renderer');
    assert($renderer instanceof RendererInterface);

    $fetcher = $container->get('dog.omeka_resource_fetcher');
    assert($fetcher instanceof ResourceFetcherInterface);

    return new static($renderer, $fetcher);
  }

  /**
   * Checks access based on dog_omeka_resource_embed filter status on the text format.
   *
   * @param \Drupal\filter\FilterFormatInterface $filter_format
   *   The text format for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public static function formatUsesOmekaResourceEmbedFilter(FilterFormatInterface $filter_format) {
    $filters = $filter_format->filters();
    return AccessResult::allowedIf(
      $filters->has('dog_omeka_resource_embed')
      && $filters->get('dog_omeka_resource_embed')->status
    )->addCacheableDependency($filter_format);
  }

  /**
   * Returns a HTML response containing a preview of the text after filtering.
   *
   * Applies all of the given text format's filters, not just the
   * `dog_omeka_resource_embed` filter, because for example `filter_align`
   * and `filter_caption` may apply to it as well.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\filter\FilterFormatInterface $filter_format
   *   The text format.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The filtered text.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Throws an exception if 'text' parameter is not found in the query
   *   string.
   *
   * @see \Drupal\editor\EditorController::getUntransformedText
   */
  public function preview(Request $request, FilterFormatInterface $filter_format) {
    self::checkCsrf($request, \Drupal::currentUser());

    $text = $request->query->get('text');
    $id = $request->query->get('id');
    $type = $request->query->get('bundle');
    if ($text == '' || $id == '') {
      throw new NotFoundHttpException();
    }

    $build = [
      '#type' => 'processed_text',
      '#text' => $text,
      '#format' => $filter_format->id(),
    ];
    $html = $this->renderer->renderPlain($build);

    // Load the resource item so we can embed the label in the response,
    // for use in an ARIA label.
    $headers = [];
    if ($resource = $this->fetcher->retrieveResource($id, $type)) {
      $headers['Drupal-Omeka-Resource-Label'] = $resource['o:title'];
    }

    // Note that we intentionally do not use:
    // - \Drupal\Core\Cache\CacheableResponse because caching it on the server
    //   side is wasteful, hence there is no need for cacheability metadata.
    // - \Drupal\Core\Render\HtmlResponse because there is no need for
    //   attachments nor cacheability metadata.
    return (new Response($html, 200, $headers))
      // Do not allow any intermediary to cache the response, only the end user.
      ->setPrivate()
      // Allow the end user to cache it for up to 5 minutes.
      ->setMaxAge(300);
  }

}
