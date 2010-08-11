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
  class sfAPCTaggingCache extends sfAPCCache
  {
    public function getCacheKeys ()
    {
      $infos = apc_cache_info('user');

      if (! is_array($infos['cache_list']))
      {
        return;
      }

      $keys = array();

      foreach ($infos['cache_list'] as $info)
      {
        $keys[] = substr($info['info'], strlen($this->getOption('prefix')));
      }

      return $keys;
    }
  }