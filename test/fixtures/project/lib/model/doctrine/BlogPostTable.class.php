<?php

class BlogPostTable extends Doctrine_Table
{
  /**
   * Returns BlogPostTable instance
   *
   * @return BlogPostTable
   */
  public static function getInstance ()
  {
    return Doctrine::getTable('BlogPost');
  }

  /**
   * Initialize new Doctrine_Query instance or clones if $q is passed
   *
   * @param null|Doctrine_Query $q optional
   * @return Doctrine_Query
   */
  private function createQueryIfNull (Doctrine_Query $q = null)
  {
    return is_null($q) ? self::getInstance()->createQuery('bp') : clone $q;
  }

  /**
   * Adds all BlogPostTable columns to the query
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
   * Adds count by foreign table as %foreign_table_name%_count column
   *
   * @return BlogPostCommentTable
   */
  public function addSelectBlogPostCommentCount (Doctrine_Query $q)
  {
    $subq = $q->createSubquery()
        ->select('count(bpcc.blog_post_id)')
        ->from('BlogPostComment bpcc')
        ->where("{$q->getRootAlias()}.id = bpcc.blog_post_id")
    ;

    $subModelNameTableized = Doctrine_Inflector::tableize('BlogPostComment');

    $q->addSelect("({$subq->getDql()}) {$subModelNameTableized}_count");

    return $this;
  }

  /**
   * Adds join to the BlogPostCommentTable and adds its columns to SELECT block
   *
   * @param Doctrine_Query $q
   * @return BlogPostTable
   */
  public function withBlogPostComment (Doctrine_Query $q)
  {
    $q
      ->addSelect('bpc.*')
      ->leftJoin("{$q->getRootAlias()}.BlogPostComment bpc")
    ;

    return $this;
  }


  /**
   *
   * @param Doctine_Query $q
   * @return Doctine_Query
   */
  public function getPostsWithCommentQuery (Doctine_Query $q = null)
  {
    $q = $this->getPostsQuery($q);

    $this
      ->withBlogPostComment($q)
    ;

    return $q;
  }

  /**
   * Adds Translation table to BlogPostTable with specified culture (or system-user culture)
   *
   * @param Doctrine_Query $q
   * @param string $culture Lowercased culture code
   * @return BlogPostTable
   */
  public function withI18n (Doctrine_Query $q, $culture = null)
  {
    $culture = is_null($culture) ? sfContext::getInstance()->getUser()->getCulture() : $culture;

    $q
      ->addSelect("{$q->getRootAlias()}t.*")
      ->leftJoin(
        "{$q->getRootAlias()}.Translation {$q->getRootAlias()}t WITH {$q->getRootAlias()}t.lang = ?",
        $culture
      )
    ;

    return $this;
  }

  /**
   *
   * @param Doctine_Query $q
   * @return Doctine_Query
   */
  public function getPostsWithCommentAndCountQuery (Doctine_Query $q = null)
  {
    $q = $this->getPostsQuery($q);

    $this
      ->addSelectBlogPostCommentCount($q)
      ->withBlogPostComment($q)
    ;

    return $q;
  }

  /**
   *
   * @param Doctine_Query $q
   * @return Doctine_Query
   */
  public function getPostsQuery (Doctine_Query $q = null)
  {
    $q = $this->createQueryIfNull($q);

    $q->orderBy('id asc')->addWhere('is_enabled = 1');

    $this
      ->addSelectTableColumns($q)
      ->withI18n($q)
    ;

    return $q;
  }
}
