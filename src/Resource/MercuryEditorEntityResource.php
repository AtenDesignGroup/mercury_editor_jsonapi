<?php

namespace Drupal\mercury_editor_jsonapi\Resource;

use Drupal\Core\Url;
use Drupal\jsonapi\ResourceResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\jsonapi\JsonApiResource\Link;
use Drupal\jsonapi\CacheableResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceType;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\jsonapi\JsonApiResource\IncludedData;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\NullIncludedData;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\JsonApiResource\TopLevelDataInterface;
use Drupal\jsonapi_resources\Resource\EntityResourceBase;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * Processes a request for a mercury editor entity being edited.
 */
class MercuryEditorEntityResource extends EntityResourceBase implements ContainerInjectionInterface {

  /**
   * The include resolver.
   *
   * @var \Drupal\jsonapi\IncludeResolver\IncludeResolver
   */
  protected $includeResolver;

  /**
   * Constructs a new EntityResourceBase object.
   */
  public function __construct($include_resolver) {
    $this->includeResolver = $include_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mercury_editor.jsonapi.include_resolver'),
    );
  }

  /**
   * Process the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\Core\Entity\ContentEntityInterface $mercury_editor_entity
   *   The menu.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function process(Request $request, ContentEntityInterface $mercury_editor_entity): ResourceResponse {
    if (isset($mercury_editor_entity->lp_storage_keys)) {
      foreach ($mercury_editor_entity->lp_storage_keys as $field_name => $value) {
        $layout = \Drupal::service('layout_paragraphs.tempstore_repository')->getWithStorageKey($value);
        $mercury_editor_entity->$field_name = $layout->getParagraphsReferenceField();
      }
    }

    $primary_data = $this->createIndividualDataFromEntity($mercury_editor_entity);
    $response = $this->buildWrappedResponse($primary_data, $request, $this->getIncludes($request, $primary_data));
    return $response;
  }

  /**
   * Gets the collection of entities.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON:API resource type for the request to be served.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Core\Http\Exception\CacheableBadRequestHttpException
   *   Thrown when filtering on a config entity which does not support it.
   */
  public function getCollection(ResourceType $resource_type, Request $request) {
    // Instantiate the query for the filtering.
    $entity_type_id = $resource_type->getEntityTypeId();

    $query_cacheability = new CacheableMetadata();

    // If the request is for the latest revision, toggle it on entity query.
    if ($request->get(ResourceVersionRouteEnhancer::WORKING_COPIES_REQUESTED, FALSE)) {
      $query->latestRevision();
    }

    try {
      $results = $this->executeQueryInRenderContext(
        $query,
        $query_cacheability
      );
    }
    catch (\LogicException $e) {
      // Ensure good DX when an entity query involves a config entity type.
      // For example: getting users with a particular role, which is a config
      // entity type: https://www.drupal.org/project/drupal/issues/2959445.
      // @todo Remove the message parsing in https://www.drupal.org/project/drupal/issues/3028967.
      if (str_starts_with($e->getMessage(), 'Getting the base fields is not supported for entity type')) {
        preg_match('/entity type (.*)\./', $e->getMessage(), $matches);
        $config_entity_type_id = $matches[1];
        $cacheability = (new CacheableMetadata())->addCacheContexts(['url.path', 'url.query_args:filter']);
        throw new CacheableBadRequestHttpException($cacheability, sprintf("Filtering on config entities is not supported by Drupal's entity API. You tried to filter on a %s config entity.", $config_entity_type_id));
      }
      else {
        throw $e;
      }
    }

    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    // We request N+1 items to find out if there is a next page for the pager.
    // We may need to remove that extra item before loading the entities.
    $pager_size = $query->getMetaData('pager_size');
    if ($has_next_page = $pager_size < count($results)) {
      // Drop the last result.
      array_pop($results);
    }
    // Each item of the collection data contains an array with 'entity' and
    // 'access' elements.
    $collection_data = $this->loadEntitiesWithAccess($storage, $results, $request->get(ResourceVersionRouteEnhancer::WORKING_COPIES_REQUESTED, FALSE));
    $primary_data = new ResourceObjectData($collection_data);
    $primary_data->setHasNextPage($has_next_page);

    // Calculate all the results and pass into a JSON:API Data object.
    $count_query_cacheability = new CacheableMetadata();
    if ($resource_type->includeCount()) {
      $count_query = $this->getCollectionCountQuery($resource_type, $params, $count_query_cacheability);
      $total_results = $this->executeQueryInRenderContext(
        $count_query,
        $count_query_cacheability
      );

      $primary_data->setTotalCount($total_results);
    }

    $response = $this->respondWithCollection($primary_data, $this->getIncludes($request, $primary_data), $request, $resource_type, $params[OffsetPage::KEY_NAME]);

    $response->addCacheableDependency($query_cacheability);
    $response->addCacheableDependency($count_query_cacheability);
    $response->addCacheableDependency((new CacheableMetadata())
      ->addCacheContexts([
        'url.query_args:filter',
        'url.query_args:sort',
        'url.query_args:page',
      ]));

    if ($resource_type->isVersionable()) {
      $response->addCacheableDependency((new CacheableMetadata())->addCacheContexts([ResourceVersionRouteEnhancer::CACHE_CONTEXT]));
    }

    return $response;
  }


  /**
   * Builds a response with the appropriate wrapped document.
   *
   * @param \Drupal\jsonapi\JsonApiResource\TopLevelDataInterface $data
   *   The data to wrap.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\jsonapi\JsonApiResource\IncludedData $includes
   *   The resources to be included in the document. Use NullData if
   *   there should be no included resources in the document.
   * @param int $response_code
   *   The response code.
   * @param array $headers
   *   An array of response headers.
   * @param \Drupal\jsonapi\JsonApiResource\LinkCollection $links
   *   The URLs to which to link. A 'self' link is added automatically.
   * @param array $meta
   *   (optional) The top-level metadata.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   */
  protected function buildWrappedResponse(TopLevelDataInterface $data, Request $request, IncludedData $includes, $response_code = 200, array $headers = [], LinkCollection $links = NULL, array $meta = []) {
    $links = ($links ?: new LinkCollection([]));
    if (!$links->hasLinkWithKey('self')) {
      $self_link = new Link(new CacheableMetadata(), self::getRequestLink($request), 'self');
      $links = $links->withLink('self', $self_link);
    }
    $document = new JsonApiDocumentTopLevel($data, $includes, $links, $meta);
    if (!$request->isMethodCacheable()) {
      return new ResourceResponse($document, $response_code, $headers);
    }
    $response = new CacheableResourceResponse($document, $response_code, $headers);
    $cacheability = (new CacheableMetadata())->addCacheContexts([
      // Make sure that different sparse fieldsets are cached differently.
      'url.query_args:fields',
      // Make sure that different sets of includes are cached differently.
      'url.query_args:include',
    ]);
    $response->addCacheableDependency($cacheability);
    return $response;
  }

  /**
   * Gets includes for the given response data.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\jsonapi\JsonApiResource\ResourceObject|\Drupal\jsonapi\JsonApiResource\ResourceObjectData $data
   *   The response data from which to resolve includes.
   *
   * @return \Drupal\jsonapi\JsonApiResource\Data
   *   A Data object to be included or a NullData object if the request does not
   *   specify any include paths.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getIncludes(Request $request, $data) {
    assert($data instanceof ResourceObject || $data instanceof ResourceObjectData);
    return $request->query->has('include') && ($include_parameter = $request->query->get('include')) && !empty($include_parameter)
      ? $this->includeResolver->resolve($data, $include_parameter)
      : new NullIncludedData();
  }

  /**
   * Get the full URL for a given request object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param array|null $query
   *   The query parameters to use. Leave it empty to get the query from the
   *   request object.
   *
   * @return \Drupal\Core\Url
   *   The full URL.
   */
  protected static function getRequestLink(Request $request, $query = NULL) {
    if ($query === NULL) {
      return Url::fromUri($request->getUri());
    }

    $uri_without_query_string = $request->getSchemeAndHttpHost() . $request->getBaseUrl() . $request->getPathInfo();
    return Url::fromUri($uri_without_query_string)->setOption('query', $query);
  }

}
