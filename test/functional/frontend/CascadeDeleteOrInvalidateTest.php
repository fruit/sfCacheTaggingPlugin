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

  global $t, $tagging, $alltags;
  $t = new lime_test();

  $sfContext = sfContext::getInstance();
  $cacheManager = $sfContext->getViewCacheManager();
  $tagging = $cacheManager->getTaggingCache();

  sfConfig::set('app_sfCacheTagging_collection_tag_name_format', null);
  sfConfig::set('app_sfCacheTagging_object_tag_name_format', null);
  sfConfig::set('app_sfCacheTagging_object_class_tag_name_provider', null);

  /* @var $tagging sfTaggingCache */

  function reset_rel_tables ()
  {
    static $cleanQuery = null;

    $con = Doctrine_Manager::getInstance()->getCurrentConnection();

    $s  = RelSiteTable::getInstance()->findAll();
    $sc = RelSiteCultureTable::getInstance()->findAll();
    $c  = RelCultureTable::getInstance()->findAll();
    $ss = RelSiteSettingTable::getInstance()->findAll();
    $ca = RelCategoryTable::getInstance()->findAll();
    $s->free(true);$sc->free(true);$c->free(true);$ss->free(true);$ca->free(true);
    $s->clear();$sc->clear(); $c->clear();$ss->clear();$ss->clear();

    $con->beginTransaction();
    if (! $cleanQuery)
    {
      $truncateQuery = array_reduce(
        array('rel_category','rel_culture','rel_site','rel_site_culture','rel_site_setting'),
        function ($return, $val) { return "{$return} TRUNCATE {$val};"; }, ''
      );

      $cleanQuery = "SET FOREIGN_KEY_CHECKS = 0;{$truncateQuery}; SET FOREIGN_KEY_CHECKS = 1;";
    }

    $con->exec($cleanQuery);
    Doctrine::loadData(sfConfig::get('sf_data_dir') .'/fixtures/cascade.yml');
    $con->commit();
  }

  function fetch_and_clean_all_tags ()
  {
    return;
    global $tagging;

    $tagging->clean();

    /**
     * @todo
     *
     * May be culture findAll returns objects that points recursivelly,
     * and getCacheTags can handle it
     */


    return $tags;
  }

  function check_tags ($microtime, array $toDelete, array $toInvalidate)
  {
    global $t, $tagging, $alltags;

    foreach ($alltags as $tagName)
    {
      if (in_array($tagName, $toDelete))
      {
        $t->ok(! $tagging->hasTag($tagName), sprintf('Tag "%s" is removed', $tagName));
      }
      elseif (in_array($tagName, $toInvalidate))
      {
        $t->cmp_ok($tagging->getTag($tagName), '>', $microtime, sprintf('Tag "%s" is invalidated', $tagName));
      }
      else
      {
        $t->ok($tagging->hasTag($tagName), sprintf('Tag "%s" exists', $tagName));
        $t->cmp_ok($tagging->getTag($tagName), '<', $microtime, sprintf('Version of "%s" is not updated', $tagName));
      }
    }

    $tagging->clean();

    reset_rel_tables();
  }

  // Initializing
  $tagging->clean();
  reset_rel_tables();
  $alltags = $tagging->getCacheKeys();

  $run = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11);

  if (in_array(1, $run, true))
  {
    $t->diag('Test #1');

    $version = sfCacheTaggingToolkit::generateVersion();

    RelSiteTable::getInstance()->find(1)->delete();

    check_tags(
      $version,
      array(
        'RelSite:1',
        'RelSiteSetting:1',
        'RelSiteCulture:1:1',
        'RelSiteCulture:2:1',
        'RelSiteCulture:5:1',
      ),
      array(
        'RelSite',
        'RelSiteSetting',
        'RelSiteCulture',
      )
    );
  }
  return;

  if (in_array(2, $run, true))
  {
    $t->diag('Test #2');
    $version = sfCacheTaggingToolkit::generateVersion();

    RelSiteTable::getInstance()->find(4)->delete();

    check_tags(
      $version,
      array(
        'RelSite:4',
        'RelSiteSetting:4',
      ),
      array(
        'RelSite', # marked not by cascade mechanism
        'RelSiteSetting', # marked not by cascade mechanism
      )
    );
  }

  if (in_array(3, $run, true))
  {
    $t->diag('Test #3');
    $version = sfCacheTaggingToolkit::generateVersion();
    RelSiteTable::getInstance()
      ->createQuery()
      ->delete()
      ->andWhereIn('id', array(2, 3))
      ->execute();

    check_tags(
      $version,
      array(
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

  if (in_array(4, $run, true))
  {
    $t->diag('Test #4');
    $version = sfCacheTaggingToolkit::generateVersion();
    RelCultureTable::getInstance()->find(1)->delete();

    check_tags(
      $version,
      array(
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

  if (in_array(5, $run, true))
  {
    $t->diag('Test #5');
    $version = sfCacheTaggingToolkit::generateVersion();

    RelCultureTable::getInstance()->find(5)->delete();


    check_tags(
      $version,
      array(
        'RelCulture:5',
        'RelCulture:6', # child of RelCulture:5
        'RelCulture:7', # child of RelCulture:5
        'RelCulture:8', # child of RelCulture:5
        'RelCulture:9', # child of RelCulture:5
        'RelSiteCulture:5:1', # as reference from RelCulture:5
        'RelSiteCulture:5:2', # as reference from RelCulture:5
        'RelSiteCulture:7:3', # as reference from RelCulture:7
      ),
      array(
        'RelCulture', # marked not by cascade mechanism
        'RelSiteCulture', # marked not by cascade mechanism
        //'RelSite', #  due to "invalidateCollectionVersionOnUpdate" for RelSite
        //'RelSite:4',
      )
    );
  }

  if (in_array(6, $run, true))
  {
    $t->diag('Test #6');
    $version = sfCacheTaggingToolkit::generateVersion();

    RelCultureTable::getInstance()->find(2)->delete();


    check_tags(
      $version,
      array(
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

  // RelSiteCulture model (M:M)

  if (in_array(7, $run, true))
  {
    $t->diag('Test #7');
    $version = sfCacheTaggingToolkit::generateVersion();

    RelSiteCultureTable::getInstance()
      ->findOneByRelCultureIdAndRelSiteId(1, 1)
      ->delete();


    check_tags(
      $version,
      array(
        'RelSiteCulture:1:1',
      ),
      array(
        'RelSiteCulture', # marked not by cascade mechanism
      )
    );
  }

  if (in_array(8, $run, true))
  {
    $t->diag('Test #8');
    $version = sfCacheTaggingToolkit::generateVersion();

    RelSiteCultureTable::getInstance()
      ->findByRelSiteId(1)
      ->delete();


    check_tags(
      $version,
      array(
        'RelSiteCulture:1:1',
        'RelSiteCulture:2:1',
        'RelSiteCulture:5:1',
      ),
      array(
        'RelSiteCulture', # marked not by cascade mechanism
      )
    );
  }

  // RelSiteSetting model (1:1)

  if (in_array(9, $run, true))
  {
    $t->diag('Test #9');
    $version = sfCacheTaggingToolkit::generateVersion();

    RelSiteSettingTable::getInstance()->find(3)->delete();


    check_tags(
      $version,
      array(
        'RelSiteSetting:3',
      ),
      array(
        'RelSiteSetting', # marked not by cascade mechanism
      )
    );
  }

  if (in_array(10, $run, true))
  {
    $t->diag('Test #10');
    $version = sfCacheTaggingToolkit::generateVersion();

    RelSiteSettingTable::getInstance()
      ->createQuery()
      ->delete()
      ->andWhere('(is_secure OR is_closed)')
      ->execute();



    check_tags(
      $version,
      array(
        'RelSiteSetting:1',
        'RelSiteSetting:3',
        'RelSiteSetting:4',
      ),
      array(
        'RelSiteSetting', # marked not by cascade mechanism
      )
    );
  }

  if (in_array(11, $run, true))
  {
    $t->diag('Test #11');

    # full delete 11
    $version = sfCacheTaggingToolkit::generateVersion();

    # id: 1,2,3,4                   [delete]
    # rel_category_id: 1,2          [invalidate]
    # rel_culture_id: 3,4           [invalidate]
    # M:M rel_culture_id: 1,2,5,7   [delete]
    RelSiteTable::getInstance()
      ->createQuery()
      ->delete()
      ->andWhere('id > 0')
      ->execute();

    # id: 1,2,5   (+ 3,4,6,7,8,9 as child cultures)
    # M:M rel_site_id: 1,2,3        [delete]
    RelCultureTable::getInstance()
      ->createQuery()
      ->delete()
      ->andWhere('rel_culture_id IS NULL')
      ->execute();

    check_tags(
      $version,
      array(
        'RelSite:1',
        'RelSite:2',
        'RelSite:3',
        'RelSite:4',
        'RelSiteSetting:1',
        'RelSiteSetting:2',
        'RelSiteSetting:3',
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
        'RelSite',        # marked not by cascade mechanism
        'RelSiteSetting', # marked not by cascade mechanism
        'RelSiteCulture', # marked not by cascade mechanism
        'RelCulture',     # marked not by cascade mechanism
      )
    );
  }
