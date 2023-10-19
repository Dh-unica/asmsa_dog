<?php

namespace Drupal\dog\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\dog\Service\ResourceFetcherInterface;
use Drupal\dog\Service\ResourceViewBuilderInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the OmekaResourceEmbed class.
 *
 * @Filter(
 *   id = "dog_omeka_resource_embed",
 *   title = @Translation("Embed Omeka Resource"),
 *   description = @Translation("Embeds Omeka Resource items using a custom tag, <code>&lt;drupal-omeka-resource&gt;</code>."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 *   weight = 100,
 * )
 * @package Drupal\dog\Plugin\Filter
 */
class OmekaResourceEmbed extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * The number of times this formatter allows rendering the same entity.
   *
   * @var int
   */
  const RECURSIVE_RENDER_LIMIT = 20;

  /**
   * An array of counters for the recursive rendering protection.
   *
   * Each counter takes into account all the relevant information about the
   * field and the referenced entity that is being rendered.
   *
   * @var array
   *
   * @see \Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter::$recursiveRenderDepth
   */
  protected static $recursiveRenderDepth = [];

  /**
   * The resource fetcher.
   *
   * @var \Drupal\dog\Service\ResourceFetcherInterface
   */
  protected $fetcher;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The view builder service for resource..
   *
   * @var \Drupal\dog\Service\ResourceViewBuilderInterface
   */
  protected $viewBuilder;

  /**
   * Construct new OmekaResourceEmbed instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\dog\Service\ResourceFetcherInterface $fetcher
   *   The resource fetcher service.
   * @param \Drupal\dog\Service\ResourceViewBuilderInterface $view_builder
   *   The resource view builder.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_channel_factory
   *   The logger factory.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ResourceFetcherInterface $fetcher,
    ResourceViewBuilderInterface $view_builder,
    RendererInterface $renderer,
    LoggerChannelFactoryInterface $logger_channel_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fetcher = $fetcher;
    $this->viewBuilder = $view_builder;
    $this->renderer = $renderer;
    $this->logger = $logger_channel_factory->get('dog');
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {

    $fetcher = $container->get('dog.omeka_resource_fetcher');
    assert($fetcher instanceof ResourceFetcherInterface);

    $view_builder = $container->get('dog.omeka_resource_view_builder');
    assert($view_builder instanceof ResourceViewBuilderInterface);

    $renderer = $container->get('renderer');
    assert($renderer instanceof RendererInterface);

    $logger_factory = $container->get('logger.factory');
    assert($logger_factory instanceof LoggerChannelFactoryInterface);

    return new static($configuration, $plugin_id, $plugin_definition, $fetcher, $view_builder, $renderer, $logger_factory);
  }

  /**
   * Replaces the contents of a DOMNode.
   *
   * @param \DOMNode $node
   *   A DOMNode object.
   * @param string $content
   *   The text or HTML that will replace the contents of $node.
   */
  protected static function replaceNodeContent(\DOMNode &$node, $content) {
    if (strlen($content)) {
      // Load the content into a new DOMDocument and retrieve the DOM nodes.
      $replacement_nodes = Html::load($content)
        ->getElementsByTagName('body')
        ->item(0)
        ->childNodes;
    }
    else {
      $replacement_nodes = [$node->ownerDocument->createTextNode('')];
    }

    foreach ($replacement_nodes as $replacement_node) {
      // Import the replacement node from the new DOMDocument into the original
      // one, importing also the child nodes of the replacement node.
      $replacement_node = $node->ownerDocument->importNode($replacement_node, TRUE);
      $node->parentNode->insertBefore($replacement_node, $node);
    }
    $node->parentNode->removeChild($node);
  }

  /**
   * {@inheritDoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    if (stristr($text, '<drupal-omeka-resource') === FALSE) {
      return $result;
    }

    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);

    foreach ($xpath->query('//drupal-omeka-resource[@data-entity-type="omeka_resource" and normalize-space(@data-entity-id)!=""]') as $node) {
      /** @var \DOMElement $node */
      $id = $node->getAttribute('data-entity-id');
      $bundle = $node->getAttribute('data-entity-bundle');
      $bundle = !empty($bundle) ? $bundle : 'item';
      $view_mode = $node->getAttribute('data-view-mode');
      $view_mode = !empty($view_mode) ? $view_mode : 'default';

      // Delete the consumed attributes.
      $node->removeAttribute('data-entity-type');
      $node->removeAttribute('data-entity-id');
      $node->removeAttribute('data-entity-bundle');
      $node->removeAttribute('data-view-mode');

      $omeka_resource = $this->fetcher->retrieveResource($id, $bundle);
      if (empty($omeka_resource)) {
        $this->logger
          ->error('During rendering of embedded omeka resource: the resource item with ID "@id" and bundle "@bundle" does not exist.', [
            '@id' => $id,
            '@bundle' => $bundle,
          ]);
      }

      $build = $omeka_resource
        ? $this->renderOmekaResource($omeka_resource, $view_mode, $langcode)
        : $this->renderMissingOmekaResourceIndicator();

      if (empty($build['#attributes']['class'])) {
        $build['#attributes']['class'] = [];
      }
      // Any attributes not consumed by the filter should be carried over to the
      // rendered embedded entity. For example, `data-align` and `data-caption`
      // should be carried over, so that even when embedded resource goes
      // missing, at least the caption and visual structure won't get lost.
      foreach ($node->attributes as $attribute) {
        if ($attribute->nodeName == 'class') {
          // We don't want to overwrite the existing CSS class of the embedded
          // resource (or if the resource entity can't be loaded, the missing
          // resource indicator). But, we need to merge in CSS classes added
          // by other filters, such as filter_align, in order for those filters
          // to work properly.
          $build['#attributes']['class'] = array_unique(array_merge($build['#attributes']['class'], explode(' ', $attribute->nodeValue)));
        }
        else {
          $build['#attributes'][$attribute->nodeName] = $attribute->nodeValue;
        }
      }

      $this->renderIntoDomNode($build, $node, $result);
    }

    $result->setProcessedText(Html::serialize($dom));

    return $result;
  }

  /**
   * {@inheritDoc}
   */
  public function tips($long = FALSE) {
    if ($long) {
      return $this->t('
      <p>You can embed Omeka resource items:</p>
      <ul>
        <li>Choose which Omeka resource item to embed: <code>&lt;drupal-omeka-resource data-entity-id="152" data-entity-bundle="items" /&gt;</code></li>
        <li>Optionally also choose a view mode: <code>data-view-mode="thumbnail"</code></li>
        <li>The <code>data-entity-type="omeka_resource"</code> attribute is required for consistency.</li>
      </ul>');
    }
    else {
      return $this->t('You can embed Omeka resource items (using the <code>&lt;drupal-omeka-resource&gt;</code> tag).');
    }
  }

  /**
   * Renders the given render array into the given DOM node.
   *
   * @param array $build
   *   The render array to render in isolation.
   * @param \DOMNode $node
   *   The DOM node to render into.
   * @param \Drupal\filter\FilterProcessResult $result
   *   The accumulated result of filter processing, updated with the metadata
   *   bubbled during rendering.
   */
  protected function renderIntoDomNode(array $build, \DOMNode $node, FilterProcessResult &$result) {
    // We need to render the embedded Omeka resource:
    // - without replacing placeholders, so that the placeholders are
    //   only replaced at the last possible moment. Hence we cannot use
    //   either renderPlain() or renderRoot(), so we must use render().
    // - without bubbling beyond this filter, because filters must
    //   ensure that the bubbleable metadata for the changes they make
    //   when filtering text makes it onto the FilterProcessResult
    //   object that they return ($result). To prevent that bubbling, we
    //   must wrap the call to render() in a render context.
    $markup = $this->renderer->executeInRenderContext(new RenderContext(), function () use (&$build) {
      return $this->renderer->render($build);
    });
    $result = $result->merge(BubbleableMetadata::createFromRenderArray($build));
    static::replaceNodeContent($node, $markup);
  }

  /**
   * Builds the render array for the indicator when resource cannot be loaded.
   *
   * @return array
   *   A render array.
   */
  protected function renderMissingOmekaResourceIndicator() {
    return [
      '#markup' => $this->t('The referenced Omeka resource is missing and needs to be re-embedded.'),
    ];
  }

  /**
   * Builds the render array for the given resource.
   *
   * @param object $omeka_resource
   *   A Omeka resource to render.
   * @param string $view_mode
   *   The view mode to render it in.
   * @param string|null $langcode
   *   Language code.
   *
   * @return array
   *   A render array.
   */
  protected function renderOmekaResource($omeka_resource, string $view_mode = 'default', string $langcode = NULL) {
    // Due to render caching and delayed calls, filtering happens later
    // in the rendering process through a '#pre_render' callback, so we
    // need to generate a counter for the resource entity that is being
    // embedded.
    // @see \Drupal\filter\Element\ProcessedText::preRenderText()
    $recursive_render_id = $omeka_resource['id'];
    $recursive_render_bundle = $omeka_resource['type'];
    if (isset(static::$recursiveRenderDepth[$recursive_render_bundle][$recursive_render_id])) {
      static::$recursiveRenderDepth[$recursive_render_bundle][$recursive_render_id]++;
    }
    else {
      static::$recursiveRenderDepth[$recursive_render_bundle][$recursive_render_id] = 1;
    }
    // Protect ourselves from recursive rendering: return an empty render array.
    if (static::$recursiveRenderDepth[$recursive_render_bundle][$recursive_render_id] > EntityReferenceEntityFormatter::RECURSIVE_RENDER_LIMIT) {
      $this->logger
        ->error('During rendering of embedded omeka resource: recursive rendering detected for %id / %bundle. Aborting rendering.', [
          '%id' => $recursive_render_id,
          '%bundle' => $recursive_render_bundle,
        ]);
      return [];
    }

    return $this->viewBuilder->view($omeka_resource, $view_mode, $langcode);
  }

}
