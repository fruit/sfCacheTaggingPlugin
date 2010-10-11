<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * Additional setup to table and its objects
   * Adds new table column "object_version" and one method to creates tag names
   *
   * @package sfCacheTaggingPlugin
   * @subpackage doctrine
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class Doctrine_Template_Cachetaggable extends Doctrine_Template
  {
    /**
     * Array of Sortable options
     *
     * @var string
     */
    protected $_options = array(
      'uniqueColumn'    => array(),
      'uniqueKeyFormat' => '',
      'versionColumn'   => 'object_version',
      'skipOnChange'    => array(),
    );

    /**
     * Copy & pasted from Doctrine_Record::toArray() 
     *
     * @var integer $_state the state of this record
     * @see Doctrine_Record::STATE_* constants
     */
    protected $_state = null;


    protected $objectIdentifiers = array();

    /**
     * Object unique namespace name to store Doctrine_Record's tags
     *
     * @var string
     */
    protected $invokerNamespace = null;

    /**
     * __construct
     *
     * @param string $array
     * @return void
     */
    public function __construct (array $options = array())
    {
      $this->_options = Doctrine_Lib::arrayDeepMerge(
        $this->getOptions(), $options
      );

      $this->invokerNamespace = sprintf(
        '%s/%s', __CLASS__, sfCacheTaggingToolkit::generateVersion()
      );

      $versionColumn = $this->getOption('versionColumn');

      if (! is_string($versionColumn) || 0 >= strlen($versionColumn))
      {
        throw new sfConfigurationException(
          sprintf(
            'sfCacheTaggingPlugin: "%s" behaviors "versionColumn" ' .
              'should be string and not empty, passed "%s"',
            sfCacheTaggingToolkit::TEMPLATE_NAME,
            (string) $versionColumn
          )
        );
      }
    }

    /**
     * Set table definition for sortable behavior
     * (borrowed and modified from Sluggable in Doctrine core)
     *
     * @return void
     */
    public function setTableDefinition ()
    {
      $this->hasColumn(
        $this->getOption('versionColumn'),
        'string',
        10 + sfCacheTaggingToolkit::getPrecision(),
        array('notnull' => false)
      );

      $this->addListener(
        new Doctrine_Template_Listener_Cachetaggable($this->getOptions())
      );
    }

    /**
     * @return string Object's namespace to store tags
     */
    protected function getInvokerNamespace ()
    {
      return $this->invokerNamespace;
    }

    /**
     * Retrieves object's tags and appended tags
     *
     * @param boolean $deep collect tags from joined related objects
     * @return array object tags (self and external from ->addTags())
     */
    public function getTags ($deep = false)
    {
      try
      {
        $tagHandler = $this->getContentTagHandler();
      }
      catch (sfCacheDisabledException $e)
      {
        return array();
      }

      if (
          $this->_state == Doctrine_Record::STATE_LOCKED
        ||
          $this->_state == Doctrine_Record::STATE_TLOCKED
      )
      {
        return array();
      }

      $invoker = $this->getInvoker();

      $stateBeforeLock = $this->_state;

      $this->_state = $invoker->exists()
        ? Doctrine_Record::STATE_LOCKED
        : Doctrine_Record::STATE_TLOCKED;

      $className = sfCacheTaggingToolkit::getBaseClassName(get_class($invoker));

      $objectVersion = $this->getObjectVersion();
      
      $tagHandler->addContentTags(
        array(
          $this->getTagName() => $objectVersion,
          $className          => $objectVersion,
        ),
        $this->getInvokerNamespace()
      );
      
      if ($deep)
      {
        foreach ($invoker->getReferences() as $reference)
        {
          if ( ! $reference instanceof Doctrine_Null)
          {
            $table = $reference->getTable();

            if (! $table->hasTemplate(sfCacheTaggingToolkit::TEMPLATE_NAME))
            {
              continue;
            }
            
            $tagHandler->addContentTags(
              $reference->getTags(true), $this->getInvokerNamespace()
            );
          }
        }
      }

      /**
       * @todo mistical code (switching added tags with fetch on the fly)
       *       maybe copy & past from toArray()?
       */
      $tags = $tagHandler->getContentTags($this->getInvokerNamespace());

      $tagHandler->removeContentTags($this->getInvokerNamespace());

      $this->_state = $stateBeforeLock;

      return $tags;
    }

    /**
     * Adds many tags to the object
     *
     * @param mixed $tags Adds tags to current object.
     *                    Supported types are: Doctrine_Record, ArrayAccess,
     *                    Doctrine_Collection_Cachetaggable, array.
     * @return boolean
     */
    public function addTags ($tags)
    {
      try
      {
        $this
          ->getContentTagHandler()
          ->addContentTags($tags, $this->getInvokerNamespace());

        return true;
      }
      catch (sfCacheDisabledException $e)
      {
        
      }

      return false;
    }

    /**
     * Adds new tag to the object
     *
     * @param string      $tagName
     * @param int|string  $tagVersion
     * @return boolean
     */
    public function addTag ($tagName, $tagVersion)
    {
      try
      {
        $this
          ->getContentTagHandler()
          ->setContentTag($tagName, $tagVersion, $this->getInvokerNamespace());

        return true;
      }
      catch (sfCacheDisabledException $e)
      {

      }

      return false;
    }

    /**
     * Retrieves object unique tag name based on its class
     *
     * @throws LogicException
     * @return string
     */
    public function getTagName ()
    {
      /* @var $object Doctrine_Record */
      $object = $this->getInvoker();

      $objectClassName = get_class($object);

      if ($object->isNew())
      {
        throw new LogicException(
          sprintf(
            'Method %s::getTagName() is allowed only for saved objects',
            $objectClassName
          )
        );
      }

      $table = $object->getTable();

      $columnValues = array(
        sfCacheTaggingToolkit::getBaseClassName($objectClassName)
      );

      $uniqueColumns = (array) $this->getOption('uniqueColumn');

      $separator = sfCacheTaggingToolkit::getModelTagNameSeparator();

      if (0 === count($uniqueColumns))
      {
        if (! array_key_exists($objectClassName, $this->objectIdentifiers))
        {
          $uniqueColumns = $table->getIdentifierColumnNames();

          $keyFormat = implode($separator, array_fill(0, count($uniqueColumns), '%s'));

          $this->objectIdentifiers[$objectClassName] = array(
            $uniqueColumns,
            $keyFormat
          );
        }
        else
        {
          list($uniqueColumns, $keyFormat)
            = $this->objectIdentifiers[$objectClassName];
        }
      }
      else
      {
        $keyFormat = $this->getOption('uniqueKeyFormat');

        if (! $keyFormat)
        {
          $keyFormat = implode($separator, array_fill(0, count($uniqueColumns), '%s'));
        }
      }

      /**
       * Hack to speed-up Doctrine_Record::get()
       */
      $accessorOverrideAttribute = $table->getAttribute(
        Doctrine_Core::ATTR_AUTO_ACCESSOR_OVERRIDE
      );

      $table->setAttribute(Doctrine_Core::ATTR_AUTO_ACCESSOR_OVERRIDE, false);

      foreach ($uniqueColumns as $columnName)
      {
        $columnValues[] = $object->get($columnName);
      }

      $table->setAttribute(
        Doctrine_Core::ATTR_AUTO_ACCESSOR_OVERRIDE,
        $accessorOverrideAttribute
      );

      return call_user_func_array(
        'sprintf', array_merge(array("%s{$separator}{$keyFormat}"), $columnValues)
      );
    }

    /**
     * Updates version of the object
     *
     * @param string $version
     * @return Doctrine_Record
     */
    public function setObjectVersion ($version)
    {
      return $this->getInvoker()->set($this->getOption('versionColumn'), $version);
    }

    /**
     * Fetches a version of the object
     *
     * @return Doctrine_Record
     */
    public function getObjectVersion ()
    {
      return $this->getInvoker()->get($this->getOption('versionColumn'));
    }

    /**
     * Updates object version
     * @return Doctrine_Recotd
     */
    public function updateObjectVersion ()
    {
      return $this->setObjectVersion(sfCacheTaggingToolkit::generateVersion());
    }

    /**
     * Retrieves handler to manage tags
     *
     * @return sfContentTagHandler
     */
    protected function getContentTagHandler ()
    {
      return sfCacheTaggingToolkit::getTaggingCache()->getContentTagHandler();
    }
  }