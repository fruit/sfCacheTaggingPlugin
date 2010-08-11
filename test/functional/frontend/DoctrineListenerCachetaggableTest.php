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

  $sfTagger = $cacheManager->getTaggingCache();
  
  $t = new lime_test();

  $connection = Doctrine::getConnectionByTableName('BlogPost');
  $connection->beginTransaction();

  BookTable::getInstance()->createQuery()->delete()->execute();
  FoodTable::getInstance()->createQuery()->delete()->execute();

  $book = new Book();
  $book->setLang('fr');
  $book->setSlug('foobarbaz');
  $book->save();

  $version = $book->getObjectVersion();
  $id = $book->getId();

  $book = BookTable::getInstance()->findOneById($id);

  $t->is($version, $book->getObjectVersion(), 'object version not changed since last save()');

  $book->save();

  $book = BookTable::getInstance()->findOneById($id);

  $t->is($version, $book->getObjectVersion(), 'object not modified, but saved - object version not updated');

  $book->setSlug('cccc');
  $book->save();

  $book = BookTable::getInstance()->findOneById($id);

  $t->isnt($version, $book->getObjectVersion(), 'object version updated');
  $t->is('cccc', $book->getSlug(), 'updated field "slug"');


  BlogPostTable::getInstance()->createQuery()->delete()->execute();

  $post = new BlogPost();
  $post->fromArray(array('title' => 'Caption_1', 'slug' => 'caption-one'));
  $post->save();

  $captionOneTagName = $post->getTagName();
  $captionOneVersion = $post->getObjectVersion();

  $t->ok($sfTagger->hasTag($captionOneTagName), 'Object tag exists in cache');
  $t->ok($sfTagger->hasTag(get_class($post)), 'Object\'s collection tag exists in cache');
  $t->is($captionOneVersion, $sfTagger->getTag($captionOneTagName), 'Object version matched with version associated with tag name in cache');

  $post->delete();

  $t->ok(! $sfTagger->hasTag($captionOneTagName), 'Object\'s tag name was removed from the cache');
  $t->ok($sfTagger->hasTag(get_class($post)), 'Object\'s collection tag still in cache');
  $t->is($captionOneVersion, $sfTagger->getTag(get_class($post)), 'Object\'s collection tag is not updated');

  $post = new BlogPost();
  $post->fromArray(array('title' => 'Caption_2', 'slug' => 'caption-two'));
  $post->save();

  $collectionTagVersion = $sfTagger->getTag(get_class($post));

  $post->setSlug('caption-two-point-one');
  $post->save();

  $t->isnt($collectionTagVersion, $sfTagger->getTag(get_class($post)), 'Object\'s collection tag was updated');

  $newCollectionTagVersion = $sfTagger->getTag(get_class($post));

  $post = new BlogPost();
  $post->fromArray(array('title' => 'Caption_3', 'slug' => 'caption-three'));
  $post->save();

  $t->isnt($newCollectionTagVersion, $sfTagger->getTag(get_class($post)), 'Collection tag is updated');
  
  BlogPostTable::getInstance()->createQuery()->delete()->execute();

  $posts = new Doctrine_Collection(BlogPostTable::getInstance());

  $newPostVersions = range(1, 6);


  foreach ($newPostVersions as $version)
  {
    $post = new BlogPost();
    $post->setTitle(sprintf('Content_%d', $version));
    $posts->add($post);
  }

  $posts->save();

  $tagNamesToDelete = array();

  foreach ($posts as $post)
  {
    if (1 == $post->getId() % 2)
    {
      $tagNamesToDelete[] = $post->getTagName();
    }
  }

  $collectionVersion = $sfTagger->getTag('BlogPost');

  $t->is(count($newPostVersions), BlogPostTable::getInstance()->count(), 'Added expected new post objects');

  $t->is(
    BlogPostTable::getInstance()->createQuery()->update()->set('slug', 'UPPER(title)')->where('id MOD 2 = 0')->execute(),
    count($newPostVersions) / 2,
    'Updated records count matched with expected'
  );

  $t->isnt($collectionVersion, $sfTagger->getTag('BlogPost'), 'Collection tag after DQL update is updated');

  $collectionVersion = $sfTagger->getTag('BlogPost');

  $t->is(
    BlogPostTable::getInstance()->createQuery()->delete()->where('id MOD 2 = 1')->execute(),
    count($newPostVersions) / 2,
    'Deleted records count matched with expected'
  );

  $t->is($collectionVersion, $sfTagger->getTag('BlogPost'), 'Collection tag after DQL delete is not updated');

  foreach ($tagNamesToDelete as $removedTagName)
  {
    $t->is($sfTagger->hasTag($removedTagName), false, sprintf('Tag "%s" is deleted', $removedTagName));
  }

  # testing model with SoftDelete and Cachetagging behavior
  $food = new Food();
  $food->setTitle('Bananas');
  $food->save();

  $bananasTagName = $food->getTagName();
  $t->ok($sfTagger->hasTag($bananasTagName), 'Bananas (Food object) tag name exists in cache');

  $t->ok($food->delete(), 'Food is "deleted" (really - NOT)');

  $t->ok(! $sfTagger->hasTag($bananasTagName), 'After SoftDelete "deletes" objects - tag cache is removed too');

  $connection->rollback();

  $univeristies = UniversityTable::getInstance()->findAll();

  try
  {
    $univeristies->getTags();

    $t->pass('Running getTags() on NON-Cachetaggable model');
  }
  catch (LogicException $e)
  {
    $t->pass($e->getMessage());
  }
