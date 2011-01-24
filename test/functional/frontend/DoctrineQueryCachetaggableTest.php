<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */
  
  require_once realpath(dirname(__FILE__) . '/../../../../../test/bootstrap/functional.php');

  $browser = new sfTestFunctional(new sfBrowser());

  $t = $browser->test();

  $connection = Doctrine::getConnectionByTableName('BlogPost');
  $connection->beginTransaction();


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
  $q->from('BlogPost p')->useResultCache(new Doctrine_Cache_Proxy());

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







  $connection->rollback();