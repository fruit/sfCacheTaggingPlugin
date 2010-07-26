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

  $cc = new sfCacheClearTask(sfContext::getInstance()->getEventDispatcher(), new sfFormatter());
  $cc->run();

  $sfContext = sfContext::getInstance();

  $cacheManager = $sfContext->getViewCacheManager();

  $t = new lime_test();

  $lnr = new Doctrine_Template_Listener_Cachetaggable(array());

  $connection = BlogPostTable::getInstance()->getConnection();

  $connection->beginTransaction();

  try
  {
    $p = new BlogPost();
    $p->setTitle('no-cached');
    $p->save();

    print $p->getTagName() . ' / ' . $p->getObjectVersion() . "\n";

    $t->pass('no exception throw with disabled sf_cache');
  }
  catch (Exception $e)
  {
    $t->fail($e->getMessage());
  }

  $connection->rollback();
