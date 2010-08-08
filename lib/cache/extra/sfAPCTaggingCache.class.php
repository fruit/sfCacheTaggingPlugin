<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
  */

  /**
   * Impoved sfAPCCache (get() & has() now checks for cache is not expired)
   * Default methods do not check this.
   *
   * @package sfCacheTaggingPlugin
   * @subpackage cache
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class sfAPCTaggingCache extends sfAPCCache
  {
    /**
     * @see parent::get()
     * @param string  $key
     * @param mixed   $default
     *
     * @return mixed
     */
    public function get ($key, $default = null)
    {
      $value = apc_fetch($this->getOption('prefix') . $key);

      if (null === $value)
      {
        return $default;
      }

      $has = $this->has($key);

      $result = $has ? $value : $default;

      return $result;
    }

    /**
     * @see parent::get()
     * @param string $key
     *
     * @return boolean
     */
    public function has ($key)
    {
      if (function_exists('apc_exists')) # APC v3.1.4
      {
        $has = apc_exists($this->getOption('prefix') . $key);
      }
      else
      {
        $has = parent::has($key);
      }

      # has, but it could be expired (APC does not check it)
      if ($has && null !== ($info = $this->getCacheInfo($key)))
      {
        return ($info['creation_time'] + $info['ttl'] > time());
      }

      return $has;
    }
  }
