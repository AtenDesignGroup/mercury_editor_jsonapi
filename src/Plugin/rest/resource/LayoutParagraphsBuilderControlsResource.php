<?php

namespace Drupal\mercury_editor_jsonapi\Plugin\rest\resource;

use Drupal\Component\Serialization\Json;
use Psr\Log\LoggerInterface;
use Drupal\Core\Render\Renderer;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Asset\AssetResolver;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Render\ElementInfoManager;
use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\mercury_editor\MercuryEditorTempstore;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\layout_paragraphs\Element\LayoutParagraphsBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository;
use Drupal\layout_paragraphs\Routing\LayoutParagraphsTempstoreParamConverter;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides a resource for the Layout Paragraphs Builder Controls.
 *
 * @RestResource(
 *   id = "layout_paragraphs_builder_controls_resource",
 *   label = @Translation("Layout Paragraphs Builder Controls Resource"),
 *   uri_paths = {
 *     "canonical" = "/me-controls/{entity_id}"
 *   }
 * )
 */
class LayoutParagraphsBuilderControlsResource extends ResourceBase {

  /**
   * Constructs a new LayoutParagraphsBuilderControlsResource resource.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\mercury_editor\MercuryEditorTempstore $mercuryEditorTempstore
   *   The Mercury Editor Tempstore service.
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository $layoutParagraphsTempstore
   *   The Layout Paragraphs Tempstore service.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   * @param \Drupal\Core\Asset\AssetResolverInterface $assetResolver
   *   The asset resolver service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    protected MercuryEditorTempstore $mercuryEditorTempstore,
    protected LayoutParagraphsLayoutTempstoreRepository $layoutParagraphsTempstore,
    protected Renderer $renderer,
    protected AssetResolverInterface $assetResolver,
    protected LanguageManagerInterface $languageManager,
    protected ElementInfoManager $elementInfoManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('mercury_editor.tempstore_repository'),
      $container->get('layout_paragraphs.tempstore_repository'),
      $container->get('renderer'),
      $container->get('asset.resolver'),
      $container->get('language_manager'),
      $container->get('plugin.manager.element_info')
    );
  }

  /**
   * Responds to GET requests.
   */
  public function get($entity_id) {
    $entity = $this->mercuryEditorTempstore->get($entity_id);
    $ui_elements = [];
    if ($entity->lp_storage_keys) {
      foreach ($entity->lp_storage_keys as $key) {
        $layout = $this->layoutParagraphsTempstore->getWithStorageKey($key);
        if ($layout) {
          $builder_element = [
            '#type' => 'layout_paragraphs_builder',
            '#layout_paragraphs_layout' => $layout,
          ];
          $this->renderer->renderRoot($builder_element);
          $attached = AttachedAssets::createFromRenderArray($builder_element);
          $assets = $attached->getSettings() ?? [];
          $ui_elements[$key] = $assets['lpBuilder']['uiElements'] ?? [];
        }
      }
    }
    return new JsonResponse($ui_elements);
  }

}
