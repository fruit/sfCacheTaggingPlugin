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

  $connection = Doctrine::getConnectionByTableName('BlogPost');
  $connection->beginTransaction();

  $cacheManager = sfContext::getInstance()->getViewCacheManager();

  $t = new lime_test();

  $connection = BlogPostTable::getInstance()->getConnection();
  $connection->beginTransaction();

  $posts = BlogPostTable::getInstance()->findAll();

  $t->ok(count($posts->getCacheTags()) > count($posts->delete()->getCacheTags()));

  $postComments = BlogPostCommentTable::getInstance()->findAll();
  $postComments->delete();

  $postTagKey = BlogPostTable::getInstance()->getClassnameToReturn();
  $postCommentTagKey = BlogPostCommentTable::getInstance()->getClassnameToReturn();

  $postCollectionTag = array("{$postTagKey}" => sfCacheTaggingToolkit::generateVersion(strtotime('today')));
  $postCommentCollectionTag = array("{$postCommentTagKey}" => sfCacheTaggingToolkit::generateVersion(strtotime('today')));

  $t->is(
    $posts->getCacheTags(),
    $postCollectionTag,
    'Doctrine_Collection returns 1 tag BlogPost as collection listener tag'
  );

  try
  {
    $posts->addCacheTag(array('MyTag'), 28182);
    $t->fail('Exception "InvalidArgumentException" was not thrown');
  }
  catch (InvalidArgumentException $e)
  {
    $t->pass(sprintf('Exception "%s" is thrown. (catched)', get_class($e)));
  }

  $posts->addCacheTag('SomeTag', sfCacheTaggingToolkit::generateVersion());
  $t->is(count($posts->getCacheTags()), 2, 'Adding new tag.');

  $posts->addCacheTag('SomeTag', sfCacheTaggingToolkit::generateVersion());
  $t->is(count($posts->getCacheTags()), 2, 'Adding tag with the same tag name "SomeTag".');
  $posts->addCacheTag('SomeTagNew', sfCacheTaggingToolkit::generateVersion());
  $t->is(count($posts->getCacheTags()), 3, 'Adding tag with new tag name "SomeTagNew".');

  $tagsToAdd = array();
  for ($i = 0; $i < 10; $i ++, usleep(rand(1, 40000)))
  {
    $tagsToAdd["{$postTagKey}_{$i}"] = sfCacheTaggingToolkit::generateVersion();
  }

  $tagsToReturn = array_merge($tagsToAdd, $postCollectionTag);

  $posts->removeCacheTags();
  $t->is($posts->getCacheTags(), $postCollectionTag, 'cleaned added tags');

  foreach (array('someTag', null, 30, 2.1293, new stdClass(), -2) as $mixed)
  {
    try
    {
      $posts->addCacheTags($mixed);
      $t->fail('Exception "InvalidArgumentException" was NOT thrown');
    }
    catch (InvalidArgumentException $e)
    {
      $t->pass($e->getMessage());
    }
  }

  $connection->rollback();


