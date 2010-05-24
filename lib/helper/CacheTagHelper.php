<?php

  /*
   * This file is part of the symfony package.
   * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
  */

  /**
   * CacheHelper.
   *
   * @package    symfony
   * @subpackage helper
   * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
   * @version    SVN: $Id: CacheHelper.php 23810 2009-11-12 11:07:44Z Kris.Wallsmith $
   */

  /* Usage

    <?php if (! cache_tag('name', 600)): ?>

      ... HTML ...

      <?php cache_save(array('tag' => time()) ?>
    <?php endif; ?>

  */

  /**
   * Starts caching process or fetch up-to-date content from the cache
   *
   * @param string $name Content cache name
   * @param integer[optional] $lifetime seconds to cache will live
   * @return boolean true - cache expired/not yet saved, false - cache is up-to-date
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

    $viewCacheManager = sfContext::getInstance()->getViewCacheManager();

    $data = $viewCacheManager->startWithTags($name);

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
   *
   * @param array $tags assoc array with content tags
   *    array(
   *      #array("key: tag name" => "value: tag version"),
   *      array("user_comment_votes" => "12983219319283213"),
   *      ...
   *
   * @throws sfCacheException
   * @return null|void null if cache is disable at all, otherwise void
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

    $viewCacheManager = sfContext::getInstance()->getViewCacheManager();

    if (null !== $tags)
    {
      $viewCacheManager->addTags($tags);
    }
    
    $data = $viewCacheManager->stopWithTags(
      sfConfig::get('symfony.cache.current_name', ''),
      sfConfig::get('symfony.cache.lifetime', null)
    );

    sfConfig::set('symfony.cache.started', false);
    sfConfig::set('symfony.cache.current_name', null);
    sfConfig::set('symfony.cache.lifetime', null);

    $viewCacheManager->clearTags();

    return $data;
  }

  /**
   * @see get_cache_tag_save()
   * @param array $tags
   */
  function cache_tag_save (array $tags = null)
  {
    print get_cache_tag_save($tags);
  }
