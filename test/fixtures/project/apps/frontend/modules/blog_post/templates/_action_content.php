<br />
<br />
<br />
<div class="posts">
<?php foreach ($posts as $post) { ?>
  <a
     href="<?php print url_for("blog_post/updateBlogPost?id={$post->getId()}&title={$post->getTitle()}_new&return={$layout}") ?>"
     id="<?php print $post->getSlug() ?>"
     title="<?php print $post->getTitle() ?>"><?php print $post->getTitle() ?></a>
  <br />
<?php } ?>
</div>