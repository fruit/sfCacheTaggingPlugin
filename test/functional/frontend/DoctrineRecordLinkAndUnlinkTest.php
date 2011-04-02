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

  # update all tags
  $sfTagger->clean();
  RelSiteCultureTable::getInstance()->createQuery()->useResultCache()->execute();

  $con->beginTransaction();
  $site = RelSiteTable::getInstance()->find(1);
  $site->unlink('RelCultures', $ids = array(1, 2, 5));
  $site->save();
  foreach ($ids as $id)
  {
    $tagName = sprintf('RelSiteCulture:%d:%d', $id, 1);
    $t->ok(! $sfTagger->hasTag($tagName), sprintf("Tag `%s` is removed", $tagName));
  }
  $con->rollback();

  $sfTagger->clean();
  RelSiteCultureTable::getInstance()->createQuery()->useResultCache()->execute();

  $con->beginTransaction();
  $site->link('RelCultures', $ids = array(3, 4));
  $site->save();
  foreach ($ids as $id)
  {
    $tagName = sprintf('RelSiteCulture:%d:%d', $id, 1);
    $t->ok($sfTagger->hasTag($tagName), sprintf("Tag `%s` is created", $tagName));
  }
  $con->rollback();

  $sfTagger->clean();
  RelSiteCultureTable::getInstance()->createQuery()->useResultCache()->execute();

  $con->beginTransaction();
  $culture = RelCultureTable::getInstance()->find(1);
  $culture->unlink('RelSites', $ids = array(1, 3));
  $culture->save();
  foreach ($ids as $id)
  {
    $tagName = sprintf('RelSiteCulture:%d:%d', 1, $id);
    $t->ok(! $sfTagger->hasTag($tagName), sprintf("Tag `%s` is removed", $tagName));
  }
  $con->rollback();


  $sfTagger->clean();
  RelSiteCultureTable::getInstance()->createQuery()->useResultCache()->execute();

  $con->beginTransaction();
  $culture = RelCultureTable::getInstance()->find(1);
  $culture->link('RelSites', $ids = array(1, 3, 4));
  $culture->save();
  foreach ($ids as $id)
  {
    $tagName = sprintf('RelSiteCulture:%d:%d', $id, 1);
    $t->ok($sfTagger->hasTag($tagName), sprintf("Tag `%s` is created", $tagName));
  }

  $con->rollback();