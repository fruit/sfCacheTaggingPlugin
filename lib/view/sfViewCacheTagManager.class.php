<?php

  /**
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */
  
  /**
   * This is extended cache manager with additional methods to work
   * with cache tags.
   *
   * The most important difference from sfViewCacheManager is support to use
   * sepparate cache systems for data and locks (performance reasons).
   *
   * By default data and lock cache system is same.
   *
   * @package sfCacheTaggingPlugin
   * @subpackage view
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class sfViewCacheTagManager extends sfViewCacheManager
  {
    /**
     * holder's namespaces
     * Namespace name should be "UpperCamelCased"
     * This names is used in method patterns "call%sMethod",
     * where %s is Page/Action/Partial
     */
    const
      NAMESPACE_PAGE    = 'Page',
      NAMESPACE_ACTION  = 'Action',
      NAMESPACE_PARTIAL = 'Partial';

    /**
     * Data cache and locker cache container
     *
     * @var sfTaggingCache
     */
    protected $taggingCache = null;


    /**
     * sfViewCacheTagManager option holder
     *
     * @var array
     */
    protected $options = array();

    /**
     * Returns predefined namespaces
     *
     * @return array Array of declared content namespaces
     */
    public static function getNamespaces ()
    {
      return array(
        self::NAMESPACE_PAGE,
        self::NAMESPACE_ACTION,
        self::NAMESPACE_PARTIAL,
      );
    }

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
     * Sets options to the sfTaggingCache
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
      return $this->getTaggingCache()->getContentTagHandler();
    }

    /**
     * @return sfEventDispatcher
     */
    public function getEventDispatcher ()
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
     * @param sfCache   $taggingCache
     * @param array     $options
     *
     * @see sfViewCacheManager::initialize()
     */
    public function initialize ($context, sfCache $taggingCache,
      $options = array()
    )
    {
      if (! $taggingCache instanceof sfTaggingCache)
      {
        throw new InvalidArgumentException(
          sprintf(
            'Cache "%s" is not instanceof sfTaggingCache',
            get_class($taggingCache)
          )
        );
      }

      if (! sfConfig::get('sf_cache'))
      {
        $taggingCache = new sfNoTaggingCache();
      }

      $this->setTaggingCache($taggingCache);
      $this->cache = $this->getTaggingCache()->getDataCache();

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
     * Retrieves sfTaggingCache object
     *
     * @return sfTaggingCache
     */
    public function getTaggingCache ()
    {
      return $this->taggingCache;
    }

    /**
     * Sets sfTaggingCache object
     *
     * @param sfTaggingCache $taggingCache
     * @return sfViewCacheTagManager
     */
    protected function setTaggingCache (sfTaggingCache $taggingCache)
    {
      $this->taggingCache = $taggingCache;

      return $this;
    }

    /**
     * Due to an optimized version (self::_get) and compatibility
     *
     * @param string $internalUri
     * @return mixed
     */
    public function get ($internalUri)
    {
      return serialize($this->_get($internalUri));
    }

    /**
     * Retrieves content in the cache.
     *
     * Match duplicated as a parent::get()
     *
     * Optimezed version, does not call to serialize/unserialize when cache
     * is stored as opcode
     *
     * @param string $internalUri Internal uniform resource identifier
     * @return mixed The content in the cache
     */
    protected function _get ($internalUri)
    {
      // no cache or no cache set for this action
      if (! $this->isCacheable($internalUri) || $this->ignore())
      {
        return null;
      }

      $retval = $this->getTaggingCache()->get($this->generateCacheKey($internalUri));

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
     * @return mixed
     */
    public function set ($data, $internalUri, $tags = array())
    {
      if (! $this->isCacheable($internalUri))
      {
        return false;
      }

      $this->getTaggingCache()->set(
        $this->generateCacheKey($internalUri),
        $data,
        $this->getLifeTime($internalUri),
        $tags
      );

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
      if (! $this->isCacheable($internalUri) || $this->ignore())
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
     * @return array An array composed of the cached content and
     *               the view attribute holder
     */
    public function getActionCache ($uri)
    {
      if (! $this->isCacheable($uri) || $this->withLayout($uri))
      {
        return null;
      }

      // retrieve content from cache
      $data = $this->_get($uri);

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
      if (! $this->isCacheable($uri) || $this->withLayout($uri))
      {
        return $content;
      }

      $actionTags = $this
        ->getContentTagHandler()
        ->getContentTags(
          self::NAMESPACE_ACTION
        );

      $actionCacheValue = array(
        'content'           => $content,
        'decoratorTemplate' => $decoratorTemplate,
        'response'          => $this->context->getResponse()
      );

      $saved = $this->set($actionCacheValue, $uri, $actionTags);

      if ($saved && sfConfig::get('sf_web_debug'))
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
     * @return null
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
          self::NAMESPACE_PAGE
        );

      // save content in cache
      $saved = $this->set(
        $this->context->getResponse(), $uri, $pageTags
      );

      if ($saved && sfConfig::get('sf_web_debug'))
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

    /**
     * Sets partial content with associated tags
     * 
     * @see parent::setPartialCache()
     * 
     * @param string $module
     * @param string $action
     * @param string $cacheKey
     * @param string $content
     * @return string
     */
    public function setPartialCache ($module, $action, $cacheKey, $content)
    {
      $uri = $this->getPartialUri($module, $action, $cacheKey);

      if (! $this->isCacheable($uri))
      {
        return $content;
      }

      $tagHandler = $this->getContentTagHandler();
      
      $partialTags = $tagHandler->getContentTags(self::NAMESPACE_PARTIAL);

      $saved = $this->set(
        array(
          'content' => $content,
          'response' => $this->context->getResponse()
        ),
        $uri,
        $partialTags
      );

      if ($saved && sfConfig::get('sf_web_debug'))
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
                'new' => true,
              )
            ),
            $content
          )
          ->getReturnValue();
      }

      $tagHandler->removeContentTags(self::NAMESPACE_PARTIAL);

      return $content;
    }

    /**
     * Listens to the 'view.cache.filter_content' event to decorate a chunk of HTML with cache information.
     *
     * Added info about linked tags
     *
     * @param sfEvent $event   A sfEvent instance
     * @param string  $content The HTML content
     *
     * @return string The decorated HTML string
     */
    public function decorateContentWithDebug (sfEvent $event, $content)
    {
      $updatedContent = parent::decorateContentWithDebug($event, $content);

      if ($content === $updatedContent)
      {
        return $content;
      }

      $cacheMetadata = new CacheMetadata($this->getCache()->get(
        $this->generateCacheKey($event['uri']))
      );

      if (null === $cacheMetadata->getData())
      {
        return $content;
      }

      $tags = $cacheMetadata->getTags();
      $tagsCount = count($tags);
      
      $tagsContent = sprintf('[cache&nbsp;tags]&nbsp;count:&nbsp;%d', $tagsCount);

      if (0 != $tagsCount)
      {
        $tagsContent .= ',&nbsp;tags:';

        foreach ($tags as $name => $version)
        {
          $tagsContent .= sprintf(
            ' %s(%s),',
            htmlspecialchars($name, ENT_QUOTES, sfConfig::get('sf_charset')),
            htmlspecialchars($version, ENT_QUOTES, sfConfig::get('sf_charset'))
          );
        }

        $tagsContent = substr($tagsContent, 0, -1) . '.';
      }

      $textToReplace = '&nbsp;<br />&nbsp;';

      return str_replace($textToReplace, $tagsContent, $updatedContent);
    }

    /**
     * Before checking cache key - saves passed tags
     *
     * @see parent::checkCacheKey()
     * @param  array  $parameters An array of parameters
     * @return string The cache key
     */
    public function checkCacheKey (array & $parameters)
    {
      $tagsKey = 'sf_cache_tags';

      if (isset($parameters[$tagsKey]))
      {
        $tags = true === sfConfig::get('sf_escaping_strategy')
          ? sfOutputEscaper::unescape($parameters[$tagsKey])
          : $parameters[$tagsKey];

        $this
          ->getContentTagHandler()
          ->setContentTags($tags, self::NAMESPACE_PARTIAL);

        if (! isset($parameters['sf_cache_key']))
        {
          throw new sfCacheException(
            '"sf_cache_key" not declared in partial parameters'
          );
        }

        unset($parameters[$tagsKey]);
      }

      return parent::checkCacheKey($parameters);
    }

    /**
     * Code is much dublicated (enough same)
     * with one distinction - it work already with unserialized data
     *
     * @see parent::getPageCache();
     * @param string $uri
     * @return boolean
     */
    public function getPageCache ($uri)
    {
      $cachedResponse = $this->_get($uri);

      if (null === $cachedResponse)
      {
        return false;
      }
      
      $cachedResponse->setEventDispatcher($this->getEventDispatcher());

      if (sfView::RENDER_VAR == $this->controller->getRenderMode())
      {
        $this->controller->getActionStack()->getLastEntry()->setPresentation($cachedResponse->getContent());
        $this->context->getResponse()->setContent('');
      }
      else
      {
        $this->context->setResponse($cachedResponse);

        if (sfConfig::get('sf_web_debug'))
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
                  'new' => false,
                )
              ),
              $this->context->getResponse()->getContent()
            )
            ->getReturnValue()
            ;

          $this->context->getResponse()->setContent($content);
        }
      }

      return true;
    }

    /**
     * Gets a partial template from the cache.
     *
     * @param  string $module    The module name
     * @param  string $action    The action name
     * @param  string $cacheKey  The cache key
     *
     * @return string The cache content
     */
    public function getPartialCache ($module, $action, $cacheKey)
    {
      $uri = $this->getPartialUri($module, $action, $cacheKey);

      if (! $this->isCacheable($uri))
      {
        return null;
      }

      // retrieve content from cache
      $cache = $this->_get($uri);

      if (null === $cache)
      {
        return null;
      }

      $content = $cache['content'];
      $this->context->getResponse()->merge($cache['response']);

      if (sfConfig::get('sf_web_debug'))
      {
        $content = $this->dispatcher->filter(new sfEvent($this, 'view.cache.filter_content', array('response' => $this->context->getResponse(), 'uri' => $uri, 'new' => false)), $content)->getReturnValue();
      }

      return $content;
    }
  }