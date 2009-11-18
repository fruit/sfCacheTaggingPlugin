<?php

class sfViewCacheTagManager extends sfViewCacheManager
{
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
    catch (Exception $e) { }

    return $data;
  }

  public function setTags ($tags)
  {
    sfConfig::set('symfony.cache.tags', $tags);
  }
}