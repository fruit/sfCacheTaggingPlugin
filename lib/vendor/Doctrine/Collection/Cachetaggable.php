<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2011 Ilya Sabelnikov <fruit.dev@gmail.com>
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
    public function getTags ($deep = false)
    {
      $table = $this->getTable();

      if (! $table->hasTemplate(sfCacheTaggingToolkit::TEMPLATE_NAME))
      {
        throw new sfConfigurationException(sprintf(
          'Model "%s" has no "%s" templates',
          $table->getClassnameToReturn(),
          sfCacheTaggingToolkit::TEMPLATE_NAME
        ));
      }

      $namespace = $this->getNamespace();

      try
      {
        $taggingCache = $this->getTaggingCache();

        $tagHandler = $taggingCache->getContentTagHandler();
      }
      catch (sfCacheDisabledException $e)
      {
        $this->notifyApplicationLog($e);

        return array();
      }

      if ($this->count())
      {
        foreach ($this as $object)
        {
          $objectVersion = $object->obtainObjectVersion();

          $tags = $deep ? $object->getTags(true) : $object->getTags();

          $tagHandler->addContentTags($tags, $namespace);
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
          $table->getClassnameToReturn()
        );

        $tagHandler->setContentTag(
          $formatedClassName,
          sfCacheTaggingToolkit::generateVersion(strtotime('today')),
          $namespace
        );
      }

      $tagHandler->addContentTags(
        $tagHandler->getContentTags($this->getNamespace(true)),
        $namespace
      );

      $tags = $tagHandler->getContentTags($namespace);

      $tagHandler->removeContentTags($namespace);

      return $tags;
    }

    /**
     * Adds additional tags to currect collection.
     * Acceptable array or Doctrine_Collection_Cachetaggable instance
     *
     * @param array|Doctrine_Collection_Cachetaggable|ArrayAccess $tags
     * @return boolean
     */
    public function addTags ($tags)
    {
      try
      {
        $this
          ->getContentTagHandler()
          ->addContentTags($tags, $this->getNamespace(true));

        return true;
      }
      catch (sfCacheDisabledException $e)
      {
        $this->notifyApplicationLog($e);
      }

      return false;
    }

    /**
     * Adds tag with its version to existing tags
     *
     * @param string $tagName
     * @param string|int $tagVersion
     * @return boolean
     */
    public function addTag ($tagName, $tagVersion)
    {
      try
      {
        $this->getContentTagHandler()->setContentTag(
          $tagName, $tagVersion, $this->getNamespace(true)
        );

        return true;
      }
      catch (sfCacheDisabledException $e)
      {
        $this->notifyApplicationLog($e);
      }

      return false;
    }

    /**
     * Remove all added tags
     *
     * @return null
     */
    public function removeTags ()
    {
      try
      {
        $this->getContentTagHandler()->removeContentTags(
          $this->getNamespace(true)
        );

        return true;
      }
      catch (sfCacheDisabledException $e)
      {
        $this->notifyApplicationLog($e);
      }

      return false;
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

    protected function notifyApplicationLog (Exception $e)
    {
      sfCacheTaggingToolkit::notifyApplicationLog(
        $this, $e->getMessage(), sfLogger::NOTICE
      );
    }
  }

