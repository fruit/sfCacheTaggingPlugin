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

  To setup cache, often, used a separate environment named "cache",
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

  Add helpers to the frontend application.

            all:
              .settings:
                standard_helpers:
                  # … other helpers
                  - Partial     # build-in Symfony helper to work with partials/components
                  - Cache       # build-in Symfony helper to work with cache
                  - CacheTag    # sfCacheTaggingPlugin helper to tagging features

1.  Customize ``sfCacheTaggingPlugin`` in the ``/config/app.yml``

  Explained and commented version of ``/config/app.yml`` (project level or to each application's level ``/apps/%application_name%/config/app.yml``)

            all:
              sfcachetaggingplugin:

                template_lock: "lock_%s"    # name for locks (recommended to include constant %SF_ENVIRONMENT%)
                template_tag: "tag_%s"      # name for tags (recommended to include constant %SF_ENVIRONMENT%)

                microtime_precision: 5      # version precision
                                            # (0 - without micro time (only seconds), version will be 10 digits length)
                                            # (5 - with micro time part, version will be 15 digits length)
                                            # (max precision value = 6, min = 0, only decimal numbers)

                lock_lifetime: 2            # number of seconds to keep lock, if failed to unlock after locking it (default value if not set 2)
                                            # value should be more then zero

                tag_lifetime: 86400         # number of seconds to keep tags alive (default value if not set 86400)
                                            # it is recommended to keep this value the same as you have declared in factories.yml at "all_view_cache_param_cache_param_lifetime" (86400)
                                            # value should be more then zero

                log_format_extended: 0      # logs will be stored in ``log/cache_%environment_name%.log``
                                            # "0" print 1 char in one line
                                            # "1" print 1 char, cache key, additional data (if available) per line
                                            #
                                            # Char explanation:
                                            #   "g": content cache not found
                                            #   "G": content cache is found
                                            #   "l": could not lock the content or the tag
                                            #   "L": content/tag was locked for writing
                                            #   "s": could not write new values to the cache (e.g. for lock reasons)
                                            #   "S": new values are saved to the cache
                                            #   "u": could not unlock the cache
                                            #   "U": cache was unlocked
                                            #   "t": cache tag was expired
                                            #   "T": cache tag is up-to-date
                                            #   "p": could not write new version of tag
                                            #   "P": tag version is updated
                                            #
                                            # Chars in lower case indicate negative operation
                                            # Chars in upper case indicate positive operation

                #object_class_tag_name_provider: # you can customize tag name naming
                #                                # useful for multi-environment models
                #  - ProjectToolkit              # [class name]
                #  - formatObjectClassName       # [static method name]

   Minified ``/config/app.yml`` content:

            all:
              sfcachetaggingplugin:
                template_lock:        "%SF_ENVIRONMENT%_lock_%s"
                template_tag:         "%SF_ENVIRONMENT%_tag_%s"
                microtime_precision:  5
                lock_lifetime:        2
                tag_lifetime:         86400
                log_format_extended:  0

## Usage ##

There are two known ways to cache partials and components:

 1. ### First way is to configure module-level ``cache.yml``:

        _my_partial:
          enabled: true
          lifetime: 600

        _myCompdonent:
          enabled: true

    And main template (e.g. ``indexSuccess.php``) will looks like:

        [php]
        <h1>Welcome user!</h1>
        <?php include_partial('default/my_partial') ?>

        <h2>About town we live in</h2>
        <?php include_component('default', 'myComponent') ?>

    *Benefits*:
     * Beautiful blue/yellow CSS-blocks when you are in dev-environment

    *Limitations*:
     * no custom cache naming (see next way)

 2. ### Second way is not to use cache.yml and store cache logic in template:

        [php]
        <h1>Welcome user!</h1>

        <?php $hash = sprintf('my-partial-id:%d-culture:%s', $sf_user->getId(), $sf_user->getCulture()); ?>
        <?php if (! cache_tag($hash, 60*60*24*30)) { ?>
          <?php include_partial('default/my_partial') ?>
          <?php cache_tag_save($posts->getTags()); ?>
        <?php } ?>

        <h2>About town we live in</h2>

        <?php $hash = sprintf('my-component-town:%s', $sf_request->getParameter('town')); ?>
        <?php if (! cache($hash)) { ?>
          <?php include_component('default', 'myComponent') ?>
          <?php cache_save($posts->getTags()); ?>
        <?php } ?>

    *Benefits*:
     * custom cache naming based on whatever variables

    *Limitations:*
     * CSS-blocks are always blue in dev-environment ;)
     * more code in templates

#### *Partials*

  * NOTICE! To cache partials you should use ``cache_tag()`` and ``cache_tag_save()``.
  * Otherwise, in components/component slots, use build-in helpers ``cache()`` and ``cache_save()``

        [php]
        <?php /* @var $posts Doctrine_Collection_Cachetaggable */ ?>
        <fieldset>
          <legend>Partial</legend>

          <?php if (! cache_tag('latest-blog-posts-index-on-page')) { ?>
            <?php foreach ($posts as $post) { ?>
              <?php include_partial('posts/one_post', array('post' => $post)) ?>
            <?php } ?>
            <?php cache_tag_save($posts->getTags()); ?>
          <?php } ?>

        </fieldset>



#### *Components (one-table)*

  * Remember to enable each partial/component in module cache.yml ``%app%/modules/%module%/config/cache.yml``:

        _listOfPosts:
          enabled: true

  * ``indexSuccess.php``

        [php]
        <fieldset>
          <legend>Component</legend>
          <?php include_component('posts', 'listOfPosts') ?>
        </fieldset>


  * ``components.class.php``

        [php]
        class postsComponents extends sfComponents
        {
          public function executeListOfPosts($request)
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

  * Notice: enable component caching in cache.yml

        _listOfPostsAndComments:
          enabled: true

  * ``indexSuccess.php``

        [php]
        <fieldset>
          <legend>Component (posts and comments)</legend>
          <?php include_component('post', 'listOfPostsAndComments') ?>
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
            $this->setPageTags($this->car);

            $this->car = $car;
          }
        }

  * Without a doubt, you have to enable the cache for that action in ``config/cache.yml``:

        # "show" is a word from action method "execiteShow"
        # also you could name it as "showSuccess"
        show:
          with_layout: true
          enabled:     true

#### *Adding tags to the specific action (action without layout)*

  * You have to disable "with_layout" and enable the cache for that action in ``config/cache.yml``:

        # "show" or "showSuccess" (useful, when you have not showFailed.php?)
        show:
          with_layout: false
          enabled:     true
          lifetime:    360

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
    result cache by calling Doctrine_Query::useResultCache()

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


  * Appending tags to existing Doctrine tags

        [php]
        class carActions extends sfActions
        {
          // Somewhere in component/action, you need to print out latest posts
          $posts = Doctrine::getTable('BlogPost')
            ->createQuery()
            ->useResultCache()
            ->addWhere('lang = ?')
            ->addWhere('is_visible = ?')
            ->limit(15)
            ->execute(array('en_GB', true));

          // objects are written to cache with it tags

          $q = Doctrine::getTable('Culture')->createQuery();
          $cultures = $q->execute();

          // if execute was runned without params "$q->execute();"
          $this->addDoctrineTags($cultures, $q);

          // if execute was runned with params "$q->execute(array(true, 1, 'foo'));"
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

            .

  * Coverage report:

            .

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