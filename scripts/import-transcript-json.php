<?php
/**
 * Import word-timed transcript JSON (data/transcripts/*.json) into sermons.
 * Sets post_content to the plain transcript text (so it's full-text searchable
 * and works without JS) and flags _jw_has_transcript. The word-timing JSON is
 * served separately for the follow-along highlighting.
 *
 * Run:  wp eval-file wp-content/jw-scripts/import-transcript-json.php
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { fwrite( STDERR, "Run via wp-cli.\n" ); exit( 1 ); }

$dir   = '/var/www/html/wp-content/jw-data/transcripts';
$files = glob( "$dir/*.json" );
if ( ! $files ) { WP_CLI::warning( "No transcript JSON in data/transcripts/." ); return; }

$by_aid = [];
foreach ( get_posts( [ 'post_type' => 'sermon', 'numberposts' => -1, 'fields' => 'ids' ] ) as $pid ) {
	$aid = get_post_meta( $pid, '_jw_archive_id', true );
	if ( $aid ) $by_aid[ $aid ] = $pid;
}

$n = 0; $skipped = 0;
foreach ( $files as $f ) {
	$d = json_decode( file_get_contents( $f ), true );
	if ( empty( $d['id'] ) || empty( $d['text'] ) ) { $skipped++; continue; }
	$pid = $by_aid[ $d['id'] ] ?? null;
	if ( ! $pid ) { $skipped++; continue; }
	wp_update_post( [ 'ID' => $pid, 'post_content' => sanitize_textarea_field( $d['text'] ) ] );
	update_post_meta( $pid, '_jw_has_transcript', 1 );
	$n++;
}
WP_CLI::success( "Imported $n transcript(s) into post content. Skipped $skipped." );
