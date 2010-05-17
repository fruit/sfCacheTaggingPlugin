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

    protected $tags = array();

    /**
     * __construct
     *
     * @param string $array
     * @return void
     */
    public function __construct (array $options = array())
    {
      $this->_options = Doctrine_Lib::arrayDeepMerge($this->_options, $options);

      $versionColumn = $this->getOption('versionColumn');

      if (! is_string($versionColumn) or 0 > strlen($versionColumn))
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
        new Doctrine_Template_Listener_Cachetaggable($this->_options)
      );
    }

    /**
     * @return array assoc array as $objectClass => $objectVersion
     */
    protected function fetchTags ()
    {
      return array(
        $this->getTagName() => $this->getObjectVersion(),
        get_class($this->getInvoker()) => $this->getObjectVersion(),
      );
    }

    /**
     * @return array object tags (self and external from ->addTags())
     */
    public function getTags ()
    {
      return array_merge($this->tags, $this->fetchTags());
    }

    /**
     * Adds many tags to the object
     *
     * @param array|ArrayAccess|Doctrine_Collection_Cachetaggable|Doctrine_Record $tags
     */
    public function addTags ($tags)
    {
      $tags = sfCacheTaggingToolkit::formatTags($tags);

      foreach ($tags as $tagName => $tagVersion)
      {
        $this->addTag($tagName, $tagVersion);
      }
    }

    /**
     * Adds new tag to the object
     *
     * @param string $tagName
     * @param int|string $tagVersion
     */
    public function addTag ($tagName, $tagVersion)
    {
      sfCacheTaggingToolkit::addTag($this->tags, $tagName, $tagVersion);
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

      foreach ((array) $this->_options['uniqueColumn'] as $column)
      {
        $methodName = sprintf('get%s', sfInflector::camelize($column));

        $callable = new sfCallable(array($object, $methodName));

        try
        {
          $columnValues[] = $callable->call();
        }
        catch (Exception $e)
        {
          throw new sfConfigurationException(
            sprintf(
              'Table "%s" does not have a column "%s". ' .
                'After you fix this column name, you should rebuild your models',
              sfInflector::tableize(get_class($object)),
              $column
            )
          );
        }
      }

      return call_user_func_array(
        'sprintf',
        array_merge(
          array("%s_{$this->_options['uniqueKeyFormat']}"),
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
      $this->getInvoker()->{$this->_options['versionColumn']} = $version;

      return $this->getInvoker();
    }

    /**
     * Fetches a version of the object
     *
     * @return Doctrine_Record
     */
    public function getObjectVersion ()
    {
      return $this->getInvoker()->{$this->_options['versionColumn']};
    }

    /**
     * Returns Cache manger tagger
     *
     * @return sfTagCache
     */
    public function getTagger ()
    {
      return sfContext::getInstance()->getViewCacheManager()->getTagger();
    }
  }