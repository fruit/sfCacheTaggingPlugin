<?php

class sfFileTaggingCache extends sfFileCache
{
  /**
   * @see sfCache
   */
  public function get($key, $default = null)
  {
    $data = parent::get($key, $default);

    return null === $data ? $default : unserialize($data);
  }

  /**
   * @see sfCache
   */
  public function set($key, $data, $lifetime = null)
  {
    return parent::set($key, serialize($data), $lifetime);
  }
}