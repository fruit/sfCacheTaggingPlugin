<?php

class frontendConfiguration extends sfApplicationConfiguration
{
  public function configure()
  {
//    $this->getEventDispatcher()->connect(
//      'sf_cache_tagging_plugin.doctrine_configure',
//      array($this, 'listenOnDoctrineConfigure')
//    );
  }

//  public function listenOnDoctrineConfigure (sfEvent $event)
//  {
//    $manager = $event->getSubject();
//    $manager->setAttribute(Doctrine_Core::ATTR_COLLECTION_CLASS, 'My_Collection');
//  }

}
