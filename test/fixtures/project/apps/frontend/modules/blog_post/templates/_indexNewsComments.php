<?php
  /**
   * @var $post BlogPost
   * @var $comment BlogPostComment
   */
?>
<h2>RAND: <?php print rand(2, 100); ?></h2>

<?php if (0 < $posts->count()) { ?>
<ul>
  <?php foreach ($posts as $post) { ?>
  <li>
    <?php print $post->getTitle() ?>
    <pre><?php print $post->getContent() ?></pre>

    <ul>
      <?php foreach ($post->getBlogPostComment() as $comment) { ?>
        <li><?php print $comment->getAuthor() ?>: <?php print $comment->getMessage() ?></li>
      <?php } ?>
    </ul>
  </li>
  <?php } ?>
</ul>
<?php } ?>
