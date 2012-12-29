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
  $cm = $sfContext->getViewCacheManager();
  /* @var $cm sfViewCacheTagManager */
  $tagging = $cm->getTaggingCache();

  $con = Doctrine_Manager::getInstance()->getCurrentConnection();
  $con->beginTransaction();
  $cleanQuery = "SET FOREIGN_KEY_CHECKS = 0; TRUNCATE `weapon`; SET FOREIGN_KEY_CHECKS = 1;";
  $con->exec($cleanQuery);
  $con->commit();
  $tagging->clean();

  $tanto = new Weapon();
  $tanto->setMaterialId(10);
  $tanto->setSizeId(2);
  $tanto->setName('Tanto, 10in, red oaky');
  $tanto->save();

  $firstVersion = $tanto->obtainObjectVersion();
  $firstCollectionVersion = $tanto->obtainCollectionVersion();

  $tanto = new Weapon();
  $tanto->setMaterialId(10);
  $tanto->setSizeId(2);
  $tanto->setName('Tanto, 9in, red oaky');
  $tanto->replace();

  $secordVersion = $tanto->obtainObjectVersion();

  $t->cmp_ok($firstVersion, '<', $secordVersion, '->replace() increments tag version');

  $tanto = WeaponTable::getInstance()->findOneByName('Tanto, 9in, red oaky');
  $t->is($tanto->obtainObjectVersion(), $secordVersion, 'Saved object version match with generated before');
  $t->is($tanto->obtainCollectionVersion(), $firstCollectionVersion, 'Saved collection version match with generated before');