<?php

class blog_postComponents extends sfComponents
{
  /**
   * Component "IndexNews"
   *
   * @param sfWebRequest $request
   */
  public function executeIndexNews($request)
  {
    $posts = BlogPostTable::getInstance()->getPostsQuery()->execute();

    $this->setContentTags($posts);

    $this->posts = $posts;
  }

  /**
   * Component "IndexNews"
   *
   * @param sfWebRequest $request
   */
  public function executeIndexNewsNone($request)
  {
    return sfView::NONE;
  }

  /**
   * Component "IndexNewsComments"
   *
   * @param sfWebRequest $request
   */
  public function executeIndexNewsComments($request)
  {
    $posts = BlogPostTable::getInstance()->getPostsWithCommentQuery()->execute();

    foreach ($posts as $post)
    {
      $posts->addCacheTags($post->getBlogPostComment());
    }

    $this->setContentTags($posts);

    $this->posts = $posts;
  }

  /**
   * Component "MySlot"
   *
   * @param sfWebRequest $request
   */
  public function executeMySlot($request)
  {
    $posts = BlogPostTable::getInstance()->getPostsWithCommentAndCountQuery()->execute();

    foreach ($posts as $post)
    {
      $posts->addCacheTags($post->getBlogPostComment());
    }

    $this->setContentTags($posts);

    $this->posts = $posts;
  }


  public function executeTenPostsComponentCached ($request)
  {
    $posts = BlogPostTable::getInstance()->getPostsQuery()->execute();

    $this->setContentTags($posts);

    $this->posts = $posts;
  }

  public function executeTenPostsComponentNotCached ($request)
  {
    $posts = BlogPostTable::getInstance()->getPostsQuery()->execute();

    $this->posts = $posts;
  }

  public function executeComponentExample ($request)
  {

  }


}
