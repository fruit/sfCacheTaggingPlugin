<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2013 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * Enables tag logging into a file
   *
   * @package sfCacheTaggingPlugin
   * @subpackage log
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class sfFileCacheTagLogger extends sfCacheTagLogger
  {
    /**
     * Log file resource
     *
     * @var resource
     */
    protected $fp = null;

    /**
     * {@inheritdoc}
     */
    public function initialize (array $options = array())
    {
      parent::initialize(array_merge(
        array('file_mode' => 0640, 'dir_mode' => 0750),
        $options
      ));

      if (null === ($file = $this->getOption('file')))
      {
        throw new sfConfigurationException(
          'You must provide a "file" parameter for this logger.'
        );
      }

      $dir = dirname($file);

      if (! is_dir($dir))
      {
        $umask = umask(0);
        mkdir($dir, $this->getOption('dir_mode'), true);
        umask($umask);
      }

      $fileExists = is_file($file);

      if ($fileExists && ! is_writable($dir))
      {
        throw new sfFileException(sprintf(
          'Unable to open the log file "%s" for writing.', $file
        ));
      }

      $this->fp = fopen($file, 'a');

      if (! $this->fp)
      {
        throw new sfFileException(sprintf('Failed to open file "%s" for append', $file));
      }

      if (! $fileExists)
      {
        $umask = umask(0);
        chmod($file, $this->getOption('file_mode'));
        umask($umask);
      }
    }

    /**
     * Locks file before writing text into a file, and then unlocks it
     *
     * {@inheritdoc}
     */
    protected function doLog ($char, $key)
    {
      if (flock($this->fp, LOCK_EX)) // will wait while lock is present
      {
        fwrite($this->fp, $this->getFormattedMessage($char, $key));

        flock($this->fp, LOCK_UN);
      }

      return true;
    }

    /**
     * Executes the shutdown method.
     *
     * {@inheritdoc}
     */
    public function shutdown ()
    {
      if (is_resource($this->fp))
      {
        return fclose($this->fp);
      }

      return false;
    }
  }
