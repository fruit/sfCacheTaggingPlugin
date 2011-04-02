# sfCacheTaggingPlugin #

The ``sfCacheTaggingPlugin`` is a ``Symfony`` plugin, that helps to store cache with
associated tags and to keep cache content up-to-date based by incrementing tag
version when cache objects are edited/removed or new objects are ready to be a
part of cache content.

## Description ##

Tagging a cache is a concept that was invented in the same time by many developers
([Andrey Smirnoff](http://www.smira.ru), [Dmitryj Koteroff](http://dklab.ru/)
and, perhaps, by somebody else)

This software was developed inspired by Andrey Smirnoff's theoretical work
["Cache tagging with Memcached (on Russian)"](http://www.smira.ru/tag/memcached/).
Some ideas are implemented in the real world (e.g. tag versions based on datetime
and micro time, cache hit/set logging, cache locking) and part of them
are not (atomic counter).

## Installation ##

 * As Symfony plugin

  * Installation

            $ ./symfony plugin:install sfCacheTaggingPlugin

  * Upgrading

            $ ./symfony cc
            $ ./symfony plugin:upgrade sfCacheTaggingPlugin

 * As a git submodule (master or devel branch)

  * Installation

            $ git submodule add git://github.com/fruit/sfCacheTaggingPlugin.git plugins/sfCacheTaggingPlugin
            $ git submodule init plugins/sfCacheTaggingPlugin

  * Upgrading

            $ cd plugins/sfCacheTaggingPlugin
            $ git pull origin master
            $ cd ../..

 * Migrating

            $ ./symfony doctrine:migrate-generate-diff

## New in v3.2.0:

  * New: Cascading tag deletion through the model relations [GH-6](https://github.com/fruit/sfCacheTaggingPlugin/issues#issue/6)
  * New: Option ``invalidateCollectionVersionByChangingColumns`` to setup ``Cachetaggable`` behavior (see below) [GH-8](https://github.com/fruit/sfCacheTaggingPlugin/issues#issue/8)
  * New: New methods in the sfComponent to add collection tags [GH-10](https://github.com/fruit/sfCacheTaggingPlugin/issues#issue/10)
  * New: ``Doctrine_Record::link`` and ``Doctrine_Record::unlink`` updates refTable's tags
  * Fixed: ``skipOnChange`` did not work properly

## <font style="text-decoration: underline;">Quick</font> setup ##

 * Check ``sfCacheTaggingPlugin`` plugin is enabled (``/config/ProjectConfiguration.class.php``).

        [php]
        class ProjectConfiguration extends sfProjectConfiguration
        {
          public function setup ()
          {
            # … other plugins
            $this->enablePlugins('sfCacheTaggingPlugin');
          }
        }

  * Change default model class ``sfDoctineRecord`` to ``sfCachetaggableDoctrineRecord``

        [php]
        <?php

        class ProjectConfiguration extends sfProjectConfiguration
        {
          # …

          public function configureDoctrine (Doctrine_Manager $manager)
          {
            sfConfig::set(
              'doctrine_model_builder_options',
              array('baseClassName' => 'sfCachetaggableDoctrineRecord')
            );
          }
        }

    And after, rebuild your models:

        ./symfony doctrine:build-model

 * Setup ``/config/factories.yml``

        all:
          view_cache:
            class: sfTaggingCache
            param:
              data:
                class: sfFileTaggingCache
                param:
                  automatic_cleaning_factor: 0
                  cache_dir: %SF_CACHE_DIR%/sf_tag_cache/data
              tags:
                class: sfFileTaggingCache
                param:
                  automatic_cleaning_factor: 0
                  cache_dir: %SF_CACHE_DIR%/sf_tag_cache/tags
              logger:
                class: sfFileCacheTagLogger
                param:
                  file: %SF_LOG_DIR%/cache_%SF_ENVIRONMENT%.log
                  format: "%char% %microtime% %key%%EOL%"

          view_cache_manager:
            class: sfViewCacheTagManager



 * Add "Cachetaggable" behavior to the each model you want to cache

        Article:
          tableName: articles
          actAs:
            Cachetaggable: ~

 * Enable cache in all applications ``settings.yml`` and declare required helpers:

        dev:
          .settings:
            cache: true

        all:
          .settings:
            standard_helpers:
              - Partial
              - Cache

## Usage ##

### How to cache partials?

  * Enable cache in ``cache.yml``:

        _listing:
          enabled: true

  * Action template ``indexSuccess.php``:

        [php]
        <?php /* @var $articles Doctrine_Collection_Cachetaggable */ ?>

        <h1><?php __('Articles') ?></h1>
        <?php include_partial('articles/listing', array(
          'articles' => $articles,
          'sf_cache_tags' => $articles->getCacheTags(),
        )) ?>

### How to cache components? (one-table)

  * Enable component caching in ``cache.yml``:

        _listOfArticles:
          enabled: true

  * ``components.class.php``

        [php]
        class articlesComponents extends sfComponents
        {
          public function executeListOfArticles ($request)
          {
            /* @var $articles Doctrine_Collection_Cachetaggable */
            $articles = Doctrine::getTable('Article')
              ->createQuery('a')
              ->select('a.*')
              ->orderBy('a.id DESC')
              ->limit(3)
              ->execute();

            $this->setPartialTags($articles);

            $this->articles = $articles;
          }
        }

  * Action template: ``indexSuccess.php``

        [php]
        <fieldset>
          <legend>Articles inside component</legend>
          <?php include_component('articles', 'listOfArticles'); ?>
        </fieldset>

### How to cache components? (many-table, combining articles and comments 1:M relation)

  * Enable component caching in ``cache.yml``

        _listOfArticlesAndComments:
          enabled: true

  * ``components.class.php``

        [php]
        class articlesComponents extends sfComponents
        {
          public function executeListOfArticlesAndComments($request)
          {
            $articles = Doctrine::getTable('Article')
              ->createQuery('a')
              ->addSelect('a.*, ac.*')
              ->innerJoin('a.ArticleComments ac')
              ->orderBy('a.id DESC')
              ->limit(3)
              ->execute();

            $this->setPartialTags($articles);

            $this->articles = $articles;
          }
        }

  * ``indexSuccess.php``

        [php]
        <fieldset>
          <legend>Component (articles and comments)</legend>
          <?php include_component('article', 'listOfArticlesAndComments'); ?>
        </fieldset>


### How to cache action with layout?

  * Enable caching in ``cache.yml``:

        showSuccess:
          with_layout: true
          enabled:     true

  * Controller example:

        [php]
        class carActions extends sfActions
        {
          public function executeShow (sfWebRequest $request)
          {
            $car = Doctrine::getTable('car')
              ->find($request->getParameter('id'));

            $driver = Doctrine::getTable('driver')
              ->find($request->getParameter('driverId'));

            $this->setPageTags($car);
            $this->addPageTags($driver);

            $this->car = $car;
            $this->driver = $driver;
          }
        }

### How to cache action _without_ layout?

  * Enable cache in ``cache.yml``:

        show:
          with_layout: false
          enabled:     true

  * Action example

        [php]
        class carActions extends sfActions
        {
          public function executeShow (sfWebRequest $request)
          {
            $car = Doctrine::getTable('car')->find($request->getParameter('id'));

            $this->setActionTags($car);

            $this->car = $car;
          }
        }

### How to cache Doctrine_Records/Doctrine_Collections?

  * Does not depends on ``cache.yml`` file

  * To cache objects/collection with its tags you have just to enable
    result cache by calling ``Doctrine_Query::useResultCache()``:

        [php]
        class articleActions extends sfActions
        {
          public function executeArticles (sfWebRequest $request)
          {
            $articles = Doctrine::getTable('Article')
              ->createQuery()
              ->useResultCache()
              ->addWhere('lang = ?', 'en_GB')
              ->addWhere('is_visible = ?', true)
              ->limit(15)
              ->execute();

            $this->articles = $articles;
          }
        }


  * Appending tags to existing Doctrine tags:

        [php]
        class articleActions extends sfActions
        {
          public function executeArticles (sfWebRequest $request)
          {
            $articles = Doctrine::getTable('Article')
              ->createQuery()
              ->useResultCache()
              ->addWhere('lang = ?')
              ->addWhere('is_visible = ?')
              ->limit(15)
              ->execute(array('en_GB', true));

            $q = Doctrine::getTable('Culture')->createQuery();
            $cultures = $q->execute();

            $this->addDoctrineTags($cultures, $q);

            $this->articles = $articles;
          }
        }

## Limitations / Specificity ##

  * In case, when model has translations (I18n behavior), it is enough to add
    ``Cachetaggable`` behavior to the root model. I18n behavior should be free from ``Cachetaggable`` behavior.
  * You can`t pass ``I18n`` table columns to the ``skipOnChange``.
  * Doctrine ``$q->count()`` can't be cached with tags
  * Be careful with joined I18n tables, cached result may differs from the expected.
    Due the [unresolved ticket](http://trac.symfony-project.org/ticket/7220) it *could be* impossible.

## TDD ##

  * Environment: PHP 5.3
  * Unit tests: 12
  * Functional tests: 29
  * Checks: 2456
  * Code coverage: 97%

## Contribution ##

* [Repository (GitHub)](http://github.com/fruit/sfCacheTaggingPlugin "Repository (GitHub)")
* [Issues (GitHub)](http://github.com/fruit/sfCacheTaggingPlugin/issues "Issues")

## Contacts ##

  * @: Ilya Sabelnikov `` <fruit dot dev at gmail dot com> ``
  * skype: ilya_roll