<?php

class frontendConfiguration extends sfApplicationConfiguration
{
  public function configure()
  {

  }

  public static function format ($name)
  {
    return "_{$name}_";
  }
}
