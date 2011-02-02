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

  $connection = Doctrine::getConnectionByTableName('Book');
  $connection->beginTransaction();

  $dbh = $connection->getDbh();

  $r = $dbh->exec('INSERT INTO `book` (slug, lang) VALUES ("war-preace", "en")');

  $stmt = $dbh->query('SELECT `object_version` FROM `book` WHERE `slug` = "war-preace" AND `lang` = "en"');

  $row = $stmt->fetchColumn(0);

  $t = new lime_test();

  $t->is($row, 1, 'Default value for "object_version" is 1');
  
  $connection->rollback();
  