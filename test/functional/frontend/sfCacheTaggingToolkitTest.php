<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  require_once realpath(dirname(__FILE__) . '/../../../../../test/bootstrap/functional.php');
  require_once sfConfig::get('sf_symfony_lib_dir') . '/vendor/lime/lime.php';

  $optionMicrotimePrecision = sfConfig::get('app_sfcachetaggingplugin_microtime_precision');

  class ArrayAsIteratorAggregate implements IteratorAggregate
  {
    protected $tags;

    public function __construct($tags)
    {
      $this->tags = $tags;
    }

    public function getIterator()
    {
      return new ArrayIterator($this->tags);
    }
  }

  $cacheManager = sfContext::getInstance()->getViewCacheManager();

  $connection = Doctrine::getConnectionByTableName('BlogPost');
  $connection->beginTransaction();

  $t = new lime_test();

  try
  {
    $p = new BlogPost();
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

  sfConfig::set('app_sfcachetaggingplugin_microtime_precision', $optionMicrotimePrecision);

  include_once sfConfig::get('sf_apps_dir') . '/frontend/modules/blog_post/actions/actions.class.php';

  try
  {
    $e = new sfEvent(
      new blog_postActions(sfContext::getInstance(), 'blog_post', 'run'),
      'component.method_not_found',
      array('method' => 'callMe', 'arguments' => array(1, 2, 3))
    );

    $v = sfCacheTaggingToolkit::listenOnComponentMethodNotFoundEvent($e);

    $t->ok(null === $v, 'Return null if method does not exists');
  }
  catch (BadMethodCallException $e)
  {
    $t->pass($e->getMessage());
  }

  # getTaggingCache

  try
  {
    $t->isa_ok(sfCacheTaggingToolkit::getTaggingCache(), 'sfTaggingCache', 'getTaggingCache return correct object');
    $t->pass('No expcetions thrown');
  }
  catch (Exception $e)
  {
    $t->fail($e->getMessage());
  }

  $posts = BlogPostTable::getInstance()->findAll();

  $post = $posts->getFirst();

  $cleanTags = array('Auto_1' => 127872123, 'Auto_2' => 192768211);

  $tests = array(
    false, $cleanTags, new ArrayIterator($cleanTags), new ArrayAsIteratorAggregate($cleanTags),
    new ArrayObject($cleanTags), $posts, $post
  );

  foreach ($tests as $tags)
  {
    try
    {
      $decoratedTags = sfCacheTaggingToolkit::formatTags($tags);
      
      $typeOfTags = gettype($tags);

      $t->is(
        gettype($decoratedTags),
        'array',
        sprintf(
          'return array if argument is "%s" %s',
          $typeOfTags,
          is_object($tags) ? '('.get_class($tags) . ')' : ''
        )
      );

      $t->pass(sprintf('Adding tags as %s', $typeOfTags));
    }
    catch (InvalidArgumentException $e)
    {
      $t->fail($e->getMessage());
    }
  }

  $tests = array(null, true, 2, 'string', 2.1E-3, new stdClass());
  foreach ($tests as $tags)
  {
    try
    {
      sfCacheTaggingToolkit::formatTags($tags);
      $t->fail();
    }
    catch (InvalidArgumentException $e)
    {
      $t->pass($e->getMessage());
    }
  }

  $connection->rollback();