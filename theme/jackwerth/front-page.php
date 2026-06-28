<?php
/** Front page — the Jack Werth library entrance. */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

// Hero feature card: a hand-picked sermon flagged _jw_featured if one exists,
// otherwise fall back to the most recent sermon.
$featured_q = new WP_Query( [ 'post_type' => 'sermon', 'posts_per_page' => 1, 'no_found_rows' => true,
	'meta_query' => [ [ 'key' => '_jw_featured', 'value' => '1' ] ] ] );
$is_featured = $featured_q->have_posts();
if ( ! $is_featured ) {
	$featured_q = new WP_Query( [ 'post_type' => 'sermon', 'posts_per_page' => 1, 'no_found_rows' => true,
		'meta_key' => '_jw_date', 'orderby' => 'meta_value', 'order' => 'DESC' ] );
}
$feat = $featured_q->have_posts() ? $featured_q->posts[0] : null;
$books  = jw_books_with_counts();
$nbooks = count( $books['OT'] ) + count( $books['NT'] );
$years  = jw_sermon_years();
$span   = $years ? ( end( $years ) . '–' . reset( $years ) ) : '';
?>

<!-- HERO ---------------------------------------------------------------->
<section class="hero">
	<div class="wrap hero-grid">
		<div class="hero-copy">
			<span class="kicker reveal">A Library of Exposition</span>
			<h1 class="reveal" data-delay="1">
				<span class="wm"><?php
					$p = explode( ' ', jw_brand( 'name' ), 2 );
					echo count( $p ) === 2 ? esc_html( $p[0] ) . ' <em>' . esc_html( $p[1] ) . '</em>' : esc_html( jw_brand( 'name' ) );
				?></span>
			</h1>
			<p class="lede reveal" data-delay="2">
				<?php echo esc_html( number_format_i18n( jw_total_sermons() ) ); ?> sermons working verse by verse
				through the Scriptures — for <em class="serif-italic">pastors</em> preparing to preach and every
				believer longing to go <em class="serif-italic">deeper</em> into the Word of God.
			</p>
			<div class="hero-cta reveal" data-delay="3">
				<a class="btn" href="<?php echo esc_url( get_post_type_archive_link( 'sermon' ) ); ?>">
					<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg> Enter the Library
				</a>
				<a class="btn btn--ghost" href="<?php echo esc_url( home_url( '/books/' ) ); ?>">Browse by Book</a>
			</div>
		</div>

		<?php if ( $feat ) : setup_postdata( $GLOBALS['post'] = $feat ); // phpcs:ignore
			$audio = jw_audio_url( $feat->ID ); ?>
		<aside class="feature-card reveal" data-delay="2">
			<span class="kicker"><?php echo $is_featured ? 'Featured Sermon' : 'Latest Sermon'; ?></span>
			<div class="passage"><?php echo esc_html( jw_meta( 'passage', $feat->ID ) ?: get_the_title( $feat ) ); ?></div>
			<div class="meta"><?php echo esc_html( jw_meta( 'service', $feat->ID ) ); ?> &middot; <?php echo esc_html( jw_pretty_date( $feat->ID ) ); ?></div>
			<div class="player-feature" style="padding:0;border:0;box-shadow:none;background:none;gap:1rem">
				<?php if ( $audio ) : ?>
				<button class="play" aria-label="Play"
					data-audio="<?php echo esc_url( $audio ); ?>"
					data-title="<?php echo esc_attr( jw_meta( 'passage', $feat->ID ) ); ?>"
					data-sub="<?php echo esc_attr( jw_meta( 'service', $feat->ID ) . ' · ' . jw_pretty_date( $feat->ID ) ); ?>">
					<svg class="i-play" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
					<svg class="i-pause" viewBox="0 0 24 24" fill="currentColor"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>
				</button>
				<?php endif; ?>
				<a class="link-more" href="<?php echo esc_url( get_permalink( $feat ) ); ?>">Open sermon <span>→</span></a>
			</div>
		</aside>
		<?php wp_reset_postdata(); endif; ?>
	</div>
</section>

