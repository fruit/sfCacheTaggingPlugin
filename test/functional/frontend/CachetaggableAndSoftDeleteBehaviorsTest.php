<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2013 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  include_once realpath(dirname(__FILE__) . '/../../bootstrap/functional.php');
//  include_once realpath(dirname(__FILE__) . '/../../bootstrap/linelime.php');
  include_once sfConfig::get('sf_symfony_lib_dir') . '/vendor/lime/lime.php';

  $sfContext = sfContext::getInstance();
  $cacheManager = $sfContext->getViewCacheManager();
  $tagging = $cacheManager->getTaggingCache();

  /* @var $tagging sfTaggingCache */
  $t = new lime_test();

  FoodTable::getInstance()->createQuery()->delete()->execute();
  FoodReorderedTable::getInstance()->createQuery()->delete()->execute();

/**
 * Testing model with SoftDelete and Cachetagging behavior
 */
  $classTests = array(                                // [ORDER]
    array('Food', 'Manufacturer'),                    // SoftDelete -> this
    array('FoodReordered', 'ManufacturerReordered')   // this -> SoftDelete
  );

  $tagging->clean();

  foreach ($classTests as $classTests)
  {
    list($foodClassName, $manClassName) = $classTests;

    $t->diag("{$foodClassName}: SoftDelete hydration test on preDqlDelete event");

    $removedItemsTags = array();
    for ($i = 0; $i < 3; $i++)
    {
      $instanceName = "test_A";
      ${$instanceName} = new $foodClassName();
      ${$instanceName}->setTitle($instanceName);
      ${$instanceName}->save();

      $removedItemsTags[
        sfCacheTaggingToolkit::obtainTagName(${$instanceName}->getTable()->getTemplate('Cachetaggable'), ${$instanceName}->toArray(false))
        ] = ${$instanceName}->obtainObjectVersion();
      ${$instanceName}->delete();
    }

    $q = Doctrine::getTable($foodClassName)->createQuery();

    // this should not update "test_A" records, because they was removed
    $q->delete()->set('title', '?', array('test_A2'))->where('title = "test_A"')->execute();

    foreach ($removedItemsTags as $tagName => $tagVersion)
    {
      $t->ok(! $tagging->hasTag($tagName), "For delete item {$tagName} version is re-setted");
    }

    $t->diag('deleted objects registry');
    $tagging->clean();
    $juice = new $foodClassName();
    $juice->setTitle('Orange juice');
    $juice->save();
    $juice->delete();

    $limo = new $foodClassName();
    $limo->setTitle('Orange limo');
    $limo->save();
    $limo->delete();

    $drink = new $foodClassName();
    $drink->setTitle('Energy drink');
    $drink->save();

    $t->is(count($drink->getCacheTags(true)), 2, 'Energy drink has 2 tags (object+collection)');

    $t->diag('postDelete with object relations');

    $indianHealthFood = new $manClassName();
    $indianHealthFood->setName('Indian Health Food Ltd.');

    $chole = new $foodClassName();
    $chole->setTitle('Chole (chickpea curry)');
    $chole->setManufacturer($indianHealthFood);
    $chole->save();

    $choleTagName = $chole->obtainTagName();
    $indianHealthFoodTagName = $indianHealthFood->obtainTagName();

    $choleCollectionName = $chole->obtainCollectionName();
    $indianHealthFoodCollectionName = $indianHealthFood->obtainCollectionName();

    $t->ok($tagging->hasTag($choleTagName), '"Chole" tag name is valid');
    $t->ok($tagging->hasTag($choleCollectionName), '"Chole" collection tag name is valid');
    $t->ok($tagging->hasTag($indianHealthFoodTagName), '"Indian Health Food Ltd" tag name is valid');
    $t->ok($tagging->hasTag($indianHealthFoodCollectionName), '"Indian Health Food Ltd" collection tag name is valid');

    $choleColVer = $tagging->getTag($choleCollectionName);
    $indianColVer = $tagging->getTag($indianHealthFoodCollectionName);
    $chole->delete();

    $t->ok(! $tagging->hasTag($choleTagName), '"Chole" record\'s tag is removed');
    $t->cmp_ok($choleColVer, '<', $tagging->getTag($choleCollectionName), '"Chole" record\'s collection tag is regenerated');
    $t->ok(! $tagging->hasTag($indianHealthFoodTagName), '"Indian Health Food Ltd" record\'s tag is removed');
    $t->cmp_ok($indianColVer, '<', $tagging->getTag($indianHealthFoodCollectionName), '"Indian Health Food Ltd" record\'s collection tag is regenerated');

  //  $t->is($chole->getCacheTags(true), array(), 'Removed object softly/hardly returns an empty array of tags recursively');
  //  $t->is($chole->getCacheTags(false), array(), 'Removed object softly/hardly return an empty array of tags');

    $t->diag('preDelete + SoftDelete');

    $bananas = new $foodClassName();
    $bananas->setTitle('Bananas');
    $bananas->save();

    $bananasTagName = $bananas->obtainTagName();
    $t->ok($tagging->hasTag($bananasTagName), 'Bananas (Food object) tag name exists in cache');

    $value = $bananas->delete();

    $t->ok($value, "Bananas is soft-deleted");

    $t->ok(! $tagging->hasTag($bananasTagName), 'Pseudo-removed record does not have a tag');

    # preDqlDelete + SoftDelete plugin conflict

    $nutsName = 'Indian Nuts';
    $nuts = new $foodClassName;
    $nuts->setTitle($nutsName);
    $nuts->save();

    $nutVersion = $nuts->getObjectVersion();

    $t->ok($tagging->hasTag(sfCacheTaggingToolkit::obtainTagName($nuts->getTable()->getTemplate('Cachetaggable'), $nuts->toArray(false))), 'Tag exists');

    $q = Doctrine::getTable($foodClassName)->createQuery();
    $rows = $q->delete()->where('title = ?', $nutsName)->execute();

    $t->is($rows, 1, 'One row deleted');
    $t->ok( ! $tagging->hasTag(sfCacheTaggingToolkit::obtainTagName($nuts->getTable()->getTemplate('Cachetaggable'), $nuts->toArray(false))), 'Tag is removed');

    $c = Doctrine::getTable($foodClassName)->getConnection();

    $stmt = $c->execute(
      $e = sprintf(
        'SELECT id, object_version FROM `%s` WHERE `title` = %s LIMIT 1',
        Doctrine::getTable($foodClassName)->getTableName(),
        $c->quote($nutsName)
      )
    );

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $t->isnt($row, false, '1 row found, skipped SoftDelete to check object_version');

    $rowVersion = $row['object_version'];

    // since the preDqlDelete skips removed records
    // nutVersion stayed unchanged
    $t->cmp_ok(
      $nutVersion, '=', $rowVersion,
      sprintf('Object version increased from %s to %s', $nutVersion, $rowVersion)
    );

    $apple = new $foodClassName();
    $apple->setTitle('Yellow apple');
    $apple->save();

    $key = $apple->obtainTagName();

    $t->ok(
      $tagging->hasTag($key),
      sprintf('new tag saved to backend with key "%s"', $key)
    );

    $limon = new $foodClassName();
    $limon->setTitle('Limon');
    $limon->save();

    $t->ok($tagging->hasTag(sfCacheTaggingToolkit::obtainTagName($limon->getTable()->getTemplate('Cachetaggable'), $limon->toArray(false))));

    $collectionVersion = $limon->obtainCollectionVersion();

    Doctrine::getTable($foodClassName)
      ->createQuery()
      ->delete()
      ->addWhere('title = ?', 'Limon')
      ->execute();

    $t->cmp_ok($tagging->getTag(sfCacheTaggingToolkit::obtainCollectionName(Doctrine::getTable($foodClassName))), '>', $collectionVersion);
    $t->ok(! $tagging->hasTag(sfCacheTaggingToolkit::obtainTagName($limon->getTable()->getTemplate('Cachetaggable'), $limon->toArray(false))));

    $optionSfCache = sfConfig::get('sf_cache');
    sfConfig::set('sf_cache', false);

    Doctrine::getTable($foodClassName)
      ->createQuery()
      ->delete()
      ->addWhere('title = ?', 'Yellow apple')
      ->execute()
    ;

    sfConfig::set('sf_cache', $optionSfCache);

    $key = $apple->obtainTagName();
    $t->ok($tagging->hasTag($key), sprintf('key still exists "%s"', $key));

    $tagging->clean();
  }

  FoodTable::getInstance()->createQuery()->delete()->execute();
  FoodReorderedTable::getInstance()->createQuery()->delete()->execute();