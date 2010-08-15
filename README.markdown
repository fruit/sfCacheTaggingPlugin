# sfCacheTaggingPlugin #

The ``sfCacheTaggingPlugin`` is a Symfony plugin, that helps to store cache with
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
and microtime, cache hit/set logging, cache locking) and part of them
are not (atomic counter).

## Illustration ##?

## Contribution ##

* [Repository (GitHub)](http://github.com/fruit/sfCacheTaggingPlugin "Repository (GitHub)")
* [Issues (GitHub)](http://github.com/fruit/sfCacheTaggingPlugin/issues "Issues")

## Installation/Upgrading ##

* Installation

        $ ./symfony plugin:install sfCacheTaggingPlugin

* Upgrading

        $ ./symfony plugin:upgrade sfCacheTaggingPlugin

## Setup ##

1.  Check ``sfCacheTaggingPlugin`` plugin is enabled (``/config/ProjectConfiguration.class.php``)

        [php]
        class ProjectConfiguration extends sfProjectConfiguration
        {
          public function setup ()
          {
            # … other plugins
            $this->enablePlugins('sfCacheTaggingPlugin');
          }
        }

1.  Create a new file ``/config/factories.yml`` (common for all applications)
    or edit application-level ``/apps/%application_name%/config/factories.yml`` file.

    Basicaly, if you have back-end for managing data and front-end to print them out
    you should enable cache in both of them. That is because you edit/create/delete
    records in back-end, so object tags should be updated to invalidate front-end cache.

    I recommend you to create default ``factories.yml`` for all applications you have by
    creating a file ``/config/factories.yml`` (you could find explained
    and short working examples bellow).

    Symfony will check for this file and will load it as a default ``factories.yml``
    configuration for all applications you have in the project.

    This is ``/config/factories.yml`` content (you can copy&paste this code
    into your brand new created file) or merge this config with each application
    ``factories.yml`` file.

  **Explained and commented working example of file ``/config/factories.yml``**

        all:
          view_cache:
            class: sfTaggingCache
            param:
              cache:
                class: sfMemcacheTaggingCache   # Content will be stored in Memcache
                                                # Here you can switch to any other backend
                                                # (see Restrictions block for more info)
                param:
                  persistent: true
                  storeCacheInfo: true
                  host: localhost
                  port: 11211
                  timeout: 5
                  lifetime: 86400           # default value is 1 day (in seconds)

              tags: ~                       # storage for tags (could be the same as
                                            # the cache storage)
                                            # if "tags" is NULL (~), it will
                                            # be the same as cache (e.i. sfMemcacheTaggingCache)

              metadata:
                class: CacheMetadata        # this class responses to save/fetch data and tags
                                            # from/to cache with custom serialization/de-serialization
              logger:
                class: sfFileCacheTagLogger # to disable logger, set class to "sfNoCacheTagLogger"
                param:

                  file:         %SF_LOG_DIR%/cache_%SF_ENVIRONMENT%.log
                  
                  file_mode:    0640              # -rw-r----- (default: 0640)
                  dir_mode:     0750              # drwxr-x--- (default: 0750)
                  time_format:  "%Y-%b-%d %T%z"   # e.g. 2010-Sep-01 15:20:58+0300 (default: "%Y-%b-%d %T%z")
                  
                  format:       %char%        # %char% - Char explanation:
                                              # Data:
                                              #   "g": data cache not found or expired
                                              #   "G": data cache was found
                                              #   "h": cache dot not have data accessed by key
                                              #   "H": cache have data accessed by key
                                              #   "l": could not lock the data cache
                                              #   "L": data cache was locked for writing
                                              #   "s": could not write new values to the cache
                                              #        (e.g. for lock reasons)
                                              #   "S": new values are saved to the data cache
                                              #   "u": could not unlock the cache
                                              #        (e.g. it is already unlocked)
                                              #   "U": cache was unlocked
                                              # Tags:
                                              #   "v": cache tag version is expired
                                              #   "V": cache tag version is up-to-date
                                              #   "p": could not write new version of tag
                                              #   "P": tag was updated with new a version
                                              #   "e": could not remove tag version
                                              #   "E": tag was removed
                                              #   "t": tag does not exists
                                              #   "T": tag was found
                                              #   "i": cache does not have tag accessed by key
                                              #   "I": cache have tag accessed by key
                                              #
                                              # Chars in lower case indicate negative operation.
                                              # Chars in upper case indicate positive operation.

                                              # %char%              - Operation char (see above)
                                              # %char_explanation%  - Operation explanation string 
                                              # %time%              - Time, when data/tag was accessed
                                              # %key%               - Cache name or tag name with its version
                                              # %microtime%         - Microtime timestamp when data/tag was accessed
                                              # %EOL%               - Whether to append \n in the end of line
                                              # (e.g. "%microtime% %char% (%char_explanation%) %key%%EOL%")

          view_cache_manager:
            class: sfViewCacheTagManager    # Extended sfViewCacheManager class
            #param:
            #  … your params here

    **Short working example to start caching with tags using APC (location: ``/config/factories.yml``)**

        all:
          view_cache:
            class: sfTaggingCache
            param:
              lifetime: 86400
              logging: true
              cache:
                class: sfAPCTaggingCache
                param: {}
              tags: ~
              logger:
                class: sfCacheTagLogger
                param:
                  file: %SF_LOG_DIR%/cache_%SF_ENVIRONMENT%.log
                  format: %char%

          view_cache_manager:
            class: sfViewCacheTagManager
            param:
              cache_key_use_vary_headers: true
              cache_key_use_host_name:    true

  > **Restrictions**: Backend's class should be inherited from ``sfCache``
    class. Then, it should be implement sfTaggingCacheInterface 
    (due a Doctrine cache engine compatibility).
    Also, it should support the caching of objects and/or arrays.

    Therefor, plugin comes with additional extended cache backend classes:

      - sfAPCTaggingCache
      - sfEAcceleratorTaggingCache
      - sfFileTaggingCache
      - sfMemcacheTaggingCache
      - sfSQLiteTaggingCache
      - sfXCacheTaggingCache

1.  Add "Cachetaggable" behavior to each model, which you want to cache

  Example of file ``/config/doctrine/schema.yml``

        YourModel:
          tableName: your_model
          actAs:
            ## CONFIGURATION SHORT VERSION
            ## Cachetaggable will detect your primary keys automatically
            ## and generates uniqueKeyFormat based on PK column count
            ## (e.g. '%s_%s' if table contains 2 primary keys)
            Cachetaggable: ~

            ## CONFIGURATION EXPLAINED VERSION
            #Cachetaggable:
            #  uniqueColumn: id               # you can customize unique column name (default is all table primary keys)
            #  versionColumn: object_version  # you can customize version column name (default is "object_version")
            #  uniqueKeyFormat: '%s'          # you can customize key format (default is "%s")
            #
            #  # if you have more then 1 unique column, you could pass all of them
            #  # as array (tag name will be based on all of them)
            #
            #  uniqueColumn: [id, is_enabled]
            #  uniqueKeyFormat: '%d-%02b'      # the order of unique columns
            #                                  # matches the "uniqueKeyFormat" template variables order


1.  Enable cache in ``settings.yml`` and add additional helpers to
    ``standard_helpers`` section

  To setup cache, often, is used a separate environment named "cache",
  but in the same way you can do it in any other environments which you already have.

        prod:
          .settings:
            cache: true

        cache:
          .settings:
            cache: true

        all:
          .settings:
            cache: false

  Add helpers to the each application:

        all:
          .settings:
            standard_helpers:
              # … other helpers
              - Partial     # build-in Symfony helper to work with partials/components
              - Cache       # build-in Symfony helper to work with cache

1.  Customize ``sfCacheTaggingPlugin`` in the ``app.yml``

  Explained and commented version of ``app.yml``:

        all:
          sfcachetaggingplugin:

            template_lock: "lock_%s"    # Name for locks.
            template_tag: "tag_%s"      # Name for tags.

            microtime_precision: 5      # Version precision.
                                        # 0: without micro time, version length 10 digits
                                        # 5: with micro time part, version length 15 digits
                                        # (allowed decimal numbers in range [0, 6]

            #object_class_tag_name_provider: # you can customize tag name naming
            #                                # useful for multi-environment models
            #  - ProjectToolkit              # [class name]
            #  - formatObjectClassName       # [static method name]

   Minified ``app.yml`` content:

        all:
          sfcachetaggingplugin:
            template_lock:        "%SF_ENVIRONMENT%_lock_%s"
            template_tag:         "%SF_ENVIRONMENT%_tag_%s"
            microtime_precision:  5

## Usage ##

  No more ``cache()`` and ``cache_save()`` helpers. You must setup all cache logic in ``cache.yml``

#### *Partials*

  To link partial with content tags, you should pass them as an extra parameter
  named ``sf_cache_tags``. Cache name should be certainly passed as parameter ``sf_cache_key``.

  * ``cache.yml`` configuration:

        _listing:
          enabled: true

  * Action template ``indexSuccess.php``:

        [php]
        <?php /* @var $posts Doctrine_Collection_Cachetaggable */ ?>

        <h1><?php __('Posts in "%1%"', array('%1%' => $sf_user->getCulture())); </h1>

        <?php include_partial('posts/listing', array(
          'posts' => $posts,
          'sf_cache_key' => sprintf('latest-posts-culture:%s', $sf_user->getCulture()),
          'sf_cache_tags' => $posts->getTags(),
        )) ?>

#### *Components (one-table)*

  To link component with tags you should call ``$this->setPartialTags();``
  inside you component.

  * Remember to enable specific component caching in ``cache.yml``:

        _listOfPosts:
          enabled: true

  * Action template: ``indexSuccess.php``

        [php]
        <fieldset>
          <legend>Component</legend>
          <?php include_component('posts', 'listOfPosts', array(
            'sf_cache_key' => sprintf('list-of-posts-%s', $sf_user->getCulture()),
          )) ?>
        </fieldset>


  * ``components.class.php``

        [php]
        class postsComponents extends sfComponents
        {
          public function executeListOfPosts ($request)
          {
            /* @var $posts Doctrine_Collection_Cachetaggable */
            $posts = Doctrine::getTable('BlogPost')
              ->createQuery('bp')
              ->select('bp.*')
              ->orderBy('bp.id DESC')
              ->limit(3)
              ->execute();


            // See more about all available methods in PHPDOC of file
            // ./plugins/sfCacheTaggingPlugin/lib/util/sfViewCacheTagManagerBridge.class.php
            // (e.g. $this->deletePartialTags(), $this->setPartialTag())

            // $this->setPartialTags($posts->getTags());
            // or equivalent-shorter code
            $this->setPartialTags($posts);

            $this->posts = $posts;
          }
        }

#### *Components (two-tables, combining posts and comments 1:M relation)*

  * Notice: enable component caching in ``cache.yml``

        _listOfPostsAndComments:
          enabled: true

  * ``indexSuccess.php``

        [php]
        <fieldset>
          <legend>Component (posts and comments)</legend>
          <?php include_component('post', 'listOfPostsAndComments', array(
            'sf_cache_key' => 'list-of-posts-in-left-block',
          )) ?>
        </fieldset>

  * ``components.class.php``

        [php]
        class postsComponents extends sfComponents
        {
          /**
           * Explained version (AVOID OF USING IT)
           */
          public function executeListOfPostsAndComments ($request)
          {
            // each post could have many comments (fetching posts with its comments)
            $posts = Doctrine::getTable('BlogPost')
              ->createQuery('bp')
              ->addSelect('bp.*, bpc.*')
              ->innerJoin('bp.BlogPostComments bpc')
              ->orderBy('bp.id DESC')
              ->limit(3)
              ->execute();


            foreach ($posts as $post)
            {
              // our cache (with posts) should be updated on edited/deleted/added the comments
              // therefore, we are collecting comment's tags

              // $posts->addTags($post->getBlogPostComment()->getTags());
              // or shorter
              $posts->addTags($post->getBlogPostComment());
            }

            // after, we pass all tags to cache manager
            // See more about all available methods in PHPDOC of file
            // ./plugins/sfCacheTaggingPlugin/lib/util/sfViewCacheTagManagerBridge.class.php
            // $this->setPartialTags($posts->getTags());

            // or shorter
            $this->setPartialTags($posts);

            $this->posts = $posts;
          }

          /**
           * Good, real world example.
           *
           * The shortest variant of previous code for lazy people like me
           */
          public function executeListOfPostsAndComments($request)
          {
            $posts = Doctrine::getTable('BlogPost')
              ->createQuery('bp')
              ->addSelect('bp.*, bpc.*')
              ->innerJoin('bp.BlogPostComments bpc')
              ->orderBy('bp.id DESC')
              ->limit(3)
              ->execute();

            // fetch object tags recursively from the joined tables ;)
            $this->setPartialTags($posts->getTags(true));

            $this->posts = $posts;
          }
        }

#### *Adding tags to the whole page (action with layout)*

  * Without a doubt, you have to enable the cache for that action in ``config/cache.yml``:

        showSuccess:
          with_layout: true
          enabled:     true

  * Use it in your action to set the tags:

        [php]
        class carActions extends sfActions
        {
          public function executeShow (sfWebRequest $request)
          {
            // get a "Cachetaggable" Doctrine_Record
            $car = Doctrine_Core::getTable('car')->find($request->getParameter('id'));

            // set the tags for the action cache
            // See more about all available methods in PHPDOC of file
            // ./plugins/sfCacheTaggingPlugin/lib/util/sfViewCacheTagManagerBridge.class.php

            // $this->setPageTags($car->getTags());
            // or shorter
            $this->setPageTags($car);

            $this->car = $car;
          }
        }

#### *Adding tags to the specific action (action without layout)*

  * You have to disable "with_layout" and enable the cache for that action in ``config/cache.yml``:

        showSuccess:
          with_layout: false
          enabled:     true

  * Action example

        [php]
        class carActions extends sfActions
        {
          public function executeShow (sfWebRequest $request)
          {
            // get a "Cachetaggable" Doctrine_Record
            $car = Doctrine_Core::getTable('car')->find($request->getParameter('id'));

            // set the tags for the action cache
            // See more about all available methods in PHPDOC of file
            // ./plugins/sfCacheTaggingPlugin/lib/util/sfViewCacheTagManagerBridge.class.php

            // $this->setActionTags($car->getTags());
            // or shorter
            $this->setActionTags($car);

            $this->car = $car;
          }
        }

#### *Caching Doctrine_Records/Doctrine_Collections with its tags*

  * To start caching objects/collection with its tags you have just to enable
    result cache by calling ``Doctrine_Query::useResultCache()``:

        [php]
        class carActions extends sfActions
        {
          // Somewhere in component/action, you need to print out latest posts
          $posts = Doctrine::getTable('BlogPost')
            ->createQuery()
            ->useResultCache()
            ->addWhere('lang = ?', 'en_GB')
            ->addWhere('is_visible = ?', true)
            ->limit(15)
            ->execute();

          $this->posts = $posts;

          // if you run this action again - you will pleasantly surprised
          // $posts now stored in cache and with object tags ;)
          // (object serialization powered by Doctrine build-in mechanism)
          // and expired as soon as you edit one of them
          // or add new record to table "blog_post"

          // when Doctrine_Query has many joins, tags will be fetched
          // recursively from all joined models
        }


  * Appending tags to existing Doctrine tags:

        [php]
        class carActions extends sfActions
        {
          // Somewhere in component/action, you need to print out latest posts
          //
          $posts = Doctrine::getTable('BlogPost')
            ->createQuery()
            ->useResultCache()
            ->addWhere('lang = ?')
            ->addWhere('is_visible = ?')
            ->limit(15)
            ->execute(array('en_GB', true));

          // objects are written to cache with it tags

          // For example, want to $posts will be invalidated if something was changed
          // in table "culture":
          $q = Doctrine::getTable('Culture')->createQuery();
          $cultures = $q->execute();

          // when execute was runned without params "$q->execute();"
          $this->addDoctrineTags($cultures, $q);

          // when execute was runned with params "$q->execute(array(true, 1, 'foo'));"
          // $this->addDoctrineTags($cultures, $q->getResultCacheHash($q->getParams()));
          // or
          // shorter
          // $this->addDoctrineTags($posts, $q, $q->getParams());

          // now if you update something in culture table, $posts will be expired

          $this->posts = $posts;
        }

## Limitations / Specificity ##

  * In case, when model has translations (I18n behavior), it is enough to add
    "``actAs: Cachetaggable``" to the model. I18n behavior should be free from ``Cachetaggable``
    behavior.

## TDD ##

  * Unit/funcational tests report:

        $ ./symfony test:all
        CacheTagging] functional/frontend/DoctrineListenerCachetaggableTest..ok
        CacheTagging] functional/frontend/DoctrineTemplateCachetaggableTest..ok
        [sfCacheTagging] functional/frontend/actionWithLayoutTest............ok
        [sfCacheTagging] functional/frontend/actionWithoutLayoutTest.........ok
        [sfCacheTagging] functional/frontend/sfCacheTaggingPluginTest........ok
        [sfCacheTagging] functional/frontend/sfCacheTaggingToolkitTest.......ok
        [sfCacheTagging] functional/frontend/sfContentTagHandlerTest.........ok
        [sfCacheTagging] functional/frontend/sfDoctrineProxyCacheTest........ok
        sfCacheTagging] functional/frontend/sfViewCacheTagManagerBridgeTest..ok
        [sfCacheTagging] functional/frontend/sfViewCacheTagManagerTest.......ok
        [sfCacheTagging] functional/notag/DoctrineListenerCachetaggableTest..ok
        [sfCacheTagging] functional/notag/sfCacheTaggingToolkitTest..........ok
        [sfCacheTagging] unit/CacheMetadataTest..............................ok
        [sfCacheTagging] unit/sfCacheTagLoggerTest...........................ok
        [sfCacheTagging] unit/sfCacheTaggingToolkitTest......................ok
        [sfCacheTagging] unit/sfCallableArrayTest............................ok
        [sfCacheTagging] unit/sfContentTagHandlerTest........................ok
        [sfCacheTagging] unit/sfFileCacheTagLoggerTest.......................ok
        [sfCacheTagging] unit/sfNoCacheTagLoggerTest.........................ok
        [sfCacheTagging] unit/sfNoTaggingCacheTest...........................ok
        [sfCacheTagging] unit/sfTagNamespacedParameterHolderTest.............ok
        [sfCacheTagging] unit/sfTaggingCacheInterfaceTest....................ok
        [sfCacheTagging] unit/sfTaggingCacheTest.............................ok
         All tests successful.
         Files=23, Tests=1564

  * Coverage report:

        ./symfony test:coverage plugins/sfCacheTaggingPlugin/test plugins/sfCacheTaggingPlugin/
        >> coverage  running …fTagNamespacedParameterHolderTest.php (1/23)
        >> coverage  running …/test/unit/sfNoCacheTagLoggerTest.php (2/23)
        >> coverage  running …ugin/test/unit/sfTaggingCacheTest.php (3/23)
        >> coverage  running …/unit/sfTaggingCacheInterfaceTest.php (4/23)
        >> coverage  running …est/unit/sfFileCacheTagLoggerTest.php (5/23)
        >> coverage  running …st/unit/sfCacheTaggingToolkitTest.php (6/23)
        >> coverage  running …in/test/unit/sfCacheTagLoggerTest.php (7/23)
        >> coverage  running …in/test/unit/sfNoTaggingCacheTest.php (8/23)
        >> coverage  running …test/unit/sfContentTagHandlerTest.php (9/23)
        >> coverage  running …ugin/test/unit/CacheMetadataTest.php (10/23)
        >> coverage  running …in/test/unit/sfCallableArrayTest.php (11/23)
        >> coverage  running …rontend/sfCacheTaggingPluginTest.php (12/23)
        >> coverage  running …ontend/sfViewCacheTagManagerTest.php (13/23)
        >> coverage  running …octrineListenerCachetaggableTest.php (14/23)
        >> coverage  running …octrineTemplateCachetaggableTest.php (15/23)
        >> coverage  running …al/frontend/actionWithLayoutTest.php (16/23)
        >> coverage  running …rontend/sfDoctrineProxyCacheTest.php (17/23)
        >> coverage  running …frontend/actionWithoutLayoutTest.php (18/23)
        >> coverage  running …ontend/sfCacheTaggingToolkitTest.php (19/23)
        >> coverage  running …frontend/sfContentTagHandlerTest.php (20/23)
        >> coverage  running …/sfViewCacheTagManagerBridgeTest.php (21/23)
        >> coverage  running …octrineListenerCachetaggableTest.php (22/23)
        >> coverage  running …/notag/sfCacheTaggingToolkitTest.php (23/23)
        plugins/sfCacheTaggingPlugin/lib/cache/sfTaggingCacheInterface.class   100%
        plugins/sfCacheTaggingPlugin/lib/cache/sfNoTaggingCache.class          100%
        plugins/sfCacheTaggingPlugin/lib/cache/sfTaggingCache.class            100%
        lugins/sfCacheTaggingPlugin/lib/cache/extra/sfSQLiteTaggingCache.class 100%
        plugins/sfCacheTaggingPlugin/lib/cache/extra/sfFileTaggingCache.class  100%
        plugins/sfCacheTaggingPlugin/lib/cache/extra/sfAPCTaggingCache.class    91%
        /sfCacheTaggingPlugin/lib/cache/extra/sfEAcceleratorTaggingCache.class  25%
        gins/sfCacheTaggingPlugin/lib/cache/extra/sfMemcacheTaggingCache.class 100%
        lugins/sfCacheTaggingPlugin/lib/cache/extra/sfXCacheTaggingCache.class  20%
        plugins/sfCacheTaggingPlugin/lib/cache/CacheMetadata.class             100%
        lugins/sfCacheTaggingPlugin/lib/util/sfViewCacheTagManagerBridge.class 100%
        plugins/sfCacheTaggingPlugin/lib/util/sfCallableArray.class            100%
        plugins/sfCacheTaggingPlugin/lib/util/sfCacheTaggingToolkit.class       99%
        plugins/sfCacheTaggingPlugin/lib/util/sfContentTagHandler.class        100%
        ins/sfCacheTaggingPlugin/lib/util/sfTagNamespacedParameterHolder.class 100%
        plugins/sfCacheTaggingPlugin/lib/view/sfViewCacheTagManager.class       53%
        plugins/sfCacheTaggingPlugin/lib/log/sfFileCacheTagLogger.class        100%
        plugins/sfCacheTaggingPlugin/lib/log/sfCacheTagLogger.class            100%
        plugins/sfCacheTaggingPlugin/lib/log/sfNoCacheTagLogger.class          100%
        ins/sfCacheTaggingPlugin/lib/doctrine/cache/sfDoctrineProxyCache.class 100%
        ugins/sfCacheTaggingPlugin/lib/doctrine/collection/Cachetaggable.class  96%
        cheTaggingPlugin/lib/doctrine/query/Cachetaggable_Doctrine_Query.class  79%
        plugins/sfCacheTaggingPlugin/lib/doctrine/listener/Cachetaggable.class  68%
        plugins/sfCacheTaggingPlugin/lib/doctrine/template/Cachetaggable.class  95%
        fCacheTaggingPlugin/lib/exception/sfCacheMissingContextException.class 100%
        gins/sfCacheTaggingPlugin/lib/exception/sfCacheDisabledException.class 100%
        gins/sfCacheTaggingPlugin/test/unit/sfTagNamespacedParameterHolderTest  95%
        plugins/sfCacheTaggingPlugin/test/unit/sfNoCacheTagLoggerTest          100%
        plugins/sfCacheTaggingPlugin/test/unit/sfTaggingCacheTest              100%
        plugins/sfCacheTaggingPlugin/test/unit/sfTaggingCacheInterfaceTest     100%
        plugins/sfCacheTaggingPlugin/test/unit/sfFileCacheTagLoggerTest         97%
        plugins/sfCacheTaggingPlugin/test/unit/sfCacheTaggingToolkitTest        90%
        plugins/sfCacheTaggingPlugin/test/unit/sfCacheTagLoggerTest             80%
        plugins/sfCacheTaggingPlugin/test/unit/sfNoTaggingCacheTest            100%
        plugins/sfCacheTaggingPlugin/test/unit/sfContentTagHandlerTest         100%
        plugins/sfCacheTaggingPlugin/test/unit/CacheMetadataTest                97%
        plugins/sfCacheTaggingPlugin/test/unit/sfCallableArrayTest              92%
        sfCacheTaggingPlugin/test/functional/frontend/sfCacheTaggingPluginTest  99%
        fCacheTaggingPlugin/test/functional/frontend/sfViewCacheTagManagerTest  99%
        ggingPlugin/test/functional/frontend/DoctrineListenerCachetaggableTest  99%
        ggingPlugin/test/functional/frontend/DoctrineTemplateCachetaggableTest  97%
        ins/sfCacheTaggingPlugin/test/functional/frontend/actionWithLayoutTest 100%
        sfCacheTaggingPlugin/test/functional/frontend/sfDoctrineProxyCacheTest 100%
        /sfCacheTaggingPlugin/test/functional/frontend/actionWithoutLayoutTest 100%
        fCacheTaggingPlugin/test/functional/frontend/sfCacheTaggingToolkitTest  85%
        /sfCacheTaggingPlugin/test/functional/frontend/sfContentTagHandlerTest  96%
        TaggingPlugin/test/functional/frontend/sfViewCacheTagManagerBridgeTest  92%
        eTaggingPlugin/test/functional/notag/DoctrineListenerCachetaggableTest  95%
        s/sfCacheTaggingPlugin/test/functional/notag/sfCacheTaggingToolkitTest  91%
        ns/sfCacheTaggingPlugin/config/sfCacheTaggingPluginConfiguration.class 100%
        TOTAL COVERAGE:  91%

Every combination is tested (data backend / locker backend) of listed below:

  * sfMemcacheTaggingCache
  * sfAPCTaggingCache
  * sfSQLiteTaggingCache (file)
  * sfSQLiteTaggingCache (memory)
  * sfFileTaggingCache

Partially tested listed below cache adapters. If anyone could help me to run functional tests
for them, I will be thankful to you:

  * sfXCacheTaggingCache
  * sfEAcceleratorTaggingCache

## Contacts ##

### Plugin lead/developer

  * @: Ilya Sabelnikov `` <fruit dot dev at gmail dot com> ``
  * skype: ilya_roll

### Plugin developer (ability to add tags to the whole page with layout)

  * @: Martin Schnabel `` <mcnilz at gmail dot com> ``
  * skype: mcnilz