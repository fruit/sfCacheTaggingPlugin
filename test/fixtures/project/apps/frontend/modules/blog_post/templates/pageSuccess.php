<br />
<br />
<br />
<br />
<br />
<?php if (isset($posts)) { ?>

<?php foreach ($posts as $post) { ?>,
  <?php print $post->getTitle() ?>
<?php } ?>

<?php } else { ?>
 POSTS?
<?php } ?>