<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2012 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  include_once realpath(dirname(__FILE__) . '/../../bootstrap/functional.php');

  $browser = new sfTestFunctional(new sfBrowser());

  define('PLUGIN_DATA_DIR', dirname(__FILE__) . '/../../fixtures/data');

  define('SF_VIEW_CACHE_MANAGER_EVENT_NAME', 'view.cache.filter_content');

  $sfContext = sfContext::getInstance();

  $sfContext->getConfiguration()->loadHelpers(array('Url'));

  $sfEventDispatcher = $sfContext->getEventDispatcher();
  $cacheManager = $sfContext->getViewCacheManager();

  $truncateQuery = array_reduce(
    array('blog_post','blog_post_comment','blog_post_vote','blog_post_translation'),
    function ($return, $val) { return "{$return} TRUNCATE {$val};"; }, ''
  );

  $cleanQuery = "SET FOREIGN_KEY_CHECKS = 0; {$truncateQuery}; SET FOREIGN_KEY_CHECKS = 1;";


  $con = Doctrine_Manager::getInstance()->getCurrentConnection();
  $con->beginTransaction();
  $con->exec($cleanQuery);
  Doctrine::loadData(sfConfig::get('sf_data_dir') .'/fixtures/blog_post.yml');
  $con->commit();

  $cacheSetups = sfYaml::load(PLUGIN_DATA_DIR . '/config/cache_setup.yml');

  $t = $browser->test();

  try
  {
    $article = new BlogPost();
    $article->obtainTagName();

    $t->fail('called ->obtainTagName() on new object');
  }
  catch (InvalidArgumentException $e)
  {
    $t->pass('could not call ->obtainTagName() on object with empty PK values');
  }
  $article->free();
  unset($article);

  try
  {
    $article = new BlogPost();
    $article->setId(1020);

    $t->is(
      $article->obtainTagName(),
      sfCacheTaggingToolkit::obtainTagName($article->getTable()->getTemplate('Cachetaggable'), $article->getData()),
      'called ->obtainTagName() on new object, but with defined `id`'
    );

    $article->free();
    unset($article);
  }
  catch (InvalidArgumentException $e)
  {
    $t->fail(sprintf('Thrown exception: %s', $e->getMessage()));
  }

  try
  {
    $article = new BlogPost();
    $article->save();
    $article->obtainTagName();

    $t->pass('called ->obtainTagName() on saved object');
    $article->delete();
  }
  catch (LogicException $e)
  {
    $t->fail('could not call ->obtainTagName() on saved object');
  }
  $article->free();
  unset($article);

  try
  {
    $cacheManager->initialize($sfContext, new sfAPCCache(), array());
    $t->fail('Exception "InvalidArgumentException" was trigged');
  }
  catch (InvalidArgumentException $e)
  {
    $t->pass(sprintf(
      'Exception "%s" cached - should be instance of sfTaggingCache',
      get_class($e)
    ));
  }


  sfConfig::set('app_sfCacheTagging_microtime_precision', 5);

  $log = sfConfig::get('sf_log_dir') . '/../../../temp/cache.log';

  foreach ($cacheSetups as $cacheSetups)
  {
    try
    {
      $tagging = new sfTaggingCache(array(
        'logger'  => array('class' => 'sfFileCacheTagLogger', 'param' => array(
          'file' => $log,
          'format' => '%microtime% [%char%] %key% (%char_explanation%)%EOL%',
        )),
        'storage'   => $cacheSetups,
      ));

      $tagging->clean();
    }
    catch (sfInitializationException $e)
    {
      $t->fail($e->getMessage());

      continue;
    }

    $t->diag(sprintf('Data - %s', $cacheSetups['class']));

    $listenersCountBefore = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));
    $cacheManager->initialize($sfContext, $tagging, $cacheManager->getOptions());
    $listenersCountAfter = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));

    $t->ok(
      $listenersCountAfter == $listenersCountBefore,
      '"sf_web_debug" was disabled in test environment'
    );

    $sfWebDebug = sfConfig::get('sf_web_debug');

    sfConfig::set('sf_web_debug', ! $sfWebDebug);


    $listenersCountBefore = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));
    $cacheManager->initialize($sfContext, $tagging, $cacheManager->getOptions());
    $listenersCountAfter = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));

    $t->ok(
      $listenersCountAfter > $listenersCountBefore,
      '"sf_web_debug" is enabled in test environment'
    );

    sfConfig::set('sf_web_debug', $sfWebDebug);

    $s  = BlogPostTable::getInstance()->findAll();
    $sc = BlogPostCommentTable::getInstance()->findAll();
    $c  = BlogPostCommentTable::getInstance()->findAll();
    $s->free(true);$sc->free(true);$c->free(true);;
    $s->clear();$sc->clear(); $c->clear();


    $con->beginTransaction();
    $con->exec($cleanQuery);
    Doctrine::loadData(sfConfig::get('sf_data_dir') .'/fixtures/blog_post.yml');
    $con->commit();
    $tagging->clean();

