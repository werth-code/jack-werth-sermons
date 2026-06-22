<?php
/** "Books of the Bible" index — the whole canon, lit where sermons exist. */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$terms = get_terms( [ 'taxonomy' => 'bible_book', 'hide_empty' => false ] );
$by    = [];
foreach ( (array) $terms as $t ) { if ( ! is_wp_error( $terms ) ) $by[ strtolower( $t->name ) ] = $t; }
$all   = class_exists( 'JW_Bible' ) ? JW_Bible::BOOKS : [];
?>
<section class="section section--tight">
	<div class="wrap">
		<div class="crumbs"><a href="<?php echo esc_url( home_url() ); ?>">Home</a><span class="sep">/</span><span>Books</span></div>
		<span class="kicker">The Whole Counsel of God</span>
		<h1 style="margin:.4rem 0 .6rem">Books of the Bible</h1>
		<p class="lede">Browse the library by book. Lit titles have sermons you can study today; the rest mark
		the road still ahead. Choose a book and listen through it, verse by verse.</p>
	</div>
</section>

<section class="section" style="padding-top:0">
	<div class="wrap">
		<?php foreach ( [ 'OT' => 'Old Testament', 'NT' => 'New Testament' ] as $code => $label ) : ?>
		<div class="testament">
			<h3><?php echo esc_html( $label ); ?></h3>
			<div class="book-grid">
				<?php foreach ( $all as $name => $t ) :
					if ( $t !== $code ) continue;
					$term = $by[ strtolower( $name ) ] ?? null;
					$has  = $term && $term->count > 0;
					if ( $has ) : ?>
						<a class="book" href="<?php echo esc_url( get_term_link( $term ) ); ?>">
							<span class="bname"><?php echo esc_html( $name ); ?></span>
							<span class="bcount"><b><?php echo esc_html( $term->count ); ?></b> sermon<?php echo $term->count == 1 ? '' : 's'; ?></span>
						</a>
					<?php else : ?>
						<span class="book" style="opacity:.4;cursor:default">
							<span class="bname"><?php echo esc_html( $name ); ?></span>
							<span class="bcount">—</span>
						</span>
					<?php endif;
				endforeach; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
</section>
<?php get_footer();
