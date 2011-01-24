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



  # add

  try
  {
    $h = new sfTagNamespacedParameterHolder('nature');
    $h->add(null, 'namespaceName');
    $t->fail();
  }
  catch (InvalidArgumentException $e)
  {
    $t->pass($e->getMessage());
  }

  try
  {
    $h = new sfTagNamespacedParameterHolder('nature');
    $h->add(array(), 'namespaceName');
    $t->is($h->getAll(), array());

    $h->add(array('TAG_01' => '99112', 'TAG_03' => '12521'));

    $t->is($h->getAll(), array('TAG_01' => '99112', 'TAG_03' => '12521'));
  }
  catch (InvalidArgumentException $e)
  {
    $t->fail($e->getMessage());
  }

  $tests = array(
    array(array(),        12912, true),
    array(null,           12912, true),
    array(new stdClass(), 12912, true),
    array('Name', array(),            true),
    array('Name', new stdClass(),     true),
    array('Name', true,               true),
    array('Name', null,               true),
    array('Name', 10201,              false),
    array('Name', -23.2E-2,           false),
    array('Name', '2e5',              false),
    array('Name', '219919374817223',  false),
    array('Name', '21.231',           false),
    array('Name', '-1.21',            false),
    array('Name', 23.02,              false),
  );

  foreach ($tests as $test)
  {
    list($arg1, $arg2, $withException) = $test;
    $h = new sfTagNamespacedParameterHolder('nature');

    try
    {
      $h->set($arg1, $arg2);
      $t->ok( ! $withException, 
        sprintf(
          'arg1: %s=%s, arg2: %s=%s OK',
          gettype($arg1), $arg1, gettype($arg2), $arg2
        )
      );
    }
    catch (InvalidArgumentException $e)
    {
      $t->ok($withException, $e->getMessage());
    }
  }

  # set
  $h = new sfTagNamespacedParameterHolder('nature');

  $h->set('TAG_01', '12059231');

  $t->is(count($h->getAll()), 1);
  $t->is($h->get('TAG_01'), '12059231');

  $h->set('TAG_01', '12059232');
  $t->is(count($h->getAll()), 1); // newer, rewrite
  $t->is($h->get('TAG_01'), '12059232');

  $h->set('TAG_01', '12059230'); // older, skip
  $t->is($h->get('TAG_01'), '12059232');
  $t->is(count($h->getAll()), 1);

  $h->set('TAG_03', '12521');
  $t->is(count($h->getAll()), 2); // new
  # remove

  $h = new sfTagNamespacedParameterHolder('nature');

  $h->set('TAG_01', '12059231');

  foreach (array(array(), null, false, new stdClass(), 31231, 23.123E-10) as $test)
  {
    try
    {
      $h->remove($test);
      $t->fail();
    }
    catch (InvalidArgumentException $e)
    {
      $t->pass($e->getMessage());
    }
  }

  $t->is($h->remove('TAG_01'), '12059231');
  $t->is($h->remove('TAG_FAKE'), null);

