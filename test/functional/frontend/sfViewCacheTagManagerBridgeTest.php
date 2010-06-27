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

  $cacheManager = sfContext::getInstance()->getViewCacheManager();
  /* @var $cacheManager sfViewCacheTagManager */
  
  $bridge = new sfViewCacheTagManagerBridge($cacheManager);
  
  $holder = $cacheManager->getContentTagHandler();

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
    array('set%sTags', array()),
    array('set%sTags', array(1)),
    array('set%sTags', array(new stdClass())),
    array('add%sTags', array()),
    array('add%sTags', array(1)),
    array('add%sTags', array('aaaa')),
    array('add%sTags', array(null)),
    array('removeMy%sTags', array()),
    array('has%sTag', array()),
    array('set%sTag', array(array())),
    array('set%sTag', array(null)),
    array('set%sTag', array(1)),
    array('set%sTag', array('MyTag', array())),
    array('set%sTag', array('MyTag', new stdClass())),
    array('remove%sTag', array()),
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

        $t->pass(sprintf('Callable method ::%s(%s)', $method, implode(', ', $arguments)));
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

        $t->fail(sprintf('Method %s(%s) was successfully called', $method, implode(', ', $arguments)));
      }
      catch (Exception $e)
      {
        $t->pass(sprintf('%s, %s', get_class($e), $e->getMessage()));
      }

    }
  }

  $t->isa_ok(
    $bridge->getTaggingCache(), 
    'sfTaggingCache',
    sprintf('"%s::getTaggingCache" returns sfTaggingCache object', get_class($bridge))
  );

  
