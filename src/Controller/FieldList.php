<?php

namespace Drupal\mercury_editor_jsonapi\Controller;

use Drupal\field\Entity\FieldConfigInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\field\FieldConfigInterface as FieldFieldConfigInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns a listed of nested entity reference fields.
 */
class FieldList extends ControllerBase {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The list of fields.
   *
   * @var array
   */
  protected $fieldList = [];

  /**
   * Constructs a new NestedReferenceFields object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * Returns a list of all nested entity reference fields from paragraph types.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The Json response.
   */
  public function listFields() {
    $this->nestedReferenceFields('node', 'page', [
      'paragraph',
      'media',
      'file',
      'remote_video',
      'video',
    ]);
    return new JsonResponse(array_values(array_unique($this->fieldList)), 200);
  }

  public function queryString() {
    $this->nestedReferenceFields('node', 'page', ['paragraph', 'media', 'file', 'remote_video']);
    return new Response(implode(',', array_values(array_unique($this->fieldList))));
  }

  /**
   * Gets a list of all nested entity reference fields from entity/bundle field.
   *
   * @param string $entity_type_id
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param array $reference_types
   *   The list of reference types.
   *
   * @return array
   *   The list of nested entity reference fields.
   */
  protected function nestedReferenceFields($entity_type_id, $bundle, $reference_types, $parents = []) {
    if ($entity_type_id == 'media') {
      $foo = 'bar';
    }
    $all_field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
    $filtered_field_definitions = array_filter(
      $all_field_definitions,
      function ($definition) use ($reference_types) {
        $settings = $definition->getSettings() ?? [];
        if (empty($settings['handler'])) {
          return FALSE;
        }
        $target_type = explode(':', $settings['handler'])[1] ?? NULL;
        return !empty($target_type) && in_array($target_type, $reference_types);
      });
    foreach ($filtered_field_definitions as $field_name => $field_definition) {
      if ($field_name == 'field_media_image') {
        $foo = 'bar';
      }
      $expanded_field = array_merge($parents, [$field_name]);
      $this->fieldList[] = implode('.', $expanded_field);
      $settings = $field_definition->getSettings();
      $nested_entity_type_id = $settings['target_type'];
      $target_bundles = $settings['handler_settings']['target_bundles'] ?? [];
      if (empty($settings['handler_settings']['negate'])) {
        $bundles = $target_bundles;
      }
      else {
        $bundles = array_diff(
          array_keys($this->entityTypeBundleInfo->getBundleInfo($nested_entity_type_id)),
          $target_bundles
        );
      }
      foreach ($bundles as $bundle) {
        $this->nestedReferenceFields($nested_entity_type_id, $bundle, $reference_types, $expanded_field);
      }
    }
  }

}
