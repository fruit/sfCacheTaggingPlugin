<?php

/*
 * This file is part of the sfCacheTaggingPlugin package.
 * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Adds functionality to fetch collection tags, also it stores other associated collection tags
 * in the Doctrine_Collection_Cachetaggable instance
 *
 * @package sfCacheTaggingPlugin
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
   * Collects collection tags keys with its versions
   *
   * @param Doctrine_Collection_Cachetaggable $collection
   * @return array
   */
  protected function fetchTags (self $collection = null)
  {
    $tags = array();

    $collection = is_null($collection) ? $this : $collection;

    $latestFoundVersion = 0;

    if ($collection->count())
    {
      foreach ($collection as $object)
      {
        $tags[$object->getTagName()] = $object->getObjectVersion();

        $latestFoundVersion = $latestFoundVersion < $object->getObjectVersion()
          ? $object->getObjectVersion()
          : $latestFoundVersion;
      }

      if (! is_null($first = $collection->getFirst()))
      {
        $tagger = sfContext::getInstance()->getViewCacheManager()->getTagger();

        $lastSavedVersion = $tagger->getTag(get_class($first));

        $tags[get_class($first)] = is_null($lastSavedVersion)
          ? $latestFoundVersion
          : $lastSavedVersion;
      }
    }
    else
    {
      /**
       * little hack, if collection is empty, emulate collection, without any tags
       * but version should be staticaly fixed (in day range)
       *
       * repeating calls with relative microtime always refresh collection tag
       * so, here is fixed value
       */
      $tags[$collection->getTable()->getClassnameToReturn()]
        = sfCacheTaggingToolkit::generateVersion(strtotime('today'));
    }

    return $tags;
  }

  /**
   * Returns this collection and added tags
   *
   * @return array
   */
  public function getTags ()
  {
    return array_merge($this->tags, $this->fetchTags());
  }

  /**
   * Adds additional tags to currect collection.
   * Acceptable array or Doctrine_Collection_Cachetaggable instance
   *
   * @todo add Doctrine_Record as acceptable $tags type
   * @todo implement sepparate addTags in plugin toolbox (DRY)
   *
   * @param array|Doctrine_Collection_Cachetaggable|ArrayAccess $tags
   */
  public function addTags ($tags)
  {
    $formatedTags = sfCacheTaggingToolkit::formatTags($tags);

    foreach ($formatedTags as $tagName => $tagVersion)
    {
      $this->addTag($tagName, $tagVersion);
    }
  }

  /**
   *
   * @param string $tagName
   * @param string|int $tagVersion
   */
  public function addTag ($tagName, $tagVersion)
  {
    sfCacheTaggingToolkit::addTag($this->tags, $tagName, $tagVersion);
  }

  /**
   * Remove all added tags
   *
   * @return void
   */
  public function removeTags ()
  {
    $this->tags = array();

    return;
  }
}

