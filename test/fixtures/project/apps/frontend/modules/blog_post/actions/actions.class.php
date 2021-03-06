<?php

  /**
   * blog_post actions.
   *
   * @package    playground
   * @subpackage blog_post
   */
  class blog_postActions extends sfActions
  {
    /**
     * Handling call the action Tree
     *
     * @var $request sfWebRequest
     */
    public function executeTree (sfWebRequest $request)
    {
      $treeTable = Doctrine::getTable('Tree');

//      $this->music = $treeTable->findOneByName('music');
      $this->tree = $treeTable->getTree();

      $this->callAAAA();
//      $jazz = new Tree();
//      $jazz->name = 'Jazz';
//      $jazz->getNode()->insertAsLastChildOf($music);
    }


    /**
     * Executes index action
     *
     * @param sfRequest $request A request object
     */
    public function executeActionWithLayout (sfWebRequest $request)
    {
      $posts = BlogPostTable::getInstance()->getPostsQuery()->execute();

      $this->setContentTags($posts);

      $this->posts = $posts;
    }

    /**
     * Executes index action
     *
     * @param sfRequest $request A request object
     */
    public function executeActionWithoutLayout (sfWebRequest $request)
    {

      $posts = BlogPostTable::getInstance()->getPostsQuery()->execute();

      $this->setContentTags($posts);

      $this->posts = $posts;
    }

    /**
     * Handling call the action ActionWithDisabledCache
     *
     * @var $request sfWebRequest
     */
    public function executeActionWithDisabledCache (sfWebRequest $request)
    {

    }

    /**
     * Handling call the action UpdateBlogPost
     *
     * @var $request sfWebRequest
     */
    public function executeUpdateBlogPost(sfWebRequest $request)
    {
      $b = BlogPostTable::getInstance()->find($request->getParameter('id'));

      $b->setTitle($request->getParameter('title'));

      $this->getContext()->getConfiguration()->loadHelpers(array('Url'));

      switch ($request->getParameter('return'))
      {
        case 'without':
          $returnAction = 'blog_post/actionWithoutLayout';
          break;

        case 'with':
          $returnAction = 'blog_post/actionWithLayout';
          break;

        default:
          $this->forward404('unknown return');
          break;
      }

      $b->save();

      $this->redirect($returnAction);
    }

    /**
     * Handling call the action Some
     *
     * @var $request sfWebRequest
     */
    public function executeIndex(sfWebRequest $request)
    {
      $this->getContentTags();
    }

    /**
     * Handling call the action Index2
     *
     * @var $request sfWebRequest
     */
    public function executeIndex2 (sfWebRequest $request)
    {
      $posts = BlogPostTable::getInstance()->getPostsQuery()->execute();

      $this->posts = $posts;
    }

    /**
     * Handling call the action Page
     *
     * @var $request sfWebRequest
     */
    public function executePage (sfWebRequest $request)
    {
      $posts = BlogPostTable::getInstance()->getPostsQuery()->execute();

      $this->posts = $posts;

      $this->setContentTags($posts);
    }

    /**
     * @var $request sfWebRequest
     */
    public function executeActionWithBlocks (sfWebRequest $request)
    {
      $this->posts = BlogPostTable::getInstance()->getPostsQuery()->execute();
    }

    /**
     * Handling call the action Welcome
     *
     * @var $request sfWebRequest
   */
    public function executeWelcome (sfWebRequest $request)
    {

    }

    /**
     * Handling call the action SignIn
     *
     * @var $request sfWebRequest
     */
    public function executeSignIn (sfWebRequest $request)
    {
      $user = $this->getUser();
      /* @var $user sfBasicSecurityUser */

      if (! $request->isMethod(sfRequest::POST))
      {
        return sfView::ERROR;
      }

      if ('' === trim($request->getPostParameter('password'), '*'))
      {
        $user->setAuthenticated(true);
        $user->setAttribute('username', $request->getPostParameter('username'));
        $user->setAttribute('user_id', 5919);

        $this->redirect('blog_post/welcome');
      }

      return sfView::ERROR;
    }


    /**
     * Handling call the action ActionWithAutoSignIn
     *
     * @var $request sfWebRequest
     */
    public function executeActionWithAutoSignIn (sfWebRequest $request)
    {
      $user = $this->getUser();

      /* @var $user sfBasicSecurityUser */

      $user->setAttribute('username', 'anonymous');
      $user->setAttribute('user_id',  1998);

      $user->setAuthenticated(true);
    }
  }