<?php

/*
 * This file is part of the sfCacheTaggingPlugin package.
 * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Adds preSave, postSave, preDelete hocks to object version be valid and fresh
 *
 * @package sfCacheTaggingPlugin
 * @author Ilya Sabelnikov <fruit.dev@gmail.com>
 */
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

  /**
   * Returns cache class to work with cache data, keys and locks
   *
   * @return sfTagCache
   */
  private function getTagger ()
  {
    if (sfContext::hasInstance())
    {
      $manager = sfContext::getInstance()->getViewCacheManager();

      if (! $manager instanceof sfViewCacheTagManager)
      {
        throw new sfConfigurationException('sfCacheTaggingPlugin will work only with own sfViewCacheTagManager. Please, edit yours factories.yml to fix this problem');
      }

      $tagger = $manager->getTagger();

      if (! $tagger instanceof sfTagCache)
      {
        throw new sfConfigurationException('sfCacheTaggingPlugin will work only with own sf%cache_engine%CacheTag class. Please, edit yours factories.yml to fix this problem');
      }

      return $tagger;
    }

    return null;
  }

  /**
   * Pre deletion hock - removes associated tag from the cache
   *
   * @param Doctrine_Event $event
   * @return void
   */
  public function preDelete (Doctrine_Event $event)
  {
    if (! is_null($taggerCache = $this->getTagger()))
    {
      $taggerCache->deleteTag($event->getInvoker()->getTagName());
    }
  }

  /**
   * pre saving hook - sets new object`s version to store it in the database
   *
   * @param string $Doctrine_Event
   * @return void
   */
  public function preSave (Doctrine_Event $event)
  {
    $object = $event->getInvoker();

    $object->setObjectVersion(sprintf("%0.0f", pow(10, 10) * microtime(true)));

    if ($object->isNew() and ! is_null($taggerCache = $this->getTagger()))
    {
      $objectClassName = get_class($object);

      $taggerCache->setTag($objectClassName, $object->getObjectVersion());

      # old version to handle new objects with cache content
      /*
      $containers = sfConfig::get('app_sfcachetaggingplugin_containers', array());

      if (array_key_exists($objectClassName, $containers) and 0 < count($containers))
      {
        foreach ($containers[$objectClassName] as $cacheKey)
        {
          $taggerCache->remove($cacheKey);
        }
      }*/
    }
  }

  /**
   * post saving hook - updates/creates the version tag (in the cache) of the stored object
   *
   * @param Doctrine_Event $event
   */
  public function postSave (Doctrine_Event $event)
  {
    $object = $event->getInvoker();
    
    if (! is_null($taggerCache = $this->getTagger()))
    {
      $taggerCache->setTag(
        $object->getTagName(),
        $object->getObjectVersion(),
        sfConfig::get('app_sfcachetaggingplugin_tag_lifetime', 86400)
      );
    }
  }
}