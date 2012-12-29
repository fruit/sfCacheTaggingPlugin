<?php
  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2013 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  include_once realpath(dirname(__FILE__) . '/../../bootstrap/functional.php');
  include_once realpath(dirname(__FILE__) . '/../../fixtures/project/apps/frontend/modules/blog_post/actions/actions.class.php');

  $browser = new sfTestFunctional(new sfBrowser());
  $t = $browser->test();

  $cacheManager = sfContext::getInstance()->getViewCacheManager();
  /* @var $cacheManager sfViewCacheTagManager */

  $tagging = $cacheManager->getTaggingCache();
  $con = Doctrine_Manager::getInstance()->getCurrentConnection();

  include_once sfConfig::get('sf_apps_dir') . '/frontend/modules/blog_post/actions/actions.class.php';

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

  function test_method ($method, array $args = array())
  {
    $e = new sfEvent(
      new blog_postActions(sfContext::getInstance(), 'blog_post', 'run'),
      'component.method_not_found',
      array('method' => $method, 'arguments' => $args)
    );

    sfContext::getInstance()
      ->getConfiguration()
      ->getPluginConfiguration('sfCacheTaggingPlugin')
      ->listenOnComponentMethodNotFoundEvent($e);

    return $e->getReturnValue();
  }

  $action = new blog_postActions(sfContext::getInstance(), 'blog_post', 'index');
  $bridge = new sfViewCacheTagManagerBridge($action);

  $validPatternMethods = array(
    'getContentTags' => array(),
    'setContentTags' => array(array('A' => sfCacheTaggingToolkit::generateVersion())),
    'addContentTags' => array(array('B' => sfCacheTaggingToolkit::generateVersion())),
    'removeContentTags' => array(),
    'hasContentTag' => array('A'),
    'setContentTag' => array('C', sfCacheTaggingToolkit::generateVersion()),
    'removeContentTag' => array('C'),
  );

  $invalidPatternMethods = array(
    array('getContentTaags', array()),
    array('setContentTags', array(1)),
    array('setContentTags', array(new stdClass())),
    array('addContentTags', array(1)),
    array('addContentTags', array('aaaa')),
    array('addContentTags', array(null)),
    array('removeMyContentTags', array()),
    array('setContentTag', array('MyTag', array())),
    array('setContentTag', array('MyTag', new stdClass())),
    array('removeContentTag', array(null)),
    array('removeContentTag', array(3)),
    array('removeContentTag', array(new stdClass())),
    array('callMe', array()),
  );

  foreach ($validPatternMethods as $method => $arguments)
  {
    try
    {
      $c = new sfCallableArray(array($bridge, $method));
      $c->callArray($arguments);

      $t->pass(sprintf('Calling a valid method %s()', $method));
    }
    catch (Exception $e)
    {
      $t->fail(sprintf('Calling a valid method %s(). Catched "%s" with messsage: %s', $method, get_class($e), $e->getMessage()));
    }
  }

  foreach ($invalidPatternMethods as $callable)
  {
    list($method, $arguments) = $callable;

    try
    {
      $c = new sfCallableArray(array($bridge, $method));
      $c->callArray($arguments);

      $t->fail(sprintf('Calling a invalid method %s()', $method));
    }
    catch (Exception $e)
    {
      $t->pass(sprintf('Calling a invalid method %s(). Catched "%s" with messsage: %s', $method, get_class($e), $e->getMessage()));
    }
  }

  # addDoctrineTags
  $t->can_ok($bridge, array('addDoctrineTags'), 'Method addDoctrineTags() is callable');

  try
  {
    $t->isa_ok(
      test_method('addDoctrineTags', array(
        array('TAG_1' => 12321839123, 'TAG_2' => 12738725), null
      )),
      'sfViewCacheTagManagerBridge'
    );

    $t->fail('Exception InvalidArgumentException is not thrown');
  }
  catch (InvalidArgumentException $e)
  {
    $t->pass($e->getMessage());
  }

  try
  {
    $q = new Doctrine_Query();
    $q->addWhere('some_value = ?', rand(1, 100));

    $t->isa_ok(
      test_method('addDoctrineTags', array(
        array('TAG_1' => 12321839123, 'TAG_2' => 12738725),
        $q->getResultCacheHash($q->getParams())
      )),
      'sfViewCacheTagManagerBridge',
      'addDoctrineTags() gets valid arguments, with query hash'
    );

    $t->pass('No exceptions are thrown');
  }
  catch (Exception $e)
  {
    $t->fail($e->getMessage());
  }

  try
  {
    $q = new Doctrine_Query();
    $q->addWhere('some_value = ?', rand(1, 100));

    $t->isa_ok(
      test_method('addDoctrineTags', array(
        array('TAG_1' => 12321839123, 'TAG_2' => 12738725),
        $q,
        $q->getParams()
      )),
      'sfViewCacheTagManagerBridge',
      'addDoctrineTags() gets valid arguments with Doctrine_Query and params'
    );

    $t->pass('No exceptions are thrown');
  }
  catch (Exception $e)
  {
    $t->fail($e->getMessage());
  }

  # moved from sfViewCacheTagManager (should be fragmented)

  $posts = BlogPostTable::getInstance()->findAll();
  $posts->delete();

  $posts = BlogPostTable::getInstance()->findAll();

  test_method('addContentTags', array($posts));

  $t->is(
    array_keys($postCollectionTag = test_method('getContentTags')),
    array(sfCacheTaggingToolkit::obtainCollectionName(BlogPostTable::getInstance())),
    'Tags stored in manager are full/same'
  );

  test_method('addContentTags', array(array('SomeTag' => 1234567890)));

  $t->is(
    test_method('getContentTags'),
    array_merge(
      array('SomeTag' => 1234567890),
      $postCollectionTag
    ),
    'Tags with new tag are successfully saved'
  );

  test_method('removeContentTags');

  $t->is(
    test_method('getContentTags'),
    array(),
    'All tags are cleared'
  );

  $t->is(test_method('doDisableCache'), true, 'Disabled default controllers module and action');
  $t->is(test_method('doDisableCache', array('blog_post', 'index')), true, 'Disabled blog_post/index to cache');

  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', false);

  $t->is(test_method('doDisableCache'), false, 'Return false, if cache is disabled');

  # existing method, with disabled cache
  try
  {
    $t->is(null, test_method('addContentTags', array(array('Tag:1' => sfCacheTaggingToolkit::generateVersion()))));
    $t->pass('Exception sfCacheDisabledException not thrown');
  }
  catch (sfCacheDisabledException $e)
  {
    $t->fail('Catching sfCacheDisabledException');
  }

  # unexisting method, with disabled cache
  $t->is(null, test_method('unknownMethod', array(array('Tag:1' => sfCacheTaggingToolkit::generateVersion()))));

  sfConfig::set('sf_cache', $optionSfCache);