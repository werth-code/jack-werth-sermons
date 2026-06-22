<?php
/** The searchable sermon library — faceted by keyword, book, year, service. */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$books = jw_books_with_counts();
$years = jw_sermon_years();
$svc   = get_terms( [ 'taxonomy' => 'service_type', 'hide_empty' => true ] );
$cur   = fn( $k ) => isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : '';
$action = get_post_type_archive_link( 'sermon' );
?>

<section class="section section--tight">
	<div class="wrap">
		<div class="crumbs"><a href="<?php echo esc_url( home_url() ); ?>">Home</a><span class="sep">/</span><span>Sermons</span></div>
		<span class="kicker">The Library</span>
		<h1 style="margin:.4rem 0 .6rem">Search the Sermons</h1>
		<p class="lede">Every sermon, searchable by <em class="serif-italic">passage</em>, <em class="serif-italic">book of the Bible</em>,
		date, and content. <?php echo esc_html( number_format_i18n( jw_total_sermons() ) ); ?> messages and growing.</p>
	</div>
</section>

<section class="section" style="padding-top:0">
	<div class="wrap">

		<form class="filterbar" method="get" action="<?php echo esc_url( $action ); ?>" data-filter>
			<div class="filter-row">
				<div class="field">
					<label for="f-q">Keyword / Passage</label>
					<input id="f-q" type="search" name="q" value="<?php echo esc_attr( $cur( 'q' ) ); ?>"
						placeholder="e.g. grace, Colossians 3:16, justification" data-f autocomplete="off">
				</div>
				<div class="field">
					<label for="f-book">Book</label>
					<select id="f-book" name="book" data-f>
						<option value="">All books</option>
						<?php foreach ( [ 'OT', 'NT' ] as $grp ) : foreach ( $books[ $grp ] as $b ) : ?>
							<option value="<?php echo esc_attr( $b->slug ); ?>" <?php selected( $cur( 'book' ), $b->slug ); ?>>
								<?php echo esc_html( $b->name ); ?> (<?php echo esc_html( $b->count ); ?>)
							</option>
						<?php endforeach; endforeach; ?>
					</select>
				</div>
				<div class="field">
					<label for="f-year">Year</label>
					<select id="f-year" name="year" data-f>
						<option value="">All years</option>
						<?php foreach ( $years as $y ) : ?>
							<option value="<?php echo esc_attr( $y ); ?>" <?php selected( $cur( 'year' ), $y ); ?>><?php echo esc_html( $y ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="field">
					<label for="f-svc">Service</label>
					<select id="f-svc" name="service" data-f>
						<option value="">All services</option>
						<?php foreach ( $svc as $s ) : ?>
							<option value="<?php echo esc_attr( $s->slug ); ?>" <?php selected( $cur( 'service' ), $s->slug ); ?>><?php echo esc_html( $s->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<button type="reset" class="filter-reset" data-reset>Reset</button>
			</div>
			<noscript><div style="margin-top:.8rem"><button class="btn" type="submit">Search</button></div></noscript>
		</form>

		<div class="results-head">
			<div class="result-count" data-count>
				<b><?php echo esc_html( number_format_i18n( $GLOBALS['wp_query']->found_posts ) ); ?></b> sermon<?php echo $GLOBALS['wp_query']->found_posts == 1 ? '' : 's'; ?> found
			</div>
			<button class="btn btn--ghost playall" data-playall-index type="button" aria-label="Play all results in order">
				<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg> Play All in Order
			</button>
		</div>

		<div class="card-grid" data-results>
			<?php if ( have_posts() ) : while ( have_posts() ) : the_post();
				get_template_part( 'template-parts/sermon-card' );
			endwhile; else : ?>
				<p class="lede">No sermons match those filters. <a href="<?php echo esc_url( $action ); ?>">Clear search →</a></p>
			<?php endif; ?>
		</div>

		<div class="pagination" data-pagination>
			<?php echo paginate_links( [ 'mid_size' => 1, 'prev_text' => '‹', 'next_text' => '›' ] ); ?>
		</div>
	</div>
</section>

<?php get_footer();
