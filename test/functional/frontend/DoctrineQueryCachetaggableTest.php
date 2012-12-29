<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2013 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  include_once realpath(dirname(__FILE__) . '/../../bootstrap/functional.php');

  $browser = new sfTestFunctional(new sfBrowser());

  $t = $browser->test();

  $sfContext = sfContext::getInstance();
  $cacheManager = $sfContext->getViewCacheManager();
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

  $q = new Doctrine_Query_Cachetaggable();

  try
  {
    $q->execute();
    $t->fail('no "from" parts');
  }
  catch (Doctrine_Query_Exception $e)
  {
    $t->pass($e->getMessage());
  }


  $q = new Doctrine_Query_Cachetaggable();
  $q->from('BlogPost p');

  $posts = $q->execute(array(), Doctrine::HYDRATE_RECORD);

  $t->is(count($posts), 3, 'Fetched 3 posts');


  $t->diag('Proxy cache');

  $q = new Doctrine_Query_Cachetaggable();
  $q->from('BlogPost p')->useResultCache(new Doctrine_Cache_Proxy(array(
    'cache' => $tagging,
  )));

  $posts = $q->execute(array(), Doctrine::HYDRATE_RECORD);

  $t->is(count($posts), 3, 'Fetched 3 posts');

  $key = $q->getResultCacheHash();

  $t->ok($q->getResultCacheDriver()->contains($key), 'Cached saved in proxy backend');

  $posts = $q->execute(array(), Doctrine::HYDRATE_RECORD); // fetch from cache

  $q->clearResultCache();


  $t->diag('Build-in  cache driver');

  $q = new Doctrine_Query_Cachetaggable();
  $q->from('BlogPost p')->useResultCache(new Doctrine_Cache_Array());

  $posts = $q->execute(array(), Doctrine::HYDRATE_ARRAY);

  $t->is(count($posts), 3, 'Fetched 3 posts');

  $key = $q->getResultCacheHash();

  $t->ok($q->getResultCacheDriver()->contains($key), 'Cached saved in proxy backend');

  $posts = $q->execute(array(), Doctrine::HYDRATE_ARRAY); // fetch from cache

  $q->clearResultCache();