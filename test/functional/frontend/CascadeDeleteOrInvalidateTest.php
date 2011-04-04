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
//  define('PLUGIN_DATA_DIR', realpath(dirname(__FILE__) . '/../../data'));

  global $t, $sfTagger, $alltags;
  $t = new lime_test();

  $sfTagger = sfContext::getInstance()->getViewCacheManager()->getTaggingCache();

//  Doctrine::loadData(PLUGIN_DATA_DIR . '/fixtures/cascade.yml', true);

  $con = Doctrine_Manager::getInstance()->getConnection('doctrine');

  $a = RelSiteTable::getInstance()->findAll()->getCacheTags();
  $b = RelSiteCultureTable::getInstance()->findAll()->getCacheTags();
  $c = RelCultureTable::getInstance()->findAll()->getCacheTags();
  $d = RelSiteSettingTable::getInstance()->findAll()->getCacheTags();

  $sfTagger->setTags($alltags = array_merge($a, $b, $c, $d));

  function checkTags ($microtime, array $toDelete, array $toInvalidate)
  {
    global $t, $sfTagger, $alltags;

    static $number = 1;

    $t->diag(sprintf('Test #%d', $number));

    foreach ($alltags as $tagName => $tagVersion)
    {
      if (in_array($tagName, $toDelete))
      {
        $t->ok(! $sfTagger->hasTag($tagName), sprintf('Tag "%s" is removed', $tagName));
      }
      elseif (in_array ($tagName, $toInvalidate))
      {
        $t->cmp_ok($sfTagger->getTag($tagName), '>', $microtime, sprintf('Tag "%s" is invalidated', $tagName));
      }
      else
      {
        $t->ok($sfTagger->hasTag($tagName), sprintf('Tag "%s" exists', $tagName));
        $t->cmp_ok($sfTagger->getTag($tagName), '<', $microtime, sprintf('Version of "%s" is not updated', $tagName));
      }
    }

    $a = RelSiteTable::getInstance()->findAll()->getCacheTags();
    $b = RelSiteCultureTable::getInstance()->findAll()->getCacheTags();
    $c = RelCultureTable::getInstance()->findAll()->getCacheTags();
    $d = RelSiteSettingTable::getInstance()->findAll()->getCacheTags();

    $sfTagger->setTags(array_merge($a, $b, $c, $d));

    $number ++;
  }

  $run = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11);

  $t->diag('RelSite model');

  if (in_array(1, $run))
  {
    $version = sfCacheTaggingToolkit::generateVersion();
    $con->beginTransaction();
    RelSiteTable::getInstance()->find(1)->delete();
    $con->rollback();

    checkTags(
      $version,
      array (
        'RelSite:1',
        'RelSiteSetting:1',
        'RelSiteCulture:1:1',
        'RelSiteCulture:2:1',
        'RelSiteCulture:5:1',
      ),
      array(
        'RelSite', # marked not by cascade mechanism
        'RelSiteSetting', # marked not by cascade mechanism
        'RelSiteCulture', # marked not by cascade mechanism
      )
    );
  }

  if (in_array(2, $run))
  {
    $version = sfCacheTaggingToolkit::generateVersion();
    $con->beginTransaction();
    RelSiteTable::getInstance()->find(4)->delete();
    $con->rollback();

    checkTags(
      $version,
      array (
        'RelSite:4',
        'RelSiteSetting:4',
      ),
      array(
        'RelSite', # marked not by cascade mechanism
        'RelSiteSetting', # marked not by cascade mechanism
      )
    );
  }

  if (in_array(3, $run))
  {
    $version = sfCacheTaggingToolkit::generateVersion();
    $con->beginTransaction();
    RelSiteTable::getInstance()->createQuery()->delete()->andWhereIn('id', array(2, 3))->execute();
    $con->rollback();

    checkTags(
      $version,
      array (
        'RelSite:2',
        'RelSiteSetting:2',
        'RelSiteCulture:2:2',
        'RelSiteCulture:5:2',
        'RelSite:3',
        'RelSiteSetting:3',
        'RelSiteCulture:1:3',
        'RelSiteCulture:7:3',
      ),
      array(
        'RelSite', # marked not by cascade mechanism
        'RelSiteSetting', # marked not by cascade mechanism
        'RelSiteCulture', # marked not by cascade mechanism
      )
    );
  }

  return;

  $t->diag('RelCulture model (tree)');

  if (in_array(4, $run))
  {
    $version = sfCacheTaggingToolkit::generateVersion();
    $con->beginTransaction();
    RelCultureTable::getInstance()->find(1)->delete();
    $con->rollback();

    checkTags(
      $version,
      array (
        'RelCulture:1',
        'RelCulture:3', # child of RelCulture:1
        'RelCulture:4', # child of RelCulture:1
        'RelSiteCulture:1:1',
        'RelSiteCulture:1:3',
      ),
      array(
        'RelCulture', # marked not by cascade mechanism
        'RelSiteCulture', # marked not by cascade mechanism
        'RelSite', #  due to "invalidateCollectionVersionOnUpdate" for RelSite
        'RelSite:1',
        'RelSite:2',
        'RelSite:3',
      )
    );
  }




  if (in_array(5, $run))
  {
    $version = sfCacheTaggingToolkit::generateVersion();
    $con->beginTransaction();
    RelCultureTable::getInstance()->find(5)->delete();
    $con->rollback();

    checkTags(
      $version,
      array (
        'RelCulture:5',
        'RelCulture:6', # child of RelCulture:5
        'RelCulture:7', # child of RelCulture:5
        'RelCulture:8', # child of RelCulture:5
        'RelCulture:9', # child of RelCulture:5
        'RelSiteCulture:5:1',
        'RelSiteCulture:5:2',
        'RelSiteCulture:7:3', # as reference from RelCulture:7
      ),
      array(
        'RelCulture', # marked not by cascade mechanism
        'RelSiteCulture', # marked not by cascade mechanism
        'RelSite', #  due to "invalidateCollectionVersionOnUpdate" for RelSite
        'RelSite:4',
      )
    );
  }

  if (in_array(6, $run))
  {
    $version = sfCacheTaggingToolkit::generateVersion();
    $con->beginTransaction();
    RelCultureTable::getInstance()->find(2)->delete();
    $con->rollback();

    checkTags(
      $version,
      array (
        'RelCulture:2',
        'RelSiteCulture:2:1',
        'RelSiteCulture:2:2',
      ),
      array(
        'RelCulture', # marked not by cascade mechanism
        'RelSiteCulture', # marked not by cascade mechanism
      )
    );
  }


  $t->diag('RelSiteCulture model (M:M)');

  if (in_array(7, $run))
  {
    $version = sfCacheTaggingToolkit::generateVersion();
    $con->beginTransaction();
    RelSiteCultureTable::getInstance()
      ->findOneByRelCultureIdAndRelSiteId(1, 1)
      ->delete();
    $con->rollback();

    checkTags(
      $version,
      array (
        'RelSiteCulture:1:1',
      ),
      array(
        'RelSiteCulture', # marked not by cascade mechanism
      )
    );
  }


  if (in_array(8, $run))
  {
    $version = sfCacheTaggingToolkit::generateVersion();
    $con->beginTransaction();
    RelSiteCultureTable::getInstance()
      ->findByRelSiteId(1)
      ->delete();
    $con->rollback();

    checkTags(
      $version,
      array (
        'RelSiteCulture:1:1',
        'RelSiteCulture:2:1',
        'RelSiteCulture:5:1',
      ),
      array(
        'RelSiteCulture', # marked not by cascade mechanism
      )
    );
  }


  $t->diag('RelSiteSetting model (1:1)');

  if (in_array(9, $run))
  {
    $version = sfCacheTaggingToolkit::generateVersion();
    $con->beginTransaction();
    RelSiteSettingTable::getInstance()->find(3)->delete();
    $con->rollback();

    checkTags(
      $version,
      array (
        'RelSiteSetting:3',
      ),
      array(
        'RelSiteSetting', # marked not by cascade mechanism
      )
    );
  }

  if (in_array(10, $run))
  {
    $version = sfCacheTaggingToolkit::generateVersion();
    $con->beginTransaction();
    RelSiteSettingTable::getInstance()
      ->createQuery()
      ->delete()
      ->andWhere('(is_secure OR is_closed)')
      ->execute();

    $con->rollback();

    checkTags(
      $version,
      array (
        'RelSiteSetting:1',
        'RelSiteSetting:3',
        'RelSiteSetting:4',
      ),
      array(
        'RelSiteSetting', # marked not by cascade mechanism
      )
    );
  }

  if (in_array(11, $run))
  {
    # full delete 11
    $version = sfCacheTaggingToolkit::generateVersion();
    $con->beginTransaction();
    RelSiteTable::getInstance()
      ->createQuery()
      ->delete()
      ->andWhere('id > 0')
      ->execute();

    RelCultureTable::getInstance()
      ->createQuery()
      ->delete()
      ->andWhere('rel_culture_id IS NULL')
      ->execute();

    $con->rollback();

    checkTags(
      $version,
      array (
        'RelSite:1',
        'RelSiteSetting:1',
        'RelSite:2',
        'RelSiteSetting:2',
        'RelSite:3',
        'RelSiteSetting:3',
        'RelSite:4',
        'RelSiteSetting:4',
        'RelCulture:1',
        'RelCulture:2',
        'RelCulture:3',
        'RelCulture:4',
        'RelCulture:5',
        'RelCulture:6',
        'RelCulture:7',
        'RelCulture:8',
        'RelCulture:9',
        'RelSiteCulture:1:1',
        'RelSiteCulture:1:3',
        'RelSiteCulture:2:1',
        'RelSiteCulture:2:2',
        'RelSiteCulture:5:1',
        'RelSiteCulture:5:2',
        'RelSiteCulture:7:3',
      ),
      array(
        'RelSite',        # marked not by cascade mechanis
        'RelSiteSetting', # marked not by cascade mechanism
        'RelSiteCulture', # marked not by cascade mechanism
        'RelCulture',     # marked not by cascade mechanism
      )
    );
  }
