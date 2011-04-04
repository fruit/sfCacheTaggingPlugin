<?php
  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  require_once realpath(dirname(__FILE__) . '/../../../../../test/bootstrap/functional.php');
  require_once realpath(dirname(__FILE__) . '/../../../../../apps/frontend/modules/blog_post/actions/actions.class.php');

  $browser = new sfTestFunctional(new sfBrowser());
  $t = $browser->test();


  $connection = Doctrine::getConnectionByTableName('BlogPost');
  $connection->beginTransaction();

  $cacheManager = sfContext::getInstance()->getViewCacheManager();

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
    $method = sprintf($callable[0], $namespace);

    $arguments = $callable[1];
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
      $bridge->addDoctrineTags(
        array('TAG_1' => 12321839123, 'TAG_2' => 12738725), null
      ),
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
      $bridge->addDoctrineTags(
        array('TAG_1' => 12321839123, 'TAG_2' => 12738725),
        $q->getResultCacheHash($q->getParams())
      ),
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
      $bridge->addDoctrineTags(
        array('TAG_1' => 12321839123, 'TAG_2' => 12738725),
        $q,
        $q->getParams()
      ),
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

  $bridge = new sfViewCacheTagManagerBridge($action);

  $posts = BlogPostTable::getInstance()->findAll();
  $posts->delete();

  $posts = BlogPostTable::getInstance()->findAll();
  $bridge->addContentTags($posts);

  $postTagKey = BlogPostTable::getInstance()->getClassnameToReturn();
  $postCollectionTag = array("{$postTagKey}" => sfCacheTaggingToolkit::generateVersion(strtotime('today')));

  $t->is(
    $bridge->getContentTags(),
    $postCollectionTag,
    'Tags stored in manager are full/same'
  );

  $bridge->addContentTags(
    array('SomeTag' => 1234567890)
  );

  $t->is(
    $bridge->getContentTags(),
    array_merge(
      array('SomeTag' => 1234567890),
      $postCollectionTag
    ),
    'Tags with new tag are successfully saved'
  );

  $bridge->removeContentTags();

  $t->is(
    $bridge->getContentTags(),
    array(),
    'All tags are cleared'
  );

  $t->is($bridge->disableCache(), true, 'Disabled default controllers module and action');
  $t->is($bridge->disableCache('blog_post', 'index'), true, 'Disabled blog_post/index to cache');

  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', false);

  $t->is($bridge->disableCache('blog_post'), false, 'Return false, if cache is disabled');

  # existing method, with disabled cache
  try
  {
    $t->is(null, $bridge->addContentTags(array('Tag:1' => sfCacheTaggingToolkit::generateVersion())));
    $t->fail('Exception sfCacheDisabledException not thrown');
  }
  catch (sfCacheDisabledException $e)
  {
    $t->pass('Catching sfCacheDisabledException');
  }

  # unexisting method, with disabled cache
  try
  {
    $t->is(null, $bridge->unknownMethod(array('Tag:1' => sfCacheTaggingToolkit::generateVersion())));
    $t->fail('Exception BadMethodCallException not thrown');
  }
  catch (BadMethodCallException $e)
  {
    $t->pass('BadMethodCallException cached when calling unknown method unknownMethod()');
  }
  catch (Exception $e)
  {
    $t->fail(sprintf(
      'Cached incorrect exception (%s): %s', get_class($e), $e->getMessage()
    ));
  }

  sfConfig::set('sf_cache', $optionSfCache);

  $connection->rollback();