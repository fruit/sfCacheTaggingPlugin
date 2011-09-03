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
  $t = new lime_test();

  $treeTable = Doctrine::getTable('Tree');


  /*
    +----+----------------+---------+------+------+-------+
    | id | name           | root_id | lft  | rgt  | level |
    +----+----------------+---------+------+------+-------+
    |  1 | + music        |       1 |    1 |   10 |     0 |
    |  2 | ++ pop         |       1 |    2 |    3 |     1 |
    |  3 | ++ rock        |       1 |    4 |    9 |     1 |
    |  4 | +++ hard rock  |       1 |    5 |    6 |     2 |
    |  5 | +++ new age    |       1 |    7 |    8 |     2 |
    |  6 | + films        |       6 |    1 |   12 |     0 |
    | 10 | ++ documentary |       6 |    2 |    5 |     1 |
    | 11 | +++ sociology  |       6 |    3 |    4 |     2 |
    |  9 | ++ action      |       6 |    6 |    7 |     1 |
    |  8 | ++ drama       |       6 |    8 |    9 |     1 |
    |  7 | ++ comedy      |       6 |   10 |   11 |     1 |
    +----+----------------+---------+------+------+-------+
  */

  $treeObject = $treeTable->getTree();

  /**
   * Testing insert methods
   */
  $insertMethods = array(
    'insertAsPrevSiblingOf',
    'insertAsNextSiblingOf',
    'insertAsFirstChildOf',
    'insertAsLastChildOf',
  );

  $music = $treeTable->findOneByName('music');

  foreach ($insertMethods as $methodName)
  {
    $jazz = new Tree();
    $jazz->name = 'Jazz';

    try
    {
      $jazz->getNode()->$methodName($music);

      $t->pass(sprintf('%s() does not evaluetes SQL error', $methodName));
    }
    catch (Exception $e)
    {
      $t->fail(sprintf('Call %s() with error %s', $methodName, $e->getMessage()));
    }

    $jazz->delete();
  }


  $moveMethods = array(
    array('moveAsLastChildOf', 'documentary', 'films'),     // after: action, drama, comedy, documentary
    array('moveAsFirstChildOf', 'drama', 'films'),          // after: drama, action, comedy, documentary
    array('moveAsPrevSiblingOf', 'action', 'documentary'),  // after: drama, comedy, action, documentary
    array('moveAsNextSiblingOf', 'drama', 'comedy'),        // after: comedy, drama, action, documentary
  );

  foreach ($moveMethods as $methodOptions)
  {
    $methodVariable = $treeTable->findOneByName($methodOptions[2]);
    $method = $methodOptions[0];

    $object = $treeTable->findOneByName($methodOptions[1]);

    try
    {
      $object->getNode()->$method($methodVariable);
      $t->pass(sprintf(
        '$"%s"->"%s"($"%s") does not evaluetes SQL error',
        $methodOptions[1],
        $methodName,
        $methodOptions[2]
      ));
    }
    catch (Exception $e)
    {
      $t->fail(sprintf('Call %s() with error %s', $methodName, $e->getMessage()));
    }
  }

  $music = $treeTable->findOneByName('music');

  /**
   * Moving nodes to another level
   */

  $sociology = $treeTable->findOneByName('sociology');
  if (! $sociology)
  {
    $t->fail('Sociology node not found');
    return;
  }

  $sociology->name = 'biography';

  $action = $treeTable->findOneByName('action');

  try
  {
    $t->is($sociology->getLevel(), 2, 'Node is @ level 2');

    $sociology->getNode()->moveAsNextSiblingOf($action);

    $t->pass(sprintf('Moving sociology with level 2 to level 1'));

    $t->is($sociology->getLevel(), 1, 'Node successfuly level updated');
  }
  catch (Exception $e)
  {
    $t->fail(sprintf('Moving to another level evaluates error: %s', $e->getMessage()));
  }


