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

  $t->is($output, '<p>This is partial content</p>');

  $cacheManager = $sfContext->getViewCacheManager();

  $r = $cacheManager->getPartialCache(
    'blog_post', '_partial_example', '40cd750bba9870f18aada2478b24840a'
  );

  $t->is($r, '<p>This is partial content</p>');
  
  $taggingCache = $cacheManager->getTaggingCache();

  $taggingCache->setTag('A', 2);

  $r = $cacheManager->getPartialCache(
    'blog_post', '_partial_example', '40cd750bba9870f18aada2478b24840a'
  );

  $t->is($r, null);
  