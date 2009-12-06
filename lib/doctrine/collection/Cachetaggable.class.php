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
  private $tags = array();

  /**
   * Collects collection tags keys with its versions
   *
   * @param Doctrine_Collection_Cachetaggable $collection
   * @return array
   */
  public function fetchTags (self $collection = null)
  {
    $tags = array();

    $collection = is_null($collection) ? $this : $collection;

    foreach ($collection as $object)
    {
      $tags[$object->getTagName()] = $object->getObjectVersion();
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
   * @param array|Doctrine_Collection_Cachetaggable $tags
   */
  public function addTags ($tags)
  {
    $this->tags = array_merge(
      $this->tags,
      $tags instanceof self ? $this->fetchTags($tags) : $tags
    );
  }
}

