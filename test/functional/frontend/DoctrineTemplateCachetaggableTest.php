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

  $separator = sfCacheTaggingToolkit::getModelTagNameSeparator();

  $sfContext = sfContext::getInstance();
  $cacheManager = $sfContext->getViewCacheManager();

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

  $connection = Doctrine::getConnectionByTableName('BlogPost');
  $connection->beginTransaction();

  $article = new Book();
  $article->setLang('fr');
  $article->setSlug('foobarbaz');
  $article->save();

  $t->isa_ok($article->assignObjectVersion(213213213213), 'Book', 'assignObjectVersion() returns self object');

  $t->is($article->obtainTagName(), "Book{$separator}fr-foobarbaz", 'Multy unique column tables are compatible with tag names');

  $connection->rollback();

  # disabled sf_cache test
  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', false);

  $article = new Book();
  $t->is($article->getTags(), array());
  $t->is($article->addTag('key', 123912), false);
  $t->is($article->addTags(array('key' => 123912)), false);

  sfConfig::set('sf_cache', $optionSfCache);

  

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

  # getTags
  $comments = $q->execute();

  $t->is(count($tags = $comments->getTags()), 9);

  $tagsKeys = array_keys($tags);

  $expected = array('BlogPostComment');
  for ($i = 1; $i <= 8; $expected[] = "BlogPostComment{$separator}{$i}", $i++);

  sort($expected);
  sort($tagsKeys);

  $t->is($tagsKeys, $expected);

  $commentsCnt = BlogPostCommentTable::getInstance()->count();
  $postsCnt = BlogPostTable::getInstance()->count();
  $voteCnt = BlogPostVoteTable::getInstance()->count();

  $t->is(count($tags = $comments->getTags(true)), $commentsCnt + $postsCnt + $voteCnt + 3/* 3 collection tags*/);

  # addTags

  foreach ($comments as $comment)
  {
    if (! $comment->getBlogPost())
    {
      continue;
    }

    $t->is($comment->addTags($comment->getBlogPost()->getTags()), true);

    $t->is(count($comment->getTags()), 4);
  }

  # obtainTagName


  try
  {
    $post = new BlogPost();

    $post->obtainTagName();
    $t->fail();
  }
  catch (LogicException $e)
  {
    $t->pass($e->getMessage());
  }

  $connection->beginTransaction();

  $post = new BlogPost();
  $post->setTitle('How to search in WEB?');
  $post->save();

  $vote = new BlogPostVote();
  $vote->setBlogPost($post);
  $vote->save();

  $t->is($vote->obtainTagName(), "BlogPostVote{$separator}{$vote->getId()}");

  $votepost = new PostVote();
  $votepost->setBlogPost($post);
  $votepost->setBlogPostVote($vote);
  $votepost->save();
  $t->is($votepost->obtainTagName(), "PostVote{$separator}{$vote->getId()}{$separator}{$post->getId()}");

  $connection->rollback();

  # assignObjectVersion


  $v = sfCacheTaggingToolkit::generateVersion();
  $post = new BlogPost();
  $post->setTitle('How to search in WEB?');
  $t->isa_ok($samePost = $post->assignObjectVersion($v), 'BlogPost');
  $t->is($post->getOId(), $samePost->getOid(), "OID is === {$post->getOId()}");

  $t->is($post->obtainObjectVersion(), $v);

  $t->isa_ok($post->updateObjectVersion(), 'BlogPost');

  $t->cmp_ok($v, '<', $post->obtainObjectVersion());
