<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
  */

  require_once realpath(dirname(__FILE__) . '/../../../../../test/bootstrap/functional.php');
  require_once sfConfig::get('sf_symfony_lib_dir') . '/vendor/lime/lime.php';

  define('SF_VIEW_CACHE_MANAGER_EVENT_NAME', 'view.cache.filter_content');

  $sfContext = sfContext::getInstance();
  $sfEventDispatcher = $sfContext->getEventDispatcher();
  $sfViewCacheManager = $sfContext->getViewCacheManager();
  $sfTagger = $sfViewCacheManager->getTagger();

  $t = new lime_test();

  $t->is($sfViewCacheManager->startWithTags('some_cache_key'), null, 'ob_start() on new key');
  $t->diag('Output some content for testing ob_start() in sfViewCacheManager');
  $t->isnt($content = $sfViewCacheManager->stopWithTags('some_cache_key', null), null, 'ob_get_clean() on key "some_cache_key"');
  print $content;
  $t->isnt($sfViewCacheManager->startWithTags('some_cache_key'), '', 'ob_start() on old key');

  try
  {
    $sfViewCacheManager->initialize($sfContext, new sfAPCCache(), $options);
    $t->fail('Exception "InvalidArgumentException" was trigged');
  }
  catch (InvalidArgumentException $e)
  {
    $t->pass(sprintf(
      'Exception "%s" cached - should be instance of sfTagCache',
      get_class($e)
    ));
  }

  $posts = BlogPostTable::getInstance()->findAll();
  $posts->delete();

  $posts = BlogPostTable::getInstance()->findAll();
  $sfViewCacheManager->addTags($posts);

  $postTagKey = BlogPostTable::getInstance()->getClassnameToReturn();
  $postCollectionTag = array("{$postTagKey}" => sfCacheTaggingToolkit::generateVersion(strtotime('today')));

  $t->is($sfViewCacheManager->getTags(), $postCollectionTag, 'Tags stored in manager are full/same');

  $sfViewCacheManager->addTags(array('SomeTag' => 1234567890));

  $t->is($sfViewCacheManager->getTags(), array_merge(array('SomeTag' => 1234567890), $postCollectionTag), 'Tags with new tag are successfully saved');

  $sfViewCacheManager->clearTags();

  $t->is($sfViewCacheManager->getTags(), array(), 'All tags are cleared');

  $listenersCountBefore = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));
  $sfViewCacheManager->initialize($sfContext, $sfTagger, $sfViewCacheManager->getOptions());
  $listenersCountAfter = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));

  $t->ok(
    $listenersCountAfter == $listenersCountBefore,
    '"sf_web_debug" is disabled in test environment'
  );

  $sfWebDebug = sfConfig::get('sf_web_debug');

  sfConfig::set('sf_web_debug', ! $sfWebDebug);

  $listenersCountBefore = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));
  $sfViewCacheManager->initialize($sfContext, $sfTagger, $sfViewCacheManager->getOptions());
  $listenersCountAfter = count($sfEventDispatcher->getListeners(SF_VIEW_CACHE_MANAGER_EVENT_NAME));

  $t->ok(
    $listenersCountAfter > $listenersCountBefore,
    '"sf_web_debug" is enabled in test environment'
  );

  sfConfig::set('sf_web_debug', $sfWebDebug);

  $actionCacheCheck = array(
    array('uri' => '/blog_post/actionWithLayout', 'is_cacheable' => true, 'is_layout' => true,),
    array('uri' => '/blog_post/actionWithoutLayout', 'is_cacheable' => true, 'is_layout' => false,),
    array('uri' => '/blog_post/actionWithDisabledCache', 'is_cacheable' => false, 'is_layout' => false,),
  );

  foreach ($actionCacheCheck as $action)
  {
    list($internalUri, $is_cacheable, $is_layout) = array_values($action);

    $t->is($is_layout, $sfViewCacheManager->withLayout($internalUri), sprintf('w/o layout "%s" -%b', $internalUri, $is_layout));

    if ($is_cacheable)
    {
      $t->ok(
        $sfViewCacheManager->isCacheable($internalUri),
        sprintf('action "%s" has enabled cache', $internalUri)
      );

      $t->isnt(
        $sfViewCacheManager->set('mycontent', $internalUri, array('A' => 123123123)),
        false,
        sprintf(
          'done on setting content on cacheable uri "%s"',
          $internalUri
        )
      );

      $content = $sfViewCacheManager->get($internalUri);
      $t->isnt(
        null,
        $content,
        sprintf(
          'sfViewCacheManager->get("%s") on cacheable uri returns "%s"',
          $internalUri,
          $content
        )
      );
    }
    else
    {
      $t->isnt(
        $sfViewCacheManager->isCacheable($internalUri),
        true,
        sprintf('action "%s" has disabled cache', $internalUri)
      );

      $t->is(
        $sfViewCacheManager->set('mycontent', $internalUri, array('A' => 123123123)),
        false,
        'failed on setting content on NOT cacheable uri'
      );

      $t->is(
        null,
        $sfViewCacheManager->get($internalUri),
        sprintf('sfViewCacheManager->get("%s") on NOT cacheable uri', $internalUri)
      );
    }


  }

  
  $cc = new sfCacheClearTask(
    sfContext::getInstance()->getEventDispatcher(),
    new sfFormatter()
  );
  
  $cc->run();
