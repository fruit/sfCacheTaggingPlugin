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

Doctrine::loadData(realpath(dirname(__FILE__) . '/../data/fixtures/fixtures.yml'));
$posts = BlogPostTable::getTable()->getPostsQuery()->execute();

foreach ($posts as $post)
{
  print $post->getUpdatedAt() . "\n";
//  die;
//  print $post->getTagName() . "\n";

  die;
}

print_r($posts->getTags());

$b = new BlogPost();
$b->setTitle('Heeelo');
$b->save();


die;

$cache->getBackend()->flush();

$num = rand(10000, 99999);

if ($cache->get('posts'))
{
  $t->fail($num . ': Key exists');
}
else
{
  $t->pass($num . ': flush done, no keys available');

  if ($cache->set('posts', $posts, null, $tags = get_tags($posts, 'posts')))
  {
    $t->pass($num . ': posts saved to mm with tags (' . implode(', ', array_keys($tags)) . ')');

    if ($posts = $cache->get('posts'))
    {
      $t->pass($num . ': posts is not expired');
    }

    $posts[1]['name'] = 'AAA';
    $cache->setTag('posts_1', time());

    if ($cache->get('posts'))
    {
      $t->fail($num . ': post_1 tag changed, but posts is not expired');
    }
    else
    {
      $t->pass($num . ': post is expired');

      if ($cache->set('posts', $posts, null, get_tags($posts, 'posts')))
      {
        $t->pass($num . ': ok, rebuilding posts in mm');

        if ($cache->get('posts'))
        {
          $t->pass($num . ': post is cached');
        }
        else
        {
          $t->fail($num . ': failed to get posts from mm');
        }
      }
      else
      {
        $t->fail($num . ': could not update posts');
      }
    }
  }
  else
  {
    $t->fail($num . ': failed to set posts into mm');
  }
}


