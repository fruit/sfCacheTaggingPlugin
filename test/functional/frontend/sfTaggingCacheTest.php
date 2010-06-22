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

  $t = new lime_test();

  try
  {
    $taggingCache = new sfTaggingCache(array());

    $t->fail('Option cache.class not passed');
  }
  catch (sfInitializationException $e)
  {
    $t->pass($e->getMessage());
  }

  try
  {
    $taggingCache = new sfTaggingCache(
      array(
        'cache' => array(
          'class' => 'sfCallable',
          'param' => array(),
        )
      )
    );

    $t->fail('sfCallable is not instance of sfCache');
  }
  catch (sfInitializationException $e)
  {
    $t->pass($e->getMessage());
  }

  $taggingCache = new sfTaggingCache(
    array(
      'cache' => array(
        'class' => 'sfAPCCache',
        'param' => array(),
      )
    )
  );

  $t->isa_ok($taggingCache->getLockerCache(), 'sfAPCCache', 'getLockerCache return object sfAPCCache');
  $t->isa_ok($taggingCache->getDataCache(), 'sfAPCCache', 'getDataCache returns object sfAPCCache');

  try
  {

    $taggingCache = new sfTaggingCache(
      array(
        'cache' => array(
          'class' => 'sfAPCCache',
          'param' => array(),
        ),
        'locker' => array(

        )
      )
    );

    $t->fail('locker class is not set');
  }
  catch (sfInitializationException $e)
  {
    $t->pass($e->getMessage());
  }

  try
  {

    $taggingCache = new sfTaggingCache(
      array(
        'cache' => array(
          'class' => 'sfAPCCache',
          'param' => array(),
        ),
        'locker' => array(
          'class' => 'sfCallable',
          'param' => array(),
        )
      )
    );

    $t->fail('locker class is not instance of sfCache');
  }
  catch (sfInitializationException $e)
  {
    $t->pass($e->getMessage());
  }

  try
  {

    $taggingCache = new sfTaggingCache(
      array(
        'cache' => array(
          'class' => 'sfAPCCache',
          'param' => array(),
        ),
        'locker' => array(
          'class' => 'noSuchClassExists',
          'param' => array(),
        )
      )
    );

    $t->fail('locker class "noSuchClassExists" does not exists');
  }
  catch (sfInitializationException $e)
  {
    $t->pass($e->getMessage());
  }

  try
  {
    $taggingCache->initialize(array(
      'cache' => array(
        'class' => 'noExistingClassName',
      )
    ));

    $t->fail('class "noExistingClassName" does not exists');
  }
  catch (sfInitializationException $e)
  {
    $t->pass($e->getMessage());
  }

  $content = 'My cache content';
  $t->is($taggingCache->remove('name'), false);

  $t->is($taggingCache->get('name'), null);

  $t->is($taggingCache->set('name', $content), true);

  $t->is($taggingCache->get('name'), $content);

  $t->is($taggingCache->remove('name'), true);
  
  
  $t->is($taggingCache->remove('name'), false);

  $t->is($taggingCache->get('name'), null);

  $tags = array('A' => 12, 'C' => 94);

  $t->is($taggingCache->set('name', $content, 300, $tags), true);

  $t->ok($taggingCache->getTimeout('name') - time() <= 300);
  
  $t->is($taggingCache->get('name'), $content);

  $t->is($taggingCache->hasTag('A'), true);
  $t->is($taggingCache->hasTag('C'), true);
  $t->is($taggingCache->hasTag('B'), false);

  $t->is($taggingCache->getTags('name'), $tags);
  $t->is($taggingCache->getTags('fake_name'), null);

  $t->is($taggingCache->remove('name'), true);

  $taggingCache->set('CityA', 'City A');
  $taggingCache->set('CityB', 'City B');

  $taggingCache->removePattern('City*');

  $t->ok(! $taggingCache->has('CityA'));
  $t->ok(! $taggingCache->has('CityB'));