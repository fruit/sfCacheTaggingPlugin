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

  $c = new sfNoTaggingCache(array());

  $c->initialize(array());

  $t->is($c->setTags(array('name' =>'123', 'name2' => 25125252), 3600), true);
  $t->is($c->hasTag('cccc'), false);
  $t->is($c->addTagsToCache('somename', array()), true);
  $t->is($c->setTag('name', '123'), true);
  $t->is($c->getTag('name'), false);

  $t->is($c->getTags('my-name'), array());
  $t->is($c->deleteTag('name'), true);
  $t->is($c->get('name', false), false);
  $t->is($c->get('name'), null);
  $t->is($c->has('name'), false);
  $t->is($c->set('name', 12312424, 200), true);
  $t->is($c->remove('name'), true);
  $t->is($c->removePattern('*'), true);
  $t->is($c->clean(sfCache::ALL), true);
  $t->is($c->clean(), true);
  $t->is($c->getLastModified('name'), 0);
  $t->is($c->getTimeout('name'), 0);

  $t->isa_ok($c->getTagsCache(), 'sfNoCache');
