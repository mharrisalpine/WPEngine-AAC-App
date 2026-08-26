<?php

if (!defined('ABSPATH')) {
	exit;
}

get_header();

$portal_post = get_post();
?>
<div class="aac-member-portal-theme-page">
	<?php
	if ($portal_post instanceof WP_Post) {
		echo do_shortcode($portal_post->post_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		echo do_shortcode('[' . AAC_Member_Portal_Plugin::SHORTCODE . ']'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	?>
</div>
<style id="aac-member-portal-theme-page-shell">
	html,
	body,
	.aac-member-portal-theme-page {
		background: #fff;
	}

	.aac-member-portal-theme-page {
		display: block;
		width: 100%;
		max-width: none;
		margin: 0;
		padding: 0;
		overflow: visible;
	}

	.aac-member-portal-theme-page > #aac-member-portal-root {
		width: 100%;
		max-width: none;
		margin: 0;
	}
</style>
<?php

get_footer();
