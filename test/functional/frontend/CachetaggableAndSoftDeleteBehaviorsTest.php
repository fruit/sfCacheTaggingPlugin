<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2012 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  include_once realpath(dirname(__FILE__) . '/../../bootstrap/functional.php');
  include_once sfConfig::get('sf_symfony_lib_dir') . '/vendor/lime/lime.php';

  $separator = sfCacheTaggingToolkit::getModelTagNameSeparator();

  $sfContext = sfContext::getInstance();
  $cacheManager = $sfContext->getViewCacheManager();
  $sfTagger = $cacheManager->getTaggingCache();
  /* @var $sfTagger sfTaggingCache */

  $t = new lime_test();

//  FoodTable::getInstance()->createQuery()->delete()->execute();
//  FoodReorderedTable::getInstance()->createQuery()->delete()->execute();

  $connection = Doctrine_Manager::getInstance()->getCurrentConnection();
  $connection->beginTransaction();

/**
 * Testing model with SoftDelete and Cachetagging behavior
 */
  $classTests = array(                                // [ORDER]
    array('Food', 'Manufacturer'),                    // SoftDelete -> this
    array('FoodReordered', 'ManufacturerReordered')   // this -> SoftDelete
  );

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

      $removedItemsTags[${$instanceName}->obtainTagName()] = ${$instanceName}->obtainObjectVersion();
      ${$instanceName}->delete();
    }

    $q = Doctrine::getTable($foodClassName)->createQuery();

    // this should not update "test_A" records, because they was removed
    $q->delete()->set('title', '?', array('test_A2'))->where('title = "test_A"')->execute();

    foreach ($removedItemsTags as $tagName => $tagVersion)
    {
      $t->ok(! $sfTagger->hasTag($tagName), "For delete item {$tagName} version is re-setted");
    }

    $t->diag('deleted objects registry');

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

  //  $t->is($juice->getCacheTags(true), array(), 'Juice is remove, no cache tags');
  //  $t->is($limo->getCacheTags(true), array(), 'Limo is remove, no cache tags');
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

    $t->ok($sfTagger->hasTag($choleTagName), '"Chole" tag name is valid');
    $t->ok($sfTagger->hasTag($choleCollectionName), '"Chole" collection tag name is valid');
    $t->ok($sfTagger->hasTag($indianHealthFoodTagName), '"Indian Health Food Ltd" tag name is valid');
    $t->ok($sfTagger->hasTag($indianHealthFoodCollectionName), '"Indian Health Food Ltd" collection tag name is valid');

    $choleColVer = $sfTagger->getTag($choleCollectionName);
    $indianColVer = $sfTagger->getTag($indianHealthFoodCollectionName);
    $chole->delete();

    $t->ok(! $sfTagger->hasTag($choleTagName), '"Chole" record\'s tag is removed');
    $t->cmp_ok($choleColVer, '<', $sfTagger->getTag($choleCollectionName), '"Chole" record\'s collection tag is regenerated');
    $t->ok(! $sfTagger->hasTag($indianHealthFoodTagName), '"Indian Health Food Ltd" record\'s tag is removed');
    $t->cmp_ok($indianColVer, '<', $sfTagger->getTag($indianHealthFoodCollectionName), '"Indian Health Food Ltd" record\'s collection tag is regenerated');

  //  $t->is($chole->getCacheTags(true), array(), 'Removed object softly/hardly returns an empty array of tags recursively');
  //  $t->is($chole->getCacheTags(false), array(), 'Removed object softly/hardly return an empty array of tags');

    $t->diag('preDelete + SoftDelete');

    $bananas = new $foodClassName();
    $bananas->setTitle('Bananas');
    $bananas->save();

    $bananasTagName = $bananas->obtainTagName();
    $t->ok($sfTagger->hasTag($bananasTagName), 'Bananas (Food object) tag name exists in cache');

    $value = $bananas->delete();

    $t->ok($value, "Bananas is soft-deleted");

    $t->ok(! $sfTagger->hasTag($bananasTagName), 'Pseudo-removed record does not have a tag');

    # preDqlDelete + SoftDelete plugin conflict

    $nutsName = 'Indian Nuts';
    $nuts = new $foodClassName();
    $nuts->setTitle($nutsName);
    $nuts->save();

    $nutVersion = $nuts->getObjectVersion();

    $t->ok($sfTagger->hasTag(sprintf('%s%s%d', $foodClassName, $separator, $nuts->getId())), 'Tag exists');

    $q = Doctrine::getTable($foodClassName)->createQuery();
    $rows = $q->delete()->where('title = ?', $nutsName)->execute();

    $t->is($rows, 1, 'One row deleted');

    $t->ok( ! $sfTagger->hasTag(sprintf('%s%s%d', $foodClassName, $separator, $nuts->getId())), 'Tag is removed');

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
      $sfTagger->hasTag($key),
      sprintf('new tag saved to backend with key "%s"', $key)
    );

    $limon = new $foodClassName();
    $limon->setTitle('Limon');
    $limon->save();

    $collectionVersion = $limon->obtainCollectionVersion();

    Doctrine::getTable($foodClassName)
      ->createQuery()
      ->delete()
      ->addWhere('title = ?', 'Limon')
      ->execute();

    $t->cmp_ok($sfTagger->getTag($foodClassName), '>', $collectionVersion);
    $t->ok(! $sfTagger->hasTag("{$foodClassName}:{$limon->getId()}"));

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
    $t->ok($sfTagger->hasTag($key), sprintf('key still exists "%s"', $key));

  }

  $connection->rollback();