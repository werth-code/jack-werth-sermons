<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<form role="search" method="get" class="jw-searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="s">Search</label>
	<input type="search" id="s" name="s" placeholder="Search sermons…" value="<?php echo esc_attr( get_search_query() ); ?>">
	<button type="submit" class="btn">Search</button>
</form>
