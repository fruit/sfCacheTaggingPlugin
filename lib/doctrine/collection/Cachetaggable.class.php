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
     * Returns this collection and added tags
     *
     * @param Doctrine_Collection_Cachetaggable $this
     * @param boolean $deep
     * @return array
     */
    public function getTags ($deep = false)
    {
      if (! $this->getTable()->hasTemplate('Cachetaggable'))
      {
        throw new LogicException(sprintf(
          'Model "%s" has no "Cachetaggable" templates',
          $this->getTable()->getClassnameToReturn()
        ));
      }

      $formatedClassName = sfCacheTaggingToolkit::getBaseClassName(
        $this->getTable()->getClassnameToReturn()
      );

      $tagger = $this->getViewCacheManger()->getTaggingCache();

      if ($this->count())
      {
        $freshestVersion = 0;

        foreach ($this as $object)
        {
          $objectVersion = $object->getObjectVersion();

          $freshestVersion = $freshestVersion < $objectVersion
            ? $objectVersion
            : $freshestVersion;

          $this
            ->getContentTagHandler()
            ->addContentTags($object->getTags($deep), $this->getNamespace());
        }

        $lastSavedVersion = $tagger->getTag($formatedClassName);

        if ($lastSavedVersion && ($lastSavedVersion < $freshestVersion))
        {
          $this->getContentTagHandler()->setContentTag(
            $formatedClassName, $lastSavedVersion, $this->getNamespace()
          );
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
        $this->getContentTagHandler()->setContentTag(
          $formatedClassName,
          sfCacheTaggingToolkit::generateVersion(strtotime('today')),
          $this->getNamespace()
        );
      }

      $tags = $this->getContentTagHandler()->getContentTags($this->getNamespace());

      $this->getContentTagHandler()->removeContentTags($this->getNamespace());

      return $tags;
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

