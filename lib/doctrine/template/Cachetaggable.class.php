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
    );

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
     * @param boolean $isRecursively collect tags from joined related objects
     * @return array object tags (self and external from ->addTags())
     */
    public function getTags ($isRecursively = false)
    {
      $tagHandler = $this->getContentTagHandler();

      $invoker = $this->getInvoker();

      $className = sfCacheTaggingToolkit::getBaseClassName(get_class($invoker));

      $objectVersion = $this->getObjectVersion();
      
      $tagHandler->addContentTags(
        array(
          $this->getTagName() => $objectVersion,
          $className          => $objectVersion,
        ),
        $this->getInvokerNamespace()
      );
      
      if ($isRecursively)
      {
        $tagHandler->addContentReferencedTags(
          $invoker, $this->getInvokerNamespace(), $isRecursively
        );
      }

      $tags = $tagHandler->getContentTags($this->getInvokerNamespace());

      $tagHandler->removeContentTags($this->getInvokerNamespace());

      return $tags;
    }

    /**
     * Adds many tags to the object
     *
     * @param mixed $tags Adds tags to current object.
     *                    Supported types are: Doctrine_Record, ArrayAccess,
     *                    Doctrine_Collection_Cachetaggable, array.
     */
    public function addTags ($tags)
    {
      $this
        ->getContentTagHandler()
        ->addContentTags($tags, $this->getInvokerNamespace());
    }

    /**
     * Adds new tag to the object
     *
     * @param string      $tagName
     * @param int|string  $tagVersion
     */
    public function addTag ($tagName, $tagVersion)
    {
      $this
        ->getContentTagHandler()
        ->setContentTag($tagName, $tagVersion, $this->getInvokerNamespace());
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

      $objectTable = $object->getTable();

      $columnValues = array(
        sfCacheTaggingToolkit::getBaseClassName($objectClassName)
      );

      $uniqueColumns = (array) $this->getOption('uniqueColumn');

      if (0 === count($uniqueColumns))
      {
        if (! array_key_exists($objectClassName, $this->objectIdentifiers))
        {
          $uniqueColumns = $objectTable->getIdentifierColumnNames();

          $keyFormat = implode('_', array_fill(0, count($uniqueColumns), '%s'));

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
          $keyFormat = implode('_', array_fill(0, count($uniqueColumns), '%s'));
        }
      }

      /**
       * Hack to speed-up Doctrine_Record::get()
       */
      $accessorOverrideAttribute = $objectTable->getAttribute(
        Doctrine_Core::ATTR_AUTO_ACCESSOR_OVERRIDE
      );

      $objectTable->setAttribute(
        Doctrine_Core::ATTR_AUTO_ACCESSOR_OVERRIDE,
        false
      );

      foreach ($uniqueColumns as $columnName)
      {
        $columnValues[] = $object->get($columnName);
      }

      $objectTable->setAttribute(
        Doctrine_Core::ATTR_AUTO_ACCESSOR_OVERRIDE,
        $accessorOverrideAttribute
      );

      if (0 === count($columnValues))
      {
        throw new sfConfigurationException(
          'Please setup column names to build tag name'
        );
      }

      return call_user_func_array(
        'sprintf', array_merge(array("%s_{$keyFormat}"), $columnValues)
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
      $invoker = $this->getInvoker();

      $invoker->set($this->getOption('versionColumn'), $version);

      return $invoker;
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
     *
     */
    public function updateObjectVersion ()
    {
      $this->setObjectVersion(sfCacheTaggingToolkit::generateVersion());
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