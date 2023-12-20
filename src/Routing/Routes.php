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
    foreach ($this->config->get('bundles') as $entity_type => $bundles) {
      foreach ($bundles as $bundle) {
        $routes['mercury_editor_jsonapi.resource.' . $entity_type . '.' . $bundle] = new Route(
          "/%jsonapi%/mercury-editor/{$entity_type}/{$bundle}/{mercury_editor_entity}",
          [
            '_jsonapi_resource' => 'Drupal\mercury_editor_jsonapi\Resource\MercuryEditorEntityResource',
            '_jsonapi_resource_types' => ["{$entity_type}--{$bundle}"],
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
          ]
        );
      }
    }
    return $routes;
  }

}
