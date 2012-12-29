<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2013 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * Class to disable cache tag logging in factories.yml file
   *
   * @package sfCacheTaggingPlugin
   * @subpackage log
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class sfNoCacheTagLogger extends sfCacheTagLogger
  {
    /**
     * {@inheritdoc}
     */
    protected function doLog ($char, $key)
    {
      return true;
    }
  }