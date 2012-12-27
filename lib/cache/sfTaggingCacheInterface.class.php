<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2012 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * Interface to expand the sfCache functionality
   *
   * @package sfCacheTaggingPlugin
   * @subpackage cache
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  interface sfTaggingCacheInterface
  {
    /**
     * @return array|null
     */
    public function getCacheKeys ();
  }