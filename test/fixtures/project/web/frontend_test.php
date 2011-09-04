<?php
  
  require_once(dirname(__FILE__) . '/../config/ProjectConfiguration.class.php');

  $app = ProjectConfiguration::getApplicationConfiguration('frontend', 'test', true);
  sfContext::createInstance($app)->dispatch();

