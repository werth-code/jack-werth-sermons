<?php
/**
 * My Library — the signed-in member's saved sermons (and, soon, playlists).
 * Rendered/gated entirely client-side by account.js against Supabase.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<section class="section section--tight">
	<div class="wrap">
		<div class="crumbs"><a href="<?php echo esc_url( home_url() ); ?>">Home</a><span class="sep">/</span><span>My Library</span></div>
		<span class="kicker">Your Account</span>
		<h1 style="margin:.4rem 0 .5rem">My Library</h1>
		<p class="lede">Your saved sermons and playlists, synced to your account on every device.</p>
	</div>
</section>

<section class="section" style="padding-top:0">
	<div class="wrap" data-library>

		<!-- shown when signed out -->
		<div class="lib-gate" data-loggedout hidden>
			<span class="kicker center">Members</span>
			<h3 style="margin:.6rem 0 .3rem">Sign in to see your library</h3>
			<p class="lede" style="margin:0 auto;max-width:46ch">Save sermons with a tap, build playlists, and pick up
			right where you left off — on your phone, tablet, or computer.</p>
			<button class="btn" data-openauth>Sign in / Create account</button>
		</div>

		<!-- shown when signed in -->
		<div data-loggedin hidden>
			<div class="lib-section">
				<div class="sec-head"><div><span class="kicker">Saved</span>
					<h2>Favorites <span style="color:var(--muted)">(<span data-favcount>0</span>)</span></h2></div></div>
				<div class="card-grid" data-favgrid></div>
			</div>

			<div class="lib-section">
				<div class="sec-head"><div><span class="kicker">Collections</span><h2>Playlists</h2></div></div>
				<div data-playlists></div>
			</div>
		</div>

	</div>
</section>

<?php get_footer();
