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

  $connection = Doctrine::getConnectionByTableName('SkipOnColumnUpdateTest');
  

  $cc = new sfCacheClearTask(sfContext::getInstance()->getEventDispatcher(), new sfFormatter());
  $cc->run();

  $sfContext = sfContext::getInstance();
  $cacheManager = $sfContext->getViewCacheManager();

  $sfTagger = $cacheManager->getTaggingCache();

  $t = new lime_test();

  $connection->beginTransaction();

  $obj = new SkipOnColumnUpdateTest();
  $obj->setName('Flower shop');
  $obj->save();

  $v = $obj->getObjectVersion();

  $obj->setAuthor('Stefano Alfano');
  $obj->save();

  $t->is($obj->getObjectVersion(), $v, 'Version not changed');

  $obj->setName('Toys shop');
  $obj->save();

  $t->isnt($obj->getObjectVersion(), $v, 'Version changed');

  $v = $obj->getObjectVersion();

  $obj->setAuthor('Dustin Latimer');
  $obj->setName('Skate shop');

  $obj->save();
  $t->isnt($obj->getObjectVersion(), $v, 'Version changed');

  $connection->rollback();

  $connection->beginTransaction();

  $obj1 = new SkipOnColumnUpdateTest();
  $obj1->setAuthor('Dustin Latimer');
  $obj1->setName('Skate shop');
  $obj1->setCount(2);
  $obj1->save();

  $objectVersion1 = $obj1->getObjectVersion();

  $obj2 = new SkipOnColumnUpdateTest();
  $obj2->setAuthor('Stefano Alfano');
  $obj2->setName('Magazine shop');
  $obj2->setCount(1);
  $obj2->save();

  $objectVersion2 = $obj2->getObjectVersion();

  $obj3 = new SkipOnColumnUpdateTest();
  $obj3->setAuthor('Brain Shime');
  $obj3->setName('T-Shirts shop');
  $obj3->setCount(4);
  $obj3->save();

  $objectVersion3 = $obj3->getObjectVersion();

  $table = Doctrine::getTable('SkipOnColumnUpdateTest');

  $c = $table
    ->createQuery()
    ->update()
    ->set('count', '?', 11)
    ->where('id = ?', $obj2->getId())
    ->execute();

  $t->is($c, 1, '1 row updated');

  $obj1 = $table->find($obj1->getId());
  $obj2 = $table->find($obj2->getId());
  $obj3 = $table->find($obj3->getId());

  $t->is($obj1->getObjectVersion(), $objectVersion1, "Version '{$objectVersion1}' NOT invalidated");
  $t->isnt($obj2->getObjectVersion(), $objectVersion2, "Version '{$objectVersion2}' invalidated");
  $t->is($obj3->getObjectVersion(), $objectVersion3, "Version '{$objectVersion3}' NOT invalidated");

  $objectVersion1 = $obj1->getObjectVersion();
  $objectVersion2 = $obj2->getObjectVersion();
  $objectVersion3 = $obj3->getObjectVersion();

  $c = $table
    ->createQuery()
    ->update()
    ->set('author', '?', 'Anonym')
    ->where('id != ?', $obj2->getId())
    ->execute();

  $t->is($c, 2, '2 rows updated');

  $obj1 = $table->find($obj1->getId());
  $obj2 = $table->find($obj2->getId());
  $obj3 = $table->find($obj3->getId());

  $t->isnt($obj1->getObjectVersion(), $objectVersion1, "Version '{$objectVersion1}' invalidated");
  $t->is($obj2->getObjectVersion(), $objectVersion2, "Version '{$objectVersion2}' NOT invalidated");
  $t->isnt($obj3->getObjectVersion(), $objectVersion3, "Version '{$objectVersion3}' invalidated");

  $objectVersion1 = $obj1->getObjectVersion();
  $objectVersion2 = $obj2->getObjectVersion();
  $objectVersion3 = $obj3->getObjectVersion();

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

  $t->is($obj1->getObjectVersion(), $objectVersion1, "Version '{$objectVersion1}' NOT invalidated");
  $t->is($obj2->getObjectVersion(), $objectVersion2, "Version '{$objectVersion2}' NOT invalidated");
  $t->is($obj3->getObjectVersion(), $objectVersion3, "Version '{$objectVersion3}' NOT invalidated");

  $connection->rollback();