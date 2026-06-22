<?php
/** Single sermon — listen, study, go deeper. */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
while ( have_posts() ) : the_post();
	$id       = get_the_ID();
	$passage  = jw_meta( 'passage' ) ?: get_the_title();
	$book     = jw_meta( 'book' );
	$speaker  = jw_meta( 'speaker' ) ?: 'Jack Werth';
	$audio    = jw_audio_url( $id );
	$arid     = jw_meta( 'archive_id' );
	$book_term= get_the_terms( $id, 'bible_book' );
	$book_link= ( $book_term && ! is_wp_error( $book_term ) ) ? get_term_link( $book_term[0] ) : '';
	$product  = (int) jw_meta( 'product_id' );

	// Adjacent sermons within the same book (series order by date).
	$adj = function ( $newer ) use ( $id, $book_term ) {
		if ( ! $book_term || is_wp_error( $book_term ) ) return null;
		$d = jw_meta( 'date', $id );
		$q = new WP_Query( [
			'post_type' => 'sermon', 'posts_per_page' => 1, 'post__not_in' => [ $id ],
			'tax_query' => [ [ 'taxonomy' => 'bible_book', 'field' => 'term_id', 'terms' => $book_term[0]->term_id ] ],
			'meta_key' => '_jw_date', 'orderby' => 'meta_value', 'order' => $newer ? 'ASC' : 'DESC',
			'meta_query' => [ [ 'key' => '_jw_date', 'value' => $d, 'compare' => $newer ? '>' : '<', 'type' => 'DATE' ] ],
		] );
		return $q->have_posts() ? $q->posts[0] : null;
	};
	$prev = $adj( false ); $next = $adj( true );
?>

<section class="section section--tight">
	<div class="wrap">
		<div class="crumbs">
			<a href="<?php echo esc_url( home_url() ); ?>">Home</a><span class="sep">/</span>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'sermon' ) ); ?>">Sermons</a>
			<?php if ( $book_link ) : ?><span class="sep">/</span><a href="<?php echo esc_url( $book_link ); ?>"><?php echo esc_html( $book ); ?></a><?php endif; ?>
			<span class="sep">/</span><span><?php echo esc_html( $passage ); ?></span>
		</div>

		<div class="sermon-hero">
			<div class="meta-line">
				<?php if ( $book ) : ?><span><?php echo esc_html( $book ); ?></span><span class="dot"></span><?php endif; ?>
				<span><?php echo esc_html( jw_service_label( $id ) ); ?></span><span class="dot"></span>
				<span><?php echo esc_html( jw_pretty_date( $id ) ); ?></span>
			</div>
			<h1><?php echo esc_html( $passage ); ?></h1>
			<div class="byline">Preached by <?php echo esc_html( $speaker ); ?></div>
			<?php if ( $arid ) : ?>
			<div class="sermon-save">
				<button class="heart" data-heart data-sermon="<?php echo esc_attr( $arid ); ?>"
					data-passage="<?php echo esc_attr( $passage ); ?>" aria-label="Save to favorites" aria-pressed="false">
					<svg viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
				</button>
				<button class="addpl" data-addplaylist data-sermon="<?php echo esc_attr( $arid ); ?>"
					data-passage="<?php echo esc_attr( $passage ); ?>" aria-label="Add to playlist">
					<svg viewBox="0 0 24 24"><path d="M4 6h11M4 12h7M4 18h7M16 15v6M13 18h6"/></svg>
				</button>
				<span class="smallcaps">Save &middot; add to a playlist</span>
			</div>
			<?php endif; ?>
		</div>

		<?php if ( $audio ) : ?>
		<div class="player-feature reveal" style="margin-top:2rem"
			data-feature data-audio="<?php echo esc_url( $audio ); ?>"
			data-title="<?php echo esc_attr( $passage ); ?>"
			data-sub="<?php echo esc_attr( jw_service_label( $id ) . ' · ' . jw_pretty_date( $id ) ); ?>">
			<button class="play" aria-label="Play sermon" data-feature-play>
				<svg class="i-play" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
				<svg class="i-pause" viewBox="0 0 24 24" fill="currentColor"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>
			</button>
			<div class="pf-body">
				<div style="font-family:var(--mono);font-size:.66rem;letter-spacing:.18em;text-transform:uppercase;color:var(--brass)">Listen Now</div>
				<div style="font-family:var(--disp);font-size:1.3rem;color:var(--ivory);margin-top:.2rem"><?php echo esc_html( $passage ); ?></div>
				<div class="pf-seek seek" data-feature-seek><div class="fill"></div><div class="head"></div></div>
				<div style="display:flex;justify-content:space-between;font-family:var(--mono);font-size:.66rem;color:var(--muted);margin-top:.4rem">
					<span data-feature-cur>0:00</span><span data-feature-dur>—</span>
				</div>
			</div>
		</div>
		<?php endif; ?>
	</div>
</section>

