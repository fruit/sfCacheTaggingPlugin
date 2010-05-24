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

  $confTests = array(
    array('options' => array(), 'throw' => false),
    array('options' => array('versionColumn' => ''), 'throw' => true),
    array('options' => array('versionColumn' => 2321), 'throw' => true),
    array('options' => array('versionColumn' => 'x'), 'throw' => false),
  );

  foreach ($confTests as $test)
  {
    list($args, $shouldThrow) = array_values($test);

    $class = 'sfConfigurationException';
    $argsText = str_replace("\n", '', var_export($args, true));
    try
    {
      new Doctrine_Template_Cachetaggable($args);

      if (! $shouldThrow)
      {
        $t->pass(sprintf('Exception "%s" not thrown on args = "%s"', $class, $argsText));
      }
      else
      {
        $t->fail(sprintf('Exception "%s" shall be thrown on args = "%s"', $class, $argsText));
      }
    }
    catch (sfConfigurationException $e)
    {
      if ($shouldThrow)
      {
        $t->pass(sprintf('Exception "%s" is thrown on args = "%s"', $class, $argsText));
      }
      else
      {
        $t->fail(sprintf('Exception "%s" should not be thrown on args = "%s"', $class, $argsText));
      }
    }
  }
  