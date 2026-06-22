<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<script>document.documentElement.className += ' js';</script>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#main" style="position:absolute;left:-9999px">Skip to content</a>

<header class="site-head" id="top">
	<div class="wrap head-row">
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( jw_brand( 'name' ) ); ?> — home">
			<?php
			$name = jw_brand( 'name' );
			// Brass-accent the surname if two words.
			$parts = explode( ' ', $name, 2 );
			if ( count( $parts ) === 2 ) {
				printf( '<span class="name">%s <b>%s</b></span>', esc_html( $parts[0] ), esc_html( $parts[1] ) );
			} else {
				printf( '<span class="name"><b>%s</b></span>', esc_html( $name ) );
			}
			?>
			<span class="tag"><?php echo esc_html( jw_brand( 'tagline' ) ); ?></span>
		</a>

		<button class="menu-toggle" aria-label="Menu" aria-expanded="false" data-menu>
			<svg width="20" height="14" viewBox="0 0 20 14" fill="none"><path d="M0 1h20M0 7h20M0 13h20" stroke="currentColor" stroke-width="1.4"/></svg>
		</button>

		<nav class="nav" data-nav aria-label="Primary">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( [ 'theme_location' => 'primary', 'container' => false, 'items_wrap' => '%3$s', 'depth' => 1 ] );
			} else {
				$fallback = [
					home_url( '/' )                              => 'Home',
					get_post_type_archive_link( 'sermon' )       => 'Sermons',
					home_url( '/books/' )                        => 'Books',
					home_url( '/for-pastors/' )                  => 'For Pastors',
					home_url( '/about/' )                        => 'About',
				];
				foreach ( $fallback as $url => $label ) {
					printf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $label ) );
				}
			}
			?>
			<a class="nav-cta" href="<?php echo esc_url( get_post_type_archive_link( 'sermon' ) ); ?>">Search the Library →</a>
		</nav>
	</div>
</header>

<main id="main">
