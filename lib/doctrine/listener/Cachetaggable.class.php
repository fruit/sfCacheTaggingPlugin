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
    if (! sfContext::hasInstance())
    {
      return null;
    }

    $manager = sfContext::getInstance()->getViewCacheManager();

    if (! $manager instanceof sfViewCacheTagManager)
    {
      return null;
    }

    $tagger = $manager->getTagger();

    if (! $tagger instanceof sfTagCache)
    {
      return null;
    }

    return $tagger;
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
   * @param Doctrine_Event $event
   * @return void
   */
  public function preSave (Doctrine_Event $event)
  {
    $object = $event->getInvoker();

    $object->setObjectVersion(sfCacheTaggingToolkit::generateVersion());

    if ($object->isNew() and ! is_null($taggerCache = $this->getTagger()))
    {
      $objectClassName = get_class($object);

      $taggerCache->setTag(
        $objectClassName,
        $object->getObjectVersion(),
        sfCacheTaggingToolkit::getTagLifetime()
      );
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
        sfCacheTaggingToolkit::getTagLifetime()
      );
    }
  }

  /**
   * pre dql update hook - add updated
   *
   * @param Doctrine_Event $event
   * @return void
   */
  public function preDqlUpdate (Doctrine_Event $event)
  {
    if (! is_null($taggerCache = $this->getTagger()))
    {
      /* @var $q Doctrine_Query */
      $q = $event->getQuery();

      $updateVersion = sfCacheTaggingToolkit::generateVersion();
      $q->set($this->getOption('versionColumn'), $updateVersion);

      $updateQuery = $event->getInvoker()->getTable()->createQuery();
      $updateQuery->select('*');

      foreach ($q->getDqlPart('where') as $whereCondition)
      {
        $updateQuery->addWhere($whereCondition);
      }

      $params = $q->getParams();
      $params['set'] = array();
      $updateQuery->setParams($params);

      foreach ($updateQuery->execute() as $object)
      {
        $taggerCache->setTag(
          $object->getTagName(),
          $updateVersion,
          sfCacheTaggingToolkit::getTagLifetime()
        );
      }
    }
  }

  /**
   * pre dql delete hook - remove object tags from tagger
   *
   * @param Doctrine_Event $event
   * @return void
   */
  public function preDqlDelete (Doctrine_Event $event)
  {
    if (! is_null($taggerCache = $this->getTagger()))
    {
      /* @var $q Doctrine_Query */
      $q = clone $event->getQuery();

      foreach ($q->select('*')->execute() as $object)
      {
        $taggerCache->deleteTag($object->getTagName());
      }
    }
  }
}