<?php
/**
 * Import the Jack Werth catalog (data/sermons.json) into the `sermon` CPT.
 * Idempotent: re-running updates existing sermons (matched by archive id).
 *
 * Run:  wp eval-file wp-content/jw-scripts/import-sermons.php
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { fwrite( STDERR, "Run via wp-cli.\n" ); exit( 1 ); }
if ( ! post_type_exists( 'sermon' ) ) { WP_CLI::error( 'Sermon CPT missing — activate the JW Sermon Library plugin first.' ); }

$json = file_get_contents( '/var/www/html/wp-content/jw-data/sermons.json' );
$rows = json_decode( $json, true );
if ( ! is_array( $rows ) ) { WP_CLI::error( 'Could not read sermons.json' ); }

WP_CLI::log( 'Importing ' . count( $rows ) . ' sermons…' );

/* Map archive_id -> existing post for idempotent re-runs. */
$existing = [];
foreach ( get_posts( [ 'post_type' => 'sermon', 'numberposts' => -1, 'fields' => 'ids' ] ) as $pid ) {
	$aid = get_post_meta( $pid, '_jw_archive_id', true );
	if ( $aid ) $existing[ $aid ] = $pid;
}

/* Cache book terms (with testament parent) so we don't re-create them. */
$book_term_cache = [];
$testament_term = function ( $code ) {
	static $cache = [];
	if ( isset( $cache[ $code ] ) ) return $cache[ $code ];
	$name = $code === 'NT' ? 'New Testament' : 'Old Testament';
	$slug = $code === 'NT' ? 'new-testament' : 'old-testament';
	$t = term_exists( $slug, 'bible_book' ) ?: wp_insert_term( $name, 'bible_book', [ 'slug' => $slug ] );
	return $cache[ $code ] = (int) ( is_array( $t ) ? $t['term_id'] : $t );
};
$get_book_term = function ( $book ) use ( &$book_term_cache, $testament_term ) {
	if ( isset( $book_term_cache[ $book ] ) ) return $book_term_cache[ $book ];
	$test   = class_exists( 'JW_Bible' ) ? JW_Bible::testament( $book ) : '';
	$parent = $test ? $testament_term( $test ) : 0;
	$found  = get_term_by( 'name', $book, 'bible_book' );
	if ( $found ) { $tid = $found->term_id; }
	else {
		$t   = wp_insert_term( $book, 'bible_book', [ 'parent' => $parent ] );
		$tid = is_wp_error( $t ) ? 0 : (int) $t['term_id'];
	}
	if ( $tid && $parent ) wp_update_term( $tid, 'bible_book', [ 'parent' => $parent ] );
	return $book_term_cache[ $book ] = $tid;
};

$created = 0; $updated = 0;
foreach ( $rows as $r ) {
	$book    = class_exists( 'JW_Bible' ) ? JW_Bible::normalize( $r['book'] ) : $r['book'];
	$passage = trim( $book . ' ' . ( $r['chapter'] ? $r['chapter'] . ':' . $r['verses'] : '' ) );
	if ( ! $r['chapter'] ) $passage = $r['passage'];
	$date    = $r['date'];
	$pretty  = date_i18n( 'F j, Y', strtotime( $date ) );
	$excerpt = sprintf(
		'Pastor Jack Werth\'s verse-by-verse exposition of %s — preached during the %s on %s at Liberty Baptist Church Reformed.',
		$passage, strtolower( $r['service'] ), $pretty
	);

	$postarr = [
		'post_type'    => 'sermon',
		'post_status'  => 'publish',
		'post_title'   => $passage,
		'post_name'    => sanitize_title( $date . '-' . $passage ),
		'post_date'    => $date . ' 10:00:00',
		'post_excerpt' => $excerpt,
		'post_content' => '', // transcripts/outlines added later
	];

	$aid = $r['identifier'];
	if ( isset( $existing[ $aid ] ) ) { $postarr['ID'] = $existing[ $aid ]; $updated++; }
	else { $created++; }

	$pid = wp_insert_post( $postarr, true );
	if ( is_wp_error( $pid ) ) { WP_CLI::warning( 'Failed: ' . $passage . ' — ' . $pid->get_error_message() ); continue; }

	$meta = [
		'_jw_date' => $date, '_jw_service_code' => $r['service_code'], '_jw_service' => $r['service'],
		'_jw_speaker' => $r['speaker'], '_jw_book' => $book, '_jw_chapter' => $r['chapter'],
		'_jw_verses' => $r['verses'], '_jw_passage' => $passage, '_jw_audio_mp3' => $r['audio_mp3'],
		'_jw_audio_ogg' => $r['audio_ogg'], '_jw_archive_id' => $aid, '_jw_details_url' => $r['details_url'],
	];
	foreach ( $meta as $k => $v ) update_post_meta( $pid, $k, $v );

	$bt = $get_book_term( $book );
	if ( $bt ) wp_set_object_terms( $pid, [ $bt ], 'bible_book', false );
	wp_set_object_terms( $pid, $book, 'sermon_series', false );
	wp_set_object_terms( $pid, $r['speaker'] ?: 'Jack Werth', 'sermon_speaker', false );
	wp_set_object_terms( $pid, $r['service'], 'service_type', false );

	if ( ( $created + $updated ) % 100 === 0 ) WP_CLI::log( '  …' . ( $created + $updated ) . ' processed' );
}

WP_CLI::success( "Done. Created $created, updated $updated. Total sermons: " . wp_count_posts( 'sermon' )->publish );
