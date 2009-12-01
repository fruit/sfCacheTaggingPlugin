<?php

/*
 * This file is part of the sfSQLToolsPlugin package.
 * (c) Ilya Sabelnikov <fruit.dev@gmail.com>
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

Doctrine::loadData(PLUGIN_DATA_DIR . '/fixtures/fixtures.yml');

$posts = BlogPostTable::getTable()->getPostsQuery()->execute();

$tagger = sfContext::getInstance()->getViewCacheManager()->getTagger();

$dataCacheSetups = sfYaml::load(PLUGIN_DATA_DIR . '/config/cache_setup.yml');
//print_r($dataCacheSetups);die;
$lockCacheSetups = $dataCacheSetups;

$count = count($dataCacheSetups);

//$t = new lime_test();
$t = new lime_test(pow($count, 2) * 12);

foreach ($dataCacheSetups as $data)
{
  foreach ($lockCacheSetups as $locker)
  {
    $tagger->initialize(array('logging' => true, 'cache' => $data, 'locker' => $locker));
    $tagger->clean();

    $manager = sfContext::getInstance()->getViewCacheManager();
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
  }
}