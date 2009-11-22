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

sfConfig::set('sf_cache', 0);
Doctrine::loadData(realpath(dirname(__FILE__) . '/../data/fixtures/fixtures.yml'));
sfConfig::set('sf_cache', 1);


$posts = BlogPostTable::getTable()->getPostsQuery()->execute();

$cache = sfContext::getInstance()->getViewCacheManager()->getCache();
//$cache->getBackend()->flush();

if ($cache->set('posts', $posts, null, $posts->getTags()))
{
  sleep(1);
  $post = $posts->getFirst();
  $post->setTitle('My new title for row ' . $post->getId())->save();
}

if (is_null($posts = $cache->get('posts')))
{
  $t->pass('Posts should be updated - no valid cache is there');

  $posts = BlogPostTable::getTable()->getPostsQuery()->execute();

  if ($cache->set('posts', $posts, null, $posts->getTags()))
  {
    $t->pass('New posts setted to mm');
  }
  else
  {
    $t->fail('could not set new posts to mm');
  }
}
else
{
  $t->fail('cache is valid, but should be invalid');
}
