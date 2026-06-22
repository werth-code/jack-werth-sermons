<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
</main><!-- #main -->

<footer class="site-foot">
	<div class="wrap">
		<div class="foot-grid">
			<div class="foot-brand">
				<div class="name"><?php
					$p = explode( ' ', jw_brand( 'name' ), 2 );
					echo count( $p ) === 2 ? esc_html( $p[0] ) . ' <b>' . esc_html( $p[1] ) . '</b>' : '<b>' . esc_html( jw_brand( 'name' ) ) . '</b>';
				?></div>
				<p>A library of expository, verse-by-verse preaching — for pastors preparing to preach and every believer longing to go deeper into the Scriptures.</p>
			</div>
			<div class="foot-col">
				<h5>Explore</h5>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'sermon' ) ); ?>">All Sermons</a>
				<a href="<?php echo esc_url( home_url( '/books/' ) ); ?>">Books of the Bible</a>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'sermon' ) ); ?>?service=morning-service">Sunday Mornings</a>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'sermon' ) ); ?>?service=evening-service">Evening Services</a>
			</div>
			<div class="foot-col">
				<h5>Study</h5>
				<a href="<?php echo esc_url( home_url( '/for-pastors/' ) ); ?>">For Pastors</a>
				<a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">About Jack Werth</a>
				<a href="<?php echo esc_url( home_url( '/store/' ) ); ?>">Outlines &amp; Books</a>
				<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a>
			</div>
			<div class="foot-col">
				<h5>Subscribe</h5>
				<a href="<?php echo esc_url( home_url( '/feed/sermons' ) ); ?>">Podcast Feed (RSS)</a>
				<a href="https://archive.org/details/@webmaster_lbref_org" target="_blank" rel="noopener">Audio Archive</a>
				<a href="<?php echo esc_url( home_url( '/wp-sitemap.xml' ) ); ?>">Sitemap</a>
			</div>
		</div>
		<div class="foot-bottom">
			<span>In loving memory of Pastor Jack Werth &middot; 1953&ndash;2023</span>
			<span><?php echo esc_html( number_format_i18n( jw_total_sermons() ) ); ?> sermons &middot; Soli Deo Gloria</span>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
