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

  $bridge = new sfViewCacheTagManagerBridge($cacheManager->getTaggingCache());

  $posts = BlogPostTable::getInstance()->findAll();
  $posts->delete();

  $posts = BlogPostTable::getInstance()->findAll();
  $bridge->addPartialTags($posts);

  $postTagKey = BlogPostTable::getInstance()->getClassnameToReturn();
  $postCollectionTag = array("{$postTagKey}" => sfCacheTaggingToolkit::generateVersion(strtotime('today')));

  $t->is(
    $bridge->getPartialTags(),
    $postCollectionTag,
    'Tags stored in manager are full/same'
  );

  $bridge->addPartialTags(
    array('SomeTag' => 1234567890)
  );

  $t->is(
    $bridge->getPartialTags(),
    array_merge(
      array('SomeTag' => 1234567890),
      $postCollectionTag
    ),
    'Tags with new tag are successfully saved'
  );

  $bridge->removePartialTags();

  $t->is(
    $bridge->getPartialTags(),
    array(),
    'All tags are cleared'
  );

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


  $connection->rollback();