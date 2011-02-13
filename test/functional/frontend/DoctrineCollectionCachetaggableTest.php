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

  # getTags

  $c = new Doctrine_Collection_Cachetaggable('BlogPost');
  $t->is(count($c->getTags()), 1);
  $t->is(count($c->getTags(true)), 1);

  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', false);

  $t->is($c->getTags(), array(), 'cache is disabled, return empty array()');
  
  sfConfig::set('sf_cache', $optionSfCache);

  $posts = BlogPostTable::getInstance()->findAll();

  $tags = $posts->getTags();

  $t->is(count($posts->getTags()), 4);
  $t->is(count($posts->getTags(true)), 4);

  $firstPost = $posts->getFirst();

  $collectionTagVersion = $tags[$posts->getTable()->getClassNameToReturn()];

  $firstPost->setTitle('new title');
  $firstPost->save();

  $freshTags = $posts->getTags();

  $t->is(count($tags), count($freshTags));

  $freshCollectionVersion = $freshTags[$posts->getTable()->getClassNameToReturn()];

  $t->ok(isset($freshTags[$posts->getTable()->getClassNameToReturn()]));

  $t->cmp_ok($collectionTagVersion, '<', $freshCollectionVersion);

  $c = new Doctrine_Collection_Cachetaggable('University');

  try
  {
    $c->getTags();
    $t->fail();
  }
  catch (sfConfigurationException $e)
  {
    $t->pass($e->getMessage());
  }

  # addVersionTags

  $c = new Doctrine_Collection_Cachetaggable('University');

  $t->is($c->addVersionTags(array('Tag_1' => 123712738123, 'Tag_3' => 12939123912)), true);

  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', false);

  $t->is($c->addVersionTags(array('Tag_1' => 123712738123, 'Tag_3' => 12939123912)), false);

  sfConfig::set('sf_cache', $optionSfCache);
  

  # addVersionTag

  $c = new Doctrine_Collection_Cachetaggable('University');

  $t->is($c->addVersionTag('Tag_1', 123712738123), true);

  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', false);

  $t->is($c->addVersionTag('Tag_1', 123712738123), false);

  sfConfig::set('sf_cache', $optionSfCache);

  # removeVersionTags

  $posts = BlogPostTable::getInstance()->findAll();

  $t->is(count($posts->getTags()), 4);

  $t->ok($posts->removeVersionTags());

  $t->is(count($posts->getTags()), 4);

  $t->is($posts->addVersionTags(array('Tag_1' => 123712738123, 'Tag_3' => 12939123912)), true);

  $t->is(count($posts->getTags()), 6);

  $t->ok($posts->removeVersionTags());

  $t->is(count($posts->getTags()), 4);

  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', false);

  $t->ok(! $posts->removeVersionTags());

  $connection->rollback();

  sfConfig::set('sf_cache', $optionSfCache);


  $connection = Doctrine::getConnectionByTableName('BlogPost');
  $connection->beginTransaction();

  $posts = BlogPostTable::getInstance()->findAll();

  $posts->addVersionTags(array('Tag_1' => 123712738123, 'Tag_3' => 12939123912));

  $t->is(count($posts->getTags()), 6);

  $posts->delete();

  # delete removes added by hand tags too
  $t->is(count($posts->getTags()), 1); // collection version tag

  $connection->rollback();