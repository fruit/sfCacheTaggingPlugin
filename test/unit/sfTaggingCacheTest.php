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

  try
  {
    $c = new sfTaggingCache(array());

    $t->fail('Option cache.class not passed');
  }
  catch (sfInitializationException $e)
  {
    $t->pass($e->getMessage());
  }

  try
  {
    $c = new sfTaggingCache(
      array(
        'cache' => array(
          'class' => 'sfCallable',
          'param' => array()
        ),
        'logger' => array(
          'class' => 'sfNoLogger',
        ),
      )
    );

    $t->fail('sfCallable is not instance of sfCache');
  }
  catch (sfInitializationException $e)
  {
    $t->pass($e->getMessage());
  }

  $c = new sfTaggingCache(
    array(
      'cache' => array(
        'class' => 'sfAPCCache',
        'param' => array(),
      ),
      'logger' => array(
        'class' => 'sfNoLogger',
      ),
    )
  );

  $t->isa_ok($c->getLockerCache(), 'sfAPCCache', 'getLockerCache return object sfAPCCache');
  $t->isa_ok($c->getDataCache(), 'sfAPCCache', 'getDataCache returns object sfAPCCache');

  try
  {

    $c = new sfTaggingCache(
      array(
        'cache' => array(
          'class' => 'sfAPCCache',
          'param' => array(),
        ),
        'locker' => array(

        ),
        'logger' => array(
          'class' => 'sfNoLogger',
        ),
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

    $c = new sfTaggingCache(
      array(
        'cache' => array(
          'class' => 'sfAPCCache',
          'param' => array(),
        ),
        'locker' => array(
          'class' => 'sfCallable',
          'param' => array(),
        ),
        'logger' => array(
          'class' => 'sfNoLogger',
        ),
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

    $c = new sfTaggingCache(
      array(
        'cache' => array(
          'class' => 'sfAPCCache',
          'param' => array(),
        ),
        'locker' => array(
          'class' => 'noSuchClassExists',
          'param' => array(),
        ),
        'logger' => array(
          'class' => 'sfNoLogger',
        ),
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
    $c->initialize(array(
      'cache' => array(
        'class' => 'noExistingClassName',
      ),
      'logger' => array(
        'class' => 'sfNoLogger',
      ),
    ));

    $t->fail('class "noExistingClassName" does not exists');
  }
  catch (sfInitializationException $e)
  {
    $t->pass($e->getMessage());
  }

  $content = 'My cache content';
  $t->is($c->remove('name'), false);

  $t->is($c->get('name'), null);

  $t->is($c->set('name', $content), true);

  $t->is($c->get('name'), $content);

  $t->is($c->remove('name'), true);


  $t->is($c->remove('name'), false);

  $t->is($c->get('name'), null);

  $tags = array('A' => 12, 'C' => 94);

  $t->is($c->set('name', $content, 300, $tags), true);

  $t->ok($c->getTimeout('name') - time() <= 300);

  $t->is($c->get('name'), $content);

  $t->is($c->hasTag('A'), true);
  $t->is($c->hasTag('C'), true);
  $t->is($c->hasTag('B'), false);

  $t->is($c->getTags('name'), $tags);
  $t->is($c->getTags('fake_name'), null);

  $t->is($c->remove('name'), true);

  $c->set('CityA', 'City A');
  $c->set('CityB', 'City B');

  $c->removePattern('City*');

  $t->ok(! $c->has('CityA'));
  $t->ok(! $c->has('CityB'));