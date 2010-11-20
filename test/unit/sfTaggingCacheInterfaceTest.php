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
      $t->comment($e->getMessage());
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
  }
