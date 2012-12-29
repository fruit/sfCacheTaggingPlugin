<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2013 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  include_once realpath(dirname(__FILE__) . '/../bootstrap/unit.php');

  $t = new lime_test();


  $c = new sfNoTaggingCache(array());

  $c->initialize(array());

  $t->is($c->getCacheKeys(), array());
  $t->ok($c instanceof sfNoCache);
  $t->ok($c instanceof sfTaggingCacheInterface);
