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

  $browser->getAndCheck('blog_post', 'actionWithoutLayout', '/blog_post/actionWithoutLayout', 200);

  $browser
    ->with('response')
    ->begin()
    ->checkElement('.posts a[id*="foo"]', 'Foo')
    ->checkElement('.posts a[id*="baz"]', 'Baz')
    ->end();

  $browser->click('a#baz');
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
    ->checkElement('.posts a[id*="foo"]', 'Foo')
    ->checkElement('.posts a[id*="baz"]', 'Baz_new')
    ->end();


  $post = BlogPostTable::getInstance()->findOneBySlug('baz');

  $post->setTitle('Baz_new_fresh')->save();

  $browser->getAndCheck('blog_post', 'actionWithoutLayout', '/blog_post/actionWithoutLayout', 200);

  $browser
    ->with('response')
    ->begin()
    ->isStatusCode(200)
    ->checkElement('.posts a[id*="baz"]', 'Baz_new_fresh')
    ->end();

  BlogPostTable::getInstance()->getConnection()->rollback();