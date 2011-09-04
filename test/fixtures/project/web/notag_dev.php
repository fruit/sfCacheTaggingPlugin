<?php

  require_once(dirname(__FILE__).'/../config/ProjectConfiguration.class.php');

  $configuration = ProjectConfiguration::getApplicationConfiguration('notag', 'dev', true);
  sfContext::createInstance($configuration)->dispatch();