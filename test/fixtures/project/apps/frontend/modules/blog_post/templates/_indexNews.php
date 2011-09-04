<?php
  /**
   * @var $post BlogPost
   */
?>
<?php if (0 < $posts->count()) {  ?>
<br />

<h1>Post listing <?php print rand(2, 1238123) ?></h1>
<ul>
  <?php foreach ($posts as $post) { ?>
  <li>
    <?php print $post->getTitle() ?>
  </li>
  <pre><?php print $post->getContent() ?></pre>
  <?php } ?>
</ul>
<?php } ?>
