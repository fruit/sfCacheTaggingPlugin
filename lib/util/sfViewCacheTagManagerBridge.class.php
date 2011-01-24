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
    /**
     * @var sfTaggingCache
     */
    protected $taggingCache = null;

    /**
     * @param sfTaggingCache $taggingCache
     */
    public function __construct (sfTaggingCache $taggingCache)
    {
      $this->taggingCache = $taggingCache;
    }

    /**
     * @see sfViewCacheTagManager::getTaggingCache
     * @return sfTaggingCache
     */
    protected function getTaggingCache ()
    {
      return $this->taggingCache;
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
     * @return void|array|boolean
     */
    public function __call ($method,  $arguments)
    {
      $orBlock = implode('|', sfViewCacheTagManager::getNamespaces());
      $pattern = sprintf('/\w+(%s)Tags?/', $orBlock);

      if (! preg_match($pattern, $method, $matches))
      {
        throw new BadMethodCallException(sprintf(
          'Method "%s" does not exists in %s', $method, get_class($this)
        ));
      }

      $contentNamespace = $matches[1];

      array_push($arguments, $contentNamespace);

      $nsLength = strlen($contentNamespace);
      
      # transforms "getPageTag" to "getContentTag"
      $contentAbstractMethod = substr_replace(
        $method, 'Content', strpos($method, $contentNamespace), $nsLength
      );

      try
      {
        $callable = new sfCallableArray(array(
          $this->getTaggingCache()->getContentTagHandler(),
          $contentAbstractMethod
        ));

        return $callable->callArray($arguments);
      }
      catch (sfException $e)
      {
        throw new BadMethodCallException($e->getMessage());
      }
    }

    /**
     * Appends tags to doctrine result cache
     *
     * @param mixed                   $tags
     * @param Doctrine_Query|string   $q        Doctrine_Query or string
     *
     * @param array $params   params from $q->getParams()
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

      $this->getTaggingCache()->addTagsToCache(
        $key, sfCacheTaggingToolkit::formatTags($tags)
      );

      return $this;
    }
  }