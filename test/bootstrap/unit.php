<?php

if (!isset($_SERVER['SYMFONY']))
{
  $_SERVER['SYMFONY'] = dirname(__FILE__).'/../../../../lib/vendor/symfony/lib';
}


if (!isset($_SERVER['SYMFONY']))
{
  throw new RuntimeException('Could not find symfony core libraries.');
}

include_once $_SERVER['SYMFONY'].'/autoload/sfCoreAutoload.class.php';
sfCoreAutoload::register();

$configuration = new sfProjectConfiguration(dirname(__FILE__).'/../fixtures/project');
include_once $configuration->getSymfonyLibDir().'/vendor/lime/lime.php';

function sfCacheTaggingPlugin_autoload_again($class)
{
  $autoload = sfSimpleAutoload::getInstance();
  $autoload->reload();

  return $autoload->autoload($class);
}
spl_autoload_register('sfCacheTaggingPlugin_autoload_again');

$config = dirname(__FILE__).'/../../config/sfCacheTaggingPluginConfiguration.class.php';

if (is_file($config))
{
  /**
   * Initialize first sfDoctrinePlugin!
   */
  include_once $_SERVER['SYMFONY'].'/plugins/sfDoctrinePlugin/config/sfDoctrinePluginConfiguration.class.php';
  $sfDoctrinePlugin_configuration = new sfDoctrinePluginConfiguration(
    $configuration, dirname(__FILE__).'/../..', 'sfDoctrinePlugin'
  );

  include_once $config;
  $plugin_configuration = new sfCacheTaggingPluginConfiguration(
    $configuration, dirname(__FILE__).'/../..', 'sfCacheTaggingPlugin'
  );
}
else
{
  $plugin_configuration = new sfPluginConfigurationGeneric($configuration, dirname(__FILE__).'/../..', 'sfCacheTaggingPlugin');
}
