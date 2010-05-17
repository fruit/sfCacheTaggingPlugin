<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * Toolkit with frequently used methods.
   *
   *
   * @package sfCacheTaggingPlugin
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */

  class sfCacheTaggingToolkit
  {
    const NAMESPACE_CACHE_TAGS = 'symfony.cache.tags';

    /**
     * Build version base on currenct microtime
     *
     * @param double|int $microtime
     * @return string Number list the represents a current timestamp
     */
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
      $presision = (int) sfConfig::get('app_sfcachetaggingplugin_microtime_precision', 5);

      if (0 > $presision or 6 < $presision)
      {
        throw new OutOfRangeException(sprintf(
          'Value of "app_sfcachetaggingplugin_microtime_precision" is out of the range (0…6)'
        ));
      }

      return $presision;
    }

    /**
     * Returns app.yml tag lifetime, otherwise, return default value (86400 - 1 day)
     *
     * @return int
     */
    public static function getTagLifetime ()
    {
      return self::validateLifetime('app_sfcachetaggingplugin_tag_lifetime', 86400);
    }

    /**
     * Returns app.yml lock lifetime, otherwise, return default value (2)
     *
     * @return int
     */
    public static function getLockLifetime ()
    {
      return self::validateLifetime('app_sfcachetaggingplugin_lock_lifetime', 2);
    }

    /**
     * Checks tag/lock defined values
     *
     * @param string $configRoute
     * @throws OutOfRangeException
     * @return int
     */
    protected static function validateLifetime($configRoute, $defaultValue)
    {
      $lifetime = (int) sfConfig::get($configRoute, $defaultValue);

      if (0 >= $lifetime)
      {
        throw new OutOfRangeException(
          sprintf(
            'Value of "%s" (%s) is less or equal to zero',
            $configRoute,
            $lifetime
          )
        );
      }

      return (int) $lifetime;
    }

    /**
     * Format passed tags to the array
     *
     * @param array|Doctrine_Record|Doctrine_Collection_Cachetaggable|ArrayAccess $tags
     * @throws InvalidArgumentException
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
     * @throws InvalidArgumentException
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