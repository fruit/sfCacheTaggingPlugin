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
   * @package sfCacheTaggingPlugin
   * @subpackage log
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class sfCacheTagLogger extends sfLogger
  {
    protected 
      $format     = '%char%',
      $timeFormat = '%Y-%b-%d %T%z',
      $fp         = null;

    /**
     * @see sfLogger::initialize()
     * @param sfEventDispatcher $dispatcher
     * @param array $options
     */
    public function initialize (sfEventDispatcher $dispatcher, $options = array())
    {
      $this->dispatcher = $dispatcher;

      if (! isset($options['file']))
      {
        throw new sfConfigurationException('You must provide a "file" parameter for this logger.');
      }

      if (isset($options['format']))
      {
        $this->format = $options['format'];
      }

      if (isset($options['time_format']))
      {
        $this->timeFormat = $options['time_format'];
      }

      $dir = dirname($options['file']);
      if (! is_dir($dir))
      {
        $dirChmod = isset($options['dir_mode']) ? $options['dir_mode'] : 0750;
        mkdir($dir, $dirChmod, true);
      }

      $fileExists = file_exists($options['file']);

      if (!is_writable($dir) || ($fileExists && !is_writable($options['file'])))
      {
        throw new sfFileException(sprintf(
          'Unable to open the log file "%s" for writing.', $options['file']
        ));
      }

      $this->fp = fopen($options['file'], 'a');

      if (! $this->fp)
      {
        throw new sfFileException(sprintf(
          'Unable to open file resource "%s"', $options['file']
        ));
      }

      if (! $fileExists)
      {
        $fileChmod = isset($options['file_mode']) ? $options['file_mode'] : 0640;
        chmod($options['file'], $fileChmod);
      }

      $this->options = $options;
    }

    protected function doLog ($char, $keyData)
    {
      if (flock($this->fp, LOCK_EX))
      {
        fwrite($this->fp, strtr($this->format, array(
          '%char%'     => $char,
          '%key_data%' => $keyData === null ? '' : $keyData,
          '%time%'     => strftime($this->timeFormat),
          '%EOL%'      => PHP_EOL,
        )));
        
        flock($this->fp, LOCK_UN);
      }
    }

    /**
     * Executes the shutdown method.
     */
    public function shutdown ()
    {
      if (is_resource($this->fp))
      {
        fclose($this->fp);
      }
    }
  }