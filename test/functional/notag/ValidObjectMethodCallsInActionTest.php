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

  $browser->info('Doctrine_Record');

  $methods = array(
    'getCollectionTags' => null,
    'obtainCollectionName' => null,
    'obtainCollectionVersion' => null,
    'getTags' => null,
    'obtainTagName' => null,
    'obtainObjectVersion' => null,
    'updateObjectVersion' => null,
    'assignObjectVersion' => array(10001212, "AAAAAAA"),
    'addVersionTag' => array('Comments', 2128918921),
    'addVersionTags' => array(array('Comment_1' => 31, 'Comment_2' => 39)),
  );

  foreach ($methods as $method => $args)
  {
    $browser->info("->{$method}()");

    $params['args'] = null === $args ? array() : $args;
    $params['method'] = $method;

    $browser->getAndCheck(
      'blog_post',
      'callDoctrineRecordMethodTest',
      '/blog_post/callDoctrineRecordMethodTest?'. http_build_query($params),
      200
    );

    $browser->checkCurrentExceptionIsEmpty();

    $browser->with('response')->begin()
      ->checkElement('p', "Page loaded after calling {$method}")
      ->checkElement('span', 'Doctrine_Record')
    ->end();
  }

  $browser->info('Doctrine_Collection');

  $methods = array(
    'getTags' => null,
    'getCollectionTags' => null,
    'removeVersionTags' => null,
    'addVersionTag' => array('Comments', 2128918921),
    'addVersionTags' => array(array('Comment_1' => 31, 'Comment_2' => 39)),
  );

  foreach ($methods as $method => $args)
  {
    $browser->info("->{$method}()");

    $params['args'] = null === $args ? array() : $args;
    $params['method'] = $method;

    $browser->getAndCheck(
      'blog_post',
      'callDoctrineCollectionMethodTest',
      '/blog_post/callDoctrineCollectionMethodTest?'. http_build_query($params),
      200
    );

    $browser->checkCurrentExceptionIsEmpty();

    $browser->with('response')->begin()
      ->checkElement('p', "Page loaded after calling {$method}")
      ->checkElement('span', 'Doctrine_Collection')
    ->end();
  }