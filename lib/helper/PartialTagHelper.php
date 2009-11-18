<?php

function get_component_tag($moduleName, $componentName, $vars = array())
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
}

function include_component_tag($moduleName, $componentName, $vars = array())
{
  echo get_component_tag($moduleName, $componentName, $vars);
}