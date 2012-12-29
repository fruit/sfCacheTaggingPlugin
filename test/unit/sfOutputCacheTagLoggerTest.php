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

  # line separated log messages
  $l = new sfOutputCacheTagLogger(array('format' => '%char% %char_explanation% %key%%EOL%'));
  ob_start();
  $result = $l->log('X', 'X_TAG');
  $t->is($output = ob_get_clean(), "X Unregistered char X_TAG\n", "prints '{$output}'");
  $t->is($result, true, 'Result always is TRUE');

  # inline format
  $l = new sfOutputCacheTagLogger(array('format' => '%char%'));
  ob_start();
  $l->log('g', 'g letter');
  $l->log('h', 'h letter');
  $l->log('T', 'h letter');
  $t->is($output = ob_get_clean(), "ghT", "prints '{$output}'");



