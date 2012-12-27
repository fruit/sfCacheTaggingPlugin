<pre>
<?php foreach ($tree->fetchRoots() as $root): ?>

  <?php echo str_repeat('  ', $root->getLevel()) ?> <?php echo $root->getName() ?>
  <?php foreach ($tree->fetchTree(array('root_id' => $root->getId())) as $node): ?>

    <?php echo str_repeat('  ', $node->getLevel()) ?> <?php echo $node->getName() ?>
  <?php endforeach; ?>
<?php endforeach; ?>
</pre>