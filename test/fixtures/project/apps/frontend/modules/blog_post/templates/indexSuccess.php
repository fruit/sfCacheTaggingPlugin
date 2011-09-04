Random on action page: <?php print rand(2, 100); ?><br /><br />

<fieldset>
  <legend>indexNews.php component</legend>
  <?php include_component($this->getModuleName(), 'indexNews') ?>
</fieldset>

<fieldset>
  <legend>indexNewsComments.php component 1</legend>

  <?php include_component($this->getModuleName(), 'indexNewsComments') ?>
</fieldset>

<fieldset>
  <legend>slot component</legend>
  <?php //include_component_slot('mysuperslot') ?>
</fieldset>


<?php include_partial('partial_example', array(
  'sf_cache_tags' => array(
    'ExampleTag' => 1298231,
    'ExampleTag:1' => 237343,
    'ExampleTag:3' => 35989283,
  ),
)) ?>