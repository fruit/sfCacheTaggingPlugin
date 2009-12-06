<?php

/*
 * This file is part of the sfCacheTaggingPlugin package.
 * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A View to render partials.
 * 
 * Disables default symfony partial caching and enables taggable caching
 * (based on sfPartialView)
 *
 * @package sfCacheTaggingPlugin
 * @author Ilya Sabelnikov <fruit.dev@gmail.com>
 */
class sfPartialTagView extends sfPHPView
{
  protected
    $partialVars = array();

  /**
   * @param array $partialVars
   */
  public function setPartialVars(array $partialVars)
  {
    $this->partialVars = $partialVars;
    $this->getAttributeHolder()->add($partialVars);
  }

  /**
   * Configures template for this view.
   */
  public function configure()
  {
    $this->setDecorator(false);
    $this->setTemplate($this->actionName.$this->getExtension());
    if ('global' == $this->moduleName)
    {
      $this->setDirectory($this->context->getConfiguration()->getDecoratorDir($this->getTemplate()));
    }
    else
    {
      $this->setDirectory($this->context->getConfiguration()->getTemplateDir($this->moduleName, $this->getTemplate()));
    }
  }

  /**
   * Renders the presentation.
   *
   * @return string Current template content
   */
  public function render()
  {
    if (sfConfig::get('sf_debug') && sfConfig::get('sf_logging_enabled'))
    {
      $timer = sfTimerManager::getTimer(sprintf('Partial "%s/%s"', $this->moduleName, $this->actionName));
    }

    // execute pre-render check
    $this->preRenderCheck();

    $this->getAttributeHolder()->set('sf_type', 'partial');

    // render template
    $retval = $this->renderFile($this->getDirectory().'/'.$this->getTemplate());

    if (sfConfig::get('sf_debug') && sfConfig::get('sf_logging_enabled'))
    {
      $timer->addTime();
    }

    return $retval;
  }
}
