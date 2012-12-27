<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2012 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  include_once realpath(dirname(__FILE__) . '/../../bootstrap/functional.php');

  $browser = new sfTestFunctional(new sfBrowser());

  $t = $browser->test();

  $cacheManager = sfContext::getInstance()->getViewCacheManager();
  /* @var $cacheManager sfViewCacheTagManager */

  $tagging = $cacheManager->getTaggingCache();
  $con = Doctrine_Manager::getInstance()->getCurrentConnection();


  $con->beginTransaction();
  $truncateQuery = array_reduce(
    array('blog_post','blog_post_comment','blog_post_vote','blog_post_translation'),
    function ($return, $val) { return "{$return} TRUNCATE {$val};"; }, ''
  );

  $cleanQuery = "SET FOREIGN_KEY_CHECKS = 0; {$truncateQuery}; SET FOREIGN_KEY_CHECKS = 1;";
  $con->exec($cleanQuery);
  Doctrine::loadData(sfConfig::get('sf_data_dir') .'/fixtures/blog_post.yml');
  $con->commit();

  $tagging->clean();


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