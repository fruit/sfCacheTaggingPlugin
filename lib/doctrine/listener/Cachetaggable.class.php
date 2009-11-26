<?php

class Doctrine_Template_Listener_Cachetaggable extends Doctrine_Record_Listener
{
  /**
   * Array of sortable options
   *
   * @var array
   */
  protected $_options = array();

  /**
   * __construct
   *
   * @param array $options
   * @return void
   */
  public function __construct(array $options)
  {
    $this->_options = $options;
  }

  private function getCache ()
  {
    if (sfContext::hasInstance() and sfConfig::get('sf_cache'))
    {
      $manager = sfContext::getInstance()->getViewCacheManager();

      if (! $manager instanceof sfViewCacheTagManager)
      {
        throw new sfConfigurationException('sfCacheTaggingPlugin will work only with own sfViewCacheTagManager. Please, edit yours factories.yml to fix this problem');
      }

      $cache = $manager->getCache();

      if (! $cache instanceof sfCacheTagInterface)
      {
        throw new sfConfigurationException('sfCacheTaggingPlugin will work only with own sf%cache_engine%CacheTag class. Please, edit yours factories.yml to fix this problem');
      }

      return $cache;
    }

    return null;
  }

  /**
   * @param string $Doctrine_Event
   * @return void
   */
  public function postDelete(Doctrine_Event $event)
  {
    if (! is_null($cache = $this->getCache()))
    {
      $cache->removeTag($event->getInvoker()->getTagName());
    }
  }

  /**
   * @param string $Doctrine_Event
   * @return void
   */
  public function preSave (Doctrine_Event $event)
  {
    $event->getInvoker()->setObjectVersion(sprintf("%0.0f", pow(10, 10) * microtime(true)));
  }

  public function postSave (Doctrine_Event $event)
  {
    $object = $event->getInvoker();

    if (! is_null($cache = $this->getCache()))
    {
      $cache->setTag(
        $object->getTagName(),
        $object->getObjectVersion(),
        sfConfig::get('app_sfcachetaggingplugin_tag_lifetime', 86400)
      );
    }
  }
}