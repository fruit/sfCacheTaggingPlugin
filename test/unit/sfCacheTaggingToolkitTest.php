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


  $option = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', true);

  try
  {
    sfCacheTaggingToolkit::getTaggingCache();
    $t->fail();
  }
  catch (sfCacheMissingContextException $e)
  {
    $t->pass($e->getMessage());
  }

  sfConfig::set('sf_cache', $option);

  # getModelTagNameSeparator
  
  $t->is(sfCacheTaggingToolkit::getModelTagNameSeparator(), sfCache::SEPARATOR);

  $option = sfConfig::get('app_sfcachetaggingplugin_model_tag_name_separator');

  sfConfig::set('app_sfcachetaggingplugin_model_tag_name_separator', '_');

  $t->is(sfCacheTaggingToolkit::getModelTagNameSeparator(), '_');

  sfConfig::set('app_sfcachetaggingplugin_model_tag_name_separator', $option);


