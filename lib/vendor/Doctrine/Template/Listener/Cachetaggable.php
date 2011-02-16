<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2011 Ilya Sabelnikov <fruit.dev@gmail.com>
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
     * Flag to be clear in self::postSave() if saved object was new or not
     * 
     * @var boolean
     */
    protected $wasObjectNew = null;

    /**
     * __construct
     *
     * @param array $options
     * @return null
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
    protected function getTaggingCache ()
    {
      return sfCacheTaggingToolkit::getTaggingCache();
    }

    /**
     * Pre deletion hook - saves object tag_name to remove it on postDelete
     *
     * @param Doctrine_Event $event
     * @return null
     */
    public function preDelete (Doctrine_Event $event)
    {
      $this->preDeleteTagName = $event->getInvoker()->obtainTagName();
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

        $invoker = $event->getInvoker();

        $taggingCache->setTag(
          $invoker->obtainCollectionName(),
          sfCacheTaggingToolkit::generateVersion()
        );

      }
      catch (sfCacheException $e)
      {

      }

      $this->preDeleteTagName = null;
    }

    public function preInsert (Doctrine_Event $event)
    {
      $invoker = $event->getInvoker();
      /* @var Doctrine_Record $invoker */
    }

    public function postInsert (Doctrine_Event $event)
    {
      $invoker = $event->getInvoker();
      /* @var Doctrine_Record $invoker */
    }

    /**
     * pre saving hook - sets new object`s version to store it in the database
     *
     * @param Doctrine_Event $event
     * @return null
     */
    public function preSave (Doctrine_Event $event)
    {
      $invoker = $event->getInvoker();

      $modifiedColumns = $invoker->getModified();

      # do not set new object version if no fields are modified
      if (! $invoker->isNew() && 0 == count($modifiedColumns))
      {
        return;
      }

      $this->wasObjectNew = $invoker->isNew();

      $skipOnChange = (array) $this->getOption('skipOnChange');

      if (0 < count($skipOnChange))
      {
        $columnsChanged = array_keys($modifiedColumns);

        if (0 == count(array_diff($columnsChanged, $skipOnChange)))
        {
          return;
        }
      }

      $invoker->assignObjectVersion(sfCacheTaggingToolkit::generateVersion());
    }

    /**
     * post saving hook - updates/creates the version tag (in the cache)
     *  of the stored object
     *
     * @param Doctrine_Event $event
     * @return null
     */
    public function postSave (Doctrine_Event $event)
    {
      try
      {
        $taggingCache = $this->getTaggingCache();
      }
      catch (sfCacheException $e)
      {
        return;
      }

      $invoker = $event->getInvoker();

      $lastModifiedColumns = $invoker->getLastModified();

      /**
       * If user tries to execute ->replace(), the $invoker->exists()
       * will return FALSE, and lastModifiedColumns will be always empty.
       *
       * If it's ->save(), then $invoker->exists() will return TRUE
       */
      if ($invoker->exists() && 0 == count($lastModifiedColumns))
      {
        $this->wasObjectNew = null;

        return;
      }

      $table = $invoker->getTable();

      # When SoftDelete behavior saves "deleted" object
      # do not update object version on when "deleted" object is saving
      if ($table->hasTemplate('SoftDelete'))
      {
        $softDeleteTemplate = $table->getTemplate('SoftDelete');
        $deleteAtField = $softDeleteTemplate->getOption('name');

        if (array_key_exists($deleteAtField, $lastModifiedColumns))
        {
          $this->wasObjectNew = null;

          # skip if SoftDeletes sets deleted_at field
          return;
        }
      }

      $invokerObjectVersion = $invoker->obtainObjectVersion();

      $isToInvalidateCollectionVersion
        = (boolean) $this->getOption('invalidateCollectionVersionOnUpdate');
      
      if ($this->wasObjectNew || $isToInvalidateCollectionVersion)
      {
        $formatedClassName = sfCacheTaggingToolkit::getBaseClassName(
          $table->getClassnameToReturn()
        );

        $taggingCache->setTag($formatedClassName, $invokerObjectVersion);

        $invoker->addVersionTag($formatedClassName, $invokerObjectVersion);
      }

      $invokerTagName = $invoker->obtainTagName();

      $taggingCache->setTag($invokerTagName, $invokerObjectVersion);

      # updating object tags
      $invoker->addVersionTag($invokerTagName, $invokerObjectVersion);

      $this->wasObjectNew = null;
    }

    /**
     * pre dql update hook - add updated
     *
     * @param Doctrine_Event $event
     * @return null
     */
    public function preDqlUpdate (Doctrine_Event $event)
    {
      try
      {
        $taggingCache = $this->getTaggingCache();
      }
      catch (sfCacheException $e)
      {
        return;
      }

      /* @var $q Doctrine_Query */
      $q = $event->getQuery();

      $skipOnChange = (array) $this->getOption('skipOnChange');

      $columnNamesToSet = array();

      foreach ($q->getDqlPart('set') as $set)
      {
        if (preg_match('/(\w+)\ =\ /', $set, $m))
        {
          $columnNamesToSet[] = $m[1];
        }
      }

      /**
       * @todo test this block
       */
      if (
          (0 < count($skipOnChange))
        &&
          (0 == count(array_intersect($columnNamesToSet, $skipOnChange)))
      )
      {
        return false;
      }
      
      $table = $event->getInvoker()->getTable();

      $collectionVersionName = sfCacheTaggingToolkit::getBaseClassName(
        $table->getClassnameToReturn()
      );

      if ($table->hasTemplate('SoftDelete'))
      {
        /**
         * @todo test this block
         *       it seems, that in test schame.yml SoftDelete now is
         *       every where before Cachetaggable behavior
         */
        $softDeleteTemplate = $table->getTemplate('SoftDelete');
        if (in_array($softDeleteTemplate->getOption('name'), $columnNamesToSet))
        {
          # invalidate collection, if soft delete sets deleted_at field
          $taggingCache->setTag(
            $collectionVersionName,
            sfCacheTaggingToolkit::generateVersion()
          );
        }
      }

      $updateVersion = sfCacheTaggingToolkit::generateVersion();
      $q->set($this->getOption('versionColumn'), $updateVersion);

      $selectQuery = $table->createQuery();
      $selectQuery->select();

      foreach ($q->getDqlPart('where') as $whereCondition)
      {
        $selectQuery->addWhere($whereCondition);
      }

      $params = $q->getParams();
      $params['set'] = array();
      $selectQuery->setParams($params);

      foreach ($selectQuery->execute() as $object)
      {
        $taggingCache->setTag($object->obtainTagName(), $updateVersion);
      }

      $isToInvalidateCollectionVersion
        = (boolean) $this->getOption('invalidateCollectionVersionOnUpdate');

      if ($isToInvalidateCollectionVersion)
      {
        $taggingCache->setTag(
          $collectionVersionName,
          sfCacheTaggingToolkit::generateVersion()
        );
      }
    }

    /**
     * pre dql delete hook - remove object tags from tagger
     *
     * @param Doctrine_Event $event
     * @return null
     */
    public function preDqlDelete (Doctrine_Event $event)
    {
      try
      {
        $taggingCache = $this->getTaggingCache();
      }
      catch (sfCacheException $e)
      {
        return;
      }

      $table = $event->getInvoker()->getTable();

      /* @var $q Doctrine_Query */
      $q = clone $event->getQuery();

      /**
       * This happens, when SoftDelete is declared before Cachetaggable
       */
      if (Doctrine_Query::UPDATE === $q->getType())
      {
        $event->getQuery()->set(
          $this->getOption('versionColumn'),
          sfCacheTaggingToolkit::generateVersion()
        );

        $q->removeDqlQueryPart('set');
      }

      $params = $q->getParams();
      $params['set'] = array();
      $q->setParams($params);

      foreach ($q->select()->execute() as $object)
      {
        $taggingCache->deleteTag($object->obtainTagName());
      }

      $taggingCache->setTag(
        sfCacheTaggingToolkit::getBaseClassName(
          $table->getClassnameToReturn()
        ),
        sfCacheTaggingToolkit::generateVersion()
      );
    }
  }
