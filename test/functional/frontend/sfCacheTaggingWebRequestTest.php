<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2013 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  include_once realpath(dirname(__FILE__) . '/../../bootstrap/functional.php');

  $browser = new sfTestFunctional(new sfBrowser());

  $t = $browser->test();

  $d = sfContext::getInstance()->getEventDispatcher();

  $_GET = $get = array(
    'page_id' => 100,
    'sf_type' => 'GET',
    'user_id' => 82,
  );

  $r = new sfCacheTaggingWebRequest($d);

  $t->is($r->getGetParameters(), $get);
  $t->isa_ok($r->addGetParameters($custom = array('name' => 'Oliver', 'nick' => 'ola')), 'sfCacheTaggingWebRequest');
  $t->is($r->getGetParameters(), array_merge($get, $custom));

  $merged = array_merge($get, $custom);
  unset($merged['user_id']);
  $t->is($r->getFilteredGetParameters(), $merged);

  $r->deleteGetParameters();
  $t->is($r->getGetParameters(), array());
