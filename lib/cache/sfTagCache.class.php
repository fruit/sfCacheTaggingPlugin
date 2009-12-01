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

  /**
   * Log file pointer
   * 
   * @var resource
   */
  protected $fileResource = null;

  /**
   * Temporary method
   */
  public function __construct($options)
  {
    $AZ = range('A', 'Z');
    $this->id = $AZ[rand(0, count($AZ) - 1)];

    $this->initialize($options);
  }

  /**
   * @throws sfInitializationException
   * @param array $options
   */
  public function initialize ($options = array())
  {
    $cacheClassName = $options['cache']['class'];

    if (! class_exists($cacheClassName, true))
    {
      throw new sfInitializationException(
        sprintf('Tagging cache class "%s" not found', $cacheClassName)
      );
    }

    # check is valid class
    $this->cache = new $cacheClassName($options['cache']['param']);
    
    if (! isset($options['locker']) or ! is_array($options['cache']))
    {
      $this->locker = $this->cache;
    }
    else
    {
      $lockerClassName = $options['locker']['class'];

      if (! class_exists($lockerClassName, true))
      {
        throw new sfInitializationException(
          sprintf('Tagging locker class "%s" not found', $lockerClassName)
        );
      }

      # check is valid class
      $this->locker = new $lockerClassName($options['locker']['param']);
    }

    if ($options['logging'])
    {
      $this->setStatsFilename(
        sfConfig::get('sf_log_dir') . DIRECTORY_SEPARATOR .
        sprintf('cache_%s.log', sfConfig::get('sf_environment'))
      );
    }
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

  public function has($key)
  {
    return $this->getCache()->has($key);
  }

  public function remove($key)
  {
    $result = $this->getCache()->remove($key);

    $this->writeChar($result ? 'D.' : 'd.');

    return $result;
  }

  public function removePattern($pattern)
  {
    return $this->getCache()->removePattern($pattern);
  }

  public function getTimeout($key)
  {
    return $this->getCache()->getTimeout($key);
  }

  public function getLastModified($key)
  {
    return $this->getCache()->getLastModified($key);
  }

  public function set ($key, $data, $lifetime = null, $tags = null)
  {
    $lifetime = null === $lifetime 
      ? $this->getCache()->getOption('lifetime')
      : $lifetime;

    $extendedData = new stdClass();
    $extendedData->data = $data;

    if (! is_null($tags))
    {
      $extendedData->tags = (array) $tags;
    }

    if ($this->lock($key))
    {
      $result = $this
        ->getCache()
        ->set($key, $extendedData, $lifetime);

      $this->writeChar($result ? 'S' : 's');

      $this->unlock($key);

      if (isset($extendedData->tags))
      {
        foreach ($extendedData->tags as $tagKey => $value)
        {
          $this->setTag($tagKey, $value);
        }
      }
    }
    else
    {
      $this->writeChar('s');

      $result = false;
    }

    return $result;
  }

  public function setTag ($key, $value, $lifetime = null)
  {
    $tagKey = sprintf(self::TEMPLATE_TAG, $key);
    
    $result = $this->getCache()->set($tagKey, $value, $lifetime);

    $this->writeChar($result ? 'ST' : 'st');

    return $result;
  }

  public function getTag ($key)
  {
    $result = $this
      ->getCache()
      ->get(sprintf(self::TEMPLATE_TAG, $key));

    $this->writeChar($result ? 'GT' : 'gt');

    return $result;
  }

  public function deleteTag ($key)
  {
    $result = $this
      ->getCache()
      ->remove(sprintf(self::TEMPLATE_TAG, $key));

    $this->writeChar($result ? 'DT' : 'dt');

    return $result;
  }

  public function get ($key, $default = null)
  {
    # reading data
    $value = $this->getCache()->get($key, $default);

    # not expired
    if (false !== $value)
    {
      if (($value instanceof stdClass) and isset($value->tags, $value->data))
      {
        $hasExpired = false;

        foreach ($value->tags as $tagKey => $tagOldVersion)
        {
          # reding tag version
          $tagNewVersion = $this->getTag($tagKey);

          # tag is exprired or version is old
          if (! $tagNewVersion or $tagOldVersion < $tagNewVersion)
          {
            $hasExpired = true;
            break;
          }
        }

        if ($hasExpired)
        {
          if ($this->isLocked($key))
          {
            # return old cache coz new data is writing to the current cache
            $value = $value->data;
          }
          else
          {
            $value = $default;
          }
        }
        else
        {
          $value = $value->data;
        }
      }
    }
    else
    {
      $value = $default;
    }

    $this->writeChar($value != $default ? 'G' : 'g');

    return $value;
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
    $this->tryToCloseStatsFileResource();
  }

  private function writeChar ($char)
  {
    if (is_resource($this->fileResource))
    {
      fwrite($this->fileResource, "{$char},");
    }
  }

  public function lock ($key, $expire = 10)
  {
    if ($this->isLocked($key))
    {
      return false;
    }

    $lockKey = sprintf(self::TEMPLATE_LOCK, $key);

    $result = $this->getLocker()->set($lockKey, 1, $expire);

    $this->writeChar(true === $result ? 'L' : 'l');

    return $result;
  }

  public function isLocked ($key)
  {
    $value = $this
      ->getLocker()
      ->get(sprintf(self::TEMPLATE_LOCK, $key));

    return (bool) $value;
  }

  public function unlock ($key)
  {
    $lockKey = sprintf(self::TEMPLATE_LOCK, $key);

    $result = $this->getLocker()->remove($lockKey);

    $this->writeChar(true === $result ? 'U' : 'u');

    return $result;
  }

  public function clean ($mode = sfCache::ALL)
  {
    if ($this->getCache() !== $this->getLocker())
    {
      $this->getCache()->clean($mode);
    }

    $this->getLocker()->clean($mode);
  }
}