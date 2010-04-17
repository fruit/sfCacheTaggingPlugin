<?php

/*
 * This file is part of the sfCacheTaggingPlugin package.
 * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$app = 'frontend';
$debug = true;
require_once dirname(__FILE__) . './../../../../test/bootstrap/functional.php';

require_once sfConfig::get('sf_symfony_lib_dir') . '/vendor/lime/lime.php';

//$autoload = sfSimpleAutoload::getInstance();
//$autoload->addDirectory(realpath(dirname(__FILE__) . '/../../lib'));
//$autoload->register();

define('PLUGIN_DATA_DIR', realpath(dirname(__FILE__) . '/../data'));

$manager = sfContext::getInstance()->getViewCacheManager();
$tagger = $manager->getTagger();

$dataCacheSetups = sfYaml::load(PLUGIN_DATA_DIR . '/config/cache_setup.yml');
$lockCacheSetups = $dataCacheSetups;

$count = count($dataCacheSetups);

# 11 - addTags/removeTags/addTag method tests
# 34 - tagger/cache tests
# $count - cache adapter count (cross chechs for tagger cache adapter and locker cache adapter)
$t = new lime_test(11 + (pow($count, 2) * 34));

# if precision approach to 0, unit tests will be failed
# (0 precision is too small for the current test)
sfConfig::set('app_sfcachetaggingplugin_microtime_precision', 5);


$posts = BlogPostTable::getTable()->findAll();
$posts->delete();

$postComments = BlogPostCommentTable::getTable()->findAll();
$postComments->delete();

$postTagKey = BlogPostTable::getTable()->getClassnameToReturn();
$postCommentTagKey = BlogPostCommentTable::getTable()->getClassnameToReturn();

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

foreach ($dataCacheSetups as $data)
{
  foreach ($lockCacheSetups as $locker)
  {
    Doctrine::loadData(PLUGIN_DATA_DIR . '/fixtures/fixtures.yml');
    $posts = BlogPostTable::getTable()->getPostsQuery()->execute();

    $tagger->initialize(array('logging' => true, 'cache' => $data, 'locker' => $locker));
    $tagger->clean();

    $manager->initialize($manager->getContext(), $tagger, $manager->getOptions());

    $t->comment(sprintf(
      'Setuping new configuration (data: "%s", locker: "%s")',
      get_class($tagger->getCache()),
      get_class($tagger->getLocker())
    ));

    $t->is($tagger->get('posts'), false, 'cache is NOT empty');

    $t->is(
      $tagger->set('posts', $posts, null, $posts->getTags()),
      true,
      'New Doctrine_Collection is saved to cache with key "posts"'
    );

    $t->is(
      ! is_null($posts = $tagger->get('posts')),
      true, 
      '"posts" are successfully fetched from the cache'
    );

    $post = $posts->getFirst();
    $post->setTitle('Row id = ' . $post->getId())->save();

    # $t->comment('Saving post updates');

    $t->is(
      is_null($posts = $tagger->get('posts')),
      true, 
      'Key expired after editing first post'
    );

    $posts = BlogPostTable::getTable()->getPostsQuery()->execute();

    $t->is(
      $tagger->set('posts', $posts, null, $posts->getTags()),
      true,
      'new "posts" was written to the cache'
    );

    $t->is(
      is_null($posts = $tagger->get('posts')),
      false,
      'Fetching "posts" from cache'
    );

    $t->is($tagger->lock('posts'), true, 'Locked key "posts"');
    $t->is($tagger->isLocked('posts'), true, '"posts" is locked');

    $post = $posts->getLast();
    $post->setTitle('Row id = ' . $post->getId())->save();
    $posts = BlogPostTable::getTable()->getPostsQuery()->execute();

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

    $postsAndComments = BlogPostTable::getTable()->getPostsWithCommentQuery()->execute();

    foreach ($postsAndComments as $post)
    {
      $postsAndComments->addTags($post->getBlogPostComment());
    }

    $t->is(
      $tagger->set('posts+comments', $postsAndComments, null, $postsAndComments->getTags()),
      true,
      'Saving posts with comments'
    );

    $t->is(! is_null($tagger->get('posts+comments')), true, '"posts+comments" are stored in cache');

    $table = Doctrine::getTable('BlogPostComment');

    $wasComments = $table->count();

    $table->findOneByAuthor('marko')->delete();
    
    $nowComments = $table->count();

    $t->is($wasComments, $nowComments + 1, 'Comments count -1');

    $t->is(is_null($tagger->get('posts+comments')), true, '"posts+comments" is expired, 1 comment removed');

    $postsAndComments = BlogPostTable::getTable()->getPostsWithCommentQuery()->execute();

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

    $t->is(is_null($tagger->get('posts+comments')), true, '"posts+comments" is not expired, removed 3 comments');

    $postsAndComments = BlogPostTable::getTable()->getPostsWithCommentQuery()->execute();

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

    $t->is(is_null($tagger->get('posts+comments')), true, '"posts+comments" is expired, 3 fruit comment updated');

    $t->diag('Empty Doctrine_Collection tests');

    BlogPostTable::getTable()->findAll()->delete();
    $emptyPosts = BlogPostTable::getTable()->findAll();

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

    $t->is(is_null($tagger->get('posts')), true, '"posts" are expired (first post is saved)');

    $posts = BlogPostTable::getTable()->findAll();
    $t->is(
      $tagger->set('posts', $posts, null, $posts->getTags()),
      true,
      'Saving empty "posts" to cache'
    );

    $t->is(is_null($tagger->get('posts')), false, '"posts" are not expired (no post was saved during previous save)');

    $t->isa_ok($tagger->get('posts'), 'Doctrine_Collection_Cachetaggable', 'Saved object in cache is "Doctrine_Collection_Cachetaggable"');

    BlogPostTable::getTable()->findAll()->delete();
    BlogPostCommentTable::getTable()->findAll()->delete();

    $emptyPosts = BlogPostTable::getTable()->findAll();
    $emptyPostComments = BlogPostCommentTable::getTable()->findAll();
    
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

    $t->is(is_null($tagger->get('posts+comments')), true, '"posts+comments" are expired (first post is saved)');

    $post = BlogPostTable::getTable()->find($newPost->getId());
    $post->addTags($post->getBlogPostComment());

    $t->is(
      $tagger->set('posts+comments', $post, null, $post->getTags()),
      true,
      'Saving empty "posts+comments" to cache'
    );

    $t->is(is_null($tagger->get('posts+comments')), false, '"posts+comments" are not expired (no post/comments was saved during previous save)');

    $newPostComment = new BlogPostComment();
    $newPostComment->setBlogPost($newPost);
    $newPostComment->setAuthor('Fruit');
    $newPostComment->setMessage('My Comment');
    $newPostComment->save();

    $t->is(is_null($tagger->get('posts+comments')), true, '"posts+comments" are expired (first associated comment was saved)');
  }
}