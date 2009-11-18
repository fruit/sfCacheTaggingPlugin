<?php

class sfMemcacheCacheTag extends sfMemcacheCache implements sfCacheTagInterface
{
  public function set($key, $data, $lifetime = null, array $tags = array())
  {
    $lifetime = null === $lifetime ? $this->getOption('lifetime') : $lifetime;

    // save metadata
    $this->setMetadata($key, $lifetime);

    # save tags
    if (0 < count($tags))
    {
      $this->setTags($key, $tags, $lifetime);
    }

    // save key for removePattern()
    if ($this->getOption('storeCacheInfo', false))
    {
      $this->setCacheInfo($key);
    }

    if (false !== $this->getBackend()->replace($key, $data, false, time() + $lifetime))
    {
      return true;
    }

    return $this->getBackend()->set($key, $data, false, time() + $lifetime);
  }

  public function setTag ($key, $value, $lifetime)
  {
    $key = sprintf(sfCacheTagInterface::TAG_TEMPLATE, $key);

    if (! $this->getBackend()->replace($key, $value, false, $lifetime))
    {
      $this->getBackend()->set($key, $value, false, $lifetime);
    }
  }

  public function setTags($key, $tags, $lifetime)
  {
    foreach ($tags as $tag => $value)
    {
      $this->setTag($tag, $value, $lifetime);
    }

    $key = sprintf(sfCacheTagInterface::TAGS_TEMPLATE, $key);

    if (! $this->getBackend()->replace($key, $tags, false, $lifetime))
    {
      $this->getBackend()->set($key, $tags, false, $lifetime);
    }
  }

  public function getTags($key)
  {
    return $this->getBackend()->get(sprintf(sfCacheTagInterface::TAGS_TEMPLATE, $key));
  }

  public function getTag($key)
  {
    return $this->getBackend()->get(sprintf(sfCacheTagInterface::TAG_TEMPLATE, $key));
  }

  public function deleteTag ($key)
  {
    return $this->getBackend()->delete(sprintf(sfCacheTagInterface::TAG_TEMPLATE, $key));
  }

  public function get($key, $default = null)
  {
    # reading data
    $value = $this->getBackend()->get($key);

    # not expired
    if (false !== $value)
    {
      # maybe key with tags? (tags are storing in another place)
      $tags = $this->getTags($key);

      if (is_array($tags))
      {
        foreach ($tags as $tagKey => $tagOldVersion)
        {
          # reding tag version
          $tagNewVersion = $this->getTag($tagKey);

          # tag is exprired or version is old
          if (false === $tagNewVersion or $tagOldVersion < $tagNewVersion)
          {
            return $default;
          }
        }
      }

      return $value;
    }
    else
    {
      return $default;
    }
  }
}