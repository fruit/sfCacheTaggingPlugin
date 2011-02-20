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


  $jetFligt = new AirCompany();
  $jetFligt->setName('Jet Flights');
  $jetFligt->setSince('2003-23-03');
  $jetFligt->setIsEnabled(true);
  $jetFligt->setIsDeleted(false);
  $jetFligt->save();

  $colVersion = $jetFligt->obtainCollectionVersion();
  $objVersion = $jetFligt->obtainObjectVersion();

  $t->cmp_ok($colVersion, '=', $objVersion, 'Collection and object version are equal');
  $jetFligt->setName('Jet Flights & Co')->save();

  $t->cmp_ok($objVersion, '<', $jetFligt->obtainObjectVersion(), 'Object version updated');
  $t->cmp_ok($colVersion, '=', $jetFligt->obtainCollectionVersion(), 'Collection version is not updated');

  $objVersion = $jetFligt->obtainObjectVersion();

  $jetFligt->setIsDeleted(true)->save();
  $t->cmp_ok($objVersion, '<', $jetFligt->obtainObjectVersion(), 'Object version updated');
  $t->cmp_ok($colVersion, '<', $jetFligt->obtainCollectionVersion(), 'Collection version is not updated');

  $connection->rollback();