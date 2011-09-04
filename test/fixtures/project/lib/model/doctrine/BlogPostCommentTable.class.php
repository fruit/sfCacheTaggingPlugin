<?php

class BlogPostCommentTable extends Doctrine_Table
{
  /**
   * Returns BlogPostCommentTable instance
   *
   * @return BlogPostCommentTable
   */
  public static function getInstance ()
  {
    return Doctrine::getTable('BlogPostComment');
  }

  /**
   * Initialize new Doctrine_Query instance or clones if $q is passed
   *
   * @param null|Doctrine_Query $q optional
   * @return Doctrine_Query
   */
  private function createQueryIfNull (Doctrine_Query $q = null)
  {
    return is_null($q) ? self::getInstance()->createQuery('bpc') : clone $q;
  }

  /**
   * Adds all BlogPostCommentTable columns to the query
   *
   * @param Doctrine_Query $q
   * @return {$TableName}Table
   */
  public function addSelectTableColumns (Doctrine_Query $q)
  {
    $q->addSelect("{$q->getRootAlias()}.*");

    return $this;
  }

  /**
   * Default getQuery method to work directly with Doctrine_Query object
   *
   * @param Doctrine_Query $q
   * @return Doctrine_Query
   */
  public function getQuery (Doctrine_Query $q = null)
  {
    $q = $this->createQueryIfNull($q);

    $this
      ->addSelectTableColumns($q)
    ;

    return $q;
  }

  /**
   * Method to fetch query with predefined filters
   *
   * @param Doctrine_Query $q
   * @return BlogPostCommentTable
   */
  public function get (Doctrine_Query $q = null)
  {
    $q = $this->getQuery($q);

    return $this;
  }

  
}
