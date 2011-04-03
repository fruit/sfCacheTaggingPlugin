  public function executeEdit(sfWebRequest $request)
  {
    $this-><?php echo $this->getSingularName() ?> = $this->getRoute()->getObject();
    $this->form = $this->configuration->getForm($this-><?php echo $this->getSingularName() ?>);

    $notice = $this->getUser()->getFlash('notice');
    $error = $this->getUser()->getFlash('error');

    /**
     * Do not cache flash messages
     */
    if (($notice || $error) && sfConfig::get('sf_cache'))
    {
      $this->disableCache(/*$this->getModuleName(), $this->getActionName()*/);
    }

    $this->setContentTags($this->form->getObject());
  }
