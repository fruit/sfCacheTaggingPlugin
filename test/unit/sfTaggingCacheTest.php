<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
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
        'cache' => array('class' => 'sfCallable', 'param' => array()),
        'logger' => array('class' => 'sfNoLogger'),
      ),
      'exceptionClass' => 'sfInitializationException',
    ),
    array(
      'options' => array(
        'cache' => array('class' => 'sfAPCCache', 'param' => array()),
        'locker' => array(),
        'logger' => array('class' => 'sfNoLogger'),
      ),
      'exceptionClass' => 'sfInitializationException',
    ),
    array(
      'options' => array(
        'cache' => array('class' => 'sfAPCCache', 'param' => array()),
        'locker' => array('class' => 'sfCallable', 'param' => array()),
        'logger' => array('class' => 'sfNoLogger'),
      ),
      'exceptionClass' => 'sfInitializationException',
    ),
    array(
      'options' => array(
        'cache' => array('class' => 'sfAPCCache', 'param' => array()),
        'locker' => array('class' => 'noSuchClassExists', 'param' => array()),
        'logger' => array('class' => 'sfNoLogger'),
      ),
      'exceptionClass' => 'sfInitializationException',
    ),
    array(
      'options' => array(
        'cache' => array('class' => 'noExistingClassName'),
        'logger' => array('class' => 'sfNoLogger'),
      ),
      'exceptionClass' => 'sfInitializationException',
    ),
    array(
      'options' => array(
        'cache' => array('class' => 'sfAPCCache', 'param' => array()),
      ),
      'exceptionClass' => 'sfInitializationException',
    ),
    array(
      'options' => array(
        'cache' => array('class' => 'sfAPCCache', 'param' => array()),
        'logger' => array(),
      ),
      'exceptionClass' => 'sfInitializationException',
    ),

    array(
      'options' => array(
        'cache' => array('class' => 'sfAPCCache', 'param' => array()),
        'logger' => array('class' => null),
      ),
      'exceptionClass' => 'sfInitializationException',
    ),
    array(
      'options' => array(
        'cache' => array('class' => 'sfAPCCache', 'param' => array()),
        'logger' => array('class' => 'sfCallable', 'param' => array()),
      ),
      'exceptionClass' => 'sfInitializationException',
    ),
    array(
      'options' => array(
        'cache' => array('class' => 'sfAPCCache', 'param' => array()),
        'logger' => array('class' => 'sfClassNotFound', 'param' => array()),
      ),
      'exceptionClass' => 'sfInitializationException',
    ),
    array(
      'options' => array(
        'cache' => array('class' => 'sfAPCCache', 'param' => array()),
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

  # getDataCache/getLockerCache

  $differentCacheEngines = array(
    'cache' => array(
      'class' => 'sfFileTaggingCache',
      'param' => array(
        'cache_dir' => sfConfig::get('sf_cache_dir') . '/test',
      )
    ),
    'locker' => array(
      'class' => 'sfSQLiteTaggingCache',
      'param' => array('database' => ':memory:')
    ),
    'logger' => array(
      'class' => 'sfFileCacheTagLogger',
      'param' => array(
        'file' => sfConfig::get('sf_log_dir') . '/cache.log',
      )
    )
  );

  $similarCacheEngines = $differentCacheEngines;
  $similarCacheEngines['locker'] = null;
  
  $c = new sfTaggingCache($differentCacheEngines);
  $t->isa_ok($c->getDataCache(), 'sfFileTaggingCache', 'getDataCache returns object sfFileTaggingCache');
  $t->isa_ok($c->getLockerCache(), 'sfSQLiteTaggingCache', 'getLockerCache return object sfSQLiteTaggingCache');

  $c = new sfTaggingCache($similarCacheEngines);
  $t->isa_ok($c->getDataCache(), 'sfFileTaggingCache', 'getDataCache returns object sfFileTaggingCache');
  $t->isa_ok($c->getLockerCache(), 'sfFileTaggingCache', 'getLockerCache return object sfFileTaggingCache');

  # adapter tests:

  $similarCacheEngines['cache'] = array('class' => 'sfAPCCache', 'param' => array());
//  $similarCacheEngines['cache']['class'] = 'sfAPCCache';

  $c->initialize($similarCacheEngines);

  # remove
  $c->set('nickname', 'Fruit');
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
  $c->set('Woodpark', 'Street 12/31 5', null, array('A' => 27, 'C' => 59));
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
  $c->set('Catchphrase', '"I know nothing."', null, array('CP_01' => 9237722, 'CP' => 9237722));
  $c->addTagsToCache('Catchphrase', array('GF_3' => 781721, 'GF_1' => 8126761), false);
  $t->is($c->getTags('Catchphrase'), array('GF_3' => 781721, 'GF_1' => 8126761));
  $c->remove('Catchphrase');

  # 2. append
  $c->set('Catchphrase', '"I know nothing."', null, array('CP_01' => 9237722, 'CP' => 9237722));
  $c->addTagsToCache('Catchphrase', array('GF_3' => 781721, 'GF_1' => 8126761), true);
  $t->is($c->getTags('Catchphrase'), array(
    'CP_01' => 9237722, 'CP' => 9237722, 'GF_3' => 781721, 'GF_1' => 8126761
  ));
  $c->remove('Catchphrase');
  
  # 3. initialized as empty and and nothing
  $c->set('Catchphrase', '"I know nothing."');
  $c->addTagsToCache('Catchphrase', array(), true);
  $t->is($c->getTags('Catchphrase'), array());
  $c->remove('Catchphrase');

  # 4. add to unexisting cache
  $t->is($c->addTagsToCache('NewerSawKey', array('FF' => 2019821), true), false);
  $t->is($c->has('NewerSawKey'), false);


  # set
  $content = 'My cache content';

  $t->is($c->set('GoogleNews', 'Google Inc. Foundation Birthday'), true);

  $c->lock('GoogleNews', 10);
  $t->is($c->set('GoogleNews', 'My new content'), false, 'Is locked');
  $c->unlock('GoogleNews');
  $c->remove('GoogleNews');

  # get
  $t->is($c->get('SomeMisticalTag'), null);
  $t->is($c->get('SomeMisticalTag', false), false);
  
  $c->set('MarkdownText', '**Hi, John!**', 4500, array('MD_31' => 123456789012345, 'MD' => 93));
  $t->is($c->get('MarkdownText'), '**Hi, John!**');
  $c->remove('MarkdownText');