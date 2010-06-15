<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  require_once realpath(dirname(__FILE__) . '/../../../../../test/bootstrap/functional.php');

//  $cc = new sfCacheClearTask(sfContext::getInstance()->getEventDispatcher(), new sfFormatter());
//  $cc->run();

  $browser = new sfTestFunctional(new sfBrowser());

  $t = $browser->test();
  
  $cacheManager = sfContext::getInstance()->getViewCacheManager();

  sfContext::getInstance()->getConfiguration()->loadHelpers(array('Partial', 'PartialTag'));

  $t = new lime_test();

  $t->ok(is_string(get_component_tag('blog_post', 'indexNews')), 'get_compoment_tag() returns content');
  $t->is(get_component_tag('blog_post', 'indexNewsNone'), null, 'get_compoment_tag() returns null on sfView::NONE');

  ob_start();
  include_component_tag('blog_post', 'indexNews');
  $content = ob_get_clean();

  $t->ok(false !== strpos($content, '<h1>Post listing</h1>'), 'content is printed out');