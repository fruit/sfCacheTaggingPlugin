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

  $connection = AirCompanyTable::getInstance()->getConnection();
  $connection->beginTransaction();


  $jetFlight = new AirCompany();
  $jetFlight->setName('Jet Flights');
  $jetFlight->setSince('2003-23-03');
  $jetFlight->setIsEnabled(true);
  $jetFlight->setIsDeleted(false);
  $jetFlight->save();

  $id = $jetFlight->getId();

  $colVersion = $jetFlight->obtainCollectionVersion();
  $objVersion = $jetFlight->obtainObjectVersion();

  $t->cmp_ok($colVersion, '=', $objVersion, 'Collection and object version are equal');

  $jetFlight->setName('Jet Flights & Co')->save();

  $t->cmp_ok($objVersion, '<', $jetFlight->obtainObjectVersion(), 'Object version is updated');
  $t->cmp_ok($colVersion, '=', $jetFlight->obtainCollectionVersion(), 'Collection version is NOT updated');

  $objVersion = $jetFlight->obtainObjectVersion();
  $colVersion = $jetFlight->obtainCollectionVersion();


  $jetFlight->setIsDeleted(true)->save();
  $t->cmp_ok($objVersion, '<', $jetFlight->obtainObjectVersion(), 'Object version is updated');
  $t->cmp_ok($colVersion, '<', $jetFlight->obtainCollectionVersion(), 'Collection version is updated');

  $objVersion = $jetFlight->obtainObjectVersion();
  $colVersion = $jetFlight->obtainCollectionVersion();

  $jetFlight->setIsEnabled(false)->setIsDeleted(false)->save();
  $t->cmp_ok($objVersion, '<', $jetFlight->obtainObjectVersion(), 'Object version is updated');
  $t->cmp_ok($colVersion, '<', $jetFlight->obtainCollectionVersion(), 'Collection version is updated');

  $objVersion = $jetFlight->obtainObjectVersion();
  $colVersion = $jetFlight->obtainCollectionVersion();

  $aff = AirCompanyTable::getInstance()
    ->createQuery()
    ->update()
    ->set('Name', '?', 'Jet Flights & Partners')
    ->execute();

  $t->is($aff, 1);

  $jetFlight = AirCompanyTable::getInstance()->find($id);

  $t->cmp_ok($objVersion, '<', $jetFlight->obtainObjectVersion(), 'Object version is NOT updated');
  $t->cmp_ok($colVersion, '=', $jetFlight->obtainCollectionVersion(), 'Collection version is NOT updated');

  $objVersion = $jetFlight->obtainObjectVersion();
  $colVersion = $jetFlight->obtainCollectionVersion();

  $aff = AirCompanyTable::getInstance()
    ->createQuery()
    ->update()
    ->set('is_enabled', '?', true)
    ->execute();

  $t->is($aff, 1);

  $jetFlight = AirCompanyTable::getInstance()->find($id);

  $t->cmp_ok($objVersion, '<', $jetFlight->obtainObjectVersion(), 'Object version is updated');
  $t->cmp_ok($colVersion, '<', $jetFlight->obtainCollectionVersion(), 'Collection version is updated');

  $connection->rollback();

