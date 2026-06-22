<?php
/** Sermon card. Expects the loop to be set to a sermon post. */
if ( ! defined( 'ABSPATH' ) ) exit;
$id      = get_the_ID();
$passage = jw_meta( 'passage' ) ?: get_the_title();
$book    = jw_meta( 'book' );
$audio   = function_exists( 'jw_audio_url' ) ? jw_audio_url( $id ) : jw_meta( 'audio_mp3' );
?>
<article class="scard reveal">
	<div class="top">
		<?php if ( $book ) : ?>
			<a class="tag" href="<?php echo esc_url( get_post_type_archive_link( 'sermon' ) . '?book=' . sanitize_title( $book ) ); ?>"><?php echo esc_html( $book ); ?></a>
		<?php else : ?><span class="tag">Sermon</span><?php endif; ?>
		<span class="date"><?php echo esc_html( jw_pretty_date() ); ?></span>
	</div>

	<h3 class="passage"><a href="<?php the_permalink(); ?>"><?php echo esc_html( $passage ); ?></a></h3>

	<?php if ( has_excerpt() ) : ?>
		<p class="excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
	<?php endif; ?>

	<div class="foot">
		<span class="svc"><?php echo esc_html( jw_service_label() ); ?></span>
		<?php if ( $audio ) : ?>
		<button class="play" aria-label="Play <?php echo esc_attr( $passage ); ?>"
			data-audio="<?php echo esc_url( $audio ); ?>"
			data-title="<?php echo esc_attr( $passage ); ?>"
			data-sub="<?php echo esc_attr( jw_service_label() . ' · ' . jw_pretty_date() ); ?>">
			<svg class="i-play" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
			<svg class="i-pause" viewBox="0 0 24 24" fill="currentColor"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>
		</button>
		<?php endif; ?>
	</div>
</article>
