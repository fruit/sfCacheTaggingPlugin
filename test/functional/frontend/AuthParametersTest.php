<?php

  global $globalTest;
  global $globalCallArgCount;

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2013 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */
  include_once realpath(dirname(__FILE__) . '/../../bootstrap/functional.php');

  class TestAuthParamUser extends sfBasicSecurityUser {}

  class TestIllegalUser
  {
    public function isAuthenticated ()
    {
      return true;
    }
  }

  $browser = new sfTestFunctional(new sfBrowser());
  $t = $browser->test();
  $ctx = sfContext::getInstance();
  $ed = $ctx->getEventDispatcher();

  define('NS_CACHE', 'cache.filter_cache_keys');

  $cm = $ctx->getViewCacheManager();
  /* @var $cm sfViewCacheTagManager */

  $t->diag('generateCacheKey');
  # we work with required cache manager
  $t->isa_ok($cm, 'sfViewCacheTagManager', 'Cache manager is sfViewCacheTagManager');

  $t->diag('User class checks');
  # Fake user class
  $ctx->set('user', new TestIllegalUser());
  $t->is($cm->generateCacheKey('store/top_items?page=1'), '/all/store/top_items/page/1', 'User is not instance of "sfSecurityUser"');
  # not authenticated
  $ctx->set('user', new TestAuthParamUser($ed, $ctx->getStorage()));
  $t->is($cm->generateCacheKey('store/top_items?page=1'), '/all/store/top_items/page/1', 'Missing shop_id, user is not authenticated');
  # all correct, add custom parameter
  $options = array ('logging' => 0, 'culture' => 'en_GB', 'default_culture' => 'en', );
  $authenticatedUser = new TestAuthParamUser($ed, $ctx->getStorage(), $options);
  $authenticatedUser->setAuthenticated(true);
  $ctx->set('user', $authenticatedUser);


  $t->diag(sprintf('Check filter "%s" available parameters', NS_CACHE));
  $ed->connect(NS_CACHE, $lambda = function (sfEvent $event, array $params) {
    global $globalTest;
    global $globalCallArgCount;

    $globalTest->ok(is_array($params) && 0 == count($params), 'Initial params array is empty');
    $globalTest->ok(isset($event['cache_type']), 'Key "cache_type" exists');
    $globalTest->ok(isset($event['view_cache']), 'Key "view_cache" exists');
    $globalTest->isa_ok($event['view_cache'], 'sfViewCacheTagManager', 'view_cache is passed and type is sfViewCacheTagManager');
    $globalTest->ok(isset($event['call_args']) && is_array($event['call_args']), 'Key "call_args" exists and is array');
    $globalTest->is(count($event['call_args']), $globalCallArgCount, "{$globalCallArgCount} args passed to generateCacheKey()");

    return array();
  });
  $globalTest = $t;
  $globalCallArgCount = 1;
  $val = $cm->generateCacheKey('my_module/my_action'); // trigger filter call
  $t->is($val, '/all/my_module/my_action', "[$val]: Checking call with {$globalCallArgCount} arguments");
  $globalCallArgCount = 2;
  $val = $cm->generateCacheKey('my_module/my_action', 'localhost'); // trigger filter call
  $t->is($val, '/localhost/all/my_module/my_action', "[$val]: Checking call with {$globalCallArgCount} arguments");
  $globalCallArgCount = 3;
  $val = $cm->generateCacheKey('my_module/my_action', 'localhost', 'Vary-Param=1'); // trigger filter call
  $t->is($val, '/localhost/Vary-Param=1/my_module/my_action', "[$val]: Checking call with {$globalCallArgCount} arguments");
  $globalCallArgCount = 4;
  $val = $cm->generateCacheKey(
    '@sf_cache_partial?module=my_module&action=_partial_contextual', 'localhost', 'Vary-Param=1', '_rev_'
  ); // trigger filter call
  $t->is($val, '/localhost/Vary-Param=1/sf_cache_partial/my_module/__partial_contextual', "[$val]: Checking call with {$globalCallArgCount} arguments");
  $ed->disconnect(NS_CACHE, $lambda);
  unset($globalTest, $globalCallArgCount);



  $t->diag('generateCacheKey simple results');
  $ed->connect(NS_CACHE, $lambda = function (sfEvent $event, array $params) {
    return array();
  });
  $t->is($cm->generateCacheKey('social/wall'), '/all/social/wall', 'clear, nothing custom to append');
  $t->is($cm->generateCacheKey('social/wall?username=voex'), '/all/social/wall/username/voex', '(action) GET args, nothing custom to append');
  $t->is($cm->generateCacheKey(
    '@sf_cache_partial?module=x&action=_y'),
    '/all/sf_cache_partial/x/__y',
    '(partial/component) clear, nothing custom to append'
  );
  $t->is($cm->generateCacheKey(
    '@sf_cache_partial?module=x&action=_y&domain=.example.com'),
    '/all/sf_cache_partial/x/__y/domain/_.example.com',
    '(partial/component) GET args, nothing custom to append'
  );
  $ed->disconnect(NS_CACHE, $lambda);



  $t->diag('generateCacheKey with one allowed parameter');
  $ed->connect(NS_CACHE, $lambda = function (sfEvent $event, array $params) {
    return array_merge($params, array('shop_id' => 491));
  });
  $t->is(
    $cm->generateCacheKey('store/top_items?page=1'),
    '/all/store/top_items/page/1/shop_id/491',
    'Appended "shop_id" parameter to "action" key'
  );
  $t->is(
    $cm->generateCacheKey('@sf_cache_partial?module=blog_post&action=_partial_example&sf_cache_key=xyz'),
    '/all/sf_cache_partial/blog_post/__partial_example/sf_cache_key/xyz/shop_id/491',
    'Appended "shop_id" parameter to "partial/component" key'
  );
  $ed->disconnect(NS_CACHE, $lambda);



  $t->diag('"module", "site_id" is default arguments, and shouldn\'t be rewrited');
  $ed->connect(NS_CACHE, $lambda = function (sfEvent $event, array $params) {
    return array_merge($params, array('site_id' => 39, 'country_code' => 'LV', 'time_zone' => 'GMT', 'module' => 'admin'));
  });
  $t->is(
    $cm->generateCacheKey('contacts/create?site_id=10&anumbers[]=9129188212&anumbers[]=851981212'),
    '/all/contacts/create/anumbers[0]/9129188212/anumbers[1]/851981212/country_code/LV/site_id/10/time_zone/GMT',
    'Ignoring protected parameters (parameter order based on the ksort result)'
  );
  $t->is(
    $cm->generateCacheKey('@sf_cache_partial?module=blog_post&action=_partial_example&sf_cache_key=xyz'),
    '/all/sf_cache_partial/blog_post/__partial_example/country_code/LV/sf_cache_key/xyz/site_id/39/time_zone/GMT',
    'Param "module" is not rewrited'
  );
  $ed->disconnect(NS_CACHE, $lambda);



  # numeric params
  $ed->connect(NS_CACHE, $lambda = function (sfEvent $event, array $params) {
    return array_merge($params, array('shell' => 'yes', 'manager', 771, 'site' => 'example.com'));
  });
  $t->is(
    $cm->generateCacheKey('search/query'),
    '/all/search/query/param_0/manager/param_1/771/shell/yes/site/example.com',
    'Checking param_# values'
  );
  $ed->disconnect(NS_CACHE, $lambda);


  $t->diag('Cache types');
  # Page
  $ed->connect(NS_CACHE, $lambda = function (sfEvent $event, array $params) use ($t) {
    $t->is($event['cache_type'], sfViewCacheTagManager::NAMESPACE_PAGE, 'Cache type is a page');
    return array_merge($params, array('cache_folder' => 'stage'));
  });
  $t->is($cm->generateCacheKey('blog_post/actionWithLayout?review_id=102'), '/all/blog_post/actionWithLayout/cache_folder/stage/review_id/102');
  $ed->disconnect(NS_CACHE, $lambda);

  # Partial
  $ed->connect(NS_CACHE, $lambda = function (sfEvent $event, array $params) use ($t) {
    $t->is($event['cache_type'], sfViewCacheTagManager::NAMESPACE_PARTIAL, 'Cache type is a partial');
    return array_merge($params, array('cache_folder' => 'stage'));
  });
  $t->is($cm->generateCacheKey('@sf_cache_partial?module=x&action=_y'), '/all/sf_cache_partial/x/__y/cache_folder/stage');
  $ed->disconnect(NS_CACHE, $lambda);

  # Action
  $ed->connect(NS_CACHE, $lambda = function (sfEvent $event, array $params) use ($t) {
    $t->is($event['cache_type'], sfViewCacheTagManager::NAMESPACE_ACTION, 'Cache type is an action');
    return array_merge($params, array('cache_folder' => 'stage'));
  });
  $t->is($cm->generateCacheKey('blog_post/actionWithBlocks?review_id=102'), '/all/blog_post/actionWithBlocks/cache_folder/stage/review_id/102');
  $ed->disconnect(NS_CACHE, $lambda);


