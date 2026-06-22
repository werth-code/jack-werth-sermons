<?php
/** Sermon card. Expects the loop to be set to a sermon post. */
if ( ! defined( 'ABSPATH' ) ) exit;
$id      = get_the_ID();
$passage = jw_meta( 'passage' ) ?: get_the_title();
$book    = jw_meta( 'book' );
$audio   = function_exists( 'jw_audio_url' ) ? jw_audio_url( $id ) : jw_meta( 'audio_mp3' );
$arid    = jw_meta( 'archive_id' );
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
		<div class="card-actions">
			<?php if ( $arid ) : ?>
			<button class="heart" data-heart data-sermon="<?php echo esc_attr( $arid ); ?>"
				data-passage="<?php echo esc_attr( $passage ); ?>" aria-label="Save to favorites" aria-pressed="false">
				<svg viewBox="0 0 24 24"><path d="M12 21s-7.5-4.6-10-9.2C.6 9 1.6 5.7 4.6 5c1.9-.4 3.6.5 4.4 2 .8-1.5 2.5-2.4 4.4-2 3 .7 4 4 2.6 6.8C19.5 16.4 12 21 12 21z"/></svg>
			</button>
			<?php endif; ?>
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
	</div>
</article>
