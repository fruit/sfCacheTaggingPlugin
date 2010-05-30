<?php

/*
 * This file is part of the sfCacheTaggingPlugin package.
 * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @link http://framework.zend.com/wiki/display/ZFDEV/Zend+Framework+Components+-+Developer+Notes
 */
if (! defined('E_USER_DEPRECATED')) 
{
  define('E_USER_DEPRECATED', E_USER_WARNING);
}

/**
 * sfCacheTaggingPlugin configuration
 *
 * @package sfCacheTaggingPlugin
 * @author Ilya Sabelnikov <fruit.dev@gmail.com>
 */
class sfCacheTaggingPluginConfiguration extends sfPluginConfiguration
{
  /**
   * Handy method to get type hinting in IDE's
   *
   * @return sfEventDispatcher
   */
  public function getEventDispatcher ()
  {
    return $this->dispatcher;
  }

  public function initialize ()
  {
    $manager = Doctrine_Manager::getInstance();
    $manager->setAttribute(Doctrine::ATTR_COLLECTION_CLASS, 'Doctrine_Collection_Cachetaggable');
    $manager->setAttribute(Doctrine::ATTR_USE_DQL_CALLBACKS, true);

    $this->getEventDispatcher()->notify(new sfEvent($manager, 'sf_cache_tagging_plugin.doctrine_configure'));
  }
}