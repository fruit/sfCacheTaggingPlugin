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
     * Handling call the action Run
     *
     * @var $request sfWebRequest
     */
    public function executeRun (sfWebRequest $request)
    {

    }

    /**
     * Handling call the action UnknownMethodTest
     *
     * @var $request sfWebRequest
     */
    public function executeUnknownMethodTest (sfWebRequest $request)
    {
      $this->callSomeUnknownMethod();
    }

    /**
     * Handling call the action ValidMethodTest
     *
     * @var $request sfWebRequest
     */
    public function executeValidMethodTest (sfWebRequest $request)
    {
      $this->tags = $this->getContentTags();
    }

    public function executeCallDoctrineRecordMethodTest (sfWebRequest $request)
    {
      $post = BlogPostTable::getInstance()->createQuery()->limit(1)->fetchOne();

      $method = $request->getParameter('method');

      if ($params = $request->getParameter('args'))
      {
        call_user_func_array(array($post, $method), $params);
      }
      else
      {
        call_user_func(array($post, $method));
      }

      $this->method = $method;

      $this->className = 'Doctrine_Record';

      $this->setTemplate('callMethod');
    }

    public function executeCallDoctrineCollectionMethodTest (sfWebRequest $request)
    {
      $posts = BlogPostTable::getInstance()->findAll();

      $method = $request->getParameter('method');

      if ($params = $request->getParameter('args'))
      {
        call_user_func_array(array($posts, $method), $params);
      }
      else
      {
        call_user_func(array($posts, $method));
      }

      $this->method = $method;

      $this->className = 'Doctrine_Collection';

      $this->setTemplate('callMethod');
    }
  }
