<?php include_partial(
  'ten_posts_partial_cached',
  array(
    'posts' => $posts,
    'sf_cache_key' => 'index-page-ten-posts-enabled-partial',
    'sf_cache_tags' => $posts->getCacheTags(),
  )) ?>

<?php include_partial(
  'ten_posts_partial_not_cached',
  array(
    'posts' => $posts,
    'sf_cache_key' => 'index-page-ten-posts-disabled-partial',
    'sf_cache_tags' => $posts->getCacheTags(),
  )) ?>

<?php include_component(
  'blog_post', 'tenPostsComponentCached',
  array(
    'sf_cache_key' => 'index-page-ten-posts-enabled-component',
  )) ?>

<?php include_component(
  'blog_post', 'tenPostsComponentNotCached',
  array(
    'sf_cache_key' => 'index-page-ten-posts-disabled-component',
  )) ?>


<?php include_partial('partial_example', array(
  'sf_cache_tags' => array(
    'ExampleTag' => 1298231,
    'ExampleTag:1' => 237343,
    'ExampleTag:3' => 35989283,
  ),
)) ?>
