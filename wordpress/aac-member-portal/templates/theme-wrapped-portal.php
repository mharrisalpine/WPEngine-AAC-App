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

	.aac-member-portal-theme-page > #aac-member-portal-root:not(.aac-member-portal-shell--signup) {
		margin-top: calc(var(--aac-portal-site-header-bottom, 0px) + clamp(1.5rem, 2.5vw, 2.5rem)) !important;
	}
</style>
<script id="aac-member-portal-theme-header-clearance">
	(function () {
		let measureFrame = 0;
		const syncHeaderClearance = function () {
			window.cancelAnimationFrame(measureFrame);
			measureFrame = window.requestAnimationFrame(function () {
				const siteHeader = document.getElementById('site-header');
				const headerBottom = siteHeader ? Math.max(0, siteHeader.getBoundingClientRect().bottom) : 0;
				document.documentElement.style.setProperty('--aac-portal-site-header-bottom', Math.ceil(headerBottom) + 'px');
			});
		};

		syncHeaderClearance();
		window.addEventListener('load', syncHeaderClearance);
		window.addEventListener('resize', syncHeaderClearance);
		document.addEventListener('click', function () {
			window.setTimeout(syncHeaderClearance, 50);
			window.setTimeout(syncHeaderClearance, 350);
		}, true);
	}());
</script>
<?php

get_footer();
