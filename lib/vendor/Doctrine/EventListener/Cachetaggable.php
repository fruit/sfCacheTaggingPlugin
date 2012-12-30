<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2013 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * Rollbacks object tag updates if something went wrong or save all tags
   * information, if no errors occureed.
   *
   * @package sfCacheTaggingPlugin
   * @subpackage doctrine
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class Doctrine_EventListener_Cachetaggable extends Doctrine_EventListener
  {
    /**
     * List of callbacks to execute on postTransactionCommit
     *
     * @var array
     */
    protected $commands = array();

    /**
     * Delays the method call to time when database transaction end with COMMIT
     *
     * @param string  $method
     * @param array   $arguments
     * @return Doctrine_EventListener_Cachetaggable
     */
    public function __call ($method, $arguments)
    {
      $this->commands[] = array($method, $arguments);

      return $this;
    }

    /**
     * Before transaction begin clears list of commands
     *
     * @param Doctrine_Event $event
     * @return null
     */
    public function preTransactionBegin (Doctrine_Event $event)
    {
      $this->commands = array();
    }

    /**
     * If rollback occuress, clears list of commands
     *
     * @param Doctrine_Event $event
     * @return null
     */
    public function postTransactionRollback (Doctrine_Event $event)
    {
      $this->commands = array(); // clear commands list
    }

    /**
     * Triggers deferred method calls if such exists
     *
     * @param Doctrine_Event $event
     * @return null
     */
    public function postTransactionCommit (Doctrine_Event $event)
    {
      if (0 == count($this->commands)) return;

      $tagging = sfCacheTaggingToolkit::getTaggingCache();

      // invoke callbacks in which the order it was added
      reset($this->commands);
      while (list(/*$key*/, list($method, $args)) = each($this->commands))
      {
        call_user_func_array(array($tagging, $method), $args);
      }

      $this->commands = array(); // free used memory
    }

  }