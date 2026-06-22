<?php
/**
 * Plugin Name:       JW Sermon Library
 * Description:       Sermon archive engine for the Jack Werth library — custom post type, Bible-book / series / service taxonomies, scripture-aware search, schema.org SEO, and a podcast feed. Data lives here so it survives any theme change.
 * Version:           1.0.0
 * Author:            Jack Werth Sermon Library
 * Text Domain:       jw-sermons
 * Requires PHP:      8.0
 *
 * WooCommerce-ready: a sermon can reference a downloadable product (outline/book)
 * via the `_jw_product_id` meta; the theme surfaces a "Get the outline" CTA when set.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'JW_SERMONS_VER', '1.0.0' );

/* -------------------------------------------------------------------------
 * Canonical Bible data — used for normalization, ordering, and testament
 * grouping on the "Books of the Bible" index. (66 books, canonical order.)
 * ---------------------------------------------------------------------- */
final class JW_Bible {

	/** name => testament ('OT'|'NT'), in canonical order. */
	const BOOKS = [
		'Genesis'=>'OT','Exodus'=>'OT','Leviticus'=>'OT','Numbers'=>'OT','Deuteronomy'=>'OT',
		'Joshua'=>'OT','Judges'=>'OT','Ruth'=>'OT','1 Samuel'=>'OT','2 Samuel'=>'OT',
		'1 Kings'=>'OT','2 Kings'=>'OT','1 Chronicles'=>'OT','2 Chronicles'=>'OT','Ezra'=>'OT',
		'Nehemiah'=>'OT','Esther'=>'OT','Job'=>'OT','Psalms'=>'OT','Proverbs'=>'OT',
		'Ecclesiastes'=>'OT','Song of Solomon'=>'OT','Isaiah'=>'OT','Jeremiah'=>'OT','Lamentations'=>'OT',
		'Ezekiel'=>'OT','Daniel'=>'OT','Hosea'=>'OT','Joel'=>'OT','Amos'=>'OT',
		'Obadiah'=>'OT','Jonah'=>'OT','Micah'=>'OT','Nahum'=>'OT','Habakkuk'=>'OT',
		'Zephaniah'=>'OT','Haggai'=>'OT','Zechariah'=>'OT','Malachi'=>'OT',
		'Matthew'=>'NT','Mark'=>'NT','Luke'=>'NT','John'=>'NT','Acts'=>'NT',
		'Romans'=>'NT','1 Corinthians'=>'NT','2 Corinthians'=>'NT','Galatians'=>'NT','Ephesians'=>'NT',
		'Philippians'=>'NT','Colossians'=>'NT','1 Thessalonians'=>'NT','2 Thessalonians'=>'NT',
		'1 Timothy'=>'NT','2 Timothy'=>'NT','Titus'=>'NT','Philemon'=>'NT','Hebrews'=>'NT',
		'James'=>'NT','1 Peter'=>'NT','2 Peter'=>'NT','1 John'=>'NT','2 John'=>'NT',
		'3 John'=>'NT','Jude'=>'NT','Revelation'=>'NT',
	];

	/** Map messy source spellings to canonical book names. */
	static function normalize( string $raw ): string {
		$raw = trim( $raw );
		$alias = [
			'Psalm' => 'Psalms', 'Song of Songs' => 'Song of Solomon',
			'Songs' => 'Song of Solomon', 'Revelations' => 'Revelation',
		];
		// Strip a stray chapter that leaked into the book name (e.g. "Psalm 139").
		if ( preg_match( '/^(Psalm|Psalms)\b/i', $raw ) ) return 'Psalms';
		if ( isset( $alias[ $raw ] ) ) return $alias[ $raw ];
		foreach ( self::BOOKS as $name => $t ) {
			if ( strcasecmp( $name, $raw ) === 0 ) return $name;
		}
		return $raw; // unknown — keep as-is
	}

	static function order( string $name ): int {
		$i = array_search( $name, array_keys( self::BOOKS ), true );
		return $i === false ? 999 : (int) $i;
	}

	static function testament( string $name ): string {
		return self::BOOKS[ $name ] ?? '';
	}
}

