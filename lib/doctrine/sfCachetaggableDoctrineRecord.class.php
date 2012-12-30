<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2013 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * @package sfCacheTaggingPlugin
   * @subpackage doctrine
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  abstract class sfCachetaggableDoctrineRecord extends sfDoctrineRecord
  {
    /**
     * Passed through the save/replace/delete user current method
     * connection instance.
     *
     * Used to detect transaction state while writing tags into cache backend.
     *
     * @var Doctrine_Connection
     */
    protected $userConnection = null;

    /**
     * @var string $templateName Template (behavior) name
     * @return Doctrine_Template
     */
    protected function getTempleteWithInvoker ($templateName, $method)
    {
      $table = $this->getTable();

      if ( ! $table->hasTemplate($templateName))
      {
        throw new InvalidArgumentException(
          sprintf('Template "%s" is not registered', $templateName)
        );
      }

      $template = $table->getTemplate($templateName);
      $template->setInvoker($this);

      $table->setMethodOwner($method, $template);

      return $template;
    }

    /**
     * @return Doctrine_Template_Cachetaggable
     */
    protected function getCachetaggable ($method)
    {
      return $this->getTempleteWithInvoker(
        sfCacheTaggingToolkit::TEMPLATE_NAME, $method
      );
    }

    /**
     * @return Doctrine_Record
     */
    public function updateObjectVersion ()
    {
      return $this->getCachetaggable('updateObjectVersion')->updateObjectVersion();
    }

    /**
     * @see Doctrine_Template_Cachetaggable::getCacheTags()
     * @var boolean $deep (whether to fetch tags recursively from all joined tables)
     * @return array Object tags
     */
    public function getCacheTags ($deep = true)
    {
      return $this
        ->getCachetaggable('getCacheTags')
        ->getCacheTags($deep)
      ;
    }

    /**
     * @see Doctrine_Template_Cachetaggable::obtainTagName()
     * @return string
     */
    public function obtainTagName ()
    {
      return $this->getCachetaggable('obtainTagName')->obtainTagName();
    }

    /**
     * @see Doctrine_Template_Cachetaggable::obtainObjectVersion()
     * @return string
     */
    public function obtainObjectVersion ()
    {
      return $this->getCachetaggable('obtainObjectVersion')->obtainObjectVersion();
    }

    /**
     * @see Doctrine_Template_Cachetaggable::getCollectionTags()
     * @return array
     */
    public function getCollectionTags ()
    {
      return $this->getCachetaggable('getCollectionTags')->getCollectionTags();
    }

    /**
     * @see Doctrine_Template_Cachetaggable::obtainCollectionName()
     * @return string
     */
    public function obtainCollectionName ()
    {
      return $this->getCachetaggable('obtainCollectionName')->obtainCollectionName();
    }

    /**
     * @see Doctrine_Template_Cachetaggable::obtainCollectionVersion()
     * @return string
     */
    public function obtainCollectionVersion ()
    {
      return $this->getCachetaggable('obtainCollectionVersion')->obtainCollectionVersion();
    }

    /**
     * Generates tags for refTable
     *
     * @param string    $alias
     * @param array     $ids
     * @return array
     */
    protected function getTagNamesByAlias ($alias, array $ids)
    {
      $relation = $this->getTable()->getRelation($alias);

      if (! $relation instanceof Doctrine_Relation_Association)
      {
        return array();
      }

      /* @var $refTable Doctrine_Table */
      $refTable = $relation->getAssociationTable();

      if (! $refTable->hasTemplate(sfCacheTaggingToolkit::TEMPLATE_NAME))
      {
        return array();
      }

      $template = $refTable->getTemplate(sfCacheTaggingToolkit::TEMPLATE_NAME);

      $values = array();

      foreach ($ids as $id)
      {
        foreach ($refTable->getIdentifierColumnNames() as $columnName)
        {
          if ($relation->getLocal() == $columnName)
          {
            $values[$id][$columnName] = $this->getPrimaryKey();
          }
          else
          {
            $values[$id][$columnName] = $id;
          }
        }
      }

      $uniqueColumns = $template->getOptionUniqueColumns();
      $keyFormat = $template->getOptionKeyFormat($uniqueColumns);

      $tagNames = array();

      foreach ($values as $columnValues)
      {
        $tagName = sfCacheTaggingToolkit::buildTagKey(
          $template, $keyFormat, array_values($columnValues)
        );

        $tagNames[$tagName] = true;
      }

      return $tagNames;
    }

    /**
     * After linking ID's, invalidates object tags
     *
     * {@inheritdoc}
     */
    public function link ($alias, $ids, $now = false)
    {
      $self = parent::link($alias, $ids, $now);
      $ids  = (array) $ids;

      if (! sfConfig::get('sf_cache') || ! count($ids)) return $self;

      $taggingCache = sfCacheTaggingToolkit::getTaggingCache();
      $taggingCache->invalidateTags($this->getTagNamesByAlias($alias, $ids));

      return $self;
    }

    /**
     * After unlinking ID's, invalidates object tags
     *
     * {@inheritdoc}
     */
    public function unlink ($alias, $ids = array(), $now = false)
    {
      $self = parent::unlink($alias, $ids, $now);
      $ids  = (array) $ids;

      if (! sfConfig::get('sf_cache') || ! count($ids)) return $self;

      $taggingCache = sfCacheTaggingToolkit::getTaggingCache();
      $taggingCache->deleteTags($this->getTagNamesByAlias($alias, $ids));

      return $self;
    }

    /**
     * Saves user custom connection instance, to correctly detect Doctrine
     * transaction state in Doctrine_Template_Listener_Cachetaggable.
     *
     * @param Doctrine_Connection $conn
     */
    protected function setUserConnection (Doctrine_Connection $conn = null)
    {
      $this->userConnection = $conn;
    }

    /**
     * Returns real connection, when user executes one of the actions:
     *    - save
     *    - delete
     *    - replace
     *
     * Used in Doctrine_Template_Listener_Cachetaggable to have right connection
     * for checking Doctrine transaction state.
     *
     * @return Doctrine_Connection
     */
    public function getCurrentConnection ()
    {
      return $this->userConnection
        ? $this->userConnection
        : $this->getTable()->getConnection();
    }

    /**
     * {@inheritdoc}
     */
    public function save (Doctrine_Connection $conn = null)
    {
      $this->setUserConnection($conn);

      return parent::save($conn);
    }

    /**
     * {@inheritdoc}
     */
    public function delete (Doctrine_Connection $conn = null)
    {
      $this->setUserConnection($conn);

      return parent::delete($conn);
    }

    /**
     * {@inheritdoc}
     */
    public function replace (Doctrine_Connection $conn = null)
    {
      $this->setUserConnection($conn);

      return parent::replace($conn);
    }

    /**
     * {@inheritdoc}
     */
    public function free ($deep = false)
    {
      unset($this->userConnection);

      return parent::free($deep);
    }
  }