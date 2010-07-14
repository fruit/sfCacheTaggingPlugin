<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * Adds functionality to fetch collection tags, also it stores other
   * associated collection tags in the Doctrine_Collection_Cachetaggable
   * instance
   *
   * @package sfCacheTaggingPlugin
   * @subpackage doctrine
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class Doctrine_Collection_Cachetaggable extends Doctrine_Collection
  {
        
    /**
     * Object tags
     *
     * @var array assoc array ("key" - tag key, "value" - tag version)
     */
    protected $tags = array();

    /**
     * Collection unique namespace to store collection's objects tags
     *
     * @var string
     */
    protected $namespace = null;

    /**
     * Retrieve collection unique namespace name
     *
     * @return string
     */
    protected function getNamespace ()
    {
      return $this->namespace;
    }

    public function __construct($table, $keyColumn = null)
    {
      parent::__construct($table, $keyColumn);

      $this->namespace = sprintf(
        '%s/%s/%s',
        sfCacheTaggingToolkit::getBaseClassName(get_class($this)),
        $this->getTable()->getClassnameToReturn(),
        sfCacheTaggingToolkit::generateVersion()
      );
    }

    /**
     * @return sfContentTagHandler
     */
    protected function getContentTagHandler ()
    {
      return $this->getViewCacheManger()->getContentTagHandler();
    }

    /**
     * @return sfViewCacheTagManager
     */
    protected function getViewCacheManger ()
    {
      $manager = sfContext::getInstance()->getViewCacheManager();
      if (! $manager instanceof sfViewCacheTagManager)
      {
        throw new sfInitializationException('view cache manager is not taggable');
      }

      return $manager;
    }

    /**
     * Collects collection tags keys with its versions
     *
     * @param Doctrine_Collection_Cachetaggable $collection
     * @param boolean $deep
     * @return array
     */
    protected function fetchTags (self $collection, $deep = false)
    {
      $tags = array();

      $latestFoundVersion = 0;

      $tagger = $this->getViewCacheManger()->getTaggingCache();

      if ($collection->count())
      {
        foreach ($collection as $object)
        {
          $tags = array_merge($tags, $object->getTags($deep));

          $objectVersion = $object->getObjectVersion();

          $latestFoundVersion = $latestFoundVersion < $objectVersion
            ? $objectVersion
            : $latestFoundVersion;
        }

        if (null !== ($first = $collection->getFirst()))
        {
          $formatedClassName = sfCacheTaggingToolkit::getBaseClassName(get_class($first));

          $lastSavedVersion = $tagger->getTag($formatedClassName);

          $tags[$formatedClassName] = (null === $lastSavedVersion)
            ? $latestFoundVersion
            : $lastSavedVersion;
        }
      }
      else
      {
        /**
         * little hack, if collection is empty, emulate collection,
         * without any tags, but version should be staticaly
         * fixed (in day range)
         *
         * repeating calls with relative microtime always refresh collection tag
         * so, here is day-fixed value
         */
        $formatedClassName = sfCacheTaggingToolkit::getBaseClassName(
          $collection->getTable()->getClassnameToReturn()
        );

        $tags[$formatedClassName]
          = sfCacheTaggingToolkit::generateVersion(strtotime('today'));
      }

      return $tags;
    }

    /**
     * Returns this collection and added tags
     *
     * @param boolean $deep
     * @return array
     */
    public function getTags ($deep = false)
    {
      try
      {
        $this->addTags($this->fetchTags($this, $deep));

        return $this
          ->getContentTagHandler()
          ->getContentTags($this->getNamespace());
      }
      catch (sfInitializationException $e)
      {
        return array();
      }
    }

    /**
     * Adds additional tags to currect collection.
     * Acceptable array or Doctrine_Collection_Cachetaggable instance
     *
     * @param array|Doctrine_Collection_Cachetaggable|ArrayAccess $tags
     * @return void
     */
    public function addTags ($tags)
    {
      try
      {
        $this
          ->getContentTagHandler()
          ->addContentTags($tags, $this->getNamespace());
      }
      catch (sfInitializationException $e)
      {

      }
    }

    /**
     * Adds tag with its version to existing tags
     *
     * @param string $tagName
     * @param string|int $tagVersion
     * @return void
     */
    public function addTag ($tagName, $tagVersion)
    {
      $this->getContentTagHandler()->setContentTag(
        $tagName, $tagVersion, $this->getNamespace()
      );
    }

    /**
     * Remove all added tags
     *
     * @return void
     */
    public function removeTags ()
    {
      $this->getContentTagHandler()->removeContentTags($this->getNamespace());
    }

    /**
     * @see Doctrine_Collection::delete()
     * @return Doctrine_Collection_Cachetaggable
     */
    public function delete (Doctrine_Connection $conn = null, $clearColl = true)
    {
      $returnValue = parent::delete($conn, $clearColl);

      $this->removeTags();

      return $returnValue;
    }
  }

