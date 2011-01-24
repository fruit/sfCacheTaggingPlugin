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

  $connection = Doctrine::getConnectionByTableName('BlogPost');
  $connection->beginTransaction();

  define('SF_VIEW_CACHE_MANAGER_EVENT_NAME', 'view.cache.filter_content');

  $sfContext = sfContext::getInstance();
  $sfEventDispatcher = $sfContext->getEventDispatcher();


  $cacheManager = $sfContext->getViewCacheManager();
  /* @var $cacheManager sfViewCacheTagManager */
  
  $taggingCache = $cacheManager->getTaggingCache();
  /* @var $taggingCache sfTaggingCache */

  $taggingCache->clean(sfCache::ALL);

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
    $this,
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

  $match = '/ContentActionText.*cache.*tags.*Magnolia_731.*9127561923/';

  $layout = '/home/fruit/www/sfpro/dev/sfcachetaggingplugin/apps/frontend/templates/layout.php';
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

  $match = '/ContentPageText.*cache.*tags.*VivaLaVida_1788.*190126012976/';

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

  $match = '/BazBazBaz.*cache.*tags.*RunLolaRun_98186.*1261029732/';

  $cacheManager->getContentTagHandler()->setContentTag('RunLolaRun_98186', 1261029732, sfViewCacheTagManager::NAMESPACE_PARTIAL);

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

  $listenersCountBefore = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));
  $cacheManager->initialize($sfContext, $taggingCache, $cacheManager->getOptions());
  $listenersCountAfter = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));

  $t->ok(
    $listenersCountAfter == $listenersCountBefore,
    '"sf_web_debug" is disabled in test environment'
  );

  $sfWebDebug = sfConfig::get('sf_web_debug');

  sfConfig::set('sf_web_debug', true);

  $listenersCountBefore = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));
  $cacheManager->initialize($sfContext, $taggingCache, $cacheManager->getOptions());
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

  $tests = array(
    array(
      'params' => array(
        'sf_cache_key' => 'my-super-customized-key',
        'sf_cache_tags' => null,
      ),
      'throw' => false,
    ),
    array(
      'params' => array(
        'sf_cache_tags' => array('T' => 12931923, 'G_TAG' => 1123.12381723),
      ),
      'throw' => true,
    ),
    array(
      'params' => array(
        'sf_cache_key' => 'my-super-customized-key',
        'sf_cache_tags' => array('T' => 12931923, 'G_TAG' => 1123.12381723),
      ),
      'throw' => false,
    ),
    array(
      'params' => array(
        'sf_cache_key' => 'my-super-customized-key',
        'sf_cache_tags' => 1,
      ),
      'throw' => true,
    ),
  );

  foreach ($tests as $test)
  {
    $params = $test['params'];
    $throw = $test['throw'];

    try
    {
      $cacheManager->checkCacheKey($params);

      $t->ok(! $throw);
    }
    catch (Exception $e)
    {
      $t->ok($throw, sprintf('type: %s, message: %s', get_class($e), $e->getMessage()));
    }
  }


  
  

  # initialize 
  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', false);

  $cacheManager->initialize($sfContext, $taggingCache, array());

  $t->isa_ok($cacheManager->getTaggingCache(), 'sfNoTaggingCache', 'sf_cache = Off, taggingCache is sfNoTaggingCache');

  sfConfig::set('sf_cache', $optionSfCache);



//  var_dump($cacheManager->getActionCache('/blog_post/actionWithoutLayout'));

//  $optionSfWebDebug = sfConfig::get('sf_web_debug');
//  sfConfig::set('sf_web_debug', true);
//
//
//
//  sfConfig::set('sf_web_debug', $optionSfWebDebug);

  
  $connection->rollback();

