<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2013 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  include_once realpath(dirname(__FILE__) . '/../../bootstrap/functional.php');
  include_once sfConfig::get('sf_symfony_lib_dir') . '/vendor/lime/lime.php';

  $t = new lime_test();

  $sfContext = sfContext::getInstance();
  $cm = $sfContext->getViewCacheManager();
  /* @var $cm sfViewCacheTagManager */
  $tagging = $cm->getTaggingCache();

  $con = Doctrine_Manager::getInstance()->getCurrentConnection();
  $cleanQuery = "SET FOREIGN_KEY_CHECKS = 0; TRUNCATE `skip_on_column_update_test`; SET FOREIGN_KEY_CHECKS = 1;";
  $con->exec($cleanQuery);
  $tagging->clean();


  $obj = new SkipOnColumnUpdateTest();
  $obj->setName('Flower shop');
  $obj->save();

  $v = $obj->obtainObjectVersion();

  $obj->setAuthor('Stefano Alfano');
  $obj->save();

  $t->is($obj->obtainObjectVersion(), $v, 'Version not changed');

  $obj->setName('Toys shop');
  $obj->save();

  $t->isnt($obj->obtainObjectVersion(), $v, 'Version changed');

  $v = $obj->obtainObjectVersion();

  $obj->setAuthor('Dustin Latimer');
  $obj->setName('Skate shop');

  $obj->save();
  $t->isnt($obj->obtainObjectVersion(), $v, 'Version changed');

  $con->exec($cleanQuery);
  $tagging->clean();

  $obj1 = new SkipOnColumnUpdateTest();
  $obj1->setAuthor('Dustin Latimer');
  $obj1->setName('Skate shop');
  $obj1->setCount(2);
  $obj1->save();

  $objectVersion1 = $obj1->obtainObjectVersion();

  $obj2 = new SkipOnColumnUpdateTest();
  $obj2->setAuthor('Stefano Alfano');
  $obj2->setName('Magazine shop');
  $obj2->setCount(1);
  $obj2->save();

  $objectVersion2 = $obj2->obtainObjectVersion();

  $obj3 = new SkipOnColumnUpdateTest();
  $obj3->setAuthor('Brain Shime');
  $obj3->setName('T-Shirts shop');
  $obj3->setCount(4);
  $obj3->save();

  $objectVersion3 = $obj3->obtainObjectVersion();

  $table = Doctrine::getTable('SkipOnColumnUpdateTest');

  $c = $table
    ->createQuery()
    ->update()
    ->set('count', '?', 11)
    ->where('count > 1')
    ->execute();

  $t->is($c, 2, '2 row updated');

  $obj1 = $table->find($obj1->getId());
  $obj2 = $table->find($obj2->getId());
  $obj3 = $table->find($obj3->getId());

  $t->cmp_ok($obj1->obtainObjectVersion(), '=', $objectVersion1, "[1] Version NOT invalidated, skipped");
  $t->cmp_ok($obj2->obtainObjectVersion(), '=', $objectVersion2, "[2] Version NOT invalidated due to DQL expression");
  $t->cmp_ok($obj3->obtainObjectVersion(), '=', $objectVersion3, "[3] Version NOT invalidated, skipped");

  $c = $table
    ->createQuery()
    ->update()
    ->set('author', '?', 'Anonym')
    ->set('count', '?', '100')
    ->execute();

  $t->is($c, 3, '3 rows updated');

  $obj1 = $table->find($obj1->getId());
  $obj2 = $table->find($obj2->getId());
  $obj3 = $table->find($obj3->getId());

  $t->cmp_ok($obj1->obtainObjectVersion(), '=', $objectVersion1, "[1] Version NOT invalidated, skipped");
  $t->cmp_ok($obj2->obtainObjectVersion(), '=', $objectVersion2, "[2] Version NOT invalidated, skipped");
  $t->cmp_ok($obj3->obtainObjectVersion(), '=', $objectVersion3, "[3] Version NOT invalidated, skipped");

  $c = $table
    ->createQuery()
    ->update()
    ->set('name', '?', 'Guest')
    ->where('id != ?', 0)
    ->execute();

  $t->is($c, 3, '3 rows updated');

  $obj1 = $table->find($obj1->getId());
  $obj2 = $table->find($obj2->getId());
  $obj3 = $table->find($obj3->getId());

  $t->cmp_ok($objectVersion1, '<', $obj1->obtainObjectVersion(),  "[1] Version is invalidated");
  $t->cmp_ok($objectVersion2, '<', $obj2->obtainObjectVersion(),  "[2] Version is invalidated");
  $t->cmp_ok($objectVersion3, '<', $obj3->obtainObjectVersion(),  "[3] Version is invalidated");

  $con->exec($cleanQuery);
  $tagging->clean();
