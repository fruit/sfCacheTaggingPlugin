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

  sfCacheTaggingToolkit::getTaggingCache()->clean();

  $connection = WeaponTable::getInstance()->getConnection();
  $connection->beginTransaction();

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



  

  $connection->rollback();