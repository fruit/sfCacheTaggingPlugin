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

  $q = BlogPostTable::getInstance()->createQuery('p');
  $t->isa_ok($q->getResultCacheDriver(), 'Doctrine_Cache_Proxy', 'instance of Proxy');

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

  $q
    ->useResultCache()
    ->select('*')
    ->addWhere('id != ?', array(4))
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

  $q->clearResultCache();
  $tagging->clean();