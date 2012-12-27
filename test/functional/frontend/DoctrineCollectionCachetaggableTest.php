<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2012 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  include_once realpath(dirname(__FILE__) . '/../../bootstrap/functional.php');
  include_once sfConfig::get('sf_symfony_lib_dir') . '/vendor/lime/lime.php';

  $sfContext = sfContext::getInstance();
  $cacheManager = $sfContext->getViewCacheManager();
  $tagging = $cacheManager->getTaggingCache();

  $t = new lime_test();

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

  # getCacheTags

  $c = new Doctrine_Collection_Cachetaggable('BlogPost');
  $t->is(count($c->getCacheTags()), 1);
  $t->is(count($c->getCacheTags(true)), 1);

  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', false);

  $t->is($c->getCacheTags(), array(), 'cache is disabled, return empty array()');

  sfConfig::set('sf_cache', $optionSfCache);

  $posts = BlogPostTable::getInstance()->findAll();

  $tags = $posts->getCacheTags();

  $t->is(count($posts->getCacheTags()), 4);
  $t->is(count($posts->getCacheTags(true)), 4);

  $firstPost = $posts->getFirst();

  $collectionTagVersion = $tags[sfCacheTaggingToolkit::obtainCollectionName($posts->getTable())];

  $firstPost->setTitle('new title');
  $firstPost->save();

  $freshTags = $posts->getCacheTags();

  $t->is(count($tags), count($freshTags));

  $freshCollectionVersion = $freshTags[sfCacheTaggingToolkit::obtainCollectionName($posts->getTable())];

  $t->ok(isset($freshTags[sfCacheTaggingToolkit::obtainCollectionName($posts->getTable())]));

  $t->cmp_ok($collectionTagVersion, '=', $freshCollectionVersion);

  $c = new Doctrine_Collection_Cachetaggable('University');

  try
  {
    $c->getCacheTags();
    $t->fail();
  }
  catch (sfConfigurationException $e)
  {
    $t->pass($e->getMessage());
  }

  # addCacheTags

  $c = new Doctrine_Collection_Cachetaggable('University');

  $t->is($c->addCacheTags(array('Tag_1' => 123712738123, 'Tag_3' => 12939123912)), true);

  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', false);

  $t->is($c->addCacheTags(array('Tag_1' => 123712738123, 'Tag_3' => 12939123912)), false);

  sfConfig::set('sf_cache', $optionSfCache);


  # addCacheTag

  $c = new Doctrine_Collection_Cachetaggable('University');

  $t->is($c->addCacheTag('Tag_1', 123712738123), true);

  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', false);

  $t->is($c->addCacheTag('Tag_1', 123712738123), false);

  sfConfig::set('sf_cache', $optionSfCache);

  # removeCacheTags

  $posts = BlogPostTable::getInstance()->findAll();

  $t->is(count($posts->getCacheTags()), 4);

  $t->ok($posts->removeCacheTags());

  $t->is(count($posts->getCacheTags()), 4);

  $t->is($posts->addCacheTags(array('Tag_1' => 123712738123, 'Tag_3' => 12939123912)), true);

  $t->is(count($posts->getCacheTags()), 6);

  $t->ok($posts->removeCacheTags());

  $t->is(count($posts->getCacheTags()), 4);

  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', false);

  $t->ok(! $posts->removeCacheTags());

  sfConfig::set('sf_cache', $optionSfCache);
  $posts->free(true);

  $posts = BlogPostTable::getInstance()->findAll();

  $posts->addCacheTags(array('Tag_1' => 123712738123, 'Tag_3' => 12939123912));

  $t->is(count($posts->getCacheTags()), 6);

  $posts->delete();

  # delete removes added by hand tags too
  $t->is(count($posts->getCacheTags()), 1); // collection version tag