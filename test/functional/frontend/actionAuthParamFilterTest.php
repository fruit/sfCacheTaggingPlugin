<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2013 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  include_once realpath(dirname(__FILE__) . '/../../bootstrap/functional.php');

  $browser = new sfTestFunctional(new sfBrowser());

  $t = $browser->test();

  $sfContext = sfContext::getInstance();
  $sfContext->getConfiguration()->loadHelpers(array('Partial'));
  $cacheManager = $sfContext->getViewCacheManager();

  $username = 'neo';
  $userId = 5919;

  $browser
    ->get('/blog_post/signIn')
    ->with('user')->begin()->isAuthenticated(false)->end()
    ->post('/blog_post/signIn', array('username' => $username, 'password' => '***'))
    ->with('response')->begin()->isRedirected(true)->end()
    ->with('user')->begin()->isAuthenticated(true)->end()
    ->followRedirect()
    ->with('request')->begin()
      ->isParameter('module', 'blog_post')
      ->isParameter('action', 'welcome')
    ->end()
  ;

  $browser
    ->with('view_cache')
    ->begin()
      ->isCached(true, false)
      ->isUriCached($correctUrl = "blog_post/welcome?user_id={$userId}", true, false)
      ->isUriCached('blog_post/welcome?user_id=00000000', false, false)
    ->end()
  ;

  list($output, $layoutFile) = $cacheManager->getActionCache($correctUrl);

  $t->is($output, "Username: {$username} ($userId)", "Welcome text matches '{$output}'!");

