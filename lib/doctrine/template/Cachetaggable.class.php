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
      'uniqueColumn'    =>  'id',
      'uniqueKeyFormat' =>  '%d',
      'versionColumn'   =>  'object_version',
    );

    /**
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

      if (! is_string($versionColumn) or 0 >= strlen($versionColumn))
      {
        throw new sfConfigurationException(
          sprintf(
            'sfCacheTaggingPlugin: "Cachetaggable" behaviors "versionColumn" ' .
              'should be string and not empty, passed "%s"',
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
     * @return string Object namespace to store tags
     */
    protected function getInvokerNamespace ()
    {
      return $this->invokerNamespace;
    }


    /**
     * @return array object tags (self and external from ->addTags())
     */
    public function getTags ()
    {
      $this
        ->getContentTagHandler()
        ->addContentTags(
          array(
            $this->getTagName()             => $this->getObjectVersion(),
            get_class($this->getInvoker())  => $this->getObjectVersion(),
          ),
          $this->getInvokerNamespace()
        );

      return $this
        ->getContentTagHandler()
        ->getContentTags($this->getInvokerNamespace());
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
     * @param string $tagName
     * @param int|string $tagVersion
     */
    public function addTag ($tagName, $tagVersion)
    {
      $this
        ->getContentTagHandler()
        ->setContentTag($tagName, $tagVersion, $this->getInvokerNamespace());
    }

    /**
     * @throws LogicException
     * @return string
     */
    public function getTagName ()
    {
      /* @var $object Doctrine_Record */
      $object = $this->getInvoker();

      if ($object->isNew())
      {
        throw new LogicException(
          'To call ->getTagName() you should save it before'
        );
      }

      $columnValues = array(get_class($object));

      foreach ((array) $this->getOption('uniqueColumn') as $columnName)
      {
        if ($object->getTable()->hasColumn($columnName))
        {
          $columnValues[] = $object[$columnName];
        }
      }

      return call_user_func_array(
        'sprintf',
        array_merge(
          array("%s_{$this->getOption('uniqueKeyFormat')}"),
          $columnValues
        )
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
      $this
        ->getInvoker()
        ->offsetSet($this->getOption('versionColumn'), $version);

      return $this->getInvoker();
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
     * Returns Cache manger tagger
     *
     * @return sfTaggingCache
     */
    public function getTaggingCache ()
    {
      return sfContext::getInstance()
        ->getViewCacheManager()
        ->getTaggingCache();
    }

    /**
     * @return sfContentTagHandler
     */
    protected function getContentTagHandler ()
    {
      return sfContext::getInstance()
        ->getViewCacheManager()
        ->getContentTagHandler();
    }
  }