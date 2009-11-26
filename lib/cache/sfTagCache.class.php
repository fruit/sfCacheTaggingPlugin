<?php

class sfTagCache extends sfCache
{
  const TEMPLATE_LOCK = '[lock]-%s';
  const TEMPLATE_TAG  = '[tag]-%s';

  /**
   * This cache stores your data any instanceof sfCache
   *
   * @var sfCache
   */
  protected $cache = null;

  /**
   * This cache stores locks
   *
   * @var sfCache
   */
  protected $locker = null;

  private $fileResource = null;

  /**
   * Temporary method
   */
  public function __construct($options)
  {
    parent::__construct($options);

    $AZ = range('A', 'Z');
    $this->id = $AZ[rand(0, count($AZ) - 1)];
  }

  /**
   * @throws sfInitializationException
   * @param array $options
   */
  public function initialize ($options = array())
  {
    $cacheClassName = $options['cache']['class'];
    
    # check is valid class
    $this->cache = new $cacheClassName($options['cache']['param']);
    
    if (! isset($options['locker']) or ! is_array($options['cache']))
    {
      $this->locker = $this->cache;
    }
    else
    {
      $lockerClassName = $options['locker']['class'];
      # check is valid class
      $this->locker = new $lockerClassName($options['locker']['param']);
    }
    
    $this->setStatsFilename(
      sfConfig::get('sf_log_dir') . DIRECTORY_SEPARATOR .
      sprintf('cache_%s.log', sfConfig::get('sf_environment'))
    );
  }

  /**
   * @return sfCache
   */
  public function getCache ()
  {
    return $this->cache;
  }
  
  /**
   * @return sfCache
   */
  public function getLocker ()
  {
    return $this->locker;
  }

  /**
   * @return sfCache
   */
  public function getBackend ()
  {
    return $this->getCache()->getBackend();
  }

  public function has($key)
  {
    return $this->getBackend()->has($key);
  }

  public function remove($key)
  {
    $result = $this->getBackend()->remove($key);

    $this->writeChar($result ? 'D' : 'd', $key);

    return $result;
  }

  public function removePattern($pattern)
  {
    return $this->getBackend()->removePattern($pattern);
  }

  public function clean($mode = self::ALL)
  {
    return $this->getBackend()->clean($mode);
  }

  public function getTimeout($key)
  {
    return $this->getBackend()->getTimeout($key);
  }

  public function getLastModified($key)
  {
    return $this->getBackend()->getLastModified($key);
  }

  public function set ($key, $data, $lifetime = null, $tags = null)
  {
    $lifetime = null === $lifetime ? $this->getOption('lifetime') : $lifetime;

    $extendedData = ! is_null($tags)
      ? array('data' => $data, 'tags' => $tags)
      : $data;

    if ($this->lock($key))
    {
      # write
      $result = $this->getBackend()->set($key, $extendedData, false, time() + $lifetime);

      $this->unlock($key);

      if (isset($extendedData['tags']))
      {
        foreach ($extendedData['tags'] as $tagKey => $value)
        {
          $this->setTag($tagKey, $value);
        }
      }

      // save metadata
      $this->setMetadata($key, $lifetime);

      // save key for removePattern()
      if ($this->getOption('storeCacheInfo', false))
      {
        $this->setCacheInfo($key);
      }
    }
    else
    {
      $result = false;
    }

    $this->writeChar($result ? 'W' : 'w', $key);

    return $result;
  }

  public function setTag ($key, $value, $lifetime = null)
  {
    $tagKey = sprintf(sfCacheTagInterface::TEMPLATE_TAG, $key);
    $this->getBackend()->set($tagKey, $value, false, $lifetime);
  }

  public function getTag ($key)
  {
    return $this
      ->getBackend()
      ->get(sprintf(sfCacheTagInterface::TEMPLATE_TAG, $key));
  }

  public function deleteTag ($key)
  {
    return $this
      ->getBackend()
      ->delete(sprintf(sfCacheTagInterface::TEMPLATE_TAG, $key));
  }

  public function get ($key, $default = null)
  {
    # reading data
    $value = $this->getBackend()->get($key);

    # not expired
    if (false !== $value)
    {
      if (is_array($value) and
          array_key_exists('tags', $value) and
          array_key_exists('data', $value))
      {
        list($data, $tags) = array_values($value);

        $hasExpired = false;
        foreach ($tags as $tagKey => $tagOldVersion)
        {
          # reding tag version
          $tagNewVersion = $this->getTag($tagKey);

          # tag is exprired or version is old
          if (false === $tagNewVersion or $tagOldVersion < $tagNewVersion)
          {
            $hasExpired = true;
            break;
          }
        }

        if ($hasExpired)
        {
          if ($this->getBackend()->isLocked($key))
          {
            # return old cache coz new data is writing to the current cache
            $value = $data;
          }
          else
          {
            $value = $default;
          }
        }
        else
        {
          $value = $data;
        }
      }
    }
    else
    {
      $value = $default;
    }

    $this->writeChar($value != $default ? 'H' : 'h', $key);

    return $value;
  }

  protected function setMetadata($key, $lifetime)
  {
    $this->getBackend()->set(
      '[metadata]-' . $key,
      array('lastModified' => time(), 'timeout' => time() + $lifetime), false, $lifetime
    );
  }

  /**
   * @param string $statsFilename
   * @return MemcacheLock
   */
  public function setStatsFilename ($statsFilename)
  {
    $this->tryToCloseStatsFileResource();

    if (! file_exists($statsFilename))
    {
      if (0 === file_put_contents($statsFilename, ''))
      {
        chmod($statsFilename, 0666);
      }
      else
      {
        throw new sfInitializationException(sprintf(
          'Could not create file "%s"', $statsFilename
        ));
      }
    }

    if (! is_readable($statsFilename) or ! is_writable($statsFilename))
    {
      throw new sfInitializationException(sprintf(
        'File "%s" is not readable/writeable', $statsFilename
      ));
    }

    $this->fileResource = fopen($statsFilename, 'a+');

    if (! $this->fileResource)
    {
      throw new sfInitializationException(sprintf(
        'Could not fopen file "%s" with append (a+) flag',
        $statsFilename
      ));
    }

    return $this;
  }

  private function tryToCloseStatsFileResource ()
  {
    if (! is_null($this->fileResource))
    {
      fclose($this->fileResource);
    }
  }

  public function __destruct ()
  {
    $this->writeChar("\n");

    $this->tryToCloseStatsFileResource();
  }

  private function writeChar ($char, $key = null)
  {
    if (! is_null($key))
    {
      fwrite($this->fileResource, sprintf("[%s] %s: %-35s | %s\n", $this->id, $char, $key, microtime()));
    }
//    fwrite($this->fileResource, $char);
  }

  public function lock ($key, $expire = 10)
  {
    $result = $this->getLocker()->getBackend()->add(sprintf(self::TEMPLATE_LOCK, $key), 1, $expire);

    if (true === $result)
    {
      $this->writeChar('L', sprintf(self::TEMPLATE_LOCK, $key));
    }
    else
    {
      $this->writeChar('l', sprintf(self::TEMPLATE_LOCK, $key));
    }

    return $result;
  }

  public function isLocked ($key)
  {
    return $this->getLocker()->getBackend()->get(sprintf(self::TEMPLATE_LOCK, $key));
  }

  public function unlock ($key)
  {
    return $this->getLocker()->getBackend()->delete(sprintf(self::TEMPLATE_LOCK, $key));
  }
}