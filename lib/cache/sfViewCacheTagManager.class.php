<?php

  /**
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
    /**
     * Data cache and locker cache container
     *
     * @var sfTagCache
     */
    protected $taggingCache = null;

    /**
     * Tag content handler with namespace holder (tag setting/adding/removing)
     * 
     * @var sfContentTagHandler
     */
    protected $contentTagHandler = null;

    /**
     * sfViewCacheTagManager option holder
     *
     * @var array
     */
    protected $options = array();

    
    /**
     * sfViewCacheTagManager options
     *
     * @return array
     */
    public function getOptions ()
    {
      return $this->options;
    }

    /**
     * Sets options to the sfTagCache
     *
     * @param array $options
     */
    public function setOptions (array $options)
    {
      $this->options = $options;
    }

    /**
     * @return sfContentTagHandler
     */
    public function getContentTagHandler ()
    {
      return $this->contentTagHandler;
    }

    /**
     * @return sfEventDispatcher
     */
    protected function getEventDispatcher ()
    {
      return $this->dispatcher;
    }

    /**
     * @param sfEventDispatcher $eventDispatcher
     * @return sfViewCacheTagManager
     */
    protected function setEventDispatcher (sfEventDispatcher $eventDispatcher)
    {
      $this->dispatcher = $eventDispatcher;

      return $this;
    }

    /**
     * Initialize cache manager
     *
     * @param sfContext $context
     * @param sfCache $taggingCache
     * @param array $options
     *
     * @see sfViewCacheManager::initialize
     */
    public function initialize ($context, sfCache $taggingCache, $options = array())
    {
      if (! $taggingCache instanceof sfTagCache)
      {
        throw new InvalidArgumentException(
          sprintf(
            'Cache "%s" is not instanceof sfTagCache',
            get_class($taggingCache)
          )
        );
      }

      $this->setTaggingCache($taggingCache);
      $this->cache = $this->getTaggingCache()->getDataCache();

      $this->contentTagHandler = new sfContentTagHandler();

      $this->setEventDispatcher($context->getEventDispatcher());

      $this->context = $context;
      $this->controller = $context->getController();
      $this->request = $context->getRequest();
      $this->routing = $context->getRouting();


      $this->setOptions(array_merge(
        array(
          'cache_key_use_vary_headers' => true,
          'cache_key_use_host_name'    => true,
        ), 
        $options
      ));

      if (sfConfig::get('sf_web_debug'))
      {
        $this->getEventDispatcher()->connect(
          'view.cache.filter_content',
          array($this, 'decorateContentWithDebug')
        );
      }

      // empty configuration
      $this->cacheConfig = array();
    }

    /**
     * @return sfTagCache
     */
    public function getTaggingCache ()
    {
      return $this->taggingCache;
    }

    /**
     * @deprecated use sfViewCacheTagManager::getTaggingCache() since v1.4.4
     *
     * @return sfTagCache
     */
    public function getTagger ()
    {
      sfCacheTaggingToolkit::triggerMethodIsDeprecated(
        __METHOD__, 'sfViewCacheTagManager::getTaggingCache', 'v1.4.4'
      );

      return $this->getTaggingCache();
    }

    /**
     * @param sfTagCache $taggingCache
     * @return sfViewCacheTagManager
     */
    protected function setTaggingCache (sfTagCache $taggingCache)
    {
      $this->taggingCache = $taggingCache;

      return $this;
    }

    /**
     * Initializes ouput buffering
     *
     * @param string $key This is Your cache key
     * @return null|mixed if cache exists and it is not expired,
     *                    returns cache data, in other case null
     */
    public function startWithTags ($key)
    {
      if (null !== ($data = $this->getTaggingCache()->get($key)))
      {
        return $data;
      }
      
      ob_start();
      ob_implicit_flush(0);

      return null;
    }

    /**
     * Determinates output buffering
     *
     * @param string $key Cache key
     * @param integer [optional] $lifeTime Time to live in seconds
     * @return mixed cache data
     */
    public function stopWithTags ($key, $lifetime = null)
    {
      $data = ob_get_clean();

      $this->getTaggingCache()->set(
        $key,
        $data,
        $lifetime, 
        $this->getContentTagHandler()->getContentTags(sfContentTagHandler::NAMESPACE_USER)
      );

      return $data;
    }

    /**
     * Temporary stores tag keys, while buffer is writing
     *
     * @param array $tags
     * @deprecated since v1.4.4
     * @return sfViewCacheTagManager
     */
    public function setTags ($tags)
    {
      sfCacheTaggingToolkit::triggerMethodIsDeprecated(__METHOD__, null, 'v1.4.4');

      $this
        ->getContentTagHandler()
        ->setContentTags($tags, sfContentTagHandler::NAMESPACE_USER)
      ;

      return $this;
    }

    /**
     * Appends the tags
     *
     * @param array|Doctrine_Record|Doctrine_Collection_Cachetaggable|ArrayAccess $tags
     * @deprecated since v1.4.4
     * @return sfViewCacheTagManager
     */
    public function addTags ($tags)
    {
      sfCacheTaggingToolkit::triggerMethodIsDeprecated(__METHOD__, null, 'v1.4.4');

      $this->setTags(
        array_merge($this->getTags(), sfCacheTaggingToolkit::formatTags($tags))
      );

      return $this;
    }

    /**
     * Returns added tags
     *
     * @deprecated since v1.4.4
     * @return array
     */
    public function getTags ()
    {
      sfCacheTaggingToolkit::triggerMethodIsDeprecated(__METHOD__, null, 'v1.4.4');

      return $this
        ->getContentTagHandler()
        ->getContentTags(sfContentTagHandler::NAMESPACE_USER);
    }

    /**
     * Clears the tags
     *
     * @deprecated since v1.4.4
     * @return sfViewCacheTagManager
     */
    public function clearTags ()
    {
      sfCacheTaggingToolkit::triggerMethodIsDeprecated(__METHOD__, null, 'v1.4.4');

      $this
        ->getContentTagHandler()
        ->removeContentTags(sfContentTagHandler::NAMESPACE_USER);

      return $this;
    }

    /**
     * Retrieves content in the cache.
     *
     * Match duplicated as a parent::get()
     *
     * @param string $internalUri  Internal uniform resource identifier
     * @return null|mixed The content in the cache
     */
    public function get ($internalUri)
    {
      // no cache or no cache set for this action
      if (! $this->isCacheable($internalUri) or $this->ignore())
      {
        return null;
      }

      $retval = $this->getTaggingCache()->get(
        $this->generateCacheKey($internalUri)
      );

      if (sfConfig::get('sf_logging_enabled'))
      {
        $this->getEventDispatcher()->notify(
          new sfEvent(
            $this,
            'application.log',
            array(
              sprintf(
                'Cache for "%s" %s',
                $internalUri,
                $retval !== null ? 'exists' : 'does not exist'
              )
            )
          )
        );
      }

      return $retval;
    }

    /**
     * Sets data to cache with passed tags
     *
     * @author Martin Schnabel <mcnilz@gmail.com>
     * @author Ilya Sabelnikov <fruit.dev@gmail.com>
     * @param string $internalUri
     * @return null|mixed
     */
    public function set ($data, $internalUri, $tags = array())
    {
      if (! $this->isCacheable($internalUri))
      {
        return false;
      }

      try
      {
        $this->getTaggingCache()->set(
          $this->generateCacheKey($internalUri),
          $data,
          $this->getLifeTime($internalUri),
          $tags
        );
      }
      catch (Exception $e)
      {
        return false;
      }

      if (sfConfig::get('sf_logging_enabled'))
      {
        $this->getEventDispatcher()->notify(
          new sfEvent(
            $this,
            'application.log',
            array(sprintf('Save cache for "%s"', $internalUri))
          )
        );
      }

      return true;
    }

    /**
     * Returns true if there is a cache.
     *
     * @param  string $internalUri  Internal uniform resource identifier
     *
     * @return bool true, if there is a cache otherwise false
     */
    public function has ($internalUri)
    {
      if (! $this->isCacheable($internalUri) or $this->ignore())
      {
        return null;
      }

      return $this->getTaggingCache()->has(
        $this->generateCacheKey($internalUri)
      );
    }

    /**
     * Gets an action template from the cache.
     *
     * @param  string $uri  The internal URI
     *
     * @return array  An array composed of the cached content and the view attribute holder
     */
    public function getActionCache ($uri)
    {
      if (! $this->isCacheable($uri) or $this->withLayout($uri))
      {
        return null;
      }

      // retrieve content from cache
      $data = $this->get($uri);

      if (null === $data)
      {
        return null;
      }

      $content = $data['content'];
      $data['response']->setEventDispatcher($this->getEventDispatcher());
      $this->context->getResponse()->copyProperties($data['response']);

      if (sfConfig::get('sf_web_debug'))
      {
        $content = $this->getEventDispatcher()
          ->filter(
            new sfEvent(
              $this,
              'view.cache.filter_content',
              array(
                'response' => $this->context->getResponse(),
                'uri' => $uri,
                'new' => false
              )
            ),
            $content
          )
          ->getReturnValue();
      }

      return array($content, $data['decoratorTemplate']);
    }

    /**
     * Sets an action template in the cache.
     *
     * @param  string $uri                The internal URI
     * @param  string $content            The content to cache
     * @param  string $decoratorTemplate  The view attribute holder to cache
     *
     * @return string The cached content
     */
    public function setActionCache ($uri, $content, $decoratorTemplate)
    {
      if (! $this->isCacheable($uri) or $this->withLayout($uri))
      {
        return $content;
      }

      $actionTags = $this
        ->getContentTagHandler()
        ->getContentTags(
          sfContentTagHandler::NAMESPACE_ACTION
        );

      $actionCacheValue = array(
        'content'           => $content,
        'decoratorTemplate' => $decoratorTemplate,
        'response'          => $this->context->getResponse()
      );

      $saved = $this->set($actionCacheValue, $uri, $actionTags);

      if ($saved and sfConfig::get('sf_web_debug'))
      {
        $content = $this->getEventDispatcher()
          ->filter(
            new sfEvent(
              $this,
              'view.cache.filter_content',
              array(
                'response' => $this->context->getResponse(),
                'uri' => $uri,
                'new' => true
              )
            ),
            $content
          )
          ->getReturnValue();
      }

      return $content;
    }

    /**
     * @see parent::setPageCache()
     * @param string $uri
     * @return void
     */
    public function setPageCache ($uri)
    {
      if (sfView::RENDER_CLIENT != $this->controller->getRenderMode())
      {
        return;
      }

      $pageTags = $this
        ->getContentTagHandler()
        ->getContentTags(
          sfContentTagHandler::NAMESPACE_PAGE
        );

      // save content in cache
      $saved = $this->set(serialize($this->context->getResponse()), $uri, $pageTags);

      if ($saved and sfConfig::get('sf_web_debug'))
      {
        $content = $this
          ->getEventDispatcher()
          ->filter(
            new sfEvent(
              $this,
              'view.cache.filter_content',
              array(
                'response' => $this->context->getResponse(),
                'uri' => $uri,
                'new' => true
              )
            ),
            $this->context->getResponse()->getContent()
          )
          ->getReturnValue();

        $this->context->getResponse()->setContent($content);
      }
    }
  }