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
  class CacheMetadata implements Serializable
  {
    /**
     * @var sfParameterHolder
     */
    protected $holder = null;

    /**
     * @var mixed
     */
    protected $data = null;

    /**
     * @param mixed $data
     * @param array $tags
     */
    public function __construct ($data = null, array $tags = array())
    {
      $this->initialize($data, $tags);
    }

    /**
     * @param mixed $data
     * @param array $tags
     * @return void
     */
    public function initialize ($data, array $tags = array())
    {
      $this->holder = new sfParameterHolder();

      $this->setTags($tags);
      $this->setData($data);
    }

    /**
     * @return sfParameterHolder
     */
    protected function getHolder ()
    {
      return $this->holder;
    }

    /**
     * @param mixed $data
     * @return void
     */
    public function setData ($data)
    {
      $this->data = $data;
    }

    /**
     * @return array
     */
    public function getTags ()
    {
      return $this->getHolder()->getAll();
    }

    /**
     * Rewrites all existing tags with new
     * 
     * @param array $tags
     * @return void
     */
    public function setTags (array $tags)
    {
      $this->getHolder()->clear();
      $this->getHolder()->add($tags);
    }

    /**
     * Return cache data (content)
     *
     * @return mixed
     */
    public function getData ()
    {
      return $this->data;
    }

    /**
     * Checks for tag exists
     *
     * @param string $tagName
     * @return boolean
     */
    public function hasTag ($tagName)
    {
      return $this->getHolder()->has($tagName);
    }

    /**
     * Appends tags to existing
     *
     * @param array $tags
     * @return void
     */
    public function addTags (array $tags)
    {
      foreach ($tags as $name => $version)
      {
        $this->setTag($name, $version);
      }
    }

    /**
     * @param string $tagName
     * @return false|string
     */
    public function getTag ($tagName)
    {
      return $this->getHolder()->get($tagName);
    }

    /**
     * @param string $tagName
     * @param string $tagVersion
     * @return void
     */
    public function setTag ($tagName, $tagVersion)
    {
      $has = $this->hasTag($tagName);

      if (! $has || ($has && $this->getTag($tagName) < $tagVersion))
      {
        $this->getHolder()->set($tagName, $tagVersion);
      }
    }

    /**
     * Serializes the current instance.
     *
     * @return array Serialized CacheMetadata object
     */
    public function serialize()
    {
      return serialize(array($this->data, $this->getTags()));
    }

    /**
     * Unserializes a CacheMetadatainstance.
     *
     * @param string $serialized A serialized CacheMetadata instance
     * @return void
     */
    public function unserialize($serialized)
    {
      list($data, $tags) = unserialize($serialized);

      $this->initialize($data, $tags);
    }

    /**
     * @return string
     */
    public function __toString ()
    {
      $output = sprintf(
        "%s:\n  data: %s\n  tags:\n",
        __CLASS__,
        (string) $this->getData());

      foreach ($this->getTags() as $name => $version)
      {
        $output .= "    {$name}: {$version}\n";
      }

      return $output;
    }
  }