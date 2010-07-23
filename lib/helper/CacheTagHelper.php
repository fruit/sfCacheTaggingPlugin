<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * CacheHelper
   *
   * @package    symfony
   * @subpackage helper
   * @author     Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * @example
   *
   * <code>
   *
   *  <?php if (! cache_tag('name', 600)): ?>
   *    … HTML …
   *    <?php cache_save(array('tag' => time()) ?>
   *  <?php endif; ?>
   *
   * </code>
   */

  /**
   * Starts caching process or fetch up-to-date content from the cache
   *
   * @param string  $name     Content cache name
   * @param int     $lifetime optional seconds to cache will live
   *
   * @return null|boolean     true:  cache expired/not yet saved,
   *                          false: cache is up-to-date
   *                          null:  cache is disabled
   */
  function cache_tag ($name, $lifetime = null)
  {
    if (! sfConfig::get('sf_cache'))
    {
      return null;
    }

    if (sfConfig::get('symfony.cache.started'))
    {
      throw new sfCacheException('Cache already started.');
    }

    $data = sfContext::getInstance()
      ->getViewCacheManager()
      ->startWithTags($name);

    if (null === $data)
    {
      sfConfig::set('symfony.cache.started', true);
      sfConfig::set('symfony.cache.current_name', $name);
      sfConfig::set('symfony.cache.lifetime', $lifetime);

      return false;
    }
    
    echo $data;

    return true;
  }

  /**
   * Returns cached content via string
   *
   * @param array $tags assoc array with content tags
   *    array(
   *      #array("key: tag name" => "value: tag version"),
   *      array("user_comment_votes" => "12983219319283213"),
   *      …
   *
   * @throws sfCacheException
   * @return void
   */
  function get_cache_tag_save (array $tags = null)
  {
    if (! sfConfig::get('sf_cache'))
    {
      return null;
    }

    if (! sfConfig::get('symfony.cache.started'))
    {
      throw new sfCacheException('Cache not started.');
    }

    $cacheManager = sfContext::getInstance()->getViewCacheManager();

    /* @var $cacheManager sfViewCacheTagManager */
    if (! $cacheManager instanceof sfViewCacheManager)
    {
      return null;
    }

    $contentTagHandler = $cacheManager->getTaggingCache()->getContentTagHandler();
    /* @var $contentTagHandler sfContentTagHandler */

    if (null !== $tags)
    {
      $contentTagHandler->setContentTags(
        $tags, sfViewCacheTagManager::NAMESPACE_PARTIAL
      );
    }
    
    $data = $cacheManager->stopWithTags(
      sfConfig::get('symfony.cache.current_name', ''),
      sfConfig::get('symfony.cache.lifetime', null)
    );

    sfConfig::set('symfony.cache.started', false);
    sfConfig::set('symfony.cache.current_name', null);
    sfConfig::set('symfony.cache.lifetime', null);

    $contentTagHandler->removeContentTags(
      sfViewCacheTagManager::NAMESPACE_PARTIAL
    );

    return $data;
  }

  /**
   * Prints the cache content to the output
   *
   * @see get_cache_tag_save()
   * @param array $tags
   * @return void
   */
  function cache_tag_save (array $tags = null)
  {
    echo get_cache_tag_save($tags);
  }
