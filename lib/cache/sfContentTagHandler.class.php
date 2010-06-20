<?php

  /**
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * @todo PHPDOC
   *
   * @package sfCacheTaggingPlugin
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class sfContentTagHandler
  {
    /**
     * holder's namespaces
     * Namespace name should be "UpperCamelCased"
     * This names is used in method patterns "call%sMethod",
     * where %s is User/Page/Action
     */
    const
      NAMESPACE_USER   = 'User',
      NAMESPACE_PAGE   = 'Page',
      NAMESPACE_ACTION = 'Action';

    /**
     * @var sfTagNamespacedParameterHolder
     */
    protected $holder = null;

    public function __construct()
    {
      $this->holder = new sfTagNamespacedParameterHolder();
    }

    /**
     *
     * @return array Array of declared content namespaces
     */
    public static function getNamespaces ()
    {
      /**
       * return same result, but difficult to read code
       */
      # $reflection = new ReflectionClass(__CLASS__);
      # return array_values($reflection->getConstants());

      return array(
        self::NAMESPACE_ACTION,
        self::NAMESPACE_USER,
        self::NAMESPACE_PAGE,
      );
    }

    /**
     * Returns namespace holder
     *
     * @see sfNamespacedParameterHolder
     * @return sfTagNamespacedParameterHolder
     */
    protected function getHolder ()
    {
      return $this->holder;
    }

    public function setContentTags ($tags, $namespace)
    {
      $this->removeContentTags($namespace);

      $this->getHolder()->add($tags, $namespace);
    }

    public function addContentTags ($tags, $namespace)
    {
      $this->getHolder()->add($tags, $namespace);
    }

    public function getContentTags ($namespace)
    {
      return $this->getHolder()->getAll($namespace);
    }

    public function setContentTag ($tagName, $tagVersion, $namespace)
    {
      $this->getHolder()->set($tagName, $tagVersion, $namespace);
    }

    public function removeContentTag ($tagName, $namespace)
    {
      $this->getHolder()->remove($tagName, null, $namespace);
    }

    public function removeContentTags ($namespace)
    {
      $this->getHolder()->removeNamespace($namespace);
    }

    public function hasContentTag ($tagName, $namespace)
    {
      return $this->getHolder()->has($tagName, $namespace);
    }
  }