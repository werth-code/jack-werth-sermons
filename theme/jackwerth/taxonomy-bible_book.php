<?php
/** A single book of the Bible — its sermons, in reading order. */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
$term = get_queried_object();
$test = class_exists( 'JW_Bible' ) ? JW_Bible::testament( $term->name ) : '';
$testL= [ 'OT' => 'Old Testament', 'NT' => 'New Testament' ][ $test ] ?? '';
?>
<section class="section section--tight">
	<div class="wrap">
		<div class="crumbs">
			<a href="<?php echo esc_url( home_url() ); ?>">Home</a><span class="sep">/</span>
			<a href="<?php echo esc_url( home_url( '/books/' ) ); ?>">Books</a><span class="sep">/</span>
			<span><?php echo esc_html( $term->name ); ?></span>
		</div>
		<span class="kicker"><?php echo esc_html( $testL ); ?></span>
		<h1 style="margin:.4rem 0 .5rem"><?php echo esc_html( $term->name ); ?></h1>
		<p class="lede"><b style="color:var(--brass)"><?php echo esc_html( $term->count ); ?></b>
			sermon<?php echo $term->count == 1 ? '' : 's'; ?> working through <?php echo esc_html( $term->name ); ?>,
			in order — listen straight through as a study of the whole book.</p>
		<?php if ( $term->count > 1 ) : ?>
		<p style="margin-top:1.5rem">
			<button class="btn playall" data-playall type="button">
				<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
				Play <?php echo esc_html( $term->name ); ?> in Order
			</button>
		</p>
		<?php endif; ?>
	</div>
</section>

<section class="section" style="padding-top:0">
	<div class="wrap">
		<div class="card-grid">
			<?php while ( have_posts() ) : the_post(); get_template_part( 'template-parts/sermon-card' ); endwhile; ?>
		</div>
		<div class="pagination"><?php echo paginate_links( [ 'mid_size' => 1, 'prev_text' => '‹', 'next_text' => '›' ] ); ?></div>
	</div>
</section>
<?php get_footer();
