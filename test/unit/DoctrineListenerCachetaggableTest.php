<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  require_once realpath(dirname(__FILE__) . '/../../../../test/bootstrap/unit.php');

  $t = new lime_test();

  $lnr = new Doctrine_Template_Listener_Cachetaggable(array());

  try
  {
    $lnr->getTaggingCache();
    $t->fail('sfContext does not have instances');
  }
  catch (UnexpectedValueException $e)
  {
    $t->pass('cached "UnexpectedValueException" when there are no sfContenxt instances');
  }

  

