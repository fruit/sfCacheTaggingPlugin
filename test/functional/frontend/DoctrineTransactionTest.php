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

//  $sfContext = sfContext::getInstance();
//  $cacheManager = $sfContext->getViewCacheManager();

  $t = new lime_test();

  $sfContext = sfContext::getInstance();
  $cm = $sfContext->getViewCacheManager();
  /* @var $cm sfViewCacheTagManager */
  $tagging = $cm->getTaggingCache();

  $con = Doctrine_Manager::getInstance()->getCurrentConnection();
  $con->beginTransaction();
  $truncateQuery = array_reduce(
    array('book','blog_post','blog_post_comment', 'post_vote', 'blog_post_vote','blog_post_translation'),
    function ($return, $val) { return "{$return} TRUNCATE {$val};"; }, ''
  );

  $cleanQuery = "SET FOREIGN_KEY_CHECKS = 0; {$truncateQuery}; SET FOREIGN_KEY_CHECKS = 1;";
  $con->exec($cleanQuery);
//  Doctrine::loadData(sfConfig::get('sf_data_dir') .'/fixtures/blog_post.yml');
  $con->commit();
  $tagging->clean();



  $t->diag('Simple save() operation inside transaction "commit"');

  $con->beginTransaction();
  $tags = array();
  try
  {
    $howto1 = new BlogPost();
    $howto1->setTitle(sprintf('A How to read the new words in foreign language? (Part #%d)', rand(1, 99999)));
    $howto1->save($con);
    $tags[] = $howto1->obtainTagName();

    $howto2 = new BlogPost();
    $howto2->setTitle(sprintf('A How to dissaseble door lock? (Part #%d)', rand(1, 99999)));
    $howto2->save($con);
    $tags[] = $howto2->obtainTagName();

    $howto3 = new BlogPost();
    $howto3->setTitle(sprintf('A How to wash keyboard? (Part #%d)', rand(1, 99999)));
    $howto3->save($con);
    $tags[] = $howto3->obtainTagName();

    foreach ($tags as $tagName) $t->ok( ! $tagging->hasTag($tagName));

    $t->ok( ! $tagging->hasTag(sfCacheTaggingToolkit::obtainCollectionName(BlogPostTable::getInstance())));
    $con->commit(); // save tags here

    foreach ($tags as $tagName) $t->ok($tagging->hasTag($tagName));

    $t->ok($tagging->hasTag(sfCacheTaggingToolkit::obtainCollectionName(BlogPostTable::getInstance())));
  }
  catch (Exception $e)
  {
    $con->rollback();
    $t->fail("Exception thrown {$e}");
  }
  $howto1->delete();
  $howto2->delete();
  $howto3->delete();
  $tagging->clean();

  $t->diag('Simple save() operation inside transaction "rollback"');

  $con->beginTransaction();
  $tags = array();
  try
  {
    $howto1 = new BlogPost();
    $howto1->setTitle(sprintf('B How to read the new words in foreign language? (Part #%d)', rand(1, 99999)));
    $howto1->save($con);
    $tags[] = $howto1->obtainTagName();

    $howto2 = new BlogPost();
    $howto2->setTitle(sprintf('B How to dissaseble door lock? (Part #%d)', rand(1, 99999)));
    $howto2->save($con);
    $tags[] = $howto2->obtainTagName();

    $howto3 = new BlogPost();
    $howto3->setTitle(sprintf('B How to wash keyboard? (Part #%d)', rand(1, 99999)));
    $howto3->save($con);
    $tags[] = $howto3->obtainTagName();

    throw new Exception;
  }
  catch (Exception $e)
  {
    $con->rollback();

    foreach ($tags as $tagName) $t->ok( ! $tagging->hasTag($tagName));

    $t->ok( ! $tagging->hasTag(sfCacheTaggingToolkit::obtainCollectionName(BlogPostTable::getInstance())));
  }
  $tagging->clean();

  $t->diag('Simple delete() operation inside transaction "commit"');

  $con->beginTransaction();
  $tags = array();
  try
  {
    $howto1 = new BlogPost();
    $howto1->setTitle(sprintf('C How to read the new words in foreign language? (Part #%d)', rand(1, 99999)));
    $howto1->save($con);
    $tags[] = $howto1->obtainTagName();

    $howto2 = new BlogPost();
    $howto2->setTitle(sprintf('C How to dissaseble door lock? (Part #%d)', rand(1, 99999)));
    $howto2->save($con);
    $tags[] = $howto2->obtainTagName();

    $howto3 = new BlogPost();
    $howto3->setTitle(sprintf('C How to wash keyboard? (Part #%d)', rand(1, 99999)));
    $howto3->save($con);
    $tags[] = $howto3->obtainTagName();

    $howto1->delete($con);
    $howto2->delete($con);
    $howto3->delete($con);

    foreach ($tags as $tagName) $t->ok(! $tagging->hasTag($tagName));

    $t->ok(! $tagging->hasTag(sfCacheTaggingToolkit::obtainCollectionName(BlogPostTable::getInstance())));

    $con->commit();

    foreach ($tags as $tagName) $t->ok( ! $tagging->hasTag($tagName));

    $t->ok($tagging->hasTag(sfCacheTaggingToolkit::obtainCollectionName(BlogPostTable::getInstance())));

  }
  catch (Exception $e)
  {
    $t->fail($e->getMessage());
  }
  $tagging->clean();

  $t->diag('Simple delete() operation inside transaction "rollback"');


  $tags = array();
  $howto1 = new BlogPost();
  $howto1->setTitle(sprintf('D How to read the new words in foreign language? (Part #%d)', rand(1, 99999)));
  $howto1->save($con);
  $tags[] = $howto1->obtainTagName();

  $howto2 = new BlogPost();
  $howto2->setTitle(sprintf('D How to dissaseble door lock? (Part #%d)', rand(1, 99999)));
  $howto2->save($con);
  $tags[] = $howto2->obtainTagName();

  $howto3 = new BlogPost();
  $howto3->setTitle(sprintf('D How to wash keyboard? (Part #%d)', rand(1, 99999)));
  $howto3->save($con);
  $tags[] = $howto3->obtainTagName();

  $collectionVersion = $tagging->getTag(sfCacheTaggingToolkit::obtainCollectionName(BlogPostTable::getInstance()));

  $con->beginTransaction();
  try
  {
    $howto1->delete($con);
    $howto2->delete($con);
    $howto3->delete($con);

    foreach ($tags as $tagName) $t->ok($tagging->hasTag($tagName));

    $t->cmp_ok($collectionVersion, '===', $tagging->getTag(sfCacheTaggingToolkit::obtainCollectionName(BlogPostTable::getInstance())));

    throw new Exception;
  }
  catch (Exception $e)
  {
    $con->rollback();

    foreach ($tags as $tagName) $t->ok($tagging->hasTag($tagName));

    $t->cmp_ok($tagging->getTag(sfCacheTaggingToolkit::obtainCollectionName(BlogPostTable::getInstance())), '===', $collectionVersion);
  }

  $howto1->refresh()->delete();
  $howto2->refresh()->delete();
  $howto3->refresh()->delete();
  $tagging->clean();

  $t->diag('DQL delete() operation inside transaction "commit"');

  $tags = array();
  $post1 = new BlogPost();
  $post1->setTitle('News')->save($con);
  $tags[] = $post1->obtainTagName();

  $post2 = new BlogPost();
  $post2->setTitle('News')->save($con);
  $tags[] = $post2->obtainTagName();

  $collectionVersion = $tagging->getTag(sfCacheTaggingToolkit::obtainCollectionName(BlogPostTable::getInstance()));
  $con->beginTransaction();
  try
  {
    $aff = BlogPostTable::getInstance()->createQuery()->delete()->where('title = ?', array('News'))->execute();
    $t->is($aff, 2, 'Removed 2 posts');

    $t->cmp_ok($tagging->getTag(sfCacheTaggingToolkit::obtainCollectionName(BlogPostTable::getInstance())), '===', $collectionVersion);

    // tags were not deleted
    foreach ($tags as $tagName) $t->ok($tagging->hasTag($tagName));

    $con->commit();

    foreach ($tags as $tagName) $t->ok(! $tagging->hasTag($tagName));

    $t->cmp_ok($tagging->getTag(sfCacheTaggingToolkit::obtainCollectionName(BlogPostTable::getInstance())), '>', $collectionVersion);
  }
  catch (Exception $e)
  {
    $t->fail($e->getMessage());
  }
  $post1->delete();
  $post2->delete();
  $tagging->clean();

  $t->diag('DQL delete() operation inside transaction "rollback"');

  $tags = array();
  $post1 = new BlogPost();
  $post1->setTitle('News')->save($con);
  $tags[] = $post1->obtainTagName();

  $post2 = new BlogPost();
  $post2->setTitle('News')->save($con);
  $tags[] = $post2->obtainTagName();

  $collectionVersion = $tagging->getTag(sfCacheTaggingToolkit::obtainCollectionName(BlogPostTable::getInstance()));
  $con->beginTransaction();
  try
  {
    $aff = BlogPostTable::getInstance()->createQuery()->delete()->where('title = ?', array('News'))->execute();
    $t->is($aff, 2, 'Removed 2 posts');

    // tags were not deleted
    foreach ($tags as $tagName) $t->ok($tagging->hasTag($tagName));

    $t->cmp_ok($tagging->getTag(sfCacheTaggingToolkit::obtainCollectionName(BlogPostTable::getInstance())), '===', $collectionVersion);
    throw new Exception;
  }
  catch (Exception $e)
  {
    $con->rollback();

    foreach ($tags as $tagName) $t->ok($tagging->hasTag($tagName));
    $t->cmp_ok($tagging->getTag(sfCacheTaggingToolkit::obtainCollectionName(BlogPostTable::getInstance())), '===', $collectionVersion);
  }
  $post1->delete();
  $post2->delete();
  $tagging->clean();

  $t->diag('DQL update() operation inside transaction "commit"');

  $tags = array();
  $post1 = new BlogPost();
  $post1->setTitle('News')->save($con);
  $tags[] = $post1->obtainTagName();

  $post2 = new BlogPost();
  $post2->setTitle('News')->save($con);
  $tags[] = $post2->obtainTagName();

  $collectionVersion = $tagging->getTag(sfCacheTaggingToolkit::obtainCollectionName(BlogPostTable::getInstance()));
  $con->beginTransaction();
  try
  {
    $aff = BlogPostTable::getInstance()
      ->createQuery()
      ->update()
      ->set('title', '?', 'Articles')
      ->where('title = ?', array('News'))
      ->limit(2)
      ->execute();

    $t->is($aff, 2, 'updated 2 posts');

    $t->cmp_ok($tagging->getTag(sfCacheTaggingToolkit::obtainCollectionName(BlogPostTable::getInstance())), '===', $collectionVersion);

    // tags were not deleted
    foreach ($tags as $tagName) $t->ok($tagging->hasTag($tagName));

    $version = sfCacheTaggingToolkit::generateVersion();
    $con->commit();

    foreach ($tags as $tagName) $t->cmp_ok($tagging->getTag($tagName), '<', $version);

    $t->cmp_ok($tagging->getTag(sfCacheTaggingToolkit::obtainCollectionName(BlogPostTable::getInstance())), '<', $version);
  }
  catch (Exception $e)
  {
    $t->fail($e->getMessage());
  }
  $post1->delete();
  $post2->delete();
  $tagging->clean();