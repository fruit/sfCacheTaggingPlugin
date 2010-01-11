<?php

/*
 * This file is part of the sfCacheTaggingPlugin package.
 * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Toolkit with frequently used methods
 *
 * @package sfCacheTaggingPlugin
 * @author Ilya Sabelnikov <fruit.dev@gmail.com>
 */

class sfCacheTaggingToolkit
{
  public static function generateVersion ()
  {
    return sprintf("%0.0f", pow(10, self::getPrecision()) * microtime(true));
  }

  /**
   * Returns app.yml precision, otherwise, return default value (5)
   *
   * @return int
   */
  public static function getPrecision ()
  {
    return (int) sfConfig::get('app_sfcachetaggingplugin_microtime_precision', 5);
  }

  /**
   * Returns app.yml tag lifetime, otherwise, return default value (86400)
   *
   * @return int
   */
  public static function getTagLifetime ()
  {
    return (int) sfConfig::get('app_sfcachetaggingplugin_tag_lifetime', 86400);
  }
}
