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

  public function set($key, $data, $lifetime = null, array $tags = array());

  public function setTag ($key, $value, $lifetime);

  public function setTags($key, $tags, $lifetime);

  public function getTags($key);

  public function getTag($key);

  public function deleteTag ($key);
}

