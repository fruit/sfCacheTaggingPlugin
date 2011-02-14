<?php
  
  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  require_once realpath(dirname(__FILE__) . '/../../../../test/bootstrap/unit.php');

  $t = new lime_test();

  class RecoverableErrorException extends ErrorException { }

  class ErrorHandler
  {
    public static function handleRecoverableError (
      $errno, $errstr, $errfile = null, $errline = null, $errcontext = array()
    )
    {
      throw new RecoverableErrorException($errstr, 0, $errno, $errfile, $errline);
    }
  }

  set_error_handler('ErrorHandler::handleRecoverableError', E_RECOVERABLE_ERROR);

  $m = new CacheMetadata();
  $t->is($m->getData(), null);
  $t->is($m->getTags(), array());


  $m = new CacheMetadata(array('data' => 'Content'));
  $t->is($m->getData(), 'Content');
  $t->is($m->getTags(), array());


  $tags = array('Article_1' => 187281, 'Article_2' => 94711);
  $m = new CacheMetadata(array('data' => 'Content', 'tags' => $tags));
  $t->is($m->getData(), 'Content');

  $t->is($m->getTags(), $tags);


  $m->setTags($tags);
  $t->is($m->getTags(), $tags);

  $m->addTags(array('Article_2' => 94710)); // old version (should skip)
  $m->addTags(array('Article_3' => 81872));

  $m->setTag('Article_3', 100); // old version (should skip)
  $t->is($m->getTag('Article_3'), 81872); // still newest version
  
  $t->is(count($m->getTags()), 3);
  $t->is($m->getTags(), array_merge($tags, array('Article_3' => 81872)));
  
  try
  {
    $m = new CacheMetadata(array('data' => 'Content', 'tags' => 1));
    $t->fail();
  }
  catch (RecoverableErrorException $e)
  {
    $t->pass('OK: ' . $e->getMessage());
  }

  restore_error_handler();