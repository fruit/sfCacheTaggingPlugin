<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2013 Ilya Sabelnikov <fruit.dev@gmail.com>
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
    protected function getNamespace ($isTagHolded = false)
    {
      return sprintf('%s%s', $this->namespace, $isTagHolded ? '_holded' : '');
    }

    /**
     * @param Doctrine_Table|string $table
     * @param string $keyColumn
     * @see Doctrine_Collection::__construct()
     */
    public function __construct ($table, $keyColumn = null)
    {
      parent::__construct($table, $keyColumn);

      if (! sfConfig::get('sf_cache')) return;

      $this->namespace = sprintf(
        '%s/%s/%s',
        __CLASS__,
        $this->getTable()->getClassnameToReturn(),
        spl_object_hash($this)
      );
    }

    /**
     * @return sfContentTagHandler
     */
    protected function getContentTagHandler ()
    {
      return $this->getTaggingCache()->getContentTagHandler();
    }

    /**
     * @return sfTaggingCache
     */
    protected function getTaggingCache ()
    {
      return sfCacheTaggingToolkit::getTaggingCache();
    }

    /**
     * Returns this collection and added tags
     *
     * @param boolean $deep
     * @return array
     */
    public function getCacheTags ($deep = true, $namespace = null)
    {
      if (! sfConfig::get('sf_cache')) return array();

      $table = $this->getTable();

      if (! $table->hasTemplate(sfCacheTaggingToolkit::TEMPLATE_NAME))
      {
        throw new sfConfigurationException(sprintf(
          'Model "%s" has no "%s" templates',
          $table->getClassnameToReturn(),
          sfCacheTaggingToolkit::TEMPLATE_NAME
        ));
      }

      $taggingCache = $this->getTaggingCache();

      $tagHandler = new sfContentTagHandler();

      $currentInstanceTags = $taggingCache
        ->getContentTagHandler()
        ->getContentTags($this->getNamespace(true));

      $tagHandler->addContentTags($this->getCollectionTags(), $namespace);

      $namespace = null;

      foreach ($this as $object)
      {
        $tagHandler->addContentTags($object->getCacheTags($deep), $namespace);
      }

      $tagHandler->addContentTags($currentInstanceTags, $namespace);

      $tags = $tagHandler->getContentTags($namespace);

      $tagHandler->clear();
      unset($tagHandler);

      return $tags;
    }

    /**
     * Collection tag with its version
     *
     * @return array
     */
    public function getCollectionTags ()
    {
      if (! sfConfig::get('sf_cache')) return array();

      $name = sfCacheTaggingToolkit::obtainCollectionName($this->getTable());
      $version = sfCacheTaggingToolkit::obtainCollectionVersion($name);

      return array($name => $version);
    }

    /**
     * Adds additional tags to currect collection.
     * Acceptable array or Doctrine_Collection_Cachetaggable instance
     *
     * @param array|Doctrine_Collection_Cachetaggable|ArrayAccess $tags
     * @return boolean
     */
    public function addCacheTags ($tags)
    {
      if (! sfConfig::get('sf_cache')) return false;

      $this
        ->getContentTagHandler()
        ->addContentTags($tags, $this->getNamespace(true));

      return true;
    }

    /**
     * Adds tag with its version to existing tags
     *
     * @param string $tagName
     * @param string|int $tagVersion
     * @return boolean
     */
    public function addCacheTag ($tagName, $tagVersion)
    {
      if (! sfConfig::get('sf_cache')) return false;

      $this->getContentTagHandler()->setContentTag(
        $tagName, $tagVersion, $this->getNamespace(true)
      );

      return true;
    }

    /**
     * Remove all added tags
     *
     * @return boolean
     */
    public function removeCacheTags ()
    {
      if (! sfConfig::get('sf_cache')) return false;

      $this->getContentTagHandler()->removeContentTags(
        $this->getNamespace(true)
      );

      return true;
    }

    /**
     * @see Doctrine_Collection::delete()
     * @return Doctrine_Collection_Cachetaggable
     */
    public function delete (Doctrine_Connection $conn = null, $clearColl = true)
    {
      if (! sfConfig::get('sf_cache')) parent::delete($conn, $clearColl);

      $returnValue = parent::delete($conn, $clearColl);
      $this->removeCacheTags();

      return $returnValue;
    }

    public function free ($deep = false)
    {
      if (! sfConfig::get('sf_cache')) return parent::free($deep);

      $this->removeCacheTags();

      return parent::free($deep);
    }
  }