<section class="section" style="padding-top:0">
	<div class="wrap sermon-body">
		<div class="prose">
			<?php if ( trim( get_the_content() ) ) : ?>
				<?php the_content(); ?>
			<?php else : ?>
				<div class="transcript-empty">
					<span class="kicker">Sermon Text</span>
					<h3 style="margin-top:.3rem">The full text of this sermon will appear here.</h3>
					<p style="color:var(--muted)">Sermon manuscripts and study notes are being added to the library.
					In the meantime, listen above, open the passage alongside, and follow the exposition verse by verse.</p>
					<?php if ( $book && jw_meta( 'passage' ) ) : ?>
						<a class="btn btn--ghost" target="_blank" rel="noopener"
						   href="https://www.biblegateway.com/passage/?version=ESV&search=<?php echo rawurlencode( jw_meta( 'passage' ) ); ?>">
						   Read <?php echo esc_html( $passage ); ?> →</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- series prev/next -->
			<?php if ( $prev || $next ) : ?>
			<nav class="series-nav">
				<?php if ( $prev ) : ?>
					<a class="prev" href="<?php echo esc_url( get_permalink( $prev ) ); ?>">
						<div class="dir">← Earlier in <?php echo esc_html( $book ); ?></div>
						<div class="t"><?php echo esc_html( jw_meta( 'passage', $prev->ID ) ); ?></div>
					</a>
				<?php else : ?><span></span><?php endif; ?>
				<?php if ( $next ) : ?>
					<a class="next" href="<?php echo esc_url( get_permalink( $next ) ); ?>">
						<div class="dir">Next in <?php echo esc_html( $book ); ?> →</div>
						<div class="t"><?php echo esc_html( jw_meta( 'passage', $next->ID ) ); ?></div>
					</a>
				<?php else : ?><span></span><?php endif; ?>
			</nav>
			<?php endif; ?>
		</div>

		<!-- aside -->
		<aside class="sermon-aside">
			<div class="panel">
				<h4>Sermon Details</h4>
				<?php if ( $book ) : ?><div class="row"><span>Book</span><b><?php echo esc_html( $book ); ?></b></div><?php endif; ?>
				<div class="row"><span>Passage</span><b><?php echo esc_html( $passage ); ?></b></div>
				<div class="row"><span>Service</span><b><?php echo esc_html( jw_service_label( $id ) ); ?></b></div>
				<div class="row"><span>Date</span><b><?php echo esc_html( jw_pretty_date( $id ) ); ?></b></div>
				<div class="row"><span>Speaker</span><b><?php echo esc_html( $speaker ); ?></b></div>
			</div>

			<div class="panel">
				<h4>Listen &amp; Study</h4>
				<div class="actions">
					<?php if ( $audio ) : ?>
						<a class="btn btn--ghost" href="<?php echo esc_url( $audio ); ?>" download>Download MP3</a>
					<?php endif; ?>
					<?php if ( $book && jw_meta( 'passage' ) ) : ?>
						<a class="btn btn--ghost" target="_blank" rel="noopener"
						   href="https://www.biblegateway.com/passage/?version=ESV&search=<?php echo rawurlencode( jw_meta( 'passage' ) ); ?>">Read the Passage</a>
					<?php endif; ?>
					<?php if ( jw_meta( 'details_url' ) ) : ?>
						<a class="btn btn--ghost" target="_blank" rel="noopener" href="<?php echo esc_url( jw_meta( 'details_url' ) ); ?>">View on archive.org</a>
					<?php endif; ?>
				</div>
			</div>

			<?php // WooCommerce-ready: show outline CTA when a product is linked.
			if ( $product && function_exists( 'wc_get_product' ) && ( $p = wc_get_product( $product ) ) ) : ?>
			<div class="panel">
				<h4>Go Deeper</h4>
				<p style="color:var(--muted);font-size:.92rem;margin:.2rem 0 1rem">Get the printable outline &amp; study notes for this sermon.</p>
				<a class="btn outline-cta" href="<?php echo esc_url( $p->get_permalink() ); ?>">Get the Outline · <?php echo wp_kses_post( $p->get_price_html() ); ?></a>
			</div>
			<?php endif; ?>
		</aside>
	</div>
</section>

<!-- more in this book -->
<?php if ( $book_term && ! is_wp_error( $book_term ) ) :
	$book_more = new WP_Query( [ 'post_type' => 'sermon', 'posts_per_page' => 3, 'post__not_in' => [ $id ],
		'tax_query' => [ [ 'taxonomy' => 'bible_book', 'field' => 'term_id', 'terms' => $book_term[0]->term_id ] ],
		'meta_key' => '_jw_date', 'orderby' => 'meta_value', 'order' => 'DESC' ] );
	if ( $book_more->have_posts() ) : ?>
<section class="section" style="padding-top:0">
	<div class="wrap">
		<div class="sec-head"><div><span class="kicker">Keep Going</span><h2>More from <?php echo esc_html( $book ); ?></h2></div>
			<a class="link-more" href="<?php echo esc_url( $book_link ); ?>">All of <?php echo esc_html( $book ); ?> <span>→</span></a></div>
		<div class="card-grid">
			<?php while ( $book_more->have_posts() ) : $book_more->the_post(); get_template_part( 'template-parts/sermon-card' ); endwhile; wp_reset_postdata(); ?>
		</div>
	</div>
</section>
<?php endif; endif; ?>

<?php endwhile; get_footer();
