<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2012 Ilya Sabelnikov <fruit.dev@gmail.com>
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
     *
     * @param object $invoker
     * @param string $method
     * @param array $args
     * @return Doctrine_EventListener_Cachetaggable
     */
    public function postpone ($invoker, $method, array $args)
    {
      $this->commands[] = array(array($invoker, $method), $args);

      return $this;
    }

    public function preTransactionBegin (Doctrine_Event $event)
    {
      $this->commands = array(); // clear commands list
    }

    public function postTransactionRollback (Doctrine_Event $event)
    {
      $this->commands = array(); // clear commands list
    }

    public function postTransactionCommit (Doctrine_Event $event)
    {
      if (0 == count($this->commands)) return;

      // invoke callbacks in which the order it was added
      reset($this->commands);
      while (list(/*$key*/, list($callable, $args)) = each($this->commands))
      {
        call_user_func_array($callable, $args);
      }

      $this->commands = array(); // free used memory
    }

  }