<?php

/**
 * Description of Doctrine_Collection_Cachetaggable
 *
 * @author fruit
 */
class Doctrine_Collection_Cachetaggable extends Doctrine_Collection
{
  private $tags = array();

  public function fetchTags (self $collection = null)
  {
    $tags = array();

    $collection = is_null($collection) ? $this : $collection;

    if (0 < $collection->count())
    {
      foreach ($collection as $object)
      {
        $tags[$object->getTagName()] = $object->getObjectVersion();
      }
    }
    
    return $tags;
  }

  public function getTags ()
  {
    return array_merge($this->tags, $this->fetchTags());
  }

  /**
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

