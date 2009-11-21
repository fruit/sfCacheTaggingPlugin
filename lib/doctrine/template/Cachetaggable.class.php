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
  public function __construct (array $options = array())
  {
    $this->_options = Doctrine_Lib::arrayDeepMerge($this->_options, $options);
  }

  public function setUp ()
  {
    parent::setUp();
    
    # add Timestamable behavior (required)
    $this->actAs(new Doctrine_Template_Timestampable());
  }

  /**
   * Set table definition for sortable behavior
   * (borrowed and modified from Sluggable in Doctrine core)
   *
   * @return void
   */
  public function setTableDefinition ()
  {
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
    
    $uniqueName = $this->_options['uniqueColumn'];
    $methodName = sprintf('get%s', sfInflector::tableize($uniqueName));
    $callable = new sfCallable(array($object, $methodName));

    try
    {
      $index = $callable->call();
    }
    catch (Exception $e)
    {
      throw new sfConfigurationException(
        sprintf('Object has not method ->%s', $methodName)
      );
    }

    return sprintf('%s_%s', sfInflector::tableize(get_class($object)), $index);
  }
}