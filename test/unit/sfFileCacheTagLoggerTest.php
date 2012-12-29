<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2013 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  include_once realpath(dirname(__FILE__) . '/../bootstrap/unit.php');

  define('LOGS_DIR', sfConfig::get('sf_plugins_dir') . '/../../../temp/logs');
  define('LOGS_FILE', LOGS_DIR . '/cache.log');

  function cleanLogDir ()
  {
    if (is_dir(LOGS_DIR))
    {
      if (is_file(LOGS_FILE))
      {
        unlink(LOGS_FILE);
      }

      rmdir(LOGS_DIR);
    }
  }

  function getchmod ($path)
  {
    return octdec(substr(sprintf('%o', fileperms($path)), -4));
  }

  $t = new lime_test();

  cleanLogDir();

  try
  {
    new sfFileCacheTagLogger(array());
    $t->fail();
  }
  catch (sfConfigurationException $e)
  {
    $t->pass($e->getMessage());
  }

  # __constructor

  try
  {
    $l = new sfFileCacheTagLogger(array('file' => '/root/file.log'));
    $t->fail('writing to denided dir /root');
  }
  catch (sfFileException $e)
  {
    $t->pass($e->getMessage());
  }


  $l = new sfFileCacheTagLogger(array(
    'file' => LOGS_FILE,
  ));

  $t->is($o = getchmod(LOGS_FILE), $l->getOption('file_mode'), sprintf('file: -rw-r--r--, got %o', $o));
  $t->is($o = getchmod(LOGS_DIR), $l->getOption('dir_mode'), sprintf('dir: -rwxr-x---, got %o', $o));

  cleanLogDir();

  $l = new sfFileCacheTagLogger(array(
    'file' => LOGS_FILE,
    'file_mode' => 0610,
    'dir_mode' => 0770,
  ));


  $t->is($o = getchmod(LOGS_FILE), 0610, sprintf('file: -rw-r-----, got %o', $o));
  $t->is($o = getchmod(LOGS_DIR), 0770, sprintf('dir: -rwxrwx---, got %o', $o));

  cleanLogDir();


  $l = new sfFileCacheTagLogger(array(
    'file' => LOGS_FILE
  ));

  $t->ok($l->log('U', 'Locker_1231'));

  $fp = fopen(LOGS_FILE, 'r');
  $t->is(fgetc($fp), 'U');
  fclose($fp);

  cleanLogDir();

  $l = new sfFileCacheTagLogger(array(
    'file' => LOGS_FILE,
    'format' => '%char%|%char_explanation%|%key%|%time%|%microtime%%EOL%',
  ));

  $t->ok($l->log('U', 'Locker_1231'));

  $fp = fopen(LOGS_FILE, 'r');
  $t->like(fgets($fp), "/U\|cache\ was\ unlocked\|Locker_1231\|.*\|\d{15,}\s/");
  fclose($fp);

  $t->ok($l->shutdown());
  $t->ok(! $l->shutdown());

  cleanLogDir();

  $l = new sfFileCacheTagLogger(array(
    'file' => LOGS_FILE,
    'skip_chars' => 'oO',
  ));

  $t->ok($l->log('U', 'Locker_1231'));

  $fp = fopen(LOGS_FILE, 'r');
  $t->is(fgetc($fp), 'U');
  fclose($fp);

  $t->ok(! $l->log('o', 'Locker_1232'));
  $t->ok(! $l->log('O', 'Locker_1232'));

  $fp = fopen(LOGS_FILE, 'r');
  $t->is(fgetc($fp), 'U');
  fclose($fp);

  cleanLogDir();
