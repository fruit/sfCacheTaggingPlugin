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

  $separator = sfCacheTaggingToolkit::getModelTagNameSeparator();

  $sfContext = sfContext::getInstance();
  $cacheManager = $sfContext->getViewCacheManager();

  $t = new lime_test();

  $connection = FoodTable::getInstance()->getConnection();
  $connection->beginTransaction();

  # checking getTags

  $versionBeforeFoodCreated = sfCacheTaggingToolkit::generateVersion();
  $banana = new Food();
  $banana->setTitle('Banana')->save();

  $bananaCollectionVersion = $banana->obtainCollectionVersion();

  # obtainCollectionName
  $t->is($banana->obtainCollectionName(), 'Food', 'Call obtainCollectionName() returns Collection name');

  # obtainCollectionVersion 1
  $t->cmp_ok($banana->obtainCollectionVersion(), '>', $versionBeforeFoodCreated, 'Food version is higher then it could be before creation');

  # obtainCollectionVersion 2
  $banana->setTitle('Tasty banana')->save();
  $t->is($bananaCollectionVersion, $banana->obtainCollectionVersion(), 'By updating banana collection version stays unchanged');

  # obtainCollectionVersion 3
  $eggs = new Food();
  $eggs->setTitle('Eggs')->save();
  $t->cmp_ok($bananaCollectionVersion, '<', $eggs->obtainCollectionVersion(), 'By creating eggs objects, collection tag version increased');

  # obtainCollectionVersion 4
  $t->is($banana->obtainCollectionVersion(), $eggs->obtainCollectionVersion(), 'Banana and eggs collection versions are equal, because of fetching it directly from cache backend');

  # obtainCollectionVersion 5
  # Delete with SoftDelete
  $eggsCollectionVersion = $eggs->obtainCollectionVersion();
  $eggs->delete();

  $t->cmp_ok($eggsCollectionVersion, '<', $banana->obtainCollectionVersion(), 'Banana collection version increased when some other Food object is deleted (object)');

  # obtainCollectionVersion 6

  $bananaCollectionVersion = $banana->obtainCollectionVersion();

  $eggs = new Food();
  $eggs->setTitle('Eggs')->save();

  $q = FoodTable::getInstance()->createQuery();
  $t->is($q->delete()->where('title = ?', 'Eggs')->execute(), 1, 'SoftDelete sets deleted_at (no physical deletion');
  $t->is(FoodTable::getInstance()->findOneByTitle('Eggs'), false, 'Teoreticaly it is removed');

  $t->cmp_ok($bananaCollectionVersion, '<', $banana->obtainCollectionVersion(), 'Banana collection version increased when some other Food object is deleted (DQL)');

  # Delete without SoftDelete behavior
  /**
   * @todo add tests with "Book" table, object and DQL deletes
   */

  $beforeVersion = sfCacheTaggingToolkit::generateVersion();

  $bible = new Book();
  $bible->setLang('en');
  $bible->setSlug('the-holy-bible');
  $bible->save();

  $afterVersion = sfCacheTaggingToolkit::generateVersion();

  $bibleCollectionVersion = $bible->obtainCollectionVersion();

  $t->cmp_ok($bibleCollectionVersion, '>', $beforeVersion, 'Bible collection version is greater then microtime taken before');
  $t->cmp_ok($bibleCollectionVersion, '<', $afterVersion, 'Bible collection version is less then microtime taken after');

  $meinKampf = new Book();
  $meinKampf->setLang('de');
  $meinKampf->setSlug('mein-kampf');
  $meinKampf->save();

  $t->cmp_ok($bibleCollectionVersion, '<', $meinKampf->obtainCollectionVersion(), 'By adding new Book, collection version increases');

  $meinKampfCollectionVersion = $meinKampf->obtainCollectionVersion();

  $meinKampf->setLang('de_CH');
  $meinKampf->save();

  $t->is($meinKampfCollectionVersion, $meinKampf->obtainCollectionVersion(), 'By updating existing Book, collection version stays unchanged');
  $meinKampfCollectionVersion = $meinKampf->obtainCollectionVersion();
  $meinKampf->delete();

  $t->cmp_ok($meinKampfCollectionVersion, '<', $bible->obtainCollectionVersion(), 'By removing Book, collection version increases');

  $bibleCollectionVersion = $bible->obtainCollectionVersion();

  $meinKampf = new Book();
  $meinKampf->setLang('de');
  $meinKampf->setSlug('mein-kampf');
  $meinKampf->save();

  $q = BookTable::getInstance()->createQuery();
  $t->is($q->delete()->where('slug = ?', 'mein-kampf')->execute(), 1, 'DQL removing Book "mein-kampf"');

  $t->cmp_ok($bibleCollectionVersion, '<', $bible->obtainCollectionVersion(), 'By removing Book using DQL, collection version increases');

  $connection->rollback();