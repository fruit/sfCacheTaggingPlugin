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

$t = new lime_test();

//Doctrine::loadData(realpath(dirname(__FILE__) . '/../data/fixtures/fixtures.yml'));

$posts = BlogPostTable::getTable()->getPostsQuery()->execute();

$cache = sfContext::getInstance()->getViewCacheManager()->getTagger();

$cache->getBackend()->flush();

//$m = $cache->getBackend();
//
//$t->diag('Testing a MemcacheLock ->lock() method');
//
//$t->info('setting a lock');
//if ($m->add('lock-my', 1, false, 100))
//{
//  $t->pass('setted!');
//
//  $t->info('test is locked?');
//
//  if ($m->add('lock-my', 2, false, 10))
//  {
//    $t->fail('not locked');
//  }
//  else
//  {
//    $t->pass('Locked');
//  }
//
//  $t->is($m->get('lock-my'), 1, 'lock value is = 1');
//}
//die;
//$t->diag('Testing a MemcacheLock ->lock() method');
//$t->info('setting a lock');
//if ($m->add('unlock-my', 1, false, 100))
//{
//  $t->pass('setted!');
//
//  $t->info('test is locked?');
//
//  if ($m->add('unlock-my', 2, false, 10))
//  {
//    $t->fail('not locked');
//  }
//  else
//  {
//    $t->pass('Locked');
//  }
//
//  $t->is($m->get('lock-my'), 1, 'lock value is = 1');
//}

if ($cache->get('posts'))
{
  $t->fail('Posts from cache after flushing mm');
  die;
}

if (! $cache->set('posts', $posts, null, $posts->getTags()))
{
  $t->fail('Something goes wrong, $cache->set() is failed');
  die;
}

$post = $posts->getFirst();
$post->setTitle('Row id = ' . $post->getId())->save();

if (! is_null($posts = $cache->get('posts')))
{
  $t->fail('cache is valid, but should be invalid');
  die;
}

$t->pass('Posts should be updated - no valid cache is there');

$posts = BlogPostTable::getTable()->getPostsQuery()->execute();

//print_r($posts->getTags());die;

if (! $cache->set('posts', $posts, null, $posts->getTags()))
{
  $t->fail('could not set new posts to mm');
  die;
}

$t->pass('New posts setted to mm');

if (is_null($posts = $cache->get('posts')))
{
  $t->fail('Posts are expired');
  die;
}

$t->pass('Getting new posts from cache');