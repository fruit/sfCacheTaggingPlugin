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
   * @subpackage util
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
     * @param mixed   $tags
     * @param string  $namespace
     *
     * @return void
     */
    public function setContentTags ($tags, $namespace)
    {
      $this->removeContentTags($namespace);

      $this->addContentTags($tags, $namespace);
    }

    /**
     * Appends tags to the existing
     *
     * @param mixed $tags
     * @param string $namespace
     * @return void
     */
    public function addContentTags ($tags, $namespace)
    {
      $this->getHolder()->add(
        sfCacheTaggingToolkit::formatTags($tags),
        $namespace
      );
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
     * @param string  $tagName
     * @param mixed   $tagVersion
     * @param string  $namespace
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
     *
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
     *
     * @return boolean
     */
    public function hasContentTag ($tagName, $namespace)
    {
      return $this->getHolder()->has($tagName, $namespace);
    }

    /**
     *
     * @param array   $references
     * @param boolean $isRecursively
     * @param string  $namespace
     *
     * @return void
     */
    public function addContentReferencedTags (Doctrine_Record $object, 
      $namespace, $isRecursively = false
    )
    {
      foreach ($object->getReferences() as $reference)
      {
        if ($reference instanceof Doctrine_Null)
        {
          continue;
        }

        if ($reference instanceof Doctrine_Collection)
        {
          foreach ($reference as $referenceKey => $referenceObject)
          {
            if (! $referenceObject->getTable()->hasTemplate(
              sfCacheTaggingToolkit::TEMPLATE_NAME
            ))
            {
              continue;
            }

            $this->addContentTags(
              $referenceObject->getTags($isRecursively),
              $namespace
            );
          }
        }

        if ($reference instanceof Doctrine_Record)
        {
          if ($reference->getTable()->hasTemplate(
            sfCacheTaggingToolkit::TEMPLATE_NAME
          ))
          {
            $this->addContentTags(
              $reference->getTags(), $namespace, $isRecursively
            );
          }
        }
      }
    }
  }