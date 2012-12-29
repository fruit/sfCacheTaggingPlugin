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
  $cleanQuery = "SET FOREIGN_KEY_CHECKS = 0; TRUNCATE `device`; SET FOREIGN_KEY_CHECKS = 1;";
  $con->exec($cleanQuery);
  $con->commit();
  $tagging->clean();

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
