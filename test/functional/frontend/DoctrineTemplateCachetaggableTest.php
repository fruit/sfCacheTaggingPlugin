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

  $sfContext = sfContext::getInstance();
  $cacheManager = $sfContext->getViewCacheManager();
  /* @var $cacheManager sfViewCacheTagManager */
  $tagging = $cacheManager->getTaggingCache();

  $con = Doctrine_Manager::getInstance()->getCurrentConnection();
  $con->beginTransaction();
  $truncateQuery = array_reduce(
    array('book','blog_post','blog_post_comment', 'post_vote', 'blog_post_vote','blog_post_translation'),
    function ($return, $val) { return "{$return} TRUNCATE {$val};"; }, ''
  );

  $cleanQuery = "SET FOREIGN_KEY_CHECKS = 0; {$truncateQuery}; SET FOREIGN_KEY_CHECKS = 1;";
  $con->exec($cleanQuery);
  Doctrine::loadData(sfConfig::get('sf_data_dir') .'/fixtures/blog_post.yml');
  $con->commit();
  $tagging->clean();

  $t = new lime_test();

  $confTests = array(
    array('options' => array(), 'throw' => false),
    array('options' => array('versionColumn' => ''), 'throw' => true),
    array('options' => array('versionColumn' => 2321), 'throw' => true),
    array('options' => array('versionColumn' => 'x'), 'throw' => false),
  );

  foreach ($confTests as $test)
  {
    list($args, $shouldThrow) = array_values($test);

    $class = 'sfConfigurationException';
    $argsText = str_replace("\n", '', var_export($args, true));
    try
    {
      new Doctrine_Template_Cachetaggable($args);

      if (! $shouldThrow)
      {
        $t->pass(sprintf('Exception "%s" not thrown on args = "%s"', $class, $argsText));
      }
      else
      {
        $t->fail(sprintf('Exception "%s" shall be thrown on args = "%s"', $class, $argsText));
      }
    }
    catch (sfConfigurationException $e)
    {
      if ($shouldThrow)
      {
        $t->pass(sprintf('Exception "%s" is thrown on args = "%s"', $class, $argsText));
      }
      else
      {
        $t->fail(sprintf('Exception "%s" should not be thrown on args = "%s"', $class, $argsText));
      }
    }
  }

  $book = new Book();
  $book->setLang('fr');
  $book->setSlug('foobarbaz');
  $book->save();

  $t->isa_ok($book->assignObjectVersion(213213213213), 'Book', 'assignObjectVersion() returns self object');

  $t->is(
    $book->obtainTagName(),
    sfCacheTaggingToolkit::obtainTagName($book->getTable()->getTemplate('Cachetaggable'), $book->toArray()),
    'Multy unique column tables are compatible with tag names'
  );
  $book->delete();
  $book->free(true);

  # disabled sf_cache test
  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', false);

  $book = new Book();
  $t->is($book->getCacheTags(), array());
  $t->is($book->getCacheTags(true), array());
  $t->is($book->addCacheTag('key', 123912), false);
  $t->is($book->addCacheTags(array('key' => 123912)), false);
  unset($book);

  sfConfig::set('sf_cache', $optionSfCache);

  # getCacheTags no deep
  $q = BlogPostCommentTable::getInstance()->createQuery('c');
  $q
    ->addSelect('c.*')
    ->addSelect('p.*')
    ->leftJoin('c.BlogPost p')
    ->addSelect('v.*')
    ->leftJoin('p.BlogPostVote v')

    ->addSelect('t.*')
    ->leftJoin('p.Translation t WITH t.lang = "en"')
  ;

  $comments = $q->execute();


  # getCacheTags

  $t->is(count($tags = $comments->getCacheTags(false)), 9);

  $commentsCnt = BlogPostCommentTable::getInstance()->count();
  $postsCnt = BlogPostTable::getInstance()->count();
  $voteCnt = BlogPostVoteTable::getInstance()->count();

  $t->diag("Comments: {$commentsCnt}  Posts: {$postsCnt}  Votes: {$voteCnt}");

  # 3 kind of collection is used
  $t->is(count($tagsA = $comments->getCacheTags()), $commentsCnt + $postsCnt + $voteCnt + 3);
  $comments->free(true);

  $comments = $q->execute();
  $t->is(count($tagsB = $comments->getCacheTags(true)), $commentsCnt + $postsCnt + $voteCnt + 3);
  $comments->free(true);

  $t->cmp_ok($tagsB, '===', $tagsA);

  # addCacheTags

  foreach ($comments as $comment)
  {
    if (! $comment->getBlogPost())
    {
      continue;
    }

    $t->is($comment->addCacheTags($comment->getBlogPost()->getCacheTags(false)), true);

    # comment is Doctrine_Record, it does not returns collection tag
    # comment contain 1:1 post, it does not return collection tag too

    $t->is(count($comment->getCacheTags(false)), 2);
  }

  # getCacheTags & fetchOne & 1:M
  $q = BlogPostTable::getInstance()->createQuery('p');
  $q
    ->addSelect('p.*, c.*')
    ->leftJoin('p.BlogPostComment c')
    ->limit(1)
  ;

  $post = $q->fetchOne();

  $values = $post->getCacheTags();
  $t->is(count($values), 6);
  $t->is(count($post->getCacheTags(true)), 6);
  $t->is(count($post->getCacheTags(false)), 2);

  $post->free(true);

  # getTag & fechOne & 1

  $q = BlogPostTable::getInstance()->createQuery('p');
  $q
    ->addSelect('p.*')
    ->limit(1)
  ;

  $post = $q->fetchOne();

  $t->is(count($post->getCacheTags()), 2, 'getCacheTags deeply count shoud be 2');
  $t->is(count($post->getCacheTags(true)), 2, 'getCacheTags deeply count shoud be 2');
  $t->is(count($post->getCacheTags(false)), 2, 'getCacheTags count shoud be 2');

  $post->free(true);

  # obtainTagName
  try
  {
    $post = new BlogPost();

    $post->obtainTagName();
    $t->fail();
  }
  catch (InvalidArgumentException $e)
  {
    $t->pass($e->getMessage());
  }


  $post = new BlogPost();
  $post->setTitle('How to search in WEB?');
  $post->save();

  $vote = new BlogPostVote();
  $vote->setBlogPost($post);
  $vote->save();

  $t->is($vote->obtainTagName(), sfCacheTaggingToolkit::obtainTagName($vote->getTable()->getTemplate('Cachetaggable'), $vote->toArray()));

  $votepost = new PostVote();
  $votepost->setBlogPost($post);
  $votepost->setBlogPostVote($vote);
  $votepost->save();
  $t->is($votepost->obtainTagName(), sfCacheTaggingToolkit::obtainTagName($votepost->getTable()->getTemplate('Cachetaggable'), $votepost->toArray()));

  # assignObjectVersion

  $v = sfCacheTaggingToolkit::generateVersion();
  $post = new BlogPost();
  $post->setTitle('How to search in WEB?');
  $t->isa_ok($samePost = $post->assignObjectVersion($v), 'BlogPost');
  $t->is($post->getOId(), $samePost->getOid(), "OID is === {$post->getOId()}");

  $t->is($post->obtainObjectVersion(), $v);

  $t->isa_ok($post->updateObjectVersion(), 'BlogPost');

  $t->cmp_ok($v, '<', $post->obtainObjectVersion());


  # getCollectionTags


  $tagging->clean();

  $t->is($tagging->getTag('BlogPost'), null);

  $t->can_ok('BlogPost', array(
    'getCollectionTags',
    'obtainCollectionName',
    'obtainCollectionVersion',
  ), 'Is callable');

  $preVersion = sfCacheTaggingToolkit::generateVersion();
  $post = new BlogPost();
  $post->setTitle('How to search in Google?');
  $post->save();

  $name = $post->obtainCollectionName();
  $t->is($name, sfCacheTaggingToolkit::obtainCollectionName($post->getTable()));

  $version = $post->obtainCollectionVersion();

  $t->cmp_ok($version, '>', $preVersion);
  $t->cmp_ok($version, '<', sfCacheTaggingToolkit::generateVersion());

  $t->is($post->getCollectionTags(), array($name => $version));

  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', false);

  # obtainCollectionName

  # obtainCollectionVersion

  $t->is($post->getCollectionTags(), array(sfCacheTaggingToolkit::obtainCollectionName($post->getTable()) => '1'));
  $t->is($post->obtainCollectionName(), $name);
  $t->is($post->obtainCollectionVersion(), '1');

  sfConfig::set('sf_cache', $optionSfCache);
