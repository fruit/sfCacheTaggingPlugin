<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2013 Ilya Sabelnikov <fruit.dev@gmail.com>
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
  class Doctrine_Template_Listener_Cachetaggable extends Doctrine_Record_Listener
  {
    /**
     * Array of cachetaggable options
     *
     * @var array
     */
    protected $_options = array();

    /**
     * Removed object does not have relations
     * This variable keeps tag names to remove till postDelete hook is executed
     *
     * @var array
     */
    protected $preDeleteTagNames = array();

    /**
     * Removed object does not have relations
     * This variable keeps tag names to invalidate till postDelete hook is executed
     *
     * @var array
     */
    protected $preInvalidateTagNames = array();

    /**
     * Flag to be clear in self::postSave() if saved object was new or not
     *
     * @var boolean
     */
    protected $wasObjectNew = null;

    /**
     * Flag if nothing was changed in object or changes are expected and useless.
     *
     * @var boolean
     */
    protected $skipVersionUpdate = null;

    /**
     * __construct
     *
     * @param array $options
     */
    public function __construct (array $options)
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
     * Checks if passed modified columns are all in skipOnChange list
     *
     * @param array $modified
     * @return boolean
     */
    protected function isModifiedInSkipList ($modified)
    {
      $skipOnChange = (array) $this->getOption('skipOnChange');

      if (0 < count($skipOnChange)
          && 0 == count(array_diff($modified, $skipOnChange)))
      {
        return true;
      }

      return false;
    }

    /**
     * Checks if passed modified is a SoftDelete "deleted_at" column
     *
     * @param array           $modified
     * @param Doctrine_Table  $table
     * @return boolean
     */
    protected function isModifiedIsASoftDeleteColumn ($modified, Doctrine_Table $table)
    {
      // When SoftDelete behavior saves "deleted" object
      // do not update object version on when "deleted" object is saving
      if ($table->hasTemplate('SoftDelete'))
      {
        $softDeleteTemplate = $table->getTemplate('SoftDelete');
        $deleteAtField = $softDeleteTemplate->getOption('name');

        // skip if SoftDelete sets deleted_at field
        if (in_array($deleteAtField, $modified))
        {
          return true;
        }
      }

      return false;
    }

    /**
     * Checks if one of modified columns are in list of defined columns, that
     * can invalidate collection version
     *
     * @param array $modified
     * @return boolean
     */
    protected function isModifiedAffectsCollectionVersion ($modified)
    {
      $affectsCollectionColumns = (array) $this->getOption(
        'invalidateCollectionVersionByChangingColumns'
      );

      if (0 < count($affectsCollectionColumns)
          && 0 < count(array_intersect($affectsCollectionColumns, $modified)))
      {
        return true;
      }

      return false;
    }

    /**
     * Pre deletion hook - saves object tag_name to remove it on postDelete
     *
     * @param Doctrine_Event $event
     * @return null
     */
    public function preDelete (Doctrine_Event $event)
    {
      if (! sfConfig::get('sf_cache')) return;

      $this->preDeleteTagNames = array();
      $this->preInvalidateTagNames = array();

      $invoker = $event->getInvoker();
      /* @var $invoker Doctrine_Record  */

      $unitOfWork = new Doctrine_Connection_CachetaggableUnitOfWork(
        $invoker->getTable()->getConnection()
      );

      $unitOfWork->collectDeletionsAndInvalidations($invoker);

      $this->preDeleteTagNames = $unitOfWork->getDeletions();
      $this->preInvalidateTagNames = $unitOfWork->getInvalidations();

      unset($unitOfWork);
    }

    /**
     * Post deletion hook - removes object tag
     *
     * @param Doctrine_Event $event
     */
    public function postDelete (Doctrine_Event $event)
    {
      if (! sfConfig::get('sf_cache')) return;

      $invoker = $event->getInvoker();
      /* @var $invoker Doctrine_Record  */

      $version      = sfCacheTaggingToolkit::generateVersion();
      $eventTagging = $this->getTransactionEvent($invoker->getCurrentConnection());
      $tagging      = $eventTagging ? $eventTagging : $this->getTaggingCache();

      $tagging->deleteTags($this->preDeleteTagNames);
      $tagging->invalidateTags($this->preInvalidateTagNames);
      $tagging->setTag($invoker->obtainCollectionName(), $version);

      // free used memory
      $this->preDeleteTagNames      = array();
      $this->preInvalidateTagNames  = array();
    }

    /**
     * pre saving hook - sets new object`s version to store it in the database
     *
     * @param Doctrine_Event $event
     * @return null
     */
    public function preSave (Doctrine_Event $event)
    {
      if (! sfConfig::get('sf_cache')) return;

      $invoker = $event->getInvoker();
      /* @var $invoker Doctrine_Record  */

      $this->skipVersionUpdate  = true;
      $this->wasObjectNew       = ! $invoker->exists();

      if (! $invoker->isModified(true))
      {
        return;
      }

      $modified = array_keys($invoker->getModified());

      if ($this->isModifiedInSkipList($modified))
      {
        return;
      }

      $table = $invoker->getTable();

      if ($this->isModifiedIsASoftDeleteColumn($modified, $table))
      {
        return;
      }

      $invoker->assignObjectVersion(sfCacheTaggingToolkit::generateVersion());

      $this->skipVersionUpdate = false;
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
      if (! sfConfig::get('sf_cache')) return;

      if ($this->skipVersionUpdate)
      {
        return;
      }

      $invoker = $event->getInvoker();
      /* @var $invoker sfCachetaggableDoctrineRecord  */

      $invokerObjectVersion = $invoker->obtainObjectVersion();

      $isToInvalidateCollectionVersion
        = (bool) $this->getOption('invalidateCollectionVersionOnUpdate');

      if (! $isToInvalidateCollectionVersion)
      {
        $lastModifiedColumns = array_keys($invoker->getLastModified());

        if ($this->isModifiedAffectsCollectionVersion($lastModifiedColumns))
        {
          $isToInvalidateCollectionVersion = true;
        }
      }

      $eventTagging = $this->getTransactionEvent($invoker->getCurrentConnection());
      $tagging      = $eventTagging ? $eventTagging : $this->getTaggingCache();

      /**
       * ->exists() returns false if it was ->replace()
       * When replace(), $this->wasObjectNew is always "true"
       */
      if ($isToInvalidateCollectionVersion || ($invoker->exists() && $this->wasObjectNew))
      {
        $collectionName = sfCacheTaggingToolkit::obtainCollectionName($invoker->getTable());

        $tagging->setTag($collectionName, $invokerObjectVersion);
      }

      $invokerTagName = $invoker->obtainTagName();

      $tagging->setTag($invokerTagName, $invokerObjectVersion);
    }

    /**
     * pre dql update hook - add updated
     *
     * @param Doctrine_Event $event
     * @return null
     */
    public function preDqlUpdate (Doctrine_Event $event)
    {
      if (! sfConfig::get('sf_cache')) return;

      /* @var $q Doctrine_Query */
      $q = $event->getQuery();

      $columnsToModify = array();

      foreach ($q->getDqlPart('set') as $set)
      {
        $matches = null;

        /**
         * Replace cases:
         *   Catalogue.is_visible = 1   => is_visible
         *   is_visible = -1            => is_visible
         */
        if (preg_match('/(\w+)\ =\ /', $set, $matches))
        {
          $columnsToModify[] = $matches[1];
        }
      }

      if ($this->isModifiedInSkipList($columnsToModify))
      {
        return false;
      }

      $table = $event->getInvoker()->getTable();
      /* @var $table Doctrine_Table  */

      $collectionName = sfCacheTaggingToolkit::obtainCollectionName($table);

      $eventTagging = $this->getTransactionEvent($q->getConnection());
      $tagging      = $eventTagging ? $eventTagging : $this->getTaggingCache();

      /**
       * @todo test, not coveraged (SoftDelete everywhere is after Cachetaggable
       */
      if ($this->isModifiedIsASoftDeleteColumn($columnsToModify, $table))
      {
        $version = sfCacheTaggingToolkit::generateVersion();
        // invalidate collection, if soft delete sets deleted_at field
        $tagging->setTag($collectionName, $version);
      }

      $updateVersion = sfCacheTaggingToolkit::generateVersion();
      $q->set($this->getOption('versionColumn'), $updateVersion);

      // stand-alone query adds SoftDelete filter in both order cases
      $selectQuery = $table->createQuery();
      $selectQuery->select();

      $where = trim(implode(' ', $q->getDqlPart('where')));

      if (! empty($where))
      {
        $selectQuery->addWhere($where);
      }

      $params = $q->getParams();
      $params['set'] = array();
      $selectQuery->setParams($params);

      $tags = array();

      $template = $table->getTemplate(sfCacheTaggingToolkit::TEMPLATE_NAME);

      foreach ($selectQuery->fetchArray() as $objectArray)
      {
        $tagName = sfCacheTaggingToolkit::obtainTagName($template, $objectArray);

        $tags[$tagName] = $updateVersion;
      }

      $tagging->setTags($tags);

      $isToInvalidateCollectionVersion
        = (bool) $this->getOption('invalidateCollectionVersionOnUpdate');

      if (! $isToInvalidateCollectionVersion
          && $this->isModifiedAffectsCollectionVersion($columnsToModify))
      {
        $isToInvalidateCollectionVersion = true;
      }

      if ($isToInvalidateCollectionVersion)
      {
        $version = sfCacheTaggingToolkit::generateVersion();
        $tagging->setTag($collectionName, $version);
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
      if (! sfConfig::get('sf_cache')) return;

      $invoker = $event->getInvoker();
      /* @var $invoker Doctrine_Record  */

      $table = $invoker->getTable();

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
      }

      $q->removeDqlQueryPart('set');

      $params = $q->getParams();
      $params['set'] = array();
      $q->setParams($params);

      // when SoftDelete goes after Cachetaggable
      if ($table->hasTemplate('SoftDelete'))
      {
        $softDeleteTemplate = $table->getTemplate('SoftDelete');
        /* @var $softDeleteTemplate Doctrine_Template_SoftDelete */
        $eventParams = $event->getParams();

        // If no SoftDelete expression added
        $alias = "{$eventParams['alias']}.{$softDeleteTemplate->getOption('name')}";
        if (! $q->contains($alias))
        {
          $softDeleteListener = new Doctrine_Template_Listener_SoftDelete(
            $softDeleteTemplate->getOptions()
          );

          $softEvent = new Doctrine_Event(
            $invoker, Doctrine_Event::RECORD_DQL_SELECT, $q, $event->getParams()
          );

          $softDeleteListener->preDqlSelect($softEvent);

          unset($softDeleteListener, $softEvent);
        }

        unset($eventParams, $softDeleteTemplate);
      }

      $objects = $q->select()->execute();

      $unitOfWork
        = new Doctrine_Connection_CachetaggableUnitOfWork($q->getConnection());

      $eventTagging = $this->getTransactionEvent($q->getConnection());
      $tagging      = $eventTagging ? $eventTagging : $this->getTaggingCache();

      foreach ($objects as $object)
      {
        $unitOfWork->collectDeletionsAndInvalidations($object);

        $deletions      = $unitOfWork->getDeletions();
        $invalidations  = $unitOfWork->getInvalidations();

        $tagging->deleteTags($deletions);
        $tagging->invalidateTags($invalidations);

        unset($deletions, $invalidations);
      }

      unset($unitOfWork);

      $tagging->setTag(
        sfCacheTaggingToolkit::obtainCollectionName($table),
        sfCacheTaggingToolkit::generateVersion()
      );
    }

    /**
     * Verifies the transaction is supported, enabled and started.
     * On success returns cache tagging event listener.
     *
     * @param Doctrine_Connection $conn
     * @return Doctrine_EventListener_Cachetaggable|false
     */
    protected function getTransactionEvent (Doctrine_Connection $conn)
    {
      // some drivers does not supports transaction,
      // then the entire transaction mechanism is disabled
      if (! $conn->supports('transactions'))
      {
        return false;
      }

      $eventTagging = $conn->getListener()->get('cache_tagging');
      /* @var $eventTagging Doctrine_EventListener_Cachetaggable */

      // can be null if Doctrine transaction support was disabled
      if (null === $eventTagging)
      {
        return false;
      }

      // 0 = STATE_SLEEP  => no started transactions
      // 1 = STATE_ACTIVE => no user transactions (Transaction inside UnitOfWork)
      // 2 = STATE_BUSY   => started user transaction

      // do not use transaction if there are not started
      if (Doctrine_Transaction::STATE_SLEEP === $conn->transaction->getState())
      {
        return false;
      }

      return $eventTagging;
    }
  }
