<?php
  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  require_once realpath(dirname(__FILE__) . '/../../../../../test/bootstrap/functional.php');
  require_once sfConfig::get('sf_symfony_lib_dir') . '/vendor/lime/lime.php';

  $t = new lime_test();

  $con = Doctrine_Manager::getInstance()->getConnection('doctrine');
  $con->beginTransaction();

  try
  {
    $con->beginTransaction();
    $culture = RelCultureTable::getInstance()->find(1);
    $culture->link('Sites', array(2));
    $culture->save();

    $t->pass('link() works fine, when calling with disabled cache');
  }
  catch (Exception $e)
  {
    $t->fail('link() with "cache=off" Catching ' . $e->getMessage());
  }

  $con->rollback();



  # unlink

  try
  {
    $con->beginTransaction();
    $culture = RelCultureTable::getInstance()->find(1);
    $culture->unlink('Sites', array(2));
    $culture->save();

    $t->pass('unlink() works fine, shen calling with disabled cache');
  }
  catch (Exception $e)
  {
    $t->fail('unlink() with "cache=off" Catching ' . $e->getMessage());
  }

  $con->rollback();