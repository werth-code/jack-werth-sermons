<?php
/** Generic fallback (blog/archive). */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<section class="section section--tight">
	<div class="wrap">
		<span class="kicker"><?php echo esc_html( jw_brand( 'name' ) ); ?></span>
		<h1 style="margin:.4rem 0 1rem"><?php
			if ( is_home() ) echo 'Journal';
			elseif ( is_archive() ) the_archive_title();
			else echo 'Latest';
		?></h1>
	</div>
</section>
<section class="section" style="padding-top:0">
	<div class="wrap">
		<div class="card-grid">
			<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
				<article class="scard reveal">
					<div class="top"><span class="date"><?php echo esc_html( get_the_date() ); ?></span></div>
					<h3 class="passage"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
					<p class="excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
				</article>
			<?php endwhile; else : ?>
				<p class="lede">Nothing here yet.</p>
			<?php endif; ?>
		</div>
		<div class="pagination"><?php echo paginate_links( [ 'mid_size' => 1, 'prev_text' => '‹', 'next_text' => '›' ] ); ?></div>
	</div>
</section>
<?php get_footer();
