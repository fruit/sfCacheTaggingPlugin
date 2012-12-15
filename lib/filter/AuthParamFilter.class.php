<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2012 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * Adds additional $_GET parameter to allow caching authenticated
   * private data.
   * Symfony cache block key will be based on "user_id" argument too.
   *
   * @package sfCacheTaggingPlugin
   * @subpackage filters
   * @since v4.2.0
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * @deprecated since version v4.3.0
   */
  class AuthParamFilter extends sfFilter
  {
    public function execute ($filterChain)
    {
      $message = sprintf(
        'The class %s is deprecated since %s v4.3.0. ' .
        'Use "cache.filter_cache_keys" event to add custom cache key params.',
        __CLASS__, sfCacheTaggingToolkit::PLUGIN_NAME
      );

      $this->getContext()->getEventDispatcher()->notify(
        new sfEvent($this, 'application.log', array(
          $message, 'priority' => sfLogger::NOTICE
        )
      ));

      if ($this->isFirstCall())
      {
        $context  = $this->getContext();

        /* @var $user sfSecurityUser */
        $user     = $context->getUser();
        $request  = $context->getRequest();

        $callable = array($user, 'getId');

        if (($user instanceof sfSecurityUser)
            && $user->isAuthenticated()
            && ($request instanceof sfCacheTaggingWebRequest)
            && method_exists($user, 'getId') // prevent of triggering __call()
            && is_callable($callable)        // only public method
        )
        {
          /* @var $request sfCacheTaggingWebRequest */
          $request->addGetParameters(array('user_id' => call_user_func($callable)));
        }
      }

      $filterChain->execute();
    }
  }
