<?php

class sfCacheTaggingPluginConfiguration extends sfPluginConfiguration
{
  public function initialize ()
  {
    $manager = Doctrine_Manager::getInstance();
    $manager->setAttribute(Doctrine::ATTR_COLLECTION_CLASS, 'Doctrine_Collection_Cachetaggable');
  }
}