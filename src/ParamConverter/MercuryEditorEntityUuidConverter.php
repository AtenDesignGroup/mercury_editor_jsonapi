<?php

namespace Drupal\mercury_editor_jsonapi\ParamConverter;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\mercury_editor\MercuryEditorTempstore;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\jsonapi\ParamConverter\EntityUuidConverter;

/**
 * Parameter converter for upcasting entity UUIDs to full objects.
 *
 * @internal JSON:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 *
 * @see https://www.drupal.org/project/drupal/issues/3032787
 * @see jsonapi.api.php
 *
 * @see \Drupal\Core\ParamConverter\EntityConverter
 *
 * @todo Remove when https://www.drupal.org/node/2353611 lands.
 */
class MercuryEditorEntityUuidConverter extends EntityUuidConverter {

  /**
   * The Mercury Editor tempstore service.
   *
   * @var Drupal\mercury_editor\MercuryEditorTempstore
   */
  protected $mercuryEditorTempstore;

  /**
   * The current user.
   *
   * @var Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Page cache kill switch.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $killSwitch;

  /**
   * Injects the page cache kill switch.
   *
   * @param Drupal\Core\PageCache\ResponsePolicy\KillSwitch $kill_switch
   *   The page cache kill switch.
   */
  public function setKillSwitch(KillSwitch $kill_switch) {
    $this->killSwitch = $kill_switch;
  }

  /**
   * Injects the Mercury Editor tempstore service.
   *
   * @param \Drupal\mercury_editor\MercuryEditorTempstore $mercury_editor_tempstore
   *   The Mercury Editor tempstore service.
   */
  public function setMercuryEditorTempstore(MercuryEditorTempstore $mercury_editor_tempstore) {
    $this->mercuryEditorTempstore = $mercury_editor_tempstore;
  }

  /**
   * Injects the current user.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function setCurrentUser(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if ($this->currentUser->id()) {
      $entity = $this->mercuryEditorTempstore->get($value);
      if ($entity) {
        $this->killSwitch->trigger();
        $this->clearNormalizationCache($entity);
        return $entity;
      }
      $paragraph = $this->mercuryEditorTempstore->getComponent($value);
      if ($paragraph) {
        $this->killSwitch->trigger();
        $this->clearNormalizationCache($paragraph);
        return $paragraph;
      }
    }
    return parent::convert($value, $definition, $name, $defaults);
  }

  /**
   * Deletes a normalization cache entry.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity for which to delete the cache entry.
   */
  protected function clearNormalizationCache(ContentEntityInterface $entity) {
    $cid = $entity->getEntityTypeId() . '--' . $entity->bundle() . ':' . $entity->uuid() . ':' . $entity->language()->getId();
    \Drupal::service('cache.jsonapi_normalizations')->delete($cid);
  }

}
