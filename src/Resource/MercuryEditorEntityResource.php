<?php

namespace Drupal\mercury_editor_jsonapi\Resource;

use Drupal\Core\Url;
use Drupal\jsonapi\ResourceResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\jsonapi\JsonApiResource\Link;
use Drupal\jsonapi\CacheableResourceResponse;
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
