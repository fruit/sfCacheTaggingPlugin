<?php

  /**
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * 
   * @package sfCacheTaggingPlugin
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class sfTagNamespacedParameterHolder extends sfNamespacedParameterHolder
  {
    public function setNamespace ($value, $ns = null)
    {
      $ns = ! $ns ? $this->default_namespace : $ns;
      
      $this->parameters[$ns] = $value;
    }

    public function add ($parameters, $ns = null)
    {
      parent::add(sfCacheTaggingToolkit::formatTags($parameters), $ns);
    }
  }