<?php

class sfMemcacheTagCache extends sfMemcacheCache implements sfCacheTagInterface
{
  public function initialize($options = array())
  {
    if (! class_exists('Memcache'))
    {
      throw new sfInitializationException(
        'You must have memcache installed and enabled to use ' .
        __CLASS__ . ' class.'
      );
    }

    $cache = new MemcacheLock();
    $options['memcache'] = $cache;

    parent::initialize($options);

    if ($this->getOption('servers'))
    {
      foreach ($this->getOption('servers') as $server)
      {
        $port = isset($server['port']) ? $server['port'] : 11211;
        $persistent = isset($server['persistent'])
          ? $server['persistent']
          : true;

        if (! $cache->addServer($server['host'], $port, $persistent))
        {
          throw new sfInitializationException(sprintf(
            'Unable to connect to the memcache server (%s:%s).',
            $server['host'],
            $port
          ));
        }
      }
    }
    else
    {
      $method = $this->getOption('persistent', true) ? 'pconnect' : 'connect';
      if (! $cache->$method(
        $this->getOption('host', 'localhost'),
        $this->getOption('port', 11211),
        $this->getOption('timeout', 1))
      )
      {
        throw new sfInitializationException(sprintf(
          'Unable to connect to the memcache server (%s:%s).',
          $this->getOption('host', 'localhost'),
          $this->getOption('port', 11211)
        ));
      }
    }


    $basename = $this->getOption(
      'log_file_basename',
      sprintf('cache_%s.log', sfConfig::get('sf_environment'))
    );
    
    $cache->setStatsFilename(sfConfig::get('sf_log_dir') . DIRECTORY_SEPARATOR . $basename);
  }

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

    return $this->getBackend()->set($key, $data, false, time() + $lifetime);
  }

  public function setTag ($key, $value, $lifetime = null)
  {
    $tagKey = sprintf(sfCacheTagInterface::TAG_TEMPLATE, $key);
    $this->getBackend()->set($tagKey, $value, false, $lifetime);
  }

  public function setTags($key, $tags, $lifetime = null)
  {
    foreach ($tags as $tag => $value)
    {
      $this->setTag($tag, $value, $lifetime);
    }

    $tagKey = sprintf(sfCacheTagInterface::TAGS_TEMPLATE, $key);

    $this->getBackend()->set($tagKey, $tags, false, $lifetime);
  }

  public function getTags($key)
  {
    return $this
      ->getBackend()
      ->get(sprintf(sfCacheTagInterface::TAGS_TEMPLATE, $key));
  }

  public function getTag($key)
  {
    return $this
      ->getBackend()
      ->get(sprintf(sfCacheTagInterface::TAG_TEMPLATE, $key));
  }

  public function deleteTag ($key)
  {
    return $this
      ->getBackend()
      ->delete(sprintf(sfCacheTagInterface::TAG_TEMPLATE, $key));
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