/* -------------------------------------------------------------------------
 * Custom post type + taxonomies
 * ---------------------------------------------------------------------- */
add_action( 'init', function () {

	register_post_type( 'sermon', [
		'labels' => [
			'name' => 'Sermons', 'singular_name' => 'Sermon', 'menu_name' => 'Sermons',
			'add_new_item' => 'Add Sermon', 'edit_item' => 'Edit Sermon',
			'search_items' => 'Search Sermons', 'all_items' => 'All Sermons',
		],
		'public'        => true,
		'has_archive'   => true,
		'menu_icon'     => 'dashicons-microphone',
		'show_in_rest'  => true,
		'rewrite'       => [ 'slug' => 'sermons', 'with_front' => false ],
		'supports'      => [ 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ],
		'taxonomies'    => [ 'bible_book', 'sermon_series', 'sermon_speaker', 'service_type' ],
	] );

	$tax = function ( $slug, $rewrite, $labels, $hier = false ) {
		register_taxonomy( $slug, 'sermon', [
			'labels'            => $labels,
			'public'            => true,
			'hierarchical'      => $hier,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => [ 'slug' => $rewrite, 'with_front' => false ],
		] );
	};
	$tax( 'bible_book',     'book',    [ 'name' => 'Books of the Bible', 'singular_name' => 'Book' ], true );
	$tax( 'sermon_series',  'series',  [ 'name' => 'Series',  'singular_name' => 'Series' ] );
	$tax( 'sermon_speaker', 'speaker', [ 'name' => 'Speakers','singular_name' => 'Speaker' ] );
	$tax( 'service_type',   'service', [ 'name' => 'Services','singular_name' => 'Service' ] );

	// Scalar meta, exposed to REST for Gutenberg / headless reuse.
	$meta = [
		'_jw_date' => 'string', '_jw_service_code' => 'string', '_jw_service' => 'string',
		'_jw_speaker' => 'string',
		'_jw_book' => 'string', '_jw_chapter' => 'string', '_jw_verses' => 'string',
		'_jw_passage' => 'string', '_jw_audio_mp3' => 'string', '_jw_audio_ogg' => 'string',
		'_jw_audio_local' => 'string', '_jw_archive_id' => 'string', '_jw_details_url' => 'string',
		'_jw_duration' => 'string', '_jw_product_id' => 'integer', '_jw_has_transcript' => 'boolean',
	];
	// URL fields MUST use esc_url_raw — sanitize_text_field strips %-encoded octets
	// (e.g. the %20 spaces in archive.org filenames), which would 404 the audio.
	$url_keys = [ '_jw_audio_mp3', '_jw_audio_ogg', '_jw_details_url' ];
	foreach ( $meta as $key => $type ) {
		register_post_meta( 'sermon', $key, [
			'type' => $type, 'single' => true, 'show_in_rest' => true,
			'sanitize_callback' => $type === 'integer' ? 'absint'
				: ( $type === 'boolean' ? 'rest_sanitize_boolean'
				: ( in_array( $key, $url_keys, true ) ? 'esc_url_raw' : 'sanitize_text_field' ) ),
			'auth_callback' => fn() => current_user_can( 'edit_posts' ),
		] );
	}
}, 0 );

/* Audio resolver: prefer a downloaded local backup, else stream from archive.org. */
function jw_audio_url( int $post_id ): string {
	$local = get_post_meta( $post_id, '_jw_audio_local', true );
	if ( $local ) {
		$path = wp_get_upload_dir()['basedir'] . '/sermons/' . ltrim( $local, '/' );
		if ( file_exists( $path ) ) return wp_get_upload_dir()['baseurl'] . '/sermons/' . ltrim( $local, '/' );
	}
	return (string) get_post_meta( $post_id, '_jw_audio_mp3', true );
}

/* -------------------------------------------------------------------------
 * Faceted query — shared by the archive page (no-JS) and the REST endpoint.
 * Filters: book (slug), year, service (slug), q (keyword across title+content+passage).
 * ---------------------------------------------------------------------- */
