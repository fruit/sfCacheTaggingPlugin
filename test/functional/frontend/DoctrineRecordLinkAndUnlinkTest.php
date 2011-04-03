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

  $sfContext = sfContext::getInstance();
  $cacheManager = $sfContext->getViewCacheManager();
  $sfTagger = $cacheManager->getTaggingCache();

  $con = Doctrine_Manager::getInstance()->getConnection('doctrine');
  $con->beginTransaction();

  $site = RelSiteTable::getInstance()->find(1);
  $site->link('Category', array(2));
  $site->save();

  $t->is($site->getCultures()->count(), 3, 'linking incorrect alias');
  $t->is($site->getCategory()->getId(), 2, 'linking still work for other relations');
  $con->rollback();

  # update all tags
  $sfTagger->clean();
  RelSiteCultureTable::getInstance()->createQuery()->useResultCache()->execute();

  $con->beginTransaction();
  $site = RelSiteTable::getInstance()->find(1);
  $site->unlink('Cultures', $ids = array(1, 2, 5));
  $site->save();
  foreach ($ids as $id)
  {
    $tagName = sprintf('RelSiteCulture:%d:%d', $id, 1);
    $t->ok(! $sfTagger->hasTag($tagName), sprintf("Tag `%s` is removed (Cultures)", $tagName));
  }
  $con->rollback();

  $sfTagger->clean();
  RelSiteCultureTable::getInstance()->createQuery()->useResultCache()->execute();

  $con->beginTransaction();
  $site->link('Cultures', $ids = array(3, 4));
  $site->save();
  foreach ($ids as $id)
  {
    $tagName = sprintf('RelSiteCulture:%d:%d', $id, 1);
    $t->ok($sfTagger->hasTag($tagName), sprintf("Tag `%s` is created (Cultures)", $tagName));
  }
  $con->rollback();

  $sfTagger->clean();
  RelSiteCultureTable::getInstance()->createQuery()->useResultCache()->execute();

  $con->beginTransaction();
  $culture = RelCultureTable::getInstance()->find(1);
  $culture->unlink('Sites', $ids = array(1, 3));
  $culture->save();
  foreach ($ids as $id)
  {
    $tagName = sprintf('RelSiteCulture:%d:%d', 1, $id);
    $t->ok(! $sfTagger->hasTag($tagName), sprintf("Tag `%s` is removed (Sites)", $tagName));
  }
  $con->rollback();


  $sfTagger->clean();
  $cultures = RelSiteCultureTable::getInstance()->createQuery()->useResultCache()->execute();

  $con->beginTransaction();
  $culture = RelCultureTable::getInstance()->find(1);
  $culture->link('Sites', $ids = array(2, 4));
  $culture->save();
  foreach ($ids as $id)
  {
    $tagName = sprintf('RelSiteCulture:%d:%d', 1, $id);
    $t->ok($sfTagger->hasTag($tagName), sprintf("Tag `%s` is created (Sites)", $tagName));
  }
  $con->rollback();

  $con->beginTransaction();
  $culture = RelCultureTable::getInstance()->find(1);
  $culture->link('Sites', $ids = array());
  $culture->save();
  foreach ($cultures as $culture)
  {
    $t->ok(
      $sfTagger->hasTag($culture->obtainTagName()),
      sprintf("Tag `%s` still there", $culture->obtainTagName())
    );
  }
  $con->rollback();
