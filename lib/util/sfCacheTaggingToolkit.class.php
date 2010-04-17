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
  public static function generateVersion ($microtime = null)
  {
    $microtime = null === $microtime ? microtime(true) : $microtime;

    return sprintf("%0.0f", pow(10, self::getPrecision()) * $microtime);
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

  /**
   * Format passed tags to the array
   *
   * @param array|Doctrine_Record|Doctrine_Collection_Cachetaggable|ArrayAccess $tags
   * @return array
   */
  public static function formatTags ($tags)
  {
    if (is_array($tags))
    {
      $mergeWith = $tags;
    }
    elseif ($tags instanceof Doctrine_Collection_Cachetaggable)
    {
      $mergeWith = $tags->getTags();
    }
    elseif ($tags instanceof Doctrine_Record)
    {
      $mergeWith = $tags->getTags();
    }
    # Doctrine_Collection_Cachetaggable and Doctrine_Record are instances of ArrayAccess
    # this check should be after them
    elseif ($tags instanceof ArrayAccess)
    {
      $mergeWith = $tags;
    }
    else
    {
      throw new InvalidArgumentException(sprintf(
        'Invalid argument type "%s". Acceptable types are: "array|ArrayAccess|%s"',
        gettype($tags),
        __CLASS__
      ));
    }

    return $mergeWith;
  }

  /**
   * Adds new tag to the tags with simple tag name check
   *
   * @param array $tags
   * @param string $tagName
   * @param int|string $tagVersion
   */
  public static function addTag (array & $tags, $tagName, $tagVersion)
  {
    if (! is_string($tagName))
    {
      throw new InvalidArgumentException(sprintf(
        'Invalid $tagName argument type "%s". Acceptable type is: "string"',
        gettype($tagName)
      ));
    }

    $tags[$tagName] = (string) $tagVersion;
  }
}
