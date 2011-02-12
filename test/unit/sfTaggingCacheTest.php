<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  require_once realpath(dirname(__FILE__) . '/../../../../test/bootstrap/unit.php');

  $t = new lime_test();

  # __constructor / initialize
  $tests = array(
    array(
      'options' => array(),
      'exceptionClass' => 'sfInitializationException',
    ),
    array(
      'options' => array(
        'data' => array('class' => 'sfCallable', 'param' => array()),
        'logger' => array('class' => 'sfNoLogger'),
      ),
      'exceptionClass' => 'sfInitializationException',
    ),
    array(
      'options' => array(
        'data' => array('class' => 'sfAPCTaggingCache', 'param' => array()),
        'tags' => array(),
        'logger' => array('class' => 'sfNoTaggingLogger'),
      ),
      'exceptionClass' => 'sfInitializationException',
    ),
    array(
      'options' => array(
        'data' => array('class' => 'sfAPCTaggingCache', 'param' => array()),
        'tags' => array('class' => 'sfCallable', 'param' => array()),
        'logger' => array('class' => 'sfNoLogger'),
      ),
      'exceptionClass' => 'sfInitializationException',
    ),
    array(
      'options' => array(
        'data' => array('class' => 'sfAPCTaggingCache', 'param' => array()),
        'tags' => array('class' => 'noSuchClassExists', 'param' => array()),
        'logger' => array('class' => 'sfNoLogger'),
      ),
      'exceptionClass' => 'sfInitializationException',
    ),
    array(
      'options' => array(
        'data' => array('class' => 'sfAPCTaggingCache', 'param' => array()),
        'tags' => array('param' => array()),
        'logger' => array('class' => 'sfNoLogger'),
      ),
      'exceptionClass' => 'sfInitializationException',
    ),
    array(
      'options' => array(
        'data' => array('class' => 'noExistingClassName'),
        'logger' => array('class' => 'sfNoLogger'),
      ),
      'exceptionClass' => 'sfInitializationException',
    ),
    array(
      'options' => array(
        'data' => array('class' => 'sfAPCTaggingCache', 'param' => array()),
      ),
      'exceptionClass' => 'sfInitializationException',
    ),
    array(
      'options' => array(
        'data' => array('class' => 'sfAPCTaggingCache', 'param' => array()),
        'logger' => array(),
      ),
      'exceptionClass' => 'sfInitializationException',
    ),

    array(
      'options' => array(
        'data' => array('class' => 'sfAPCTaggingCache', 'param' => array()),
        'logger' => array('class' => null),
      ),
      'exceptionClass' => 'sfInitializationException',
    ),
    array(
      'options' => array(
        'data' => array('class' => 'sfAPCTaggingCache', 'param' => array()),
        'logger' => array('class' => 'sfCallable', 'param' => array()),
      ),
      'exceptionClass' => 'sfInitializationException',
    ),
    array(
      'options' => array(
        'data' => array('class' => 'sfAPCTaggingCache', 'param' => array()),
        'logger' => array('class' => 'sfClassNotFound', 'param' => array()),
      ),
      'exceptionClass' => 'sfInitializationException',
    ),
    array(
      'options' => array(
        'data' => array('class' => 'sfAPCTaggingCache', 'param' => array()),
        'logger' => array('class' => 'sfFileCacheTagLogger', 'param' => array(
          'file' => sfConfig::get('sf_log_dir') . '/cache.log',
        )),
      ),
      'message' => '__constructor arguments is valid',
    ),
  );

  foreach ($tests as $test)
  {
    $options = $test['options'];
    $message = isset($test['message']) ? $test['message'] : '';
    $exceptionClassName = isset($test['exceptionClass'])
      ? $test['exceptionClass']
      : false;

    try
    {
      $c = new sfTaggingCache($options);

      $t->ok(! $exceptionClassName, $message);
    }
    catch (Exception $e)
    {
      $t->ok($exceptionClassName && $e instanceof $exceptionClassName, $e->getMessage());
    }
  }

  # getDataCache/getTagsCache

  $differentCacheEngines = array(
    'data' => array(
      'class' => 'sfAPCTaggingCache',
      'param' => array(
        'cache_dir' => sfConfig::get('sf_cache_dir') . '/test',
        'automatic_cleaning_factor' => 0,
      )
    ),
    'tags' => array(
      'class' => 'sfSQLitePDOTaggingCache',
      'param' => array(
        'dsn' => 'sqlite::memory:',
        'automatic_cleaning_factor' => 0,
      )
    ),
    'logger' => array(
      'class' => 'sfFileCacheTagLogger',
      'param' => array(
        'format' => '%microtime% %char% %key%%EOL%',
        'file' => sfConfig::get('sf_log_dir') . '/cache.log',
      )
    )
  );

  $similarCacheEngines = $differentCacheEngines;
  $similarCacheEngines['tags'] = null;
  
  $c = new sfTaggingCache($differentCacheEngines);
  $t->isa_ok($c->getDataCache(), $differentCacheEngines['data']['class'], 'getDataCache returns right object');
  $t->isa_ok($c->getTagsCache(), $differentCacheEngines['tags']['class'], 'getTagsCache return right object');

  $c = new sfTaggingCache($similarCacheEngines);
  $t->isa_ok($c->getDataCache(), $similarCacheEngines['data']['class'], 'getDataCache returns right object ');
  $t->isa_ok($c->getTagsCache(), $similarCacheEngines['data']['class'], 'getTagsCache return right object ');

  $c->initialize($similarCacheEngines);

  $c->clean();

  # remove
  $c->set('nickname', 'Fruit');
  $t->ok($c->has('nickname'));
  $t->is($c->remove('nickname'), true, 'remove existing cache');
  $t->is($c->remove('nickname'), false, 'removing already removed cache');
  $t->is($c->remove('Utopia'), false, 'remove never existing cache');

  # has
  $c->set('nickname', 'Fruit');
  $t->ok($c->has('nickname'), "'nickname' exists");
  $t->ok(! $c->has('surname'), "'surname' does not exists");
  $c->remove('nickname');

  # getTimeout
  $c->set('nickname', 'Fruit', 300, array('A' => 12, 'C' => 94));
  $t->ok(0 < $c->getTimeout('nickname'), 'timeout more then 0');
  $t->ok($c->getTimeout('nickname') - time() <= 300, 'getTimeout <= 300');
  $t->is($c->has('nickname'), true, "cache 'nickname' not expired");
  $c->remove('nickname');

  $c->set('nickname', 'Fruit', 1);
  sleep(2);
  $t->is($c->getTimeout('nickname'), 0, 'getTimeout is now "0"');
  $t->is($c->has('nickname'), false, "has() cache 'nickname' expired TTL was 1 sec");
  $t->is($c->get('nickname'), null, "get() cache 'nickname' expired TTL was 1 sec");
  $c->remove('nickname');

  # hasTag
  $c->set('Woodpark', 'Street 12/31 5', 1000, array('A' => 27, 'C' => 59));
  $t->is($c->hasTag('A'), true);
  $t->is($c->hasTag('C'), true);
  $t->is($c->hasTag('B'), false);
  $c->remove('Woodpark');

  # getTags
  $c->set('MyAnimals', 'Cat & Crocodile', null, array('A' => 91, 'C' => 26));
  $t->is($c->getTags('MyAnimals'), array('A' => 91, 'C' => 26));
  $t->is($c->getTags('MyBirds'), array());
  $c->remove('MyAnimals');

  # removePattern
  $c->set('CityA', 'City A');
  $c->set('CityB', 'City B');
  $c->removePattern('City*');


  # getLastModified
  $now = time() - 5;
  $c->set('UpdateNowKey', 'NY');
  $t->ok($now <= $c->getLastModified('UpdateNowKey'), "{$now} / {$c->getLastModified('UpdateNowKey')}");
  $c->remove('UpdateNowKey');

  # addTagsToCache
  # 1. rewrite

  apc_clear_cache('user');
  
  $c->set('Catchphrase', '"I know nothing."', 500, array('CP_01' => 9237722, 'CP' => 9237722));
  
  $t->is($c->getTags('Catchphrase'), array('CP_01' => 9237722, 'CP' => 9237722));
  
  $c->remove('Catchphrase');

  # 2. append
  $c->set('Catchphrase', '"I know nothing."', null, array('CP_01' => 9237722, 'CP' => 9237722));
  $c->addTagsToCache('Catchphrase', array('GF_3' => 781721, 'GF_1' => 8126761));
  $t->is($c->getTags('Catchphrase'), array(
    'CP_01' => 9237722, 'CP' => 9237722, 'GF_3' => 781721, 'GF_1' => 8126761
  ));
  $c->remove('Catchphrase');

  # 3. initialized as empty and and nothing
  $c->set('Catchphrase', '"I know nothing."');
  $c->addTagsToCache('Catchphrase', array());
  $t->is($c->getTags('Catchphrase'), array());
  $c->remove('Catchphrase');

  # 4. add to unexisting cache
  $t->is($c->addTagsToCache('NewerSawKey', array('FF' => 2019821)), false);
  $t->is($c->has('NewerSawKey'), false);


  # set
  $content = 'My cache content';

  $t->is($c->set('GoogleNews', 'Google Inc. Foundation Birthday'), true);
  $t->ok(! $c->isLocked('GoogleNews'));
  $c->lock('GoogleNews', 10);
  $t->ok($c->isLocked('GoogleNews'));

  $t->is($c->set('GoogleNews', 'My new content'), false, 'Is locked');
  $t->ok($c->unlock('GoogleNews'));
  $t->ok(! $c->unlock('GoogleNews'));
  $t->ok(! $c->isLocked('GoogleNews'));

  $c->remove('GoogleNews');

  # get
  $c->clean();
  $t->is($c->get('SomeMisticalTag'), null);
  $t->is($c->get('SomeMisticalTag', false), false);
  
  $c->set('MarkdownText', '**Hi, John!**', 4500, array('MD_31' => 123456789012345, 'MD' => 93));
  $t->is($c->get('MarkdownText'), '**Hi, John!**');

  $c->setTag('MD_31', 123456789012346);

  $t->is($c->get('MarkdownText'), null, 'Tag MD_31 is updated');
  $c->remove('MarkdownText');

  $t->ok(
    $c->set('AboutUs', '**Hi, John!**', 4500, array('MD_31' => '123456789012345', 'MD' => 93))
  );
  
  $t->ok($c->setTag('MD_31', '123456789012346'));
  
  $t->ok($c->lock('AboutUs'));
  $t->ok($c->isLocked('AboutUs'));

  $t->is($c->get('AboutUs'), '**Hi, John!**', 'Tag MD_31 is updated, but cache is locked');


  # clean

  $c->clean(sfCache::ALL);

  $c = new sfTaggingCache($differentCacheEngines);

  $t->ok($c->set('file', 'robots.txt', 1000, array('X_1' => 928, 'X_3' => '187')));
  $t->is($c->getTags('file'), array('X_1' => 928, 'X_3' => '187'));

  $t->is($c->getTag('X_1'), 928);
  $t->is($c->getTag('X_3'), '187');

  $t->is($c->get('file'), 'robots.txt', 'File is "robots.txt"');

  $c->clean(sfCache::ALL);

  $t->is($c->get('file'), null);
  $t->is($c->getTag('X_1'), null);
  $t->is($c->getTag('X_3'), null);
  
  # getContentTagHandler
  $t->isa_ok($c->getContentTagHandler(), 'sfContentTagHandler');

  # getCacheKeys

  $c->clean(sfCache::ALL);

  $c->set('CityA', 'City A');
  $c->set('CityB', 'City B');

  $keys = $c->getCacheKeys();
  sort($keys);
  
  $t->is(gettype($keys), 'array');
  $t->is($keys, array('CityA', 'CityB'));

  # trying to use multi get cache invalidated check

  $c->set('MultiGet', 'Some data.', 500, array('A_1' => '223344', 'A_2' => '115577', 'A' => '992211'));
  $c->deleteTag('A_1');

  $t->is($c->get('MultiGet'), null, 'Checking via multi get');