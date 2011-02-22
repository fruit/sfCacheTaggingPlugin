<?php
  
  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * UnitOfWork to remove only tags through all relations with Cachetaggable
   * behavior. If relation is a Doctrine cascade delete, then it will skip, due
   * to automatical machanisms.
   * 
   * Most part of code stolen from Doctrine_Connection_UnitOfWork witch
   * authors are:
   *   Konsta Vesterinen <kvesteri@cc.hut.fi>
   *   Roman Borschel <roman@code-factory.org>
   * Revision: 7684
   *
   * @package sfCacheTaggingPlugin
   * @subpackage doctrine
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class Doctrine_Connection_CachetaggableUnitOfWork extends Doctrine_Connection_Module
  {
    /**
     * @see Doctrine_Connection_UnitOfWork::delete() copy&past from
     * 
     * @param Doctrine_Record $record 
     * @return array
     */
    public function getRelatedTags (Doctrine_Record $record)
    {
      $deletions = array();
      
      $this->collectDeletions($record, $deletions);
      
      return array_flip($deletions);
    }

    /**
     * @see Doctrine_Connection_UnitOfWork::_collectDeletions() copy&past from
     *
     * @param array $deletions  Map of the records to delete. Keys=Oids Values=Records.
     */
    private function collectDeletions (Doctrine_Record $record, array & $deletions)
    {
      if ( ! $record->exists())
      {
        return;
      }
      
      if (! $record->getTable()->hasTemplate(sfCacheTaggingToolkit::TEMPLATE_NAME))
      {
        return;
      }

      $deletions[$record->getOid()] = $record->obtainTagName();
      $this->cascadeDelete($record, $deletions);
    }

    /**
     * @see Doctrine_Connection_UnitOfWork::_cascadeDelete() copy&past from
     *
     * @param Doctrine_Record  The record for which the delete operation will be cascaded.
     * @throws PDOException    If something went wrong at database level
     * @return null
     */
    protected function cascadeDelete (Doctrine_Record $record, array & $deletions)
    {
      foreach ($record->getTable()->getRelations() as $relation)
      {
        /**
         * @todo may be incorrect logic - main idea to check it for root relations
         */
        if (! $relation->isCascadeDelete())
        {
          $fieldName = $relation->getAlias();
          
          // if it's a xToOne relation and the related object is already loaded
          // we don't need to refresh.
          if ( ! ($relation->getType() == Doctrine_Relation::ONE && isset($record->$fieldName)))
          {
            $record->refreshRelated($relation->getAlias());
          }
          
          $relatedObjects = $record->get($relation->getAlias());
          
          if (
            $relatedObjects instanceof Doctrine_Record && $relatedObjects->exists()
              && 
            ! isset($deletions[$relatedObjects->getOid()])
          )
          {
            $this->collectDeletions($relatedObjects, $deletions);
          }
          else if ($relatedObjects instanceof Doctrine_Collection && count($relatedObjects) > 0)
          {
            if (! $relatedObjects->getTable()->hasTemplate(sfCacheTaggingToolkit::TEMPLATE_NAME))
            {
              continue;
            }
      
            // cascade the delete to the other objects
            foreach ($relatedObjects as $object)
            {
              if ( ! isset($deletions[$object->getOid()]))
              {
                $this->collectDeletions($object, $deletions);
              }
            }
          }
        }
      }
    }

  }