<?php

if (!isset($app))
{
  $app = 'frontend';
}

if (!isset($_SERVER['SYMFONY']))
{
  $_SERVER['SYMFONY'] = dirname(__FILE__).'/../../../../lib/vendor/symfony/lib';
}

require_once $_SERVER['SYMFONY'].'/autoload/sfCoreAutoload.class.php';
sfCoreAutoload::register();

function sfCacheTaggingPlugin_cleanup()
{
  sfToolkit::clearDirectory(dirname(__FILE__).'/../fixtures/project/cache');
  sfToolkit::clearDirectory(dirname(__FILE__).'/../fixtures/project/log');
}
sfCacheTaggingPlugin_cleanup();
// hard to debug, when logs and cache is removed on test complete
//register_shutdown_function('sfCacheTaggingPlugin_cleanup');

function sfCacheTaggingPlugin_autoload_again($class)
{
  $autoload = sfSimpleAutoload::getInstance();
  $autoload->reload();

  return $autoload->autoload($class);
}
spl_autoload_register('sfCacheTaggingPlugin_autoload_again');

require_once dirname(__FILE__).'/../fixtures/project/config/ProjectConfiguration.class.php';

$configuration = ProjectConfiguration::getApplicationConfiguration($app, 'test', isset($debug) ? $debug : true);
sfContext::createInstance($configuration);