function jw_sermon_query_args( array $f, int $paged = 1, int $per = 12 ): array {
	$args = [
		'post_type'      => 'sermon',
		'posts_per_page' => $per,
		'paged'          => max( 1, $paged ),
		'orderby'        => 'meta_value',
		'meta_key'       => '_jw_date',
		'order'          => 'DESC',
		'tax_query'      => [ 'relation' => 'AND' ],
	];
	if ( ! empty( $f['book'] ) ) {
		$args['tax_query'][] = [ 'taxonomy' => 'bible_book', 'field' => 'slug', 'terms' => sanitize_title( $f['book'] ) ];
	}
	if ( ! empty( $f['series'] ) ) {
		$args['tax_query'][] = [ 'taxonomy' => 'sermon_series', 'field' => 'slug', 'terms' => sanitize_title( $f['series'] ) ];
	}
	if ( ! empty( $f['service'] ) ) {
		$args['tax_query'][] = [ 'taxonomy' => 'service_type', 'field' => 'slug', 'terms' => sanitize_title( $f['service'] ) ];
	}
	if ( ! empty( $f['year'] ) ) {
		$y = (int) $f['year'];
		$args['meta_query'] = [ [ 'key' => '_jw_date', 'value' => [ "$y-01-01", "$y-12-31" ], 'compare' => 'BETWEEN', 'type' => 'DATE' ] ];
	}
	if ( ! empty( $f['q'] ) ) {
		$args['s'] = sanitize_text_field( $f['q'] );
	}
	return $args;
}

/* Let the main search + sermon archive sort by sermon date and honor ?book/?year/?service. */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() ) return;
	if ( $q->is_post_type_archive( 'sermon' ) || $q->is_tax( [ 'bible_book', 'sermon_series', 'sermon_speaker', 'service_type' ] ) ) {
		$q->set( 'posts_per_page', 12 );
		$q->set( 'meta_key', '_jw_date' );
		$q->set( 'orderby', 'meta_value' );
		// A book reads in order — oldest first (start of the series); everything else newest first.
		$q->set( 'order', $q->is_tax( 'bible_book' ) ? 'ASC' : 'DESC' );
		foreach ( [ 'book' => 'bible_book', 'series' => 'sermon_series', 'service' => 'service_type' ] as $param => $taxn ) {
			if ( ! empty( $_GET[ $param ] ) ) {
				$tq = (array) $q->get( 'tax_query' );
				$tq[] = [ 'taxonomy' => $taxn, 'field' => 'slug', 'terms' => sanitize_title( wp_unslash( $_GET[ $param ] ) ) ];
				$q->set( 'tax_query', $tq );
			}
		}
		if ( ! empty( $_GET['year'] ) ) {
			$y = (int) $_GET['year'];
			$q->set( 'meta_query', [ [ 'key' => '_jw_date', 'value' => [ "$y-01-01", "$y-12-31" ], 'compare' => 'BETWEEN', 'type' => 'DATE' ] ] );
		}
		if ( ! empty( $_GET['q'] ) ) {
			$q->set( 's', sanitize_text_field( wp_unslash( $_GET['q'] ) ) );  // keyword across title/content/passage
		}
	}
} );

