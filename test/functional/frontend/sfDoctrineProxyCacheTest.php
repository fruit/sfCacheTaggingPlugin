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

  $t = new lime_test();

  $connection = Doctrine::getConnectionByTableName('BlogPost');
  $connection->beginTransaction();

  $cacheManager = sfContext::getInstance()->getViewCacheManager();
  /* @var $cacheManager sfViewCacheTagManager */

  $q = BlogPostTable::getInstance()->createQuery('p');
  $t->isa_ok($q->getResultCacheDriver(), 'sfDoctrineProxyCache', 'instance of Proxy');

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

  $connection->rollback();