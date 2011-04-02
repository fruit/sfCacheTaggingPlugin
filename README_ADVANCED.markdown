## Advanced setup

1.  Create a new file ``/config/factories.yml`` (common for all applications)
    or edit application-level ``/apps/%application_name%/config/factories.yml`` file.

    Often, if you have back-end for managing data and front-end to print them out
    you should enable cache in both of them. That is because you edit/create/delete
    records in back-end, so object tags should be updated to invalidate front-end cache.

    I recommend you to create default ``factories.yml`` for all applications you have by
    creating a file ``/config/factories.yml`` (you could find explained
    and short working examples bellow).

    Symfony will check for this file and will load it as a default ``factories.yml``
    configuration for all applications you have in the project.

    This is ``/config/factories.yml`` content (you can copy & paste this code
    into your brand new created file) or merge this configuration with each application
    ``factories.yml`` file.

  **Explained example of file ``/config/factories.yml``**

        all:
          view_cache_manager:
            class: sfViewCacheTagManager

          view_cache:
            class: sfTaggingCache
            param:
              data:
                class: sfMemcacheTaggingCache   # Content will be stored in Memcache
                                                # Here you can switch to any other backend
                                                # (see Restrictions block for more info)
                param:
                  persistent: true
                  storeCacheInfo: true
                  host: localhost
                  port: 11211
                  lifetime: 86400           # default value is 1 day (in seconds)

              tags: ~                       # storage for tags (could be the same as
                                            # the cache storage)
                                            # if "tags" is NULL (~), it will
                                            # be the same as "data" (e.i. sfMemcacheTaggingCache)

              logger:
                class: sfFileCacheTagLogger   # to disable logger, set class to "sfNoCacheTagLogger"
                param:
                  # All given parameters are default
                  file:         %SF_LOG_DIR%/cache_%SF_ENVIRONMENT%.log
                  file_mode:    0640
                  dir_mode:     0750
                  time_format:  "%Y-%b-%d %T%z"   # e.g. 2010-Sep-01 15:20:58+0300
                  skip_chars:   ""
                  format:       "%char%"
                    # Available place-holders:
                    # %char%              - Operation char (see char explanation in sfCacheTagLogger::explainChar())
                    # %char_explanation%  - Operation explanation string
                    # %time%              - Time, when data/tag was accessed
                    # %key%               - Cache name or tag name with its version
                    # %microtime%         - Micro time timestamp when data/tag was accessed
                    # %EOL%               - Whether to append \n in the end of line
                    # (e.g. "%microtime% %char% (%char_explanation%) %key%%EOL%")
                    #
                    # Example: "%char% %microtime% %key%%EOL%"

  > **Restrictions**: Backend's class should be inherited from ``sfCache``
    class. Then, it should be implement ``sfTaggingCacheInterface``
    (due to a ``Doctrine`` cache engine compatibility).
    Also, it should support the caching of objects and/or arrays.

    Therefor, plugin comes with additional extended backend classes:

      - sfAPCTaggingCache
      - sfEAcceleratorTaggingCache
      - sfFileTaggingCache
      - sfMemcacheTaggingCache
      - sfSQLiteTaggingCache
      - sfXCacheTaggingCache

    And bonus one:

      - sfSQLitePDOTaggingCache (based on stand alone sfSQLitePDOCache)



1.  Add "Cachetaggable" behavior to each model, which you want to cache

  Two major setups to pay attention:
    * Model setup
      * When object tag will be invalidated
      * How object tag will stored
    * Relation setup
      * What will happen with related objects in case root-object is deleted or updated
      * Choosing cascading type (deleteTags, invalidateTags)

  Explained behavior setup, file ``/config/doctrine/schema.yml``:

        Article:
          tableName: articles
          actAs:
            Cachetaggable:

              # If you have more then 1 unique column, you could pass all of them
              # as array (tag name will be based on all of them)
              # (default: [], primary key auto-detection)
              uniqueColumn:    [id, is_visible]

              # cache tag will be based on 2 columns
              # (e.g. "Article:5:01", "Article:912:00")
              # matches the "uniqueColumn" column order
              # (default: "")
              uniqueKeyFormat: '%d-%02b'

              # Column name, where object version will be stored in table
              # (default: "object_version")
              versionColumn:    version_microtime


              # Option to skip object invalidation by changing listed columns
              # Useful for sf_guard_user.last_login or updated_at
              skipOnChange:
                - last_accessed


              # Invalidates or not object collection tag when any
              # record was updated (BC with v2.*)
              # Useful, when table contains rarely changed data (e.g. Countries, Currencies)
              # allowed values: true/false (default: false)
              invalidateCollectionVersionOnUpdate: false

              # Useful option when model contains columns like "is_visible", "is_active"
              # updates collection tag, if one of columns was updated.
              # will not work if "invalidateCollectionVersionOnUpdate" is set to "true"
              # will not work if one of columns are in "skipOnChange" list.
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
              # Because foreign key "onDelete" type is "SET NULL"
              cascade: [invalidateTags]
            Category:
              class: Category
              local: category_id
              foreign: id
              foreignAlias: Categories
              type: one
              foreignType: many
              # Cascading type chosen "deleteTags"
              # Because foreign key "onDelete" type is "CASCADE"
              cascade: [deleteTags]

        Culture:
          tableName: cultures
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

1.  How to customize ``sfCacheTaggingPlugin`` (file ``/config/app.yml``):

    All given below values is default.

        all:
          sfcachetaggingplugin:

            # Tag name delimiter
            # (default: ":")
            model_tag_name_separator: ":"

            # Version of precision
            # 0: without micro time, version length 10 digits
            # 5: with micro time part, version length 15 digits
            # allowed decimal numbers in range [0, 6]
            # (default: 5)
            microtime_precision: 5

            # Callable array
            # Example: [ClassName, StaticClassMethod]
            # useful when tag name should contains extra information
            # (e.g. Environment name, or application name)
            # (default: [])
            object_class_tag_name_provider: []

            # This class responses to save/fetch data and tags
            # from/to cache with custom serialization/de-serialization
            # (default: "CacheMetadata")
            metadata_class: CacheMetadata



## Hacks / Enhancements / Recommendations

  * Remember to enable Doctrine query cache in production:

    For ease of configuration (enable/disable) add following lines to ``./config/app.yml``:

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

    And plug in query cache:

        [php]
        class ProjectConfiguration extends sfProjectConfiguration
        {
          public function configureDoctrine (Doctrine_Manager $manager)
          {
            $doctrineQueryCache = sfConfig::get('app_doctrine_query_cache');

            if ($doctrineQueryCache)
            {
              list($class, $param) = array_values($doctrineQueryCache);
              $manager->setAttribute(Doctrine_Core::ATTR_QUERY_CACHE, new $class($param));
            }
          }
        }

  * All we want to make our application fast, so here goes some tips, how to speed up this plugin.

    One of solutions to create direct proxy methods to ``Doctrine_Template_Cachetaggable`` class.

    By extending ``sfDoctrineRecord`` class with build-in ``sfCachetaggableDoctrineRecord``
    we make frequently used methods as proxy (i.e. faster).

    Since v3.1.2 this is required setup for plugin users.

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

    And REMEMBER TO rebuild your models after this changes:

        ./symfony doctrine:build-model
