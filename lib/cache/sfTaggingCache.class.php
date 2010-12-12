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
  class sfTaggingCache extends sfCache implements sfTaggingCacheInterface
  {
    /**
     * This cache stores your data any instanceof sfCache
     *
     * @var sfCache
     */
    protected $dataCache = null;

    /**
     * This cache stores tags
     *
     * @var sfCache
     */
    protected $tagsCache = null;

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
     * @var sfCacheTagLogger
     */
    protected $logger = null;

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
     * @param string  $name
     * @param mixed   $default
     * @return mixed
     */
    protected function getArrayValueByKeyPath ($keyPath, $array)
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

      return isset($array[$keyPath]) ? $array[$keyPath] : null;
    }

    /**
     * Returns option by key path
     *
     * @see PHPDOC of method self::getArrayValueByKeyPath
     * @param string  $name     Array key, or key path joined by "."
     * @param mixed   $default  optional on unsuccess return default value
     * @return mixed
     */
    public function getOption ($name, $default = null)
    {
      $option = $this->getArrayValueByKeyPath($name, $this->options);

      return null === $option ? $default : $option;
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

      $dataCacheClassName = $this->getOption('data.class');

      if (! $dataCacheClassName)
      {
        throw new sfInitializationException(sprintf(
          'You must pass a "data.class" option to initialize a %s object.',
          __CLASS__
        ));
      }

      if (! class_exists($dataCacheClassName, true))
      {
        throw new sfInitializationException(
          sprintf('Data cache class "%s" not found', $dataCacheClassName)
        );
      }

      # check is valid class
      $this->dataCache = new $dataCacheClassName(
        $this->getOption('data.param', array())
      );

      if (! $this->dataCache instanceof sfCache)
      {
        throw new sfInitializationException(
          'Data cache class is not instance of sfCache.'
        );
      }

      if (! $this->getOption('tags'))
      {
        $this->tagsCache = $this->dataCache;
      }
      else
      {
        $tagsClassName = $this->getOption('tags.class');

        if (! $tagsClassName)
        {
          throw new sfInitializationException(sprintf(
            'You must pass a "tags.class" option to initialize a %s object.',
            __CLASS__
          ));
        }

        if (! class_exists($tagsClassName, true))
        {
          throw new sfInitializationException(
            sprintf('tags cache class "%s" not found', $tagsClassName)
          );
        }

        # check is valid class
        $this->tagsCache = new $tagsClassName($options['tags']['param']);

        if (! $this->tagsCache instanceof sfCache)
        {
          throw new sfInitializationException(
            'tags cache class is not instance of sfCache'
          );
        }
      }

      if (! $this->getOption('logger.class'))
      {
        throw new sfInitializationException(sprintf(
          'You must pass a "logger.class" option to initialize a %s object.',
          __CLASS__
        ));
      }

      $loggerClassName = $this->getOption('logger.class');

      if (! class_exists($loggerClassName, true))
      {
        throw new sfInitializationException(
          sprintf('Logger cache class "%s" not found', $loggerClassName)
        );
      }

      $this->logger = new $loggerClassName(
        $this->getOption('logger.param', array())
      );

      if (! $this->logger instanceof sfCacheTagLogger)
      {
        throw new sfInitializationException(sprintf(
          'Logger class is not instance of sfCacheTagLogger, got "%s"',
          get_class($this->logger)
        ));
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
     * @return sfCacheTagLogger
     */
    protected function getLogger ()
    {
      return $this->logger;
    }

    /**
     * Returns cache class for tags
     *
     * @return sfCache
     */
    public function getTagsCache ()
    {
      return $this->tagsCache;
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
      $has = null !== $this->get($key);

      $this->getLogger()->log($has ? 'H' : 'h', $key);

      return $has;
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
      $cacheMetadata = $this->getDataCache()->get($key);

      $cacheMetadataClassName = sfCacheTaggingToolkit::getMetadataClassName();

      if ($cacheMetadata instanceof $cacheMetadataClassName)
      {
        $this->deleteTags($cacheMetadata->getTags());
      }

      $result = $this->getDataCache()->remove($key);

      $this->getLogger()->log($result ? 'D' : 'd', $key);

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
    
    public function getTTL ($key)
    {
      $timeout = $this->getTimeout($key);
      
      return 0 == $timeout ? $this->getLifetime(null) : ($timeout - time());
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
     * @param string  $key
     * @param array   $tags
     * @param boolean $append To combine new tags with existing use "true"
     *                        To replace existing tags with new one use "false"
     * @return boolean
     */
    public function addTagsToCache ($key, array $tags, $append = true)
    {
      $cacheMetadata = $this->getDataCache()->get($key);

      $cacheMetadataClassName = sfCacheTaggingToolkit::getMetadataClassName();

      if ($cacheMetadata instanceof $cacheMetadataClassName)
      {
        $append 
          ? $cacheMetadata->addTags($tags)
          : $cacheMetadata->setTags($tags);

        return $this->set(
          $key,
          $cacheMetadata->getData(),
          $this->getTTL($key),
          $cacheMetadata->getTags()
        );
      }

      return false;
    }

    /**
     * Sets data into the cache with related tags
     *
     * @see sfCache::set
     * @param string  $key
     * @param mixed   $data
     * @param string  $timeout optional
     * @param array   $tags    optional
     * @return mixed  false - when cache expired/not valid
     *                mixed - in other case
     */
    public function set ($key, $data, $timeout = null, array $tags = array())
    {
      $cacheMetadataClassName = sfCacheTaggingToolkit::getMetadataClassName();

      $cacheMetadata = new $cacheMetadataClassName($data, $tags);

      $result = false;

      if (! $this->isLocked($key))
      {
        $this->lock($key);

        $result = $this->getDataCache()->set($key, $cacheMetadata, $timeout);

        $this->getLogger()->log($result ? 'S' : 's', $key);

        $this->setTags($cacheMetadata->getTags());
        
        $this->unlock($key);
      }
      else
      {
        $this->getLogger()->log('s', $key);
      }
      

      return $result;
    }

    /**
     * Saves tag with its version
     *
     * @param string  $key      tag name
     * @param string  $tagVersion   tag version
     * @param int     $lifetime     optional tag time to live
     * @return boolean
     */
    public function setTag ($key, $tagVersion, $lifetime = null)
    {
      $result = $this->getTagsCache()->set($key, $tagVersion, $lifetime);

      $this->getLogger()->log($result ? 'P' :'p', sprintf('%s(%s)', $key, $tagVersion));

      return $result;
    }

    /**
     * Saves tags with its version
     *
     * @param array $tags
     * @param int   $lifetime optional
     */
    public function setTags (array $tags, $lifetime = null)
    {
      foreach ($tags as $tagName => $version)
      {
        $this->setTag($tagName, $version, $lifetime);
      }
    }

    /**
     * Returns version of the tag by key
     *
     * @param string $key
     * @return string version of the tag
     */
    public function getTag ($key)
    {
      $result = $this->getTagsCache()->get($key);

      $this->getLogger()->log(
        $result ? 'T' : 't',
        $key . ($result ? "({$result})" : '')
      );

      return $result;
    }

    /**
     * Checks tag key exists
     *
     * @param string $key
     * @return boolean
     */
    public function hasTag ($key)
    {
      $has = $this->getTagsCache()->has($key);

      $this->getLogger()->log($has ? 'I' : 'i', $key);

      return $has;
    }

    /**
     * Returns associated cache tags
     *
     * @param string $key
     * @return array
     */
    public function getTags ($key)
    {
      $value = $this->getDataCache()->get($key);

      $cacheMetadataClassName = sfCacheTaggingToolkit::getMetadataClassName();

      if ($value instanceof $cacheMetadataClassName)
      {
        return $value->getTags();
      }

      return array();
    }

    /**
     * Removes tag version (basicly called on physical object removing)
     *
     * @param string $key
     * @return boolean
     */
    public function deleteTag ($key)
    {
      $result = $this->getTagsCache()->remove($key);

      $this->getLogger()->log($result ? 'E' : 'e', $key);
      
      return $result;
    }

    /**
     * Deletes tags
     *
     * @param array $tags
     * @return void
     */
    public function deleteTags (array $tags)
    {
      foreach ($tags as $name => $version)
      {
        $this->deleteTag($name);
      }
    }

    /**
     * Pulls data out of cache.
     * Also, it checks all related tags for expiration/version-up.
     *
     * @see sfCache::get
     * @param string  $key
     * @param mixed   $default returned back if result is false
     * @return mixed
     */
    public function get ($key, $default = null)
    {
      $cacheMetadata = $this->getDataCache()->get($key, $default);

      $cacheMetadataClassName = sfCacheTaggingToolkit::getMetadataClassName();

      # check data exist in cache and data content is a tags container
      if ($cacheMetadata instanceof $cacheMetadataClassName)
      {
        $tags = $cacheMetadata->getTags();

        if (0 !== count($tags))
        {
          /**
           * speed up multi tag selection from backend
           */
          $tagKeys = array_keys($tags);
          
          $multiTags = $this->getTagsCache()->getMany($tagKeys);

          if (count($tags) > count($multiTags))
          {
            $this->getLogger()->log(
  //            'v', sprintf('%s(%s=>%s)', $tagKey, $tagVersion, $tagLatestVersion)
              'v', 'multi fetch'
            );

            # one tag is expired, no reasons to continue
            # (should revalidate cache data)
            $hasExpired = true;
          }
          else
          {
            $extendedKeysWithCurrentVersions = array_combine(array_keys($multiTags), array_values($tags));

            # check for data tags is expired
            $hasExpired = false;

            foreach ($multiTags as $tagKey => $tagLatestVersion)
            {
              $tagVersion = $extendedKeysWithCurrentVersions[$tagKey];
              # tag is exprired or version is old
              if (! $tagLatestVersion || $tagVersion < $tagLatestVersion)
              {
                $this->getLogger()->log(
                  'v', sprintf('%s(%s=>%s)', $tagKey, $tagVersion, $tagLatestVersion)
                );

                # one tag is expired, no reasons to continue
                # (should revalidate cache data)
                $hasExpired = true;

                break;
              }

              $this->getLogger()->log(
                'V', sprintf('%s(%s)', $tagKey, $tagLatestVersion)
              );
            }
          }

          // some cache tags is invalidated
          if ($hasExpired)
          {
            if ($this->isLocked($key))
            {
              # return old cache coz new data is writing to the current cache
              $cacheMetadata = $cacheMetadata->getData();
            }
            else
            {
              # cache no locked, but cache is expired
              $cacheMetadata = null;
            }
          }
          else
          {
            $cacheMetadata = $cacheMetadata->getData();
          }
        }
        else
        {
          $cacheMetadata = $cacheMetadata->getData();
        }
      }

      $this->getLogger()->log($cacheMetadata !== $default ? 'G' : 'g', $key);

      return $cacheMetadata;
    }

    /**
     * Set lock on $key on $expire seconds
     *
     * @param string  $lockName
     * @param int     $expire expire time in seconds
     * @return boolean true: was locked
     *                 false: could not lock
     */
    public function lock ($lockName, $expire = 2)
    {
      $key = $this->generateLockKey($lockName);

      $result = $this->getDataCache()->set($key, 1, $expire);

      $this->getLogger()->log($result ? 'L' : 'l', $key);

      return $result;
    }

    /**
     * Check for $lockName is locked/not locked
     *
     * @param string $lockName
     * @return boolean
     */
    public function isLocked ($lockName)
    {
      $key = $this->generateLockKey($lockName);

      $result = $this->getDataCache()->has($key);

      $this->getLogger()->log($result ? 'R' : 'r', $key);

      return $result;
    }

    /**
     * Call this to unlock key
     *
     * @param string $lockName
     * @return boolean
     */
    public function unlock ($lockName)
    {
      $key = $this->generateLockKey($lockName);

      $result = $this->getDataCache()->remove($key);

      $this->getLogger()->log($result ? 'U' : 'u', $lockName);

      return $result;
    }

    /**
     * @see sfCache::clean
     * @param int   $mode   One of sfCache::ALL, sfCache::OLD params
     * @return void
     */
    public function clean ($mode = sfCache::ALL)
    {
      if ($this->getDataCache() !== $this->getTagsCache())
      {
        $this->getTagsCache()->clean($mode);
      }

      $this->getDataCache()->clean($mode);
    }

    /**
     * Creates name for lock key
     *
     * @param string $key
     * @return string
     */
    protected function generateLockKey ($key)
    {
      return "{$key}_lock";
    }

    /**
     * Creates name for tag key
     *
     * @param string $key
     * @return string
     */
    protected function generateTagKey ($key)
    {
      return $key;
//      return sprintf(
//        sfConfig::get(
//          'app_sfcachetaggingplugin_template_tag',
//          sprintf('%s:tag:%%s', sfConfig::get('sf_environment'))
//        ),
//        $key
//      );
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

    /**
     * @return array registered keys in storage
     */
    public function getCacheKeys ()
    {
      return $this->getDataCache()->getCacheKeys();
    }
  }
