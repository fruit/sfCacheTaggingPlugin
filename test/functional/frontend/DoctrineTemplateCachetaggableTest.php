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

  $sfContext = sfContext::getInstance();
  $cacheManager = $sfContext->getViewCacheManager();

  $t = new lime_test();

  BookTable::getInstance()->createQuery()->delete()->execute();
  RepositoryTable::getInstance()->createQuery()->delete()->execute();
  
  $article = new Book();
  $article->setLang('fr');
  $article->setSlug('foobarbaz');
  $article->save();

  $t->isa_ok($article->setObjectVersion(213213213213), 'Book', 'setObjectVersion() returns self object');

  $t->is($article->getTagName(), 'Book_fr-foobarbaz', 'Multy unique column tables are compatible with tag names');