<?php
/**
 * Jack Werth — "Midnight Study" theme.
 * A reverent, lamplit library for expository, verse-by-verse preaching.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'JW_THEME_VER', '1.0.5' );

/* ------------------------------------------------------------------ setup */
add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', [ 'search-form', 'gallery', 'caption', 'style', 'script' ] );
	add_theme_support( 'custom-logo', [ 'height' => 60, 'flex-width' => true ] );
	add_theme_support( 'woocommerce' ); // ready to sell outlines + books later
	add_theme_support( 'editor-styles' );

	register_nav_menus( [
		'primary' => 'Primary Menu',
		'footer'  => 'Footer Menu',
	] );

	add_image_size( 'jw_card', 800, 500, true );
} );

/* --------------------------------------------------------------- assets */
add_action( 'wp_enqueue_scripts', function () {
	// Distinctive type: Fraunces (display, characterful old-style) + Newsreader (literary body).
	wp_enqueue_style( 'jw-fonts',
		'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300..900;1,9..144,300..900&family=Newsreader:ital,opsz,wght@0,6..72,300..600;1,6..72,300..600&family=JetBrains+Mono:wght@400;500&display=swap',
		[], null );

	wp_enqueue_style( 'jw-main', get_stylesheet_directory_uri() . '/assets/css/main.css', [ 'jw-fonts' ], JW_THEME_VER );

	wp_enqueue_script( 'jw-app', get_stylesheet_directory_uri() . '/assets/js/app.js', [], JW_THEME_VER, true );
	wp_enqueue_script( 'jw-player', get_stylesheet_directory_uri() . '/assets/js/player.js', [], JW_THEME_VER, true );

	wp_localize_script( 'jw-app', 'JW', [
		'rest'    => esc_url_raw( rest_url( 'jw/v1/sermons' ) ),
		'archive' => get_post_type_archive_link( 'sermon' ),
	] );
} );

add_filter( 'preconnect', fn() => null );
add_action( 'wp_head', function () {
	echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
	echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
	echo '<link rel="preconnect" href="https://archive.org">';
}, 1 );

/* --------------------------------------------------------------- brand */
function jw_brand( $key = 'name' ) {
	$d = [
		'name'    => get_theme_mod( 'jw_brand_name', 'Jack Werth' ),
		'tagline' => get_theme_mod( 'jw_brand_tagline', 'Expository preaching, verse by verse.' ),
	];
	return $d[ $key ] ?? '';
}

add_action( 'customize_register', function ( $wp ) {
	$wp->add_section( 'jw_brand', [ 'title' => 'Brand', 'priority' => 20 ] );
	$wp->add_setting( 'jw_brand_name', [ 'default' => 'Jack Werth', 'sanitize_callback' => 'sanitize_text_field' ] );
	$wp->add_control( 'jw_brand_name', [ 'label' => 'Brand name', 'section' => 'jw_brand', 'type' => 'text' ] );
	$wp->add_setting( 'jw_brand_tagline', [ 'default' => 'Expository preaching, verse by verse.', 'sanitize_callback' => 'sanitize_text_field' ] );
	$wp->add_control( 'jw_brand_tagline', [ 'label' => 'Tagline', 'section' => 'jw_brand', 'type' => 'text' ] );
	$wp->add_setting( 'jw_og_image', [ 'default' => '', 'sanitize_callback' => 'esc_url_raw' ] );
	$wp->add_control( new WP_Customize_Image_Control( $wp, 'jw_og_image', [ 'label' => 'Social share image', 'section' => 'jw_brand' ] ) );
} );

/* --------------------------------------------------------------- helpers */

/** Books that actually have sermons, with counts, grouped + ordered canonically. */
function jw_books_with_counts() {
	$terms = get_terms( [ 'taxonomy' => 'bible_book', 'hide_empty' => true ] );
	if ( is_wp_error( $terms ) ) return [ 'OT' => [], 'NT' => [] ];
	$out = [ 'OT' => [], 'NT' => [] ];
	foreach ( $terms as $t ) {
		if ( in_array( $t->slug, [ 'old-testament', 'new-testament' ], true ) ) continue;
		$test = class_exists( 'JW_Bible' ) ? JW_Bible::testament( $t->name ) : '';
		$out[ $test ?: 'OT' ][] = $t;
	}
	$sort = fn( $a, $b ) => ( class_exists( 'JW_Bible' ) ? JW_Bible::order( $a->name ) - JW_Bible::order( $b->name ) : strcmp( $a->name, $b->name ) );
	usort( $out['OT'], $sort ); usort( $out['NT'], $sort );
	return $out;
}

/** Years that have sermons (for the filter dropdown). */
function jw_sermon_years() {
	global $wpdb;
	$rows = $wpdb->get_col( "SELECT DISTINCT LEFT(meta_value,4) y FROM {$wpdb->postmeta} WHERE meta_key='_jw_date' ORDER BY y DESC" );
	return array_filter( $rows );
}

function jw_meta( $key, $id = null ) { return get_post_meta( $id ?: get_the_ID(), '_jw_' . $key, true ); }

/** Human service label from a sermon. */
function jw_service_label( $id = null ) { return jw_meta( 'service', $id ) ?: 'Service'; }

/** Pretty long date. */
function jw_pretty_date( $id = null ) {
	$d = jw_meta( 'date', $id );
	return $d ? date_i18n( 'F j, Y', strtotime( $d ) ) : get_the_date();
}

/** Total sermon count (cached). */
function jw_total_sermons() {
	$c = wp_cache_get( 'jw_total' );
	if ( false === $c ) { $c = (int) wp_count_posts( 'sermon' )->publish; wp_cache_set( 'jw_total', $c, '', 300 ); }
	return $c;
}

/* Excerpt polish */
add_filter( 'excerpt_length', fn() => 28 );
add_filter( 'excerpt_more', fn() => '…' );

/* Body classes for styling hooks */
add_filter( 'body_class', function ( $c ) {
	$c[] = 'jw';
	if ( is_singular( 'sermon' ) ) $c[] = 'jw-single-sermon';
	return $c;
} );

/* Make archive.org thumbnails graceful: default placeholder handled in CSS. */