/* Extend core search so a passage like "Colossians 3:16" matches via meta. */
add_filter( 'posts_search', function ( $search, $q ) {
	global $wpdb;
	if ( is_admin() || ! $q->is_main_query() || ! $q->is_search() || ! ( $term = $q->get( 's' ) ) ) return $search;
	$like = '%' . $wpdb->esc_like( $term ) . '%';
	$ids  = $wpdb->get_col( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_jw_passage','_jw_book','_jw_date') AND meta_value LIKE %s", $like ) );
	if ( $ids ) {
		$in = implode( ',', array_map( 'absint', $ids ) );
		// OR-in the meta matches alongside core's title/content search.
		$search = preg_replace( '/^\s*AND\s*\((.*)\)\s*$/s', " AND ( $1 OR {$wpdb->posts}.ID IN ($in) )", $search );
	}
	return $search;
}, 10, 2 );

/* -------------------------------------------------------------------------
 * REST: live/instant faceted search -> JSON cards
 * GET /wp-json/jw/v1/sermons?book=luke&year=2019&service=morning-service&q=grace&page=1
 * ---------------------------------------------------------------------- */
add_action( 'rest_api_init', function () {
	register_rest_route( 'jw/v1', '/sermons', [
		'methods'  => 'GET',
		'permission_callback' => '__return_true',
		'callback' => function ( WP_REST_Request $r ) {
			$f = [
				'book' => $r['book'] ?? '', 'series' => $r['series'] ?? '', 'service' => $r['service'] ?? '',
				'year' => $r['year'] ?? '', 'q' => $r['q'] ?? '',
			];
			$query = new WP_Query( jw_sermon_query_args( $f, max( 1, (int) ( $r['page'] ?? 1 ) ) ) );
			$items = [];
			foreach ( $query->posts as $p ) {
				$items[] = [
					'id'        => $p->ID,
					'archive_id'=> get_post_meta( $p->ID, '_jw_archive_id', true ),
					'title'     => get_the_title( $p ),
					'permalink' => get_permalink( $p ),
					'passage'  => get_post_meta( $p->ID, '_jw_passage', true ),
					'book'     => get_post_meta( $p->ID, '_jw_book', true ),
					'date'     => get_post_meta( $p->ID, '_jw_date', true ),
					'service'  => get_post_meta( $p->ID, '_jw_service', true ),
					'audio'    => jw_audio_url( $p->ID ),
					'excerpt'  => wp_strip_all_tags( get_the_excerpt( $p ) ),
				];
			}
			return new WP_REST_Response( [
				'total' => (int) $query->found_posts,
				'pages' => (int) $query->max_num_pages,
				'items' => $items,
			] );
		},
	] );
} );

/* -------------------------------------------------------------------------
 * SEO: schema.org JSON-LD (AudioObject + breadcrumbs on single; Person/Org on home)
 * ---------------------------------------------------------------------- */
add_action( 'wp_head', function () {
	$blocks = [];
	if ( is_singular( 'sermon' ) ) {
		$id = get_the_ID();
		$blocks[] = [
			'@context' => 'https://schema.org', '@type' => 'AudioObject',
			'name' => get_the_title(), 'description' => wp_strip_all_tags( get_the_excerpt() ),
			'contentUrl' => jw_audio_url( $id ), 'encodingFormat' => 'audio/mpeg',
			'uploadDate' => get_post_meta( $id, '_jw_date', true ),
			'datePublished' => get_post_meta( $id, '_jw_date', true ),
			'creator' => [ '@type' => 'Person', 'name' => get_post_meta( $id, '_jw_speaker', true ) ?: 'Jack Werth' ],
			'about' => get_post_meta( $id, '_jw_passage', true ),
			'isFamilyFriendly' => true, 'inLanguage' => 'en',
		];
		$crumbs = [ [ home_url(), 'Home' ], [ get_post_type_archive_link( 'sermon' ), 'Sermons' ] ];
		if ( $b = get_post_meta( $id, '_jw_book', true ) ) {
			$t = get_the_terms( $id, 'bible_book' );
			if ( $t && ! is_wp_error( $t ) ) $crumbs[] = [ get_term_link( $t[0] ), $b ];
		}
		$crumbs[] = [ get_permalink(), get_the_title() ];
		$blocks[] = [ '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
			'itemListElement' => array_map( fn( $c, $i ) => [
				'@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[1], 'item' => $c[0],
			], $crumbs, array_keys( $crumbs ) ) ];
	}
	if ( is_front_page() ) {
		$blocks[] = [ '@context' => 'https://schema.org', '@type' => 'Person',
			'name' => 'Jack Werth', 'jobTitle' => 'Pastor', 'description' => 'Expository preacher — verse-by-verse exposition of Scripture.',
			'url' => home_url(), 'sameAs' => [ 'https://archive.org/details/@webmaster_lbref_org' ] ];
		$blocks[] = [ '@context' => 'https://schema.org', '@type' => 'WebSite',
			'name' => get_bloginfo( 'name' ), 'url' => home_url(),
			'potentialAction' => [ '@type' => 'SearchAction',
				'target' => home_url( '/?s={search_term_string}' ), 'query-input' => 'required name=search_term_string' ] ];
	}
	foreach ( $blocks as $b ) {
		echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $b, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>";
	}
}, 5 );

/* Open Graph + Twitter card basics (works without an SEO plugin). */
add_action( 'wp_head', function () {
	$title = wp_get_document_title();
	$desc  = is_singular() ? wp_strip_all_tags( get_the_excerpt() ) : get_bloginfo( 'description' );
	$desc  = $desc ? mb_substr( $desc, 0, 200 ) : '';
	$url   = is_singular() ? get_permalink() : home_url( add_query_arg( [] ) );
	$img   = ( is_singular() && has_post_thumbnail() ) ? get_the_post_thumbnail_url( null, 'large' )
		: get_theme_mod( 'jw_og_image', '' );
	printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
	printf( '<meta property="og:type" content="%s">' . "\n", is_singular() ? 'article' : 'website' );
	printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $title ) );
	printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $desc ) );
	printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
	if ( $img ) printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $img ) );
	printf( '<meta name="twitter:card" content="%s">' . "\n", $img ? 'summary_large_image' : 'summary' );
}, 6 );

