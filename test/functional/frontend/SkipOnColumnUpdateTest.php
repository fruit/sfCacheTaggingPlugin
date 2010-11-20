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
  $connection->beginTransaction();

  $cc = new sfCacheClearTask(sfContext::getInstance()->getEventDispatcher(), new sfFormatter());
  $cc->run();

  $sfContext = sfContext::getInstance();
  $cacheManager = $sfContext->getViewCacheManager();

  $sfTagger = $cacheManager->getTaggingCache();

  $t = new lime_test();

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
