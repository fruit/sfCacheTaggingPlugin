<?php

class sfViewCacheTagManager extends sfViewCacheManager
{
  protected $tagger = null;

  public function initialize($context, sfCache $cache, $options = array())
  {
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

  public function startWithTags($name)
  {
    if (! is_null($data = $this->getCache()->get($name)))
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

  public function stopWithTags($name, $lifeTime, $tags)
  {
    $data = ob_get_clean();

    try
    {
      $this->getCache()->set($name, $data, $lifeTime, $tags);
    }
    catch (Exception $e)
    {
      # could be triggered Exception?
    }

    return $data;
  }

  public function setTags ($tags)
  {
    sfConfig::set('symfony.cache.tags', $tags);
  }
}