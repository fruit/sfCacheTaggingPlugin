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

  $t = new lime_test();

  $sfContext = sfContext::getInstance();
  $cm = $sfContext->getViewCacheManager();
  /* @var $cm sfViewCacheTagManager */
  $tagging = $cm->getTaggingCache();

  $con = Doctrine_Manager::getInstance()->getCurrentConnection();
  $con->beginTransaction();
  $cleanQuery = "SET FOREIGN_KEY_CHECKS = 0; TRUNCATE `blog_post`; SET FOREIGN_KEY_CHECKS = 1;";
  $con->exec($cleanQuery);
//  Doctrine::loadData(sfConfig::get('sf_data_dir') .'/fixtures/blog_post.yml');
  $con->commit();
  $tagging->clean();

  $post = new BlogPost();
  $post->setSlug('new-post');
  $post->setTitle('New article written by myself');
  $post->save();

  $preVersion = $post->obtainObjectVersion();

  $post->setContent('new value to blog_post_translation table');
  $post->save();

  $t->cmp_ok($preVersion, '<', $post->obtainObjectVersion(), 'BlogPost record tag name invalidated');

  $post = BlogPostTable::getInstance()->findOneBySlug('new-post');
  $preVersion = $post->obtainObjectVersion();
  $post->save();

  $t->cmp_ok($preVersion, '=', $post->obtainObjectVersion(), 'No new tags, nothing to cache');