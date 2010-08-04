<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
  */

  /**
   * Cache key and tag logger
   *
   * Due the priority logic in sfLogger, was created sfCacheLogger
   *
   * @package sfCacheTaggingPlugin
   * @subpackage log
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  abstract class sfCacheTagLogger
  {
    /**
     * Default logger format
     *
     * Available arguments combinations: %char%, %time%, %key%, %EOL%
     *
     * @var string
     */
    protected $format = '%char%';
    
    /**
     *
     * @var <type>
     */
    protected $options = array();

    /**
     * Class constructor.
     *
     * @see initialize()
     */
    public function __construct (array $options = array())
    {
      $this->initialize($options);

      if ($this->getOption('auto_shutdown'))
      {
        register_shutdown_function(array($this, 'shutdown'));
      }
    }

    /**
     * Initializes this sfCacheTagLogger instance.
     *
     * @param array $options An array of options.
     * @return void
     */
    public function initialize (array $options = array())
    {
      $this->options = Doctrine_Lib::arrayDeepMerge(
        array('auto_shutdown' => true, 'skip_chars' => ''), $options
      );

      if (null !== ($timeFormat = $this->getOption('time_format')))
      {
        $this->timeFormat = $timeFormat;
      }

      if (null !== ($format = $this->getOption('format')))
      {
        $this->format = $format;
      }
    }

    /**
     * Returns the options for the logger instance.
     */
    public function getOptions()
    {
      return $this->options;
    }

    /**
     * Sets option value
     */
    public function setOption ($name, $value)
    {
      $this->options[$name] = $value;
    }

    /**
     * @param string $name Option name
     * @param mixed  $default Return this value if option does not exists
     * @return mixed|null
     */
    public function getOption ($name, $default = null)
    {
      return isset($this->options[$name]) ? $this->options[$name] : $default;
    }

    /**
     * @param string $char  One character
     * @param string $key   Cache name or tag name with version
     *                      (e.g. "CompanyArticle_1(947568127349582")
     */
    abstract protected function doLog ($char, $key);

    /**
     * Logs a message.
     *
     * @param string $char  One character
     * @param string $key   Cache name or tag name
     *                      (e.g. "CompanyArticle_1" or "top-10-en-posts")
     */
    public function log ($char, $key)
    {
      if (false !== strpos($this->getOption('skip_chars'), $char))
      {
        return false;
      }

      return $this->doLog($char, $key);
    }


    /**
     * Executes the shutdown method.
     */
    public function shutdown ()
    {

    }
  }