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
  
  $sfViewCacheManager = sfContext::getInstance()->getViewCacheManager();

  $t = new lime_test();



  try
  {
    $p = new BlogPost();
    $p->save();

    sfCacheTaggingToolkit::formatTags($p);

    $t->pass('sfCacheTaggingToolkit::formatTags() works for Doctrine_Record with Tagging template');
  }
  catch (InvalidArgumentException $e)
  {
    $t->fail($e->getMessage());
  }

  try
  {
    $u = new University();
    $u->save();
    sfCacheTaggingToolkit::formatTags($u);

    $t->fail('sfCacheTaggingToolkit::formatTags() works for Doctrine_Record');
  } 
  catch (InvalidArgumentException $e)
  {
    $t->pass($e->getMessage());
  }

  BlogPostTable::getInstance()->findAll()->delete();
  UniversityTable::getInstance()->findAll()->delete();


  $cc = new sfCacheClearTask(sfContext::getInstance()->getEventDispatcher(), new sfFormatter());
  $cc->run();