<?php foreach ($posts as $post) { ?>
<i><?php print $post->getTitle() ?></i> <u><?php print $post->getBlogPostCommentCount() ?></u><br />
<?php } ?>