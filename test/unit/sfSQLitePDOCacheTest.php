<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  require_once
    realpath(dirname(__FILE__) . '/../../../../test/bootstrap/unit.php');

  require_once
    sfConfig::get('sf_symfony_lib_dir') . '/../test/unit/cache/sfCacheDriverTests.class.php';
  
  $t = new lime_test();

  
  # Driver tests

  $t->diag('Symfony build-in cache driver tests');
  $dir = sfConfig::get('sf_cache_dir');
  $c = new sfSQLitePDOCache(array(
    'dsn' => "sqlite:{$dir}/cache.sqlite.test.db",
  ));
  sfCacheDriverTests::launch($t, $c);
  unlink("{$dir}/cache.sqlite.test.db");


  # no DSN
  try
  {
    $c = new sfSQLitePDOCache(array());
    $t->fail('passed without DSN');
  }
  catch (sfConfigurationException $e)
  {
    $t->pass($e->getMessage());
  }


  # :memory: create schema every time
  $t->comment(':memory:');
  $c = new sfSQLitePDOCache(array(
    'dsn' => "sqlite::memory:",
  ));

  
  # create dir "-r"
  $testDir = "{$dir}/content/storage/cache";
  $testFile = "{$testDir}/my.db";
  $t->ok(! is_dir($testDir), sprintf('test dir "%s" does not exists', $testDir));
  $t->ok(! is_file("{$testDir}/my.db"), sprintf('test file "%s" does not exists', $testFile));

  $c = new sfSQLitePDOCache(array(
    'dsn' => "sqlite:{$testFile}",
  ));

  $t->ok(is_dir($testDir), sprintf('test dir "%s" exists', $testDir));
  $t->ok(is_file("{$testDir}/my.db"), sprintf('test file "%s" exists', $testFile));

  unlink($testFile);
  rmdir($testDir);
