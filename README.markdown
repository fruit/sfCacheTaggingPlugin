# sfCacheTaggingPlugin

The sfCacheTaggingPlugin is a Symfony plugin that allows you to not think about
cache obsolescence. The user will see only a fresh data thanks to cache tagging.
The cache will be linked with a tags versions and will be incremented when the
cached Doctrine objects were edited/removed or new Doctrine objects are
ready to be a part of cache content.

# Table of contents

 * <a href="#desc">Description</a>
 * <a href="#installation">Installation</a>
 * <a href="#quick-setup">Quick setup</a>
 * <a href="#usage">Usage</a>
 * <a href="#advanced-setup">Advanced setup</a>
 * <a href="#miscellaneous">Miscellaneous</a>

# <a id="desc">Description</a>

Tagging a cache is a concept that was invented in the same time by many developers
([Andrey Smirnoff](http://www.smira.ru), [Dmitryj Koteroff](http://dklab.ru/lib/Dklab_Cache/)
and, perhaps, by somebody else)

This software was developed inspired by Andrey Smirnoff's theoretical work
[Cache tagging with Memcached (on Russian)](http://www.smira.ru/tag/memcached/).
Some ideas are implemented in the real world (e.i. tag versions based on datetime
and micro time, cache hit/set logging, cache locking) and part of them
are not (atomic counter).

# <a id="installation">Installation</a>

 * As Symfony plugin

  * Installation

            $ ./symfony plugin:install sfCacheTaggingPlugin

  * Upgrading

            $ ./symfony cc
            $ ./symfony plugin:upgrade sfCacheTaggingPlugin

 * As a git submodule (master branch)

  * Installation

            $ git submodule add git://github.com/fruit/sfCacheTaggingPlugin.git plugins/sfCacheTaggingPlugin
            $ git submodule init plugins/sfCacheTaggingPlugin

  * Upgrading

            $ cd plugins/sfCacheTaggingPlugin
            $ git pull origin master
            $ cd ../..

 * Migrating

        $ ./symfony doctrine:migrate-generate-diff

# <a id="quick-setup">Quick setup</a>

  _After quick setup you may be interested in "<a href="#advanced-setup">Advanced setup</a>"_

## 1. Check plugin is enabled.

Location: ``/config/ProjectConfiguration.class.php``

    [php]
    <?php

    class ProjectConfiguration extends sfProjectConfiguration
    {
      public function setup ()
      {
        # … other plugins
        $this->enablePlugins('sfCacheTaggingPlugin');
      }
    }

## 2. Change default model class

Switch the default model class ``sfDoctineRecord`` with ``sfCachetaggableDoctrineRecord``

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

After, rebuild the models:

    $ ./symfony doctrine:build-model

## 3. Configure "_view_cache_" and "_view_cache_manager_" in ``/config/factories.yml``

    all:
      view_cache_manager:
        class: sfViewCacheTagManager

      view_cache:
        class: sfTaggingCache
        param:
          storage:
            class: sfFileTaggingCache
            param:
              automatic_cleaning_factor: 0
              cache_dir: %SF_CACHE_DIR%/sf_tag_cache
          logger:
            class: sfFileCacheTagLogger
            param:
              file: %SF_LOG_DIR%/cache_%SF_ENVIRONMENT%.log
              format: "%char% %microtime% %key%%EOL%"



## 4. Add "_Cachetaggable_" behavior to the each model you want to cache

    Article:
      tableName: articles
      actAs:
        Cachetaggable: ~

And don't forget to rebuild models again:

    $ ./symfony doctrine:build-model

## 5. Enable the cache and add mandatory helpers to ``standard_helpers`` (file ``/apps/%APP%/config/settings.yml``):

    dev:
      .settings:
        cache: true

    all:
      .settings:
        standard_helpers:
          - Partial
          - Cache

# <a id="usage">Usage</a>

## How to cache partials?

  * Enable cache in ``/apps/%APP%/modules/%MODULE%/config/cache.yml``:

        _listing:
          enabled: true

  * Action template ``indexSuccess.php``:

        [php]
        <?php /* @var $articles Doctrine_Collection_Cachetaggable */ ?>

        <h1><?php __('Articles') ?></h1>
        <?php include_partial('articles/listing', array(
          'articles' => $articles,
          'sf_cache_tags' => $articles,
        )) ?>

## How to cache components? (one-table)

  * ``components.class.php``

        [php]
        <?php

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

            $this->setContentTags($articles);

            $this->articles = $articles;
          }
        }

  * Action template: ``indexSuccess.php``

        [php]
        <fieldset>
          <legend>Articles inside component</legend>
          <?php include_component('articles', 'listOfArticles'); ?>
        </fieldset>

  * Enable component caching in ``/apps/%APP%/modules/%MODULE%/config/cache.yml``:

        _listOfArticles:
          enabled: true

