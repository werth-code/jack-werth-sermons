<?php
/** Generic page (About, For Pastors, Contact, …). */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
while ( have_posts() ) : the_post(); ?>
<section class="section section--tight">
	<div class="wrap" style="max-width:820px">
		<div class="crumbs"><a href="<?php echo esc_url( home_url() ); ?>">Home</a><span class="sep">/</span><span><?php the_title(); ?></span></div>
		<span class="kicker">Jack Werth</span>
		<h1 style="margin:.4rem 0 1.4rem"><?php the_title(); ?></h1>
	</div>
</section>
<section class="section" style="padding-top:0">
	<div class="wrap" style="max-width:820px">
		<div class="prose"><?php the_content(); ?></div>
	</div>
</section>
<?php endwhile; get_footer();
