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

  $cacheManager = sfContext::getInstance()->getViewCacheManager();
  /* @var $cacheManager sfViewCacheTagManager */

  $tagging = $cacheManager->getTaggingCache();
  $con = Doctrine_Manager::getInstance()->getCurrentConnection();


  $con->beginTransaction();
  $truncateQuery = array_reduce(
    array('blog_post','blog_post_comment','blog_post_vote','blog_post_translation'),
    function ($return, $val) { return "{$return} TRUNCATE {$val};"; }, ''
  );

  $cleanQuery = "SET FOREIGN_KEY_CHECKS = 0; {$truncateQuery}; SET FOREIGN_KEY_CHECKS = 1;";
  $con->exec($cleanQuery);
  Doctrine::loadData(sfConfig::get('sf_data_dir') .'/fixtures/blog_post.yml');
  $con->commit();

  $tagging->clean();

  $posts = BlogPostTable::getInstance()->findAll();

  $t->ok(count($posts->getCacheTags()) > count($posts->delete()->getCacheTags()));

  $postComments = BlogPostCommentTable::getInstance()->findAll();
  $postComments->delete();
  $tagging->clean();

  $version = sfCacheTaggingToolkit::generateVersion();

  $t1 = $posts->getCacheTags();

  $t->is(key($t1), sfCacheTaggingToolkit::obtainCollectionName(BlogPostTable::getInstance()));
  $t->cmp_ok($version, '<', current($t1));
  $t2 = $posts->getCacheTags();
  $t->cmp_ok($version, '<', current($t2));
  $t->is(key($t2), sfCacheTaggingToolkit::obtainCollectionName(BlogPostTable::getInstance()));
  $t->cmp_ok(current($t1), '===', current($t2));

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

  $posts->removeCacheTags();
  $t->is($posts->getCacheTags(), array(
    sfCacheTaggingToolkit::obtainCollectionName(BlogPostTable::getInstance()) =>
    current($t1)
  ), 'cleaned added tags');

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