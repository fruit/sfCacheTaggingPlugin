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

## Contribution ##

* [Repository (GitHub)](http://github.com/fruit/sfCacheTaggingPlugin "Repository (GitHub)") and [Issues (GitHub)](http://github.com/fruit/sfCacheTaggingPlugin/issues "Issues")

## Installation/Upgrading ##

* Installation

        $ ./symfony plugin:install sfCacheTaggingPlugin

* Upgrading

        $ ./symfony plugin:upgrade sfCacheTaggingPlugin

## Release notes

  * Plugin API is fully upgraded (v2.0.0 => v3.0.0)

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

1.  Create a new file ``/config/factories.yml`` or edit each application's level
    ``/apps/%application_name%/config/factories.yml`` file

    > Cache tagging works for you each time you save/update/delete Doctrine record
      or fetch them from DB. So you should enable caching (**sf_cache: true**) in
      all applications you work with. I recommend you to create default ``factories.yml``
      for all applications you have by creating a file ``/config/factories.yml``
      (you could find working example bellow). Symfony will check this file and load
      it as a default factories.yml configuration to all applications you have in the project.

    > This is ``/config/factories.yml`` content (you can copy&paste this code
      into your brand new created file) or merge this config with each application's
      ``factories.yml`` (applications, where you need the data to be fetched/written from/to cache)

    > ### Working example of file ``/config/factories.yml``

        [yml]
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
                  lifetime: 86400           # default value is 1 day - 86400 for each sf*Cache mechanism

              # place, where to store locks for data and tag cache
              locker:                       # if "locker" not setted (is "~"), it will be the same as cache (e.i. sfMemcacheCache)
                class: sfAPCCache           # Locks will be stored in APC
                                            # Here you can switch to any other backend sf*Cache
                                            # (see Restrictions block for more info)
                param: {}

          view_cache_manager:
            class: sfViewCacheTagManager    # Extended sfViewCacheManager class
            param:
              cache_key_use_vary_headers: true
              cache_key_use_host_name:    true

    > ### Short working example to start caching with tags (file ``/config/factories.yml``)

        [yml]
        all:
          view_cache:
            class: sfTaggingCache
            param:
              logging: true
              cache:
                class: sfAPCCache
                param: []
              locker: ~

          view_cache_manager:
            class: sfViewCacheTagManager
            param:
              cache_key_use_vary_headers: true
              cache_key_use_host_name:    true


    > **Restrictions**: Backend's class should be inheritable from ``sfCache``
      class. Also, it should support the caching of objects and/or arrays.

    > **Bonus**: In additional to this plugin comes ``sfFileTaggingCache``
      and ``sfSQLiteTaggingCache`` which are ready to use as backend class.
      This classes already have serialization/unserialization support.



1.  Edit Symfony's predefined application's level ``/apps/%application_name%/config/factories.yml`` files

    > If you have edited each application's level ``/apps/%application_name%/config/factories.yml`` file in
      2nd step - go to 4th step.

    > In each application you want to use cache tagging please remove
      "``all_view_cache_manager``" section (you have already configured it
      in global ``/config/factories.yml`` file).

1.  Add "Cachetaggable" behavior to each model, which you want to be a part of cache content.

  1. ### Example: file ``/config/doctrine/schema.yml``

            [yml]
            BlogPost:
              tableName: blog_post
              actAs:
                Cachetaggable: ~
                #Cachetaggable:
                #  uniqueColumn: id               # you can customize unique column name (default is all table primary keys)
                #  versionColumn: object_version  # you can customize version column name (default is "object_version")
                #  uniqueKeyFormat: '%d'          # you can customize key format (default is "%d")
                #
                #  # if you have more then 1 unique column, you could pass all of them
                #  # as array (tag name will be based on them)
                #
                #  uniqueColumn: [id, is_enabled]
                #  uniqueKeyFormat: '%d-%02b'      # the order of unique columns
                #                                  # matches the "uniqueKeyFormat" template variables order

              columns:
                id:
                  type: integer
                  primary: true
                  autoincrement: true
                  unsigned: true
                title: string(255)
              relations:
                BlogPostComment:
                  class: BlogPostComment
                  type: many
                  local: id
                  foreign: blog_post_id

            BlogPostComment:
              tableName: blog_post_comment
              actAs:
                Cachetaggable: ~
              columns:
                id:
                  type: integer
                  primary: true
                  autoincrement: true
                  unsigned: true
                blog_post_id:
                  type: integer
                  unsigned: true
                  notnull: false
                author: string(20)
                message: string(255)
              indexes:
                blog_post_id: { fields: [blog_post_id] }
              relations:
                BlogPost:
                  onUpdate: CASCADE
                  onDelete: CASCADE

1.  Enable cache in ``settings.yml`` and add additional helpers to ``standard_helpers`` section

  1. To setup cache, often, used a separate environment named "cache",
     but in the same way you can do it in any other environments which (dev) you already have.

            [yml]
            prod:
              .settings:
                cache: true

            cache:
              .settings:
                cache: true

            all:
              .settings:
                cache: false

  1. Add helpers to the frontend application

            [yml]
            all:
              .settings:
                standard_helpers:
                  # … other helpers
                  - Partial     # symfony build in helper (mandatory)
                  - CacheTag    # sfCacheTaggingPlugin helper (mandatory)
                  - PartialTag  # sfCacheTaggingPlugin helper (mandatory)

1.  Customize ``sfCacheTaggingPlugin`` in the ``/config/app.yml``

  1. Copy & past this content to yours ``/config/app.yml`` (project level or to each application's level ``/apps/%application_name%/config/app.yml``)

            [yml]
            all:
              sfcachetaggingplugin:

                template_lock: "lock_%s"    # name for locks
                template_tag: "tag_%s"      # name for tags

                microtime_precision: 5      # version precision (0, or positive number)
                                            # (0 - without micro time, version will be 10 digits length)
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
                #  - ProjectToolkit              # useful for multi-environment models
                #  - formatObjectClassName

   1. Minified ``app.yml`` content:

            [yml]
            all:
              sfcachetaggingplugin:
                template_lock: "lock_%s"
                template_tag: "tag_%s"
                microtime_precision: 5
                lock_lifetime: 2
                tag_lifetime: 86400
                log_format_extended: 0

## Using ##

*   ### Native use
    
    * ``actions.class.php`` or ``components.class.php``

            [php]
            # Somewhere in the frontend, you need to print out latest posts

            $posts = Doctrine::getTable('BlogPost')
              ->createQuery()
              ->orderBy('id DESC')
              ->limit(3)
              ->execute();

            /* @var $tagger sfTaggingCache */
            $tagger = $this->getContext()->getViewCacheManager()->getTaggingCache();

            # write data to the cache ($posts is instance of the Doctrine_Collection_Cachetaggable)
            $tagger->set('my_posts', $posts->toArray(), 60 * 60 * 24 * 30/* 1 month */, $posts->getTags());

            # fetch latest post to edit it
            $post = $posts->getFirst();

            # prints something like "126070596212512"
            print $post->getObjectVersion();

            $post->setTitle('How to use sfCacheTaggingPlugin');

            # save and update/upgrade version of the tag
            $post->save();

            # prints something like "126072290862231" (new version of the tag)
            echo $post->getObjectVersion();

            # will return null
            # $post object was updated, so, all $posts in cache "my_posts” is invalidated automatically)
            if ($data = $tagger->get('my_posts'))
            {
              # this block never executed
            }

            # save new data to the cache
            $tagger->set('my_posts', $posts->toArray(), null, $posts->getTags());

            # will return data (objects are fresh)
            if ($data = $tagger->get('my_posts'))
            {
              # this code block will be executed
            }

            $post = new BlogPost();
            $post->setTitle('New post should be in inserted to the cache results');
            $post->save();

            # will return null, because 'my_posts' cache knows that it contains BlogPost objects
            # and listens on new objects with same type that are newer
            if ($data = $tagger->get('my_posts'))
            {
              # this block will not be executed
            }

            $posts = Doctrine::getTable('BlogPost')
              ->createQuery()
              ->orderBy('id DESC')
              ->limit(3)
              ->execute();

            $tagger->set('my_posts', $posts->toArray(), null, $posts->getTags());

            # will return data
            if ($data = $tagger->get('my_posts'))
            {
              # this block will be executed
            }


*   ### non-Doctrine use:

    * ``indexSuccess.php``

            [php]
            <fieldset>
              <legend>Daylight</legend>

              <?php if (! cache_tag('daylight_content')) { ?>

                <h1>Text to cache No-<?php rand(1, 1000000) ?></h1>

                Text text text text text text text text text text text text text.
                <?php cache_tag_save(array('sun' => time(), 'moon' => time())); ?>
              <?php } ?>

            </fieldset>

    * e.g. ``actions.class.php`` or ``components.class.php``

            [php]
            # when you want to update Daylight content
            $this->getTaggingCache()->setTag('moon', time(), 60 * 60 * 24 * 7 /*1 week*/);

</fieldset>

*   ### Using with partials:

  * ``_posts.php``

            [php]
            <?php /* @var $posts Doctrine_Collection */ ?>
            <fieldset>
              <legend>Partial</legend>

              <?php if (! cache_tag('latest-blog-posts-index-on-page')) { ?>
                <?php foreach ($posts as $post) { ?>
                  <?php include_partial('posts/one_post', array('post' => $post) ?>
                <?php } ?>
                <?php cache_tag_save($posts->getTags()); ?>
              <?php } ?>

            </fieldset>

*   ### Using with components (simple):

  * ``indexSuccess.php``

            [php]
            <fieldset>
              <legend>Component</legend>

              <?php if (! cache_tag('latest-blog-posts-index-on-page')) { ?>
                <?php include_component_tag('posts', 'listOfPosts') ?>
                <?php cache_tag_save(); ?>
              <?php } ?>

            </fieldset>


  * ``components.class.php``

            [php]
            class postsComponents extends sfComponents
            {
              public function executeListOfPosts($request)
              {
                $posts = Doctrine::getTable('BlogPost')
                  ->createQuery('bp')
                  ->select('bp.*')
                  ->orderBy('bp.id DESC')
                  ->limit(3)
                  ->execute();


                # See more about all available methods in PHPDOC of file
                # ./plugins/sfCacheTaggingPlugin/lib/util/sfViewCacheTagManagerBridge.class.php
                $this->setUserTags($posts->getTags());

                # or equivalent-shorter code
                # $this->setUserTags($posts);

                $this->posts = $posts;
              }
            }

*   ### Using with components (complex - Combining posts with comments)

  * ``_post_and_comments.php``

            [php]
            <fieldset>
              <legend>Component (posts and comments)</legend>

              <?php if (! cache_tag('posts_and_comments')) { ?>
                <?php include_component_tag('post', 'listOfPostsAndComments') ?>
                <?php cache_tag_save(); ?>
              <?php } ?>

            </fieldset>

  * ``components.class.php``

            [php]
            class postsComponents extends sfComponents
            {
              public function executeListOfPostsAndComments($request)
              {
                # each post could have many comments (fetching posts with its comments)
                $posts = Doctrine::getTable('BlogPost')
                  ->createQuery('bp')
                  ->addSelect('bp.*, bpc.*')
                  ->innerJoin('bp.BlogPostComments bpc')
                  ->orderBy('bp.id DESC')
                  ->limit(3)
                  ->execute();

                foreach ($posts as $post)
                {
                  # our cache (with posts) should be updated on edited/deleted/added the comments
                  # therefore, we are collecting comment's tags

                  $posts->addTags($post->getBlogPostComment()->getTags());

                  # or shorter
                  # $posts->addTags($post->getBlogPostComment());
                }

                # after, we pass all tags to cache manager
                # See more about all available methods in PHPDOC of file
                # ./plugins/sfCacheTaggingPlugin/lib/util/sfViewCacheTagManagerBridge.class.php
                $this->setUserTags($posts->getTags());

                # or shorter
                # $this->setUserTags($posts);

                $this->posts = $posts;
              }
            }

* ### Adding tags to the whole page (action with layout)

  * Use it in your action to set the tags:

            [php]
            class carActions extends sfActions
            {
              # … setPageTags()

              public function executeShow (sfWebRequest $request)
              {
                # get a "Cachetaggable" Doctrine_Record
                $this->car = Doctrine_Core::getTable('car')->find($request->getParameter('id'));

                # set the tags for the action cache
                # See more about all available methods in PHPDOC of file
                # ./plugins/sfCacheTaggingPlugin/lib/util/sfViewCacheTagManagerBridge.class.php
                $this->setPageTags($this->car->getTags());

                # or shorter
                # $this->setPageTags($this->car);
              }
            }

  * Without a doubt, you have to enable the cache for that action in ``config/cache.yml``:

            [yml]
            # "show" is a word from action method "execiteShow"
            # also you could name it as "showSuccess"
            show:
              with_layout: true
              enabled:     true
              lifetime:    86400

* ### Adding tags to the specific action (action without layout)

  * Use it in your action to set the tags:

            [php]
            class carActions extends sfActions
            {
              # … setActionTags()

              public function executeShow (sfWebRequest $request)
              {
                # get a "Cachetaggable" Doctrine_Record
                $this->car = Doctrine_Core::getTable('car')->find($request->getParameter('id'));

                # set the tags for the action cache
                # See more about all available methods in PHPDOC of file
                # ./plugins/sfCacheTaggingPlugin/lib/util/sfViewCacheTagManagerBridge.class.php
                $this->setActionTags($this->car->getTags());

                # or shorter
                # $this->setActionTags($this->car);
              }
            }

  * You have to disable "with_layout" and enable the cache for that action in ``config/cache.yml``:

            [yml]
            # "show" is a word from action method "execiteShow"
            # also you could name it as "showSuccess"
            show:
              with_layout: false
              enabled:     true
              lifetime:    360

* ### Caching Doctrine_Records/Doctrine_Collections with its tags in action/component

  * Running execute() WITHOUT passed "all together" params:

            [php]
            class carActions extends sfActions
            {
              # Somewhere in component/action, you need to print out latest posts
              $posts = Doctrine::getTable('BlogPost')
                ->createQuery()
                ->useResultCache()
                ->addWhere('lang = ?', 'en_GB')
                ->addWhere('is_visible = ?', true)
                ->limit(15)
                ->execute();

              $this->addDoctrineTags($posts->getTags(), $q);

              # or shorter
              # $this->addDoctrineTags($posts, $q);

              $this->posts = $posts;
            }


  * Running execute() WITH passed "all together" params:

            [php]
            class carActions extends sfActions
            {
              # Somewhere in component/action, you need to print out latest posts
              $posts = Doctrine::getTable('BlogPost')
                ->createQuery()
                ->useResultCache()
                ->addWhere('lang = ?')
                ->addWhere('is_visible = ?')
                ->limit(15)
                ->execute(array('en_GB', true));

              # explained version
              $this->addDoctrineTags($posts->getTags(), $q, $q->getParams());

              # or
              # same result
              # $this->addDoctrineTags($posts->getTags(), $q->getResultCacheHash($q->getParams()));

              # or
              # same result and shorter
              # $this->addDoctrineTags($posts, $q, $q->getParams());

              $this->posts = $posts;
            }

## Limitations / Peculiarities ##

  * In case, when model has translations (I18n behavior), it is enough to add
    "``actAs: Cachetaggable``" to the model. I18n behavior should be free from ``Cachetaggable``
    behavior.

## TDD ##

  * Unit/funcational tests report:

            [sfCacheTagging] functional/frontend/CacheTagHelperTest..............ok
            CacheTagging] functional/frontend/DoctrineListenerCachetaggableTest..ok
            CacheTagging] functional/frontend/DoctrineTemplateCachetaggableTest..ok
            [sfCacheTagging] functional/frontend/PartialTagHelperTest............ok
            [sfCacheTagging] functional/frontend/actionWithLayoutTest............ok
            [sfCacheTagging] functional/frontend/actionWithoutLayoutTest.........ok
            [sfCacheTagging] functional/frontend/sfCacheTaggingPluginTest........ok
            [sfCacheTagging] functional/frontend/sfCacheTaggingToolkitTest.......ok
            [sfCacheTagging] functional/frontend/sfContentTagHandlerTest.........ok
            [sfCacheTagging] functional/frontend/sfTaggingCacheTest..............ok
            sfCacheTagging] functional/frontend/sfViewCacheTagManagerBridgeTest..ok
            [sfCacheTagging] functional/frontend/sfViewCacheTagManagerTest.......ok
            [sfCacheTagging] functional/notag/DoctrineListenerCachetaggableTest..ok
            [sfCacheTagging] functional/notag/sfCacheTaggingToolkitTest..........ok
            [sfCacheTagging] unit/DoctrineListenerCachetaggableTest..............ok
            [sfCacheTagging] unit/DoctrineTemplateCachetaggableTest..............ok
            [sfCacheTagging] unit/sfCacheTaggingToolkitTest......................ok
            [sfCacheTagging] unit/sfCallableArrayTest............................ok
            [sfCacheTagging] unit/sfContentTagHandlerTest........................ok
             All tests successful.
             Files=19, Tests=1278

  * Coverage report:

            ...ugins/sfCacheTaggingPlugin/lib/cache/sfViewCacheTagManagerBridge.class 100%
            ...plugins/sfCacheTaggingPlugin/lib/cache/sfViewCacheTagManager.class      75%
            ...plugins/sfCacheTaggingPlugin/lib/cache/sfTaggingCache.class             92%
            ...lugins/sfCacheTaggingPlugin/lib/cache/extra/sfSQLiteTaggingCache.class 100%
            ...plugins/sfCacheTaggingPlugin/lib/cache/extra/sfFileTaggingCache.class  100%
            ...plugins/sfCacheTaggingPlugin/lib/cache/sfContentTagHandler.class       100%
            ...plugins/sfCacheTaggingPlugin/lib/util/sfCallableArray.class            100%
            ...plugins/sfCacheTaggingPlugin/lib/util/sfCacheTaggingToolkit.class      100%
            ...ins/sfCacheTaggingPlugin/lib/util/sfTagNamespacedParameterHolder.class 100%
            ...plugins/sfCacheTaggingPlugin/lib/view/sfPartialTagView.class            92%
            ...plugins/sfCacheTaggingPlugin/lib/helper/CacheTagHelper                  95%
            ...plugins/sfCacheTaggingPlugin/lib/helper/PartialTagHelper               100%
            ...ugins/sfCacheTaggingPlugin/lib/doctrine/collection/Cachetaggable.class 100%
            ...plugins/sfCacheTaggingPlugin/lib/doctrine/listener/Cachetaggable.class  96%
            ...plugins/sfCacheTaggingPlugin/lib/doctrine/template/Cachetaggable.class  95%

            TOTAL COVERAGE:  91%


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