<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2013 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * Cache class that stores content in files.
   * This class differs from parent with set() and get() methods
   * Added opportunity to store objects via serialization/unserialization
   *
   * @package sfCacheTaggingPlugin
   * @subpackage cache
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class sfFileTaggingCache extends sfFileCache implements sfTaggingCacheInterface
  {
    /**
     * @see sfFileCache::get()
     */
    public function get ($key, $default = null)
    {
      clearstatcache();
      $data = parent::get($key, $default);
      return null === $data ? $default : unserialize($data);
    }

    /**
     * @see sfFileCache::set()
     */
    public function set ($key, $data, $lifetime = null)
    {
      clearstatcache();
      return parent::set($key, serialize($data), $lifetime);
    }

    /**
     * {@inheritdoc}
     */
    public function has ($key)
    {
      clearstatcache();
      return parent::has($key);
    }

    /**
     * @return array
     */
    public function getCacheKeys ()
    {
      $cacheDir = $this->getOption('cache_dir');

      $keys = array();

      clearstatcache();

      foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cacheDir)) as $path)
      {
        if (! is_file($path))
        {
          continue;
        }

        $key = str_replace($cacheDir . DIRECTORY_SEPARATOR, '', $path);
        $key = str_replace(DIRECTORY_SEPARATOR, self::SEPARATOR, $key);
        $key = substr($key, 0, - strlen(self::EXTENSION));
        $keys[] = $key;
      }

      return $keys;
    }
  }
