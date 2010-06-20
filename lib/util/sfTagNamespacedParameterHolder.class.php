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

    public function remove($tagName, $default = null, $ns = null)
    {
      if (gettype($tagName) !== 'string')
      {
        throw new InvalidArgumentException(sprintf(
          'Name should be typeof "string" (given "%s")', gettype($tagName)
        ));
      }

      parent::remove($tagName, $default, $ns);
    }

    public function set ($tagName, $tagVersion, $ns = null)
    {
      if (! is_string($tagName))
      {
        throw new InvalidArgumentException(sprintf(
          'Called "%s" with invalid first argument type "%s". Acceptable type is: "string"',
          __METHOD__,
          gettype($tagName)
        ));
      }

      if (! is_scalar($tagVersion))
      {
        throw new InvalidArgumentException(sprintf(
          'Called "%s" with invalid second argument type "%s".  are scalars',
          __METHOD__,
          gettype($tagVersion)
        ));
      }

      if (! $ns)
      {
        $ns = $this->default_namespace;
      }

      if (! isset($this->parameters[$ns]))
      {
        $this->parameters[$ns] = array();
      }

      # skip old tag versions
      if (! isset($this->parameters[$ns][$tagName]) or ($tagVersion > $this->parameters[$ns][$tagName]))
      {
        $this->parameters[$ns][$tagName] = $tagVersion;
      }
    }

    /**
     *
     * @throws InvalidArgumentException
     * @param mixed $parameters
     * @param mixed $ns
     */
    public function add ($parameters, $ns = null)
    {
      if (! $ns)
      {
        $ns = $this->default_namespace;
      }

      if (! isset($this->parameters[$ns]))
      {
        $this->parameters[$ns] = array();
      }

      $parameters = sfCacheTaggingToolkit::formatTags($parameters);

      foreach ($parameters as $name => $value)
      {
        $this->set($name, $value, $ns);
      }
    }
  }