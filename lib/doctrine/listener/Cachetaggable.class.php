<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * Adds preSave, postSave, preDelete hocks to object
   * version be valid and fresh
   *
   * @package sfCacheTaggingPlugin
   * @subpackage doctrine
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class Doctrine_Template_Listener_Cachetaggable
    extends Doctrine_Record_Listener
  {
    /**
     * Array of sortable options
     *
     * @var array
     */
    protected $_options = array();

    /**
     * Removing object does not have a tag name
     * This variable keeps tag name till postDelete hook is executed
     *
     * @var string
     */
    protected $preDeleteTagName = null;

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
     * @return sfTaggingCache
     */
    public function getTaggingCache ()
    {
      if (! sfContext::hasInstance())
      {
        throw new UnexpectedValueException(
          'sfContext instance is not initialized'
        );
      }

      $manager = sfContext::getInstance()->getViewCacheManager();

      if (! $manager instanceof sfViewCacheTagManager)
      {
        throw new UnexpectedValueException(
          'Application\'s sfViewManager should be the instance ' .
          'of sfViewCacheTagManager'
        );
      }

      return $manager->getTaggingCache();
    }

    /**
     * Pre deletion hook - saves object tag_name to remove it on postDelete
     *
     * @param Doctrine_Event $event
     * @return void
     */
    public function preDelete (Doctrine_Event $event)
    {
      $this->preDeleteTagName = $event->getInvoker()->getTagName();
    }

    /**
     * Post deletion hook - removes object tag
     *
     * @param Doctrine_Event $event
     */
    public function postDelete (Doctrine_Event $event)
    {
      try
      {
        $taggingCache = $this->getTaggingCache();

        if (null !== $this->preDeleteTagName)
        {
          $taggingCache->deleteTag($this->preDeleteTagName);
        }
      }
      catch (UnexpectedValueException $e)
      {

      }

      $this->preDeleteTagName = null;
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
      
      # do not set new object version if no fields are modified
      if (0 == count($object->getModified()))
      {
        return;
      }

      $object->setObjectVersion(sfCacheTaggingToolkit::generateVersion());
    }

    /**
     * post saving hook - updates/creates the version tag (in the cache)
     *  of the stored object
     *
     * @param Doctrine_Event $event
     */
    public function postSave (Doctrine_Event $event)
    {
      try
      {
        $taggingCache = $this->getTaggingCache();
      }
      catch (UnexpectedValueException $e)
      {
        return;
      }

      $object = $event->getInvoker();

      $lastModifiedColumns = $object->getLastModified();

      # do not update tags in cache if no fields was modified
      if (0 == count($lastModifiedColumns))
      {
        return;
      }

      # When SoftDelete behavior saves "deleted" object
      # do not update object version on when "deleted" object is saving
      if ($object->getTable()->hasTemplate('SoftDelete'))
      {
        $softDeleteTemplate = $object->getTable()->getTemplate('SoftDelete');
        $deleteAtField = $softDeleteTemplate->getOption('name');
        
        if (array_key_exists($deleteAtField, $lastModifiedColumns))
        {
          # skip if SoftDeletes sets deleted_at field
          return;
        }
      }

      $tagLifetime = sfCacheTaggingToolkit::getTagLifetime();

      $taggingCache->setTag(
        $object->getTagName(), $object->getObjectVersion(), $tagLifetime
      );

      $formatedClassName = sfCacheTaggingToolkit::getBaseClassName(get_class($object));

      $taggingCache->setTag(
        $formatedClassName,
        $object->getObjectVersion(),
        $tagLifetime
      );

      # updating object tags
      $object->addTag($object->getTagName(), $object->getObjectVersion());
      $object->addTag(
        $formatedClassName,
        $object->getObjectVersion()
      );
    }

    /**
     * pre dql update hook - add updated
     *
     * @param Doctrine_Event $event
     * @return void
     */
    public function preDqlUpdate (Doctrine_Event $event)
    {
      try
      {
        $taggingCache = $this->getTaggingCache();
      }
      catch (UnexpectedValueException $e)
      {
        return;
      }

      /* @var $q Doctrine_Query */
      $q = $event->getQuery();

      $updateVersion = sfCacheTaggingToolkit::generateVersion();
      $q->set($this->getOption('versionColumn'), $updateVersion);

      $selectQuery = $event->getInvoker()->getTable()->createQuery();
      $selectQuery->select();

      foreach ($q->getDqlPart('where') as $whereCondition)
      {
        $selectQuery->addWhere($whereCondition);
      }

      $params = $q->getParams();
      $params['set'] = array();
      $selectQuery->setParams($params);

      $lifetime = sfCacheTaggingToolkit::getTagLifetime();

      foreach ($selectQuery->execute() as $object)
      {
        $taggingCache->setTag($object->getTagName(), $updateVersion, $lifetime);
      }
      
      $taggingCache->setTag(
        sfCacheTaggingToolkit::getBaseClassName(get_class($object)),
        $updateVersion,
        $lifetime
      );
    }

    /**
     * pre dql delete hook - remove object tags from tagger
     *
     * @param Doctrine_Event $event
     * @return void
     */
    public function preDqlDelete (Doctrine_Event $event)
    {
      try
      {
        $taggingCache = $this->getTaggingCache();
      }
      catch (UnexpectedValueException $e)
      {
        return;
      }

      /* @var $q Doctrine_Query */
      $q = clone $event->getQuery();

      # conflicts with build-in SoftDelete behavior
      # SoftDelete passes UPDATE query to the preDqlDelete event
      if ($q->getType() != Doctrine_Query::DELETE)
      {
        return;
      }

      $params = $q->getParams();
      $params['set'] = array();
      $q->setParams($params);

      foreach ($q->select()->execute() as $object)
      {
        $taggingCache->deleteTag($object->getTagName());
      }
    }
  }
