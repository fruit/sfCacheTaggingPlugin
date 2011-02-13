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

  $connection = DeviceTable::getInstance()->getConnection();
  $connection->beginTransaction();

  $obj = new Device();
  $obj->setName('Color Picker');
  $obj->save();

  $collectionVersion = $obj->obtainCollectionVersion();

  $obj->setName('Color Picker v2');
  $obj->save();

  $t->cmp_ok($collectionVersion, '<', $obj->obtainCollectionVersion(), 'Collection version updated due to setting "invalidateCollectionVersionOnUpdate: true" (Record)');

  $collectionVersion = $obj->obtainCollectionVersion();

  $affected = DeviceTable::getInstance()->createQuery('d')
    ->update()
    ->set('name', '?', 'Color Picker v3')
    ->where('id = ?', $obj->getId())
    ->execute();

  $t->is($affected, 1, 'Updated 1 row');

  $obj = DeviceTable::getInstance()->findOneByName('Color Picker v3');

  $t->cmp_ok($collectionVersion, '<', $obj->obtainCollectionVersion(), 'Collection version updated due to setting "invalidateCollectionVersionOnUpdate: true" (DQL)');

  $connection->rollback();