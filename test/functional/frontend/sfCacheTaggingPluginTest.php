<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
  */

  require_once realpath(dirname(__FILE__) . '/../../../../../test/bootstrap/functional.php');
  require_once sfConfig::get('sf_symfony_lib_dir') . '/vendor/lime/lime.php';

  define('PLUGIN_DATA_DIR', realpath(dirname(__FILE__) . '/../../data'));

  define('SF_VIEW_CACHE_MANAGER_EVENT_NAME', 'view.cache.filter_content');

  
  $sfContext = sfContext::getInstance();

  $sfContext->getConfiguration()->loadHelpers(array('Url'));
  
  $sfEventDispatcher = $sfContext->getEventDispatcher();
  $sfViewCacheManager = $sfContext->getViewCacheManager();

  $sfTagger = $sfViewCacheManager->getTagger();

  $dataCacheSetups = sfYaml::load(PLUGIN_DATA_DIR . '/config/cache_setup.yml');
  $lockCacheSetups = $dataCacheSetups;

  $count = count($dataCacheSetups);

  # $count - cache adapter count (cross chechs for tagger cache adapter and locker cache adapter)
  $t = new lime_test();

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



  $t->is($sfViewCacheManager->startWithTags('some_cache_key'), null, 'ob_start() on new key');
  $t->diag('Output some content for testing ob_start() in sfViewCacheManager');
  $t->isnt($content = $sfViewCacheManager->stopWithTags('some_cache_key', null), null, 'ob_get_clean() on key "some_cache_key"');
  print $content;
  $t->isnt($sfViewCacheManager->startWithTags('some_cache_key'), '', 'ob_start() on old key');

  try
  {
    $sfViewCacheManager->initialize($sfContext, new sfAPCCache(), $options);
    $t->fail('Exception "InvalidArgumentException" was trigged');
  }
  catch (InvalidArgumentException $e)
  {
    $t->pass(sprintf(
      'Exception "%s" cached - should be instance of sfTagCache',
      get_class($e)
    ));
  }

  $precisionToTest = array(
    array('value' => -1, 'throwException' => true),
    array('value' =>  0, 'throwException' => false),
    array('value' =>  3, 'throwException' => false),
    array('value' =>  6, 'throwException' => false),
    array('value' =>  7, 'throwException' => true),
  );

  # if precision approach to 0, unit tests will be failed
  # (0 precision is too small for the current test)

  foreach ($precisionToTest as $precisionTest)
  {
    try
    {
      sfConfig::set('app_sfcachetaggingplugin_microtime_precision', $precisionTest['value']);

      sfCacheTaggingToolkit::getPrecision();

      if ($precisionTest['throwException'])
      {
        $t->fail(sprintf(
          'Should be thrown an OutOfRangeException value "%d" no in range 0…6',
          $precisionTest['value']
        ));
      }
      else
      {
        $t->pass(sprintf(
          'Precision value "%d" in range 0…6, no exception was thrown',
          $precisionTest['value']
        ));
      }
    }
    catch (OutOfRangeException $e)
    {
      if ($precisionTest['throwException'])
      {
        $t->pass(sprintf(
          'OutOfRangeException catched value "%d" is not in range 0…6',
          $precisionTest['value']
        ));
      }
      else
      {
        $t->fail(sprintf(
          'Precision value "%d" in range 0…6, exception was thrown',
          $precisionTest['value']
        ));
      }
    }
  }

  sfConfig::set('app_sfcachetaggingplugin_microtime_precision', 5);

  $posts = BlogPostTable::getInstance()->findAll();
  $posts->delete();

  $postComments = BlogPostCommentTable::getInstance()->findAll();
  $postComments->delete();

  $postTagKey = BlogPostTable::getInstance()->getClassnameToReturn();
  $postCommentTagKey = BlogPostCommentTable::getInstance()->getClassnameToReturn();

  $postCollectionTag = array("{$postTagKey}"   => sfCacheTaggingToolkit::generateVersion(strtotime('today')));
  $postCommentCollectionTag = array("{$postCommentTagKey}"   => sfCacheTaggingToolkit::generateVersion(strtotime('today')));

  $t->is(
    $posts->getTags(),
    $postCollectionTag,
    'Doctrine_Collection returns 1 tag BlogPost as collection listener tag'
  );

  try
  {
    $posts->addTag(array('MyTag'), 28182);
    $t->fail('Exception "InvalidArgumentException" was not thrown');
  }
  catch (InvalidArgumentException $e)
  {
    $t->pass(sprintf('Exception "%s" is thrown. (catched)', get_class($e)));
  }

  $posts->addTag('SomeTag', 1239218391283213);
  $t->is(count($posts->getTags()), 2, 'Adding new tag.');
  $posts->addTag('SomeTag', 40545945);
  $t->is(count($posts->getTags()), 2, 'Adding tag with the same tag name "SomeTag".');
  $posts->addTag('SomeTagNew', 1239218391283213);
  $t->is(count($posts->getTags()), 3, 'Adding tag with new tag name "SomeTagNew".');

  $tagsToAdd = array(
    "{$postTagKey}_1" => 1238732512, "{$postTagKey}_2" => 4968984292,
    "{$postTagKey}_3" => 3823394234, "{$postTagKey}_4" => 4989238912,
  );

  $tagsToReturn = array_merge($tagsToAdd, $postCollectionTag);

  $posts->removeTags();
  $t->is($posts->getTags(), $postCollectionTag, 'cleaned added tags');

  $posts->addTags($tagsToAdd);
  $t->is($posts->getTags(), $tagsToReturn, 'passed tags equals to added as "array"');

  $posts->removeTags();
  $posts->addTags(new ArrayIterator($tagsToAdd));
  $t->is($posts->getTags(), $tagsToReturn, 'passed tags equals to added as "ArrayIterator"');

  $posts->removeTags();
  $posts->addTags(new ArrayObject($tagsToAdd));
  $t->is($posts->getTags(), $tagsToReturn, 'passed tags equals to added as "ArrayObject"');



  $posts->removeTags();
  $posts->addTags($postComments);
  $t->is($posts->getTags(), array_merge($postCollectionTag, $postCommentCollectionTag), 'passed tags equals to added as "Doctrine_Collection_Cachetaggable"');
  $posts->removeTags();

  try
  {
    $posts->addTags('someTag');
    $t->fail('Exception "InvalidArgumentException" was NOT thrown');
  }
  catch (InvalidArgumentException $e)
  {
    $t->pass(sprintf(
      'Exception "%s" is thrown (catched). String is not acceptable',
      get_class($e)
    ));
  }

  $posts = BlogPostTable::getInstance()->findAll();
  $sfViewCacheManager->addTags($posts);
  $t->is($sfViewCacheManager->getTags(), $postCollectionTag, 'Tags stored in manager are full/same');
  $sfViewCacheManager->addTags(array('SomeTag' => 1234567890));
  $t->is($sfViewCacheManager->getTags(), array_merge(array('SomeTag' => 1234567890), $postCollectionTag), 'Tags with new tag are successfully saved');
  $sfViewCacheManager->clearTags();
  $t->is($sfViewCacheManager->getTags(), array(), 'All tags are cleared');

  foreach ($dataCacheSetups as $data)
  {
    foreach ($lockCacheSetups as $locker)
    {
      Doctrine::loadData(PLUGIN_DATA_DIR . '/fixtures/fixtures.yml');
      $posts = BlogPostTable::getInstance()->getPostsQuery()->execute();

      $sfTagger->initialize(array('logging' => true, 'cache' => $data, 'locker' => $locker));
      $sfTagger->clean();


      $listenersCountBefore = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));
      $sfViewCacheManager->initialize($sfContext, $sfTagger, $sfViewCacheManager->getOptions());
      $listenersCountAfter = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));

      $t->ok(
        $listenersCountAfter == $listenersCountBefore,
        '"sf_web_debug" is disabled in test environment'
      );

      $sfWebDebug = sfConfig::get('sf_web_debug');

      sfConfig::set('sf_web_debug', ! $sfWebDebug);


      $listenersCountBefore = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));
      $sfViewCacheManager->initialize($sfContext, $sfTagger, $sfViewCacheManager->getOptions());
      $listenersCountAfter = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));

      $t->ok(
        $listenersCountAfter > $listenersCountBefore,
        '"sf_web_debug" is enabled in test environment'
      );

      sfConfig::set('sf_web_debug', $sfWebDebug);

      $t->comment(sprintf(
        'Setuping new configuration (data: "%s", locker: "%s")',
        get_class($sfTagger->getDataCache()),
        get_class($sfTagger->getLockerCache())
      ));

      $t->is($sfTagger->get('posts'), false, 'cache is NOT empty');

      $t->is(
        $sfTagger->set('posts', $posts, null, $posts->getTags()),
        true,
        'New Doctrine_Collection is saved to cache with key "posts"'
      );

      $t->is(
        null !== ($posts = $sfTagger->get('posts')),
        true,
        '"posts" are successfully fetched from the cache'
      );

      $post = $posts->getFirst();
      $post->setTitle('Row id = ' . $post->getId())->save();

      # $t->comment('Saving post updates');

      $t->is(
        null === ($posts = $sfTagger->get('posts')),
        true,
        'Key expired after editing first post'
      );

      $posts = BlogPostTable::getInstance()->getPostsQuery()->execute();

      $t->is(
        $sfTagger->set('posts', $posts, null, $posts->getTags()),
        true,
        'new "posts" was written to the cache'
      );

      $t->is(
        null === ($posts = $sfTagger->get('posts')),
        false,
        'Fetching "posts" from cache'
      );

      $t->is($sfTagger->lock('posts'), true, 'Locked key "posts"');
      $t->is($sfTagger->isLocked('posts'), true, '"posts" is locked');

      $post = $posts->getLast();
      $post->setTitle('Row id = ' . $post->getId())->save();
      $posts = BlogPostTable::getInstance()->getPostsQuery()->execute();

      $t->is(
        $sfTagger->set('posts', $posts, null, $posts->getTags()),
        false,
        'Skipped writing to cache, "posts" is locked'
      );

      $t->is($sfTagger->unlock('posts'), true, 'Unlocked "posts"');

      $t->is($sfTagger->isLocked('posts'), false, '"posts" is now not locked');

      $t->is(
        $sfTagger->set('posts', $posts, null, $posts->getTags()),
        true,
        'Writing to cache, "posts" is not locked'
      );

      $postsAndComments = BlogPostTable::getInstance()->getPostsWithCommentQuery()->execute();

      foreach ($postsAndComments as $post)
      {
        $postsAndComments->addTags($post->getBlogPostComment());
      }

      $t->is(
        $sfTagger->set('posts+comments', $postsAndComments, null, $postsAndComments->getTags()),
        true,
        'Saving posts with comments'
      );

      $t->is(null !== ($sfTagger->get('posts+comments')), true, '"posts+comments" are stored in cache');

      $table = BlogPostCommentTable::getInstance();

      $wasComments = $table->count();

      $table->findOneByAuthor('marko')->delete();

      $nowComments = $table->count();

      $t->is($wasComments, $nowComments + 1, 'Comments count -1');

      $t->is(null === ($sfTagger->get('posts+comments')), true, '"posts+comments" is expired, 1 comment removed');

      $postsAndComments = BlogPostTable::getInstance()->getPostsWithCommentQuery()->execute();

      foreach ($postsAndComments as $post)
      {
        $postsAndComments->addTags($post->getBlogPostComment());
      }

      $t->is(
        $sfTagger->set('posts+comments', $postsAndComments, null, $postsAndComments->getTags()),
        true,
        'Saving posts with comments'
      );

      $table->createQuery()->addWhere('author = ?', 'david')->delete()->execute();

      $afterDeleteComments = $table->count();

      3 == $afterDeleteComments
        ? $t->pass('Removed all davids comments')
        : $t->fail('Not removed davids comments');

      $t->is(null === ($sfTagger->get('posts+comments')), true, '"posts+comments" is not expired, removed 3 comments');

      $postsAndComments = BlogPostTable::getInstance()->getPostsWithCommentQuery()->execute();

      foreach ($postsAndComments as $post)
      {
        $postsAndComments->addTags($post->getBlogPostComment());
      }

      $t->is(
        $sfTagger->set('posts+comments', $postsAndComments, null, $postsAndComments->getTags()),
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

      $t->is(null === ($sfTagger->get('posts+comments')), true, '"posts+comments" is expired, 3 fruit comment updated');

      $t->diag('Empty Doctrine_Collection tests');

      BlogPostTable::getInstance()->createQuery()->delete()->execute();
      $emptyPosts = BlogPostTable::getInstance()->findAll();

      $t->is(
        $sfTagger->set('posts', $emptyPosts, null, $emptyPosts->getTags()),
        true,
        'Saving empty "posts" to cache'
      );

      $newPost = new BlogPost();
      $newPost->setIsEnabled(true);
      $newPost->setTitle('My Title');
      $newPost->setContent('Content, content, content, content, content');
      $newPost->save();

      $t->is(null === ($sfTagger->get('posts')), true, '"posts" are expired (first post is saved)');

      $posts = BlogPostTable::getInstance()->findAll();
      $t->is(
        $sfTagger->set('posts', $posts, null, $posts->getTags()),
        true,
        'Saving empty "posts" to cache'
      );

      $t->is(null === ($sfTagger->get('posts')), false, '"posts" are not expired (no post was saved during previous save)');

      $t->isa_ok($sfTagger->get('posts'), 'Doctrine_Collection_Cachetaggable', 'Saved object in cache is "Doctrine_Collection_Cachetaggable"');

      BlogPostTable::getInstance()->createQuery()->delete()->execute();
      BlogPostCommentTable::getInstance()->createQuery()->delete()->execute();

      $emptyPosts = BlogPostTable::getInstance()->findAll();
      $emptyPostComments = BlogPostCommentTable::getInstance()->findAll();

      $t->is($emptyPosts->count(), 0, 'All posts are removed');
      $t->is($emptyPostComments->count(), 0, 'All comments are removed');

      # sync post with comments (adding new comment post should be updated)
      $emptyPosts->addTags($emptyPostComments);

      $t->is(
        $sfTagger->set('posts+comments', $emptyPosts, null, $emptyPosts->getTags()),
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

      $t->is(null === ($sfTagger->get('posts+comments')), true, '"posts+comments" are expired (first post is saved)');

      $post = BlogPostTable::getInstance()->find($newPost->getId());
      $post->addTags($post->getBlogPostComment());

      $t->is(
        $sfTagger->set('posts+comments', $post, null, $post->getTags()),
        true,
        'Saving empty "posts+comments" to cache'
      );

      $t->is(null === ($sfTagger->get('posts+comments')), false, '"posts+comments" are not expired (no post/comments was saved during previous save)');

      $newPostComment = new BlogPostComment();
      $newPostComment->setBlogPost($newPost);
      $newPostComment->setAuthor('Fruit');
      $newPostComment->setMessage('My Comment');
      $newPostComment->save();

      $t->is(null === ($sfTagger->get('posts+comments')), true, '"posts+comments" are expired (first associated comment was saved)');
    }
  }

  $cc = new sfCacheClearTask(sfContext::getInstance()->getEventDispatcher(), new sfFormatter());
  $cc->run();
