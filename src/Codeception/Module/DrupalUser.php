<?php

namespace Codeception\Module;

use Codeception\Module;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\user\Entity\User;
use Faker\Factory;

/**
 * Class DrupalUser.
 *
 * ### Example
 * #### Example (DrupalUser)
 *     modules:
 *        - DrupalUser:
 *          default_role: 'authenticated'
 *          cleanup_entities:
 *            - media
 *            - file
 *            - paragraph
 *          cleanup_test: false
 *          cleanup_failed: false
 *          cleanup_suite: true
 *          alias: @site.com
 *
 * @package Codeception\Module
 */
class DrupalUser extends Module {

  /**
   * Driver to use.
   *
   * @var WebDriver|PhpBrowser
   */
  protected $driver;

  /**
   * A list of user ids created during test suite.
   *
   * @var array
   */
  protected $users;

  /**
   * Default module configuration.
   *
   * @var array
   */
  protected $config = [
    'default_role' => 'authenticated',
    'cleanup_entities' => [],
    'cleanup_test' => TRUE,
    'cleanup_failed' => TRUE,
    'cleanup_suite' => TRUE,
  ];

  /**
   * {@inheritdoc}
   */
  public function _after(\Codeception\TestCase $test) { // @codingStandardsIgnoreLine
    if ($this->_getConfig('cleanup_test')) {
      $this->userCleanup();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function _failed(TestInterface $test, Exception $fail) { // @codingStandardsIgnoreLine
    if ($this->_getConfig('cleanup_failed')) {
      $this->userCleanup();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function _afterSuite() { // @codingStandardsIgnoreLine
    if ($this->_getConfig('cleanup_suite')) {
      $this->userCleanup();
    }
  }

  /**
   * Create test user with specified roles.
   *
   * @param array $roles
   *   List of user roles.
   * @param mixed $password
   *   Password.
   *
   * @return \Drupal\user\Entity\User
   *   User object.
   */
  public function createUserWithRoles(array $roles = [], $password = FALSE) {
    $faker = Factory::create();
    /** @var \Drupal\user\Entity\User $user */
    try {
      $user = \Drupal::entityTypeManager()->getStorage('user')->create([
        'name' => $faker->userName,
        'mail' => $faker->email,
        'roles' => empty($roles) ? $this->_getConfig('default_role') : $roles,
        'pass' => $password ? $password : $faker->password(12, 14),
        'status' => 1,
      ]);

      $user->save();
      $this->users[] = $user->id();
    }
    catch (\Exception $e) {
      $message = sprintf('Could not create user with roles: %s. Error: %s', implode(', ', $roles), $e->getMessage());
      $this->fail($message);
    }

    return $user;
  }

  /**
   * Log in user by username.
   *
   * @param string|int $username
   *   User id.
   */
  public function logInAs($username) {
    /** @var \Drupal\user\Entity\User $user */
    try {
      // Load the user.
      $account = user_load_by_name($username);

      if (FALSE === $account ) {
        throw new \Exception();
      }

      // Login with the user.
      user_login_finalize($account);
    }
    catch (\Exception $e) {
      $this->fail('Coud not login with username ' . $username);
    }
  }

  /**
   * Create user with role and Log in.
   *
   * @param string $role
   *   Role.
   *
   * @return \Drupal\user\Entity\User
   *   User object.
   */
  public function logInWithRole($role) {
    $user = $this->createUserWithRoles([$role], Factory::create()->password(12, 14));

    $this->logInAs($user->getAccountName());

    return $user;
  }

  /**
   * Delete stored entities.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function userCleanup() {
    if (isset($this->users)) {
      $users = User::loadMultiple($this->users);
      /** @var \Drupal\user\Entity\User $user */
      foreach ($users as $user) {
        $this->deleteUsersContent($user->id());
        try {
          $user->delete();
        } catch (\Exception $e) {
          continue;
        }
      }
    }
  }

  /**
   * Delete user created entities.
   *
   * @param string|int $uid
   *   User id.
   */
  private function deleteUsersContent($uid) {
    $errors = [];
    $cleanup_entities = $this->_getConfig('cleanup_entities');
    if (is_array($cleanup_entities)) {
      foreach ($cleanup_entities as $cleanup_entity) {
        if (!is_string($cleanup_entity)) {
          continue;
        }
        try {
          /** @var EntityStorageInterface $storage */
          $storage = \Drupal::entityTypeManager()->getStorage($cleanup_entity);
        }
        catch (\Exception $e) {
          $errors[] = 'Could not load storage ' . $cleanup_entity;
          continue;
        }
        try {
          $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($cleanup_entity);
          foreach ($bundles as $bundle => $bundle_data) {
            $all_bundle_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions($cleanup_entity, $bundle);
            if (isset($all_bundle_fields['uid'])) {
              $entities = $storage->loadByProperties(['uid' => $uid]);
            }
          }
        }
        catch (\Exception $e) {
          $errors[] = 'Could not load entities of type ' . $cleanup_entity . ' by uid ' . $uid;
          continue;
        }
        try {
          foreach ($entities as $entity) {
            $entity->delete();
          }
        }
        catch (\Exception $e) {
          $errors[] = $e->getMessage();
          continue;
        }
      }
    }
    if ($errors) {
      $this->fail(implode(PHP_EOL, $errors));
    }
  }

}