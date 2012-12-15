<?php

  // Used together with "phpweb" as a router
  $ext = strtolower(pathinfo($_SERVER["REQUEST_URI"], PATHINFO_EXTENSION));
  if (in_array($ext, explode(',', 'png,jpg,jpeg,gif,css,js,ico'), true)) return false;

  require_once(dirname(__FILE__) . '/../config/ProjectConfiguration.class.php');

  $app = ProjectConfiguration::getApplicationConfiguration('frontend', 'dev', true);
  sfContext::createInstance($app)->dispatch();