//    $connection->beginTransaction();

    $t->is($tagging->get('posts'), false, '"posts" cache is empty');

    $posts = BlogPostTable::getInstance()->getPostsQuery()->execute();

    $t->ok(
      $tagging->set('posts', $posts, null, $posts->getCacheTags()),
      'New Doctrine_Collection is saved to cache with key "posts"'
    );

    $t->ok(
      null !== ($posts = $tagging->get('posts')),
      '"posts" are successfully fetched from the cache'
    );

    $post = $posts->getFirst();
    $post->setTitle('Row id = ' . $post->getId())->save();

    # $t->comment('Saving post updates');

    $t->is(
      null === ($posts = $tagging->get('posts')),
      true,
      'Key expired after editing first post'
    );

    $posts = BlogPostTable::getInstance()->getPostsQuery()->execute();

    $t->is(
      $tagging->set('posts', $posts, null, $posts->getCacheTags()),
      true,
      'new "posts" was written to the cache'
    );

    $t->is(
      null === ($posts = $tagging->get('posts')),
      false,
      'Fetching "posts" from cache'
    );

    $t->is($tagging->lock('posts'), true, 'Locked key "posts"');
    $t->is($tagging->isLocked('posts'), true, '"posts" is locked');

    $post = $posts->getLast();
    $post->setTitle('Row id = ' . $post->getId())->save();
    $posts = BlogPostTable::getInstance()->getPostsQuery()->execute();

    $t->is(
      $tagging->set('posts', $posts, null, $posts->getCacheTags()),
      false,
      'Skipped writing to cache, "posts" is locked'
    );

    $t->is($tagging->unlock('posts'), true, 'Unlocked "posts"');

    $t->is($tagging->isLocked('posts'), false, '"posts" is now not locked');

    $t->is(
      $tagging->set('posts', $posts, null, $posts->getCacheTags()),
      true,
      'Writing to cache, "posts" is not locked'
    );

    $postsAndComments = BlogPostTable::getInstance()->getPostsWithCommentQuery()->execute();

    foreach ($postsAndComments as $post)
    {
      $postsAndComments->addCacheTags($post->getBlogPostComment());
    }

    $t->is(
      $tagging->set('posts+comments', $postsAndComments, null, $postsAndComments->getCacheTags()),
      true,
      'Saving posts with comments'
    );

    $t->is(null !== ($tagging->get('posts+comments')), true, '"posts+comments" are stored in cache');

    $table = BlogPostCommentTable::getInstance();

    $wasComments = $table->count();

    $table->findOneByAuthor('marko')->delete();

    $nowComments = $table->count();

    $t->is($wasComments, $nowComments + 1, 'Comments count -1');

    $t->is(null === ($tagging->get('posts+comments')), true, '"posts+comments" is expired, 1 comment removed');

    $postsAndComments = BlogPostTable::getInstance()->getPostsWithCommentQuery()->execute();

    foreach ($postsAndComments as $post)
    {
      $postsAndComments->addCacheTags($post->getBlogPostComment());
    }

    $t->is(
      $tagging->set('posts+comments', $postsAndComments, null, $postsAndComments->getCacheTags()),
      true,
      'Saving posts with comments'
    );

    $table->createQuery()->addWhere('author = ?', 'david')->delete()->execute();

    $afterDeleteComments = $table->count();

    4 == $afterDeleteComments
      ? $t->pass('Removed all davids comments')
      : $t->fail("Not removed davids comments {$afterDeleteComments}");

    $t->is(null === ($tagging->get('posts+comments')), true, '"posts+comments" is not expired, removed 3 comments');

    $postsAndComments = BlogPostTable::getInstance()->getPostsWithCommentQuery()->execute();

    foreach ($postsAndComments as $post)
    {
      $postsAndComments->addCacheTags($post->getBlogPostComment());
    }

    $t->is(
      $tagging->set('posts+comments', $postsAndComments, null, $postsAndComments->getCacheTags()),
      true,
      'Saving posts with comments'
    );

    $q = Doctrine_Query::create()
      ->update('BlogPostComment b')
      ->addWhere('author = ?', 'fruit')
      ->set('message', 'upper(message)')
    ;

    3 == $q->execute()
      ? $t->pass('Updated all fruits comments')
      : $t->fail('Not removed fruits');

    $t->is(null === ($tagging->get('posts+comments')), true, '"posts+comments" is expired, 3 fruit comment updated');

    BlogPostTable::getInstance()->createQuery()->delete()->execute();
    $emptyPosts = BlogPostTable::getInstance()->findAll();

    $t->is(
      $tagging->set('posts', $emptyPosts, null, $emptyPosts->getCacheTags()),
      true,
      'Saving empty "posts" to cache'
    );

    $newPost = new BlogPost();
    $newPost->setIsEnabled(true);
    $newPost->setTitle('My Title');
    $newPost->setContent('Content, content, content, content, content');
    $newPost->save();

    $t->is(null === ($tagging->get('posts')), true, '"posts" are expired (first post is saved)');

    $posts = BlogPostTable::getInstance()->findAll();

    $t->is(
      $tagging->set('posts', $posts, null, $posts->getCacheTags()),
      true,
      'Saving empty "posts" to cache'
    );

    $t->is(null === ($tagging->get('posts')), false, '"posts" are not expired (no post was saved during previous save)');

    $t->isa_ok($tagging->get('posts'), 'Doctrine_Collection_Cachetaggable', 'Saved object in cache is "Doctrine_Collection_Cachetaggable"');

    BlogPostTable::getInstance()->createQuery()->delete()->execute();
    BlogPostCommentTable::getInstance()->createQuery()->delete()->execute();

    $emptyPosts = BlogPostTable::getInstance()->findAll();
    $emptyPostComments = BlogPostCommentTable::getInstance()->findAll();

    $t->is($emptyPosts->count(), 0, 'All posts are removed');
    $t->is($emptyPostComments->count(), 0, 'All comments are removed');

    # sync post with comments (adding new comment post should be updated)
    $emptyPosts->addCacheTags($emptyPostComments);

    $t->is(
      $tagging->set('posts+comments', $emptyPosts, null, $emptyPosts->getCacheTags()),
      true,
      sprintf(
        'Saving empty "posts+comments" to cache (%d posts, %d comments)',
        $emptyPosts->count(),
        $emptyPostComments->count()
      )
    );

    $newPost = new BlogPost();
    $newPost->setIsEnabled(true);
    $newPost->setTitle('My Title 2');
    $newPost->setContent('Content 2, content 2, content 2, content 2, content 2');
    $newPost->save();

    $t->is(null === ($tagging->get('posts+comments')), true, '"posts+comments" are expired (first post is saved)');

    $post = BlogPostTable::getInstance()->find($newPost->getId());
    $post->addCacheTags($post->getBlogPostComment());

    $t->is(
      $tagging->set('posts+comments', $post, null, $post->getCacheTags()),
      true,
      'Saving empty "posts+comments" to cache'
    );

    $t->is(null === ($tagging->get('posts+comments')), false, '"posts+comments" are not expired (no post/comments was saved during previous save)');

    $newPostComment = new BlogPostComment();
    $newPostComment->setBlogPost($newPost);
    $newPostComment->setAuthor('Fruit');
    $newPostComment->setMessage('My Comment');
    $newPostComment->save();

    $t->is(null === ($tagging->get('posts+comments')), true, '"posts+comments" are expired (first associated comment was saved)');

    unset($tagging);
  }
