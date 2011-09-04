<?php

  if ( ! isset($_SERVER['SYMFONY']))
  {
    throw new RuntimeException('Could not find symfony core libraries.');
  }

  include_once $_SERVER['SYMFONY'] . '/autoload/sfCoreAutoload.class.php';
  sfCoreAutoload::register();

  class ProjectConfiguration extends sfProjectConfiguration
  {

    public function setup ()
    {
//      print dirname(__FILE__) . '/../../../test' . "\n";
      sfConfig::set('sf_test_dir', dirname(__FILE__) . '/../../../../test');


//      require_once(dirname(__FILE__).'/../../../../config/sfCacheTaggingPluginConfiguration.class.php');

      $this->setPluginPath('sfCacheTaggingPlugin', dirname(__FILE__) . '/../../../..');
      $this->enablePlugins(array('sfDoctrinePlugin', 'sfCacheTaggingPlugin'));
    }

    public function configureDoctrine (Doctrine_Manager $manager)
    {
      sfConfig::set(
        'doctrine_model_builder_options', array('baseClassName' => 'sfCachetaggableDoctrineRecord')
      );

      $doctrineQueryCache = sfConfig::get('app_doctrine_query_cache');

      if ($doctrineQueryCache)
      {
        list($class, $param) = array_values($doctrineQueryCache);
        $manager->setAttribute(Doctrine_Core::ATTR_QUERY_CACHE, new $class($param));

        if (isset($param['lifetime']))
        {
          $manager->setAttribute(Doctrine_Core::ATTR_QUERY_CACHE_LIFESPAN, (int) $param['lifetime']);
        }
      }
    }

  }
