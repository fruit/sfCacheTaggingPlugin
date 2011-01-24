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

  class A
  {
    public function f ($a, $b, $c)
    {
      return $a + $b + $c;
    }
  }

  $callableArrays = array(
    array(
      array(new A(), 'f'), true
    ),
    array(
      array(new A(), 'e'), false
    ),
  );

  foreach ($callableArrays as $callableArray)
  {
    try
    {
      $c = new sfCallableArray($callableArray[0]);

      if (6 !== $c->callArray(array(1, 2, 3)))
      {
        $t->fail('6 !== 1 + 2 + 3');

        continue;
      }

      $t->ok(
        $callableArray[1],
        sprintf(
          'Called method "%s::%s" without exceptions',
          get_class($callableArray[0][0]),
          $callableArray[0][1]
        )
      );
    }
    catch (sfException $e)
    {
      $t->ok(! $callableArray[1], $e->getMessage());
    }
  }