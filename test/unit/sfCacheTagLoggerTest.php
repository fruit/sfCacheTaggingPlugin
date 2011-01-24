<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  require_once realpath(dirname(__FILE__) . '/../../../../test/bootstrap/unit.php');

  define('LOGS_DIR', sfConfig::get('sf_plugins_dir') . '/sfCacheTaggingPlugin/test/temp/logs');
  define('LOGS_FILE', LOGS_DIR . '/cache.log');

  function cleanLogDir()
  {
    if (is_dir(LOGS_DIR))
    {
      if (file_exists(LOGS_FILE))
      {
        unlink(LOGS_FILE);
      }

      rmdir(LOGS_DIR);
    }
  }

  class sfOutputCacheTagLogger extends sfCacheTagLogger
  {
    protected function doLog ($char, $key)
    {
      printf("%s %s\n", $char, $key);

      return true;
    }

    public function getExplanationForChar ($char)
    {
      return $this->explainChar($char);
    }
  }

  $t = new lime_test();

  $l = new sfOutputCacheTagLogger(array());

  $l->setOption('new_line', true);

  $t->is($l->getOption('new_line'), true);
  $t->is($l->getOption('new_line', 10), true);
  $t->is($l->getOption('new_line_dir'), null);
  $t->is($l->getOption('new_line_dir', 10), 10);

  $t->is($l->getOptions(), array(
    'auto_shutdown' => true,
    'skip_chars' => '',
    'new_line' => true,
  ));

  $l->initialize(array('time_format' => '%T%z', 'format' => '%char%%EOL%'));

  $t->is($l->getOption('time_format'), '%T%z');
  $t->is($l->getOption('format'), '%char%%EOL%');

  $l->initialize(array('skip_chars' => 'Ll'));

  $t->ok(! $l->log('l', 'Air_68224'), 'l is skipped');
  $t->ok(! $l->log('L', 'Air_68226'), 'L is skipped');
  $t->ok($l->log('X', 'Air_68225'), 'X is written');


  $chars = 'gGhHlLsSuUvVpPeEtTiI';

  for ($i = 0; $i < strlen($chars); $i++)
  {
    $t->is(
      gettype($explanation = $l->getExplanationForChar($chars[$i])),
      'string',
      sprintf('"%s" returns "%s"', $chars[$i], $explanation)
    );
  }

  $t->is($l->getExplanationForChar('-'), 'Unregistered char', '"-" return "Unregistered char"');

  $t->is($l->shutdown(), null);