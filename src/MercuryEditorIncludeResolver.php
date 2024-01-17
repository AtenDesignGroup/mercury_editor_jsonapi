<?php

namespace Drupal\mercury_editor_jsonapi;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\jsonapi\Access\EntityAccessChecker;
use Drupal\jsonapi\Context\FieldResolver;
use Drupal\jsonapi\Exception\EntityAccessDeniedHttpException;
use Drupal\jsonapi\IncludeResolver;
use Drupal\jsonapi\JsonApiResource\Data;
use Drupal\jsonapi\JsonApiResource\IncludedData;
use Drupal\jsonapi\JsonApiResource\LabelOnlyResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceIdentifierInterface;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Resolves included resources for an entity or collection of entities.
 *
 * @internal JSON:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 *
 * @see https://www.drupal.org/project/drupal/issues/3032787
 * @see jsonapi.api.php
 */
class MercuryEditorIncludeResolver extends IncludeResolver {

  /**
   * Receives a tree of include field names and resolves resources for it.
   *
   * This method takes a tree of relationship field names and JSON:API Data
   * object. For the top-level of the tree and for each entity in the
   * collection, it gets the target entity type and IDs for each relationship
   * field. The method then loads all of those targets and calls itself
   * recursively with the next level of the tree and those loaded resources.
   *
   * @param array $include_tree
   *   The include paths, represented as a tree.
   * @param \Drupal\jsonapi\JsonApiResource\Data $data
   *   The entity collection from which includes should be resolved.
   * @param \Drupal\jsonapi\JsonApiResource\Data|null $includes
   *   (Internal use only) Any prior resolved includes.
   *
   * @return \Drupal\jsonapi\JsonApiResource\Data
   *   A JSON:API Data of included items.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if an included entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if a storage handler couldn't be loaded.
   */
  protected function resolveIncludeTree(array $include_tree, Data $data, Data $includes = NULL) {
    $includes = is_null($includes) ? new IncludedData([]) : $includes;
    foreach ($include_tree as $field_name => $children) {
      $references = [];
      foreach ($data as $resource_object) {
        // Some objects in the collection may be LabelOnlyResourceObjects or
        // EntityAccessDeniedHttpException objects.
        assert($resource_object instanceof ResourceIdentifierInterface);
        $public_field_name = $resource_object->getResourceType()->getPublicName($field_name);

        if ($resource_object instanceof LabelOnlyResourceObject) {
          $message = "The current user is not allowed to view this relationship.";
          $exception = new EntityAccessDeniedHttpException($resource_object->getEntity(), AccessResult::forbidden("The user only has authorization for the 'view label' operation."), '', $message, $public_field_name);
          $includes = IncludedData::merge($includes, new IncludedData([$exception]));
          continue;
        }
        elseif (!$resource_object instanceof ResourceObject) {
          continue;
        }

        // Not all entities in $entity_collection will be of the same bundle and
        // may not have all of the same fields. Therefore, calling
        // $resource_object->get($a_missing_field_name) will result in an
        // exception.
        if (!$resource_object->hasField($public_field_name)) {
          continue;
        }
        $field_list = $resource_object->getField($public_field_name);
        // Config entities don't have real fields and can't have relationships.
        if (!$field_list instanceof EntityReferenceFieldItemListInterface) {
          continue;
        }
        $field_access = $field_list->access('view', NULL, TRUE);
        if (!$field_access->isAllowed()) {
          $message = 'The current user is not allowed to view this relationship.';
          $exception = new EntityAccessDeniedHttpException($field_list->getEntity(), $field_access, '', $message, $public_field_name);
          $includes = IncludedData::merge($includes, new IncludedData([$exception]));
          continue;
        }
        $target_type = $field_list->getFieldDefinition()->getFieldStorageDefinition()->getSetting('target_type');
        assert(!empty($target_type));
        foreach ($field_list->referencedEntities() as $entity) {
          assert($entity instanceof EntityInterface);
          if ($entity instanceof Paragraph) {
            $settings = $entity->getAllBehaviorSettings();
            $entity->behavior_settings->value = serialize($entity->getAllBehaviorSettings());
            //$entity->set('behavior_settings', serialize($entity->getAllBehaviorSettings()));
          }
          $references[$target_type][$entity->uuid()] = $entity;
        }
      }
      foreach ($references as $target_type => $targeted_entities) {
        $access_checked_entities = array_map(function (EntityInterface $entity) {
          return $this->entityAccessChecker->getAccessCheckedResourceObject($entity);
        }, $targeted_entities);
        $targeted_collection = new IncludedData(array_filter($access_checked_entities, function (ResourceIdentifierInterface $resource_object) {
          return !$resource_object->getResourceType()->isInternal();
        }));
        $includes = static::resolveIncludeTree($children, $targeted_collection, IncludedData::merge($includes, $targeted_collection));
      }
    }
    return $includes;
  }

}
