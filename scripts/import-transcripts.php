<?php
/**
 * Bulk-import sermon manuscripts / transcripts / outlines into the matching sermon.
 *
 * Drop .txt or .md files into  data/transcripts/  then run:
 *   wp eval-file wp-content/jw-scripts/import-transcripts.php
 *
 * Matching (first that resolves to exactly one sermon wins):
 *   1. Filename equals the archive.org identifier   e.g. 2019.02.03.ETitus3.15JackWerth.txt
 *   2. A manifest row in data/transcripts/manifest.csv  (identifier,filename)
 *   3. A date in the filename  (YYYY-MM-DD or YYYY.MM.DD), optionally + service letter
 *        e.g. 2019-02-03.txt   2019-02-03-E.txt   2019.02.03.E Titus 3.15.txt
 *
 * Text becomes the post content (so it is fully searchable) and flags _jw_has_transcript.
 * Re-running updates content in place. Designed to scale to thousands of files.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { fwrite( STDERR, "Run via wp-cli.\n" ); exit( 1 ); }

$dir = '/var/www/html/wp-content/jw-data/transcripts';
if ( ! is_dir( $dir ) ) { WP_CLI::error( "Create the folder first: data/transcripts/ then add .txt/.md files." ); }

/* Index sermons by archive id and by date for fast lookup. */
$by_id = []; $by_date = [];
foreach ( get_posts( [ 'post_type' => 'sermon', 'numberposts' => -1, 'fields' => 'ids' ] ) as $pid ) {
	$aid = get_post_meta( $pid, '_jw_archive_id', true );
	$d   = get_post_meta( $pid, '_jw_date', true );
	$sc  = get_post_meta( $pid, '_jw_service_code', true );
	if ( $aid ) $by_id[ $aid ] = $pid;
	if ( $d )   { $by_date[ $d ][ $sc ] = $pid; $by_date[ $d ]['*'][] = $pid; }
}

/* Optional explicit manifest. */
$manifest = [];
$mf = "$dir/manifest.csv";
if ( file_exists( $mf ) && ( $h = fopen( $mf, 'r' ) ) ) {
	while ( ( $r = fgetcsv( $h ) ) !== false ) {
		if ( count( $r ) >= 2 ) $manifest[ trim( $r[1] ) ] = trim( $r[0] );
	}
	fclose( $h );
}

$to_html = function ( $file ) {
	$raw = file_get_contents( $file );
	if ( preg_match( '/\.md$/i', $file ) && function_exists( 'wpautop' ) ) {
		// light markdown: headings + paragraphs
		$raw = preg_replace( '/^###\s?(.+)$/m', '<h3>$1</h3>', $raw );
		$raw = preg_replace( '/^##\s?(.+)$/m', '<h2>$1</h2>', $raw );
	}
	return wpautop( wp_kses_post( $raw ) );
};

$files = array_merge( glob( "$dir/*.txt" ) ?: [], glob( "$dir/*.md" ) ?: [] );
WP_CLI::log( 'Found ' . count( $files ) . ' transcript file(s).' );

$matched = 0; $skipped = [];
foreach ( $files as $f ) {
	$base = basename( $f );
	$name = preg_replace( '/\.(txt|md)$/i', '', $base );
	$pid  = null;

	if ( isset( $manifest[ $base ] ) && isset( $by_id[ $manifest[ $base ] ] ) ) {
		$pid = $by_id[ $manifest[ $base ] ];
	} elseif ( isset( $by_id[ $name ] ) ) {
		$pid = $by_id[ $name ];
	} elseif ( preg_match( '/(\d{4})[-.](\d{2})[-.](\d{2})/', $name, $m ) ) {
		$date = "{$m[1]}-{$m[2]}-{$m[3]}";
		if ( isset( $by_date[ $date ] ) ) {
			if ( preg_match( '/\b([MEXA])\b/', str_replace( $date, '', $name ), $sm ) && isset( $by_date[ $date ][ $sm[1] ] ) ) {
				$pid = $by_date[ $date ][ $sm[1] ];
			} elseif ( count( $by_date[ $date ]['*'] ) === 1 ) {
				$pid = $by_date[ $date ]['*'][0];
			}
		}
	}

	if ( ! $pid ) { $skipped[] = $base; continue; }

	wp_update_post( [ 'ID' => $pid, 'post_content' => $to_html( $f ) ] );
	update_post_meta( $pid, '_jw_has_transcript', 1 );
	$matched++;
}

WP_CLI::success( "Imported $matched transcript(s)." );
if ( $skipped ) {
	WP_CLI::warning( count( $skipped ) . ' could not be matched (add to manifest.csv or rename with a date/identifier):' );
	foreach ( array_slice( $skipped, 0, 15 ) as $s ) WP_CLI::log( "   $s" );
}
