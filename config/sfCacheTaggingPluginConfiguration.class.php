<?php

/*
 * This file is part of the sfCacheTaggingPlugin package.
 * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfCacheTaggingPlugin configuration
 *
 * @package sfCacheTaggingPlugin
 * @author Ilya Sabelnikov <fruit.dev@gmail.com>
 */
class sfCacheTaggingPluginConfiguration extends sfPluginConfiguration
{
  public function initialize ()
  {
    $manager = Doctrine_Manager::getInstance();
    $manager->setAttribute(Doctrine::ATTR_COLLECTION_CLASS, 'Doctrine_Collection_Cachetaggable');
    $manager->setAttribute(Doctrine::ATTR_USE_DQL_CALLBACKS, true);
  }
}