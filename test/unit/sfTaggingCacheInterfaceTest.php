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

  $cacheSetupLocation = realpath(dirname(__FILE__) . '/../data/config/cache_setup.yml');

  $cacheKeyData = array(
    'Client:Name'     => 'Eddy',
    'Client:Surname'  => 'Parkinson',
    'Client:Age'      => 72,
    'TAG_271'         => 97102750910122,
  );

  $cacheKeys = array_keys($cacheKeyData);
  sort($cacheKeys);

  foreach (sfYaml::load($cacheSetupLocation) as $engineConfiguration)
  {
    $t->info(sprintf('Class "%s"', $engineConfiguration['class']));

    try
    {
      $engine = new $engineConfiguration['class']($engineConfiguration['param']);
      $engine->clean(sfCache::ALL);
    }
    catch (Exception $e)
    {
      $t->fail($e->getMessage());
      continue;
    }

    $engine->clean(sfCache::ALL);

    $t->is($engine->getCacheKeys(), array(), $engineConfiguration['class']);

    foreach ($cacheKeyData as $key => $data)
    {
      $t->ok($engine->set($key, $data), sprintf('Writing "%s":="%s"', $key, $data));
      $t->ok($engine->has($key), sprintf('Has "%s"', $key));
      $t->is($engine->get($key), $data, sprintf('Reading "%s", value is: "%s"', $key, $data));
    }

    $engineKeys = $engine->getCacheKeys();
    sort($engineKeys);
    
    $t->is($engineKeys, $cacheKeys, 'getCacheKeys return same result as expecting');

    foreach ($cacheKeyData as $key => $data)
    {
      $t->ok($engine->remove($key), sprintf('Removing "%s"', $key));
      $t->ok(! $engine->has($key), sprintf('Has removed "%s"?', $key));
    }

    $engine->clean(sfCache::ALL);

    # getMany

    $t->is(
      $engine->getMany(array('key_A', 'key_B', 'key_O')),
      array('key_A' => null, 'key_B' => null, 'key_O' => null),
      'Requested keys has no values in cache backend'
    );

    $engine->set('key_A', 100);
    $engine->set('key_B', 130);
    $engine->set('key_C', 11);

    $t->is(
      $engine->getMany(array('key_A', 'key_B', 'key_O')),
      array('key_A' => 100, 'key_B' => 130, 'key_O' => null),
      '2 values with values, 1 is null'
    );

    $t->is(
      $engine->getMany(array('key_A', 'key_B', 'key_C')),
      array('key_A' => 100, 'key_B' => 130, 'key_C' => 11),
      'All requested keys has values'
    );

    $t->is(
      $engine->getMany(array()),
      array(),
      'No keys = no values'
    );

    $engine->clean(sfCache::ALL);
  }
