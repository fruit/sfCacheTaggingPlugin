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

  $sfContext = sfContext::getInstance();
  $cacheManager = $sfContext->getViewCacheManager();
  /* @var $cacheManager sfViewCacheTagManager */
  $tagging = $cacheManager->getTaggingCache();
  /* @var $tagging sfTaggingCache */
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


  define('SF_VIEW_CACHE_MANAGER_EVENT_NAME', 'view.cache.filter_content');

  $sfEventDispatcher = $sfContext->getEventDispatcher();

  $t = $browser->test();

  $actionCacheCheck = array(
    #     uri                                   is_cacheable  has_layout
    array('/blog_post/actionWithLayout',        true,         true,   ),
    array('/blog_post/actionWithoutLayout',     true,         false,  ),
    array('/blog_post/actionWithDisabledCache', false,        false,  ),
  );

  foreach ($actionCacheCheck as $action)
  {
    list($internalUri, $is_cacheable, $has_layout) = $action;

    $t->is($cacheManager->withLayout($internalUri), $has_layout, sprintf('w/o layout "%s" -%b', $internalUri, $has_layout));

    $t->is(
      $cacheManager->isCacheable($internalUri),
      $is_cacheable,
      sprintf('uri "%s" with enabled cache', $internalUri)
    );

    $t->is(
      $cacheManager->set('mycontent', $internalUri, array('A' => 123123123)),
      $is_cacheable,
      sprintf(
        'done on setting content on cacheable uri "%s"',
        $internalUri
      )
    );

    $serializedContent = $cacheManager->get($internalUri);

    $t->is(
      $serializedContent,
      $is_cacheable ? 's:9:"mycontent";' : 'N;',
      sprintf(
        'sfViewCacheManager->get("%s") returns "%s"',
        $internalUri,
        var_export($serializedContent, true)
      )
    );
  }


  # ->decorateContentWithDebug()
  $t->diag('->decorateContentWithDebug()');

  $response = $sfContext->getResponse();

  $response->setContent('Existing Content');

  $event = new sfEvent(
    'subject',
    'view.cache.filter_content',
    array(
      'response' => $response,
      'uri' => '/blog_post/actionWithDisabledLayout',
      'new' => true,
    )
  );


  $t->is($cacheManager->decorateContentWithDebug($event, ''), '');

  $output = $cacheManager->decorateContentWithDebug($event, 'MyTempContent');

  $t->is($output, 'MyTempContent', 'Cache is not a object(CacheMetadata), return not decorated content');


  # (set|get)ActionCache

  $t->diag('(set|get)ActionCache');

  $sfWebDebug = sfConfig::get('sf_web_debug');
  sfConfig::set('sf_web_debug', true);

  $cacheManager->getEventDispatcher()->connect(
    'view.cache.filter_content',
    array($cacheManager, 'decorateContentWithDebug')
  );

  $t->is($cacheManager->getActionCache('/blog_post/actionWithDisabledLayout'), null);

  $cacheManager->getContentTagHandler()->setContentTag('Magnolia_731', 9127561923, sfViewCacheTagManager::NAMESPACE_ACTION);

  $match = '/ContentActionText.*cache.*tags.*9127561923.*Magnolia_731/';

  $layout = sfConfig::get('sf_root_dir') . '/apps/frontend/templates/layout.php';

  $t->like(
    $cacheManager->setActionCache('blog_post/actionWithoutLayout', 'ContentActionText&nbsp;<br />&nbsp;', $layout),
    $match
  );

  list($paramContent, $paramLayout) = $cacheManager->getActionCache('/blog_post/actionWithoutLayout');

  $t->is($paramLayout, $layout);

  $t->like($paramContent, $match);

  sfConfig::set('sf_web_debug', $sfWebDebug);

  $cacheManager->getContentTagHandler()->removeContentTags(sfViewCacheTagManager::NAMESPACE_ACTION);


  # (set|get)PageCache


  $t->diag('(set|get)PageCache');

  $sfWebDebug = sfConfig::get('sf_web_debug');
  sfConfig::set('sf_web_debug', true);

  $cacheManager->getContentTagHandler()->setContentTag('VivaLaVida_1788', 190126012976, sfViewCacheTagManager::NAMESPACE_PAGE);

  $match = '/ContentPageText.*cache.*tags.*190126012976.*VivaLaVida_1788/';

  $t->is($cacheManager->setPageCache('/blog_post/actionWithoutLayout'), null, 'page is not in cached.yml with true value');
  $cacheManager->setPageCache('/blog_post/actionWithLayout');

  $t->ok($cacheManager->getPageCache('/blog_post/actionWithLayout'));

  sfConfig::set('sf_web_debug', $sfWebDebug);

  $cacheManager->getContentTagHandler()->removeContentTags(sfViewCacheTagManager::NAMESPACE_PAGE);

  # (set|get)PartialCache

  $t->diag('(set|get)PartialCache');

  $sfWebDebug = sfConfig::get('sf_web_debug');
  sfConfig::set('sf_web_debug', true);

  $t->is(
    $cacheManager->getPartialCache(
      'blog_post',
      '_ten_posts_partial_not_cached',
      'index-page-ten-posts-disabled-partial'
    ),
    null
  );

  $t->is(
    $cacheManager->setPartialCache(
      'blog_post',
      '_ten_posts_partial_not_cached',
      'index-page-ten-posts-disabled-partial',
      'BazBazBaz&nbsp;<br />&nbsp;'
    ),
    'BazBazBaz&nbsp;<br />&nbsp;',
    'partial is not cachable'
  );

  $match = '/BazBazBaz.*cache.*tags.*1261029732.*RunLolaRun_98186/';

  $cacheManager->getContentTagHandler()->setContentTag(
    'RunLolaRun_98186', 1261029732,
    sprintf(
      '%s-%s-%s',
      'blog_post',
      '_ten_posts_partial_cached',
      sfViewCacheTagManager::NAMESPACE_PARTIAL
    )
  );

  $content = $cacheManager->setPartialCache(
    'blog_post',
    '_ten_posts_partial_cached',
    'index-page-ten-posts-enabled-partial',
    'BazBazBaz&nbsp;<br />&nbsp;'
  );

  $t->like($content, $match);

  $content = $cacheManager->getPartialCache(
    'blog_post',
    '_ten_posts_partial_cached',
    'index-page-ten-posts-enabled-partial'
  );

  $t->like($content, $match);

  $cacheManager->getContentTagHandler()->removeContentTags(sfViewCacheTagManager::NAMESPACE_PARTIAL);

  sfConfig::set('sf_web_debug', $sfWebDebug);


  $t->comment('listeners counts');
  try
  {
    $cacheManager->initialize($sfContext, new sfAPCCache(), array());
    $t->fail('Exception "InvalidArgumentException" was trigged');
  }
  catch (InvalidArgumentException $e)
  {
    $t->pass(sprintf(
      'Exception "%s" cached - should be instance of sfTaggingCache',
      get_class($e)
    ));
  }


  $layout = sfConfig::get('sf_root_dir') . '/apps/frontend/templates/layout.php';
  $v = $cacheManager->setActionCache('/blog_post/actionWithoutLayout', 'Content, may be, to cache', $layout);

  $t->is(
    $cacheManager->isCacheable('/blog_post/actionWithoutLayout'),
    true,
    'Checking again, action is still cachable'
  );

  $cacheManager->disableCache('blog_post', 'actionWithoutLayout');

  $t->is(
    $cacheManager->isCacheable('/blog_post/actionWithoutLayout'),
    false,
    'Ok, then, it should be not cachable now'
  );

  $cacheManager->addCache(
    'blog_post',
    'actionWithoutLayout',
    array(
      'lifeTime'    => 100,
      'withLayout'  => false,
    )
  );

  $t->is(
    $cacheManager->isCacheable('/blog_post/actionWithoutLayout'),
    true,
    'Added again to cache'
  );

  $cacheManager->disableCache('blog_post');

  $t->is(
    $cacheManager->isCacheable('/blog_post/actionWithoutLayout'),
    false,
    'Disaled all module cache, action now should not be cachable too'
  );


  /**
   * After all $cacheManager->configCache[] is empty
   */

  $listenersCountBefore = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));
  $cacheManager->initialize($sfContext, $tagging, $cacheManager->getOptions());
  $listenersCountAfter = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));

  $t->ok(
    $listenersCountAfter == $listenersCountBefore,
    '"sf_web_debug" is disabled in test environment'
  );

  $sfWebDebug = sfConfig::get('sf_web_debug');

  sfConfig::set('sf_web_debug', true);

  $listenersCountBefore = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));
  $cacheManager->initialize($sfContext, $tagging, $cacheManager->getOptions());
  $listenersCountAfter = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));

  $t->ok(
    $listenersCountAfter > $listenersCountBefore,
    '"sf_web_debug" is enabled in test environment'
  );

  sfConfig::set('sf_web_debug', $sfWebDebug);

  # checkCacheKey

  $params = array(

  );
  $t->is(strlen($cacheManager->checkCacheKey($params)), 32, 'md5 random key');

  $params = array(
    'sf_cache_key' => 'my-super-customized-key',
  );

  $t->is($cacheManager->checkCacheKey($params) ,'my-super-customized-key', 'personal key');

  # initialize
  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', false);

  $cacheManager->initialize($sfContext, $tagging, array());

  $t->isa_ok($cacheManager->getTaggingCache(), 'sfTaggingCache', 'sf_cache = Off, taggingCache is the same');

  sfConfig::set('sf_cache', $optionSfCache);
