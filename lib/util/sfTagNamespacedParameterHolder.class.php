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
    /**
     * Custom method to skip older tag versions before mergin two tag arrays
     *
     * @param string $a
     * @param string $b
     * @return boolean
     */
    protected function choiceNewerTag ($a, $b)
    {
      return $a < $b;
    }

    public function setNamespace ($value, $ns = null)
    {
      $ns = ! $ns ? $this->default_namespace : $ns;
      
      $this->parameters[$ns] = $value;
    }

    public function remove($name, $default = null, $ns = null)
    {
      if (gettype($name) !== 'string')
      {
        throw new InvalidArgumentException(sprintf(
          'Name should be typeof "string" (given "%s")', gettype($name)
        ));
      }

      parent::remove($name, $default, $ns);
    }

    public function set($name, $value, $ns = null)
    {
      if (! $ns)
      {
        $ns = $this->default_namespace;
      }

      if (! isset($this->parameters[$ns]))
      {
        $this->parameters[$ns] = array();
      }

      if (gettype($value) !== 'string')
      {
        throw new InvalidArgumentException(sprintf(
          'Value should be typeof "string" (given "%s")', gettype($value)
        ));
      }

      if (! isset($this->parameters[$ns][$name]))
      {
        $this->parameters[$ns][$name] = $value;
      }
      elseif ($value > $this->parameters[$ns][$name])
      {
        $this->parameters[$ns][$name] = $value;
      }
    }

    public function add ($parameters, $ns = null)
    {
      if ($parameters === null)
      {
        throw new InvalidArgumentException(sprintf(
          'parameters should be not null'
        ));
      }

      if (is_scalar($parameters))
      {
        throw new InvalidArgumentException(sprintf(
          'Parameters should be scalar (given "%s")', gettype($parameters)
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

      $parameters = sfCacheTaggingToolkit::formatTags($parameters);

      $this->parameters[$ns] = array_merge(
        $this->parameters[$ns],
        $parameters,
        array_uintersect_assoc($this->parameters[$ns], $parameters, array($this, 'choiceNewerTag')),
        array_uintersect_assoc($parameters, $this->parameters[$ns], array($this, 'choiceNewerTag'))
      );
    }
  }