<!-- STATS --------------------------------------------------------------->
<div class="wrap">
	<div class="stats reveal">
		<div class="stat"><div class="n"><?php echo esc_html( number_format_i18n( jw_total_sermons() ) ); ?></div><div class="l">Sermons</div></div>
		<div class="stat"><div class="n"><?php echo esc_html( $nbooks ); ?></div><div class="l">Books of the Bible</div></div>
		<div class="stat"><div class="n"><?php echo esc_html( $span ); ?></div><div class="l">Years of Preaching</div></div>
		<div class="stat"><div class="n">Verse<span style="color:var(--muted)">·by·</span>Verse</div><div class="l">Method</div></div>
	</div>
</div>

<!-- BROWSE BY BOOK ------------------------------------------------------>
<section class="section">
	<div class="wrap">
		<div class="sec-head">
			<div><span class="kicker">The Whole Counsel of God</span><h2>Browse by Book</h2></div>
			<a class="link-more" href="<?php echo esc_url( home_url( '/books/' ) ); ?>">All books <span>→</span></a>
		</div>

		<?php foreach ( [ 'OT' => 'Old Testament', 'NT' => 'New Testament' ] as $k => $label ) :
			if ( empty( $books[ $k ] ) ) continue; ?>
		<div class="testament reveal">
			<h3><?php echo esc_html( $label ); ?></h3>
			<div class="book-grid">
				<?php foreach ( $books[ $k ] as $b ) : ?>
				<a class="book" href="<?php echo esc_url( get_term_link( $b ) ); ?>">
					<span class="bname"><?php echo esc_html( $b->name ); ?></span>
					<span class="bcount"><b><?php echo esc_html( $b->count ); ?></b> sermon<?php echo $b->count == 1 ? '' : 's'; ?></span>
				</a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
</section>

<!-- MISSION (for pastors / for students) -------------------------------->
<section class="section section--tight">
	<div class="wrap split">
		<div class="mission reveal">
			<span class="kicker">For Pastors</span>
			<h3>Preparation that goes deep</h3>
			<p>Sit under years of careful, consecutive exposition. Trace how a faithful preacher handles a
			text — its structure, its doctrine, its application — as a model for your own study and pulpit work.</p>
			<ul>
				<li>Whole books preached straight through, verse by verse</li>
				<li>Search by passage to compare your text with the exposition</li>
				<li>Outlines &amp; study notes <span class="smallcaps">(coming soon)</span></li>
			</ul>
			<a class="btn btn--ghost" href="<?php echo esc_url( home_url( '/for-pastors/' ) ); ?>">How to use the library</a>
		</div>
		<div class="mission reveal" data-delay="1">
			<span class="kicker">For Every Believer</span>
			<h3>Know the Scriptures more truly</h3>
			<p>Follow a book of the Bible from first verse to last and watch its message unfold. No proof-texts —
			just patient, reverent teaching that helps you understand and love the Word.</p>
			<ul>
				<li>Listen free, anywhere — every sermon streams instantly</li>
				<li>Follow a series in order, like reading a book</li>
				<li>Discover deeper biblical concepts, one passage at a time</li>
			</ul>
			<a class="btn btn--ghost" href="<?php echo esc_url( get_post_type_archive_link( 'sermon' ) ); ?>">Start listening</a>
		</div>
	</div>
</section>

<!-- LATEST SERMONS ------------------------------------------------------>
<section class="section">
	<div class="wrap">
		<div class="sec-head">
			<div><span class="kicker">Most Recent</span><h2>Latest Sermons</h2></div>
			<a class="link-more" href="<?php echo esc_url( get_post_type_archive_link( 'sermon' ) ); ?>">All sermons <span>→</span></a>
		</div>
		<div class="card-grid">
			<?php
			$recent = new WP_Query( [ 'post_type' => 'sermon', 'posts_per_page' => 6, 'meta_key' => '_jw_date', 'orderby' => 'meta_value', 'order' => 'DESC' ] );
			while ( $recent->have_posts() ) : $recent->the_post();
				get_template_part( 'template-parts/sermon-card' );
			endwhile; wp_reset_postdata();
			?>
		</div>
	</div>
</section>

<?php get_footer();
