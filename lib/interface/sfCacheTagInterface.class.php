<?php

/**
 * Description of sfCacheTagInterface
 *
 * @author fruit
 */
interface sfCacheTagInterface
{
  const
    TAGS_TEMPLATE = '[tags]-%s',
    TAG_TEMPLATE  = '[tag]-%s';

  public function set($key, $data, $lifetime = null, $tags = null);

  public function setTag ($key, $value, $lifetime = null);

  public function setTags($key, $tags, $lifetime = null);

  public function getTags($key);

  public function getTag($key);

  public function deleteTag ($key);
}

