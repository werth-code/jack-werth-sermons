<?php
// Static-export helper (copied into WP only during `publish.sh`, then removed):
// render every sermon of a book on a single page so the static site needs no pagination.
add_action('pre_get_posts', function ($q) {
	if (is_admin() || !$q->is_main_query()) return;
	if ($q->is_tax('bible_book')) $q->set('posts_per_page', -1);
}, 99);
