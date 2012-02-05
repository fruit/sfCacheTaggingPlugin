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

  BlogPostTable::getInstance()->getConnection()->beginTransaction();

  $t = new lime_test();

  sfCacheTaggingToolkit::getTaggingCache()->clean();

  $t->diag('Testing default class content');
  $obj = new BlogPost();

  try
  {
    $obj->getUnknownTemplate();
    $t->fail('Exception InvalidArgumentException is not thrown');
  }
  catch (InvalidArgumentException $e)
  {
    $t->pass('Exception InvalidArgumentException is thrown');
  }

  $t->can_ok($obj, array(
    'updateObjectVersion',
    'getCacheTags',
    'obtainTagName',
    'obtainObjectVersion',
    'obtainCollectionName',
    'obtainCollectionVersion',
  ), 'Checking for available methods');

  $t->diag('Testing methods content');

  $t->diag('obtainObjectVersion() unsaved');

  // default value changed to string '1' (was 1) to match returning value
  // in Doctrine_Template_Cachetaggable::obtainCollectionVersion and describing
  // table custom column "object_version" specification
  $t->is(gettype($obj->obtainObjectVersion()), 'string', 'Return type is string');
  $t->is(
    $obj->obtainObjectVersion(),
    Doctrine_Template_Cachetaggable::UNSAVED_RECORD_DEFAULT_VERSION,
    'Unsaved object returns 1'
  );

  $t->diag('obtainCollectionVersion() unsaved');
  $t->is(gettype($obj->obtainCollectionVersion()), 'string', 'Return type is string');
  $t->cmp_ok($obj->obtainCollectionVersion(), '<', sfCacheTaggingToolkit::generateVersion(), 'Collection tag version could not be greater then current microtime');

  $version1 = $obj->obtainCollectionVersion();
  $version2 = $obj->obtainCollectionVersion();
  /**
   * @since 4.2.2 Unsaved object memorize first generated version
   *              for next method calls
   */
  $t->cmp_ok($version1, '=', $version2, 'Collection version stays unchanged for future method calls');


  $obj->set('is_enabled', true);
  $obj->setTitle('NetBeans-Platform Training');
  $obj->setSlug('netbeans-platform-training');
  $obj->save();


  $t->diag('updateObjectVersion() saved');
  $version = $obj->obtainObjectVersion();
  $t->isa_ok($obj->updateObjectVersion(), 'BlogPost', 'updateObjectVersion returns BlogPost object');
  $t->cmp_ok($version, '<', $obj->obtainObjectVersion(), 'Update version is newer then taked before');

  $t->diag('getCacheTags() saved');
  $t->is(gettype($obj->getCacheTags()), 'array', 'Return type is array');
  $t->is(count($obj->getCacheTags()), 1, 'Return self tag');

  $t->diag('obtainTagName() saved');
  $t->is(gettype($obj->obtainTagName()), 'string', 'Return type is string');
  $t->is($obj->obtainTagName(), 'BlogPost:' . $obj->getId(), 'Tag name is "ClassName":"PK"');
  BlogPostTable::getInstance()->getConnection()->rollback();

  $t->diag('obtainCollectionName() saved');
  $t->is(gettype($obj->obtainCollectionName()), 'string', 'Return type is string');

  $t->diag('obtainCollectionVersion() saved');
  $t->is(gettype($obj->obtainCollectionVersion()), 'string', 'Return type is string');
  $t->cmp_ok($obj->obtainCollectionVersion(), '<', sfCacheTaggingToolkit::generateVersion(), 'For saved object collection tag version is older then current microtime');

  $t->diag('obtainObjectVersion() saved');
  $t->is(gettype($obj->obtainObjectVersion()), 'string', 'Return type is string');

  $version1 = $obj->obtainCollectionVersion();
  $version2 = $obj->obtainCollectionVersion();
  $t->cmp_ok($version1, '=', $version2, 'For saved object, collection stays the same, till any BlogPost-object is added or removed');