## How to cache components? (many-table, combining articles and comments 1:M relation)

  * ``components.class.php``

        [php]
        <?php

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

            $this->setContentTags($articles);

            $this->articles = $articles;
          }
        }

  * ``indexSuccess.php``

        [php]
        <fieldset>
          <legend>Component (articles and comments)</legend>
          <?php include_component('article', 'listOfArticlesAndComments'); ?>
        </fieldset>

  * Enable component caching in ``/apps/%APP%/modules/%MODULE%/config/cache.yml``

        _listOfArticlesAndComments:
          enabled: true


## How to cache action with layout?

  * Controller example:

        [php]
        <?php

        class carActions extends sfActions
        {
          public function executeShow (sfWebRequest $request)
          {
            $car = Doctrine::getTable('car')
              ->find($request->getParameter('id'));

            $driver = Doctrine::getTable('driver')
              ->find($request->getParameter('driverId'));

            $this->setContentTags($car);
            $this->addContentTags($driver);

            $this->car = $car;
            $this->driver = $driver;
          }
        }

  * Enable caching in ``/apps/%APP%/modules/%MODULE%/config/cache.yml``:

        showSuccess:
          with_layout: true
          enabled:     true

## How to cache action _without_ layout?

  * Action example

        [php]
        <?php

        class carActions extends sfActions
        {
          public function executeShow (sfWebRequest $request)
          {
            $car = Doctrine::getTable('car')->find($request->getParameter('id'));

            $this->setContentTags($car);

            $this->car = $car;
          }
        }

  * Enable cache in ``/apps/%APP%/modules/%MODULE%/config/cache.yml``:

        show:
          with_layout: false
          enabled:     true

## How to cache Doctrine query results?

  * Does not depends on ``cache.yml`` file

  * To cache objects/collection with tags you need to enable
    result cache by calling ``Doctrine_Query::useResultCache()``:

        [php]
        <?php

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

# <a id="advanced-setup">Advanced setup</a>

_NB. Please read "<a href="#quick-setup">Quick setup</a>" before reading this._

## How to cache private blocks (actions/pages/partials) for authenticated users

  Since version v4.3.0 the classes ``AuthParamFilter`` and ``sfCacheTaggingWebRequest`` are deprecated.
  This is done because such approach can't handle components and partials (just actions and layouts).
  So, if you have using ``AuthParamFilter`` (file ``filters.yml``), please disable it.

    [yaml]
    rendering: ~
    security:  ~

    auth_params:
      class: AuthParamFilter
      enabled: false

    cache:     ~
    execution: ~

  The new implementation is simple and w/o hacks. It works with actions, components and
  partials. Here is working example of how to add "user_id" and "user_type" to cache key parameters:

    [php]
    <?php

    class myUser extends sfBasicSecurityUser
    {
      public function initialize (sfEventDispatcher $dispatcher, sfStorage $storage, $options = array())
      {
        parent::initialize($dispatcher, $storage, $options);

        $dispatcher->connect('cache.filter_cache_keys', array($this, 'listenOnCacheFilterCacheKeys'));
      }

      /**
       * The method is called on condition the user is authenticated.
       * Also, it's called for each partial/component/action you access on the page.
       *
       * Adds 2 custom cache key parameters to any type of cache
       *
       * @param $event    sfEvent
       * @param $params   array
       * @return array
       */
      public function listenOnCacheFilterCacheKeys (sfEvent $event, array $params)
      {
        /* @var $user myUser */
        $user = $event->getSubject();

        /* @var $viewCache sfViewCacheTagManager */
        $viewCache = $event['view_cache'];

        /* @var $cacheType int */
        // Type of the cache sfViewCacheTagManager::NAMESPACE_*
        $cacheType = $event['cache_type'];

        return array_merge($params, array(
          'user_id'   => $user->getAttribute('user_id'),
          'user_type' => 'BASIC',
        ));
      }
    }

