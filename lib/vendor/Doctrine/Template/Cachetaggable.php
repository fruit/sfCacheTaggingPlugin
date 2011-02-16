<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2011 Ilya Sabelnikov <fruit.dev@gmail.com>
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
      'invalidateCollectionVersionOnUpdate' => false,
    );

    /**
     * Copy & pasted from Doctrine_Record::toArray() 
     *
     * @var integer $_state the state of this record
     * @see Doctrine_Record::STATE_* constants
     */
    protected $_state = null;

    /**
     * Unique key names buffer
     *
     * @var array
     */
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
     * @return null
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
     * @return null
     */
    public function setTableDefinition ()
    {
      $this->hasColumn(
        $this->getOption('versionColumn'),
        'string',
        10 + sfCacheTaggingToolkit::getPrecision(),
        array('notnull' => false, 'default' => 1)
      );

      $this->addListener(
        new Doctrine_Template_Listener_Cachetaggable($this->getOptions())
      );
    }

    /**
     * Retrieves object's version tags with once added
     *
     * @param boolean   $deep
     * @return array    object tags (self and external from ->addVersionTags())
     */
    public function getTags ($deep = true)
    {
      if (
          $this->_state == Doctrine_Record::STATE_LOCKED
        ||
          $this->_state == Doctrine_Record::STATE_TLOCKED
      )
      {
        return array();
      }

      try
      {
        $tagHandler = $this->getContentTagHandler();
      }
      catch (sfCacheDisabledException $e)
      {
        return array();
      }

      $stateBeforeLock = $this->_state;

      $invoker = $this->getInvoker();

      $this->_state = $invoker->exists()
        ? Doctrine_Record::STATE_LOCKED
        : Doctrine_Record::STATE_TLOCKED;

      $invokerTags = array(
        $this->obtainTagName() => $this->obtainObjectVersion(),
      );

      $tagHandler->addContentTags($invokerTags, $this->getInvokerNamespace());
      
      if ($deep)
      {
        foreach ($invoker->getReferences() as $reference)
        {
          if ($reference instanceof Doctrine_Null)
          {
            continue;
          }

          $table = $reference->getTable();

          if (! $table->hasTemplate(sfCacheTaggingToolkit::TEMPLATE_NAME))
          {
            continue;
          }

          $tagHandler->addContentTags(
            $reference->getTags($deep),
            $this->getInvokerNamespace()
          );
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
    public function addVersionTags ($tags)
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
    public function addVersionTag ($tagName, $tagVersion)
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
     * Returns collection name and its version
     *
     * @return array
     */
    public function getCollectionTags ()
    {
      return array(
        $this->obtainCollectionName() => $this->obtainCollectionVersion()
      );
    }

    /**
     * Collections tag name
     *
     * @return string
     */
    public function obtainCollectionName ()
    {
      $invoker = $this->getInvoker();

      return sfCacheTaggingToolkit::obtainCollectionName($invoker->getTable());
    }

    /**
     * Retrieves collections tags version or initialize new version if
     * nothing was before
     *
     * @return string Version
     */
    public function obtainCollectionVersion ()
    {
      try
      {
        $version = sfCacheTaggingToolkit::obtainCollectionVersion(
          $this->obtainCollectionName()
        );

        return $version;
      }
      catch (sfCacheDisabledException $e)
      {
        
      }

      return '1';
    }

    /**
     * Retrieves object unique tag name based on its class
     *
     * @throws InvalidArgumentException
     * @return string
     */
    public function obtainTagName ()
    {
      $invoker = $this->getInvoker();

      $table = $invoker->getTable();

      $objectClassName = $table->getClassnameToReturn();

      $columnValues = array(
        sfCacheTaggingToolkit::getBaseClassName($objectClassName)
      );

      $uniqueColumns = (array) $this->getOption('uniqueColumn');

      $separator = sfCacheTaggingToolkit::getModelTagNameSeparator();

      if (0 == count($uniqueColumns))
      {
        if (! array_key_exists($objectClassName, $this->objectIdentifiers))
        {
          $uniqueColumns = $table->getIdentifierColumnNames();

          $keyFormat = str_repeat("{$separator}%s", count($uniqueColumns));

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
          $keyFormat = str_repeat("{$separator}%s", count($uniqueColumns));
        }
        else
        {
          $keyFormat = $separator . $keyFormat;
        }
      }

      $keyFormat = '%s' . $keyFormat;

      /**
       * $keyFormat is now ~ "%s:%s:%s" => MyModel:2:3
       */

      /**
       * Hack to speed-up Doctrine_Record::get()
       */
      $accessorOverrideFlag = Doctrine_Core::ATTR_AUTO_ACCESSOR_OVERRIDE;

      $accessorOverrideAttribute = $table->getAttribute($accessorOverrideFlag);

      $table->setAttribute(Doctrine_Core::ATTR_AUTO_ACCESSOR_OVERRIDE, false);

      foreach ($uniqueColumns as $columnName)
      {
        $value = $invoker->get($columnName);

        if (null === $value)
        {
          $table->setAttribute($accessorOverrideFlag, $accessorOverrideAttribute);
          
          throw new InvalidArgumentException(
            sprintf(
              'sfCacheTaggingPlugin: Object(%s) contains invalid value ' .
                '(NULL) in column "%s".',
              $objectClassName, $columnName
            )
          );
          
        }
        
        $columnValues[] = $value;
      }

      $table->setAttribute($accessorOverrideFlag, $accessorOverrideAttribute);

      return call_user_func_array(
        'sprintf', array_merge(array($keyFormat), $columnValues)
      );
    }

    /**
     * Updates version of the object
     *
     * @param string $version
     * @return Doctrine_Record
     */
    public function assignObjectVersion ($version)
    {
      return $this->getInvoker()->set($this->getOption('versionColumn'), $version);
    }

    /**
     * Fetches a version of the object
     *
     * @return Doctrine_Record
     */
    public function obtainObjectVersion ()
    {
      return $this->getInvoker()->get($this->getOption('versionColumn'));
    }

    /**
     * Updates object version
     *
     * @return Doctrine_Recotd
     */
    public function updateObjectVersion ()
    {
      return $this->assignObjectVersion(sfCacheTaggingToolkit::generateVersion());
    }

    /**
     * @return string Object's namespace to store tags
     */
    protected function getInvokerNamespace ()
    {
      return $this->invokerNamespace;
    }

    /**
     * Retrieves handler to manage tags
     *
     * @return sfContentTagHandler
     */
    protected function getContentTagHandler ()
    {
      return $this->getTaggingCache()->getContentTagHandler();
    }

    /**
     * Retrieves sfTaggigCache object
     *
     * @return sfTaggigCache
     */
    protected function getTaggingCache ()
    {
      return sfCacheTaggingToolkit::getTaggingCache();
    }
  }