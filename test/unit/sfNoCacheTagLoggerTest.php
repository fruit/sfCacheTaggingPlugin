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

  $l = new sfNoCacheTagLogger(array());

  ob_start();
  $result = $l->log('X', 'X_TAG');

  $output = ob_get_clean();
  $t->is($output, '');
  $t->is($result, true);




