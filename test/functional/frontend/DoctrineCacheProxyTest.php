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

  $t = new lime_test();

  $connection = Doctrine::getConnectionByTableName('BlogPost');

  $cacheManager = sfContext::getInstance()->getViewCacheManager();
  /* @var $cacheManager sfViewCacheTagManager */

  $q = BlogPostTable::getInstance()->createQuery('p');
  $t->isa_ok($q->getResultCacheDriver(), 'Doctrine_Cache_Proxy', 'instance of Proxy');

  $connection->beginTransaction();

  $q
    ->useResultCache()
    ->select('*')
    ->addWhere('id != ?', 4)
    ->leftJoin('p.BlogPostComment c')
    ->limit(5);
  
  $q->clearResultCache();

  $hash = $q->getResultCacheHash();

  $t->ok(! $q->getResultCacheDriver()->contains($hash), 'hash is new');

  $posts = $q->execute();

  $t->ok($q->getResultCacheDriver()->contains($hash), 'hash exists');

  $t->is(gettype($q->getResultCacheDriver()->fetch($hash)), 'string', 'cache seamse to be ok');

  $post = $posts->getFirst();

  $post->delete();

  $t->ok(! $q->getResultCacheDriver()->contains($hash), 'cache invalidated');

  $posts = $q->execute();

  $t->ok($q->getResultCacheDriver()->contains($hash), 'cache updated');

  $q->getResultCacheDriver()->delete($hash);

  $t->ok(! $q->getResultCacheDriver()->contains($hash), 'cache removed');

  # _ getCacheKeys

  $posts = $q->execute();

  $hash = $q->getResultCacheHash();

  $t->ok($q->getResultCacheDriver()->contains($hash), 'cache exists');

  $q->getResultCacheDriver()->deleteAll();
  
  $t->ok(! $q->getResultCacheDriver()->contains($hash), 'cache removed');

  $t->is(
    $q->getResultCacheDriver()->save(md5('key'), serialize(array(1, 3, 5)), 291),
    true
  );

  $connection->rollback();
  
  $q->clearResultCache();
  
  
  # with sfCacheDisabledException

  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', false);

  $q = BlogPostTable::getInstance()->createQuery('p');

  $connection->beginTransaction();
  $q
    ->useResultCache()
    ->select('*')
    ->addWhere('id != ?', 4)
    ->leftJoin('p.BlogPostComment c')
    ->limit(5);

  $q->clearResultCache();

  $hash = $q->getResultCacheHash();

  $t->ok(! $q->getResultCacheDriver()->contains($hash), 'hash is new');

  $posts = $q->execute();

  $t->is($q->getResultCacheDriver()->contains($hash), false);

  $t->is($q->getResultCacheDriver()->fetch($hash), false);

  $post = $posts->getFirst();

  $post->delete();

  $t->is($q->getResultCacheDriver()->contains($hash), false);

  $posts = $q->execute();

  $t->is($q->getResultCacheDriver()->contains($hash), false);

  $t->is($q->getResultCacheDriver()->delete($hash), false);

  $t->is($q->getResultCacheDriver()->contains($hash), false);

  # _ getCacheKeys
  $posts = $q->execute();

  $hash = $q->getResultCacheHash();

  $t->ok(! $q->getResultCacheDriver()->contains($hash), 'cache not saved');

  $q->getResultCacheDriver()->deleteAll();

  $t->ok(! $q->getResultCacheDriver()->contains($hash), 'cache removed');

  $t->is(
    $q->getResultCacheDriver()->save(md5('key'), serialize(array(1, 3, 5)), 291),
    false
  );

  $connection->rollback();
  
  sfConfig::set('sf_cache', $optionSfCache);

  $q->clearResultCache();



  
