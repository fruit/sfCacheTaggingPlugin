<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  require_once realpath(dirname(__FILE__) . '/../../../../../test/bootstrap/functional.php');

  require_once sfConfig::get('sf_symfony_lib_dir') . '/vendor/lime/lime.php';

//  $cacheManager = sfContext::getInstance()->getViewCacheManager();

  $t = new lime_test();

  # check component.method_not_found

  include_once sfConfig::get('sf_apps_dir') . '/notag/modules/blog_post/actions/actions.class.php';

  try
  {
    $e = new sfEvent(
      new blog_postActions(sfContext::getInstance(), 'blog_post', 'run'),
      'component.method_not_found',
      array('method' => 'callMe', 'arguments' => array(1,2,3))
    );

    $v = sfCacheTaggingToolkit::listenOnComponentMethodNotFoundEvent($e);

    $t->ok(null === $v, 'Return null if view manager is defualt');
  }
  catch (Exception $e)
  {
    $t->fail($e->getMessage());
  }

  