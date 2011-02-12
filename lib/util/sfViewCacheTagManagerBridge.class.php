<?php

  /**
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * Asteric (*) desribes place where you pass values: Partial/Action/Page
   *
   * @method null     set*Tags    set*Tags (mixed $tags)
   * @method null     add*Tags    add*Tags (mixed $tags)
   * @method array    get*Tags    get*Tags ()
   * @method null     remove*Tags remove*Tags ()
   * @method null     set*Tag     set*Tag (string $tagName, string $tagVersion)
   * @method boolean  has*Tag     has*Tag (string $tagName)
   * @method null     remove*Tag  remove*Tag (string $tagName)
   *
   * @package sfCacheTaggingPlugin
   * @subpackage util
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class sfViewCacheTagManagerBridge
  {
    protected $allowedCallMethods = array(
      sfViewCacheTagManager::NAMESPACE_ACTION => array(
        'setActionTags',
        'addActionTags',
        'getActionTags',
        'removeActionTags',
        'setActionTag',
        'hasActionTag',
        'removeActionTag',
      ),
      sfViewCacheTagManager::NAMESPACE_PARTIAL => array(
        'setPartialTags',
        'addPartialTags',
        'getPartialTags',
        'removePartialTags',
        'setPartialTag',
        'hasPartialTag',
        'removePartialTag',
      ),
      sfViewCacheTagManager::NAMESPACE_PAGE => array(
        'setPageTags',
        'addPageTags',
        'getPageTags',
        'removePageTags',
        'setPageTag',
        'hasPageTag',
        'removePageTag',
      ),
    );

    /**
     * @see sfViewCacheTagManager::getTaggingCache
     * @return sfTaggingCache
     */
    protected function getTaggingCache ()
    {
      return sfCacheTaggingToolkit::getTaggingCache();
    }

    /**
     * Magic method __call
     *
     *    If user calls:
     *        $this->setActionTags($tags);
     *    transform it to:
     *        $this->setContentTags(
     *          $tags, sfViewCacheTagManager::NAMESPACE_ACTION
     *        );
     *
     *    If user calls:
     *       $this->hasPageTag();
     *    transform it to:
     *       $this->hasContentTag(sfViewCacheTagManager::NAMESPACE_PAGE);
     *
     * @param string  $method
     * @param array   $arguments
     * @throws BadMethodCallException
     * @return null|array|boolean
     */
    public function __call ($method, $arguments)
    {
      $contentNamespace = null;

      foreach ($this->allowedCallMethods as $namespace => $methods)
      {
        if (in_array($method, $methods))
        {
          $contentNamespace = $namespace;

          break;
        }
      }

      if (null === $contentNamespace)
      {
        throw new BadMethodCallException(sprintf(
          'Method "%s" does not exists in %s', $method, get_class($this)
        ));
      }

      array_push($arguments, $contentNamespace);

      $nsLength = strlen($contentNamespace);
      
      # transforms "getPageTag" to "getContentTag"
      $contentAbstractMethod = substr_replace(
        $method, 'Content', strpos($method, $contentNamespace), $nsLength
      );

      $callable = new sfCallableArray(array(
        $this->getTaggingCache()->getContentTagHandler(),
        $contentAbstractMethod
      ));

      return $callable->callArray($arguments);
    }

    /**
     * Appends tags to doctrine result cache
     *
     * @param mixed                   $tags
     * @param Doctrine_Query|string   $q        Doctrine_Query or string
     * @param array                   $params   params from $q->getParams()
     *
     * @return sfViewCacheTagManagerBridge
     */
    public function addDoctrineTags ($tags, $q, array $params = array())
    {
      $key = null;

      if (is_string($q))
      {
        $key = $q;
      }
      elseif ($q instanceof Doctrine_Query)
      {
        $key = $q->getResultCacheHash($params);
      }
      else
      {
        throw new InvalidArgumentException('Invalid arguments are passed');
      }

      $tags = sfCacheTaggingToolkit::formatTags($tags);

      $this->getTaggingCache()->addTagsToCache($key, $tags);

      return $this;
    }
  }