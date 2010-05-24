<?php

  /*
   * This file is part of the sfCacheTaggingPlugin package.
   * (c) 2009-2010 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * Evaluates and returns a component.
   * The syntax is similar to the one of include_component_tag
   *
   * @param  string $moduleName     module name
   * @param  string $componentName  component name
   * @param  array  $vars           variables to be made accessible to the component
   * @return string|void Component content
   */
  function get_component_tag ($moduleName, $componentName, $vars = array())
  {
    $context = sfContext::getInstance();
    $actionName = '_'.$componentName;

    $view = new sfPartialTagView($context, $moduleName, $actionName, '');
    $view->setPartialVars($vars);

    $allVars = _call_component($moduleName, $componentName, $vars);

    if (null !== $allVars)
    {
      // render
      $view->getAttributeHolder()->add($allVars);

      return $view->render();
    }

    return;
  }

  /**
   * Evaluates and echoes a component.
   * For a variable to be accessible to the component and its partial,
   * it has to be passed in the third argument.
   *
   * @param  string $moduleName     module name
   * @param  string $componentName  component name
   * @param  array  $vars           variables to be made accessible to the component
   * @return string
   */
  function include_component_tag ($moduleName, $componentName, $vars = array())
  {
    echo get_component_tag($moduleName, $componentName, $vars);
  }