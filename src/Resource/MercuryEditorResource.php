<?php

namespace Drupal\mercury_editor_jsonapi\Resource;

use Drupal\jsonapi\ResourceResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\jsonapi_resources\Resource\ResourceBase;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;

/**
 * Processes a request for a mercury editor entity being edited.
 */
class MercuryEditorResource extends ResourceBase {

  /**
   * Process the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param Drupal\Core\Entity\ContentEntityInterface $mercury_editor_entity
   *   The menu.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function process(Request $request, ContentEntityInterface $mercury_editor_entity): ResourceResponse {
    $data = new ResourceObjectData($mercury_editor_entity);
    $response = $this->createJsonapiResponse(new ResourceObjectData($data), $request, 200, []);
    return $response;
  }

}
