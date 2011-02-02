<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  require_once realpath(dirname(__FILE__) . '/../../../../../test/bootstrap/functional.php');

  $browser = new sfTestFunctional(new sfBrowser());

  $browser->getAndCheck('blog_post', 'actionWithBlocks', '/blog_post/actionWithBlocks', 200);

  $browser
    ->with('view_cache')
    ->begin()
    ->isUriCached(
      '@sf_cache_partial?' . 
        'module=blog_post&' .
        'action=_ten_posts_partial_cached&' .
        'sf_cache_key=index-page-ten-posts-enabled-partial',
      true, false
    )
    ->isUriCached(
      '@sf_cache_partial?' .
        'module=blog_post&' .
        'action=_ten_posts_partial_not_cached&' .
        'sf_cache_key=index-page-ten-posts-disabled-partial',
      false, false
    )
    ->isUriCached(
      '@sf_cache_partial?' .
        'module=blog_post&' .
        'action=_tenPostsComponentCached&' .
        'sf_cache_key=index-page-ten-posts-enabled-component',
      true, false
    )
    ->isUriCached(
      '@sf_cache_partial?' .
        'module=blog_post&' .
        'action=_tenPostsComponentNotCached&' .
        'sf_cache_key=index-page-ten-posts-disabled-component',
      false, false
    )
    ->end();

  $browser->getAndCheck('blog_post', 'actionWithLayout', '/blog_post/actionWithLayout', 200);

  $browser
    ->with('view_cache')
    ->begin()
    ->isCached(true, true)
    ->end();

  $browser->getAndCheck('blog_post', 'actionWithoutLayout', '/blog_post/actionWithoutLayout', 200);

  $browser
    ->with('view_cache')
    ->begin()
    ->isCached(true, false)
    ->end();

  $browser->getAndCheck('blog_post', 'actionWithDisabledCache', '/blog_post/actionWithDisabledCache', 200);

  $browser
    ->with('view_cache')
    ->begin()
    ->isCached(false, false)
    ->end();