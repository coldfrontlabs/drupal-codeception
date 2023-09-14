<?php

namespace Codeception\Module;

use Codeception\Configuration;
use Codeception\Lib\ModuleContainer;
use Codeception\Module;
use Codeception\Module\DrupalBootstrap\EventsAssertionsTrait;
use Codeception\TestDrupalKernel;
use DrupalFinder\DrupalFinder;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class DrupalBootstrap.
 *
 * ### Example
 * #### Example (DrupalBootstrap)
 *     modules:
 *        - DrupalBootstrap:
 *            root: './web'
 *            site_path: 'sites/default'
 *            http_host: 'mysite.local'
 *
 * @package Codeception\Module
 */
class DrupalBootstrap extends Module {

  use EventsAssertionsTrait;

  /**
   * Default module configuration.
   *
   * @var array
   */
  protected $config = [
    'site_path' => 'sites/default',
  ];

  /**
   * Track wether we enabled the webprofiler module or not.
   *
   * @var bool
   */
  protected $enabledWebProfiler = FALSE;

  /**
   * DrupalBootstrap constructor.
   *
   * @param \Codeception\Lib\ModuleContainer $container
   *   Module container.
   * @param null|array $config
   *   Array of configurations or null.
   *
   * @throws \Codeception\Exception\ModuleConfigException
   * @throws \Codeception\Exception\ModuleException
   */
  public function __construct(ModuleContainer $container, $config = NULL) {
    parent::__construct($container, $config);
    if (!isset($this->config['root'])) {

      $drupalRoot = $this->getDrupalRoot();

      // Autodetect Drupal root.
      if ($drupalRoot) {
        $this->_setConfig(['root' => $drupalRoot]);
      }
      else {
        // Otherwise try what you can.
        $this->_setConfig(['root' => Configuration::projectDir() . 'web']);
      }
    }
    chdir($this->_getConfig('root'));
    if (isset($this->config['http_host'])) {
      $_SERVER['HTTP_HOST'] = $this->config['http_host'];
    }
    $request = Request::createFromGlobals();
    $autoloader = require $this->_getConfig('root') . '/autoload.php';
    $kernel = new TestDrupalKernel('prod', $autoloader, $this->_getConfig('root'));
    $kernel->bootTestEnvironment($this->_getConfig('site_path'), $request);
  }

  /**
   * Try to find where Drupal's root directory is.
   *
   * @return string|bool
   *   Return the string absolute path to the Drupal root directory, FALSE otherwise.
   */
  public function getDrupalRoot(): ?string {
    $drupalFinder = new DrupalFinder();
    $drupalRoot = $drupalFinder->getDrupalRoot();
    return !is_null($drupalRoot) ? $drupalRoot : FALSE;
  }
  
  /**
   * Enabled dependent modules.
   */
  public function _beforeSuite($settings = []) {
    $module_handler = \Drupal::service('module_handler');
    if (!$module_handler->moduleExists('webprofiler')) {
      $this->enabledWebProfiler = TRUE;
      \Drupal::service('module_installer')->install(['webprofiler']);
    }
  }

  /**
   * Disable modules which were enabled.
   */
  public function _afterSuite($settings = []) {
    if ($this->enabledWebProfiler) {
      $this->enabledWebProfiler = FALSE;
      \Drupal::service('module_installer')->uninstall(['webprofiler']);
    }
  }

}
