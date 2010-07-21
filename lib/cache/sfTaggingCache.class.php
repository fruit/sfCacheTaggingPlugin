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
   * @subpackage cache
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class sfTaggingCache extends sfCache
  {
    /**
     * This cache stores your data any instanceof sfCache
     *
     * @var sfCache
     */
    protected $dataCache = null;

    /**
     * This cache stores locks
     *
     * @var sfCache
     */
    protected $lockerCache = null;

    /**
     * Log file pointer
     *
     * @var resource
     */
    protected $fileResource = null;


    /**
     * Tag content handler with namespace holder (tag setting/adding/removing)
     *
     * @var sfContentTagHandler
     */
    protected $contentTagHandler = null;

    /**
     * Extended verion of default getOption method
     * In case, options is array with sub arrays you could easy to get value
     * by joining array key names with "."
     *
     * @example
     *   # options array:
     *    array(
     *      'php' => array(
     *        'frameworks' => array(
     *          'ZF'  => 'Zend Framework',
     *          'Yii' => 'Yii',
     *          'sf'  => 'Symfony',
     *        ),
     *      ),
     *    );
     *
     * "Symfony" will be accessed by $keyPath "php.frameworks.sf"
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    protected function getArrayValueByKeyPath ($keyPath, $array, $default = null)
    {
      $dotPosition = strpos($keyPath, '.');

      if (0 < $dotPosition)
      {
        $firstKey = substr($keyPath, 0, $dotPosition);
        $lastKeys = substr($keyPath, $dotPosition + 1);

        if (isset($array[$firstKey]) && is_array($array[$firstKey]))
        {
          return $this->getArrayValueByKeyPath($lastKeys, $array[$firstKey]);
        }
      }

      return isset($array[$keyPath]) ? $array[$keyPath] : $default;
    }

    /**
     * Returns option by key path
     *
     * @see PHPDOC of method self::getArrayValueByKeyPath
     * @param string $name Array key, or key path joined by "."
     * @param mixed [optional] $default
     * @return mixed
     */
    public function getOption ($name, $default = null)
    {
      return $this->getArrayValueByKeyPath($name, $this->options, $default);
    }

    /**
     * Initialization process based on parent but without calling parent method
     *
     * @see sfCache::initialize
     * @throws sfInitializationException
     * @param array $options
     */
    public function initialize ($options = array())
    {
      parent::initialize((array) $options);

      $this->contentTagHandler = new sfContentTagHandler();

      $cacheClassName = $this->getOption('cache.class');

      if (! $cacheClassName)
      {
        throw new sfInitializationException(sprintf(
          'You must pass a "cache.class" option to initialize a %s object.',
          __CLASS__
        ));
      }

      if (! class_exists($cacheClassName, true))
      {
        throw new sfInitializationException(
          sprintf(
            'sfCacheTaggingPlugin: Tagging cache class "%s" not found',
            $cacheClassName
          )
        );
      }

      $params = $this->getOption('cache.param', array());
      # check is valid class
      $this->dataCache = new $cacheClassName($params);

      if (! $this->dataCache instanceof sfCache)
      {
        throw new sfInitializationException(
          'sfCacheTaggingPlugin: Data backend class is not instance of sfCache.'
        );
      }

      if (null === $this->getOption('locker'))
      {
        $this->lockerCache = $this->dataCache;
      }
      else
      {
        $lockerClassName = $this->getOption('locker.class');

        if (! $lockerClassName)
        {
          throw new sfInitializationException(sprintf(
            'You must pass a "locker.class" option to initialize a %s object.',
            __CLASS__
          ));
        }

        if (! class_exists($lockerClassName, true))
        {
          throw new sfInitializationException(
            sprintf(
              'sfCacheTaggingPlugin: Tagging locker class "%s" not found',
              $lockerClassName
            )
          );
        }

        # check is valid class
        $this->lockerCache = new $lockerClassName($options['locker']['param']);

        if (! $this->lockerCache instanceof sfCache)
        {
          throw new sfInitializationException(
          'sfCacheTaggingPlugin: Locker backend class is not instance ' .
            'of sfCache.'
          );
        }
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
    public function getDataCache ()
    {
      return $this->dataCache;
    }

    /**
     * Returns cache class for locks
     *
     * @return sfCache
     */
    public function getLockerCache ()
    {
      return $this->lockerCache;
    }

    /**
     * @since v1.4.0
     *    parent::has() replaced by $this->get()
     *    build-in has method does not check if cache
     *    is expired (by comparing contents cache tags version)
     *    works little longer and in the same time accurately
     *
     * @see sfCache::get
     * @param string $key
     * @return boolean
     */
    public function has ($key)
    {
      return null !== $this->get($key);
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
      $value = $this->getDataCache()->get($key);

      if (null !== $value)
      {
        if (
            ($value instanceof stdClass)
          &&
            isset($value->tags)
          &&
            is_array($value->tags)
        )
        {
          foreach ($value->tags as $tagKey => $tagOldVersion)
          {
            $this->deleteTag($tagKey);
          }
        }
      }

      $result = $this->getDataCache()->remove($key);

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
      return $this->getDataCache()->removePattern($pattern);
    }

    /**
     * @see sfCache::getTimeout
     * @param string $key
     * @return int
     */
    public function getTimeout ($key)
    {
      return $this->getDataCache()->getTimeout($key);
    }

    /**
     * @see sfCache::getLastModified
     * @param string $key
     * @return int
     */
    public function getLastModified ($key)
    {
      return $this->getDataCache()->getLastModified($key);
    }

    /**
     * Adds tags to existring data cache
     * Useful, when tags are generated after data is cached
     * (i.g. doctrine object cache)
     *
     * If appending tag already exists, we will compare version to save
     * tag with newest one
     *
     * @param string $key
     * @param array $tags
     * @param boolean $append To combine new tags with existing use "true"
     *                        To replace existing tags with new one use "false"
     * @return boolean
     */
    public function addTagsToCache ($key, array $tags, $append = true)
    {
      $value = $this->getDataCache()->get($key);

      if ($value && $value instanceof stdClass && isset($value->data))
      {
        if ($append)
        {
          foreach ($tags as $addedTagKey => $addedTagValue)
          {
            if (
                ! isset($value->tags[$addedTagKey])
              ||
                (
                  isset($value->tags[$addedTagKey])
                &&
                  $value->tags[$addedTagKey] < $addedTagValue
                )
            )
            {
              $value->tags[$addedTagKey] = $addedTagValue;
            }
          }
        }

        return $this->set($key, $value->data, $this->getTimeout($key), $value->tags);
      }

      return false;
    }

    /**
     * Sets data into the cache with related tags
     *
     * @see sfCache::set
     * @param string $key
     * @param mixed $data
     * @param string $timeout optional
     * @param array $tags optional
     * @return mixed|false false - when cache expired/not valid
     *                     mixed - in other case
     */
    public function set ($key, $data, $timeout = null, array $tags = null)
    {
      $timeout = null === $timeout
        ? $this->getDataCache()->getOption('lifetime')
        : $timeout;

      $extendedData = new stdClass();
      $extendedData->data = $data;
      $extendedData->tags = (array) $tags;

      $lockLifetime = sfCacheTaggingToolkit::getLockLifetime();

      if ($this->lock($key, $lockLifetime))
      {
        $result = $this
          ->getDataCache()
          ->set($key, $extendedData, $timeout);

        $this->writeChar($result ? 'S' : 's', $key);

        $this->unlock($key);

        if (isset($extendedData->tags) && is_array($extendedData->tags))
        {
          $this->setTags(
            $extendedData->tags,
            sfCacheTaggingToolkit::getTagLifetime()
          );
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
     * @param string $tagKey tag key
     * @param string $tagValue tag version
     * @param int $lifetime optional tag time to live
     * @return boolean
     */
    public function setTag ($tagKey, $tagValue, $lifetime = null)
    {
      $tagKey = $this->generateTagKey($tagKey);

      $lifetime = (null === $lifetime)
        ? sfCacheTaggingToolkit::getTagLifetime()
        : $lifetime;

      $result = $this->getDataCache()->set($tagKey, $tagValue, $lifetime);

      $this->writeChar($result ? 'P' :'p', $tagKey, $tagValue);

      return $result;
    }

    /**
     * Saves tags with its version
     *
     * @todo add PHPDOC
     * @param array $tags
     * @param int [optional] $lifetime
     */
    public function setTags ($tags, $lifetime = null)
    {
      foreach ($tags as $tagKey => $tagValue)
      {
        $this->setTag($tagKey, $tagValue, $lifetime);
      }
    }

    /**
     * Returns version of the tag by key
     *
     * @param string $tagKey
     * @return string version of the tag
     */
    public function getTag ($tagKey)
    {
      $result = $this->getDataCache()->get($this->generateTagKey($tagKey));

      $this->writeChar(
        $result ? 'G' :'g', $this->generateTagKey($tagKey), $result
      );

      return $result;
    }

    /**
     * Checks tag key exists
     *
     * @param string $tagKey
     * @return boolean
     */
    public function hasTag ($tagKey)
    {
      return $this->getDataCache()->has($this->generateTagKey($tagKey));
    }

    /**
     * Returns associated cache tags
     *
     * @param string $key
     * @return array|null
     */
    public function getTags ($key)
    {
      $value = $this->getDataCache()->get($key);

      if (
          null !== $value
        &&
          $value instanceof stdClass
        &&
          isset($value->tags)
        &&
          is_array($value->tags)
      )
      {
        return $value->tags;
      }

      return;
    }

    /**
     * Removes tag version (basicly called on physical object removing)
     *
     * @param string $tagKey
     * @return boolean
     */
    public function deleteTag ($tagKey)
    {
      $result = $this
        ->getDataCache()
        ->remove($this->generateTagKey($tagKey));

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
      $value = $this->getDataCache()->get($key, $default);

      # check data exist in cache and data content is a tags container
      if (
          $default !== $value
        &&
          $value instanceof stdClass
        &&
          isset($value->data, $value->tags)
      )
      {
        # check for data tags is expired
        $hasExpired = false;

        foreach ($value->tags as $tagKey => $tagOldVersion)
        {
          # reding tag version
          $tagNewVersion = $this->getTag($tagKey);

          # tag is exprired or version is old
          if (! $tagNewVersion || $tagOldVersion < $tagNewVersion)
          {
            $this->writeChar(
              't',
              $this->generateTagKey($tagKey),
              sprintf('%s => %s', $tagOldVersion, $tagNewVersion)
            );

            # one tag is expired, no reasons to continue
            # (should revalidate cache data)
            $hasExpired = true;

            break;
          }

          $this->writeChar('T', $this->generateTagKey($tagKey), $tagNewVersion);
        }

        # if was expired, check data is not locked by any other client
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

      $this->writeChar($value !== $default ? 'G' : 'g', $key);

      return $value;
    }

    /**
     * Defines log file
     *
     * @param string $statsFilename
     * @return MemcacheLock
     */
    protected function setStatsFilename ($statsFilename)
    {
      $this->tryToCloseStatsFileResource();

      if (! file_exists($statsFilename))
      {
        if (0 === file_put_contents($statsFilename, ''))
        {
          chmod($statsFilename, 0600);
        }
        else
        {
          throw new sfInitializationException(sprintf(
            'Could not create file "%s"', $statsFilename
          ));
        }
      }

      if (! is_readable($statsFilename) || ! is_writable($statsFilename))
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
      if (null !== $this->fileResource)
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
    private function writeChar ($char, $key, $info = null)
    {
      if (is_resource($this->fileResource))
      {
        if (sfConfig::get('app_sfcachetaggingplugin_log_format_extended', false))
        {
          if (null !== $info)
          {
            $logFormat = "%s:%s:%s\n";
          }
          else
          {
            $logFormat = "%s:%s\n";
          }
        }
        else
        {
          $logFormat = "%s";
        }

        fwrite($this->fileResource, sprintf($logFormat, $char, $key, $info));
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
      $lockKey = $this->generateLockKey($key);

      $lockerCache = $this->getLockerCache();

      if ($lockerCache instanceof sfMemcacheCache)
      {
        $memcache = $lockerCache->getBackend();

        $prefix = $lockerCache->getOption('prefix');

        $result = $memcache->add(sprintf('%s%s', $prefix, $lockKey), 1, $expire);
      }
      elseif ($this->isLocked($key))
      {
        $result = false;
      }
      else
      {
        $result = $lockerCache->set($lockKey, 1, $expire);
      }

      $this->writeChar(true === $result ? 'L' : 'l', $key);

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
      return (bool) $this->getLockerCache()->get($this->generateLockKey($key));
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

      $result = $this->getLockerCache()->remove($lockKey);

      $this->writeChar(true === $result ? 'U' : 'u', $key);

      return $result;
    }

    /**
     * @see sfCache::clean
     * @param int $mode One of sfCache::ALL, sfCache::OLD params
     * @return void
     */
    public function clean ($mode = sfCache::ALL)
    {
      if ($this->getDataCache() !== $this->getLockerCache())
      {
        $this->getDataCache()->clean($mode);
      }

      $this->getLockerCache()->clean($mode);
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
        sfConfig::get('app_sfcachetaggingplugin_template_lock', 'lock_%s'), $key
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
        sfConfig::get('app_sfcachetaggingplugin_template_tag', 'tag_%s'), $key
      );
    }

    /**
     * Retrieves handler to manage tags
     *
     * @return sfContentTagHandler
     */
    public function getContentTagHandler ()
    {
      return $this->contentTagHandler;
    }

  }
