<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * Added callArray method to pass arguments not separatly, but as array
   *
   * @package sfCacheTaggingPlugin
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class sfCallableArray extends sfCallable
  {
    public function callArray ($arguments)
    {
      if (! is_callable($this->getCallable()))
      {
        return call_user_func_array(array($this, 'call'), $arguments);
      }

      return call_user_func_array($this->callable, $arguments);
    }
  }