<?php
/**
 * Contact page — branded form that emails BOTH matthewwerth@gmail.com and
 * rlwerth@gmail.com on a single submission, via FormSubmit (works on the static
 * GitHub Pages site too — no server needed).
 *
 * One-time activation: the first submission triggers a confirmation email to the
 * primary address; click "Activate" once and submissions flow to both thereafter.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Change these two lines to update recipients (kept here so it's obvious).
$jw_to = 'matthewwerth@gmail.com';   // primary recipient (To)
$jw_cc = 'rlwerth@gmail.com';        // second recipient (Cc) — both get every message
$jw_thanks = home_url( '/contact/?sent=1' );

get_header();
while ( have_posts() ) : the_post(); ?>

<section class="section section--tight">
	<div class="wrap" style="max-width:760px">
		<div class="crumbs"><a href="<?php echo esc_url( home_url() ); ?>">Home</a><span class="sep">/</span><span>Contact</span></div>
		<span class="kicker">Get in Touch</span>
		<h1 style="margin:.4rem 0 1rem">Contact</h1>
		<?php if ( trim( get_the_content() ) ) : ?>
			<div class="prose" style="max-width:60ch"><?php the_content(); ?></div>
		<?php else : ?>
			<p class="lede">Questions about a sermon, a request to use the recordings, or a word about how
			the library is serving your study — we’d love to hear from you.</p>
		<?php endif; ?>
	</div>
</section>

<section class="section" style="padding-top:0">
	<div class="wrap" style="max-width:760px">

		<div class="contact-success reveal" data-sent <?php echo isset( $_GET['sent'] ) ? '' : 'hidden'; ?>>
			<span class="kicker">Message Sent</span>
			<h3>Thank you — your message is on its way.</h3>
			<p>It’s been delivered to both of us, and we’ll get back to you soon.</p>
		</div>

		<form class="contact-form reveal" action="https://formsubmit.co/<?php echo esc_attr( $jw_to ); ?>" method="POST" data-contact>
			<!-- FormSubmit configuration -->
			<input type="hidden" name="_cc" value="<?php echo esc_attr( $jw_cc ); ?>">
			<input type="hidden" name="_subject" value="New message from the Jack Werth sermon library">
			<input type="hidden" name="_template" value="table">
			<input type="hidden" name="_captcha" value="false">
			<input type="hidden" name="_next" value="<?php echo esc_url( $jw_thanks ); ?>">
			<!-- honeypot: bots fill this; humans never see it -->
			<input type="text" name="_honey" class="honey" tabindex="-1" autocomplete="off" aria-hidden="true">

			<div class="cf-row">
				<label>Your name
					<input type="text" name="name" placeholder="Jane Doe" required>
				</label>
				<label>Your email
					<input type="email" name="email" placeholder="you@example.com" required>
				</label>
			</div>
			<label>Subject
				<input type="text" name="subject" placeholder="What’s this about?">
			</label>
			<label>Message
				<textarea name="message" rows="6" placeholder="Write your message…" required></textarea>
			</label>

			<button class="btn" type="submit">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
				Send Message
			</button>
			<p class="contact-note">Sent to Jack Werth’s team. We never share your email.</p>
		</form>
	</div>
</section>

<?php endwhile; get_footer();
