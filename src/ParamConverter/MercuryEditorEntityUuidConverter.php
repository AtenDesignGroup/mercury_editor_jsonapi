<?php

namespace Drupal\mercury_editor_jsonapi\ParamConverter;

use Drupal\jsonapi\Routing\Routes;
use Symfony\Component\Routing\Route;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\ParamConverter\EntityConverter;
use Drupal\mercury_editor\MercuryEditorTempstore;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\jsonapi\ParamConverter\EntityUuidConverter;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

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
        return $entity;
      }
      $paragraph = $this->mercuryEditorTempstore->getComponent($value);
      if ($paragraph) {
        return $paragraph;
      }
    }
    return parent::convert($value, $definition, $name, $defaults);
  }

}
