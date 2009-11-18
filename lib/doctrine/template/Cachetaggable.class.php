<?php

/**
 * Easily create a slug for each record based on a specified set of fields
 *
 * @package     sfCacheTaggingPlugin
 * @subpackage  template
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.phpdoctrine.org
 * @since       1.0
 * @version     $Revision$
 * @author      Ilya Sabelnikov <fruit.dev@gmail.com>
 */
class Doctrine_Template_Cachetaggable extends Doctrine_Template
{
  /**
   * Array of Sortable options
   *
   * @var string
   */
  protected $_options = array(
    'uniqueColumn'   =>  'id'
  );

  /**
   * __construct
   *
   * @param string $array
   * @return void
   */
  public function __construct(array $options = array())
  {
    $this->_options = Doctrine_Lib::arrayDeepMerge($this->_options, $options);
  }


  /**
   * Set table definition for sortable behavior
   * (borrowed and modified from Sluggable in Doctrine core)
   *
   * @return void
   */
  public function setTableDefinition()
  {
    # add Timestamable behavior (required)

    $this->addListener(new Doctrine_Template_Listener_Cachetaggable($this->_options));

    $this->actAs(new Doctrine_Template_Timestampable());
  }


}