<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2013 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  $app = 'notag';

  include_once realpath(dirname(__FILE__) . '/../../bootstrap/functional.php');
  include_once sfConfig::get('sf_symfony_lib_dir') . '/vendor/lime/lime.php';

  $t = new lime_test();

  # check component.method_not_found

  include_once dirname(__FILE__) . '/../../fixtures/project/apps/notag/modules/blog_post/actions/actions.class.php';

  try
  {
    $e = new sfEvent(
      new blog_postActions(sfContext::getInstance(), 'blog_post', 'run'),
      'component.method_not_found',
      array('method' => 'callMe', 'arguments' => array(1, 2, 3))
    );

    $v = $configuration->getPluginConfiguration('sfCacheTaggingPlugin')->listenOnComponentMethodNotFoundEvent($e);

    $t->ok(null === $v);
    $t->ok(null === $e->getReturnValue());
  }
  catch (Exception $e)
  {
    $t->fail($e->getMessage());
  }

  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', true);

  try
  {
    $t->isa_ok($c = sfCacheTaggingToolkit::getTaggingCache(), 'sfTaggingCache');
    $t->isa_ok($c->getCache(), 'sfNoTaggingCache');
    $t->isa_ok($c->getLogger(), 'sfNoCacheTagLogger');

    $t->fail('No exceptions was thrown');
  }
  catch (sfConfigurationException $e)
  {
    $t->pass($e->getMessage());
  }

  try
  {
    $e = new sfEvent(
      new blog_postActions(sfContext::getInstance(), 'blog_post', 'run'),
      'component.method_not_found',
      array('method' => 'callMe', 'arguments' => array(1, 2, 3))
    );

    $v = $configuration->getPluginConfiguration('sfCacheTaggingPlugin')->listenOnComponentMethodNotFoundEvent($e);

    $t->ok(null === $v);
    $t->ok(null === $e->getReturnValue());
  }
  catch (Exception $e)
  {
    $t->pass($e->getMessage());
  }

  sfConfig::set('sf_cache', $optionSfCache);