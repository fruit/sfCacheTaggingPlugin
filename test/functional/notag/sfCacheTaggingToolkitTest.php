<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  require_once realpath(dirname(__FILE__) . '/../../../../../test/bootstrap/functional.php');
  require_once sfConfig::get('sf_symfony_lib_dir') . '/vendor/lime/lime.php';

  $t = new lime_test();

  # check component.method_not_found

  include_once sfConfig::get('sf_apps_dir') . '/notag/modules/blog_post/actions/actions.class.php';

  try
  {
    $e = new sfEvent(
      new blog_postActions(sfContext::getInstance(), 'blog_post', 'run'),
      'component.method_not_found',
      array('method' => 'callMe', 'arguments' => array(1, 2, 3))
    );

    $v = sfCacheTaggingToolkit::listenOnComponentMethodNotFoundEvent($e);

    $t->ok(null === $v, 'Return null if view manager is defualt');
  }
  catch (Exception $e)
  {
    $t->fail($e->getMessage());
  }

  try
  {
    sfCacheTaggingToolkit::getTaggingCache();

    $t->fail();
  }
  catch (sfCacheDisabledException $e)
  {
    $t->pass($e->getMessage());
  }

  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', true);

  try
  {
    sfCacheTaggingToolkit::getTaggingCache();
    $t->fail();
  }
  catch (sfConfigurationException $e)
  {
    $t->pass($e->getMessage());
  }

  try
  {
    $e = new sfEvent(
      new blog_postActions(sfContext::getInstance(), 'blog_post', 'run'),
      'component.method_not_found',
      array('method' => 'callMe', 'arguments' => array(1, 2, 3))
    );

    $v = sfCacheTaggingToolkit::listenOnComponentMethodNotFoundEvent($e);

    $t->ok(null === $v, 'Return null if method does not exists');
  }
  catch (BadMethodCallException $e)
  {
    $t->pass($e->getMessage());
  }

  sfConfig::set('sf_cache', $optionSfCache);


  # getBaseClassName

  class ClassNameProvider
  {
    public static function decorate ($name)
    {
      return strtr($name, array('Frontend' => '', 'Backend' => ''));
    }
  }

  $optionProvider = sfConfig::get('app_sfcachetaggingplugin_object_class_tag_name_provider');

  sfConfig::set('app_sfcachetaggingplugin_object_class_tag_name_provider', null);
  $t->is(
    sfCacheTaggingToolkit::getBaseClassName('FrontendCompany'),
    'FrontendCompany'
  );

  sfConfig::set(
    'app_sfcachetaggingplugin_object_class_tag_name_provider',
    array('ClassNameProvider', 'decorate')
  );

  $t->is(sfCacheTaggingToolkit::getBaseClassName('FrontendCompany'), 'Company');

  # second time from buffer
  $t->is(sfCacheTaggingToolkit::getBaseClassName('FrontendCompany'), 'Company');


  sfConfig::set('app_sfcachetaggingplugin_object_class_tag_name_provider', $optionProvider);