## Explaining ``/config/factories.yml``

    all:
      view_cache_manager:
        class: sfViewCacheTagManager

      view_cache:
        class: sfTaggingCache
        param:

          # Content will be stored in Memcache
          # Here you can switch to any other backend
          # (see below "Restrictions" for more info)
          storage:
            class: sfMemcacheTaggingCache
            param:
              storeCacheInfo: true
              host: localhost
              port: 11211

          logger:
            class: sfFileCacheTagLogger   # to disable logger, set class to "sfNoCacheTagLogger"
            param:
              # All given parameters are default
              file:         %SF_LOG_DIR%/cache_%SF_ENVIRONMENT%.log
              file_mode:    0640
              dir_mode:     0750
              time_format:  "%Y-%b-%d %T%z"   # e.i. 2010-Sep-01 15:20:58+0300
              skip_chars:   ""

              # Logging format
              # There are such available place-holders:
              #   %char%              - Operation char (see char explanation in sfCacheTagLogger::explainChar())
              #   %char_explanation%  - Operation explanation string
              #   %time%              - Time, when data/tag has been accessed
              #   %key%               - Cache name or tag name with its version
              #   %microtime%         - Micro time timestamp when data/tag has been accessed
              #   %EOL%               - Whether to append \n in the end of line
              #
              # (Example: "%char% %microtime% %key%%EOL%")
              format:       "%char%"

> **Restrictions**: Backend's class should be inherited from the ``sfCache``
  class. Then, it should be implement ``sfTaggingCacheInterface``
  (due to a ``Doctrine`` cache engine compatibility).
  Also, it should support the caching of objects and/or arrays.

Therefor, plugin comes with additional extended backend classes:

  - ``sfAPCTaggingCache``
  - ``sfEAcceleratorTaggingCache``
  - ``sfFileTaggingCache``
  - ``sfMemcacheTaggingCache``
  - ``sfSQLiteTaggingCache``
  - ``sfXCacheTaggingCache``

And bonus one:

  - ``sfSQLitePDOTaggingCache`` (based on stand alone ``sfSQLitePDOCache``)


## Adding "Cachetaggable" behavior to the models

Two major setups to pay attention:

  * **Model setup**
    * When object tag will be invalidated
    * How object tag will stored (tag naming)
  * **Relation setup**
    * What will happen with related objects in case root-object is deleted or updated
    * Choosing cascading type (deleteTags, invalidateTags)

