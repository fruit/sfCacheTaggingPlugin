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

  $sfContext = sfContext::getInstance();

  $sfContext->getConfiguration()->loadHelpers(array('Partial'));

  $output = get_partial('blog_post/partial_example', array(
    'sf_cache_tags' => array(
      'A'   => 1,
      'A:1' => 19,
      'A:2' => 43,
    ),
  ));

  $t->is($output, '<p>This is partial content</p>', 'Content matches');

  $cacheManager = $sfContext->getViewCacheManager();

  $r = $cacheManager->getPartialCache(
    'blog_post', '_partial_example', '40cd750bba9870f18aada2478b24840a'
  );

  $t->is($r, '<p>This is partial content</p>', 'Content matches');

  $taggingCache = $cacheManager->getTaggingCache();

  $taggingCache->setTag('A', 2);

  $r = $cacheManager->getPartialCache(
    'blog_post', '_partial_example', '40cd750bba9870f18aada2478b24840a'
  );

  $t->is($r, null, 'Invalidated cache - empty (NULL)');


  $output = get_partial('blog_post/partial_example', array(
    'sf_cache_tags' => array(
      'A'   => 1,
      'A:1' => 19,
      'A:2' => 43,
    ),
  ));


  $cache = $cacheManager->getCache()->get(
    '/all/sf_cache_partial/blog_post/__partial_example/sf_cache_key/40cd750bba9870f18aada2478b24840a'
  );

  $t->is(count($cache['tags']), 3, '3 Tags saved with partial cache content');

  $output = get_component('blog_post', 'componentExample', array(
    'sf_cache_key' => 'my-super-key',
    'sf_cache_tags' => array(
      'B'   => 12812,
      'B:1' => 12592,
    ),
  ));

  $cache = $cacheManager->getCache()->get(
    '/all/sf_cache_partial/blog_post/__componentExample/sf_cache_key/my-super-key'
  );

  $t->is(count($cache['tags']), 2, '2 Tags saved with component cache content');


  $output = get_partial('blog_post/level_1_partial', array(
    'sf_cache_tags' => array(
      'T:1' => 1, 'T:2' => 11, 'T:3' => 111, 'T:4' => 1111,
    ),
  ));

  $levels = array();

  $levels[] = array(
    'key' => '/all/sf_cache_partial/blog_post/__level_1_partial/sf_cache_key/40cd750bba9870f18aada2478b24840a',
    'count' => 4,
    'content' => "Level 1 partial,Level 1.1 partial,Level 1.2 partial,Level 1.2.1 partial,"
  );

  $levels[] = array(
    'key' => '/all/sf_cache_partial/blog_post/__level_1_1_partial/sf_cache_key/40cd750bba9870f18aada2478b24840a',
    'count' => 2,
    'content' => "Level 1.1 partial,"
  );

  $levels[] = array(
    'key' => '/all/sf_cache_partial/blog_post/__level_1_2_partial/sf_cache_key/40cd750bba9870f18aada2478b24840a',
    'count' => 4,
    'content' => "Level 1.2 partial,Level 1.2.1 partial,"
  );

  $levels[] = array(
    'key' => '/all/sf_cache_partial/blog_post/__level_1_2_1_partial/sf_cache_key/40cd750bba9870f18aada2478b24840a',
    'count' => 3,
    'content' => "Level 1.2.1 partial,"
  );

  foreach ($levels as $level)
  {
    list($key, $count, $content) = array_values($level);

    $cache = $cacheManager->getCache()->get($key);

    $t->is($cache['data']['content'], $content, 'Content matches');
    $t->is(count($cache['tags']), $count, "{$count} Tags saved with inherited partial cache content");
  }
