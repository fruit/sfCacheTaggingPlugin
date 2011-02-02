<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  require_once realpath(dirname(__FILE__) . '/../../../../../test/bootstrap/functional.php');

  BlogPostTable::getInstance()->getConnection()->beginTransaction();

  $browser = new sfTestFunctional(new sfBrowser());

  $browser->getAndCheck('blog_post', 'actionWithLayout', '/blog_post/actionWithLayout', 200);

  $browser
    ->with('response')
    ->begin()
    ->checkElement('.posts a[id*="foo"]', 'Foo')
    ->checkElement('.posts a[id*="bar"]', 'Bar')
    ->end();

  $browser->click('a#foo');
  $browser->isForwardedTo('blog_post', 'updateBlogPost');

  $browser
    ->with('response')
    ->begin()
    ->isStatusCode(302)
    ->isRedirected()
    ->end();

  $browser->followRedirect();

  $browser
    ->with('response')
    ->begin()
    ->isStatusCode(200)
    ->checkElement('.posts a[id*="foo"]', 'Foo_new')
    ->checkElement('.posts a[id*="bar"]', 'Bar')
    ->end();


  $post = BlogPostTable::getInstance()->findOneBySlug('bar');

  $post->setTitle('BarBar')->save();

  $browser->getAndCheck('blog_post', 'actionWithLayout', '/blog_post/actionWithLayout', 200);

  $browser
    ->with('response')
    ->begin()
    ->isStatusCode(200)
    ->checkElement('.posts a[id*="bar"]', 'BarBar')
    ->end();

  BlogPostTable::getInstance()->getConnection()->rollback();