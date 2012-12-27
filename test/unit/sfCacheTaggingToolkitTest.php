<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2012 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  include_once realpath(dirname(__FILE__) . '/../bootstrap/unit.php');

  $t = new lime_test();


  $option = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', true);

  try
  {
    $t->isa_ok($c = sfCacheTaggingToolkit::getTaggingCache(), 'sfTaggingCache');
    $t->isa_ok($c->getCache(), 'sfNoTaggingCache');
    $t->isa_ok($c->getLogger(), 'sfNoCacheTagLogger');
    $t->pass('Return blank classes');
  }
  catch (Exception $e)
  {
    $t->fail($e->getMessage());
  }

  sfConfig::set('sf_cache', $option);

  # getModelTagNameSeparator

  $t->is(sfCacheTaggingToolkit::getModelTagNameSeparator(), sfCache::SEPARATOR, 'Separator is constant one');
  $option = sfConfig::get('app_sfCacheTagging_model_tag_name_separator');

  sfConfig::set('app_sfCacheTagging_model_tag_name_separator', '_');
  $t->is(sfCacheTaggingToolkit::getModelTagNameSeparator(), '_', 'set "_" and get it back');

  sfConfig::set('app_sfCacheTagging_model_tag_name_separator', $option);


  # getBaseClassName

  class TestProvider
  {
    public static function publicStatic ($name) { return ucfirst($name); }
    protected static function protectedStatic ($name) { return strtolower($name); }
    protected function protectedMethod ($name) { return "new_{$name}"; }
    public function publicMethod ($name) { return strrev($name); }
  }

  $optionProvider = sfConfig::get('app_sfCacheTagging_object_class_tag_name_provider');

  sfConfig::set('app_sfCacheTagging_object_class_tag_name_provider', array());
  $t->is(sfCacheTaggingToolkit::getBaseClassName('Cup'), 'Cup', '[]');
  sfConfig::set('app_sfCacheTagging_object_class_tag_name_provider', null);
  $t->is(sfCacheTaggingToolkit::getBaseClassName('Cup'), 'Cup', 'NULL');

  sfConfig::set('app_sfCacheTagging_object_class_tag_name_provider', array('TestProvider', 'publicStatic'));
  $t->is(sfCacheTaggingToolkit::getBaseClassName('hello_text'), 'Hello_text', '[TestProvider, publicStatic]');

  sfConfig::set('app_sfCacheTagging_object_class_tag_name_provider', 'TestProvider::publicStatic');
  $t->is(sfCacheTaggingToolkit::getBaseClassName('hello_text'), 'Hello_text', 'TestProvider::publicStatic');


  sfConfig::set('app_sfCacheTagging_object_class_tag_name_provider', array('TestProvider', 'protectedMethod'));
  $t->is(sfCacheTaggingToolkit::getBaseClassName('My'), 'My', '[TestProvider, protectedMethod]'); // is protected

  sfConfig::set('app_sfCacheTagging_object_class_tag_name_provider', 'TestProvider::protectedMethod');
  $t->is(sfCacheTaggingToolkit::getBaseClassName('My'), 'My', 'TestProvider::protectedMethod'); // is protected


  sfConfig::set('app_sfCacheTagging_object_class_tag_name_provider', array('TestProvider', 'publicMethod'));
  $t->is(sfCacheTaggingToolkit::getBaseClassName('abc'), 'cba', '[TestProvider, publicMethod]');
  sfConfig::set('app_sfCacheTagging_object_class_tag_name_provider', 'TestProvider::publicMethod');
  $t->is(sfCacheTaggingToolkit::getBaseClassName('abc'), 'cba', 'TestProvider::publicMethod');

  sfConfig::set('app_sfCacheTagging_object_class_tag_name_provider', 'TestProvider::protectedStatic');
  $t->is(sfCacheTaggingToolkit::getBaseClassName('CAP'), 'CAP', 'TestProvider::protectedStatic'); // is private


  sfConfig::set('app_sfCacheTagging_object_class_tag_name_provider', $optionProvider);