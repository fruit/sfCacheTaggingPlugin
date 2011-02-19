<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  require_once realpath(dirname(__FILE__) . '/../../../../../test/bootstrap/functional.php');
  require_once sfConfig::get('sf_symfony_lib_dir') . '/vendor/lime/lime.php';

  $separator = sfCacheTaggingToolkit::getModelTagNameSeparator();

  $sfContext = sfContext::getInstance();
  $cacheManager = $sfContext->getViewCacheManager();

  $sfTagger = $cacheManager->getTaggingCache();
  
  $t = new lime_test();

  $connection = Doctrine::getConnectionByTableName('BlogPost');
  $connection->beginTransaction();

  BookTable::getInstance()->createQuery()->delete()->execute();
  FoodTable::getInstance()->createQuery()->delete()->execute();

  $post = new Book();
  $post->setLang('fr');
  $post->setSlug('foobarbaz');
  $post->save();

  $version = $post->obtainObjectVersion();
  $id = $post->getId();

  $post = BookTable::getInstance()->findOneById($id);

  $t->is($version, $post->obtainObjectVersion(), 'object version not changed since last save()');

  $post->save();

  $post = BookTable::getInstance()->findOneById($id);

  $t->is($version, $post->obtainObjectVersion(), 'object not modified, but saved - object version not updated');

  $post->setSlug('cccc');
  $post->save();

  $post = BookTable::getInstance()->findOneById($id);

  $t->isnt($version, $post->obtainObjectVersion(), 'object version updated');
  $t->is('cccc', $post->getSlug(), 'updated field "slug"');


  BlogPostTable::getInstance()->createQuery()->delete()->execute();

  $post = new BlogPost();
  $post->fromArray(array('title' => 'Caption_1', 'slug' => 'caption-one'));
  $post->save();

  $captionOneTagName = $post->obtainTagName();
  $captionOneVersion = $post->obtainObjectVersion();

  $t->ok($sfTagger->hasTag($captionOneTagName), 'Object tag exists in cache');
  $t->ok($sfTagger->hasTag(get_class($post)), 'Object\'s collection tag exists in cache');
  $t->is($captionOneVersion, $sfTagger->getTag($captionOneTagName), 'Object version matched with version associated with tag name in cache');

  $post->delete();

  $t->ok(! $sfTagger->hasTag($captionOneTagName), 'Object\'s tag name was removed from the cache');
  $t->ok($sfTagger->hasTag(get_class($post)), 'Object\'s collection tag still in cache');
  $t->cmp_ok($captionOneVersion, '<', $sfTagger->getTag(get_class($post)), 'Object\'s collection tag changed (coz deleted $post) and new collection verions is newer');

  $post = new BlogPost();
  $post->fromArray(array('title' => 'Caption_2', 'slug' => 'caption-two'));
  $post->save();

  $collectionTagVersion = $sfTagger->getTag(get_class($post));

  $post->setSlug('caption-two-point-one');
  $post->save();

  $t->is($collectionTagVersion, $sfTagger->getTag(get_class($post)), 'Object\'s collection tag stays unchanged');

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
      $tagNamesToDelete[] = $post->obtainTagName();
    }
  }

  $collectionVersion = $sfTagger->getTag('BlogPost');

  $t->is(count($newPostVersions), BlogPostTable::getInstance()->count(), 'Added expected new post objects');

  $t->is(
    BlogPostTable::getInstance()->createQuery()->update()->set('slug', 'UPPER(title)')->where('id MOD 2 = 0')->execute(),
    count($newPostVersions) / 2,
    'Updated records count matched with expected'
  );

  $t->is($collectionVersion, $sfTagger->getTag('BlogPost'), 'Collection tag after DQL update not changed');

  $collectionVersion = $sfTagger->getTag('BlogPost');

  $t->is(
    BlogPostTable::getInstance()->createQuery()->delete()->where('id MOD 2 = 1')->execute(),
    count($newPostVersions) / 2,
    'Deleted records count matched with expected'
  );

  $t->cmp_ok($collectionVersion, '<', $sfTagger->getTag('BlogPost'), 'Collection tag after DQL delete is increased');

  foreach ($tagNamesToDelete as $removedTagName)
  {
    $t->is($sfTagger->hasTag($removedTagName), false, sprintf('Tag "%s" is deleted', $removedTagName));
  }

  # testing model with SoftDelete and Cachetagging behavior
  $food = new Food();
  $food->setTitle('Bananas');
  $food->save();

  $bananasTagName = $food->obtainTagName();
  $t->ok($sfTagger->hasTag($bananasTagName), 'Bananas (Food object) tag name exists in cache');

  $t->ok($food->delete(), 'Food is "deleted" (really - NOT)');

  $t->ok(! $sfTagger->hasTag($bananasTagName), 'After SoftDelete "deletes" objects - tag cache is removed too');

  # postSave
  $post = new BlogPost();
  $post->save();
  $id = $post->getId();
  $version = $post->obtainObjectVersion();

  $post->free();

  $post = BlogPostTable::getInstance()->find($id);
  $post->save();

  $t->is($version, $post->obtainObjectVersion(), 'Tag is not updated');

  # preDqlUpdate

  $blackSwan = new Book();
  $blackSwan->setSlug('black-swan');
  $blackSwan->setLang('en');
  $blackSwan->save();

  $blackSwanId = $blackSwan->getId();
  $blackSwanVersion = $blackSwan->obtainObjectVersion();
  $blackSwanCollectionVersion = $blackSwan->obtainCollectionVersion();
  
  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', false);

  BookTable::getInstance()
    ->createQuery()
    ->update()
    ->set('slug', '?',  'my-slug-123923')
    ->addWhere('id = ?', $blackSwanId)
    ->execute();


  $post = BookTable::getInstance()->find($blackSwanId);

  $t->is(
    $post->obtainObjectVersion(),
    $blackSwanVersion,
    'Update DQL does not rewrites object version when cache is disabled'
  );

  $t->is(
    $sfTagger->getTag('Book'),
    $blackSwanCollectionVersion,
    'Object collection stays unchanged'
  );

  sfConfig::set('sf_cache', true);

  $bookCollectionVersion = $sfTagger->getTag(
    sfCacheTaggingToolkit::getBaseClassName(BookTable::getInstance()->getClassnameToReturn())
  );

  BookTable::getInstance()
    ->createQuery()
    ->update()
    ->set('slug', '?',  'my-slug-1111')
    ->addWhere('id = ?', $blackSwanId)
    ->execute();

  $post = BookTable::getInstance()->find($blackSwanId);

  $t->isnt(
    $post->obtainObjectVersion(),
    $blackSwanVersion,
    'DQL Update updates tags when sf_cache = true'
  );

  $currentBookCollectionVersion = $sfTagger->getTag(
    sfCacheTaggingToolkit::getBaseClassName(BookTable::getInstance()->getClassnameToReturn())
  );
  $t->is(
    $bookCollectionVersion,
    $currentBookCollectionVersion,
    'Object collection is not changed'
  );
  
  #postSave

  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', false);

  $post = new BlogPost();
  $post->setSlug('Review: Atlas shrugged');
  $post->save();

  sfConfig::set('sf_cache', $optionSfCache);

  $t->ok(
    ! $sfTagger->hasTag(sprintf('BlogPost%s%d', $separator, $post->getId())),
    'When cache is disabled, no tags was saved to backend'
  );

  # preDqlDelete

  $post = new BlogPost();
  $post->setSlug('Git-HowTo');
  $post->save();

  $t->ok(
    $sfTagger->hasTag($key = sprintf('BlogPost%s%d', $separator, $post->getId())),
    sprintf('new tag saved to backend with key "%s"', $key)
  );

  $id = $post->getId();

  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', false);

  BlogPostTable::getInstance()
    ->createQuery()
    ->delete()
    ->addWhere('id = ?', $id)
    ->execute();

  sfConfig::set('sf_cache', $optionSfCache);

  # preDqlDelete + SoftDelete plugin conflict

  $nutName = 'Indian Nuts';
  $nut = new Food();
  $nut->setTitle('Indian Nuts');
  $nut->save();

  $nutVersion = $nut->getObjectVersion();

  $t->ok(
    $sfTagger->hasTag(sprintf('Food%s%d', $separator, $nut->getId())),
    'Tag exists'
  );

  $q = FoodTable::getInstance()->createQuery();
  $rows = $q->delete()->where('title = ?', $nutName)->execute();

  $t->is($rows, 1, 'One row "teoreticaly" (softly) removed');

  $t->ok(
    ! $sfTagger->hasTag(sprintf('Food%s%d', $separator, $nut->getId())),
    'Tag is removed'
  );

  $c = FoodTable::getInstance()->getConnection();

  $stmt = $c->execute(
    sprintf(
      'SELECT id, object_version FROM `food` WHERE `title` = %s LIMIT 1',
      $c->quote($nutName)
    )
  );

  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  $t->isnt($row, false, '1 row found (skipped SoftDelete to check object_version');

  $rowVersion = $row['object_version'];

  $t->cmp_ok(
    $nutVersion, '<', $rowVersion,
    sprintf('Object version increased from %s to %s', $nutVersion, $rowVersion)
  );

  # next test

  $t->ok(
    $sfTagger->hasTag(sprintf('BlogPost%s%d', $separator, $post->getId())),
    'tag deletion skipped due the cache was disabled when preDqlDelete was runned'
  );


  $apple = new Food();
  $apple->setTitle('Yellow apple');
  $apple->save();

  $key = $apple->obtainTagName();

  $t->ok(
    $sfTagger->hasTag($key),
    sprintf('new tag saved to backend with key "%s"', $key)
  );

  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', false);

  FoodTable::getInstance()
    ->createQuery()
    ->delete()
    ->addWhere('title = ?', 'Yellow apple')
    ->execute()
  ;
  sfConfig::set('sf_cache', $optionSfCache);

  $key = $apple->obtainTagName();
  $t->ok(
    $sfTagger->hasTag($key),
    sprintf('key still exists "%s"', $key)
  );

  $connection->rollback();

  $univeristies = UniversityTable::getInstance()->findAll();

  try
  {
    $univeristies->getTags();

    $t->pass('Running getTags() on NON-Cachetaggable model');
  }
  catch (sfConfigurationException $e)
  {
    $t->pass($e->getMessage());
  }