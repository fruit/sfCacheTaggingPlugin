<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2012 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  include_once realpath(dirname(__FILE__) . '/../../bootstrap/functional.php');
  include_once sfConfig::get('sf_symfony_lib_dir') . '/vendor/lime/lime.php';

  $separator = sfCacheTaggingToolkit::getModelTagNameSeparator();

  $sfContext = sfContext::getInstance();
  $cacheManager = $sfContext->getViewCacheManager();
  $templateName = sprintf('Doctrine_Template_%s', sfCacheTaggingToolkit::TEMPLATE_NAME);

  $sfTagger = $cacheManager->getTaggingCache();

  $t = new lime_test();

  $connection = Doctrine_Manager::getInstance()->getCurrentConnection();
  $connection->beginTransaction();

  BookTable::getInstance()->createQuery()->delete()->execute();

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

  $optionSfCache = sfConfig::get('sf_cache');
  sfConfig::set('sf_cache', false);

  BlogPostTable::getInstance()
    ->createQuery()
    ->delete()
    ->addWhere('id = ?', $post->getId())
    ->execute();

  sfConfig::set('sf_cache', $optionSfCache);

  $t->ok(
    $sfTagger->hasTag(sprintf('BlogPost%s%d', $separator, $post->getId())),
    'tag deletion skipped due the cache was disabled when preDqlDelete was runned'
  );

  try
  {
    UniversityTable::getInstance()->findAll()->getCacheTags();
    $t->pass('Running getCacheTags() on NON-Cachetaggable model');
  }
  catch (sfConfigurationException $e)
  {
    $t->pass($e->getMessage());
  }

  // obtainObjectNamespace
  // =====================

  class TestDoctrine_Template_Cachetaggable extends Doctrine_Template_Cachetaggable
  {
    public function obtainInvokerNamespace ()
    {
      return parent::obtainInvokerNamespace();
    }
  }

  class TestBook extends Book
  {
    public function setUp ()
    {
      $this->actAs(new TestDoctrine_Template_Cachetaggable(array(
        'uniqueColumn' => array('lang', 'slug'),
        'uniqueKeyFormat' => '%s-%s',
      )));
    }
  }

  // To different object instances should have different namespace names
  $abc = new TestBook();
  $qwe = new TestBook();

  // Namespace based on template name and object hash
  $t->is($abc->obtainInvokerNamespace(), sprintf('%s/%s', $templateName, spl_object_hash($abc)), 'Namespace based on template name and SPL object hash');
  $t->is($qwe->obtainInvokerNamespace(), sprintf('%s/%s', $templateName, spl_object_hash($qwe)), 'Namespace based on template name and SPL object hash');

  $t->isnt($abc->obtainInvokerNamespace(), $qwe->obtainInvokerNamespace(), 'ABC namespace is not same as QWE');

  $connection->rollback();