<?php

/*
 * This file is part of the sfCacheTaggingPlugin package.
 * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * This code adds opportunity to use cache tagging, there are extra methods to
 * work with cache tags and locks
 *
 * @package sfCacheTaggingPlugin
 * @author Ilya Sabelnikov <fruit.dev@gmail.com>
 */
class sfTagCache extends sfCache
{
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
   * Initialization process based on parent but without calling parent method
   *
   * @see sfCache::initialize
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
   * Returns cache class for data caching
   *
   * @return sfCache
   */
  public function getCache ()
  {
    return $this->cache;
  }
  
  /**
   * Returns cache class for locks
   *
   * @return sfCache
   */
  public function getLocker ()
  {
    return $this->locker;
  }

  /**
   * @see sfCache::has
   * @param string $key
   * @return boolean
   */
  public function has ($key)
  {
    return $this->getCache()->has($key);
  }

  /**
   * Removes cache from backend by key
   *
   * @see sfCache::remove
   * @param string $key
   * @return boolean
   */
  public function remove ($key)
  {
    $value = $this->getCache()->get($key);

    if (! is_null($value))
    {
      if (($value instanceof stdClass) and isset($value->tags) and is_array($value->tags))
      {
        foreach ($value->tags as $tagKey => $tagOldVersion)
        {
          $this->deleteTag($tagKey);
        }
      }
    }

    $result = $this->getCache()->remove($key);

    $this->writeChar($result ? 'D' : 'd', $key);

    return $result;
  }

  /**
   * @see sfCache::removePattern
   * @param string $pattern
   * @return boolean
   */
  public function removePattern ($pattern)
  {
    return $this->getCache()->removePattern($pattern);
  }

  /**
   * @see sfCache::getTimeout
   * @param string $key
   * @return int
   */
  public function getTimeout ($key)
  {
    return $this->getCache()->getTimeout($key);
  }

  /**
   * @see sfCache::getLastModified
   * @param string $key
   * @return int
   */
  public function getLastModified($key)
  {
    return $this->getCache()->getLastModified($key);
  }

  /**
   * Sets data into the cache with related tags
   *
   * @see sfCache::set
   * @param string $key
   * @param mixed $data
   * @param string $lifetime optional
   * @param array $tags optional
   * @return mixed|false Cache expired/not valid - returns false, in other case mixed
   */
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

      $this->writeChar($result ? 'S' : 's', $key);

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
      $this->writeChar('s', $key);

      $result = false;
    }

    return $result;
  }

  /**
   * Saves tag with its version
   *
   * @param string $key tag key
   * @param string $value tag version
   * @param int $lifetime optional tag time to live
   * @return boolean
   */
  public function setTag ($key, $value, $lifetime = null)
  {
    $tagKey = $this->generateTagKey($key);
    
    $result = $this->getCache()->set($tagKey, $value, $lifetime);

    return $result;
  }

  /**
   * Returns version of the tag by key
   *
   * @param string $key
   * @return string version of the tag
   */
  public function getTag ($key)
  {
    $result = $this
      ->getCache()
      ->get($this->generateTagKey($key));

    return $result;
  }

  /**
   * Removes tag version (basicly called on physical object removing)
   *
   * @param string $key
   * @return boolean
   */
  public function deleteTag ($key)
  {
    $result = $this
      ->getCache()
      ->remove($this->generateTagKey($key));

    return $result;
  }

  /**
   * Pulls data out of cache.
   * Also, it checks all related tags for expiration/version-up.
   *
   * @see sfCache::get
   * @param string $key
   * @param mixed $default returned back if result is false
   * @return mixed|$default
   */
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

    $this->writeChar($value != $default ? 'G' : 'g', $key);

    return $value;
  }

  /**
   * Defines log file
   *
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

  /**
   * Closes file log resource
   * @return void
   */
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

  /**
   * Writes $char to log file
   *
   * @param string $char
   * @return void
   */
  private function writeChar ($char, $key)
  {
    if (is_resource($this->fileResource))
    {
      fwrite($this->fileResource, sprintf("%s: %-40s | %s\n",  $char, $key, microtime()));

//      fwrite($this->fileResource, $char);
    }
  }

  /**
   * Set lock on $key on $expire seconds
   *
   * @param string $key
   * @param int $expire expire time in seconds
   * @return boolean locked - true, could not lock - false
   */
  public function lock ($key, $expire = 2)
  {
    if ($this->isLocked($key))
    {
      return false;
    }

    $lockKey = $this->generateLockKey($key);

    $result = $this->getLocker()->set($lockKey, 1, $expire);

    $this->writeChar(true === $result ? 'L' : 'l', $lockKey);

    return $result;
  }

  /**
   * Check for $key is locked/not locked
   *
   * @param string $key
   * @return boolean
   */
  public function isLocked ($key)
  {
    $value = $this
      ->getLocker()
      ->get($this->generateLockKey($key));

    return (bool) $value;
  }

  /**
   * Call this to unlock key
   *
   * @param string $key
   * @return boolean
   */
  public function unlock ($key)
  {
    $lockKey = $this->generateLockKey($key);

    $result = $this->getLocker()->remove($lockKey);

    $this->writeChar(true === $result ? 'U' : 'u', $lockKey);

    return $result;
  }

  /**
   * @see sfCache::clean
   * @param int $mode One of sfCache::ALL, sfCache::OLD params
   * @return void
   */
  public function clean ($mode = sfCache::ALL)
  {
    if ($this->getCache() !== $this->getLocker())
    {
      $this->getCache()->clean($mode);
    }

    $this->getLocker()->clean($mode);
  }

  /**
   * Creates name for lock key
   *
   * @param string $key
   * @return string
   */
  private function generateLockKey ($key)
  {
    return sprintf(
      sfConfig::get(
        'app_sfcachetaggingplugin_template_lock',
        '[lock]-%s'
      ),
      $key
    );
  }

  /**
   * Creates name for tag key
   *
   * @param <type> $key
   * @return <type>
   */
  private function generateTagKey ($key)
  {
    return sprintf(
      sfConfig::get(
        'app_sfcachetaggingplugin_template_tag',
        '[tag]-%s'
      ),
      $key
    );
  }
}