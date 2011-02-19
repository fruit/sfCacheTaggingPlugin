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

  $connection = BlogPostTable::getInstance()->getConnection();
  $connection->beginTransaction();

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

  $connection->rollback();