/* -------------------------------------------------------------------------
 * Podcast feed (RSS 2.0 + iTunes) at /feed/sermons — SEO + podcast apps.
 * ---------------------------------------------------------------------- */
add_action( 'init', fn() => add_feed( 'sermons', 'jw_render_podcast_feed' ) );
function jw_render_podcast_feed() {
	$q = new WP_Query( [ 'post_type' => 'sermon', 'posts_per_page' => 300, 'meta_key' => '_jw_date', 'orderby' => 'meta_value', 'order' => 'DESC' ] );
	header( 'Content-Type: application/rss+xml; charset=UTF-8' );
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	?>
<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:content="http://purl.org/rss/1.0/modules/content/">
<channel>
	<title><?php echo esc_html( get_bloginfo( 'name' ) ); ?> — Sermons</title>
	<link><?php echo esc_url( home_url() ); ?></link>
	<description><?php echo esc_html( get_bloginfo( 'description' ) ); ?></description>
	<language>en-us</language>
	<itunes:author>Jack Werth</itunes:author>
	<itunes:category text="Religion &amp; Spirituality"><itunes:category text="Christianity"/></itunes:category>
	<?php while ( $q->have_posts() ) : $q->the_post(); $id = get_the_ID(); $mp3 = jw_audio_url( $id ); ?>
	<item>
		<title><?php echo esc_html( get_the_title() ); ?></title>
		<link><?php the_permalink(); ?></link>
		<guid isPermaLink="false"><?php echo esc_html( get_post_meta( $id, '_jw_archive_id', true ) ?: get_permalink() ); ?></guid>
		<description><?php echo esc_html( get_the_excerpt() ); ?></description>
		<pubDate><?php echo esc_html( gmdate( 'r', strtotime( get_post_meta( $id, '_jw_date', true ) ?: get_the_date( 'c' ) ) ) ); ?></pubDate>
		<itunes:author>Jack Werth</itunes:author>
		<?php if ( $mp3 ) : ?><enclosure url="<?php echo esc_url( $mp3 ); ?>" type="audio/mpeg" length="0"/><?php endif; ?>
	</item>
	<?php endwhile; wp_reset_postdata(); ?>
</channel>
</rss>
	<?php
}

/* -------------------------------------------------------------------------
 * Activation: pretty permalinks + seed testament parents for the book tax.
 * ---------------------------------------------------------------------- */
register_activation_hook( __FILE__, function () {
	// Ensure CPT/taxonomies/feed exist before flushing.
	do_action( 'init' );
	if ( ! term_exists( 'Old Testament', 'bible_book' ) ) wp_insert_term( 'Old Testament', 'bible_book', [ 'slug' => 'old-testament' ] );
	if ( ! term_exists( 'New Testament', 'bible_book' ) ) wp_insert_term( 'New Testament', 'bible_book', [ 'slug' => 'new-testament' ] );
	flush_rewrite_rules();
} );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
