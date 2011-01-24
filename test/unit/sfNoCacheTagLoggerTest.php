<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  require_once realpath(dirname(__FILE__) . '/../../../../test/bootstrap/unit.php');

  $t = new lime_test();

  $l = new sfNoCacheTagLogger(array());

  $t->is($l->getOptions(), array());

  $l->initialize(array('some_option' => '1111'));

  $t->is($l->getOptions(), array());

  $l->setOption('some_option', 213213213);
  $t->is($l->getOptions(), array());

  $t->is($l->getOption('some_option'), null);
  $t->is($l->getOption('some_option', true), true);

  $t->is($l->log('X', 'X_TAG'), true);




  