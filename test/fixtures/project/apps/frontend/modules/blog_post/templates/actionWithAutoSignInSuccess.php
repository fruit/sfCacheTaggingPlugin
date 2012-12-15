<?php include_partial('partial_example', array(
  'sf_cache_tags' => array('guest_only' => 1, ),
  'sf_cache_key' => 'auto-signin-partial',
)) ?>


{COMPONENT}
<?php include_component(
  'blog_post', 'tenPostsComponentCached',
  array(
    'sf_cache_key' => 'index-page-ten-posts-enabled-component',
  )) ?>
{/COMPONENT}


<hr />
You are signed-in automatically.