Explained behavior setup, file ``/config/doctrine/schema.yml``:

    Article:
      tableName: articles
      actAs:
        Cachetaggable:

          # If you have more then 1 unique column, you could pass all of them
          # as array (tag name will be based on all of them)
          # (default: [], primary keys will be auto-detected)
          uniqueColumn:    [id, is_visible]


          # cache tag will be based on 2 columns
          # (e.g. "Article:5:01", "Article:912:00")
          # matches the "uniqueColumn" column order
          # (default: "", key format is auto-generated)
          uniqueKeyFormat: '%d-%02b'


          # Column name, where the object version will be stored in a table
          # (default: "object_version")
          versionColumn:    version_microtime


          # Skips the object invalidation if the altered column is in this list
          # Useful for columns like sf_guard_user.last_login, updated_at
          # (default: [])
          skipOnChange:
            - last_accessed


          # Invalidates or not the object-collection tag when any
          # record was just updated (BC with v2.*) associated with this collection-tag.
          # If the new record is added to collection, or removed - the collection-tag
          # will be updated in any case.
          # Useful, when table contains rarely changed data (e.g. Countries, Currencies)
          # permitted values: true/false
          # (default: false)
          invalidateCollectionVersionOnUpdate: false


          # Useful option when model contains columns like "is_visible", "is_active"
          # updates collection tag, if one of columns was updated.
          # Would not work if "invalidateCollectionVersionOnUpdate" is set to "true"
          # Would not work if modified column is in the "skipOnChange" list.
          # (default: [])
          invalidateCollectionVersionByChangingColumns:
            - is_visible

      columns:
        id:
          type: integer(4)
          autoincrement: true
          primary: true
        culture_id:
          type: integer(4)
          notnull: false
          default: null
        category_id:
          type: integer(4)
          notnull: true
        slug: string(255)
        is_visible: boolean(true)
        is_moderated: boolean(false)
        last_accessed: date(25)
      relations:
        Culture:
          class: Culture
          local: culture_id
          foreign: id
          foreignAlias: Articles
          type: one
          foreignType: many
          # Cascading type chosen "invalidateTags"
          # Due to foreign key "onDelete" type is "SET NULL"
          cascade: [invalidateTags]
        Category:
          class: Category
          local: category_id
          foreign: id
          foreignAlias: Categories
          type: one
          foreignType: many
          # Cascading type chosen "deleteTags"
          # Due to foreign key "onDelete" type is "CASCADE"
          cascade: [deleteTags]

    Culture:
      tableName: cultures
      actAs:
        Cachetaggable: ~
      columns:
        id:
          type: integer(4)
          autoincrement: true
          primary: true
        lang: string(10)
        is_visible: boolean(true)
      relations:
        Articles:
          onDelete: SET NULL
          onUpdate: CASCADE

    Category:
      tableName: categories
      actAs:
        Cachetaggable: ~
      columns:
        id:
          type: integer(4)
          autoincrement: true
          primary: true
        name: string(127)
      relations:
        Articles:
          onDelete: CASCADE
          onUpdate: CASCADE

## Explained ``sfCacheTaggingPlugin`` options (file ``/config/app.yml``):

    all:
      sfCacheTagging:

        # Tag name delimiter
        # (default: ":")
        model_tag_name_separator: ":"

        # Version of precision
        # 0: without micro time, version length 10 digits
        # 5: with micro time part, version length 15 digits
        # allowed decimal numbers in range [0, 6]
        # (default: 5)
        microtime_precision: 5

        # Callable array, or string
        # Examples:
        #      [ClassName, MethodName]
        #    OR
        #      "ClassName::staticMethodName"
        # useful when tag name should contains extra information
        # (e.g. Environment name, or application name)
        # (default: [])
        object_class_tag_name_provider: []


## Tag manipulations

Here is a list of available methods you can call inside ``sfComponent`` & ``sfAction`` to manage tags:

 - ``setContentTags (mixed $tags)``
 - ``addContentTags (mixed $tags)``
 - ``getContentTags ()``
 - ``removeContentTags ()``
 - ``setContentTag (string $tagName, string $tagVersion)``
 - ``hasContentTag (string $tagName)``
 - ``removeContentTag (string $tagName)``
 - ``disableCache (string $moduleName = null, string $actionName = null)``
 - ``addDoctrineTags (mixed $tags, Doctrine_Query $q, array $params = array())``

More about is you could find in ``sfViewCacheTagManagerBridge.class.php``

Component example:

    [php]
    <?php

    class articlesComponents extends sfComponents
    {
      public function executeList ($request)
      {
        $articles = ArticleTable::getInstance()->findAll();
        $this->setContentTags($articles);

        # Appending tags to already set $articles tags
        $banners = BannerTable::getInstance()->findByCategoryId(4);
        $this->addContentTags($articles);

        # adding only Culture collection tag "Culture"
        # useful when page contains all cultures output in form widget
        $this->addContentTags(CultureTable::getInstance());


        # adding personal tag
        $this->addContentTag('Portal_EN', sfCacheTaggingToolkit::generateVersion());

        # remove "Article:31" from content tags
        $this->removeContentTag('Article:31');

        # print all set tags, excepting the removed one
        // var_dump($this->getContentTags());

        $this->articles = $articles;
        $this->banners = $banners;
      }
    }

## Configurating Doctrine`s query cache

