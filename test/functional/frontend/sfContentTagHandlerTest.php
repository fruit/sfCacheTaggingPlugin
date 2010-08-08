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

  $connection = Doctrine::getConnectionByTableName('BlogPost');
  $connection->beginTransaction();

  $cacheManager = sfContext::getInstance()->getViewCacheManager();

  $t = new lime_test();

  $connection = BlogPostTable::getInstance()->getConnection();
  $connection->beginTransaction();
  
  $posts = BlogPostTable::getInstance()->findAll();

  $t->ok(count($posts->getTags() > count($posts->delete()->getTags())));

  $postComments = BlogPostCommentTable::getInstance()->findAll();
  $postComments->delete();

  $postTagKey = BlogPostTable::getInstance()->getClassnameToReturn();
  $postCommentTagKey = BlogPostCommentTable::getInstance()->getClassnameToReturn();

  $postCollectionTag = array("{$postTagKey}"   => sfCacheTaggingToolkit::generateVersion(strtotime('today')));
  $postCommentCollectionTag = array("{$postCommentTagKey}"   => sfCacheTaggingToolkit::generateVersion(strtotime('today')));

  $t->is(
    $posts->getTags(),
    $postCollectionTag,
    'Doctrine_Collection returns 1 tag BlogPost as collection listener tag'
  );

  try
  {
    $posts->addTag(array('MyTag'), 28182);
    $t->fail('Exception "InvalidArgumentException" was not thrown');
  }
  catch (InvalidArgumentException $e)
  {
    $t->pass(sprintf('Exception "%s" is thrown. (catched)', get_class($e)));
  }

  $posts->addTag('SomeTag', sfCacheTaggingToolkit::generateVersion());
  $t->is(count($posts->getTags()), 2, 'Adding new tag.');

  $posts->addTag('SomeTag', sfCacheTaggingToolkit::generateVersion());
  $t->is(count($posts->getTags()), 2, 'Adding tag with the same tag name "SomeTag".');
  $posts->addTag('SomeTagNew', sfCacheTaggingToolkit::generateVersion());
  $t->is(count($posts->getTags()), 3, 'Adding tag with new tag name "SomeTagNew".');

  $tagsToAdd = array();
  for ($i = 0; $i < 10; $i ++, usleep(rand(1, 40000)))
  {
    $tagsToAdd["{$postTagKey}_{$i}"] = sfCacheTaggingToolkit::generateVersion();
  }

  $tagsToReturn = array_merge($tagsToAdd, $postCollectionTag);

  $posts->removeTags();
  $t->is($posts->getTags(), $postCollectionTag, 'cleaned added tags');

  foreach (array('someTag', null, 30, 2.1293, new stdClass(), -2) as $mixed)
  {
    try
    {
      $posts->addTags($mixed);
      $t->fail('Exception "InvalidArgumentException" was NOT thrown');
    }
    catch (InvalidArgumentException $e)
    {
      $t->pass($e->getMessage());
    }
  }

  $connection->rollback();
  
  