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
    'getTags',
    'obtainTagName',
    'obtainObjectVersion',
    'obtainCollectionName',
    'obtainCollectionVersion',
  ), 'Checking for available methods');

  $t->diag('Testing methods content');

  $t->diag('obtainObjectVersion() unsaved');
  $t->is(gettype($obj->obtainObjectVersion()), 'integer', 'Return type is integer');
  $t->is($obj->obtainObjectVersion(), 1, 'Unsaved object returns 1');

  $t->diag('obtainCollectionVersion() unsaved');
  $t->is(gettype($obj->obtainCollectionVersion()), 'string', 'Return type is string');
  $t->cmp_ok($obj->obtainCollectionVersion(), '<', sfCacheTaggingToolkit::generateVersion(), 'Collection tag version could not be greater then current microtime');

  $version1 = $obj->obtainCollectionVersion();
  $version2 = $obj->obtainCollectionVersion();
  $t->cmp_ok($version1, '<', $version2, 'Unsaved object with neved saved any other objects return always current microtime');


  $obj->set('is_enabled', true);
  $obj->setTitle('NetBeans-Platform Training');
  $obj->setSlug('netbeans-platform-training');
  $obj->save();


  $t->diag('updateObjectVersion() saved');
  $version = $obj->obtainObjectVersion();
  $t->isa_ok($obj->updateObjectVersion(), 'BlogPost', 'updateObjectVersion returns BlogPost object');
  $t->cmp_ok($version, '<', $obj->obtainObjectVersion(), 'Update version is newer then taked before');

  $t->diag('getTags() saved');
  $t->is(gettype($obj->getTags()), 'array', 'Return type is array');
  $t->is(count($obj->getTags()), 1, 'Return self tag');

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