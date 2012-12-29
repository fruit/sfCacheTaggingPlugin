<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2013 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  include_once realpath(dirname(__FILE__) . '/../../bootstrap/functional.php');
  include_once sfConfig::get('sf_symfony_lib_dir') . '/vendor/lime/lime.php';

  $t = new lime_test();

  $sfContext = sfContext::getInstance();
  $cacheManager = $sfContext->getViewCacheManager();
  $tagging = $cacheManager->getTaggingCache();


  sfConfig::set('app_sfCacheTagging_collection_tag_name_format', null);
  sfConfig::set('app_sfCacheTagging_object_tag_name_format', null);
  sfConfig::set('app_sfCacheTagging_object_class_tag_name_provider', null);

  $con = Doctrine_Manager::getInstance()->getCurrentConnection();

  $truncateQuery = array_reduce(
    array('rel_category','rel_culture','rel_site','rel_site_culture','rel_site_setting'),
    function ($return, $val) { return "{$return} TRUNCATE {$val};"; }, ''
  );
  $cleanQuery = "SET FOREIGN_KEY_CHECKS = 0;{$truncateQuery}; SET FOREIGN_KEY_CHECKS = 1;";

  $con->beginTransaction();
  $con->exec($cleanQuery);
  Doctrine::loadData(sfConfig::get('sf_data_dir') .'/fixtures/cascade.yml');
  $con->commit();


  $site = RelSiteTable::getInstance()->find(1);
  $site->link('Category', array(2));
  $site->save();

  $t->is($site->getCultures()->count(), 3, 'linking incorrect alias');
  $t->is($site->getCategory()->getId(), 2, 'linking still work for other relations');

  # update all tags

  $con->beginTransaction();
  $con->exec($cleanQuery);
  Doctrine::loadData(sfConfig::get('sf_data_dir') .'/fixtures/cascade.yml');
  $con->commit();
  $tagging->clean();

  $site = RelSiteTable::getInstance()->find(1);
  $site->unlink('Cultures', $ids = array(1, 2, 5));
  $site->save();
  foreach ($ids as $id)
  {
    $tagName = sprintf('RelSiteCulture:%d:%d', $id, 1);
    $t->ok(! $tagging->hasTag($tagName), sprintf("Tag `%s` is removed (Cultures)", $tagName));
  }

  $con->beginTransaction();
  $con->exec($cleanQuery);
  Doctrine::loadData(sfConfig::get('sf_data_dir') .'/fixtures/cascade.yml');
  $con->commit();
  $tagging->clean();

  $con->beginTransaction();
  $site = RelSiteTable::getInstance()->find(1);
  $site->link('Cultures', $ids = array(3, 4));
  $site->save();
  foreach ($ids as $id)
  {
    $tagName = sprintf('RelSiteCulture:%d:%d', $id, 1);
    $t->ok($tagging->hasTag($tagName), sprintf("Tag `%s` is created (Cultures)", $tagName));
  }

  $con->beginTransaction();
  $con->exec($cleanQuery);
  Doctrine::loadData(sfConfig::get('sf_data_dir') .'/fixtures/cascade.yml');
  $con->commit();
  $tagging->clean();

  $culture = RelCultureTable::getInstance()->find(1);
  $culture->unlink('Sites', $ids = array(1, 3));
  $culture->save();
  foreach ($ids as $id)
  {
    $tagName = sprintf('RelSiteCulture:%d:%d', 1, $id);
    $t->ok(! $tagging->hasTag($tagName), sprintf("Tag `%s` is removed (Sites)", $tagName));
  }

  $con->beginTransaction();
  $con->exec($cleanQuery);
  Doctrine::loadData(sfConfig::get('sf_data_dir') .'/fixtures/cascade.yml');
  $con->commit();
  $tagging->clean();

  $culture = RelCultureTable::getInstance()->find(1);
  $culture->link('Sites', $ids = array(2, 4));
  $culture->save();
  foreach ($ids as $id)
  {
    $tagName = sprintf('RelSiteCulture:%d:%d', 1, $id);
    $t->ok($tagging->hasTag($tagName), sprintf("Tag `%s` is created (Sites)", $tagName));
  }

  $con->beginTransaction();
  $con->exec($cleanQuery);
  Doctrine::loadData(sfConfig::get('sf_data_dir') .'/fixtures/cascade.yml');
  $con->commit();
  $tagging->clean();

  $culture = RelCultureTable::getInstance()->find(1);
  $culture->unlink('Sites', $ids = range(1, 5));
  $culture->save();
  foreach ($ids as $id)
  {
    $tagName = sprintf('RelSiteCulture:%d:%d', 1, $id);
    $t->ok(! $tagging->hasTag($tagName), sprintf("Tag `%s` is created (Sites)", $tagName));
  }
