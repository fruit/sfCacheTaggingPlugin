<?php

/*
 * This file is part of the sfCacheTaggingPlugin package.
 * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Additional setup to table and its objects
 * Adds new table column "object_version" and one method to creates tag names
 *
 * @package sfCacheTaggingPlugin
 * @author Ilya Sabelnikov <fruit.dev@gmail.com>
 */
class Doctrine_Template_Cachetaggable extends Doctrine_Template
{
  /**
   * Array of Sortable options
   *
   * @var string
   */
  protected $_options = array(
    'uniqueColumn'  =>  'id',
    'versionColumn' =>  'object_version',
  );

  /**
   * __construct
   *
   * @param string $array
   * @return void
   */
  public function __construct (array $options = array())
  {
    $this->_options = Doctrine_Lib::arrayDeepMerge($this->_options, $options);
  }

  /**
   * Set table definition for sortable behavior
   * (borrowed and modified from Sluggable in Doctrine core)
   *
   * @return void
   */
  public function setTableDefinition ()
  {
    $versionColumn = $this->_options['versionColumn'];

    if (! is_string($versionColumn) or 0 > strlen($versionColumn))
    {
      throw new sfConfigurationException('sfCacheTaggingPlugin: "Cachetaggable" behaviors "versionColumn" should be string and not empty');
    }

    $precision = (int) sfConfig::get('app_sfcachetaggingplugin_microtime_precision', 0);

    $precision = 0 > $precision ? 0 : $precision;

    $this->hasColumn($versionColumn, 'string', 10 + $precision, array('notnull' => false));

    $this->addListener(new Doctrine_Template_Listener_Cachetaggable($this->_options));
  }

  /**
   * @throws LogicException
   * @return string
   */
  public function getTagName ()
  {
    /* @var $object Doctrine_Record */
    $object = $this->getInvoker();

    if ($object->isNew())
    {
      throw new LogicException('To call ->getTagName() you should save it before');
    }
    
    return sprintf(
      '%s_%s',
      get_class($object),
      $object->{$this->_options['uniqueColumn']}
    );
  }

  /**
   * Updates version of the object
   *
   * @param string $version
   * @return Doctrine_Record
   */
  public function setObjectVersion ($version)
  {
    $this->getInvoker()->{$this->_options['versionColumn']} = $version;

    return $this->getInvoker();
  }

  /**
   * Fetches a version of the object
   *
   * @return Doctrine_Record
   */
  public function getObjectVersion ()
  {
    return $this->getInvoker()->{$this->_options['versionColumn']};
  }

  /**
   * Returns Cache manger tagger
   *
   * @return sfTagCache
   */
  public function getTagger ()
  {
    return sfContext::getInstance()->getViewCacheManager()->getTagger();
  }
}