Remember to enable Doctrine query cache in production:

    [yml]
    # config/app.yml
    dev:
      doctrine:
        query_cache: ~

    prod:
      doctrine:
        query_cache:
          class: Doctrine_Cache_Apc # or another backend class Doctrine_Cache_*
          param:
            prefix: doctrine_dql_query_cache
            lifetime: 86400

And plug in query cache:

    [php]
    <?php

    class ProjectConfiguration extends sfProjectConfiguration
    {
      public function configureDoctrine (Doctrine_Manager $manager)
      {
        $doctrineQueryCache = sfConfig::get('app_doctrine_query_cache');

        if ($doctrineQueryCache)
        {
          list($class, $param) = array_values($doctrineQueryCache);
          $manager->setAttribute(
            Doctrine_Core::ATTR_QUERY_CACHE,
            new $class($param)
          );

          if (isset($param['lifetime']))
          {
            $manager->setAttribute(
              Doctrine_Core::ATTR_QUERY_CACHE_LIFESPAN,
              (int) $param['lifetime']
            );
          }
        }
      }
    }

## Clarifying  Doctrine`s result cache

Plugin contains universal proxy class ``Doctrine_Cache_Proxy`` to connect Doctrine
cache mechanisms with Symfony's one. This mean, when you setup "storage" cache back-end to
file cache, [Doctrine`s result cache](http://www.doctrine-project.org/projects/orm/1.2/docs/manual/caching/en#query-cache-result-cache:result-cache) will use it to store cached ``DQL`` results.

To enable result cache use:

    $q->useResultCache();

Set hydration to ``Doctrine_Core::HYDRATE_RECORD`` (NB! using another hydrator, its impossible to cache ``DQL`` result with tags.)

    [php]
    <?php

    $q->setHydrationMode(Doctrine_Core::HYDRATE_RECORD)->execute();
    // or
    $q->execute(array(), Doctrine_Core::HYDRATE_RECORD);

Cached ``DQL`` results will be associated with all linked tags based on query results.

# <a id="miscellaneous">Miscellaneous</a>

## Limitations / Specificity

  * In case, when model has translations (I18n behavior), it is enough to add
    ``Cachetaggable`` behavior to the root model. I18n behavior should be free from ``Cachetaggable`` behavior.
  * You can't pass ``I18n`` table columns to the ``skipOnChange``.
  * Doctrine ``$q->count()`` can't be cached with tags
  * Be careful with joined I18n tables, cached result may differs from the expected.
    Due the [unresolved ticket](http://trac.symfony-project.org/ticket/7220) it *could be* impossible.

## TDD

  * Test environment: PHP 5.4.9, MySQL 5.5.28, Memcached 1.4.10, OS Fedora 17 x64
  * Number of files: 48
  * Tests: 1840
  * Code coverage: 96%

Whether you want to run a plugin tests, you need:

  1. Install plugin from GIT repository.
  2. Install [APC](http://pecl.php.net/package/APC), [Memcache](http://pecl.php.net/package/Memcache) and MySQL
  3. Configure ``php.ini`` and restart Apache/php-fpm:

        [ini]
        [APC]
          apc.enabled = 1
          apc.enable_cli = 1

  4. Add CLI variable:

    For current session only:

        $ export SYMFONY=/path/to/symfony/lib

    For all further sessions:

        $ echo "export SYMFONY=/path/to/symfony/lib" >> ~/.bashrc; source ~/.bashrc

  5. Run tests:

        [php]
        $ cd plugins/sfCacheTaggingPlugin/test/fixtures/project/

        # it will create the ``sfcachetaggingplugin_test`` database
        $ ./symfony doctrine:build --all --and-load --env=test

        $ ./symfony cc

        # runs unit and functional tests
        $ ./symfony test:all

        # runs all unit tests
        $ ./symfony test:unit

        # runs all functional tests
        $ ./symfony test:functional


## Contribution

* [Repository (GitHub)](http://github.com/fruit/sfCacheTaggingPlugin "Repository (GitHub)")
* [Issues (GitHub)](http://github.com/fruit/sfCacheTaggingPlugin/issues "Issues")

## Contacts ##

  * @: Ilya Sabelnikov `` <fruit dot dev at gmail dot com> ``
  * Skype: ilya_roll