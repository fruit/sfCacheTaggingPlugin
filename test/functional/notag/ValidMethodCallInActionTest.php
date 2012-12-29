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

  $browser = new sfTestFunctional(new sfBrowser());

  $browser->getAndCheck('blog_post', 'validMethodTest', '/blog_post/validMethodTest', 200);
  $browser->checkCurrentExceptionIsEmpty();

  $browser
    ->with('response')
    ->begin()
    ->checkElement('b', 'Tags count is 0')
    ->end();
