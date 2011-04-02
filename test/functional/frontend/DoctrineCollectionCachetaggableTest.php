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

  $sfContext = sfContext::getInstance();
  $cacheManager = $sfContext->getViewCacheManager();

  $sfTagger = $cacheManager->getTaggingCache();

  $t = new lime_test();

  $sfTagger->clean();

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

  $collectionTagVersion = $tags[$posts->getTable()->getClassNameToReturn()];

  $firstPost->setTitle('new title');
  $firstPost->save();

  $freshTags = $posts->getCacheTags();

  $t->is(count($tags), count($freshTags));

  $freshCollectionVersion = $freshTags[$posts->getTable()->getClassNameToReturn()];

  $t->ok(isset($freshTags[$posts->getTable()->getClassNameToReturn()]));

  $t->cmp_ok($collectionTagVersion, '<', $freshCollectionVersion);

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

  $connection->rollback();

  sfConfig::set('sf_cache', $optionSfCache);


  $connection = Doctrine::getConnectionByTableName('BlogPost');
  $connection->beginTransaction();

  $posts = BlogPostTable::getInstance()->findAll();

  $posts->addCacheTags(array('Tag_1' => 123712738123, 'Tag_3' => 12939123912));

  $t->is(count($posts->getCacheTags()), 6);

  $posts->delete();

  # delete removes added by hand tags too
  $t->is(count($posts->getCacheTags()), 1); // collection version tag

  $connection->rollback();