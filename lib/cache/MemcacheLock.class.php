<?php

/**
 * Description of MemcacheLock
 *
 * @author fruit
 */
class MemcacheLock extends Memcache
{
  private
    $fileResource = null;

  public function __construct()
  {
    $AZ = range('A', 'Z');
    $this->id = $AZ[rand(0, count($AZ) - 1)];
  }

  /**
   * @param string $statsFilename
   * @return MemcacheLock
   */
  public function setStatsFilename ($statsFilename)
  {
    $this->tryToCloseStatsFileResource();

    if (! file_exists($statsFilename))
    {
      if (0 === file_put_contents($statsFilename, ''))
      {
        chmod($statsFilename, 0666);
      }
      else
      {
        throw new sfInitializationException(sprintf(
          'Could not create file "%s"', $statsFilename
        ));
      }
    }

    if (! is_readable($statsFilename) or ! is_writable($statsFilename))
    {
      throw new sfInitializationException(sprintf(
        'File "%s" is not readable/writeable', $statsFilename
      ));
    }

    $this->fileResource = fopen($statsFilename, 'a+');

    if (! $this->fileResource)
    {
      throw new sfInitializationException(sprintf(
        'Could not fopen file "%s" with append (a+) flag',
        $statsFilename
      ));
    }

    return $this;
  }

  private function tryToCloseStatsFileResource ()
  {
    if (! is_null($this->fileResource))
    {
      fclose($this->fileResource);
    }
  }

  public function __destruct ()
  {
    $this->writeChar("\n");
    
    $this->tryToCloseStatsFileResource();
  }

  private function writeChar ($char, $key = null)
  {
    if (! is_null($key))
    {
      fwrite($this->fileResource, sprintf("[%s] %s: %-35s | %s\n", $this->id, $char, $key, microtime()));
    }
//    fwrite($this->fileResource, $char);
  }

  public function lock ($key, $expire = 10)
  {
    if (true === ($result = apc_add("lock_{$key}", 1, $expire)))
    {
      $this->writeChar('L', "lock_{$key}");
    }
    else
    {
      $this->writeChar('l', "lock_{$key}");
    }

//    $result = $this->add("[lock]-{$key}", 1, false, $expire);
//    if ($result)
//    {
//      $this->writeChar('L', $key);
//    }
//    else
//    {
//      $this->writeChar('l', $key);
//    }

    return $result;
  }

  public function unlock ($key)
  {
    if (true === ($result = apc_delete("lock_{$key}")))
    {
      $this->writeChar('U', "lock_{$key}");
    }
    else
    {
      $this->writeChar('u', "lock_{$key}");
    }

//    $result = $this->delete(sprintf('[lock]-%s', $key));
//    if ($result)
//    {
//      $this->writeChar('U', $key);
//    }
//    else
//    {
//      $this->writeChar('u', $key);
//    }

    return $result;
  }

  public function set ($key, $var, $flag = null, $expire = null)
  {
    $result = parent::set($key, $var, $flag, $expire);

    if ($result)
    {
      $this->writeChar('W', $key);
    }
    else
    {
      $this->writeChar('w', $key);
    }

    return $result;
  }

  public function get ($key, & $flags = null)
  {
    $result = parent::get($key, $flags);

    if ($result)
    {
      $this->writeChar('H', $key);
    }
    else
    {
      $this->writeChar('h', $key);
    }

    return $result;
  }

//  public function delete ($key, $timeout = null)
//  {
//    $result = parent::delete($key, $timeout);
//
//    if ($result)
//    {
//      $this->writeChar('D', $key);
//    }
//    else
//    {
//      $this->writeChar('d', $key);
//    }
//
//    return $result;
//  }

  public function replace ($key, $var, $flag = null, $expire = null)
  {
    $result = parent::replace($key, $var, $flag, $expire);

    if ($result)
    {
      $this->writeChar('R', $key);
    }
    else
    {
      $this->writeChar('r', $key);
    }

    return $result;
  }
}

