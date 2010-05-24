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

  $sfContext = sfContext::getInstance();
  $sfViewCacheManager = $sfContext->getViewCacheManager();

  $t = new lime_test();

  $lnr = new Doctrine_Template_Listener_Cachetaggable(array());

  try
  {
    $lnr->getTagger();
    $t->fail('pass on taggable view manager is disabled');
  }
  catch (UnexpectedValueException $e)
  {
    $t->pass('cached "UnexpectedValueException" on disabled taggable view manager');
  }

  $sfViewCacheManager->initialize($sfContext, new sfAPCCache());

  try
  {
    $lnr->getTagger();
    $t->fail('pass on cache engine is not sfTagCache');
  }
  catch (UnexpectedValueException $e)
  {
    $t->pass('cached "UnexpectedValueException" on incompatible sfCache mechanism');
  }

  $cc = new sfCacheClearTask(sfContext::getInstance()->getEventDispatcher(), new sfFormatter());
  $cc->run();


