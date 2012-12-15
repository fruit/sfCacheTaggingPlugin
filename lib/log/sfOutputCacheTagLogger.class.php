<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2012 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * Outputs all log message to the output
   *
   * @package sfCacheTaggingPlugin
   * @subpackage log
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class sfOutputCacheTagLogger extends sfCacheTagLogger
  {
    /**
     * {@inheritdoc}
     */
    protected function doLog ($char, $key)
    {
      // STDOUT does not work in CLI
      echo $this->getFormattedMessage($char, $key);

      return true;
    }
  }