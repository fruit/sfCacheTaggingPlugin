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
  $cleanQuery = "SET FOREIGN_KEY_CHECKS = 0; TRUNCATE `air_company`; SET FOREIGN_KEY_CHECKS = 1;";
  $con->exec($cleanQuery);
  $con->commit();
  $tagging->clean();

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