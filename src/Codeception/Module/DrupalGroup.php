<?php

namespace Codeception\Module;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\GroupMembership;
use Drupal\user\Entity\User;

/**
 * Class DrupalEntity.
 *
 * ### Example
 * #### Example (DrupalEntity)
 *     modules:
 *        - DrupalEntity:
 *          cleanup_test: true
 *          cleanup_failed: false
 *          cleanup_suite: true
 *          route_entities:
 *            - node
 *            - taxonomy_term.
 *
 * @package Codeception\Module
 */
class DrupalGroup extends DrupalEntity {

  /**
   * Create entity from values.
   *
   * @param array $values
   *   Data for creating entity.
   * @param string $type
   *   Entity type.
   * @param bool $validate
   *   Flag to validate entity fields..
   *
   * @return \Drupal\Core\Entity\EntityInterface|bool
   *   Created entity.
   */
  public function createEntity(array $values = [], $type = 'group', $validate = TRUE) {
    try {
      $entity = \Drupal::entityTypeManager()
        ->getStorage($type)
        ->create($values);
      if ($validate && $entity instanceof FieldableEntityInterface) {
        $violations = $entity->validate();
        if ($violations->count() > 0) {
          $message = PHP_EOL;
          foreach ($violations as $violation) {
            $message .= $violation->getPropertyPath() . ': ' . $violation->getMessage() . PHP_EOL;
          }
          throw new \Exception($message);
        }
      }
      // Group specific entity save options.
      $entity->setOwner(User::load($values['uid'] ?? 1));
      $entity->set('label', $values['label'] ?? 'Test group');
      $entity->save();
    }
    catch (\Exception $e) {
      $this->fail('Could not group entity. Error message: ' . $e->getMessage());
    }
    if (!empty($entity)) {
      $this->registerTestEntity($entity->getEntityTypeId(), $entity->id());

      return $entity;
    }

    return FALSE;
  }

  /**
   * Wrapper method to create a group.
   *
   * Improves readbility of tests by having the method read "create group".
   *
   * @see createEntity()
   */
  public function createGroup(array $values = [], $validate = TRUE) {
    if (!array_key_exists('uid', $values)) {
      $values['uid'] = 1;
    }

    return $this->createEntity($values, 'group', $validate);
  }

  /**
   * Join the defined group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Instance of a group.
   *
   * @return \Drupal\group\GroupMembership|false
   *   Returns the group content entity, FALSE otherwise.
   */
  public function joinGroup(GroupInterface $group) {
    $group->addMember(\Drupal::currentUser()->getAccount());
    return $group->getMember(\Drupal::currentUser()->getAccount());
  }

  /**
   * Leave a group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Instance of a group.
   *
   * @return bool
   *   Returns the TRUE if the user is no longer a member of the group,
   *   FALSE otherwise.
   */
  public function leaveGroup(GroupInterface $group) {
    $group->removeMember(\Drupal::currentUser()->getAccount());
    // Get member should return FALSE if the user isn't a member so we
    // reverse the logic. If they are still a member it'll cast to TRUE.
    $is_member = (bool) $group->getMember(\Drupal::currentUser()->getAccount());
    return !$is_member;
  }

}
