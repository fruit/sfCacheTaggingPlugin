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
    'uniqueColumn'   =>  'id',
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
    $this->hasColumn('object_version', 'string', 20, array('notnull' => false));

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
      sfInflector::tableize(get_class($object)),
      $object->{$this->_options['uniqueColumn']}
    );
  }
}