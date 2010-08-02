<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
  */

  require_once realpath(dirname(__FILE__) . '/../../../../../test/bootstrap/functional.php');
  
  $browser = new sfTestFunctional(new sfBrowser());

  $cc = new sfCacheClearTask(sfContext::getInstance()->getEventDispatcher(), new sfFormatter());
  $cc->run();


  define('PLUGIN_DATA_DIR', realpath(dirname(__FILE__) . '/../../data'));

  define('SF_VIEW_CACHE_MANAGER_EVENT_NAME', 'view.cache.filter_content');

  $sfContext = sfContext::getInstance();

  $sfContext->getConfiguration()->loadHelpers(array('Url'));
  
  $sfEventDispatcher = $sfContext->getEventDispatcher();
  $cacheManager = $sfContext->getViewCacheManager();

  $connection = Doctrine::getConnectionByTableName('BlogPost');
  $connection->beginTransaction();

  $taggingCache = $cacheManager->getTaggingCache();

  $dataCacheSetups = sfYaml::load(PLUGIN_DATA_DIR . '/config/cache_setup.yml');
  $lockCacheSetups = $dataCacheSetups;

  $count = count($dataCacheSetups);

  # $count - cache adapter count (cross chechs for tagger cache adapter and locker cache adapter)
  $t = $browser->test();

  try
  {
    $article = new BlogPost();
    $article->getTagName();

    $t->fail('called ->getTagName() on new object');
  }
  catch (LogicException $e)
  {
    $t->pass('could not call ->getTagName() on not saved object');
  }

  try
  {
    $article = new BlogPost();
    $article->save();
    $article->getTagName();

    $t->pass('called ->getTagName() on saved object');
  }
  catch (LogicException $e)
  {
    $t->fail('could not call ->getTagName() on saved object');
  }

  $connection->rollback();

  try
  {
    $cacheManager->initialize($sfContext, new sfAPCCache(), $options);
    $t->fail('Exception "InvalidArgumentException" was trigged');
  }
  catch (InvalidArgumentException $e)
  {
    $t->pass(sprintf(
      'Exception "%s" cached - should be instance of sfTaggingCache',
      get_class($e)
    ));
  }

  sfConfig::set('app_sfcachetaggingplugin_microtime_precision', 5);


  Doctrine::loadData(PLUGIN_DATA_DIR . '/fixtures/fixtures.yml');

  foreach ($dataCacheSetups as $data)
  {
    foreach ($lockCacheSetups as $locker)
    {
      $t->info(sprintf('Data/Locker - %s/%s combination', $data['class'], $locker['class']));
      
      try
      {
        $taggingCache->initialize(array(
          'logger'  => array('class' => 'sfNoLogger'),
          'cache'   => $data,
          'locker'  => $locker,
        ));
        $taggingCache->clean();

      }
      catch (sfInitializationException $e)
      {
        $t->comment(sprintf(
          'Skipping combination %s/%s. %s.',
          $data['class'],
          $locker['class'],
          $e->getMessage()
        ));

        continue;
      }
      
      $listenersCountBefore = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));
      $cacheManager->initialize($sfContext, $taggingCache, $cacheManager->getOptions());
      $listenersCountAfter = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));

      $t->ok(
        $listenersCountAfter == $listenersCountBefore,
        '"sf_web_debug" was disabled in test environment'
      );

      $sfWebDebug = sfConfig::get('sf_web_debug');

      sfConfig::set('sf_web_debug', ! $sfWebDebug);


      $listenersCountBefore = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));
      $cacheManager->initialize($sfContext, $taggingCache, $cacheManager->getOptions());
      $listenersCountAfter = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));

      $t->ok(
        $listenersCountAfter > $listenersCountBefore,
        '"sf_web_debug" is enabled in test environment'
      );

      sfConfig::set('sf_web_debug', $sfWebDebug);

      $connection->beginTransaction();
      
      $t->is($taggingCache->get('posts'), false, 'cache is NOT empty');

      $posts = BlogPostTable::getInstance()->getPostsQuery()->execute();

      $t->is(
        $taggingCache->set('posts', $posts, null, $posts->getTags()),
        true,
        'New Doctrine_Collection is saved to cache with key "posts"'
      );

      $t->is(
        null !== ($posts = $taggingCache->get('posts')),
        true,
        '"posts" are successfully fetched from the cache'
      );

      $post = $posts->getFirst();
      $post->setTitle('Row id = ' . $post->getId())->save();

      # $t->comment('Saving post updates');

      $t->is(
        null === ($posts = $taggingCache->get('posts')),
        true,
        'Key expired after editing first post'
      );

      $posts = BlogPostTable::getInstance()->getPostsQuery()->execute();

      $t->is(
        $taggingCache->set('posts', $posts, null, $posts->getTags()),
        true,
        'new "posts" was written to the cache'
      );

      $t->is(
        null === ($posts = $taggingCache->get('posts')),
        false,
        'Fetching "posts" from cache'
      );

      $t->is($taggingCache->lock('posts'), true, 'Locked key "posts"');
      $t->is($taggingCache->isLocked('posts'), true, '"posts" is locked');

      $post = $posts->getLast();
      $post->setTitle('Row id = ' . $post->getId())->save();
      $posts = BlogPostTable::getInstance()->getPostsQuery()->execute();

      $t->is(
        $taggingCache->set('posts', $posts, null, $posts->getTags()),
        false,
        'Skipped writing to cache, "posts" is locked'
      );

      $t->is($taggingCache->unlock('posts'), true, 'Unlocked "posts"');

      $t->is($taggingCache->isLocked('posts'), false, '"posts" is now not locked');

      $t->is(
        $taggingCache->set('posts', $posts, null, $posts->getTags()),
        true,
        'Writing to cache, "posts" is not locked'
      );

      $postsAndComments = BlogPostTable::getInstance()->getPostsWithCommentQuery()->execute();

      foreach ($postsAndComments as $post)
      {
        $postsAndComments->addTags($post->getBlogPostComment());
      }

      $t->is(
        $taggingCache->set('posts+comments', $postsAndComments, null, $postsAndComments->getTags()),
        true,
        'Saving posts with comments'
      );

      $t->is(null !== ($taggingCache->get('posts+comments')), true, '"posts+comments" are stored in cache');

      $table = BlogPostCommentTable::getInstance();

      $wasComments = $table->count();

      $table->findOneByAuthor('marko')->delete();

      $nowComments = $table->count();

      $t->is($wasComments, $nowComments + 1, 'Comments count -1');

      $t->is(null === ($taggingCache->get('posts+comments')), true, '"posts+comments" is expired, 1 comment removed');

      $postsAndComments = BlogPostTable::getInstance()->getPostsWithCommentQuery()->execute();

      foreach ($postsAndComments as $post)
      {
        $postsAndComments->addTags($post->getBlogPostComment());
      }

      $t->is(
        $taggingCache->set('posts+comments', $postsAndComments, null, $postsAndComments->getTags()),
        true,
        'Saving posts with comments'
      );

      $table->createQuery()->addWhere('author = ?', 'david')->delete()->execute();

      $afterDeleteComments = $table->count();

      3 == $afterDeleteComments
        ? $t->pass('Removed all davids comments')
        : $t->fail('Not removed davids comments');

      $t->is(null === ($taggingCache->get('posts+comments')), true, '"posts+comments" is not expired, removed 3 comments');

      $postsAndComments = BlogPostTable::getInstance()->getPostsWithCommentQuery()->execute();

      foreach ($postsAndComments as $post)
      {
        $postsAndComments->addTags($post->getBlogPostComment());
      }

      $t->is(
        $taggingCache->set('posts+comments', $postsAndComments, null, $postsAndComments->getTags()),
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

      $t->is(null === ($taggingCache->get('posts+comments')), true, '"posts+comments" is expired, 3 fruit comment updated');

      BlogPostTable::getInstance()->createQuery()->delete()->execute();
      $emptyPosts = BlogPostTable::getInstance()->findAll();

      $t->is(
        $taggingCache->set('posts', $emptyPosts, null, $emptyPosts->getTags()),
        true,
        'Saving empty "posts" to cache'
      );

      $newPost = new BlogPost();
      $newPost->setIsEnabled(true);
      $newPost->setTitle('My Title');
      $newPost->setContent('Content, content, content, content, content');
      $newPost->save();

      $t->is(null === ($taggingCache->get('posts')), true, '"posts" are expired (first post is saved)');

      $posts = BlogPostTable::getInstance()->findAll();

      $t->is(
        $taggingCache->set('posts', $posts, null, $posts->getTags()),
        true,
        'Saving empty "posts" to cache'
      );

      $t->is(null === ($taggingCache->get('posts')), false, '"posts" are not expired (no post was saved during previous save)');

      $t->isa_ok($taggingCache->get('posts'), 'Doctrine_Collection_Cachetaggable', 'Saved object in cache is "Doctrine_Collection_Cachetaggable"');

      BlogPostTable::getInstance()->createQuery()->delete()->execute();
      BlogPostCommentTable::getInstance()->createQuery()->delete()->execute();

      $emptyPosts = BlogPostTable::getInstance()->findAll();
      $emptyPostComments = BlogPostCommentTable::getInstance()->findAll();

      $t->is($emptyPosts->count(), 0, 'All posts are removed');
      $t->is($emptyPostComments->count(), 0, 'All comments are removed');

      # sync post with comments (adding new comment post should be updated)
      $emptyPosts->addTags($emptyPostComments);

      $t->is(
        $taggingCache->set('posts+comments', $emptyPosts, null, $emptyPosts->getTags()),
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

      $t->is(null === ($taggingCache->get('posts+comments')), true, '"posts+comments" are expired (first post is saved)');

      $post = BlogPostTable::getInstance()->find($newPost->getId());
      $post->addTags($post->getBlogPostComment());

      $t->is(
        $taggingCache->set('posts+comments', $post, null, $post->getTags()),
        true,
        'Saving empty "posts+comments" to cache'
      );

      $t->is(null === ($taggingCache->get('posts+comments')), false, '"posts+comments" are not expired (no post/comments was saved during previous save)');

      $newPostComment = new BlogPostComment();
      $newPostComment->setBlogPost($newPost);
      $newPostComment->setAuthor('Fruit');
      $newPostComment->setMessage('My Comment');
      $newPostComment->save();

      $t->is(null === ($taggingCache->get('posts+comments')), true, '"posts+comments" are expired (first associated comment was saved)');

      $connection->rollback();
    }
  }