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


  $handler = new sfContentTagHandler();

  $methods = array(
    'getContentTags',
    'setContentTags',
    'addContentTags',
    'removeContentTags',
    'hasContentTag',
    'setContentTag',
    'removeContentTag',
  );

  $t->can_ok($handler, $methods);

  $t->isa_ok(sfViewCacheTagManager::getNamespaces(), 'array');
  $t->is(count(sfViewCacheTagManager::getNamespaces()), 3);

  $oldVersion = sfCacheTaggingToolkit::generateVersion();
  $tagsAB = array(
    'A' => sfCacheTaggingToolkit::generateVersion(),
    'B' => sfCacheTaggingToolkit::generateVersion(),
  );

  $tagsCDT = array(
    'C' => sfCacheTaggingToolkit::generateVersion(),
    'D' => sfCacheTaggingToolkit::generateVersion(),
    'T' => sfCacheTaggingToolkit::generateVersion(),
  );

  $tagsAC = array(
    'A' => sfCacheTaggingToolkit::generateVersion(),
    'C' => sfCacheTaggingToolkit::generateVersion(),
  );

  $tagsAE = array(
    'A' => sfCacheTaggingToolkit::generateVersion(),
    'E' => sfCacheTaggingToolkit::generateVersion(),
  );

  $tagsA = array(
    'A_A' => sfCacheTaggingToolkit::generateVersion(),
    'A_B' => sfCacheTaggingToolkit::generateVersion(),
    'A_C' => sfCacheTaggingToolkit::generateVersion(),
    'A'   => sfCacheTaggingToolkit::generateVersion(),
  );

  $tagsB = array(
    'B_A' => sfCacheTaggingToolkit::generateVersion(),
    'B_B' => sfCacheTaggingToolkit::generateVersion(),
    'B_C' => sfCacheTaggingToolkit::generateVersion(),
    'B'   => sfCacheTaggingToolkit::generateVersion(),
  );

  $namespacesWithCustomOne = array_merge(
    sfViewCacheTagManager::getNamespaces(),
    array('Custom')
  );

  # default interface test
  foreach ($namespacesWithCustomOne as $namespace)
  {
    $t->comment(sprintf('Namespace "%s"', $namespace));
    $t->isa_ok($namespace, 'string');
    $t->isa_ok($handler->getContentTags($namespace), 'array');
    $t->is(count($handler->getContentTags($namespace)), 0);

    
    $handler->removeContentTags($namespace);
    $t->is(count($handler->getContentTags($namespace)), 0);


    $handler->setContentTags($tagsAB, $namespace);
    $t->is(count($handler->getContentTags($namespace)), count($tagsAB));
    $handler->removeContentTags($namespace);


    $handler->setContentTags($tagsAB, $namespace);
    $t->is($handler->hasContentTag('C', $namespace), false);
    foreach ($tagsAB as $key => $value)
    {
      $t->is($handler->hasContentTag($key, $namespace), true);
      $handler->removeContentTag($key, $namespace);
    }
    $t->is(count($handler->getContentTags($namespace)), 0);


    $handler->setContentTags($tagsAB, $namespace);
    $t->is(count($handler->getContentTags($namespace)), count($tagsAB));
    $handler->setContentTags($tagsCDT, $namespace);
    $t->is(count($handler->getContentTags($namespace)), count($tagsCDT));
    $t->is(array_keys($handler->getContentTags($namespace)), array_keys($tagsCDT));
    $handler->removeContentTags($namespace);


    $handler->addContentTags($tagsAB, $namespace);
    $handler->addContentTags($tagsCDT, $namespace);
    $t->is(count($handler->getContentTags($namespace)), count($tagsAB) + count($tagsCDT));
    $t->is(array_keys($handler->getContentTags($namespace)), array_keys($tagsAB + $tagsCDT));
    $handler->removeContentTags($namespace);


    $handler->addContentTags($tagsAB, $namespace);
    $handler->setContentTag('U', $version = sfCacheTaggingToolkit::generateVersion(), $namespace);
    $t->is(count($handler->getContentTags($namespace)), count($tagsAB) + 1);
    $t->is(array_keys($handler->getContentTags($namespace)), array_keys($tagsAB + array('U' => $version)));
    $handler->removeContentTags($namespace);


    $handler->addContentTags($tagsAB, $namespace);
    $handler->setContentTag('A', $version = sfCacheTaggingToolkit::generateVersion(), $namespace);
    $t->is(count($handler->getContentTags($namespace)), count($tagsAB));
    $t->is(array_keys($handler->getContentTags($namespace)), array_keys($tagsAB));
    $handler->removeContentTags($namespace);


    $handler->addContentTags($tagsAB, $namespace);
    $handler->addContentTags($tagsCDT, $namespace);
    $handler->addContentTags($tagsAC, $namespace);
    $t->is(count($handler->getContentTags($namespace)), count($tagsAB) + count($tagsCDT));
    $t->is(array_keys($handler->getContentTags($namespace)), array_keys($tagsAB + $tagsCDT));
    $handler->addContentTags($tagsAE, $namespace);
    $t->is(count($handler->getContentTags($namespace)), count($tagsAB) + count($tagsCDT) + 1);
    $t->is(array_keys($handler->getContentTags($namespace)), array_keys($tagsAB + $tagsCDT + $tagsAE));
    $handler->removeContentTags($namespace);


    $handler->addContentTags($tagsAE, $namespace);
    $t->is(count($handler->getContentTags($namespace)), count($tagsAE));
    $t->is(array_keys($handler->getContentTags($namespace)), array_keys($tagsAE));
    $handler->addContentTags($tagsCDT, $namespace);
    $t->is(count($handler->getContentTags($namespace)), count($tagsAE) + count($tagsCDT));
    $t->is(array_keys($handler->getContentTags($namespace)), array_keys($tagsAE + $tagsCDT));
    $handler->removeContentTags($namespace);


    $handler->setContentTags($tagsA, $namespace);

    $clonedA = $tagsA;
    unset($clonedA['A_A']);
    $clonedA['A_B'] = sfCacheTaggingToolkit::generateVersion();
    $clonedA['A'] = $oldVersion;

    $handler->addContentTags($clonedA, $namespace);

    $t->is(
      $handler->getContentTags($namespace),
      array(
        'A_A' => $tagsA['A_A'],   # same (not updated)
        'A_B' => $clonedA['A_B'], # newer (updated)
        'A_C' => $tagsA['A_C'],   # same (not updated)
        'A'   => $tagsA['A'],     # older (not updated)
      )
    );

    $handler->setContentTags($tagsA, $namespace);

    $handler->setContentTag('A', $versionA = sfCacheTaggingToolkit::generateVersion(), $namespace);
    $handler->setContentTag('A_A', $oldVersion, $namespace);

    $t->is(
      $handler->getContentTags($namespace),
      array(
        'A_A' => $tagsA['A_A'], # older (not updated)
        'A_B' => $tagsA['A_B'], # newer (updated)
        'A_C' => $tagsA['A_C'], # same (not updated)
        'A'   => $versionA,     # newer (updated)
      )
    );



  }