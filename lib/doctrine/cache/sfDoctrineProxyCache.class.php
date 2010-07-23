<?php

  /**
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * Class to replace doctrine cache engine with symfony's cache engine
   * This is only for storing cache with its associated tags
   * (Doctrine does not yet supports ability to add tags on stored cache)
   * 
   * @package sfCacheTaggingPlugin
   * @subpackage doctrine
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class sfDoctrineProxyCache extends Doctrine_Cache_Driver
  {
    /**
     * Short method to retrieve sfTaggingCache for internal use
     *
     * @throws sfCacheDisabledException when some mandatory objects are missing
     * @return sfTaggingCache
     */
    protected function getTaggingCache ()
    {
      return sfCacheTaggingToolkit::getTaggingCache();
    }

    /**
     * @see parent::_doSave()
     * @return boolean
     */
    protected function _doSave ($id, $data, $lifeTime = false)
    {
      try
      {
        return $this->getTaggingCache()->set(
          $id, $data, ! $lifeTime ? null : $lifeTime
        );
      }
      catch (sfCacheDisabledException $e)
      {
        # be silent - sf_cache is disabled for this environment
      }

      return false;
    }

    /**
     * @see parent::_doSave()
     * @return boolean
     */
    protected function _doSaveWithTags ($id, $data, $lifeTime = false, 
      array $tags = array()
    )
    {
      try
      {
        return $this->getTaggingCache()->set(
          $id, $data, ! $lifeTime ? null : $lifeTime, $tags
        );
      }
      catch (sfCacheDisabledException $e)
      {
        # be silent - sf_cache is disabled for this environment
      }

      return false;
    }

    /**
     * @todo sf*Cache::getCacheInfo() is protected to use from outside
     *       base functionality works without this method implementation
     *
     * @return array
     */
    protected function _getCacheKeys ()
    {
      return array();
    }

    /**
     * @see parent::_doDelete()
     * @return boolean
     */
    protected function _doDelete ($id)
    {
      try
      {
        $this->getTaggingCache()->remove($id);

        return $this->getTaggingCache()->remove($id);
      }
      catch (sfCacheDisabledException $e)
      {
        # be silent - sf_cache is disabled for this environment
      }

      return false;
    }

    /**
     * @see parent::_doContains()
     * @return boolean
     */
    protected function _doContains ($id)
    {
      try
      {
        return $this->getTaggingCache()->has($id);
      }
      catch (sfCacheDisabledException $e)
      {
        # be silent - sf_cache is disabled for this environment
      }

      return false;
    }

    /**
     * @see parent::_doFetch()
     * @return mixed
     */
    protected function _doFetch ($id, $testCacheValidity = true)
    {
      $value = false;

      try
      {
        $value = $this->getTaggingCache()->get($id);
        $value = null === $value ? false : $value;
      }
      catch (sfCacheDisabledException $e)
      {
        # be silent - sf_cache is disabled for this environment
      }

      return $value;
    }

    public function saveWithTags ($id, $data, $lifeTime = false,
      array $tags = array()
    )
    {
      return $this->_doSaveWithTags(
        $this->_getKey($id), $data, $lifeTime, $tags
      );
    }

  }