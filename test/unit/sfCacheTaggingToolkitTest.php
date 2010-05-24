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

  sfConfig::set('app_sfcachetaggingplugin_tag_lifetime', 0);

  $methods = array(
    'lock_lifetime' => array(
      array('value' => null, 'shouldThrown' => false),
      array('value' => -1, 'shouldThrown' => true),
      array('value' => 0, 'shouldThrown' => true),
      array('value' => 1, 'shouldThrown' => false),
      array('value' => 6, 'shouldThrown' => false),
    ),
    'tag_lifetime' => array(
      array('value' => null, 'shouldThrown' => false),
      array('value' => -1, 'shouldThrown' => true),
      array('value' => 0, 'shouldThrown' => true),
      array('value' => 1, 'shouldThrown' => false),
      array('value' => 6, 'shouldThrown' => false),
    )

  );

  foreach ($methods as $method_name => $checks)
  {
    foreach ($checks as $check)
    {
      list($value, $shouldThrow) = array_values($check);

      $const = sprintf('app_sfcachetaggingplugin_%s', $method_name);
      sfConfig::set($const, $value);

      try
      {
        $newValue = call_user_func(
          sprintf(
            'sfCacheTaggingToolkit::get%s',
            sfInflector::camelize($method_name)
          )
        );

        if ($shouldThrow)
        {
          $t->fail(sprintf('Value "%s" is uncompatible. No exceptions was thrown', $value));
        }
        else
        {
          $t->pass(sprintf('Value "%s" is compatible. No exceptions was thrown', $value));
        }
      }
      catch (OutOfBoundsException $e)
      {
        if (! $shouldThrow)
        {
          $t->fail(sprintf('Value "%s" is compatible. Exceptions was thrown', $value));
        }
        else
        {
          $t->pass(sprintf('Value "%s" is incompatible. Exceptions was thrown', $value));
        }
      }
    }
  }