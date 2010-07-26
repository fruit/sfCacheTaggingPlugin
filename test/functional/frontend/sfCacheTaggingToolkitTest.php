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

  $cc = new sfCacheClearTask(sfContext::getInstance()->getEventDispatcher(), new sfFormatter());
  $cc->run();

  $cacheManager = sfContext::getInstance()->getViewCacheManager();

  $connection = Doctrine::getConnectionByTableName('BlogPost');
  $connection->beginTransaction();

  $t = new lime_test();

  try
  {
    $p = new BlogPost();
//    $p->setTitle('Blog post title "AAAA"');
    $p->save();

    sfCacheTaggingToolkit::formatTags($p);

    $t->pass('sfCacheTaggingToolkit::formatTags() works for Doctrine_Record with "Doctrine_Template_Cachetaggable" template');
  }
  catch (InvalidArgumentException $e)
  {
    $t->fail($e->getMessage());
  }

  try
  {
    $u = new University();
    $u->save();
    sfCacheTaggingToolkit::formatTags($u);

    $t->fail('sfCacheTaggingToolkit::formatTags() does not works for Doctrine_Record without "Doctrine_Template_Cachetaggable" template');
  } 
  catch (InvalidArgumentException $e)
  {
    $t->pass($e->getMessage());
  }

  BlogPostTable::getInstance()->createQuery()->delete()->execute();
  UniversityTable::getInstance()->createQuery()->delete()->execute();


  $precisionToTest = array(
    array('value' => -1, 'throwException' => true),
    array('value' =>  0, 'throwException' => false),
    array('value' =>  3, 'throwException' => false),
    array('value' =>  6, 'throwException' => false),
    array('value' =>  7, 'throwException' => true),
  );

  # if precision approach to 0, unit tests will be failed
  # (0 precision is too small for the current test)

  foreach ($precisionToTest as $precisionTest)
  {
    try
    {
      sfConfig::set('app_sfcachetaggingplugin_microtime_precision', $precisionTest['value']);

      sfCacheTaggingToolkit::getPrecision();

      if ($precisionTest['throwException'])
      {
        $t->fail(sprintf(
          'Should be thrown an OutOfRangeException value "%d" no in range 0…6',
          $precisionTest['value']
        ));
      }
      else
      {
        $t->pass(sprintf(
          'Precision value "%d" in range 0…6, no exception was thrown',
          $precisionTest['value']
        ));
      }
    }
    catch (OutOfRangeException $e)
    {
      if ($precisionTest['throwException'])
      {
        $t->pass(sprintf(
          'OutOfRangeException catched value "%d" is not in range 0…6',
          $precisionTest['value']
        ));
      }
      else
      {
        $t->fail(sprintf(
          'Precision value "%d" in range 0…6, exception was thrown',
          $precisionTest['value']
        ));
      }
    }
  }

  include_once sfConfig::get('sf_apps_dir') . '/frontend/modules/blog_post/actions/actions.class.php';

  try
  {
    $e = new sfEvent(
      new blog_postActions(sfContext::getInstance(), 'blog_post', 'run'),
      'component.method_not_found',
      array('method' => 'callMe', 'arguments' => array(1,2,3))
    );

    $v = sfCacheTaggingToolkit::listenOnComponentMethodNotFoundEvent($e);

    $t->ok(null === $v, 'Return null if method does not exists');
  }
  catch (BadMethodCallException $e)
  {
    $t->pass($e->getMessage());
  }


  $connection->rollback();