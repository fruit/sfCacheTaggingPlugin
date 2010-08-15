<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
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

    $content = $cacheManager->get($internalUri);

    $t->is(
      $content,
      $is_cacheable ? 'mycontent' : null,
      sprintf(
        'sfViewCacheManager->get("%s") returns "%s"',
        $internalUri,
        var_export($content, true)
      )
    );
  }

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

  sfConfig::set('sf_web_debug', ! $sfWebDebug);

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

  $connection->rollback();

