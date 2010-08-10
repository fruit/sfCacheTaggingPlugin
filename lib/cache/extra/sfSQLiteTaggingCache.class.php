<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
  */

  /**
   * Cache class that stores content sqlite database
   * This class differs from parent with set() and get() methods
   * Added opportunity to store objects via serialization/unserialization
   *
   * @package sfCacheTaggingPlugin
   * @subpackage cache
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class sfSQLiteTaggingCache extends sfSQLiteCache
    implements sfTaggingCacheInterface
  {
    /**
     * @see sfSQLiteCache::get()
     */
    public function get ($key, $default = null)
    {
      $data = parent::get($key, $default);

      return null === $data ? $default : unserialize($data);
    }

    /**
     * @see sfSQLiteCache::set()
     */
    public function set ($key, $data, $lifetime = null)
    {
      return parent::set($key, serialize($data), $lifetime);
    }

    /**
     * @return array
     */
    public function getCacheKeys ()
    {
      return $this->dbh->arrayQuery('SELECT key FROM cache WHERE 1', SQLITE_ASSOC);
    }
  }