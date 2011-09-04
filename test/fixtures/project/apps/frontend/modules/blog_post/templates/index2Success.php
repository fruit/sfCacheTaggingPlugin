Random on action page: <?php print rand(2, 100); ?><br /><br />

<h1>Work!</h1>

<?php include_partial('blog_post/indexNews2', array(
  'posts' => $posts,
  'sf_cache_key' => 'posts-news2',
  'sf_cache_tags' => $posts,
)); ?>