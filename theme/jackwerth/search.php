<?php
/** Site-wide search results (sermons + pages). */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<section class="section section--tight">
	<div class="wrap">
		<div class="crumbs"><a href="<?php echo esc_url( home_url() ); ?>">Home</a><span class="sep">/</span><span>Search</span></div>
		<span class="kicker">Search Results</span>
		<h1 style="margin:.4rem 0 .6rem">“<?php echo esc_html( get_search_query() ); ?>”</h1>
		<p class="lede"><b style="color:var(--brass)"><?php echo esc_html( number_format_i18n( $GLOBALS['wp_query']->found_posts ) ); ?></b>
			result<?php echo $GLOBALS['wp_query']->found_posts == 1 ? '' : 's'; ?>.
			<a href="<?php echo esc_url( get_post_type_archive_link( 'sermon' ) ); ?>">Use advanced sermon filters →</a></p>
	</div>
</section>
<section class="section" style="padding-top:0">
	<div class="wrap">
		<div class="card-grid">
			<?php if ( have_posts() ) : while ( have_posts() ) : the_post();
				if ( get_post_type() === 'sermon' ) {
					get_template_part( 'template-parts/sermon-card' );
				} else { ?>
					<article class="scard reveal">
						<div class="top"><span class="tag"><?php echo esc_html( get_post_type() ); ?></span></div>
						<h3 class="passage"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<p class="excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>
					</article>
				<?php }
			endwhile; else : ?>
				<p class="lede">Nothing found. Try the <a href="<?php echo esc_url( get_post_type_archive_link( 'sermon' ) ); ?>">sermon library</a>.</p>
			<?php endif; ?>
		</div>
		<div class="pagination"><?php echo paginate_links( [ 'mid_size' => 1, 'prev_text' => '‹', 'next_text' => '›' ] ); ?></div>
	</div>
</section>
<?php get_footer();
