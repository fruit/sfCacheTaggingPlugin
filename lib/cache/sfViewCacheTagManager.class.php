<?php

/*
 * This file is part of the sfCacheTaggingPlugin package.
 * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * This is extended cache manager with additional methods to work with cache tags.
 * The most important difference from sfViewCacheManager is support to use
 * sepparate cache systems for data and locks (performance reasons).
 *
 * By default data and lock cache system is same.
 *
 * @package sfCacheTaggingPlugin
 * @author Ilya Sabelnikov <fruit.dev@gmail.com>
 */
class sfViewCacheTagManager extends sfViewCacheManager
{
  protected 
    $tagger = null,
    $options = array();

  public function getOptions ()
  {
    return $this->options;
  }

  /**
   * Initialize cache manager
   *
   * @param sfContext $context
   * @param sfCache $cache
   * @param array $options
   *
   * @see sfViewCacheManager::initialize
   */
  public function initialize($context, sfCache $cache, $options = array())
  {
    if (! $cache instanceof sfTagCache)
    {
      throw new InvalidArgumentException(
        sprintf('Cache "%s" is not instanceof sfTagCache', get_class($cache))
      );
    }

    $this->context    = $context;
    $this->dispatcher = $context->getEventDispatcher();
    $this->controller = $context->getController();
    $this->request    = $context->getRequest();
    $this->options    = array_merge(array(
      'cache_key_use_vary_headers' => true,
      'cache_key_use_host_name'    => true,
    ), $options);

    if (sfConfig::get('sf_web_debug'))
    {
      $this->dispatcher->connect('view.cache.filter_content', array($this, 'decorateContentWithDebug'));
    }

    // empty configuration
    $this->cacheConfig = array();

    $this->tagger = $cache;
    // cache instance
    $this->cache = $cache->getCache();

    // routing instance
    $this->routing = $context->getRouting();
  }

  /**
   * @return sfTagCache
   */
  public function getTagger ()
  {
    return $this->tagger;
  }

  /**
   * Initializes ouput buffering
   *
   * @param string $key This is Your cache key
   * @return null|mixed if cache exists and it is not expired,
   *                    returns cache data, in other case null
   */
  public function startWithTags($key)
  {
    if (! is_null($data = $this->getTagger()->get($key)))
    {
      return $data;
    }
    else
    {
      ob_start();
      ob_implicit_flush(0);
      
      return null;
    }
  }

  /**
   * Determinates output buffering
   *
   * @param string $key Cache key
   * @param integer $lifeTime Time to live in seconds
   * @param array $tags Assoc array where key is tag key and value is version
   * @return mixed cache data
   */
  public function stopWithTags($key, $lifeTime, $tags)
  {
    $data = ob_get_clean();

    $this->getTagger()->set($key, $data, $lifeTime, $tags);
    
    return $data;
  }

  /**
   * Temporary stores tag keys, while buffer is writing
   *
   * @param array $tags
   */
  public function setTags ($tags)
  {
    sfConfig::set('symfony.cache.tags', $tags);
  }
}