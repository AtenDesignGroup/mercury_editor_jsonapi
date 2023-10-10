<?php

namespace Drupal\mercury_editor_jsonapi\Routing;

use Symfony\Component\Routing\Route;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * Provides routes for mercury editor resources.
 */
class Routes implements ContainerInjectionInterface {

  /**
   * The mercury editor settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Constructs a new Routes object.
   */
  public function __construct($config_factory) {
    $this->config = $config_factory->get('mercury_editor.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
    );
  }

  /**
   * Provides routes for mercury editor resources.
   */
  public function routes() {
    // @toto Refactor for 'bundles' once #3388911 is in.
    foreach (array_keys($this->config->get('content_types')) as $bundle) {
      $routes['mercury_editor_jsonapi.resouce.node.' . $bundle] = new Route(
        "/%jsonapi%/mercury-editor/node/{$bundle}/{mercury_editor_entity}",
        [
          '_jsonapi_resource' => 'Drupal\mercury_editor_jsonapi\Resource\MercuryEditorEntityResource',
          '_jsonapi_resource_types' => ["node--{$bundle}"],
        ],
        [
          '_permission' => 'access content',
        ],
        [
          'parameters' => [
            'mercury_editor_entity' => [
              'type' => 'mercury_editor_entity',
            ],
          ],
        ]);
    }
    return $routes;
  }

}
