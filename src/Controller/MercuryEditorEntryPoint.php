<?php

namespace Drupal\mercury_editor_jsonapi\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\CacheableResourceResponse;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\NullIncludedData;
use Drupal\jsonapi\JsonApiResource\Link;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Controller for the API entry point.
 *
 * @internal JSON:API maintains no PHP API. The API is the HTTP API. This class
 *   may change at any time and could break any dependencies on it.
 *
 * @see https://www.drupal.org/project/drupal/issues/3032787
 * @see jsonapi.api.php
 */
class MercuryEditorEntryPoint extends ControllerBase {

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The account object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * Mercury Edtior configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * EntryPoint constructor.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The resource type repository.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The current user.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The mercury editor settings.
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository, AccountInterface $user, ImmutableConfig $config) {
    $this->resourceTypeRepository = $resource_type_repository;
    $this->user = $user;
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('jsonapi.resource_type.repository'),
      $container->get('current_user'),
      $container->get('config.factory')->get('mercury_editor.settings')
    );
  }

  /**
   * Controller to list all the resources.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response object.
   */
  public function index() {
    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['user.roles:authenticated'])
      ->addCacheTags(['jsonapi_resource_types']);

    // Only build URLs for exposed resources.
    $me_enabled = [];
    $me_bundles = $this->config->get('bundles');
    foreach ($me_bundles as $entity_type => $bundles) {
      foreach ($bundles as $bundle) {
        $me_enabled[$entity_type . '--' . $bundle] = Url::fromRoute('mercury_editor_jsonapi.resource.' . $entity_type . '.' . $bundle);
      }
    }
    $resources = array_filter($this->resourceTypeRepository->all(), function ($resource) use ($me_enabled) {
      return !$resource->isInternal() && isset($me_enabled[$resource->getTypeName()]);
    });

    $self_link = new Link(new CacheableMetadata(), Url::fromRoute('jsonapi.resource_list'), 'self');
    $urls = array_reduce($resources, function (LinkCollection $carry, ResourceType $resource_type) use ($me_enabled) {
      if ($resource_type->isLocatable() || $resource_type->isMutable()) {
        $url = $me_enabled[$resource_type->getTypeName()];
        // Using a resource type name in place of a link relation type is not
        // technically valid. However, since it matches the link key, it will
        // not actually be serialized since the rel is omitted if it matches the
        // link key; because of that no client can rely on it. Once an extension
        // relation type is implemented for links to a collection, that should
        // be used instead. Unfortunately, the `collection` link relation type
        // would not be semantically correct since it would imply that the
        // entrypoint is a *member* of the link target.
        // @todo: implement an extension relation type to signal that this is a primary collection resource.
        $link_relation_type = $resource_type->getTypeName();
        return $carry->withLink($resource_type->getTypeName(), new Link(new CacheableMetadata(), $url, $link_relation_type));
      }
      return $carry;
    }, new LinkCollection(['self' => $self_link]));

    $meta = [];
    if ($this->user->isAuthenticated()) {
      $current_user_uuid = $this->entityTypeManager()->getStorage('user')->load($this->user->id())->uuid();
      $meta['links']['me'] = ['meta' => ['id' => $current_user_uuid]];
      $cacheability->addCacheContexts(['user']);
      try {
        $me_url = Url::fromRoute(
          'jsonapi.user--user.individual',
          ['entity' => $current_user_uuid]
        )
          ->setAbsolute()
          ->toString(TRUE);
        $meta['links']['me']['href'] = $me_url->getGeneratedUrl();
        // The cacheability of the `me` URL is the cacheability of that URL
        // itself and the currently authenticated user.
        $cacheability = $cacheability->merge($me_url);
      }
      catch (RouteNotFoundException $e) {
        // Do not add the link if the route is disabled or marked as internal.
      }
    }

    $response = new CacheableResourceResponse(new JsonApiDocumentTopLevel(new ResourceObjectData([]), new NullIncludedData(), $urls, $meta));
    return $response->addCacheableDependency($cacheability);
  }

}
