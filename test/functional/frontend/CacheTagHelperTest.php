<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  require_once realpath(dirname(__FILE__) . '/../../../../../test/bootstrap/functional.php');

  require_once sfConfig::get('sf_symfony_lib_dir') . '/vendor/lime/lime.php';

  $cc = new sfCacheClearTask(sfContext::getInstance()->getEventDispatcher(), new sfFormatter());
  $cc->run();

  $sfViewCacheManager = sfContext::getInstance()->getViewCacheManager();

  sfContext::getInstance()->getConfiguration()->loadHelpers(array('CacheTag'));

  $t = new lime_test();

  $t->ok(! sfConfig::get('symfony.cache.started'), 'session is not started');
  $t->ok(! sfConfig::get('symfony.cache.current_name'), 'session name is not set');
  $t->ok(! sfConfig::get('symfony.cache.lifetime'), 'session lifetime is not set');

  sfConfig::set('sf_cache', false);

  $t->is(cache_tag('ffff'), null, 'on disabled "sf_cache" do not use cache_tag()');
  $t->is(cache_tag_save(), null, 'on disabled "sf_cache" do not use cache_tag_save()');

  sfConfig::set('sf_cache', true);

  try
  {
    cache_tag_save();

    $t->fail('could not run cache_tag_save() - session is not started');
  }
  catch (sfCacheException $e)
  {
    $t->pass('cached "sfCacheException" on executing cache_tag_save() without any started sessions');
  }

  $t->is(cache_tag('xoXoaSdvad'), false, 'on enabled "sf_cache" use cache - [start xoXoaSdvad]');


  $t->is(sfConfig::get('symfony.cache.started'), true, 'session is started');
  $t->is(sfConfig::get('symfony.cache.current_name'), 'xoXoaSdvad', 'session name is "xoXoaSdvad"');
  $t->is(sfConfig::get('symfony.cache.lifetime'), null, 'session lifetime is "null"');

  try
  {
    cache_tag('goo');

    $t->fail('run cache more then one time');
  }
  catch (sfCacheException $e)
  {
    $t->pass('cached "sfCacheException" on executing cache_tag() twice');
  }



  $tags = $sfViewCacheManager->getTags();

  # its removes permanently tags after function get_cache_tag_save is finished
  $content = get_cache_tag_save($newTags);

  $t->is($sfViewCacheManager->getTags(), array(), 'Tags are removed after function get_cache_tag_save() is successfully runned');

  print $content;

  $t->ok(false !== strpos($content, 'xoXoaSdvad'), 'cached content seams to be ok');


  $t->is(cache_tag('xoXoaSdvad'), true, 'Content "xoXoaSdvad" is cached');


  $t->is(cache_tag('FooBar', 360), false, 'on enabled "sf_cache" use cache - [start FooBar]');


  $t->is(sfConfig::get('symfony.cache.started'), true, 'session is started');
  $t->is(sfConfig::get('symfony.cache.current_name'), 'FooBar', 'session name is "FooBar"');
  $t->is(sfConfig::get('symfony.cache.lifetime'), 360, 'session lifetime is "360"');

  cache_tag_save();

  $t->ok(! sfConfig::get('symfony.cache.started'), 'session is not started');
  $t->ok(! sfConfig::get('symfony.cache.current_name'), 'session name is not set');
  $t->ok(! sfConfig::get('symfony.cache.lifetime'), 'session lifetime is not set');