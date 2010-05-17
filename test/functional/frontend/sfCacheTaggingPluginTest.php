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

  $manager = sfContext::getInstance()->getViewCacheManager();
  $tagger = $manager->getTagger();

  $dataCacheSetups = sfYaml::load(PLUGIN_DATA_DIR . '/config/cache_setup.yml');
  $lockCacheSetups = $dataCacheSetups;

  $count = count($dataCacheSetups);

  # 16 - OutOfRangeException/addTags/removeTags/addTag method tests
  # 34 - tagger/cache tests
  # $count - cache adapter count (cross chechs for tagger cache adapter and locker cache adapter)
  $t = new lime_test(19 + (pow($count, 2) * 34));

  # if precision approach to 0, unit tests will be failed
  # (0 precision is too small for the current test)

  $precisionToTest = array(
    array('value' => -1, 'throwException' => true),
    array('value' =>  0, 'throwException' => false),
    array('value' =>  3, 'throwException' => false),
    array('value' =>  6, 'throwException' => false),
    array('value' =>  7, 'throwException' => true),
  );

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
  $manager->addTags($posts);
  $t->is($manager->getTags(), $postCollectionTag, 'Tags stored in manager are full/same');
  $manager->addTags(array('SomeTag' => 1234567890));
  $t->is($manager->getTags(), array_merge(array('SomeTag' => 1234567890), $postCollectionTag), 'Tags with new tag are successfully saved');
  $manager->clearTags();
  $t->is($manager->getTags(), array(), 'All tags are cleared');

  foreach ($dataCacheSetups as $data)
  {
    foreach ($lockCacheSetups as $locker)
    {
      Doctrine::loadData(PLUGIN_DATA_DIR . '/fixtures/fixtures.yml');
      $posts = BlogPostTable::getInstance()->getPostsQuery()->execute();

      $tagger->initialize(array('logging' => true, 'cache' => $data, 'locker' => $locker));
      $tagger->clean();

      $manager->initialize($manager->getContext(), $tagger, $manager->getOptions());

      $t->comment(sprintf(
        'Setuping new configuration (data: "%s", locker: "%s")',
        get_class($tagger->getDataCache()),
        get_class($tagger->getLockerCache())
      ));

      $t->is($tagger->get('posts'), false, 'cache is NOT empty');

      $t->is(
        $tagger->set('posts', $posts, null, $posts->getTags()),
        true,
        'New Doctrine_Collection is saved to cache with key "posts"'
      );

      $t->is(
        null !== ($posts = $tagger->get('posts')),
        true,
        '"posts" are successfully fetched from the cache'
      );

      $post = $posts->getFirst();
      $post->setTitle('Row id = ' . $post->getId())->save();

      # $t->comment('Saving post updates');

      $t->is(
        null === ($posts = $tagger->get('posts')),
        true,
        'Key expired after editing first post'
      );

      $posts = BlogPostTable::getInstance()->getPostsQuery()->execute();

      $t->is(
        $tagger->set('posts', $posts, null, $posts->getTags()),
        true,
        'new "posts" was written to the cache'
      );

      $t->is(
        null === ($posts = $tagger->get('posts')),
        false,
        'Fetching "posts" from cache'
      );

      $t->is($tagger->lock('posts'), true, 'Locked key "posts"');
      $t->is($tagger->isLocked('posts'), true, '"posts" is locked');

      $post = $posts->getLast();
      $post->setTitle('Row id = ' . $post->getId())->save();
      $posts = BlogPostTable::getInstance()->getPostsQuery()->execute();

      $t->is(
        $tagger->set('posts', $posts, null, $posts->getTags()),
        false,
        'Skipped writing to cache, "posts" is locked'
      );

      $t->is($tagger->unlock('posts'), true, 'Unlocked "posts"');

      $t->is($tagger->isLocked('posts'), false, '"posts" is now not locked');

      $t->is(
        $tagger->set('posts', $posts, null, $posts->getTags()),
        true,
        'Writing to cache, "posts" is not locked'
      );

      $postsAndComments = BlogPostTable::getInstance()->getPostsWithCommentQuery()->execute();

      foreach ($postsAndComments as $post)
      {
        $postsAndComments->addTags($post->getBlogPostComment());
      }

      $t->is(
        $tagger->set('posts+comments', $postsAndComments, null, $postsAndComments->getTags()),
        true,
        'Saving posts with comments'
      );

      $t->is(null !== ($tagger->get('posts+comments')), true, '"posts+comments" are stored in cache');

      $table = BlogPostCommentTable::getInstance();

      $wasComments = $table->count();

      $table->findOneByAuthor('marko')->delete();

      $nowComments = $table->count();

      $t->is($wasComments, $nowComments + 1, 'Comments count -1');

      $t->is(null === ($tagger->get('posts+comments')), true, '"posts+comments" is expired, 1 comment removed');

      $postsAndComments = BlogPostTable::getInstance()->getPostsWithCommentQuery()->execute();

      foreach ($postsAndComments as $post)
      {
        $postsAndComments->addTags($post->getBlogPostComment());
      }

      $t->is(
        $tagger->set('posts+comments', $postsAndComments, null, $postsAndComments->getTags()),
        true,
        'Saving posts with comments'
      );

      $table->createQuery()->addWhere('author = ?', 'david')->delete()->execute();

      $afterDeleteComments = $table->count();

      3 == $afterDeleteComments
        ? $t->pass('Removed all davids comments')
        : $t->fail('Not removed davids comments');

      $t->is(null === ($tagger->get('posts+comments')), true, '"posts+comments" is not expired, removed 3 comments');

      $postsAndComments = BlogPostTable::getInstance()->getPostsWithCommentQuery()->execute();

      foreach ($postsAndComments as $post)
      {
        $postsAndComments->addTags($post->getBlogPostComment());
      }

      $t->is(
        $tagger->set('posts+comments', $postsAndComments, null, $postsAndComments->getTags()),
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

      $t->is(null === ($tagger->get('posts+comments')), true, '"posts+comments" is expired, 3 fruit comment updated');

      $t->diag('Empty Doctrine_Collection tests');

      BlogPostTable::getInstance()->findAll()->delete();
      $emptyPosts = BlogPostTable::getInstance()->findAll();

      $t->is(
        $tagger->set('posts', $emptyPosts, null, $emptyPosts->getTags()),
        true,
        'Saving empty "posts" to cache'
      );

      $newPost = new BlogPost();
      $newPost->setIsEnabled(true);
      $newPost->setTitle('My Title');
      $newPost->setContent('Content, content, content, content, content');
      $newPost->save();

      $t->is(null === ($tagger->get('posts')), true, '"posts" are expired (first post is saved)');

      $posts = BlogPostTable::getInstance()->findAll();
      $t->is(
        $tagger->set('posts', $posts, null, $posts->getTags()),
        true,
        'Saving empty "posts" to cache'
      );

      $t->is(null === ($tagger->get('posts')), false, '"posts" are not expired (no post was saved during previous save)');

      $t->isa_ok($tagger->get('posts'), 'Doctrine_Collection_Cachetaggable', 'Saved object in cache is "Doctrine_Collection_Cachetaggable"');

      BlogPostTable::getInstance()->findAll()->delete();
      BlogPostCommentTable::getInstance()->findAll()->delete();

      $emptyPosts = BlogPostTable::getInstance()->findAll();
      $emptyPostComments = BlogPostCommentTable::getInstance()->findAll();

      $t->is($emptyPosts->count(), 0, 'All posts are removed');
      $t->is($emptyPostComments->count(), 0, 'All comments are removed');

      # sync post with comments (adding new comment post should be updated)
      $emptyPosts->addTags($emptyPostComments);

      $t->is(
        $tagger->set('posts+comments', $emptyPosts, null, $emptyPosts->getTags()),
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

      $t->is(null === ($tagger->get('posts+comments')), true, '"posts+comments" are expired (first post is saved)');

      $post = BlogPostTable::getInstance()->find($newPost->getId());
      $post->addTags($post->getBlogPostComment());

      $t->is(
        $tagger->set('posts+comments', $post, null, $post->getTags()),
        true,
        'Saving empty "posts+comments" to cache'
      );

      $t->is(null === ($tagger->get('posts+comments')), false, '"posts+comments" are not expired (no post/comments was saved during previous save)');

      $newPostComment = new BlogPostComment();
      $newPostComment->setBlogPost($newPost);
      $newPostComment->setAuthor('Fruit');
      $newPostComment->setMessage('My Comment');
      $newPostComment->save();

      $t->is(null === ($tagger->get('posts+comments')), true, '"posts+comments" are expired (first associated comment was saved)');
    }
  }