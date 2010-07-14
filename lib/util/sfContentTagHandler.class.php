<?php

  /**
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * Handler for managing tags
   *
   * @package sfCacheTaggingPlugin
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class sfContentTagHandler
  {
    /**
     * @var sfTagNamespacedParameterHolder
     */
    protected $holder = null;

    public function __construct()
    {
      $this->holder = new sfTagNamespacedParameterHolder();
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

    /**
     * Removes all namespace tags and then sets new tags
     *
     * @param mixed $tags
     * @param string $namespace
     * @return void
     */
    public function setContentTags ($tags, $namespace)
    {
      $this->removeContentTags($namespace);

      $this->getHolder()->add($tags, $namespace);
    }

    /**
     * Appends tags to the existing
     *
     * @param array $tags
     * @param string $namespace
     * @return void
     */
    public function addContentTags (array $tags, $namespace)
    {
      $this->getHolder()->add($tags, $namespace);
    }

    /**
     * Retrieves tags by namespace
     *
     * @param string $namespace
     * @return array
     */
    public function getContentTags ($namespace)
    {
      return $this->getHolder()->getAll($namespace);
    }

    /**
     * Updates specific tag with new tag version
     *
     * @param string $tagName
     * @param mixed $tagVersion
     * @param string $namespace
     * @return void
     */
    public function setContentTag ($tagName, $tagVersion, $namespace)
    {
      $this->getHolder()->set($tagName, $tagVersion, $namespace);
    }

    /**
     * Remove specific tag by tag name
     *
     * @param string $tagName
     * @param string $namespace
     * @return void
     */
    public function removeContentTag ($tagName, $namespace)
    {
      $this->getHolder()->remove($tagName, null, $namespace);
    }

    /**
     * Removes all namespace tags
     *
     * @param string $namespace
     * @return void
     */
    public function removeContentTags ($namespace)
    {
      $this->getHolder()->removeNamespace($namespace);
    }

    /**
     * Check, if specific tag exists
     *
     * @param string $tagName
     * @param string $namespace
     * @return boolean
     */
    public function hasContentTag ($tagName, $namespace)
    {
      return $this->getHolder()->has($tagName, $namespace);
    }


    /**
     *
     * @param array $references
     * @param boolean $deep
     * @param string $namespace
     * @return void
     */
    public function addContentReferencesTags (Doctrine_Record $object, $namespace, $deep = false)
    {
      foreach ($object->getReferences() as $objectKey => $reference)
      {
        if ($reference instanceof Doctrine_Null)
        {
          continue;
        }

        if ($reference instanceof Doctrine_Collection)
        {
          foreach ($reference as $referenceKey => $referenceObject)
          {
            if (! $referenceObject->getTable()->hasTemplate('Cachetaggable'))
            {
              continue;
            }

            $this->addContentTags($referenceObject->getTags(), $namespace, $deep);

            if ($deep)
            {
              $this->addContentReferencesTags($referenceObject, $namespace, $deep);
            }
          }
        }

        if ($reference instanceof Doctrine_Record)
        {
          if ($reference->getTable()->hasTemplate('Cachetaggable'))
          {
            $this->addContentTags($reference->getTags(), $namespace, $deep);
          }
        }
      }
    }
  }