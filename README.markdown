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

## Ilustration ##?

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
    creating a file ``/config/factories.yml`` (you could find explaind
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
              logging: true                 # logging is enabled ("false" to disable)
              cache:
                class: sfMemcacheCache      # Content will be stored in Memcache
                                            # Here you can switch to any other backend
                                            # (see Restrictions block for more info)
                param:
                  persistent: true
                  storeCacheInfo: false
                  host: localhost
                  port: 11211
                  timeout: 5
                  lifetime: 86400           # default value is 1 day (in seconds)


              locker:                       # locks storage (could be same as
                                            # the cache storage)
                                            # if "locker" not setted (is "~"), it will
                                            # be the same as cache (e.i. sfMemcacheCache)

                class: sfAPCCache           # Locks will be stored in APC
                                            # Here you can switch to any other backend sf*Cache
                                            # (see Restrictions block for more info)
                param: {}

          view_cache_manager:
            class: sfViewCacheTagManager    # Extended sfViewCacheManager class
            #param:
            #  … your params here

    **Short working example to start caching with tags using APC (location: ``/config/factories.yml``)**

        all:
          view_cache:
            class: sfTaggingCache
            param:
              logging: true
              cache: { class: sfAPCCache, param: {} }
              locker: ~

          view_cache_manager:
            class: sfViewCacheTagManager
            param:
              cache_key_use_vary_headers: true
              cache_key_use_host_name:    true

  > **Restrictions**: Backend's class should be inherited from ``sfCache``
    class. Also, it should support the caching of objects and/or arrays.

  > **Bonus**: In additional to this plugin comes ``sfFileTaggingCache``
    and ``sfSQLiteTaggingCache`` which are ready to use as backend class.
    This classes already have serialization/unserialization support.

1.  Add "Cachetaggable" behavior to each model, which you want to cache

  Example of file ``/config/doctrine/schema.yml``

        YourModel:
          tableName: your_model
          actAs:
            ## CONFIGURATION SHORT VERSION
            ## Cachetaggable will detect your primery keys automaticaly
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

            lock_lifetime: 2            # Number of seconds to keep lock, if failed to
                                        # unlock after locking it.
                                        # Value should be more then zero.

            tag_lifetime: 86400         # Number of seconds to keep tags alive
                                        # (default value if not set 86400).
                                        # It's recommended to keep this value the same as
                                        # you have declared in factories.yml at
                                        # "all_view_cache_param_cache_param_lifetime" (86400).
                                        # Value should be more then zero.

            log_format_extended: 0      # Logs will be stored in ``log/cache_%environment_name%.log``
                                        # 0: Print 1 char in one line.
                                        # 1: Print 1 char + cache key + additional data
                                        #    (if available) per line.
                                        #
                                        # Char explanation:
                                        #   "g": content cache not found
                                        #   "G": content cache is found
                                        #   "l": could not lock the content or the tag
                                        #   "L": content/tag was locked for writing
                                        #   "s": could not write new values to the cache
                                        #        (e.g. for lock reasons)
                                        #   "S": new values are saved to the cache
                                        #   "u": could not unlock the cache
                                        #   "U": cache was unlocked
                                        #   "t": cache tag was expired
                                        #   "T": cache tag is up-to-date
                                        #   "p": could not write new version of tag
                                        #   "P": tag version is updated
                                        #
                                        # Chars in lower case indicate negative operation.
                                        # Chars in upper case indicate positive operation.

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
            lock_lifetime:        2
            tag_lifetime:         86400
            log_format_extended:  0

## Usage ##

  No more ``cache()`` and ``cache_save()`` helpers. You must setup all
  cache logic in ``cache.yml`` (location: ``%app%/modules/%module%/config/cache.yml``).

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

  To link component with tags you will should call ``$this->setPartialTags();``
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
            // $this->setUserTags($posts->getTags());

            // or shorter
            $this->setUserTags($posts);

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

          // if you run this action again - you will pleasantly suprised
          // $posts now stored in cache and with object tags ;)
          // (object serialization powered by Doctrine build-in mechanism)
          // and expired as soon as you edit one of them
          // or add new record to table "blog_post"
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

          // For example, want to $posts will be invalidated if something was chanaged
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

## Limitations / Specifity ##

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
        [sfCacheTagging] functional/frontend/sfTaggingCacheTest..............ok
        sfCacheTagging] functional/frontend/sfViewCacheTagManagerBridgeTest..ok
        [sfCacheTagging] functional/frontend/sfViewCacheTagManagerTest.......ok
        [sfCacheTagging] functional/notag/DoctrineListenerCachetaggableTest..ok
        [sfCacheTagging] functional/notag/sfCacheTaggingToolkitTest..........ok
        [sfCacheTagging] unit/DoctrineTemplateCachetaggableTest..............ok
        [sfCacheTagging] unit/sfCacheTaggingToolkitTest......................ok
        [sfCacheTagging] unit/sfCallableArrayTest............................ok
        [sfCacheTagging] unit/sfContentTagHandlerTest........................ok
         All tests successful.
         Files=17, Tests=1256

  * Coverage report:

        $ ./symfony test:coverage --detailed plugins/sfCacheTaggingPlugin/test/ plugins/sfCacheTaggingPlugin/lib/
        >> coverage  running /www/sfpro/de...lateCachetaggableTest.php (1/17)
        >> coverage  running /www/sfpro/de...cheTaggingToolkitTest.php (2/17)
        >> coverage  running /www/sfpro/de...ContentTagHandlerTest.php (3/17)
        >> coverage  running /www/sfpro/de...t/sfCallableArrayTest.php (4/17)
        >> coverage  running /www/sfpro/de...acheTaggingPluginTest.php (5/17)
        >> coverage  running /www/sfpro/de...ewCacheTagManagerTest.php (6/17)
        >> coverage  running /www/sfpro/de...enerCachetaggableTest.php (7/17)
        >> coverage  running /www/sfpro/de...nd/sfTaggingCacheTest.php (8/17)
        >> coverage  running /www/sfpro/de...lateCachetaggableTest.php (9/17)
        >> coverage  running /www/sfpro/de...actionWithLayoutTest.php (10/17)
        >> coverage  running /www/sfpro/de...ctrineProxyCacheTest.php (11/17)
        >> coverage  running /www/sfpro/de...ionWithoutLayoutTest.php (12/17)
        >> coverage  running /www/sfpro/de...heTaggingToolkitTest.php (13/17)
        >> coverage  running /www/sfpro/de...ontentTagHandlerTest.php (14/17)
        >> coverage  running /www/sfpro/de...TagManagerBridgeTest.php (15/17)
        >> coverage  running /www/sfpro/de...nerCachetaggableTest.php (16/17)
        >> coverage  running /www/sfpro/de...heTaggingToolkitTest.php (17/17)
        plugins/sfCacheTaggingPlugin/lib/cache/sfNoTaggingCache.class           21%
        plugins/sfCacheTaggingPlugin/lib/cache/sfTaggingCache.class             83%
        lugins/sfCacheTaggingPlugin/lib/cache/extra/sfSQLiteTaggingCache.class 100%
        plugins/sfCacheTaggingPlugin/lib/cache/extra/sfFileTaggingCache.class  100%
        lugins/sfCacheTaggingPlugin/lib/util/sfViewCacheTagManagerBridge.class  66%
        plugins/sfCacheTaggingPlugin/lib/util/sfCallableArray.class            100%
        plugins/sfCacheTaggingPlugin/lib/util/sfCacheTaggingToolkit.class       83%
        plugins/sfCacheTaggingPlugin/lib/util/sfContentTagHandler.class         74%
        ins/sfCacheTaggingPlugin/lib/util/sfTagNamespacedParameterHolder.class  83%
        plugins/sfCacheTaggingPlugin/lib/view/sfViewCacheTagManager.class       53%
        ins/sfCacheTaggingPlugin/lib/doctrine/cache/sfDoctrineProxyCache.class  59%
        ugins/sfCacheTaggingPlugin/lib/doctrine/collection/Cachetaggable.class  85%
        cheTaggingPlugin/lib/doctrine/query/Cachetaggable_Doctrine_Query.class  79%
        plugins/sfCacheTaggingPlugin/lib/doctrine/listener/Cachetaggable.class  69%
        plugins/sfCacheTaggingPlugin/lib/doctrine/template/Cachetaggable.class  91%
        fCacheTaggingPlugin/lib/exception/sfCacheMissingContextException.class   0%
        gins/sfCacheTaggingPlugin/lib/exception/sfCacheDisabledException.class 100%
        TOTAL COVERAGE:  74%

Every combination is tested (data backend / locker backend) of listed below:

  * sfMemcacheCache
  * sfAPCCache
  * sfSQLiteTaggingCache - file (extended from sfSQLiteCache)
  * sfSQLiteTaggingCache - memory (extended from sfSQLiteCache)
  * sfFileTaggingCache (extended from sfFilecache)

Partially tested listed below cache adapters. If anyone could help me to run functional tests
for them, I will be thankful to you:

  * sfXCacheCache
  * sfEAcceleratorCache

## Contacts ##

### Plugin lead/developer

  * @: Ilya Sabelnikov `` <fruit dot dev at gmail dot com> ``
  * skype: ilya_roll

### Plugin developer (ability to add tags to the whole page with layout)

  * @: Martin Schnabel `` <mcnilz at gmail dot com> ``
  * skype: mcnilz