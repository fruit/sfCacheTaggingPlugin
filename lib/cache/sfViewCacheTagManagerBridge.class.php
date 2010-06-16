<?php

  /**
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * Asteric (*) desribes place where you pass values: User/Action/Page
   *
   * @method null set*Tags set*Tags(mixed $tags)
   * @method null add*Tags add*Tags(mixed $tags)
   * @method array get*Tags get*Tags()
   * @method null remove*Tags remove*Tags()
   * @method null set*Tag set*Tag(string $tagName, string $tagVersion)
   * @method boolean has*Tag has*Tag(string $tagName)
   * @method null remove*Tag remove*Tag(string $tagName)
   *
   * @package sfCacheTaggingPlugin
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class sfViewCacheTagManagerBridge
  {
    /**
     *
     * @var sfViewCacheTagManager
     */
    protected $manager = null;

    public function __construct (sfViewCacheTagManager $viewCacheTagManager)
    {
      $this->manager = $viewCacheTagManager;
    }

    /**
     * Returns a instance of sfViewCacheTagManager
     *
     * @return sfViewCacheTagManager
     */
    protected function getManager ()
    {
      return $this->manager;
    }

    /**
     * Magic method __call
     *
     *    If user calls:
     *        $this->setActionTags($tags);
     *    transform it to:
     *        $this->setContentTags($tags, sfContentTagHandler::NAMESPACE_ACTION);
     *
     *    If user calls:
     *       $this->hasPageTag();
     *    transform it to:
     *       $this->hasContentTag(sfContentTagHandler::NAMESPACE_PAGE);
     *
     * @param string $method
     * @param array $arguments
     * @throws BadMethodCallException
     * @return null|array|boolean
     */
    public function __call ($method,  $arguments)
    {
      $orBlock = implode('|', sfContentTagHandler::getNamespaces());
      $pattern = sprintf('/\w+(%s)Tags?/', $orBlock);

      if (! preg_match($pattern, $method, $matches))
      {
        throw new BadMethodCallException(sprintf(
          'Method "%s" does not exists in %s', $method, get_class($this)
        ));
      }

      $contentNamespace = $matches[1];

      array_push($arguments, $contentNamespace);

      # transforms "getPageTag" to "getContentTag"
      $contentAbstractMethod = substr_replace(
        $method, 'Content', strpos($method, $contentNamespace), strlen($contentNamespace)
      );

      try
      {
        $callable = new sfCallableArray(array(
          $this->getManager()->getContentTagHandler(),
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
     * @see sfViewCacheTagManager::getTaggingCache
     * @return sfTagCache
     */
    public function getTaggingCache ()
    {
      return $this->getManager()->getTaggingCache();
    }
  }