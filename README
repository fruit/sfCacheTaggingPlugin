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

## Setup ##

1.  Check ``sfCacheTaggingPlugin`` plugin is enabled (``/config/ProjectConfiguration.class.php``)

        [php]
        class ProjectConfiguration extends sfProjectConfiguration
        {
          public function setup()
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
              locker:
                class: sfAPCCache           # Locks will be stored in APC
                                            # Here you can switch to any other backend sf*Cache
                                            # (see Restrictions block for more info)
                param: {}

          view_cache_manager:
            class: sfViewCacheTagManager          # Extended sfViewCacheManager class
            param:
              cache_key_use_vary_headers: true
              cache_key_use_host_name:    true

    > **Easter eggs**: If you remove "``all_view_cache_param_locker``" section,
      locker will be the same as section "``all_view_cache_param_cache``".

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
                #  uniqueColumn: id               # you can customize unique column name (default is "id")
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

## Using ##

*    ### Native use

 * not recommended - use Doctrine_Cache_* to store Doctrine_Record and Doctrine_Collection objects

            [php]
            # Somewhere in the frontend, you need to print out latest posts

            $posts = Doctrine::getTable('BlogPost')
              ->createQuery()
              ->orderBy('id DESC')
              ->limit(3)
              ->execute();

            /* @var $tagger sfViewCacheTagManager */
            $tagger = $this->getContext()->getViewCacheManager();

            # write data to the cache ($posts is instance of the Doctrine_Collection_Cachetaggable)
            $tagger->set('my_posts', $posts, 60 * 60 * 24 * 30/* 1 month */, $posts->getTags());

            # fetch latest post to edit it
            $post = $posts->getFirst();

            # prints something like "126070596212512"
            print $post->getObjectVersion();

            $post->setTitle('How to use sfCacheTaggingPlugin');

            # save and update/upgrade version of the tag
            $post->save();

            # prints something like "126072290862231" (new version of the tag)
            print $post->getObjectVersion();

            # will return null
            # $post object was updated, so, all $posts in cache "my_posts” is invalidated automatically)
            if ($data = $tagger->get('my_posts'))
            {
              # this block will not be executed
            }

            # save new data to the cache
            $tagger->set('my_posts', $posts, null, $posts->getTags());

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

            $tagger->set('my_posts', $posts, null, $posts->getTags());

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
            $this->getContext()->getViewCacheManager()->getTaggingCache()->setTag('moon', time(), 60 * 60 * 24 * 7 /*1 week*/);

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

                $this->setUserTags($posts->getTags());
                # or equivalent
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
                $this->setPageTags($this->car->getTags());

                # or shorter
                # $this->setPageTags($this->car);
              }
            }

  * Of cause you have to enable the cache for that action in ``config/cache.yml``:

            [yml]
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
                $this->setActionTags($this->car->getTags());

                # or shorter
                # $this->setActionTags($this->car);
              }
            }

  * You have to disable "with_layout" and enable the cache for that action in ``config/cache.yml``:

            [yml]
            show:
              with_layout: false
              enabled:     true
              lifetime:    360


## Limitations / Peculiarities ##

  * In case, when model has translations (I18n behavior), it is enough to add
    "``actAs: Cachetaggable``" to the model. I18n behavior should be free from ``Cachetaggable``
    behavior.

## Unit/functional test ##

  * Test report (tests: 1049):

            [sfCacheTagging] functional/frontend/CacheTagHelperTest..............ok
            CacheTagging] functional/frontend/DoctrineListenerCachetaggableTest..ok
            CacheTagging] functional/frontend/DoctrineTemplateCachetaggableTest..ok
            [sfCacheTagging] functional/frontend/PartialTagHelperTest............ok
            [sfCacheTagging] functional/frontend/actionWithLayoutTest............ok
            [sfCacheTagging] functional/frontend/actionWithoutLayoutTest.........ok
            [sfCacheTagging] functional/frontend/sfCacheTaggingPluginTest........ok
            [sfCacheTagging] functional/frontend/sfCacheTaggingToolkitTest.......ok
            [sfCacheTagging] functional/frontend/sfViewCacheTagManagerTest.......ok
            [sfCacheTagging] functional/notag/DoctrineListenerCachetaggableTest..ok
            [sfCacheTagging] unit/DoctrineListenerCachetaggableTest..............ok
            [sfCacheTagging] unit/DoctrineTemplateCachetaggableTest..............ok
            [sfCacheTagging] unit/sfCacheTaggingToolkitTest......................ok
             All tests successful.
             Files=13, Tests=1049

  * Coverage report (total: 84%):

            >> coverage  running /www/sfpro/dev...tenerCachetaggableTest.php (1/13)
            >> coverage  running /www/sfpro/dev...plateCachetaggableTest.php (2/13)
            >> coverage  running /www/sfpro/dev...acheTaggingToolkitTest.php (3/13)
            >> coverage  running /www/sfpro/dev...end/CacheTagHelperTest.php (4/13)
            >> coverage  running /www/sfpro/dev...CacheTaggingPluginTest.php (5/13)
            >> coverage  running /www/sfpro/dev...iewCacheTagManagerTest.php (6/13)
            >> coverage  running /www/sfpro/dev...tenerCachetaggableTest.php (7/13)
            >> coverage  running /www/sfpro/dev...d/PartialTagHelperTest.php (8/13)
            >> coverage  running /www/sfpro/dev...plateCachetaggableTest.php (9/13)
            >> coverage  running /www/sfpro/dev.../actionWithLayoutTest.php (10/13)
            >> coverage  running /www/sfpro/dev...tionWithoutLayoutTest.php (11/13)
            >> coverage  running /www/sfpro/dev...cheTaggingToolkitTest.php (12/13)
            >> coverage  running /www/sfpro/dev...enerCachetaggableTest.php (13/13)
            plugins/sfCacheTaggingPlugin/lib/cache/sfViewCacheTagManager.class      75%
            plugins/sfCacheTaggingPlugin/lib/cache/sfTaggingCache.class                 73%
            lugins/sfCacheTaggingPlugin/lib/cache/extra/sfSQLiteTaggingCache.class 100%
            plugins/sfCacheTaggingPlugin/lib/cache/extra/sfFileTaggingCache.class  100%
            plugins/sfCacheTaggingPlugin/lib/util/sfCacheTaggingToolkit.class      100%
            plugins/sfCacheTaggingPlugin/lib/view/sfPartialTagView.class            92%
            plugins/sfCacheTaggingPlugin/lib/helper/CacheTagHelper                  95%
            plugins/sfCacheTaggingPlugin/lib/helper/PartialTagHelper               100%
            ugins/sfCacheTaggingPlugin/lib/doctrine/collection/Cachetaggable.class 100%
            plugins/sfCacheTaggingPlugin/lib/doctrine/listener/Cachetaggable.class  95%
            plugins/sfCacheTaggingPlugin/lib/doctrine/template/Cachetaggable.class  96%
            TOTAL COVERAGE:  84%

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