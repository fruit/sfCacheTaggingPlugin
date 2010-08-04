<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
  */

  /**
   * @package sfCacheTaggingPlugin
   * @subpackage cache
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class CacheMetadata extends stdClass implements Serializable
  {
    /**
     * @var sfParameterHolder
     */
    protected $holder = null;

    /**
     * @var mixed
     */
    protected $data = null;

    public function __construct ($data = null, array $tags = array())
    {
      $this->initialize($data, $tags);
    }

    protected function initialize ($data, array $tags = array())
    {
      $this->holder = new sfParameterHolder();

      $this->setTags($tags);
      $this->setData($data);
    }

    /**
     *
     * @return sfParameterHolder
     */
    protected function getHolder ()
    {
      return $this->holder;
    }

    public function setData ($data)
    {
      $this->data = $data;
    }

    public function getTags ()
    {
      return $this->getHolder()->getAll();
    }

    public function setTags (array $tags)
    {
      $this->getHolder()->clear();
      $this->getHolder()->add($tags);
    }

    public function getData ()
    {
      return $this->data;
    }

    public function addTags (array $tags)
    {
      foreach ($tags as $name => $version)
      {
        $has = $this->getHolder()->has($name);

        if (! $has || ($has && $this->getHolder()->get($name) < $version))
        {
          $this->getHolder()->set($name, $version);
        }
      }
    }

    /**
     * Serializes the current instance.
     *
     * @return array Objects instance
     */
    public function serialize()
    {
      return serialize(array($this->data, $this->getTags()));
    }

    /**
     * Unserializes a sfParameterHolder instance.
     *
     * @param string $serialized  A serialized sfParameterHolder instance
     */
    public function unserialize($serialized)
    {
      list($data, $tags) = unserialize($serialized);

      $this->initialize($data, $tags);
    }
  }