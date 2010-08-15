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
  $t = $browser->test();

  
  $connection = Doctrine::getConnectionByTableName('BlogPost');
  $connection->beginTransaction();
  
  $cacheManager = sfContext::getInstance()->getViewCacheManager();

  try
  {
    $bridge = new sfViewCacheTagManagerBridge(
      new sfTaggingCache(array(
        'data' => array('class' => 'sfAPCCache'),
        'logger' => array('class' => 'sfNoCacheTagLogger'),
      ))
    );

    $t->pass('Bridge initialized');
  }
  catch (Exception $e)
  {
    $t->fail($e->getMessage());
  }

  $validPatternMethods = array(
    'get%sTags' => array(),
    'set%sTags' => array(array('A' => sfCacheTaggingToolkit::generateVersion())),
    'add%sTags' => array(array('B' => sfCacheTaggingToolkit::generateVersion())),
    'remove%sTags' => array(),
    'has%sTag' => array('A'),
    'set%sTag' => array('C', sfCacheTaggingToolkit::generateVersion()),
    'remove%sTag' => array('C'),
  );

  $invalidPatternMethods = array(
    array('get%sTaags', array()),
    array('set%sTags', array(1)),
    array('set%sTags', array(new stdClass())),
    array('add%sTags', array(1)),
    array('add%sTags', array('aaaa')),
    array('add%sTags', array(null)),
    array('removeMy%sTags', array()),
    array('set%sTag', array('MyTag', array())),
    array('set%sTag', array('MyTag', new stdClass())),
    array('remove%sTag', array(null)),
    array('remove%sTag', array(3)),
    array('remove%sTag', array(new stdClass())),
    array('callMe', array()),
  );

  foreach (sfViewCacheTagManager::getNamespaces() as $namespace)
  {
    foreach ($validPatternMethods as $patternMethod => $arguments)
    {
      $method = sprintf($patternMethod, $namespace);

      try
      {
        $c = new sfCallableArray(array($bridge, $method));
        $c->callArray($arguments);

        $t->pass(sprintf('Callable method %s()', $method));
      }
      catch (Exception $e)
      {
        $t->fail(sprintf('%s: ', get_class($e), $e->getMessage()));
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

        $t->fail(sprintf('Method %s() was successfully called', $method));
      }
      catch (Exception $e)
      {
        $t->pass(sprintf('%s, %s', get_class($e), $e->getMessage()));
      }

    }
  }


  $t->can_ok($bridge, array('addDoctrineTags'));

  try
  {
     $t->isa_ok(
       $bridge->addDoctrineTags(
         array('TAG_1' => 12321839123, 'TAG_2' => 12738725), null
       ),
       'sfViewCacheTagManagerBridge'
     );

     $t->fail();
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
     'sfViewCacheTagManagerBridge'
    );

    $t->pass();
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
      'sfViewCacheTagManagerBridge'
    );

    $t->pass();
  }
  catch (Exception $e)
  {
    $t->fail($e->getMessage());
  }

  # moved from sfViewCacheTagManager (should be fragmented)

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

  $connection->rollback();