<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
  */

  /**
   * @package sfCacheTaggingPlugin
   * @subpackage cache
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class sfMemcacheTaggingCache extends sfMemcacheCache
    implements sfTaggingCacheInterface
  {
    public function getCacheKeys ()
    {
      $keys = array();
      
      foreach ($this->getCacheInfo() as $key)
      {
        $keys[] = substr($key, strlen($this->getOption('prefix')));
      }

      return $keys;
    }

    /**
     * @see sfCache
     * @return array
     */
    public function getMany ($keys)
    {
      foreach ($keys as $i => $key)
      {
        $key[$i] = $this->getOption('prefix') . $key;
      }

      return $this->memcache->get($keys);
    }
  }