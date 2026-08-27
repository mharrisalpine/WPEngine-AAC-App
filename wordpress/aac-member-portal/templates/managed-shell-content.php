<?php
/**
 * Inline managed shell used to wrap managed PMPro account pages inside the theme page.
 *
 * Expected variables:
 * - string $content
 * - string $portal_url
 * - string $billing_url
 * - string $orders_url
 * - string $cancel_url
 * - string $checkout_url
 * - string $confirmation_url
 * - string $page_title
 * - string $page_kicker
 * - string $page_description
 * - bool $is_account_page
 * - bool $is_billing_page
 * - bool $is_orders_page
 * - bool $is_cancel_page
 * - bool $is_checkout_page
 * - bool $is_confirmation_page
 */

if (!defined('ABSPATH')) {
	exit;
}

$portal_plugin = $GLOBALS['aac_member_portal_plugin'] ?? null;
$portal_design_settings = $portal_plugin instanceof AAC_Member_Portal_Plugin
	? $portal_plugin->get_template_design_settings()
	: [
		'sidebar_background_url' => 'https://wallpapers.com/images/high/abstract-black-topographic-map-q34pt7luthso1030.webp',
		'sidebar_overlay_start' => '0.18',
		'sidebar_overlay_end' => '0.30',
		'sidebar_button_background' => '#000000',
		'sidebar_button_hover_background' => '#111111',
		'sidebar_button_active_background' => '#000000',
		'sidebar_accent_color' => '#f8c235',
		'publication_tile_images' => [
			'aaj' => '',
			'anac' => '',
			'acj' => '',
			'guidebook' => '',
		],
	];
$managed_account_url = untrailingslashit((string) ($portal_url ?? home_url('/membership/'))) . '/#/membership';
$sidebar_overlay_start = max(0.72, (float) ($portal_design_settings['sidebar_overlay_start'] ?? 0));
$sidebar_overlay_end = max(0.82, (float) ($portal_design_settings['sidebar_overlay_end'] ?? 0));
$checkout_profile_defaults = $portal_plugin instanceof AAC_Member_Portal_Plugin
	? $portal_plugin->get_pmpro_checkout_profile_defaults()
	: [
		'publication_pref' => 'Print',
		'aaj_pref' => 'Print',
		'anac_pref' => 'Print',
		'acj_pref' => 'Print',
		'guidebook_pref' => 'Print',
		'size' => 'No T-shirt',
	];
$checkout_tshirt_size_options = $portal_plugin instanceof AAC_Member_Portal_Plugin
	? $portal_plugin->get_pmpro_tshirt_size_options()
	: [
		['value' => 'No T-shirt', 'label' => 'No T-shirt'],
		['value' => 'Unisex Small', 'label' => 'Unisex Small'],
		['value' => 'Unisex Medium', 'label' => 'Unisex Medium'],
		['value' => 'Unisex Large', 'label' => 'Unisex Large'],
		['value' => 'Unisex X-Large', 'label' => 'Unisex X-Large'],
		['value' => 'Unisex XX-Large', 'label' => 'Unisex XX-Large'],
	];
$is_embed_request = isset($_GET['aac_embed']) && sanitize_text_field(wp_unslash($_GET['aac_embed'])) === '1';
$is_logged_in = is_user_logged_in();
$current_member = $is_logged_in ? wp_get_current_user() : null;
$current_member_id = $current_member instanceof WP_User && $current_member->exists() ? (int) $current_member->ID : 0;
$current_primary_membership = $current_member_id ? AAC_Member_Portal_PMPro::get_primary_membership($current_member_id) : null;
$current_membership_actions = ($current_member_id && $current_primary_membership)
	? AAC_Member_Portal_PMPro::build_membership_actions($current_member_id, ['tier' => $current_primary_membership['tier']])
	: [
		'account_url' => $account_url,
		'billing_url' => $billing_url,
		'cancel_url' => $cancel_url,
		'current_level_id' => null,
		'current_subscription_id' => null,
		'current_level_checkout_url' => '',
		'levels' => new stdClass(),
	];
$current_auto_renew = $current_member_id && !empty($current_membership_actions['current_level_id'])
	? AAC_Member_Portal_PMPro::has_active_auto_renewal($current_member_id, (int) $current_membership_actions['current_level_id'])
	: false;
$current_can_cancel_membership = $current_auto_renew && !empty($current_membership_actions['cancel_url']);
$current_renewal_date = is_array($current_primary_membership) ? ($current_primary_membership['renewal_date'] ?? '') : '';
$current_expiration_date = is_array($current_primary_membership) ? ($current_primary_membership['expiration_date'] ?? '') : '';
$current_pending_downgrade = is_array($current_membership_actions['pending_downgrade'] ?? null) ? $current_membership_actions['pending_downgrade'] : null;
$managed_billing_url = !empty($current_membership_actions['billing_url'])
	? $current_membership_actions['billing_url']
	: $billing_url;
if (untrailingslashit((string) wp_parse_url($managed_billing_url, PHP_URL_PATH)) === untrailingslashit((string) wp_parse_url($account_url, PHP_URL_PATH))) {
	$managed_billing_url = $billing_url;
}

if (!function_exists('aac_member_portal_sidebar_icon_svg')) {
	function aac_member_portal_sidebar_icon_svg($icon) {
		$icons = [
			'user' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
			'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V6l8-3 8 3z"/></svg>',
			'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.54V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1-1.54 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.7 1.7 0 0 0 .34-1.87 1.7 1.7 0 0 0-1.54-1H3a2 2 0 1 1 0-4h.09a1.7 1.7 0 0 0 1.54-1 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.7 1.7 0 0 0 1.87.34H9A1.7 1.7 0 0 0 10 3.09V3a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1 1.54 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87V9c0 .67.39 1.28 1 1.54.18.08.37.13.57.13H21a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.54 1Z"/></svg>',
			'pen' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4Z"/></svg>',
			'book' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 7v14"/><path d="M3 18.5A2.5 2.5 0 0 1 5.5 16H12v5H5.5A2.5 2.5 0 0 1 3 18.5Z"/><path d="M21 18.5a2.5 2.5 0 0 0-2.5-2.5H12v5h6.5A2.5 2.5 0 0 0 21 18.5Z"/><path d="M5.5 16V5a2 2 0 0 1 2-2H12v13H5.5Z"/><path d="M18.5 16V5a2 2 0 0 0-2-2H12v13h6.5Z"/></svg>',
			'credit-card' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h2"/><path d="M10 15h4"/></svg>',
			'receipt' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 3h16v18l-2-1.5-2 1.5-2-1.5-2 1.5-2-1.5-2 1.5-2-1.5-2 1.5Z"/><path d="M8 7h8"/><path d="M8 11h8"/><path d="M8 15h5"/></svg>',
			'file-text' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v6h6"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>',
			'x-circle' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>',
			'tag' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.6 13.4L13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z"/><circle cx="8.5" cy="8.5" r="1.5"/></svg>',
			'badge-percent' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.78 4.78 4 4 0 0 1-6.74 0 4 4 0 0 1-4.78-4.78 4 4 0 0 1 0-6.75Z"/><path d="m15 9-6 6"/><path d="M9 9h.01"/><path d="M15 15h.01"/></svg>',
			'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
			'mail' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
		];

		return $icons[$icon] ?? $icons['user'];
	}
}

$top_nav = $portal_plugin instanceof AAC_Member_Portal_Plugin
	? $portal_plugin->get_template_top_nav_sections($portal_url)
	: [];

$portal_sections = $portal_plugin instanceof AAC_Member_Portal_Plugin
	? $portal_plugin->get_template_sidebar_sections($portal_url)
	: [];
?>
<style>
	.wp-site-blocks > header,
	.wp-site-blocks > footer,
	.wp-site-blocks > main > .wp-block-group > .wp-block-group > .wp-block-post-title,
	.wp-site-blocks > main .wp-block-post-title,
	.wp-site-blocks > main .entry-title {
		display: none !important;
	}

	.wp-site-blocks > main,
	.wp-site-blocks > main .wp-block-group,
	.wp-site-blocks > main .wp-block-columns,
	.wp-site-blocks > main .wp-block-column {
		margin: 0 !important;
		padding: 0 !important;
		max-width: none !important;
		background: #ffffff !important;
		background-image: none !important;
	}

	.wp-site-blocks > main .entry-content {
		margin: 0 !important;
		max-width: none !important;
		background: #ffffff !important;
		background-image: none !important;
	}

	.aac-managed-shell {
		width: 100%;
		max-width: 100%;
		margin-left: 0;
		min-height: 100vh;
		overflow-x: clip;
		background: #ffffff;
		background-image: none;
		color: #0c0a09;
		padding-top: clamp(2.25rem, 4vw, 3.5rem);
	}

	.aac-managed-header {
		position: sticky;
		top: 0;
		z-index: 50;
		border-bottom: 1px solid rgba(255, 255, 255, 0.1);
		background: rgba(3, 0, 0, 0.96);
		backdrop-filter: blur(14px);
	}

	.aac-managed-header__inner {
		width: 100%;
		padding-top: env(safe-area-inset-top, 0px);
	}

	.aac-managed-header__bar,
	.aac-managed-actions,
	.aac-managed-topnav,
	.aac-managed-actions-row,
	.aac-managed-layout,
	.aac-managed-card .pmpro_actions_nav,
	.aac-managed-card .pmpro_card_actions {
		display: flex;
		flex-wrap: wrap;
		gap: 0.75rem;
	}

	.aac-managed-header__bar {
		display: grid;
		grid-template-columns: auto minmax(0, 1fr) auto;
		align-items: stretch;
		min-height: 4.75rem;
	}

	.aac-managed-logo img {
		display: block;
		height: 48px;
		width: auto;
	}

	.aac-managed-logo {
		display: flex;
		align-items: center;
		padding: 0 1.5rem;
		border-right: 1px solid rgba(255, 255, 255, 0.1);
	}

	.aac-managed-actions {
		align-items: center;
		padding: 0 1.5rem;
		border-left: 1px solid rgba(255, 255, 255, 0.1);
	}

	.aac-managed-topnav {
		flex-wrap: nowrap;
		align-items: stretch;
		justify-content: flex-start;
		gap: 0;
		min-width: 0;
		padding: 0 1rem;
		overflow-x: auto;
	}

	.aac-managed-topnav a,
	.aac-managed-pill {
		text-decoration: none;
		transition: color 0.2s ease, background-color 0.2s ease, border-color 0.2s ease;
	}

	.aac-managed-topnav__item {
		position: relative;
		display: flex;
		align-items: stretch;
	}

	.aac-managed-topnav__trigger {
		display: inline-flex;
		align-items: center;
		gap: 0.6rem;
		min-height: 4.75rem;
		padding: 0 0.9rem;
		white-space: nowrap;
		color: rgba(255, 255, 255, 0.92);
		font-size: 0.82rem;
		font-weight: 700;
		letter-spacing: 0.14em;
		text-transform: uppercase;
	}

	.aac-managed-topnav__caret {
		display: inline-flex;
		align-items: center;
		justify-content: flex-start;
		min-width: 1rem;
		color: #f8c235;
		font-size: 1.35rem;
		font-weight: 500;
		line-height: 1;
		opacity: 0.92;
	}

	.aac-managed-topnav__trigger:hover,
	.aac-managed-topnav__item:focus-within .aac-managed-topnav__trigger {
		color: #f8c235;
	}

	.aac-managed-topnav__panel {
		position: absolute;
		left: 0;
		top: 100%;
		z-index: 90;
		visibility: hidden;
		min-width: 18rem;
		max-width: 22rem;
		padding-top: 0.75rem;
		opacity: 0;
		transition: opacity 0.15s ease, visibility 0.15s ease;
	}

	.aac-managed-topnav__item:hover .aac-managed-topnav__panel,
	.aac-managed-topnav__item:focus-within .aac-managed-topnav__panel {
		visibility: visible;
		opacity: 1;
	}

	.aac-managed-topnav__panel-inner {
		border: 1px solid rgba(255, 255, 255, 0.12);
		border-radius: 0;
		background: rgba(11, 9, 8, 0.95);
		padding: 1.25rem;
		box-shadow: 0 28px 80px rgba(0, 0, 0, 0.45);
		backdrop-filter: blur(14px);
	}

	.aac-managed-topnav__panel-title {
		display: block;
		margin-bottom: 0.75rem;
		padding: 0 1rem;
		color: #f8c235;
		font-size: 0.68rem;
		font-weight: 600;
		letter-spacing: 0.25em;
		text-transform: uppercase;
	}

	.aac-managed-topnav__panel ul {
		list-style: none;
		margin: 0;
		padding: 0;
	}

	.aac-managed-topnav__panel li + li {
		margin-top: 0.25rem;
	}

	.aac-managed-topnav__link {
		display: block;
		border-radius: 1rem;
		padding: 0.8rem 1rem;
		color: #f4efe7;
		font-size: 0.95rem;
		font-weight: 500;
		letter-spacing: normal;
		text-transform: none;
	}

	.aac-managed-topnav__link:hover {
		background: rgba(255, 255, 255, 0.08);
		color: #f8c235;
	}

	.aac-managed-topnav__link--overview {
		font-weight: 700;
		color: #fff;
	}

	.aac-managed-pill {
		display: inline-flex;
		align-items: center;
		justify-content: flex-start;
		min-height: 3rem;
		padding: 0 1.2rem;
		border-radius: 0;
		font-size: 0.78rem;
		font-weight: 700;
		letter-spacing: 0.14em;
		text-transform: uppercase;
	}

	.aac-managed-pill--icon {
		width: 3rem;
		min-width: 3rem;
		padding: 0;
	}

	.aac-managed-pill--icon svg {
		width: 1.35rem;
		height: 1.35rem;
	}

	.aac-managed-shell button,
	.aac-managed-shell input[type="submit"],
	.aac-managed-shell input[type="button"],
	.aac-managed-shell input[type="reset"],
	.aac-managed-shell .button,
	.aac-managed-shell .pmpro_btn,
	.aac-managed-shell .pmpro_btn-submit,
	.aac-managed-shell .pmpro_btn-select,
	.aac-managed-shell .wp-block-button__link,
	.aac-managed-shell .wp-element-button {
		border-radius: 0 !important;
	}

	.aac-managed-pill--ghost {
		border: 1px solid #b71c1c;
		background: #ffffff;
		color: #8f1515;
	}

	.aac-managed-pill--ghost:hover {
		border-color: #8f1515;
		background: #fff5f5;
		color: #6b1010;
	}

	.aac-managed-pill--primary {
		background: #b71c1c;
		color: #ffffff;
	}

	.aac-managed-pill--primary:hover {
		background: #8f1515;
	}

	.aac-managed-pill--danger {
		background: #8f1515;
		color: #fff;
	}

	.aac-managed-pill--danger:hover {
		background: #6b1010;
	}

	.aac-managed-layout {
		display: block;
		min-height: 100vh;
	}

	.aac-managed-sidebar {
		position: sticky;
		top: 0;
		width: 100%;
		height: auto;
		min-height: 0;
		max-height: none;
		overflow-x: auto;
		overflow-y: hidden;
		border-right: 0;
		border-top: 1px solid rgba(3, 0, 0, 0.08);
		border-bottom: 1px solid rgba(3, 0, 0, 0.12);
		background: #ffffff;
		color: #16130f;
		padding: 1rem;
		box-sizing: border-box;
		box-shadow: none;
		z-index: 4;
		text-align: center;
	}

	.aac-managed-sidebar::before {
		display: none;
	}

	.aac-managed-sidebar__section + .aac-managed-sidebar__section {
		margin-top: 0;
		margin-left: 0.75rem;
	}

	.aac-managed-sidebar__section-title {
		display: none;
	}

	.aac-managed-sidebar ul {
		display: flex;
		flex-wrap: nowrap;
		align-items: center;
		justify-content: center;
		gap: 0.75rem;
		list-style: none;
		margin: 0;
		padding: 0;
	}

	.aac-managed-sidebar,
	.aac-managed-sidebar__section {
		scrollbar-width: none;
	}

	.aac-managed-sidebar::-webkit-scrollbar,
	.aac-managed-sidebar__section::-webkit-scrollbar {
		display: none;
	}

	.aac-managed-sidebar__section {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		vertical-align: top;
	}

	.aac-managed-sidebar a {
		display: flex;
		align-items: center;
		gap: 0.75rem;
		justify-content: center;
		position: relative;
		min-height: 3.25rem;
		min-width: 12.75rem;
		padding: 0.95rem 1.5rem;
		border: 0;
		background: #ffffff;
		color: #16130f;
		font-size: 0.98rem;
		font-weight: 500;
		text-align: center;
		text-decoration: none;
		transition: all 0.2s ease;
		white-space: nowrap;
	}

	.aac-managed-sidebar a:hover {
		background: #fff5f5;
		color: #8f1515;
	}

	.aac-managed-sidebar__icon {
		display: inline-flex;
		width: 1.25rem;
		height: 1.25rem;
		flex: 0 0 auto;
		color: currentColor;
	}

	.aac-managed-sidebar__icon svg {
		width: 100%;
		height: 100%;
	}

	.aac-managed-sidebar > * {
		position: relative;
		z-index: 1;
	}

	.aac-managed-sidebar__label {
		position: static;
		z-index: auto;
		display: inline-flex;
		align-items: center;
		min-height: 0;
		min-width: 0;
		padding: 0;
		border: 0;
		background: transparent;
		box-shadow: none;
		white-space: normal;
		opacity: 1;
		pointer-events: auto;
		transform: none;
	}

	.aac-managed-sidebar a[aria-current="page"] .aac-managed-sidebar__icon {
		color: #ffffff;
	}

	.aac-managed-sidebar a[aria-current="page"] {
		background: #b71c1c;
		color: #ffffff;
	}

	.aac-managed-main {
		flex: 1;
		min-width: 0;
		padding: 2rem 1rem 2.5rem;
		box-sizing: border-box;
	}

	.aac-managed-main__inner {
		max-width: 80rem;
		margin: 0 auto;
	}

	.aac-managed-hero {
		border: 0;
		border-bottom: 2px solid #b71c1c;
		background: #ffffff;
		color: #0c0a09;
		border-radius: 0;
		padding: 0 0 1.5rem;
		box-shadow: none;
	}

	.aac-managed-hero__kicker {
		margin: 0;
		color: #b71c1c;
		font-size: 0.72rem;
		font-weight: 700;
		letter-spacing: 0.3em;
		text-transform: uppercase;
	}

	.aac-managed-hero h1 {
		margin: 0.75rem 0 0;
		font-size: clamp(2rem, 4vw, 2.75rem);
		line-height: 1.1;
	}

	.aac-managed-hero p {
		max-width: 46rem;
		margin: 0.85rem 0 0;
		color: #57534e;
		font-size: 1rem;
		line-height: 1.75;
	}

	.aac-managed-actions-row {
		display: grid;
		grid-template-columns: repeat(3, minmax(0, 1fr));
		gap: 0.9rem;
		margin-top: 1.8rem;
	}

	.aac-managed-actions-row .aac-managed-pill {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 0.75rem;
		width: 100%;
		min-height: 4rem;
		padding: 0 1.25rem;
		border: 1px solid #d8d2c7;
		background: #ffffff;
		color: #16130f;
		font-size: 0.78rem;
		font-weight: 800;
		letter-spacing: 0.18em;
		text-align: center;
		text-transform: uppercase;
	}

	.aac-managed-actions-row .aac-managed-pill svg {
		width: 1rem;
		height: 1rem;
		flex: 0 0 auto;
	}

	.aac-managed-actions-row .aac-managed-pill--ghost {
		border-color: #d8d2c7;
		background: #ffffff;
		color: #16130f;
	}

	.aac-managed-actions-row .aac-managed-pill--ghost:hover {
		border-color: #b71c1c;
		background: #fffafa;
		color: #8f1515;
	}

	.aac-managed-actions-row .aac-managed-pill--primary {
		border-color: #b71c1c;
		background: #b71c1c;
		color: #ffffff;
	}

	.aac-managed-actions-row .aac-managed-pill--primary:hover {
		background: #8f1515;
	}

	@media (max-width: 760px) {
		.aac-managed-actions-row {
			grid-template-columns: 1fr;
		}
	}

	.aac-managed-card {
		margin-top: 1.5rem;
		border: 0;
		border-top: 2px solid #b71c1c;
		border-radius: 0;
		background: #ffffff;
		padding: 1.5rem 0 0;
		box-shadow: none;
	}

	.aac-managed-card .pmpro_section,
	.aac-managed-card .pmpro_card,
	.aac-managed-card .pmpro_message,
	.aac-managed-card form.pmpro_form,
	.aac-managed-card .pmpro_checkout_gateway,
	.aac-managed-card .pmpro_invoice,
	.aac-managed-card .pmpro_checkout-fields {
		border: 0;
		border-top: 1px solid #e7e5e4;
		border-radius: 0;
		background: #ffffff;
		padding: 1.2rem 0;
		box-shadow: none;
	}

	.aac-managed-card .pmpro_section + .pmpro_section,
	.aac-managed-card .pmpro_card + .pmpro_card,
	.aac-managed-card .pmpro_checkout-fields + .pmpro_checkout-fields {
		margin-top: 1rem;
	}

	body.pmpro-cancel .aac-managed-card .pmpro,
	body.pmpro-cancel .aac-managed-card .pmpro_section,
	body.pmpro-cancel .aac-managed-card .pmpro_card,
	body.pmpro-cancel .aac-managed-card form.pmpro_form,
	body.pmpro-cancel .aac-managed-card .pmpro_card_content {
		margin: 0;
		border: 0;
		border-radius: 0;
		background: transparent;
		box-shadow: none;
		padding: 0;
	}

	body.pmpro-cancel .aac-managed-card #pmpro_form_fieldset-discount-fields,
	body.pmpro-cancel .aac-managed-card #other_discount_code_p,
	body.pmpro-cancel .aac-managed-card #other_discount_code_tr,
	body.pmpro-cancel .aac-managed-card #discount_code,
	body.pmpro-cancel .aac-managed-card #pmpro_discount_code,
	body.pmpro-cancel .aac-managed-card #pmpro_discount_code_button,
	body.pmpro-cancel .aac-managed-card .pmpro_checkout-field-discount_code,
	body.pmpro-cancel .aac-managed-card .pmpro_checkout-fields-discount_code,
	body.pmpro-cancel .aac-managed-card .pmpro_payment-discount-code,
	body.pmpro-cancel .aac-managed-card .pmpro_level_discount_applied {
		display: none !important;
	}

	body.pmpro-cancel .aac-managed-card .pmpro_form_submit {
		margin-top: 1.25rem;
		padding-top: 0;
	}

	body.pmpro-cancel .aac-managed-card .aac-cancel-fallback-actions {
		justify-content: flex-start;
		gap: 1rem;
		margin-top: 1.5rem;
		padding-top: 0.25rem;
		text-align: center;
	}

	body.pmpro-cancel .aac-managed-card .aac-cancel-fallback-button {
		display: inline-flex;
		align-items: center;
		justify-content: flex-start;
		min-height: 3rem;
		padding: 0.9rem 1.45rem;
		border: 2px solid #8f1515;
		font-size: 0.78rem;
		font-weight: 900;
		letter-spacing: 0.11em;
		line-height: 1.1;
		text-align: center;
		text-decoration: none;
		text-transform: uppercase;
		transition: background-color 160ms ease, color 160ms ease, border-color 160ms ease;
	}

	body.pmpro-cancel .aac-managed-card .aac-cancel-fallback-button--return {
		background: #8f1515;
		color: #ffffff !important;
	}

	body.pmpro-cancel .aac-managed-card .aac-cancel-fallback-button--return:hover,
	body.pmpro-cancel .aac-managed-card .aac-cancel-fallback-button--return:focus-visible {
		background: #6f1010;
		border-color: #6f1010;
	}

	body.pmpro-cancel .aac-managed-card .aac-cancel-fallback-button--continue {
		background: #ffffff;
		color: #8f1515 !important;
	}

	body.pmpro-cancel .aac-managed-card .aac-cancel-fallback-button--continue:hover,
	body.pmpro-cancel .aac-managed-card .aac-cancel-fallback-button--continue:focus-visible {
		background: #fff5f5;
		border-color: #6f1010;
		color: #6f1010 !important;
	}

	@media (max-width: 640px) {
		body.pmpro-cancel .aac-managed-card .aac-cancel-fallback-actions {
			align-items: stretch;
			flex-direction: column;
		}

		body.pmpro-cancel .aac-managed-card .aac-cancel-fallback-button {
			width: 100%;
		}
	}

	body.pmpro-billing .aac-managed-card .pmpro,
	body.pmpro-billing .aac-managed-card .pmpro_section,
	body.pmpro-billing .aac-managed-card .pmpro_card,
	body.pmpro-billing .aac-managed-card .pmpro_card_content {
		border: 0;
		border-radius: 0;
		background: transparent;
		box-shadow: none;
	}

	body.pmpro-billing .aac-managed-card .pmpro {
		padding: 0;
	}

	body.pmpro-billing .aac-managed-card .pmpro_section,
	body.pmpro-billing .aac-managed-card .pmpro_card,
	body.pmpro-billing .aac-managed-card .pmpro_card_content {
		padding: 0;
	}

	body.pmpro-billing .aac-managed-card .pmpro_spacer {
		display: none;
	}

	body.pmpro-billing .aac-managed-card .pmpro_section + .pmpro_section,
	body.pmpro-billing .aac-managed-card .pmpro_card + .pmpro_card,
	body.pmpro-billing .aac-managed-card .pmpro_actions_nav {
		margin-top: 1rem;
	}

	body.pmpro-billing .aac-managed-card .pmpro_section_title,
	body.pmpro-billing .aac-managed-card .pmpro_card_title {
		margin-bottom: 0.7rem;
	}

	body.pmpro-billing .aac-managed-card .pmpro_card_actions {
		margin-top: 0.75rem;
		padding-top: 0;
	}

	body.pmpro-billing .aac-managed-card form.pmpro_form,
	body.pmpro-billing .aac-managed-card .pmpro_form_fields,
	body.pmpro-billing .aac-managed-card .pmpro_form_field,
	body.pmpro-billing .aac-managed-card .pmpro_card_fields,
	body.pmpro-billing .aac-managed-card .pmpro_payment_information,
	body.pmpro-billing .aac-managed-card #pmpro_payment_information_fields,
	body.pmpro-billing .aac-managed-card #pmpro_payment_method,
	body.pmpro-billing .aac-managed-card #pmpro_payment_method_fields,
	body.pmpro-billing .aac-managed-card #pmpro_payment_information_fields .pmpro_card_content,
	body.pmpro-billing .aac-managed-card .pmpro_checkout_gateway,
	body.pmpro-billing .aac-managed-card .pmpro_payment_gateway,
	body.pmpro-billing .aac-managed-card .StripeElement,
	body.pmpro-billing .aac-managed-card .__PrivateStripeElement,
	body.pmpro-billing .aac-managed-card [class*="stripe"],
	body.pmpro-billing .aac-managed-card [id*="stripe"],
	body.pmpro-billing .aac-managed-card [class*="card"],
	body.pmpro-billing .aac-managed-card iframe {
		visibility: visible !important;
		opacity: 1 !important;
		max-height: none !important;
		overflow: visible !important;
	}

	body.pmpro-billing .aac-managed-card .StripeElement,
	body.pmpro-billing .aac-managed-card .__PrivateStripeElement,
	body.pmpro-billing .aac-managed-card iframe {
		display: block !important;
		min-height: 2.75rem !important;
		width: 100% !important;
	}

	body.pmpro-billing .aac-managed-card .pmpro_form_field,
	body.pmpro-billing .aac-managed-card .pmpro_card_fields,
	body.pmpro-billing .aac-managed-card .pmpro_form_fields {
		min-height: auto !important;
	}

	body.pmpro-confirmation .aac-managed-card,
	body.pmpro-confirmation .aac-managed-card .pmpro,
	body.pmpro-confirmation .aac-managed-card .pmpro_section,
	body.pmpro-confirmation .aac-managed-card .pmpro_card,
	body.pmpro-confirmation .aac-managed-card .pmpro_card_content,
	body.pmpro-confirmation .aac-managed-card .pmpro_invoice,
	body.pmpro-confirmation .aac-managed-card .aac-pmpro-confirmation-fallback,
	.aac-managed-card .aac-pmpro-confirmation-fallback,
	.aac-managed-card .aac-pmpro-confirmation-fallback__section {
		border: 0 !important;
		border-radius: 0 !important;
		background: #fff !important;
		background-image: none !important;
		box-shadow: none !important;
	}

	body.pmpro-confirmation .aac-managed-card .pmpro_section,
	body.pmpro-confirmation .aac-managed-card .pmpro_card,
	body.pmpro-confirmation .aac-managed-card .pmpro_card_content,
	body.pmpro-confirmation .aac-managed-card .pmpro_invoice,
	.aac-managed-card .aac-pmpro-confirmation-fallback,
	.aac-managed-card .aac-pmpro-confirmation-fallback__section {
		margin: 0 !important;
		padding: 0 !important;
	}

	.aac-managed-card .aac-pmpro-confirmation-fallback__heading {
		margin: 0 0 1.25rem;
		padding-bottom: 1rem;
		border-bottom: 2px solid #b71c1c;
	}

	.aac-managed-card .aac-pmpro-confirmation-fallback__heading h2 {
		margin: 0;
		color: #0c0a09;
		font-size: clamp(1.45rem, 2vw, 2rem);
		line-height: 1.15;
	}

	.aac-managed-card .aac-pmpro-confirmation-fallback__actions {
		justify-content: flex-start;
		margin-top: 1.5rem;
		padding-top: 1.25rem;
		border-top: 2px solid #b71c1c;
	}

	body.pmpro-checkout .aac-managed-card {
		border: 0 !important;
		background: transparent !important;
		box-shadow: none !important;
		padding: 0 !important;
	}

	body.pmpro-checkout .aac-managed-card .pmpro,
	body.pmpro-checkout .aac-managed-card .pmpro_section,
	body.pmpro-checkout .aac-managed-card form.pmpro_form,
	body.pmpro-checkout .aac-managed-card .pmpro_checkout_gateway,
	body.pmpro-checkout .aac-managed-card .pmpro_invoice {
		border: 0;
		border-radius: 0;
		background: transparent;
		box-shadow: none;
		padding: 0;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_form_fieldset {
		margin: 0;
		padding: 0;
		border: 0;
		border-radius: 0 !important;
		background: transparent !important;
		box-shadow: none !important;
	}

	body.pmpro-checkout .aac-managed-card #username_div,
	body.pmpro-checkout .aac-managed-card .pmpro_form_field-username,
	body.pmpro-checkout .aac-managed-card .pmpro_checkout-field-username,
	body.pmpro-checkout .aac-managed-card .pmpro_checkout-field-user_login,
	body.pmpro-checkout .aac-managed-card [data-name="username"],
	body.pmpro-checkout .aac-managed-card input[name="username"],
	body.pmpro-checkout .aac-managed-card input[name="user_login"] {
		display: none !important;
		visibility: hidden !important;
		position: absolute !important;
		width: 1px !important;
		height: 1px !important;
		overflow: hidden !important;
		pointer-events: none !important;
	}

	body.pmpro-checkout #pmpro_form_fieldset-discount-fields {
		display: none !important;
		visibility: hidden !important;
	}

	body.pmpro-checkout .aac-student-university-field input,
	body.pmpro-checkout input[name="student_university"],
	body.pmpro-checkout input[name="university_or_school"],
	body.pmpro-checkout #t_shirt_div select,
	body.pmpro-checkout select[name="t_shirt"],
	body.pmpro-checkout select[id="t_shirt"],
	body.pmpro-checkout #service_component_div select,
	body.pmpro-checkout #military_service_component_div select,
	body.pmpro-checkout select[name="service_component"],
	body.pmpro-checkout select[name="military_service_component"],
	body.pmpro-checkout select[name="service_branch"] {
		background: #ffffff !important;
		color: #030000 !important;
		color-scheme: light;
	}

	body.pmpro-checkout #t_shirt_div select,
	body.pmpro-checkout select[name="t_shirt"],
	body.pmpro-checkout select[id="t_shirt"],
	body.pmpro-checkout #service_component_div select,
	body.pmpro-checkout #military_service_component_div select,
	body.pmpro-checkout select[name="service_component"],
	body.pmpro-checkout select[name="military_service_component"],
	body.pmpro-checkout select[name="service_branch"] {
		border: 1px solid #d6d3d1 !important;
		border-radius: 0 !important;
		box-shadow: none !important;
		min-height: 3.25rem;
	}

	body.pmpro-checkout #t_shirt_div select option,
	body.pmpro-checkout select[name="t_shirt"] option,
	body.pmpro-checkout select[id="t_shirt"] option,
	body.pmpro-checkout #service_component_div select option,
	body.pmpro-checkout #military_service_component_div select option,
	body.pmpro-checkout select[name="service_component"] option,
	body.pmpro-checkout select[name="military_service_component"] option,
	body.pmpro-checkout select[name="service_branch"] option {
		background: #ffffff !important;
		color: #16130f !important;
	}

	body.pmpro-checkout .aac-student-university-field {
		position: relative;
	}

	body.pmpro-checkout .aac-student-university-dropdown {
		position: absolute;
		z-index: 10000;
		top: calc(100% + 0.25rem);
		left: 0;
		right: 0;
		max-height: 16rem;
		overflow-y: auto;
		border: 1px solid #d6d3d1;
		background: #ffffff;
		box-shadow: 0 14px 30px rgba(12, 10, 9, 0.12);
	}

	body.pmpro-checkout .aac-student-university-dropdown[hidden] {
		display: none !important;
	}

	body.pmpro-checkout .aac-student-university-dropdown__option {
		display: block;
		width: 100%;
		border: 0;
		border-bottom: 1px solid #eee7dc;
		background: #ffffff;
		color: #16130f;
		padding: 0.72rem 0.85rem;
		text-align: left;
		font: inherit;
		line-height: 1.35;
		cursor: pointer;
	}

	body.pmpro-checkout .aac-student-university-dropdown__option:hover,
	body.pmpro-checkout .aac-student-university-dropdown__option:focus {
		background: #f7f3ec;
		color: #8f1515;
		outline: none;
	}

	body.pmpro-checkout .aac-student-university-dropdown__empty {
		padding: 0.72rem 0.85rem;
		color: #57534e;
		background: #ffffff;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_card,
	body.pmpro-checkout .aac-managed-card .pmpro_card_content,
	body.pmpro-checkout .aac-managed-card .pmpro_form_fieldset > .pmpro_card,
	body.pmpro-checkout .aac-managed-card .pmpro_checkout-fields > .pmpro_card,
	body.pmpro-checkout .aac-managed-card .pmpro_form_fieldset > .pmpro_card > .pmpro_card_content,
	body.pmpro-checkout .aac-managed-card .pmpro_checkout-fields > .pmpro_card > .pmpro_card_content,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card,
	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__card,
	body.pmpro-checkout .aac-managed-card .aac-partner-family__card-inner,
	body.pmpro-checkout .aac-managed-card .aac-partner-family__dependents,
	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary,
	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo,
	body.pmpro-checkout .aac-managed-card .aac-donation-option,
	body.pmpro-checkout .aac-managed-card .aac-order-summary,
	body.pmpro-checkout .aac-managed-card .aac-order-summary__row {
		border-radius: 0 !important;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_form_fieldset > .pmpro_card > .pmpro_card_content {
		display: grid;
		gap: 0.7rem;
		align-content: start;
		padding-top: 0 !important;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_form_legend {
		display: block;
		width: 100%;
		max-width: none;
		margin: 0 !important;
		padding: 0 !important;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_form_legend:first-child,
	body.pmpro-checkout .aac-managed-card .pmpro_card_content > :first-child {
		margin-top: 0 !important;
		padding-top: 0 !important;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_form_fieldset > .pmpro_card,
	body.pmpro-checkout .aac-managed-card .pmpro_checkout-fields > .pmpro_card,
	body.pmpro-checkout .aac-managed-card .pmpro_form_fieldset > .pmpro_card > .pmpro_card_content,
	body.pmpro-checkout .aac-managed-card .pmpro_checkout-fields > .pmpro_card > .pmpro_card_content {
		border-radius: 0 !important;
		box-shadow: none !important;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_form_fieldset > .pmpro_card,
	body.pmpro-checkout .aac-managed-card .pmpro_checkout-fields > .pmpro_card {
		overflow: hidden;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_form_fieldset > .pmpro_card > .pmpro_card_content,
	body.pmpro-checkout .aac-managed-card .pmpro_checkout-fields > .pmpro_card > .pmpro_card_content {
		border-radius: 0 !important;
		padding: 1.15rem 1.2rem !important;
	}

	html:has(body.pmpro-checkout),
	body.pmpro-checkout,
	body.pmpro-checkout #page,
	body.pmpro-checkout .site,
	body.pmpro-checkout .site-content,
	body.pmpro-checkout .entry-content,
	body.pmpro-checkout #aac-member-portal-root,
	body.pmpro-checkout .aac-managed-shell,
	body.pmpro-checkout .aac-managed-card {
		background: #fff !important;
		background-image: none !important;
	}

	body.pmpro-checkout .aac-managed-card .pmpro,
	body.pmpro-checkout .aac-managed-card .pmpro_section,
	body.pmpro-checkout .aac-managed-card form.pmpro_form,
	body.pmpro-checkout .aac-managed-card .pmpro_checkout_gateway,
	body.pmpro-checkout .aac-managed-card .pmpro_invoice,
	body.pmpro-checkout .aac-managed-card .pmpro_checkout-fields,
	body.pmpro-checkout .aac-managed-card .pmpro_form_fieldset,
	body.pmpro-checkout .aac-managed-card .pmpro_card,
	body.pmpro-checkout .aac-managed-card .pmpro_card_content,
	body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields,
	body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields > .pmpro_card,
	body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields > .pmpro_card > .pmpro_card_content,
	body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields .pmpro_card_fields,
	body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields .pmpro_payment-discount-code,
	body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields .pmpro_form_fields,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card,
	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__card,
	body.pmpro-checkout .aac-managed-card .aac-partner-family__card-inner,
	body.pmpro-checkout .aac-managed-card .aac-partner-family__dependents,
	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary,
	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo,
	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-row,
	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-row--total,
	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__pricing-note,
	body.pmpro-checkout .aac-managed-card .aac-checkout-autorenew,
	body.pmpro-checkout .aac-managed-card #pmpro_form_fieldset-donation #pmprodon_donation_input,
	body.pmpro-checkout .aac-managed-card .aac-order-summary,
	body.pmpro-checkout .aac-managed-card .aac-order-summary__row,
	body.pmpro-checkout .aac-managed-card .aac-order-summary__row--total {
		background: #fff !important;
		background-image: none !important;
	}

	body.pmpro-checkout .aac-managed-card #pmpro_pricing_fields {
		display: none !important;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_form_fieldset + .pmpro_form_fieldset,
	body.pmpro-checkout .aac-managed-card #pmpro_pricing_fields + .pmpro_form_fieldset {
		margin-top: 0.95rem;
		padding-top: 0.95rem;
		border-top: 1px solid rgba(0, 0, 0, 0.08);
	}

	body.pmpro-checkout .aac-managed-card .pmpro_form_heading,
	body.pmpro-checkout .aac-managed-card .pmpro_card_title,
	body.pmpro-checkout .aac-managed-card .pmpro_section_title {
		margin-top: 0;
		margin-bottom: 0.8rem;
		color: #0c0a09;
	}

	body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields .pmpro_form_legend {
		display: none;
	}

	body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields,
	body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields > .pmpro_card,
	body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields > .pmpro_card > .pmpro_card_content {
		background: #fff;
		border-radius: 0;
		box-shadow: none;
		color: #1c1917;
	}

	body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields > .pmpro_card > .pmpro_card_content,
	body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields .pmpro_card_fields,
	body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields .pmpro_payment-discount-code,
	body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields .pmpro_form_fields {
		gap: 0.45rem;
		background: #fff;
		border: 1px solid #111;
		border-radius: 0;
		box-shadow: none;
		color: #1c1917;
	}

	body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields .pmpro_payment-request-button,
	body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields .pmpro_payment-request-button .pmpro_form_heading {
		margin-top: 0;
		margin-bottom: 0.45rem;
	}

	body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields .pmpro_card_fields,
	body.pmpro-checkout .aac-managed-card #pmpro_payment_information_fields .pmpro_form_fields {
		border: 0 !important;
		box-shadow: none !important;
	}

	body.pmpro-checkout .aac-managed-card #pmpro_social_login {
		display: none !important;
	}

	body.pmpro-checkout .aac-managed-card #pmpro_user_fields {
		display: block !important;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_card_actions,
	body.pmpro-checkout .aac-managed-card .pmpro_form_submit {
		display: flex;
		justify-content: flex-start;
		align-items: center;
		width: 100%;
		margin-top: 0.9rem;
		padding-top: 0;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_message {
		padding: 0.95rem 1rem;
		border-radius: 0;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_form_fields.pmpro_cols-2 {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 0.85rem 1rem;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_cols-2 {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 0.85rem 1rem;
		width: 100%;
		align-items: start;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_form_field {
		margin: 0;
		min-width: 0;
		width: 100% !important;
		max-width: none !important;
		float: none !important;
		clear: none !important;
		display: flex;
		flex-direction: column;
		align-self: start;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_form_fields {
		gap: 0.85rem 1rem;
	}

	body.pmpro-checkout .aac-managed-card .aac-checkout-discount-detail-fields {
		display: grid;
		gap: 0.85rem 1rem;
		width: 100%;
	}

	body.pmpro-checkout .aac-managed-card .aac-contact-discount-detail-row {
		display: grid;
		grid-column: 1 / -1;
		grid-template-columns: minmax(12rem, 0.44fr) minmax(18rem, 1fr);
		gap: 0.85rem 1rem;
		width: 100%;
		align-items: start;
	}

	body.pmpro-checkout .aac-managed-card .aac-contact-discount-detail-row .pmpro_form_field {
		display: flex !important;
		flex-direction: column;
		align-self: start;
		width: 100% !important;
		margin: 0 !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-contact-discount-detail-row .pmpro_form_label {
		display: flex;
		align-items: flex-end;
		min-height: 1.4rem;
		margin: 0 0 0.45rem !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-contact-discount-detail-row input,
	body.pmpro-checkout .aac-managed-card .aac-contact-discount-detail-row select {
		min-height: 3.25rem;
		margin-top: 0 !important;
	}

	@media (max-width: 720px) {
		body.pmpro-checkout .aac-managed-card .aac-contact-discount-detail-row {
			grid-template-columns: 1fr;
		}
	}

	body.pmpro-checkout .aac-managed-card .aac-managed-two-up {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 0.85rem 1rem;
		width: 100%;
		align-items: start;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_cols-2 > * {
		min-width: 0;
		width: 100% !important;
		max-width: none !important;
		margin: 0 !important;
		float: none !important;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_cols-2::before,
	body.pmpro-checkout .aac-managed-card .pmpro_cols-2::after,
	body.pmpro-checkout .aac-managed-card .aac-managed-two-up::before,
	body.pmpro-checkout .aac-managed-card .aac-managed-two-up::after {
		display: none !important;
		content: none !important;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_form_label {
		display: block;
		width: 100%;
		margin: 0 0 0.45rem;
		color: #0c0a09;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_form_input,
	body.pmpro-checkout .aac-managed-card input[type="text"],
	body.pmpro-checkout .aac-managed-card input[type="email"],
	body.pmpro-checkout .aac-managed-card input[type="password"],
	body.pmpro-checkout .aac-managed-card input[type="tel"],
	body.pmpro-checkout .aac-managed-card input[type="date"],
	body.pmpro-checkout .aac-managed-card input[type="number"],
	body.pmpro-checkout .aac-managed-card select,
	body.pmpro-checkout .aac-managed-card textarea {
		min-height: 3rem;
		width: 100%;
		border-radius: 0;
		border: 1px solid #d6d3d1;
		background: #fff;
		box-shadow: none;
		padding: 0.85rem 0.95rem;
	}

	body.pmpro-checkout .aac-managed-card select,
	body.pmpro-checkout #t_shirt_div select,
	body.pmpro-checkout select[name="t_shirt"],
	body.pmpro-checkout select[id="t_shirt"],
	body.pmpro-checkout #service_component_div select,
	body.pmpro-checkout #military_service_component_div select,
	body.pmpro-checkout select[name="service_component"],
	body.pmpro-checkout select[name="military_service_component"],
	body.pmpro-checkout select[name="service_branch"] {
		appearance: none !important;
		-webkit-appearance: none !important;
		border: 1px solid #d6d3d1 !important;
		background-color: #ffffff !important;
		background-image:
			linear-gradient(45deg, transparent 50%, #16130f 50%),
			linear-gradient(135deg, #16130f 50%, transparent 50%),
			linear-gradient(to bottom, #d6d3d1, #d6d3d1) !important;
		background-position:
			calc(100% - 1.08rem) 50%,
			calc(100% - 0.82rem) 50%,
			calc(100% - 2.3rem) 50% !important;
		background-repeat: no-repeat !important;
		background-size: 0.34rem 0.34rem, 0.34rem 0.34rem, 1px 1.55rem !important;
		color: #16130f !important;
		padding-right: 3rem !important;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_form_field .select2-container {
		width: 100% !important;
	}

	body.pmpro-checkout .aac-managed-card .select2-container--default .select2-selection--single {
		min-height: 3rem;
		border-radius: 0;
		border: 1px solid #d6d3d1;
		background: #fff;
	}

	body.pmpro-checkout .aac-managed-card .select2-container--default .select2-selection--single .select2-selection__rendered {
		padding-left: 1rem;
		line-height: 3rem;
		color: #0c0a09;
	}

	body.pmpro-checkout .aac-managed-card .select2-container--default .select2-selection--single .select2-selection__arrow {
		height: 3rem;
		right: 0.65rem;
	}

	body.pmpro-checkout .aac-managed-card .aac-email-availability {
		margin: 0.45rem 0 0;
		font-size: 0.9rem;
		line-height: 1.45;
	}

	body.pmpro-checkout .aac-managed-card .aac-email-availability[data-state="available"] {
		color: #166534;
	}

	body.pmpro-checkout .aac-managed-card .aac-email-availability[data-state="available"]::before {
		content: "\2713";
		display: inline-grid;
		width: 1.15rem;
		height: 1.15rem;
		margin-right: 0.4rem;
		place-items: center;
		border-radius: 999px;
		background: #166534;
		color: #fff;
		font-size: 0.78rem;
		font-weight: 700;
		line-height: 1;
	}

	body.pmpro-checkout .aac-managed-card .aac-email-availability[data-state="unavailable"] {
		color: #8f1515;
	}

	body.pmpro-checkout .aac-managed-card .aac-email-availability[data-state="unavailable"]::before {
		content: "!";
		display: inline-grid;
		width: 1.15rem;
		height: 1.15rem;
		margin-right: 0.4rem;
		place-items: center;
		border-radius: 999px;
		background: #8f1515;
		color: #fff;
		font-size: 0.78rem;
		font-weight: 700;
		line-height: 1;
	}

	body.pmpro-checkout .aac-managed-card .aac-email-availability[data-state="checking"],
	body.pmpro-checkout .aac-managed-card .aac-email-availability[data-state="idle"] {
		color: #57534e;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__intro {
		margin: 0 0 0.2rem;
		color: #57534e;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__picker {
		display: grid;
		gap: 0.85rem;
		justify-items: center;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__none {
		display: inline-flex;
		align-items: center;
		gap: 0.55rem;
		width: fit-content;
		font-size: 0.95rem;
		font-weight: 700;
		color: #292524;
		cursor: pointer;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__none input {
		margin: 0;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__grid {
		display: grid;
		width: 100%;
		max-width: 48rem;
		grid-template-columns: repeat(3, minmax(0, 1fr));
		gap: 0.75rem;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__field {
		margin: 0;
		height: 100%;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label {
		display: block;
		height: 100%;
		margin: 0;
		cursor: pointer;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input {
		position: absolute;
		opacity: 0;
		pointer-events: none;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: flex-start;
		gap: 0.5rem;
		height: 100%;
		min-height: 6.25rem;
		padding: 1rem;
		border: 1px solid #9e1b1e;
		border-radius: 0;
		background: #fff;
		box-shadow: none;
		color: #16130f !important;
		text-align: center;
		transition: background-color 160ms ease, border-color 160ms ease, color 160ms ease, transform 160ms ease;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card *,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card .aac-membership-discounts__copy,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card .aac-membership-discounts__copy strong,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card svg {
		color: #16130f !important;
		-webkit-text-fill-color: #16130f !important;
		stroke: currentColor !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label:hover .aac-membership-discounts__card {
		transform: translateY(-2px);
		border-color: #9e1b1e;
		box-shadow: none;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:focus-visible + .aac-membership-discounts__card {
		outline: 2px solid rgba(143, 21, 21, 0.28);
		outline-offset: 3px;
	}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:checked + .aac-membership-discounts__card {
			border-color: #9e1b1e;
			box-shadow: none;
			background: #ffffff;
			color: #16130f !important;
		}

		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:checked + .aac-membership-discounts__card,
		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:checked + .aac-membership-discounts__card *,
		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:checked + .aac-membership-discounts__card .aac-membership-discounts__copy,
		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:checked + .aac-membership-discounts__card .aac-membership-discounts__copy strong,
		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:checked + .aac-membership-discounts__card .aac-membership-discounts__copy span,
		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:checked + .aac-membership-discounts__card .aac-membership-discounts__price,
		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:checked + .aac-membership-discounts__card svg {
			color: #16130f !important;
			-webkit-text-fill-color: #16130f !important;
			stroke: currentColor !important;
		}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__icon {
		display: inline-flex;
		align-items: center;
		justify-content: flex-start;
		width: auto;
		height: auto;
		border-radius: 0;
		background: transparent;
		color: currentColor;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__icon svg {
		width: 1.75rem;
		height: 1.75rem;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:checked + .aac-membership-discounts__card .aac-membership-discounts__icon {
		background: transparent;
		color: currentColor;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__body {
		display: flex;
		flex: 1 1 auto;
		flex-direction: column;
		justify-content: flex-start;
		gap: 0.32rem;
		width: 100%;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__copy {
		display: grid;
		gap: 0.32rem;
		color: currentColor;
		text-align: center;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__copy strong {
		color: currentColor;
		font-size: 0.86rem;
		line-height: 1.2;
		font-weight: 700;
		letter-spacing: 0.11em;
		text-transform: uppercase;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__copy span {
		color: #16130f !important;
		-webkit-text-fill-color: #16130f !important;
		font-size: 0.78rem;
		line-height: 1.35;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:checked + .aac-membership-discounts__card .aac-membership-discounts__copy span {
		color: #16130f !important;
		-webkit-text-fill-color: #16130f !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__footer {
		margin-top: 0.1rem;
		display: flex;
		justify-content: flex-start;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__price {
		display: inline-flex;
		align-items: center;
		gap: 0.4rem;
		width: fit-content;
		padding: 0;
		border-radius: 0;
		background: transparent;
		color: #16130f !important;
		-webkit-text-fill-color: #16130f !important;
		font-size: 0.78rem;
		font-weight: 500;
		text-transform: none;
		letter-spacing: 0;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:checked + .aac-membership-discounts__card .aac-membership-discounts__price {
		color: #16130f !important;
		-webkit-text-fill-color: #16130f !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label:has(.aac-membership-discounts__input:checked) .aac-membership-discounts__card {
		border-color: #9e1b1e !important;
		background: #ffffff !important;
		color: #16130f !important;
		-webkit-text-fill-color: #16130f !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label:has(.aac-membership-discounts__input:checked) .aac-membership-discounts__card,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label:has(.aac-membership-discounts__input:checked) .aac-membership-discounts__card *,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label:has(.aac-membership-discounts__input:checked) .aac-membership-discounts__icon,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label:has(.aac-membership-discounts__input:checked) .aac-membership-discounts__copy,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label:has(.aac-membership-discounts__input:checked) .aac-membership-discounts__copy strong,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label:has(.aac-membership-discounts__input:checked) .aac-membership-discounts__copy span,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label:has(.aac-membership-discounts__input:checked) .aac-membership-discounts__price,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label:has(.aac-membership-discounts__input:checked) svg {
		color: #16130f !important;
		-webkit-text-fill-color: #16130f !important;
		stroke: currentColor !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__intro {
		margin: 1rem 0 0;
		color: #57534e;
		line-height: 1.7;
		text-align: center;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__grid {
		display: grid;
		grid-template-columns: repeat(4, minmax(0, 1fr));
		justify-content: flex-start;
		gap: 1rem;
		margin-top: 1.5rem;
		align-items: stretch;
		width: 100%;
		max-width: 100%;
		margin-left: auto;
		margin-right: auto;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__card {
		display: grid;
		grid-template-rows: auto 1fr;
		height: 100%;
		max-width: none;
		padding: 0;
		border-radius: 0;
		overflow: hidden;
		border: 1px solid rgba(12, 10, 9, 0.1);
		box-shadow: none;
		background: #fff;
		color: #0c0a09;
		margin: 0 auto;
		width: 100%;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__art {
		display: flex;
		align-items: center;
		justify-content: flex-start;
		height: 14rem;
		min-height: 0;
		padding: 0.75rem;
		overflow: hidden;
		background: #ffffff;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__content {
		display: grid;
		gap: 0.75rem;
		padding: 0.85rem 0.85rem 0.95rem;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__cover-image {
		display: block;
		width: 100%;
		max-width: none;
		height: 100%;
		max-height: none;
		object-fit: contain;
		object-position: center;
		filter: none;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__title-block {
		display: grid;
		gap: 0.35rem;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__eyebrow {
		display: inline-block;
		font-size: 0.68rem;
		font-weight: 700;
		letter-spacing: 0.22em;
		text-transform: uppercase;
		color: #78716c;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__title {
		margin: 0;
		font-size: 1rem;
		line-height: 1.2;
		font-weight: 700;
		color: #0c0a09;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__description {
		margin: 0;
		color: #57534e;
		font-size: 0.92rem;
		line-height: 1.5;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__choices {
		position: relative;
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 0.85rem;
		isolation: isolate;
		margin-top: auto;
		padding: 0;
		border: 0;
		border-radius: 0;
		background: #ffffff;
		overflow: visible;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__choices::before {
		display: none;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__option {
		display: block;
		position: relative;
		z-index: 1;
		cursor: pointer;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__input {
		position: absolute;
		opacity: 0;
		pointer-events: none;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__choice {
		display: inline-flex;
		align-items: center;
		justify-content: flex-start;
		position: relative;
		z-index: 1;
		width: 100%;
		min-height: 2.05rem;
		padding: 0.48rem 0.2rem 0.42rem;
		border: 0;
		border-bottom: 4px solid transparent;
		border-radius: 0;
		background: #ffffff;
		color: #292524;
		font-weight: 800;
		cursor: pointer;
		letter-spacing: 0.08em;
		text-transform: uppercase;
		transition: border-color 160ms ease, color 160ms ease;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__option:hover .aac-member-preferences__choice {
		color: #8f1515;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__choice.is-active {
		border-bottom-color: #b71c1c;
		background: #ffffff;
		color: #16130f;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__input:checked + .aac-member-preferences__choice {
		border-bottom-color: #b71c1c;
		background: #ffffff;
		color: #16130f;
	}

	body.pmpro-checkout #aaj_preference_div,
	body.pmpro-checkout #anac_preference_div,
	body.pmpro-checkout #american_climbing_journal_preference_div,
	body.pmpro-checkout #guidebook_preferences_div,
	body.pmpro-checkout #publications_preference_div,
	body.pmpro-checkout #birthdate_div {
		display: none !important;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_form_field-password,
	body.pmpro-checkout .aac-managed-card .aac-password-input-wrap {
		position: relative;
	}

	/* Keep the public account row deterministic even before checkout JS runs. */
	body.pmpro-checkout .aac-managed-card #pmpro_user_fields > .pmpro_card > .pmpro_card_content > .pmpro_form_fields {
		display: grid !important;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 0.85rem 1rem;
	}

	body.pmpro-checkout .aac-managed-card #pmpro_user_fields > .pmpro_card > .pmpro_card_content > .pmpro_form_fields > .pmpro_cols-2 {
		display: contents !important;
	}

	body.pmpro-checkout .aac-managed-card #pmpro_user_fields .pmpro_form_field-username,
	body.pmpro-checkout .aac-managed-card #pmpro_user_fields .pmpro_form_field:has(#password2),
	body.pmpro-checkout .aac-managed-card #pmpro_user_fields .pmpro_form_field:has(#bconfirmemail) {
		display: none !important;
	}

	body.pmpro-checkout .aac-managed-card #pmpro_user_fields .pmpro_form_field:has(#bemail) {
		order: 1;
	}

	body.pmpro-checkout .aac-managed-card #pmpro_user_fields .pmpro_form_field:has(#password) {
		order: 2;
	}

	body.pmpro-checkout .aac-managed-card #pmpro_user_fields .pmpro_form_field:has(#bemail),
	body.pmpro-checkout .aac-managed-card #pmpro_user_fields .pmpro_form_field:has(#password),
	body.pmpro-checkout .aac-managed-card #pmpro_user_fields .aac-password-input-wrap {
		width: 100% !important;
		min-width: 0;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_form_field-password .pmpro_form_input-password,
	body.pmpro-checkout .aac-managed-card .aac-password-input-wrap input[type="password"],
	body.pmpro-checkout .aac-managed-card .aac-password-input-wrap input[type="text"] {
		padding-right: 7.75rem !important;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_form_field-password .pmpro_form_field-password-toggle,
	body.pmpro-checkout .aac-managed-card .aac-password-input-wrap > .aac-password-toggle {
		position: absolute;
		right: 0.75rem;
		top: 50%;
		bottom: auto;
		transform: translateY(-50%);
		margin: 0;
		z-index: 2;
	}

	/* PMPro 3.8 wraps the password input and reveal button together. */
	body.pmpro-checkout .aac-managed-card .pmpro_form_field-password .pmpro_form_field-password-toggle:has(input) {
		display: block;
		position: relative;
		right: auto;
		top: auto;
		bottom: auto;
		transform: none;
		width: 100%;
		margin: 0;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_form_field-password .pmpro_form_field-password-toggle:has(input) .pmpro_btn-password-toggle {
		position: absolute;
		right: 0.75rem;
		top: 50%;
		transform: translateY(-50%);
		z-index: 2;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_form_field-password .pmpro_btn-password-toggle,
	body.pmpro-checkout .aac-managed-card .aac-password-toggle {
		display: inline-flex !important;
		align-items: center !important;
		justify-content: center !important;
		min-height: 2rem !important;
		border: 1px solid rgba(183, 28, 28, 0.28) !important;
		background: #fffafa !important;
		box-shadow: none !important;
		padding: 0 0.75rem !important;
		color: #8f1515 !important;
		font-size: 0.68rem;
		font-weight: 800;
		letter-spacing: 0.12em;
		line-height: 1 !important;
		text-decoration: none !important;
		text-transform: uppercase;
	}

	body.pmpro-checkout .aac-managed-card .pmpro_form_field-password .pmpro_btn-password-toggle .pmpro_icon {
		display: none;
	}

	@media (max-width: 900px) {
		body.pmpro-checkout .aac-managed-card #pmpro_user_fields > .pmpro_card > .pmpro_card_content > .pmpro_form_fields {
			grid-template-columns: 1fr;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_form_fields.pmpro_cols-2 {
			grid-template-columns: 1fr;
		}

		body.pmpro-checkout .aac-managed-card .pmpro_cols-2 {
			grid-template-columns: 1fr;
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__grid {
			grid-template-columns: repeat(2, minmax(0, 1fr));
			width: min(94vw, 38rem);
		}
	}

	body.pmpro-checkout .aac-managed-card #pmpro_form_fieldset-magazine-addons .pmpro_form_fields {
		gap: 0.8rem;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__intro {
		margin: 0 0 0.2rem;
		color: #57534e;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__grid {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 1rem;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__field {
		margin: 0;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__label {
		display: block;
		margin: 0;
		cursor: pointer;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__input {
		position: absolute;
		opacity: 0;
		pointer-events: none;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__card {
		display: grid;
		grid-template-rows: auto 1fr;
		height: 100%;
		overflow: hidden;
		border: 1px solid rgba(12, 10, 9, 0.1);
		border-radius: 0;
		background: rgba(255, 255, 255, 0.9);
		box-shadow: none;
		transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__label:hover .aac-magazine-addons__card {
		transform: translateY(-2px);
		box-shadow: none;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__input:focus-visible + .aac-magazine-addons__card {
		outline: 2px solid rgba(143, 21, 21, 0.28);
		outline-offset: 3px;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__input:checked + .aac-magazine-addons__card {
		border-color: rgba(143, 21, 21, 0.52);
		box-shadow: none;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__cover {
		display: flex;
		align-items: center;
		justify-content: flex-start;
		min-height: 15.5rem;
		padding: 1rem 1rem 0.35rem;
		background: #ffffff;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__cover-image {
		display: block;
		width: auto;
		max-width: 100%;
		height: 13.75rem;
		max-height: 100%;
		object-fit: contain;
		object-position: center top;
		filter: drop-shadow(0 14px 24px rgba(12, 10, 9, 0.12));
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__body {
		display: grid;
		gap: 0.95rem;
		padding: 1rem 1rem 1.05rem;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__copy {
		display: grid;
		gap: 0.35rem;
		color: #57534e;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__copy strong {
		color: #0c0a09;
		font-size: 1rem;
		line-height: 1.2;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__footer {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 0.75rem;
		flex-wrap: wrap;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__price {
		font-weight: 700;
		color: #8f1515;
		white-space: nowrap;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__selector {
		display: inline-flex;
		align-items: center;
		gap: 0.55rem;
		padding: 0.55rem 0.8rem;
		border: 1px solid rgba(12, 10, 9, 0.14);
		border-radius: 999px;
		background: rgba(12, 10, 9, 0.03);
		color: #292524;
		font-size: 0.92rem;
		font-weight: 700;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__check {
		position: relative;
		display: inline-flex;
		align-items: center;
		justify-content: flex-start;
		width: 1.05rem;
		height: 1.05rem;
		border: 1.5px solid currentColor;
		border-radius: 0.3rem;
		background: #fff;
		color: inherit;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__check::after {
		content: '';
		width: 0.28rem;
		height: 0.58rem;
		border-right: 2px solid #fff;
		border-bottom: 2px solid #fff;
		transform: rotate(45deg) scale(0);
		transition: transform 160ms ease;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__input:checked + .aac-magazine-addons__card .aac-magazine-addons__selector {
		border-color: #8f1515;
		background: rgba(143, 21, 21, 0.1);
		color: #8f1515;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__input:checked + .aac-magazine-addons__card .aac-magazine-addons__check {
		background: #8f1515;
		color: #8f1515;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__input:checked + .aac-magazine-addons__card .aac-magazine-addons__check::after {
		transform: rotate(45deg) scale(1);
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__intro {
		margin: 0 0 1rem;
		color: #57534e;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__mode {
		display: flex;
		flex-wrap: wrap;
		gap: 0.75rem;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__mode-option {
		display: inline-flex;
		align-items: center;
		gap: 0.55rem;
		padding: 0.8rem 1rem;
		border: 1px solid rgba(12, 10, 9, 0.12);
		border-radius: 999px;
		background: rgba(255, 255, 255, 0.92);
		color: #292524;
		font-weight: 600;
		cursor: pointer;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__details {
		display: grid;
		gap: 1rem;
		margin-top: 1rem;
		justify-items: center;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__card {
		display: block;
		width: min(100%, 48rem);
		cursor: pointer;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__card input {
		position: absolute;
		opacity: 0;
		pointer-events: none;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__card-inner,
	body.pmpro-checkout .aac-managed-card .aac-partner-family__dependents {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 1rem;
		width: 100%;
		padding: 1rem;
		border: 1px solid #9e1b1e;
		border-radius: 0;
		background: #fff;
		color: #16130f !important;
		-webkit-text-fill-color: #16130f !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__card-inner,
	body.pmpro-checkout .aac-managed-card .aac-partner-family__card-inner *,
	body.pmpro-checkout .aac-managed-card .aac-partner-family__card-inner svg,
	body.pmpro-checkout .aac-managed-card .aac-partner-family__dependents,
	body.pmpro-checkout .aac-managed-card .aac-partner-family__dependents * {
		color: #16130f !important;
		-webkit-text-fill-color: #16130f !important;
		stroke: currentColor !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__card-copy {
		display: grid;
		gap: 0.25rem;
		color: #16130f !important;
		-webkit-text-fill-color: #16130f !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__card-copy strong {
		color: #16130f !important;
		-webkit-text-fill-color: #16130f !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__card-price {
		white-space: nowrap;
		font-weight: 700;
		color: #16130f !important;
		-webkit-text-fill-color: #16130f !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__card input:checked + .aac-partner-family__card-inner {
		border-color: #9e1b1e;
		background: #9e1b1e;
		color: #ffffff !important;
		-webkit-text-fill-color: #ffffff !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__card input:checked + .aac-partner-family__card-inner,
	body.pmpro-checkout .aac-managed-card .aac-partner-family__card input:checked + .aac-partner-family__card-inner *,
	body.pmpro-checkout .aac-managed-card .aac-partner-family__card input:checked + .aac-partner-family__card-inner .aac-partner-family__card-copy,
	body.pmpro-checkout .aac-managed-card .aac-partner-family__card input:checked + .aac-partner-family__card-inner .aac-partner-family__card-copy strong,
	body.pmpro-checkout .aac-managed-card .aac-partner-family__card input:checked + .aac-partner-family__card-inner .aac-partner-family__card-price,
	body.pmpro-checkout .aac-managed-card .aac-partner-family__card input:checked + .aac-partner-family__card-inner svg {
		color: #ffffff !important;
		-webkit-text-fill-color: #ffffff !important;
		stroke: currentColor !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__card:has(input:checked) .aac-partner-family__card-inner {
		border-color: #9e1b1e !important;
		background: #9e1b1e !important;
		color: #ffffff !important;
		-webkit-text-fill-color: #ffffff !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__card:has(input:checked) .aac-partner-family__card-inner,
	body.pmpro-checkout .aac-managed-card .aac-partner-family__card:has(input:checked) .aac-partner-family__card-inner *,
	body.pmpro-checkout .aac-managed-card .aac-partner-family__card:has(input:checked) .aac-partner-family__card-copy,
	body.pmpro-checkout .aac-managed-card .aac-partner-family__card:has(input:checked) .aac-partner-family__card-copy strong,
	body.pmpro-checkout .aac-managed-card .aac-partner-family__card:has(input:checked) .aac-partner-family__card-price,
	body.pmpro-checkout .aac-managed-card .aac-partner-family__card:has(input:checked) svg {
		color: #ffffff !important;
		-webkit-text-fill-color: #ffffff !important;
		stroke: currentColor !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__dependents {
		align-items: center;
		justify-content: flex-start;
		flex-direction: column;
		width: min(100%, 48rem);
		text-align: center;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__dependents .pmpro_form_label {
		margin: 0 0 0.45rem;
		font-size: 0.78rem;
		letter-spacing: 0.16em;
		text-transform: uppercase;
		color: #78716c;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__dependents select {
		min-width: 12rem;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__dependent-select {
		position: absolute;
		width: 1px !important;
		height: 1px !important;
		overflow: hidden;
		clip: rect(0 0 0 0);
		clip-path: inset(50%);
		white-space: nowrap;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__dependent-buttons {
		display: flex;
		flex-wrap: wrap;
		justify-content: flex-start;
		gap: 0.5rem;
		width: 100%;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__dependent-button {
		min-height: 2.75rem;
		min-width: 7.25rem;
		border: 1px solid #d7cfbf;
		border-radius: 0;
		background: #fff;
		color: #16130f;
		font-size: 0.86rem;
		font-weight: 700;
		letter-spacing: 0.08em;
		text-transform: uppercase;
		transition: background-color 160ms ease, border-color 160ms ease, color 160ms ease;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__dependent-button:hover {
		border-color: #9e1b1e;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__dependent-button[aria-pressed="true"] {
		border-color: #9e1b1e !important;
		background: #9e1b1e !important;
		color: #ffffff !important;
		-webkit-text-fill-color: #ffffff !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__dependents-note {
		margin: 0.4rem 0 0;
		font-size: 0.9rem;
		color: #57534e;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary {
		margin: 0;
		padding: 1rem 1.05rem;
		border: 1px solid rgba(12, 10, 9, 0.08);
		border-radius: 1rem;
		background: #ffffff;
		color: #292524;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-header {
		display: grid;
		gap: 0.2rem;
		margin-bottom: 0.8rem;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-title {
		margin: 0;
		font-size: 1rem;
		font-weight: 700;
		color: #0c0a09;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-caption {
		margin: 0;
		color: #57534e;
		font-size: 0.92rem;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo {
		margin: 0 0 0.85rem;
		padding: 1rem;
		border: 1px solid rgba(12, 10, 9, 0.08);
		border-radius: 1rem;
		background: rgba(255, 255, 255, 0.7);
		display: grid;
		gap: 0.75rem;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo-copy {
		display: grid;
		gap: 0.2rem;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo-label {
		margin: 0;
		font-size: 0.82rem;
		font-weight: 700;
		letter-spacing: 0.12em;
		text-transform: uppercase;
		color: #0c0a09;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo-copy p {
		margin: 0;
		font-size: 0.92rem;
		color: #57534e;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo-form {
		display: grid;
		grid-template-columns: minmax(0, 1fr) auto;
		gap: 0.7rem;
		align-items: center;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo-input {
		width: 100%;
		min-height: 48px;
		padding: 0.8rem 1rem;
		border-radius: 0;
		border: 1px solid rgba(12, 10, 9, 0.14);
		background: #fff;
		color: #0c0a09;
		font: inherit;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo-button {
		min-height: 48px;
		padding: 0.8rem 1.2rem;
		border: 0;
		border-radius: 0;
		background: #000;
		color: #fff;
		font: inherit;
		font-weight: 700;
		cursor: pointer;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo-button:hover {
		background: #171717;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo-applied {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 0.6rem;
		font-size: 0.92rem;
		color: #57534e;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo-clear {
		padding: 0;
		border: 0;
		background: transparent;
		color: #8f1515;
		font: inherit;
		font-weight: 700;
		cursor: pointer;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-rows {
		display: grid;
		gap: 0.5rem;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 1rem;
		padding: 0.75rem 0.85rem;
		border: 1px solid rgba(12, 10, 9, 0.12);
		border-radius: 0;
		background: #fff;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-row strong {
		color: #0c0a09;
		white-space: nowrap;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-row--discount {
		color: #8f1515;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-row--discount strong {
		color: #8f1515;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-row--total {
		border-color: rgba(143, 21, 21, 0.45);
		background: #fff;
		color: #6b1010;
		font-weight: 700;
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__summary-row--total strong {
		color: #8f1515;
	}

	@media (max-width: 720px) {
		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__promo-form {
			grid-template-columns: 1fr;
		}
	}

	body.pmpro-checkout .aac-managed-card .aac-magazine-addons__pricing-note {
		margin: 0;
		padding: 0.9rem 1rem;
		border: 1px solid rgba(143, 21, 21, 0.25);
		border-radius: 0;
		background: #fff;
		color: #6b1010;
		font-weight: 600;
	}

	body.pmpro-checkout .aac-managed-card #pmpro_autorenewal_checkbox .pmpro_form_fields {
		display: block;
	}

	body.pmpro-checkout .aac-managed-card .aac-checkout-autorenew {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 1rem;
		padding: 1rem 1.05rem;
		border: 1px solid rgba(12, 10, 9, 0.1);
		border-radius: 0;
		background: #fff;
		color: #1c1917;
	}

	body.pmpro-checkout .aac-managed-card .aac-checkout-autorenew__copy {
		display: grid;
		gap: 0.25rem;
	}

	body.pmpro-checkout .aac-managed-card .aac-checkout-autorenew__copy strong {
		color: #0c0a09;
	}

	body.pmpro-checkout .aac-managed-card .aac-checkout-autorenew__copy span {
		color: #57534e;
		font-size: 0.92rem;
		line-height: 1.45;
	}

	body.pmpro-checkout .aac-managed-card #pmpro_form_fieldset-donation .pmpro_form_fields-inline {
		display: grid;
		gap: 0.85rem;
	}

	body.pmpro-checkout .aac-managed-card #pmpro_form_fieldset-donation #donation_dropdown {
		display: none;
	}

	body.pmpro-checkout .aac-managed-card .aac-donation-picker {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(6.5rem, 1fr));
		gap: 0.6rem;
	}

	body.pmpro-checkout .aac-managed-card .aac-donation-option {
		display: inline-flex;
		align-items: center;
		justify-content: flex-start;
		min-height: 3rem;
		border: 1px solid rgba(143, 21, 21, 0.78);
		border-radius: 0;
		background: #b71c1c;
		color: #fff;
		font-size: 0.92rem;
		font-weight: 700;
		letter-spacing: 0.01em;
		text-transform: none;
		padding: 0 0.95rem;
		transition: background-color 160ms ease, border-color 160ms ease, color 160ms ease, transform 160ms ease;
	}

	body.pmpro-checkout .aac-managed-card .aac-donation-option:hover {
		transform: translateY(-1px);
		background: #8f1515;
		border-color: #8f1515;
	}

	body.pmpro-checkout .aac-managed-card .aac-donation-option[data-selected="true"] {
		border-color: #6f1010;
		background: #6f1010;
		color: #fff;
	}

	body.pmpro-checkout .aac-managed-card #pmpro_form_fieldset-donation #pmprodon_donation_input {
		display: none;
		align-items: center;
		gap: 0.55rem;
		margin-top: 0;
		padding: 0.85rem 0.95rem;
		border: 1px solid rgba(12, 10, 9, 0.1);
		border-radius: 1rem;
		background: rgba(255, 255, 255, 0.84);
	}

	body.pmpro-checkout .aac-managed-card #pmpro_form_fieldset-donation[data-aac-donation-mode="custom"] #pmprodon_donation_input {
		display: inline-flex;
	}

	body.pmpro-checkout .aac-managed-card #pmpro_form_fieldset-donation #pmprodon_donation_input input {
		width: 100%;
		max-width: 11rem;
		margin-top: 0;
	}

	body.pmpro-checkout .aac-managed-card .aac-donation-helper {
		margin: 0.35rem 0 0;
		color: #57534e;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label:has(.aac-membership-discounts__input:checked) .aac-membership-discounts__card,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:checked + .aac-membership-discounts__card {
		border-color: #d7cfbf !important;
		background: #ffffff !important;
		color: #16130f !important;
		-webkit-text-fill-color: #16130f !important;
		box-shadow: none !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label:hover .aac-membership-discounts__card {
		border-color: #9e1b1e !important;
		background: #ffffff !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label:has(.aac-membership-discounts__input:checked) .aac-membership-discounts__card,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:checked + .aac-membership-discounts__card {
		border-color: #9e1b1e !important;
		box-shadow: inset 0 -4px 0 #9e1b1e !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card *,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:checked + .aac-membership-discounts__card *,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label:has(.aac-membership-discounts__input:checked) .aac-membership-discounts__card * {
		color: #16130f !important;
		-webkit-text-fill-color: #16130f !important;
		stroke: currentColor !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:checked + .aac-membership-discounts__card .aac-membership-discounts__icon,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:checked + .aac-membership-discounts__card .aac-membership-discounts__icon *,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label:has(.aac-membership-discounts__input:checked) .aac-membership-discounts__icon,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label:has(.aac-membership-discounts__input:checked) .aac-membership-discounts__icon * {
		color: #9e1b1e !important;
		-webkit-text-fill-color: #9e1b1e !important;
		stroke: currentColor !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label.is-selected .aac-membership-discounts__card,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card.is-selected {
		border-color: #9e1b1e !important;
		background: #ffffff !important;
		color: #16130f !important;
		-webkit-text-fill-color: #16130f !important;
		box-shadow: inset 0 -4px 0 #9e1b1e !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label.is-selected .aac-membership-discounts__card,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label.is-selected .aac-membership-discounts__card *,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card.is-selected,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card.is-selected *,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label.is-selected .aac-membership-discounts__copy,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label.is-selected .aac-membership-discounts__copy strong,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label.is-selected .aac-membership-discounts__copy span,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label.is-selected .aac-membership-discounts__price {
		color: #16130f !important;
		-webkit-text-fill-color: #16130f !important;
		stroke: currentColor !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label.is-selected .aac-membership-discounts__icon,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label.is-selected .aac-membership-discounts__icon *,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card.is-selected .aac-membership-discounts__icon,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card.is-selected .aac-membership-discounts__icon * {
		color: #9e1b1e !important;
		-webkit-text-fill-color: #9e1b1e !important;
		stroke: currentColor !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card {
		position: relative !important;
		min-height: 5.65rem !important;
		padding: 0.9rem 3rem 1.55rem 0.95rem !important;
		border-color: #d7cfbf !important;
		background: #fbfaf8 !important;
		text-align: left !important;
		transform: none !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card::before {
		content: "";
		position: absolute;
		top: 0.85rem;
		right: 0.9rem;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 1.05rem;
		height: 1.05rem;
		border: 2px solid #b7ad9c;
		background: #ffffff;
		color: #ffffff;
		font-size: 0.78rem;
		font-weight: 900;
		line-height: 1;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card::after {
		content: "Select";
		position: absolute;
		right: 0.9rem;
		bottom: 0.62rem;
		color: #8f877a;
		font-size: 0.62rem;
		font-weight: 900;
		letter-spacing: 0.14em;
		line-height: 1;
		text-transform: uppercase;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label:hover .aac-membership-discounts__card {
		background: #ffffff !important;
		box-shadow: inset 0 -3px 0 rgba(158, 27, 30, 0.35) !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:checked + .aac-membership-discounts__card,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label:has(.aac-membership-discounts__input:checked) .aac-membership-discounts__card,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label.is-selected .aac-membership-discounts__card,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card.is-selected {
		border-color: #9e1b1e !important;
		background: #ffffff !important;
		box-shadow: inset 0 -4px 0 #9e1b1e !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:checked + .aac-membership-discounts__card::before,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label:has(.aac-membership-discounts__input:checked) .aac-membership-discounts__card::before,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label.is-selected .aac-membership-discounts__card::before,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card.is-selected::before {
		content: "✓";
		border-color: #9e1b1e;
		background: #9e1b1e;
		color: #ffffff;
		-webkit-text-fill-color: #ffffff;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__input:checked + .aac-membership-discounts__card::after,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label:has(.aac-membership-discounts__input:checked) .aac-membership-discounts__card::after,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__label.is-selected .aac-membership-discounts__card::after,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card.is-selected::after {
		content: "Selected";
		color: #9e1b1e;
		-webkit-text-fill-color: #9e1b1e;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card .aac-membership-discounts__icon svg {
		width: 1.35rem;
		height: 1.35rem;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card .aac-membership-discounts__body,
	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card .aac-membership-discounts__copy {
		align-items: flex-start;
		text-align: left;
	}

	body.pmpro-checkout .aac-managed-card .aac-membership-discounts__card .aac-membership-discounts__copy strong {
		font-size: 0.8rem;
	}

	body.pmpro-checkout .aac-managed-card .aac-donation-option {
		border-color: #d7cfbf !important;
		background: #ffffff !important;
		color: #16130f !important;
		-webkit-text-fill-color: #16130f !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-donation-option:hover,
	body.pmpro-checkout .aac-managed-card .aac-donation-option[data-selected="true"] {
		border-color: #9e1b1e !important;
		background: #ffffff !important;
		color: #16130f !important;
		-webkit-text-fill-color: #16130f !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-donation-option[data-selected="true"] {
		box-shadow: inset 0 -4px 0 #9e1b1e !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__choices::before {
		display: none !important;
		background: transparent !important;
		box-shadow: none !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-member-preferences__option:hover .aac-member-preferences__choice {
		color: #16130f !important;
	}

	body.pmpro-checkout .aac-managed-card .aac-partner-family__card input:checked + .aac-partner-family__card-inner,
	body.pmpro-checkout .aac-managed-card .aac-partner-family__card:has(input:checked) .aac-partner-family__card-inner,
	body.pmpro-checkout .aac-managed-card .aac-partner-family__dependent-button[aria-pressed="true"] {
		border-color: #16130f !important;
		background: #16130f !important;
		color: #ffffff !important;
		-webkit-text-fill-color: #ffffff !important;
	}

	.aac-managed-card .aac-order-summary {
		margin: 0 0 1.25rem;
		padding: 1.1rem 1.2rem;
		border: 1px solid rgba(12, 10, 9, 0.1);
		border-radius: 0;
		background: #fff;
	}

	.aac-managed-card .aac-order-summary__header {
		display: grid;
		gap: 0.25rem;
		margin-bottom: 0.9rem;
	}

	.aac-managed-card .aac-order-summary__header h2 {
		margin: 0;
		font-size: 1.05rem;
		color: #0c0a09;
	}

	.aac-managed-card .aac-order-summary__header p,
	.aac-managed-card .aac-order-summary__meta {
		margin: 0;
		color: #57534e;
	}

	.aac-managed-card .aac-order-summary__rows {
		display: grid;
		gap: 0.5rem;
	}

	.aac-managed-card .aac-order-summary__row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 1rem;
		padding: 0.75rem 0.9rem;
		border: 1px solid rgba(12, 10, 9, 0.1);
		border-radius: 0;
		background: #fff;
		color: #1c1917;
	}

	.aac-managed-card .aac-order-summary__row strong {
		color: #0c0a09;
		white-space: nowrap;
	}

	.aac-managed-card .aac-order-summary__row--total {
		border-color: rgba(183, 28, 28, 0.82);
		background: #fff;
		color: #8f1515;
		font-weight: 700;
	}

	.aac-managed-card .aac-order-summary__row--total strong {
		color: #ef4444;
	}

	.aac-managed-card .aac-order-summary__meta {
		margin-top: 0.8rem;
		font-size: 0.92rem;
	}

	@media (max-width: 760px) {
		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__grid {
			grid-template-columns: minmax(0, 1fr);
		}

		body.pmpro-checkout .aac-managed-card .aac-magazine-addons__grid {
			grid-template-columns: minmax(0, 1fr);
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__grid {
			grid-template-columns: minmax(0, 1fr);
		}
	}

	@media (max-width: 1100px) {
		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__grid {
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__grid {
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}
	}

	@media (max-width: 760px) {
		body.pmpro-checkout .aac-managed-card .aac-membership-discounts__grid {
			grid-template-columns: minmax(0, 1fr);
		}

		body.pmpro-checkout .aac-managed-card .aac-partner-family__dependent-button {
			width: 100%;
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__grid {
			grid-template-columns: minmax(0, 1fr);
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__choices {
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 0.6rem;
		}

		body.pmpro-checkout .aac-managed-card .aac-member-preferences__choice {
			min-height: 2.05rem;
			min-width: 0;
			padding: 0.48rem 0.2rem 0.42rem;
			font-size: 0.82rem;
			line-height: 1.15;
		}
	}

	.aac-managed-card a {
		color: #8f1515;
	}

	.aac-managed-card a:hover {
		color: #6b1010;
	}

	.aac-managed-account-summary {
		display: grid;
		gap: 1rem;
		margin-bottom: 1.5rem;
		padding: 1.35rem 0;
		border: 0;
		border-bottom: 2px solid #b71c1c;
		border-radius: 0;
		background: #ffffff;
		box-shadow: none;
	}

	.aac-managed-account-summary__grid {
		display: grid;
		gap: 0.9rem;
		grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
	}

	.aac-managed-account-summary__item {
		padding: 1rem 1.1rem;
		border: 0;
		border-top: 1px solid #e7e5e4;
		border-radius: 0;
		background: #ffffff;
	}

	.aac-managed-account-summary__label {
		display: block;
		margin-bottom: 0.35rem;
		color: #57534e;
		font-size: 0.68rem;
		font-weight: 700;
		letter-spacing: 0.18em;
		text-transform: uppercase;
	}

	.aac-managed-account-summary__value {
		color: #0c0a09;
		font-size: 1.05rem;
		font-weight: 700;
		line-height: 1.35;
	}

	.aac-managed-account-summary__notice {
		padding: 0.95rem 1.1rem;
		border-left: 4px solid #9e1b1e;
		background: #fff7ed;
		color: #3a2b14;
		font-size: 0.92rem;
		font-weight: 650;
		line-height: 1.55;
	}

	.aac-managed-account-summary__toggle {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		justify-content: space-between;
		gap: 1rem;
		padding: 1rem 1.1rem;
		border: 1px solid #e7e5e4;
		border-radius: 0;
		background: #ffffff;
	}

	.aac-managed-account-summary__toggle-copy strong {
		display: block;
		margin-bottom: 0.25rem;
		color: #0c0a09;
		font-size: 0.98rem;
	}

	.aac-managed-account-summary__toggle-copy span {
		color: #57534e;
		font-size: 0.9rem;
		line-height: 1.55;
	}

	.aac-managed-toggle {
		display: inline-flex;
		align-items: center;
		gap: 0.75rem;
		cursor: pointer;
	}

	.aac-managed-toggle input {
		position: absolute;
		opacity: 0;
		pointer-events: none;
	}

	.aac-managed-toggle__track {
		position: relative;
		width: 3.35rem;
		height: 2rem;
		border-radius: 999px;
		background: rgba(12, 10, 9, 0.18);
		transition: background-color 0.2s ease;
	}

	.aac-managed-toggle__track::after {
		content: '';
		position: absolute;
		top: 0.2rem;
		left: 0.2rem;
		width: 1.6rem;
		height: 1.6rem;
		border-radius: 50%;
		background: #fff;
		box-shadow: 0 6px 14px rgba(0, 0, 0, 0.18);
		transition: transform 0.2s ease;
	}

	.aac-managed-toggle input:checked + .aac-managed-toggle__track {
		background: #8f1515;
	}

	.aac-managed-toggle input:checked + .aac-managed-toggle__track::after {
		transform: translateX(1.35rem);
	}

	.aac-managed-toggle__state {
		color: #0c0a09;
		font-size: 0.82rem;
		font-weight: 700;
		letter-spacing: 0.12em;
		text-transform: uppercase;
	}

	.aac-managed-card input[type="text"],
	.aac-managed-card input[type="email"],
	.aac-managed-card input[type="password"],
	.aac-managed-card input[type="tel"],
	.aac-managed-card input[type="number"],
	.aac-managed-card select,
	.aac-managed-card textarea {
		width: 100%;
		margin-top: 0.35rem;
		border: 1px solid #d6d3d1;
		border-radius: 0;
		background: #fff;
		color: #0c0a09;
		padding: 0.8rem 0.95rem;
		box-sizing: border-box;
	}

	.aac-managed-card input[type="submit"],
	.aac-managed-card button,
	.aac-managed-card .pmpro_btn,
	.aac-managed-card .button {
		display: inline-flex;
		align-items: center;
		justify-content: flex-start;
		min-height: 2.85rem;
		border: 0;
		border-radius: 0;
		background: #b71c1c;
		color: #fff;
		font-weight: 700;
		letter-spacing: 0.08em;
		text-transform: uppercase;
		padding: 0 1.2rem;
		cursor: pointer;
	}

	.aac-managed-card input[type="submit"]:hover,
	.aac-managed-card button:hover,
	.aac-managed-card .pmpro_btn:hover,
	.aac-managed-card .button:hover {
		background: #8f1515;
		color: #fff;
	}

	.aac-managed-card .pmpro_message:last-child,
	.aac-managed-card .pmpro_form_submit:last-child,
	.aac-managed-card form.pmpro_form > .pmpro_form_submit:last-child {
		margin-bottom: 0;
	}

	.aac-managed-card .pmpro_form_submit {
		padding-bottom: 0;
	}

	body.pmpro-checkout .aac-managed-card.aac-managed-card--embed,
	body.pmpro-checkout .aac-managed-card:not(.aac-managed-card--embed) {
		padding-top: clamp(1.5rem, 3vw, 2.5rem) !important;
	}

	body[data-aac-checkout-wizard="true"] .aac-managed-card form.pmpro_form {
		display: block;
	}

	body[data-aac-checkout-wizard="true"] .aac-checkout-wizard {
		display: grid;
		gap: 1.4rem;
		width: 100%;
	}

	body[data-aac-checkout-wizard="true"] .aac-checkout-wizard__steps {
		display: grid;
		grid-template-columns: repeat(4, minmax(0, 1fr));
		gap: 0.85rem;
		margin: 0 0 1.25rem;
	}

	body.aac-member-portal-embed[data-aac-checkout-wizard="true"] .aac-checkout-wizard__steps {
		display: none !important;
	}

	body:not(.aac-member-portal-embed)[data-aac-checkout-wizard="true"] .aac-checkout-wizard__steps {
		display: grid;
	}

	body[data-aac-checkout-wizard="true"] .aac-checkout-wizard__step {
		display: flex;
		align-items: center;
		gap: 0.8rem;
		min-width: 0;
		padding: 0.8rem 0.9rem;
		border: 1px solid #ddd5c6;
		border-radius: 0;
		background: #fff;
		color: #6e675d;
		font-size: 0.88rem;
		font-weight: 800;
		text-align: left;
		cursor: pointer;
	}

	body[data-aac-checkout-wizard="true"] .aac-checkout-wizard__step[aria-current="step"] {
		border-color: #9e1b1e;
		background: #fbf1ef;
		color: #16130f;
	}

	body[data-aac-checkout-wizard="true"] .aac-checkout-wizard__step-mark {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 1.65rem;
		height: 1.65rem;
		flex: 0 0 auto;
		border-radius: 999px;
		background: #e8e0d3;
		color: #16130f;
		font-size: 0.76rem;
		font-weight: 900;
		line-height: 1;
		text-align: center;
	}

	body[data-aac-checkout-wizard="true"] .aac-checkout-wizard__step[data-complete="true"] .aac-checkout-wizard__step-mark,
	body[data-aac-checkout-wizard="true"] .aac-checkout-wizard__step[aria-current="step"] .aac-checkout-wizard__step-mark {
		background: #9e1b1e;
		color: #fff;
	}

	body[data-aac-checkout-wizard="true"] .aac-checkout-wizard__step-label {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		margin-left: 0.15rem;
	}

	body[data-aac-checkout-wizard="true"] .aac-checkout-wizard__progress {
		display: none;
		height: 0.45rem;
		overflow: hidden;
		border-radius: 999px;
		background: #e8e0d3;
	}

	body:not(.aac-member-portal-embed)[data-aac-checkout-wizard="true"] .aac-checkout-wizard__progress {
		display: none !important;
	}

		body[data-aac-checkout-wizard="true"] .aac-checkout-wizard__progress-fill {
			height: 100%;
			width: 25%;
			border-radius: inherit;
			background: #9e1b1e;
			transition: width 180ms ease;
		}

	body[data-aac-checkout-wizard="true"] .aac-checkout-wizard__notice {
		margin: 0 0 0.2rem;
	}

	body[data-aac-checkout-wizard="true"] .aac-checkout-wizard__processing {
		align-items: center;
		background: #fff7df;
		border: 1px solid rgba(248, 194, 53, 0.75);
		color: #3d2f08;
		display: flex;
		font-size: 0.9rem;
		font-weight: 700;
		gap: 0.65rem;
		letter-spacing: 0.04em;
		margin: 0.35rem 0 0;
		padding: 0.85rem 1rem;
		text-transform: uppercase;
	}

	body[data-aac-checkout-wizard="true"] .aac-checkout-wizard__processing[hidden] {
		display: none !important;
	}

		body[data-aac-checkout-wizard="true"] .aac-checkout-wizard__panels {
			min-width: 0;
		}

	body[data-aac-checkout-wizard="true"] .aac-checkout-wizard__panel {
		display: grid;
		gap: 1.1rem;
	}

	body[data-aac-checkout-wizard="true"] .aac-checkout-wizard__panel[hidden] {
		display: none !important;
	}

	body[data-aac-checkout-wizard="true"] .aac-checkout-wizard [hidden] {
		display: none !important;
	}

	body[data-aac-checkout-wizard="true"] .aac-checkout-wizard__nav {
		display: flex;
		align-items: center;
		gap: 0.85rem;
		margin-top: 1.35rem;
	}

	body[data-aac-checkout-wizard="true"] .aac-checkout-wizard__nav .aac-checkout-wizard__back {
		background: transparent !important;
		border: 1px solid #d7cfbf !important;
		color: #16130f !important;
		box-shadow: none !important;
	}

	body[data-aac-checkout-wizard="true"] .aac-checkout-wizard__hint {
		color: #8f877a;
		font-size: 0.86rem;
	}

	body[data-aac-checkout-wizard="true"] .aac-checkout-wizard .pmpro_form_submit {
		justify-content: flex-start;
		margin-top: 1rem;
	}

	@media (max-width: 760px) {
		body[data-aac-checkout-wizard="true"] .aac-checkout-wizard__steps {
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 0.8rem;
		}

		body[data-aac-checkout-wizard="true"] .aac-checkout-wizard__step {
			gap: 0.7rem;
			padding: 0.72rem 0.75rem;
			font-size: 0.8rem;
		}

		body[data-aac-checkout-wizard="true"] .aac-checkout-wizard__nav {
			align-items: stretch;
			flex-direction: column;
		}

		body[data-aac-checkout-wizard="true"] .aac-checkout-wizard__nav button,
		body[data-aac-checkout-wizard="true"] .aac-checkout-wizard .pmpro_form_submit input,
		body[data-aac-checkout-wizard="true"] .aac-checkout-wizard .pmpro_form_submit button {
			width: 100%;
		}
	}

	@media (max-width: 960px) {
		.aac-managed-header__bar {
			display: flex;
			flex-direction: column;
			align-items: stretch;
		}

		.aac-managed-logo,
		.aac-managed-actions {
			padding: 1rem 1rem 0;
			border: 0;
		}

		.aac-managed-topnav {
			flex-wrap: wrap;
			padding: 0.75rem 1rem 1rem;
		}

		.aac-managed-layout {
			display: block;
		}

		.aac-managed-sidebar {
			position: static;
			width: auto;
			height: auto;
			overflow-x: auto;
			overflow-y: hidden;
			border-right: 0;
			border-bottom: 1px solid rgba(0, 0, 0, 0.08);
			padding: 1rem;
		}

		.aac-managed-sidebar__section-title {
			opacity: 1;
			max-height: none;
			transform: none;
			margin-bottom: 0.55rem;
		}

		.aac-managed-sidebar a {
			justify-content: center;
			min-width: 9.25rem;
			padding: 0.75rem 1rem;
		}

		.aac-managed-sidebar__label {
			position: static;
			min-height: 0;
			padding: 0;
			border: 0;
			background: transparent;
			box-shadow: none;
			opacity: 1;
			pointer-events: auto;
			transform: none;
		}
	}
</style>

<?php if (!empty($is_checkout_page) && $is_embed_request) : ?>
	<section class="aac-managed-card aac-managed-card--embed">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</section>
	<?php return; ?>
<?php endif; ?>

<div class="aac-managed-shell">
	<div class="aac-managed-layout">
		<aside class="aac-managed-sidebar" aria-label="Member portal navigation">
			<?php foreach ($portal_sections as $section) : ?>
				<section class="aac-managed-sidebar__section">
					<p class="aac-managed-sidebar__section-title"><?php echo esc_html($section['title']); ?></p>
					<ul>
						<?php foreach ($section['items'] as $item) : ?>
							<li>
								<a href="<?php echo esc_url($item['href']); ?>"<?php echo !empty($item['active']) ? ' aria-current="page"' : ''; ?>>
									<span class="aac-managed-sidebar__icon" aria-hidden="true"><?php echo aac_member_portal_sidebar_icon_svg($item['icon'] ?? 'user'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<span class="aac-managed-sidebar__label"><?php echo esc_html($item['label']); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endforeach; ?>
		</aside>

		<main class="aac-managed-main">
			<div class="aac-managed-main__inner">
					<section class="aac-managed-hero">
						<p class="aac-managed-hero__kicker"><?php echo esc_html($page_kicker); ?></p>
						<h1><?php echo esc_html($page_title); ?></h1>
						<p><?php echo esc_html($page_description); ?></p>
						<div class="aac-managed-actions-row">
							<a class="aac-managed-pill <?php echo (!empty($is_account_page) || !empty($is_billing_page) || !empty($is_orders_page)) ? 'aac-managed-pill--primary' : 'aac-managed-pill--ghost'; ?>" href="<?php echo esc_url($managed_account_url); ?>"><?php echo aac_member_portal_sidebar_icon_svg('user'); ?> <span>Account</span></a>
							<?php if ($current_can_cancel_membership) : ?>
								<a class="aac-managed-pill <?php echo $is_cancel_page ? 'aac-managed-pill--primary' : 'aac-managed-pill--ghost'; ?>" href="<?php echo esc_url($current_membership_actions['cancel_url']); ?>"><?php echo aac_member_portal_sidebar_icon_svg('x-circle'); ?> <span>Cancel</span></a>
							<?php endif; ?>
							<a class="aac-managed-pill <?php echo $is_confirmation_page ? 'aac-managed-pill--primary' : 'aac-managed-pill--ghost'; ?>" href="<?php echo esc_url($confirmation_url); ?>"><?php echo aac_member_portal_sidebar_icon_svg('file-text'); ?> <span>Confirmation</span></a>
						</div>
					</section>

				<?php if (!empty($is_account_page) && $current_member_id > 0 && $current_primary_membership) : ?>
					<section class="aac-managed-account-summary">
						<div class="aac-managed-account-summary__grid">
							<div class="aac-managed-account-summary__item">
								<span class="aac-managed-account-summary__label">Membership Level</span>
								<span class="aac-managed-account-summary__value"><?php echo esc_html($current_primary_membership['tier'] ?: 'Free'); ?></span>
							</div>
							<div class="aac-managed-account-summary__item">
								<span class="aac-managed-account-summary__label">Renewal Date</span>
								<span class="aac-managed-account-summary__value">
									<?php
									echo esc_html(
										$current_auto_renew && !empty($current_renewal_date)
											? date_i18n(get_option('date_format'), strtotime($current_renewal_date))
											: 'Not scheduled'
									);
									?>
								</span>
							</div>
							<div class="aac-managed-account-summary__item">
								<span class="aac-managed-account-summary__label">Expiration Date</span>
								<span class="aac-managed-account-summary__value">
									<?php
									echo esc_html(
										!$current_auto_renew && !empty($current_expiration_date)
											? date_i18n(get_option('date_format'), strtotime($current_expiration_date))
											: 'Not scheduled'
									);
									?>
								</span>
							</div>
						</div>
						<?php if ($current_pending_downgrade) : ?>
							<div class="aac-managed-account-summary__notice">
								<?php
								$pending_effective_date = trim((string) ($current_pending_downgrade['effective_date'] ?? ''));
								$pending_effective_label = $pending_effective_date && strtotime($pending_effective_date)
									? date_i18n(get_option('date_format'), strtotime($pending_effective_date))
									: 'the end of your current term';
								printf(
									'Your downgrade to %1$s is scheduled for %2$s. Your current membership remains active until then.',
									esc_html($current_pending_downgrade['target_tier'] ?? 'the selected level'),
									esc_html($pending_effective_label)
								);
								?>
							</div>
						<?php endif; ?>
						<?php if (!$current_auto_renew && !empty($current_expiration_date)) : ?>
							<div class="aac-managed-account-summary__notice">
								<?php
								printf(
									'Automatic renewal is off. No cancellation is needed; your membership remains active through %s.',
									esc_html(date_i18n(get_option('date_format'), strtotime($current_expiration_date)))
								);
								?>
							</div>
						<?php endif; ?>
					</section>
				<?php endif; ?>

				<section class="aac-managed-card">
					<?php
					$managed_card_content = (string) $content;
					if ($portal_plugin instanceof AAC_Member_Portal_Plugin) {
						$managed_card_content = $portal_plugin->render_managed_pmpro_content(
							$managed_card_content,
							[
								'is_account_page' => !empty($is_account_page),
								'is_billing_page' => !empty($is_billing_page),
								'is_orders_page' => !empty($is_orders_page),
								'is_cancel_page' => !empty($is_cancel_page),
								'is_confirmation_page' => !empty($is_confirmation_page),
								'user_id' => $current_member_id,
								'primary_membership' => $current_primary_membership,
								'membership_actions' => $current_membership_actions,
							]
						);
					}
					echo $managed_card_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</section>
			</div>
		</main>
	</div>
</div>
<script>
	(function () {
		const buildPreferredLoggedInName = () => {
			const nameCandidates = [];
			const currentUserFirstName = String(window.AAC_CURRENT_USER_FIRST_NAME || '').trim();
			const currentUserLastName = String(window.AAC_CURRENT_USER_LAST_NAME || '').trim();
			const runtimeFullName = [currentUserFirstName, currentUserLastName].filter(Boolean).join(' ').trim();
			if (runtimeFullName) {
				nameCandidates.push(runtimeFullName);
			}

			const checkoutFirstName = String(document.querySelector('input[name="bfirstname"]')?.value || '').trim();
			const checkoutLastName = String(document.querySelector('input[name="blastname"]')?.value || '').trim();
			const checkoutFullName = [checkoutFirstName, checkoutLastName].filter(Boolean).join(' ').trim();
			if (checkoutFullName) {
				nameCandidates.push(checkoutFullName);
			}

			const accountName = String(document.querySelector('input[name="name"]')?.value || '').trim();
			if (accountName) {
				nameCandidates.push(accountName);
			}

			if (currentUserDisplayName) {
				nameCandidates.push(currentUserDisplayName);
			}

			return nameCandidates.find((candidate) => candidate && candidate.includes(' ')) || nameCandidates.find(Boolean) || '';
		};

		const currentUserEmail = <?php echo wp_json_encode($is_logged_in ? wp_get_current_user()->user_email : ''); ?>;
		const currentUserDisplayName = <?php
			if ($is_logged_in) {
				$current_user = wp_get_current_user();
				$account_info = get_user_meta($current_user->ID, 'aac_account_info', true);
				$account_first_name = is_array($account_info) ? trim((string) ($account_info['first_name'] ?? '')) : '';
				$account_last_name = is_array($account_info) ? trim((string) ($account_info['last_name'] ?? '')) : '';
				$account_name = is_array($account_info) ? trim((string) ($account_info['name'] ?? '')) : '';
				$display_name = trim($account_first_name . ' ' . $account_last_name);
				if ($display_name === '' && $account_name !== '') {
					$display_name = $account_name;
				}
				if ($display_name === '') {
					$display_name = trim(($current_user->first_name ?? '') . ' ' . ($current_user->last_name ?? ''));
				}
				if ($display_name === '') {
					$display_name = $current_user->display_name ?: $current_user->user_email;
				}
				echo wp_json_encode($display_name);
			} else {
				echo wp_json_encode('');
			}
		?>;
		const emailAvailabilityEndpoint = new URL('/wp-json/aac/v1/email-availability', window.location.origin).toString();

		const buildUsernameFromEmail = (value) => {
			const normalized = String(value || '')
				.trim()
				.toLowerCase()
				.replace(/[@.+-]+/g, '_')
				.replace(/[^a-z0-9_]+/g, '_')
				.replace(/^_+|_+$/g, '');

			return normalized || 'aac_member';
		};

const formatUsd = (value) => new Intl.NumberFormat('en-US', {
	style: 'currency',
	currency: 'USD',
	minimumFractionDigits: 2,
	maximumFractionDigits: 2,
}).format(Number.isFinite(value) ? value : 0);
const checkoutProfileDefaults = <?php echo wp_json_encode($checkout_profile_defaults); ?>;
const configuredTshirtSizeOptions = <?php echo wp_json_encode($checkout_tshirt_size_options); ?>;
const fallbackTshirtSizeOptions = [
	{ value: 'No T-shirt', label: 'No T-shirt' },
	{ value: 'Unisex Small', label: 'Unisex Small' },
	{ value: 'Unisex Medium', label: 'Unisex Medium' },
	{ value: 'Unisex Large', label: 'Unisex Large' },
	{ value: 'Unisex X-Large', label: 'Unisex X-Large' },
	{ value: 'Unisex XX-Large', label: 'Unisex XX-Large' },
];
const getConfiguredTshirtSizeOptions = () => {
	const source = Array.isArray(configuredTshirtSizeOptions) && configuredTshirtSizeOptions.length
		? configuredTshirtSizeOptions
		: fallbackTshirtSizeOptions;
	const seen = new Set();
	return source
		.map((option) => {
			const value = String(option?.value || option?.label || '').trim();
			const label = String(option?.label || option?.value || '').trim();
			return value && label ? { value, label } : null;
		})
		.filter((option) => {
			if (!option || seen.has(option.value)) {
				return false;
			}
			seen.add(option.value);
			return true;
		});
};
const publicationCardImages = <?php echo wp_json_encode($portal_design_settings['publication_tile_images'] ?? []); ?>;
const studentUniversitySearchEndpoint = new URL('/wp-json/aac/v1/universities', window.location.origin).toString();
const defaultPublicationCardImages = {
	aaj: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-95.jpeg',
	anac: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-28.jpeg',
	acj: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-4.jpg',
	guidebook: 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-2.jpg',
};

	const escapeHtml = (value) => String(value ?? '')
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#39;');

	const parseCurrencyValue = (value) => {
		const match = String(value || '').match(/\$([\d,]+(?:\.\d{2})?)/);
		if (!match) {
			return null;
		}

		const parsed = Number.parseFloat(match[1].replace(/,/g, ''));
		return Number.isFinite(parsed) ? parsed : null;
	};

	const getNativeDiscountCodeInputs = () => Array.from(document.querySelectorAll('#pmpro_discount_code, #pmpro_other_discount_code'));

	const getNativeDiscountCodeButton = () =>
		document.getElementById('discount_code_button')
		|| document.getElementById('other_discount_code_button');

	const getPmproCheckoutForm = () =>
		document.getElementById('pmpro_form') || document.querySelector('form.pmpro_form');

	const ensureCheckoutHiddenInput = (form, name) => {
		if (!form || !name) {
			return null;
		}

		let input =
			form.querySelector(`input[type="hidden"][name="${name}"][data-aac-generated-checkout-input="true"]`)
			|| form.querySelector(`input[type="hidden"][name="${name}"]`);
		if (!input) {
			input = document.createElement('input');
			input.type = 'hidden';
			input.name = name;
			input.dataset.aacGeneratedCheckoutInput = 'true';
			form.appendChild(input);
		}

		return input;
	};

	const removeGeneratedCheckoutHiddenInput = (form, name) => {
		if (!form || !name) {
			return;
		}

		form.querySelectorAll(`input[type="hidden"][name="${name}"][data-aac-generated-checkout-input="true"]`).forEach((input) => {
			input.remove();
		});
	};

	const getNativeDiscountCodeMessage = () => document.getElementById('discount_code_message');

	const getDiscountCodeState = () => {
		const appliedCode = String(window.__aacAppliedDiscountCode || '').trim();
		if (appliedCode) {
			return appliedCode;
		}

		const selectedInput = document.querySelector('input[name="aac_membership_discount"]:checked');
		const selectedCode = (selectedInput?.dataset.aacMembershipDiscountCode || '').trim();
		if (selectedCode) {
			return selectedCode;
		}

		return '';
	};

	const normalizeDiscountCode = (code) => String(code || '').trim().toUpperCase();

	const getMembershipDiscountLabelForCode = (code) => {
		const normalizedCode = normalizeDiscountCode(code);
		if (!normalizedCode) {
			return '';
		}

		const selectedInput = document.querySelector('input[name="aac_membership_discount"]:checked');
		if (normalizeDiscountCode(selectedInput?.dataset.aacMembershipDiscountCode) === normalizedCode) {
			const selectedLabel = (selectedInput.dataset.aacMembershipDiscountLabel || '').trim();
			return selectedLabel ? `${selectedLabel} (35%)` : '';
		}

		if (normalizedCode === 'STUDENT') {
			return 'Student Discount (35%)';
		}

		if (normalizedCode === 'USMILITARY') {
			return 'Military Discount (35%)';
		}

		return '';
	};

	const getDisplayDiscountCodeState = () => {
		const appliedCode = getDiscountCodeState();
		if (appliedCode) {
			return getMembershipDiscountLabelForCode(appliedCode) ? '' : appliedCode;
		}

		return String(window.__aacDiscountCodeInputValue || '').trim();
	};

	const isRawPmproDiscountMessage = (text) => {
		const value = String(text || '');
		return /jQuery\(|pmpro_require_billing|pmpropbc|other_discount_code_toggle|pmpro_level_discount_applied|<\\\//.test(value);
	};

	const cleanCheckoutMessageText = (text) => {
		const value = String(text || '').trim();
		if (!value) {
			return '';
		}

		if (isRawPmproDiscountMessage(value) && /code has been applied to your order/i.test(value)) {
			const appliedCode = getDiscountCodeState();
			const appliedLabel = getMembershipDiscountLabelForCode(appliedCode);
			return appliedLabel ? `${appliedLabel} has been applied to your order.` : (appliedCode ? `The ${appliedCode} code has been applied to your order.` : 'The discount code has been applied to your order.');
		}

		if (isRawPmproDiscountMessage(value)) {
			return '';
		}

		return value;
	};

	const isDiscountCodeInvalidMessage = (text) => {
		const value = String(text || '').trim();
		return /invalid|not found|not valid|expired|no longer valid|does not apply|not applicable|already used|usage limit|could not be found|please try another/i.test(value);
	};

	const isDiscountCodeAppliedMessage = (text) => {
		const value = String(text || '').trim();
		return /code has been applied|discount code has been applied|has been applied to your order|discount applied/i.test(value);
	};

	const setDiscountCodeMessage = (text, type = 'error') => {
		window.__aacDiscountCodeMessage = String(text || '').trim();
		window.__aacDiscountCodeMessageType = type === 'success' ? 'success' : 'error';

		const message = document.querySelector('[data-aac-discount-code-message]');
		if (!message) {
			return;
		}

		message.textContent = window.__aacDiscountCodeMessage;
		message.className = `pmpro_message ${window.__aacDiscountCodeMessageType === 'success' ? 'pmpro_success' : 'pmpro_error'}`;
		message.style.display = window.__aacDiscountCodeMessage ? '' : 'none';
	};

	const clearDiscountCodeApplication = (inputValue = '') => {
		window.__aacAppliedDiscountCode = '';
		window.__aacMembershipDiscountCode = '';
		window.__aacDiscountCodeInputValue = String(inputValue || '').trim();
		document.querySelectorAll('input[name="aac_membership_discount"][data-aac-toggleable-choice="true"]').forEach((input) => {
			input.checked = false;
			input.removeAttribute('checked');
		});
		getNativeDiscountCodeInputs().forEach((input) => {
			input.value = '';
		});
	};

	const markDiscountCodeApplied = (code) => {
		const normalizedCode = String(code || '').trim();
		window.__aacAppliedDiscountCode = normalizedCode;
		window.__aacDiscountCodeInputValue = '';
		window.__aacMembershipDiscountCode = getMembershipDiscountLabelForCode(normalizedCode) ? normalizedCode : '';
		setDiscountCodeMessage(cleanCheckoutMessageText(getNativeDiscountCodeMessage()?.textContent || '') || 'Discount code applied.', 'success');
	};

	const hasPmproDiscountCodePriceEffect = () => {
		const codeLevel = window.pmpropbc?.code_level || null;
		const nocodeLevel = window.pmpropbc?.nocode_level || null;
		if (!codeLevel || !nocodeLevel) {
			return false;
		}

		const pairs = [
			['initial_payment', 'initial_payment'],
			['billing_amount', 'billing_amount'],
		];

		return pairs.some(([codeKey, nocodeKey]) => {
			const codeAmount = Number.parseFloat(codeLevel?.[codeKey] ?? '');
			const nocodeAmount = Number.parseFloat(nocodeLevel?.[nocodeKey] ?? '');
			return Number.isFinite(codeAmount)
				&& Number.isFinite(nocodeAmount)
				&& Math.abs(codeAmount - nocodeAmount) >= 0.01;
		});
	};

	const hasVisibleDiscountCodePriceEffect = () => {
		const priceText = document.querySelector('#pmpro_level_cost .pmpro_level-price, #pmpro_level_cost .pmpro_level_cost_text strong, #pmpro_level_cost')?.textContent || '';
		const visibleAmount = parseCurrencyValue(priceText);
		if (!Number.isFinite(visibleAmount)) {
			return false;
		}

		const baseCandidates = [
			document.getElementById('pmpro_form_fieldset-membership-discounts')?.dataset?.aacMembershipBasePrice,
			document.getElementById('pmpro_form_fieldset-partner-family')?.dataset?.aacPartnerFamilyBasePrice,
			document.getElementById('pmpro_form_fieldset-magazine-addons')?.dataset?.aacMagazineBasePrice,
			window.pmpropbc?.nocode_level?.initial_payment,
			window.pmpropbc?.nocode_level?.billing_amount,
		]
			.map((value) => Number.parseFloat(value ?? ''))
			.filter((value) => Number.isFinite(value) && value >= 0);

		return baseCandidates.some((baseAmount) => baseAmount > visibleAmount && Math.abs(baseAmount - visibleAmount) >= 0.01);
	};

	const hasDiscountCodePriceEffect = () => hasPmproDiscountCodePriceEffect() || hasVisibleDiscountCodePriceEffect();

	const syncDiscountCodeValidationFromNative = (pendingCode) => {
		const code = String(pendingCode || '').trim();
		const nativeMessage = getNativeDiscountCodeMessage();
		const rawMessage = (nativeMessage?.textContent || '').trim();
		const cleanMessage = cleanCheckoutMessageText(rawMessage);
		const isNativeError = nativeMessage?.classList.contains('pmpro_error') || nativeMessage?.classList.contains('pmpro_alert-danger');

		if (hasDiscountCodePriceEffect()) {
			markDiscountCodeApplied(code);
			return true;
		}

		if (isDiscountCodeAppliedMessage(rawMessage) || isDiscountCodeAppliedMessage(cleanMessage)) {
			markDiscountCodeApplied(code);
			return true;
		}

		if (isNativeError || isDiscountCodeInvalidMessage(rawMessage) || isDiscountCodeInvalidMessage(cleanMessage)) {
			clearDiscountCodeApplication(code);
			setDiscountCodeMessage(cleanMessage && !isRawPmproDiscountMessage(rawMessage) ? cleanMessage : 'Invalid discount code.', 'error');
			return true;
		}

		return false;
	};

	let discountMessageSyncSuppressedUntil = 0;
	const suppressDiscountMessageSync = (duration = 1800) => {
		discountMessageSyncSuppressedUntil = Date.now() + duration;
	};
	const isDiscountMessageSyncSuppressed = () => Date.now() < discountMessageSyncSuppressedUntil;

	const scrubPmproDiscountMessages = () => {
		Array.from(document.querySelectorAll('#pmpro_message, #pmpro_message_bottom, #discount_code_message, .pmpro_message, .pmpro_error, [role="alert"]')).forEach((message) => {
			if (!message) {
				return;
			}

			const messageText = (message.textContent || '').trim();
			if (!isRawPmproDiscountMessage(messageText)) {
				return;
			}

			const cleanText = cleanCheckoutMessageText(messageText);
			if (cleanText) {
				message.textContent = cleanText;
				message.classList.remove('pmpro_error');
				message.classList.add('pmpro_success');
				message.style.display = 'none';
			} else {
				message.textContent = '';
				message.style.display = 'none';
			}
		});
	};

	const getPmproMembershipAmount = (fallbackAmount) => {
		const codeLevel = window.pmpropbc?.code_level || null;
		const nocodeLevel = window.pmpropbc?.nocode_level || null;
		const nocodeInitialPayment = Number.parseFloat(nocodeLevel?.initial_payment ?? '');
		const codeInitialPayment = Number.parseFloat(codeLevel?.initial_payment ?? '');
		if (
			Number.isFinite(codeInitialPayment)
			&& codeInitialPayment >= 0
			&& Number.isFinite(nocodeInitialPayment)
			&& Math.abs(codeInitialPayment - nocodeInitialPayment) >= 0.01
		) {
			return codeInitialPayment;
		}

		const nocodeBillingAmount = Number.parseFloat(nocodeLevel?.billing_amount ?? '');
		const codeBillingAmount = Number.parseFloat(codeLevel?.billing_amount ?? '');
		if (
			Number.isFinite(codeBillingAmount)
			&& codeBillingAmount >= 0
			&& Number.isFinite(nocodeBillingAmount)
			&& Math.abs(codeBillingAmount - nocodeBillingAmount) >= 0.01
		) {
			return codeBillingAmount;
		}

		const selectedMembershipDiscountInput = document.querySelector('input[name="aac_membership_discount"][data-aac-toggleable-choice="true"]:checked');
		const selectedMembershipDiscountRate = Number.parseFloat(selectedMembershipDiscountInput?.dataset.aacMembershipDiscountRate || '0') || 0;
		if (selectedMembershipDiscountRate > 0 && selectedMembershipDiscountRate < 1) {
			const baseAmount = Number.parseFloat(fallbackAmount || '0') || 0;
			return Math.max(0, Math.round((baseAmount * (1 - selectedMembershipDiscountRate)) * 100) / 100);
		}

		const priceText = document.querySelector('#pmpro_level_cost .pmpro_level_cost_text strong')?.textContent
			|| document.querySelector('#pmpro_level_cost')?.textContent
			|| '';
		return parseCurrencyValue(priceText) ?? fallbackAmount;
	};

	const buildDiscountCodeMarkup = () => {
		if (document.body.classList.contains('pmpro-billing')) {
			return '';
		}
		const appliedCode = getDiscountCodeState();
		const appliedDiscountLabel = getMembershipDiscountLabelForCode(appliedCode);
		const displayCode = getDisplayDiscountCodeState();
		const messageText = String(window.__aacDiscountCodeMessage || '').trim();
		const messageClass = window.__aacDiscountCodeMessageType === 'success' ? 'pmpro_success' : 'pmpro_error';
		return `
			<div class="aac-magazine-addons__promo" data-aac-discount-code>
				<div class="aac-magazine-addons__promo-copy">
					<p class="aac-magazine-addons__promo-label">Promo or Discount Code</p>
					<p>Apply a PMPro-generated discount code before payment.</p>
				</div>
				<div class="aac-magazine-addons__promo-form" data-aac-discount-code-form>
					<input
						type="text"
						name="discount_code"
						class="aac-magazine-addons__promo-input"
						placeholder="Enter code"
						value="${escapeHtml(displayCode)}"
						autocomplete="off"
					/>
					<button type="button" class="aac-magazine-addons__promo-button" data-aac-discount-code-apply>Apply Code</button>
				</div>
				<p class="pmpro_message ${messageClass}" data-aac-discount-code-message style="${messageText ? '' : 'display: none;'}">${escapeHtml(messageText)}</p>
				${appliedCode ? `
					<div class="aac-magazine-addons__promo-applied">
						<span>${appliedDiscountLabel ? 'Applied discount' : 'Applied code'}: <strong>${escapeHtml(appliedDiscountLabel || appliedCode)}</strong></span>
						<button type="button" class="aac-magazine-addons__promo-clear" data-aac-discount-code-clear>Remove code</button>
					</div>
				` : ''}
			</div>
		`;
	};

	const bindDiscountCodeForm = (summary) => {
		const wrapper = summary?.querySelector('[data-aac-discount-code-form]');
		if (wrapper && wrapper.dataset.aacBound !== 'true') {
			const applyDiscountCode = () => {
				const nextCode = (wrapper.querySelector('input[name="discount_code"]')?.value || '').trim();
				if (!nextCode) {
					clearDiscountCodeApplication('');
					setDiscountCodeMessage('Enter a discount code.', 'error');
					syncMagazineAddonSummary();
					return;
				}

				clearDiscountCodeApplication(nextCode);
				window.__aacDiscountCodeMessage = '';
				window.__aacDiscountCodeMessageType = 'error';
				document.querySelectorAll('input[name="aac_membership_discount"][data-aac-toggleable-choice="true"]').forEach((input) => {
					const inputCode = normalizeDiscountCode(input.dataset.aacMembershipDiscountCode);
					const shouldKeepSelected = inputCode && inputCode === normalizeDiscountCode(nextCode);
					input.checked = shouldKeepSelected;
					if (shouldKeepSelected) {
						input.setAttribute('checked', 'checked');
					} else {
						input.removeAttribute('checked');
					}
				});
					getNativeDiscountCodeInputs().forEach((input) => {
						input.value = nextCode;
					});
				suppressDiscountMessageSync();
				getNativeDiscountCodeButton()?.click();
				[120, 300, 700, 1300].forEach((delay) => {
					window.setTimeout(scrubPmproDiscountMessages, delay);
					window.setTimeout(() => {
						if (syncDiscountCodeValidationFromNative(nextCode)) {
							syncMagazineAddonSummary();
						}
					}, delay + 40);
				});
				window.setTimeout(() => {
					if (!getDiscountCodeState() && normalizeDiscountCode(window.__aacDiscountCodeInputValue) === normalizeDiscountCode(nextCode)) {
						if (hasDiscountCodePriceEffect()) {
							markDiscountCodeApplied(nextCode);
						} else {
							clearDiscountCodeApplication(nextCode);
							setDiscountCodeMessage(window.__aacDiscountCodeMessage || 'Invalid discount code.', 'error');
						}
						syncMagazineAddonSummary();
					}
				}, 2200);
				window.setTimeout(syncMagazineAddonSummary, 250);
				window.setTimeout(syncMagazineAddonSummary, 900);
			};

			wrapper.querySelector('[data-aac-discount-code-apply]')?.addEventListener('click', applyDiscountCode);
			wrapper.querySelector('input[name="discount_code"]')?.addEventListener('keydown', (event) => {
				if (event.key !== 'Enter') {
					return;
				}

				event.preventDefault();
				applyDiscountCode();
			});
			wrapper.dataset.aacBound = 'true';
		}

			const clearButton = summary?.querySelector('[data-aac-discount-code-clear]');
			if (clearButton && clearButton.dataset.aacBound !== 'true') {
			clearButton.addEventListener('click', () => {
				window.__aacAppliedDiscountCode = '';
				window.__aacMembershipDiscountCode = '';
				document.querySelectorAll('input[name="aac_membership_discount"][data-aac-toggleable-choice="true"]').forEach((input) => {
					input.checked = false;
					input.removeAttribute('checked');
				});
				getNativeDiscountCodeInputs().forEach((input) => {
					input.value = '';
				});
				window.location.reload();
			});
			clearButton.dataset.aacBound = 'true';
		}

		const summaryMessage = summary?.querySelector('[data-aac-discount-code-message]');
		const nativeMessage = getNativeDiscountCodeMessage();
		if (summaryMessage && window.__aacDiscountCodeMessage) {
			setDiscountCodeMessage(window.__aacDiscountCodeMessage, window.__aacDiscountCodeMessageType);
		} else if (summaryMessage && nativeMessage) {
			const messageText = (nativeMessage.textContent || '').trim();
			summaryMessage.textContent = messageText;
			summaryMessage.className = nativeMessage.className ? `pmpro_message ${nativeMessage.className}` : 'pmpro_message';
			summaryMessage.style.display = messageText ? '' : 'none';
		}
	};

	const getCurrentCheckoutLevelId = () => Number.parseInt(document.getElementById('pmpro_level')?.value || '0', 10) || 0;

	const getCurrentCheckoutLevelName = () => {
		const levelId = getCurrentCheckoutLevelId();
		const levels = window.pmpro?.all_levels || window.pmpro?.all_levels_formatted_text || {};
		const preferredName =
			levels[String(levelId)]?.name?.trim()
			|| window.pmpropbc?.nocode_level?.name?.trim()
			|| document.querySelector('.pmpro_level_name_text strong')?.textContent?.trim()
			|| '';
		return preferredName && !/^membership$/i.test(preferredName) ? preferredName : 'Membership';
	};

		const currentLevelSupportsDiscountTiers = () => {
			const levelName = String(getCurrentCheckoutLevelName() || '').trim().toLowerCase();
			if (!levelName || levelName === 'membership') {
				return false;
			}

			return levelName === 'partner' && isCheckoutCountryUS();
		};

		const getCheckoutControl = (ids) => {
			for (const id of ids) {
				const control = document.getElementById(id);
				if (control) {
					return control;
				}
			}
			return null;
		};

		const getCheckoutCountryControl = () => getCheckoutControl(['pmpro_scountry', 'scountry', 'bcountry']);
		const getCheckoutStateControl = () => getCheckoutControl(['pmpro_sstate', 'sstate', 'bstate']);
		const getCheckoutCountryValue = () => String(getCheckoutCountryControl()?.value || 'US').trim().toUpperCase();
		const getCheckoutNamedControl = (names) => {
			for (const name of names) {
				const control = document.getElementsByName(name)?.[0] || document.getElementById(name);
				if (control) {
					return control;
				}
			}
			return null;
		};
		const syncNativePmproMemberFieldsToLegacyBilling = () => {
			[
				[['pmpro_sfirstname', 'sfirstname', 'first_name'], ['bfirstname', 'first_name']],
				[['pmpro_slastname', 'slastname', 'last_name'], ['blastname', 'last_name']],
				[['pmpro_saddress1', 'saddress1'], ['baddress1']],
				[['pmpro_saddress2', 'saddress2'], ['baddress2']],
				[['pmpro_scity', 'scity'], ['bcity']],
				[['pmpro_sstate', 'sstate'], ['bstate']],
				[['pmpro_szipcode', 'szipcode'], ['bzipcode']],
				[['pmpro_scountry', 'scountry'], ['bcountry']],
				[['pmpro_sphone', 'sphone'], ['bphone']],
			].forEach(([sourceNames, targetNames]) => {
				const source = getCheckoutNamedControl(sourceNames);
				if (!source) {
					return;
				}

				const value = source.value || '';
				targetNames.forEach((targetName) => {
					const target = getCheckoutNamedControl([targetName]);
					if (target && target !== source) {
						target.value = value;
					}
				});
			});
		};

		const isCheckoutCountryUS = () => ['', 'US', 'USA', 'UNITED STATES', 'UNITED STATES OF AMERICA'].includes(getCheckoutCountryValue());

		const getNormalizedCheckoutCountryCode = () => {
			const normalized = getCheckoutCountryValue().replace(/[^A-Z ]+/g, '').replace(/\s+/g, ' ').trim();
			if (['', 'US', 'USA', 'UNITED STATES', 'UNITED STATES OF AMERICA'].includes(normalized)) {
				return 'US';
			}
			if (['CA', 'CAN', 'CANADA'].includes(normalized)) {
				return 'CA';
			}
			if (['MX', 'MEX', 'MEXICO'].includes(normalized)) {
				return 'MX';
			}
			return normalized;
		};

		const isCheckoutCountryNorthAmerica = () => ['US', 'CA', 'MX'].includes(getNormalizedCheckoutCountryCode());

		const getPmproLevels = () => window.pmpro?.all_levels || window.pmpro?.all_levels_formatted_text || {};

		const getPmproLevelByName = (name) => {
			const normalizedName = String(name || '').trim().toLowerCase();
			return Object.values(getPmproLevels()).find((level) => String(level?.name || '').trim().toLowerCase() === normalizedName) || null;
		};

		const isPartnerCountryRoutedLevelName = (name) => ['partner', 'partner north america', 'partner international'].includes(String(name || '').trim().toLowerCase());

		const getPartnerCountryTargetLevel = () => {
			const countryCode = getNormalizedCheckoutCountryCode();
			const targetName = countryCode === 'US'
				? 'Partner'
				: (['CA', 'MX'].includes(countryCode) ? 'Partner North America' : 'Partner International');
			return getPmproLevelByName(targetName);
		};

		const getCurrentCheckoutLevelPrice = () => {
			const level = getPmproLevels()[String(getCurrentCheckoutLevelId())] || null;
			const initialPayment = Number.parseFloat(level?.initial_payment ?? '');
			if (Number.isFinite(initialPayment) && initialPayment >= 0) {
				return initialPayment;
			}

			const billingAmount = Number.parseFloat(level?.billing_amount ?? '');
			if (Number.isFinite(billingAmount) && billingAmount >= 0) {
				return billingAmount;
			}

			return null;
		};

		const syncCountryRoutedPartnerLevel = () => {
			const levelInput = document.getElementById('pmpro_level');
			if (!levelInput) {
				return;
			}

			const currentLevelName = getCurrentCheckoutLevelName();
			if (!isPartnerCountryRoutedLevelName(currentLevelName)) {
				return;
			}

			const targetLevel = getPartnerCountryTargetLevel();
			if (!targetLevel?.id) {
				return;
			}

			const nextLevelId = String(targetLevel.id);
			window.__aacCountryRoutedPartnerLevel = true;
			if (levelInput.value !== nextLevelId) {
				levelInput.value = nextLevelId;
				levelInput.dispatchEvent(new Event('change', { bubbles: true }));
			}

			const levelNameNode = document.querySelector('.pmpro_level_name_text strong');
			if (levelNameNode) {
				levelNameNode.textContent = targetLevel.name || getCurrentCheckoutLevelName();
			}

			const priceNode = document.querySelector('#pmpro_level_cost .pmpro_level-price, #pmpro_level_cost .pmpro_level_cost_text strong');
			if (priceNode && targetLevel.formatted_price) {
				priceNode.innerHTML = targetLevel.formatted_price;
			}
		};

	const getCurrentCheckoutBasePrice = () => {
		const countryRoutedPrice = window.__aacCountryRoutedPartnerLevel ? getCurrentCheckoutLevelPrice() : null;
		if (Number.isFinite(countryRoutedPrice) && countryRoutedPrice >= 0) {
			return countryRoutedPrice;
		}

		const datasetBasePrice = [
			document.getElementById('pmpro_form_fieldset-membership-discounts')?.dataset?.aacMembershipBasePrice,
			document.getElementById('pmpro_form_fieldset-partner-family')?.dataset?.aacPartnerFamilyBasePrice,
			document.getElementById('pmpro_form_fieldset-magazine-addons')?.dataset?.aacMagazineBasePrice,
		]
			.map((value) => Number.parseFloat(value || ''))
			.find((value) => Number.isFinite(value) && value >= 0);
		if (Number.isFinite(datasetBasePrice)) {
			return datasetBasePrice;
		}

		const levelId = getCurrentCheckoutLevelId();
		const levels = window.pmpro?.all_levels || window.pmpro?.all_levels_formatted_text || {};
		const level = levels[String(levelId)] || null;
		const initialPayment = Number.parseFloat(level?.initial_payment ?? '');
		if (Number.isFinite(initialPayment) && initialPayment >= 0) {
			return initialPayment;
		}

		const billingAmount = Number.parseFloat(level?.billing_amount ?? '');
		if (Number.isFinite(billingAmount) && billingAmount >= 0) {
			return billingAmount;
		}

		return null;
	};

	const buildMembershipLineItemLabel = (membershipName) => {
		const normalized = String(membershipName || '')
			.replace(/\s+membership(?:\s+membership)+$/i, ' Membership')
			.trim();
		if (!normalized || /^membership$/i.test(normalized)) {
			return 'Membership';
		}

		return /membership$/i.test(normalized) ? normalized : `${normalized} Membership`;
	};

	const getProratedMembershipSummaryLabel = (membershipName) => {
		const membershipLabel = buildMembershipLineItemLabel(membershipName);
		return isCurrentCheckoutProrated()
			? `${membershipLabel} (prorated amount due today)`
			: membershipLabel;
	};

	const isCurrentCheckoutProrated = () => {
		const levelId = getCurrentCheckoutLevelId();
		const levels = window.pmpro?.all_levels || window.pmpro?.all_levels_formatted_text || {};
		const level = levels[String(levelId)] || null;
		const initialPayment = Number.parseFloat(level?.initial_payment ?? '');
		const billingAmount = Number.parseFloat(level?.billing_amount ?? '');
		return Number.isFinite(initialPayment) && Number.isFinite(billingAmount) && Math.abs(initialPayment - billingAmount) >= 0.01;
	};

	const ensureHiddenPreferenceInput = (form, name, value) => {
		if (!form) {
			return null;
		}

		let input = form.querySelector(`input[name="${name}"]`);
		if (!input) {
			input = document.createElement('input');
			input.type = 'hidden';
			input.name = name;
			form.appendChild(input);
		}

		input.value = value;
		return input;
	};

	const ensureTshirtPreferenceField = (targetContainer = null) => {
		const existingField =
			document.getElementById('t_shirt_div') ||
			document.querySelector('select[name="t_shirt"]')?.closest('.pmpro_form_field');
		if (existingField) {
			return existingField;
		}

		const form = document.getElementById('pmpro_form') || document.querySelector('form.pmpro_form');
		if (!form) {
			return null;
		}

		const field = document.createElement('div');
		field.id = 't_shirt_div';
		field.className = 'pmpro_form_field pmpro_form_field-select pmpro_form_field-t_shirt';

		const label = document.createElement('label');
		label.className = 'pmpro_form_label';
		label.htmlFor = 't_shirt';
		label.textContent = 'T-shirt Size';

		const select = document.createElement('select');
		select.id = 't_shirt';
		select.name = 't_shirt';
		select.className = 'pmpro_form_input pmpro_form_input-select';

		getConfiguredTshirtSizeOptions().forEach(({ value, label }) => {
			const option = document.createElement('option');
			option.value = value;
			option.textContent = label;
			select.appendChild(option);
		});

		const requestedDefault = String(checkoutProfileDefaults?.size || 'No T-shirt').trim();
		select.value = Array.from(select.options).some((option) => option.value === requestedDefault)
			? requestedDefault
			: 'No T-shirt';

		field.append(label, select);
		(targetContainer || form).appendChild(field);
		return field;
	};

	const buildMemberPreferenceCards = (fieldset, currentLevelId) => {
		if (!fieldset) {
			return;
		}

		if (fieldset.querySelector('.aac-server-member-preferences')) {
			return;
		}

		const legacyPublicationField =
			document.getElementById('publications_preference_div') ||
			fieldset.querySelector('.pmpro_form_field-publications_preference');
		const aajField =
			document.getElementById('aaj_preference_div') ||
			fieldset.querySelector('.pmpro_form_field-aaj_preference');
		const anacField =
			document.getElementById('anac_preference_div') ||
			fieldset.querySelector('.pmpro_form_field-anac_preference');
		const acjField =
			document.getElementById('american_climbing_journal_preference_div') ||
			fieldset.querySelector('.pmpro_form_field-american_climbing_journal_preference');
		const guidebookField =
			document.getElementById('guidebook_preferences_div') ||
			fieldset.querySelector('.pmpro_form_field-guidebook_preferences');

		const showPublicationPreferences = currentLevelId > 2 && isCheckoutCountryUS();
		let intro = fieldset.querySelector('.aac-member-preferences__intro');
		if (!intro) {
			intro = document.createElement('p');
			intro.className = 'aac-member-preferences__intro';
			intro.textContent = 'Select a publication to receive a print copy. Publications you do not select for print will be delivered digitally and can be accessed through your member profile.';
		}

		let cardsGrid = fieldset.querySelector('.aac-member-preferences__grid');
		if (!cardsGrid) {
			cardsGrid = document.createElement('div');
			cardsGrid.className = 'aac-member-preferences__grid';
		}

		const hideOriginalField = (field) => {
			if (!field) {
				return;
			}
			field.hidden = true;
			field.style.display = 'none';
		};

		hideOriginalField(legacyPublicationField);
		hideOriginalField(aajField);
		hideOriginalField(anacField);
		hideOriginalField(acjField);
		hideOriginalField(guidebookField);

		if (!showPublicationPreferences) {
			intro.remove();
			cardsGrid.remove();
			return;
		}

		const legacyPublicationSelect = legacyPublicationField?.querySelector('select');
		const aajSelect = aajField?.querySelector('select');
			const anacSelect = anacField?.querySelector('select');
			const acjSelect = acjField?.querySelector('select');
			const guidebookSelect = guidebookField?.querySelector('select');
			if (!isCheckoutCountryUS()) {
				[legacyPublicationSelect, aajSelect, anacSelect, acjSelect, guidebookSelect].filter(Boolean).forEach((select) => {
					select.value = 'Digital';
					select.dispatchEvent(new Event('change', { bubbles: true }));
				});
			}
			const resolvedPublicationCardImages = {
			aaj: publicationCardImages.aaj || defaultPublicationCardImages.aaj,
			anac: publicationCardImages.anac || defaultPublicationCardImages.anac,
			acj: publicationCardImages.acj || defaultPublicationCardImages.acj,
			guidebook: publicationCardImages.guidebook || defaultPublicationCardImages.guidebook,
		};

		if (!intro.parentNode) {
			fieldset.querySelector('.pmpro_form_fields')?.prepend(intro);
		}

		if (!cardsGrid.parentNode) {
			intro.insertAdjacentElement('afterend', cardsGrid);
		}

		const createPreferenceCard = ({ themeClass, eyebrow, title, description, fieldName, selectElement, imageUrl, onChange }) => {
			if (!selectElement) {
				return null;
			}

			const card = document.createElement('article');
			card.className = `aac-member-preferences__card ${themeClass}`;
			card.dataset.aacPrefSource = fieldName;
			if (imageUrl) {
				card.style.setProperty('--aac-member-pref-image', `url("${String(imageUrl).replace(/"/g, '&quot;')}")`);
			}
				card.innerHTML = `
				<div class="aac-member-preferences__art">${imageUrl ? `<img src="${String(imageUrl).replace(/"/g, '&quot;')}" alt="${title} cover" class="aac-member-preferences__cover-image" />` : ''}</div>
				<div class="aac-member-preferences__content">
					<div class="aac-member-preferences__title-block">
						<span class="aac-member-preferences__eyebrow">${eyebrow}</span>
						<h3 class="aac-member-preferences__title">${title}</h3>
					</div>
					<p class="aac-member-preferences__description">${description}</p>
					<div class="aac-member-preferences__choices">
						<button type="button" class="aac-member-preferences__choice" data-value="Print">Print</button>
						<button type="button" class="aac-member-preferences__choice" data-value="Digital">Digital</button>
					</div>
				</div>
			`;

			const syncCardState = () => {
				const nextValue = (selectElement.value || 'Print').trim() === 'Digital' ? 'Digital' : 'Print';
				selectElement.value = nextValue;
				const choicesWrap = card.querySelector('.aac-member-preferences__choices');
				if (choicesWrap) {
					choicesWrap.classList.toggle('is-print', nextValue === 'Print');
					choicesWrap.classList.toggle('is-digital', nextValue === 'Digital');
				}
				card.querySelectorAll('.aac-member-preferences__choice').forEach((choice) => {
					choice.classList.toggle('is-active', choice.dataset.value === nextValue);
				});
			};

			card.querySelectorAll('.aac-member-preferences__choice').forEach((choice) => {
				choice.addEventListener('click', () => {
					selectElement.value = choice.dataset.value;
					selectElement.dispatchEvent(new Event('change', { bubbles: true }));
					if (typeof onChange === 'function') {
						onChange(choice.dataset.value);
					}
					syncMagazineAddonSummary();
					document.querySelectorAll(`[data-aac-pref-source="${fieldName}"]`).forEach((node) => {
						node.dispatchEvent(new CustomEvent('aac:sync-card-state'));
					});
				});
			});

			if (selectElement.dataset.aacCardSyncBound !== 'true') {
				selectElement.addEventListener('change', syncCardState);
				selectElement.dataset.aacCardSyncBound = 'true';
			}

			card.addEventListener('aac:sync-card-state', syncCardState);
			syncCardState();

			return card;
		};

		cardsGrid.innerHTML = '';
		[
			createPreferenceCard({
				themeClass: 'aac-member-preferences__card--journal',
				eyebrow: 'Annual',
				title: 'American Alpine Journal',
				description: 'Annual climbing journal. Choose print delivery or digital-only access.',
				fieldName: 'aaj_preference',
				selectElement: aajSelect,
				imageUrl: resolvedPublicationCardImages.aaj,
				onChange: (value) => {
					if (legacyPublicationSelect) {
						legacyPublicationSelect.value = value;
						legacyPublicationSelect.dispatchEvent(new Event('change', { bubbles: true }));
					}
				},
			}),
			createPreferenceCard({
				themeClass: 'aac-member-preferences__card--accidents',
				eyebrow: 'Annual',
				title: 'Accidents in North American Climbing',
				description: 'Annual accident review. Choose print delivery or digital-only access.',
				fieldName: 'anac_preference',
				selectElement: anacSelect,
				imageUrl: resolvedPublicationCardImages.anac,
			}),
			createPreferenceCard({
				themeClass: 'aac-member-preferences__card--journal',
				eyebrow: 'Journal',
				title: 'American Climbing Journal',
				description: 'Member stories and club updates. Choose print delivery or digital-only access.',
				fieldName: 'american_climbing_journal_preference',
				selectElement: acjSelect,
				imageUrl: resolvedPublicationCardImages.acj,
			}),
			createPreferenceCard({
				themeClass: 'aac-member-preferences__card--guidebook',
				eyebrow: 'Quarterly',
				title: 'Guidebook to Membership',
				description: 'Quarterly member publication. Choose print delivery or digital-only access.',
				fieldName: 'guidebook_preferences',
				selectElement: guidebookSelect,
				imageUrl: resolvedPublicationCardImages.guidebook,
			}),
		].filter(Boolean).forEach((card) => cardsGrid.appendChild(card));
	};

		const enhancePmproProfileInformation = () => {
			const socialLoginFieldset = document.getElementById('pmpro_social_login');
			const socialLoginActions = document.getElementById('pmpro_card_actions-social_login');
			const pricingFieldset = document.getElementById('pmpro_pricing_fields');
			const userFieldsFieldset = document.getElementById('pmpro_user_fields');
			const billingFieldset = document.getElementById('pmpro_billing_address_fields');
			if (!billingFieldset || billingFieldset.dataset.aacProfileEnhanced === 'true') {
				return;
			}

			const billingFields = billingFieldset.querySelector('.pmpro_form_fields');
			if (!billingFields) {
				return;
			}

			billingFieldset.dataset.aacProfileEnhanced = 'true';

			if (userFieldsFieldset) {
				userFieldsFieldset.hidden = false;
				userFieldsFieldset.style.display = 'block';
			}

			document.querySelectorAll('style').forEach((styleNode) => {
				if (styleNode.textContent && styleNode.textContent.includes('#pmpro_user_fields')) {
					styleNode.textContent = styleNode.textContent.replace(/#pmpro_user_fields\s*\{[^}]*\}/g, '');
				}
			});

			if (socialLoginActions) {
				socialLoginActions.remove();
			}

			if (socialLoginFieldset) {
				socialLoginFieldset.remove();
			}

			if (pricingFieldset) {
				pricingFieldset.hidden = true;
				pricingFieldset.style.display = 'none';
			}

			const accountHeading = userFieldsFieldset?.querySelector('.pmpro_form_heading');
			if (accountHeading) {
				accountHeading.textContent = 'Create Account';
			}

		const accountFields = userFieldsFieldset?.querySelector('.pmpro_form_fields');
		const usernameInput = userFieldsFieldset?.querySelector('input[name="username"]');
		const emailInput = userFieldsFieldset?.querySelector('input[name="bemail"]');
		const confirmEmailInput = userFieldsFieldset?.querySelector('input[name="bconfirmemail"]');
		const passwordInput = userFieldsFieldset?.querySelector('input[name="password"]');
		const confirmPasswordInput = userFieldsFieldset?.querySelector('input[name="password2"]');
		const generateCheckoutUsernameFromEmail = (email) => {
			const emailPrefix = String(email || '').split('@')[0] || '';
			const cleaned = emailPrefix
				.toLowerCase()
				.replace(/[^a-z0-9._-]+/g, '.')
				.replace(/^[._-]+|[._-]+$/g, '')
				.replace(/[._-]{2,}/g, '.');
			return cleaned || `member${Date.now()}`;
		};
		const syncCheckoutAccountHiddenFields = () => {
			if (emailInput && confirmEmailInput) {
				const emailValue = String(emailInput.value || '').trim();
				if (confirmEmailInput.value !== emailValue) {
					confirmEmailInput.value = emailValue;
					confirmEmailInput.dispatchEvent(new Event('input', { bubbles: true }));
					confirmEmailInput.dispatchEvent(new Event('change', { bubbles: true }));
				}
			}

			if (passwordInput && confirmPasswordInput) {
				const passwordValue = String(passwordInput.value || '');
				if (confirmPasswordInput.value !== passwordValue) {
					confirmPasswordInput.value = passwordValue;
					confirmPasswordInput.dispatchEvent(new Event('input', { bubbles: true }));
					confirmPasswordInput.dispatchEvent(new Event('change', { bubbles: true }));
				}
			}

			if (usernameInput && emailInput) {
				const nextUsername = generateCheckoutUsernameFromEmail(emailInput.value);
				if (usernameInput.value !== nextUsername) {
					usernameInput.value = nextUsername;
					usernameInput.dispatchEvent(new Event('input', { bubbles: true }));
					usernameInput.dispatchEvent(new Event('change', { bubbles: true }));
				}
			}
		};
			const birthdateField = document.getElementById('birthdate_div');
			const tshirtField = ensureTshirtPreferenceField(billingFields);
			const personalDetailsFieldset = document.getElementById('pmpro_form_fieldset-personal-details');
		const usernameField = usernameInput?.closest('.pmpro_form_field');
		const emailField = emailInput?.closest('.pmpro_form_field');
		const confirmEmailField = confirmEmailInput?.closest('.pmpro_form_field');
		const passwordField = passwordInput?.closest('.pmpro_form_field');
		const confirmPasswordField = confirmPasswordInput?.closest('.pmpro_form_field');
		const firstNameField = document.getElementById('first_name_div');
		const lastNameField = document.getElementById('last_name_div');
			const nativeDetailFieldIds = [
				'pmpro_saddress1_div',
				'pmpro_saddress2_div',
				'pmpro_scountry_div',
				'pmpro_scity_div',
			'pmpro_sstate_div',
			'pmpro_szipcode_div',
			'pmpro_sphone_div',
		];
		const nativeDetailFields = nativeDetailFieldIds
			.map((fieldId) => document.getElementById(fieldId))
			.filter(Boolean);
		const checkoutPhoneField =
			document.getElementById('pmpro_sphone_div') ||
			document.getElementById('bphone_div') ||
			document.getElementById('phone_div') ||
			document.querySelector('input[name="pmpro_sphone"], input[name="bphone"], input[name="phone"]')?.closest('.pmpro_form_field');
		if (checkoutPhoneField && !nativeDetailFields.includes(checkoutPhoneField)) {
			nativeDetailFields.push(checkoutPhoneField);
		}
		const markRequiredField = (fieldId) => {
			const field = document.getElementById(fieldId);
			if (!field) {
				return;
			}

			const input = field.querySelector('input, select, textarea');
			if (input) {
				input.required = true;
				input.classList.add('pmpro_form_input-required');
			}

			field.classList.add('pmpro_form_field-required');
			const label = field.querySelector('label');
			if (label && !label.querySelector('.pmpro_asterisk')) {
				const asterisk = document.createElement('span');
				asterisk.className = 'pmpro_asterisk';
				asterisk.setAttribute('aria-hidden', 'true');
				asterisk.textContent = ' *';
				label.appendChild(asterisk);
			}
		};
		const markOptionalField = (fieldId) => {
			const field = document.getElementById(fieldId);
			if (!field) {
				return;
			}

			field.classList.remove('pmpro_form_field-required');
			const input = field.querySelector('input, select, textarea');
			if (input) {
				input.required = false;
				input.removeAttribute('required');
				input.removeAttribute('aria-required');
				input.classList.remove('pmpro_form_input-required');
			}
			field.querySelectorAll('.pmpro_asterisk').forEach((asterisk) => asterisk.remove());
		};
		const enhancePasswordRevealControl = (field, input) => {
			if (!field || !input || field.dataset.aacPasswordRevealEnhanced === 'true') {
				return;
			}

			const toggleButton = Array.from(field.querySelectorAll('button, a')).find((node) => {
				const text = (node.textContent || '').trim().toLowerCase();
				return node.classList.contains('pmpro_btn-password-toggle') || text === 'show password' || text === 'hide password';
			});
			if (!toggleButton) {
				return;
			}

			const toggleNode = toggleButton.closest('.pmpro_form_field-password-toggle') || toggleButton;
			if (!toggleNode || toggleNode.contains(input) || input.parentElement?.classList.contains('aac-password-input-wrap')) {
				field.dataset.aacPasswordRevealEnhanced = 'true';
				toggleButton.classList.add('aac-password-toggle');
				return;
			}

			const wrapper = document.createElement('div');
			wrapper.className = 'aac-password-input-wrap';
			input.parentNode.insertBefore(wrapper, input);
			wrapper.appendChild(input);
			wrapper.appendChild(toggleNode);
			toggleButton.classList.add('aac-password-toggle');
			field.dataset.aacPasswordRevealEnhanced = 'true';
		};

			[
				'bemail_div',
				'bconfirmemail_div',
				'pmpro_saddress1_div',
				'pmpro_scountry_div',
				'pmpro_scity_div',
				'pmpro_sstate_div',
			'pmpro_szipcode_div',
		].forEach(markRequiredField);
		markOptionalField('username_div');
			markOptionalField('bphone_div');
			markOptionalField('pmpro_sphone_div');
			markOptionalField('birthdate_div');
			markOptionalField('pmpro_sfirstname_div');
			markOptionalField('pmpro_slastname_div');
			if (nativeDetailFields.length) {
				[
					firstNameField,
					lastNameField,
					document.getElementById('pmpro_sfirstname_div'),
					document.getElementById('pmpro_slastname_div'),
				].filter(Boolean).forEach((field) => {
					markOptionalField(field.id);
					field.hidden = true;
					field.style.display = 'none';
				});
			}
		enhancePasswordRevealControl(passwordField, passwordInput);
		enhancePasswordRevealControl(confirmPasswordField, confirmPasswordInput);
		if (usernameInput) {
			usernameInput.type = 'hidden';
			usernameInput.autocomplete = 'off';
			usernameInput.required = false;
			usernameInput.removeAttribute('required');
			usernameInput.removeAttribute('aria-required');
			usernameInput.classList.remove('pmpro_form_input-required');
		}
		if (usernameField) {
			usernameField.hidden = true;
			usernameField.style.display = 'none';
			usernameField.classList.remove('pmpro_form_field-required');
		}
		[emailInput, passwordInput].filter(Boolean).forEach((control) => {
			if (control.dataset.aacAccountHiddenSyncBound === 'true') {
				return;
			}
			control.addEventListener('input', syncCheckoutAccountHiddenFields);
			control.addEventListener('change', syncCheckoutAccountHiddenFields);
			control.dataset.aacAccountHiddenSyncBound = 'true';
		});
		syncCheckoutAccountHiddenFields();
		if (
			accountFields &&
			emailField &&
				confirmEmailField &&
				passwordField &&
				confirmPasswordField &&
				accountFields.dataset.aacAccountRowsBuilt !== '1'
			) {
				const firstRow = document.createElement('div');
				firstRow.className = 'pmpro_cols-2 aac-managed-two-up';
				firstRow.append(emailField, confirmEmailField);
				const secondRow = document.createElement('div');
				secondRow.className = 'pmpro_cols-2 aac-managed-two-up';
				secondRow.append(passwordField, confirmPasswordField);
				accountFields.append(firstRow, secondRow);
				Array.from(accountFields.querySelectorAll('.pmpro_cols-2')).forEach((row) => {
					if (!row.children.length) {
						row.remove();
					}
				});
				accountFields.dataset.aacAccountRowsBuilt = '1';
			}

			let nativeDetailsFieldset = document.getElementById('aac_pmpro_native_member_information_fields');
			if (!nativeDetailsFieldset && nativeDetailFields.length) {
				nativeDetailsFieldset = document.createElement('fieldset');
				nativeDetailsFieldset.id = 'aac_pmpro_native_member_information_fields';
				nativeDetailsFieldset.className = 'pmpro_form_fieldset pmpro_checkout-fields';
				nativeDetailsFieldset.innerHTML = '<legend class="pmpro_form_legend"><h2 class="pmpro_form_heading">Member Information</h2></legend><div class="pmpro_form_fields"></div>';
				billingFieldset.parentNode.insertBefore(nativeDetailsFieldset, billingFieldset);
			}

			const nativeDetailsFields = nativeDetailsFieldset?.querySelector('.pmpro_form_fields');
			if (nativeDetailsFieldset && nativeDetailsFields && nativeDetailsFields.dataset.aacNativeRowsBuilt !== '1') {
				nativeDetailsFieldset.dataset.aacNativeMemberInfo = 'true';
				const buildNativeTwoUpRow = (fieldIds) => {
					const fields = fieldIds
						.map((fieldId) => typeof fieldId === 'string' ? document.getElementById(fieldId) : fieldId)
						.filter(Boolean);
					if (!fields.length) {
						return;
					}
					const row = document.createElement('div');
					row.className = 'pmpro_cols-2 aac-managed-two-up';
					fields.forEach((field) => row.appendChild(field));
					nativeDetailsFields.appendChild(row);
				};

				[tshirtField].filter(Boolean).forEach((field) => {
					nativeDetailsFields.appendChild(field);
				});

				[
					['pmpro_saddress1_div', 'pmpro_saddress2_div'],
					['pmpro_scountry_div', 'pmpro_scity_div'],
					['pmpro_sstate_div', 'pmpro_szipcode_div'],
					[checkoutPhoneField, tshirtField],
				].forEach(buildNativeTwoUpRow);

				Array.from(nativeDetailsFields.querySelectorAll('.pmpro_cols-2')).forEach((row) => {
					if (!row.children.length) {
						row.remove();
					}
				});

				nativeDetailsFields.dataset.aacNativeRowsBuilt = '1';
			}

			if (nativeDetailFields.length) {
				billingFieldset.dataset.aacLegacyBillingBridge = 'true';
				billingFieldset.hidden = true;
				billingFieldset.style.display = 'none';
				billingFields.querySelectorAll('input, select, textarea').forEach((control) => {
					control.required = false;
					control.removeAttribute('required');
					control.removeAttribute('aria-required');
					control.classList.remove('pmpro_form_input-required');
				});
				nativeDetailsFields?.querySelectorAll('input, select, textarea').forEach((control) => {
					if (control.dataset.aacNativeBillingBridgeBound === 'true') {
						return;
					}
					control.addEventListener('input', syncNativePmproMemberFieldsToLegacyBilling);
					control.addEventListener('change', syncNativePmproMemberFieldsToLegacyBilling);
					control.dataset.aacNativeBillingBridgeBound = 'true';
				});
				} else {
					billingFieldset.dataset.aacNativeMemberInfo = 'true';
					const billingHeading = billingFieldset.querySelector('.pmpro_form_heading');
					if (billingHeading) {
						billingHeading.textContent = 'Member Information';
					}
				}
				const shippingSameAsBillingField = document.getElementById('pmproship_same_billing_address_div');
				if (shippingSameAsBillingField) {
					shippingSameAsBillingField.hidden = true;
					shippingSameAsBillingField.style.display = 'none';
					shippingSameAsBillingField.querySelectorAll('input, select, textarea').forEach((control) => {
						if (control.type === 'hidden') {
							return;
						}
						control.required = false;
						control.removeAttribute('required');
						control.removeAttribute('aria-required');
					});
				}
				const shippingFieldset = document.getElementById('pmpro_form_fieldset-pmproship');
				if (shippingFieldset) {
					shippingFieldset.hidden = true;
					shippingFieldset.style.display = 'none';
					shippingFieldset.querySelectorAll('input, select, textarea').forEach((control) => {
						if (control.type === 'hidden') {
							return;
						}
						control.required = false;
						control.removeAttribute('required');
						control.removeAttribute('aria-required');
					});
				}
				document.querySelectorAll('.pmpro_form_fieldset').forEach((fieldset) => {
					if (fieldset === nativeDetailsFieldset || fieldset === billingFieldset) {
						return;
				}
				const headingText = (fieldset.querySelector('.pmpro_form_heading, legend, h2, h3')?.textContent || '').trim();
				const fieldsContainer = fieldset.querySelector('.pmpro_form_fields');
				if (fieldsContainer && !fieldsContainer.children.length && /mailing address|member information/i.test(headingText)) {
					fieldset.remove();
				}
			});
			syncNativePmproMemberFieldsToLegacyBilling();

			if (personalDetailsFieldset) {
				const personalFields = personalDetailsFieldset.querySelector('.pmpro_form_fields');
				if (!personalFields || !personalFields.children.length) {
					personalDetailsFieldset.remove();
				}
			}

			const memberPreferencesFieldset =
				document.getElementById('pmpro_form_fieldset-publication-preferences') ||
				document.getElementById('pmpro_form_fieldset-member-preferences') ||
				document.getElementById('pmpro_form_fieldset-more-information');
			const memberPreferencesFields = memberPreferencesFieldset?.querySelector('.pmpro_form_fields');
			const memberPreferencesHeading = memberPreferencesFieldset?.querySelector('.pmpro_form_heading');

			if (memberPreferencesFieldset && memberPreferencesHeading) {
				memberPreferencesHeading.textContent = 'Publication Preferences';
			}

			const moreInformationFieldset = document.getElementById('pmpro_form_fieldset-more-information');
			if (
				moreInformationFieldset &&
				memberPreferencesFieldset &&
				moreInformationFieldset !== memberPreferencesFieldset &&
				memberPreferencesFields
			) {
				const moreInformationFields = moreInformationFieldset.querySelector('.pmpro_form_fields');
				if (moreInformationFields) {
					Array.from(moreInformationFields.children).forEach((field) => {
						memberPreferencesFields.appendChild(field);
					});
				}
				moreInformationFieldset.remove();
			}

			const discountFieldset = document.getElementById('pmpro_form_fieldset-membership-discounts');
			const familyFieldset = document.getElementById('pmpro_form_fieldset-partner-family');

			if (memberPreferencesFieldset?.parentNode && billingFieldset.parentNode === memberPreferencesFieldset.parentNode) {
				billingFieldset.parentNode.insertBefore(memberPreferencesFieldset, billingFieldset.nextSibling);
			}

		const magazineFieldset = document.getElementById('pmpro_form_fieldset-magazine-addons');
		if (magazineFieldset) {
			magazineFieldset.hidden = true;
			magazineFieldset.style.display = 'none';
		}

		const levelInput = document.getElementById('pmpro_level');
		const currentLevelId = Number.parseInt(levelInput?.value || '0', 10) || 0;
			if (discountFieldset) {
				const showMembershipDiscounts = currentLevelSupportsDiscountTiers();
				discountFieldset.hidden = !showMembershipDiscounts;
				discountFieldset.style.display = showMembershipDiscounts ? '' : 'none';
			if (!showMembershipDiscounts) {
				discountFieldset.querySelectorAll('input[name="aac_membership_discount"]').forEach((input) => {
					input.checked = false;
					input.removeAttribute('checked');
				});
			}
		}

		const familyAccountFieldset = document.getElementById('pmprogroupacct_parent_fields');
		if (familyAccountFieldset) {
			familyAccountFieldset.hidden = true;
			familyAccountFieldset.style.display = 'none';
		}

		buildMemberPreferenceCards(memberPreferencesFieldset, currentLevelId);

			const donationFieldset = document.getElementById('pmpro_form_fieldset-donation');
			const autoRenewFieldset = document.getElementById('pmpro_autorenewal_checkbox');
			const paymentInformationFieldset = document.getElementById('pmpro_payment_information_fields');
			const checkoutSummary = document.querySelector('[data-aac-magazine-summary]');
			const autoRenewHeading = autoRenewFieldset?.querySelector('.pmpro_form_heading');
			const nativeDiscountCodePrompt = document.getElementById('other_discount_code_p');
			const nativeDiscountCodeFields = document.getElementById('other_discount_code_fields');
			const nativeDiscountCodePaymentField = document.querySelector('.pmpro_payment-discount-code')?.closest('.pmpro_cols-2') || document.querySelector('.pmpro_payment-discount-code');

			if (autoRenewHeading) {
				autoRenewHeading.textContent = 'Automatic Renewals';
			}

			[nativeDiscountCodePrompt, nativeDiscountCodeFields, nativeDiscountCodePaymentField].forEach((node) => {
				if (!node) {
					return;
				}
				node.hidden = true;
				node.style.display = 'none';
			});

			if (paymentInformationFieldset?.parentNode) {
				const checkoutSectionParent = paymentInformationFieldset.parentNode;
				const paymentLegend = paymentInformationFieldset.querySelector('.pmpro_form_legend');

				if (paymentLegend) {
					paymentLegend.remove();
				}

				if (familyFieldset && familyFieldset.parentNode === checkoutSectionParent) {
					checkoutSectionParent.insertBefore(familyFieldset, paymentInformationFieldset);
				}

				if (donationFieldset && donationFieldset.parentNode === checkoutSectionParent) {
					checkoutSectionParent.insertBefore(donationFieldset, paymentInformationFieldset);
				}

				if (autoRenewFieldset && autoRenewFieldset.parentNode === checkoutSectionParent) {
					checkoutSectionParent.insertBefore(autoRenewFieldset, paymentInformationFieldset);
				}

				if (discountFieldset && discountFieldset.parentNode === checkoutSectionParent) {
					const discountAnchor = checkoutSummary && checkoutSummary.parentNode === checkoutSectionParent
						? checkoutSummary
						: paymentInformationFieldset;
					checkoutSectionParent.insertBefore(discountFieldset, discountAnchor);
				}

				if (checkoutSummary) {
					checkoutSectionParent.insertBefore(checkoutSummary, paymentInformationFieldset);
				}
			}

		};

	const syncMagazineAddonSummary = () => {
			const fieldset = document.getElementById('pmpro_form_fieldset-magazine-addons');
			const checkboxInputs = fieldset
				? Array.from(fieldset.querySelectorAll('input[name="aac_magazine_addons[]"]'))
				: [];

		const basePrice = getCurrentCheckoutBasePrice()
			?? (Number.parseFloat(fieldset?.dataset?.aacMagazineBasePrice || '0') || 0);
			const addonTotal = checkboxInputs.reduce((total, input) => {
				if (!input.checked) {
					return total;
				}

				return total + (Number.parseFloat(input.dataset.aacMagazinePrice || '0') || 0);
			}, 0);
		const summary = document.querySelector('[data-aac-magazine-summary]');
		const currentLevelId = getCurrentCheckoutLevelId();
		const membershipName = getCurrentCheckoutLevelName();
		const familyModeValue = String(
			document.querySelector('input[name="aac_partner_family_mode"]')?.value ||
			document.querySelector('input[name="aac_partner_family_mode"]:checked')?.value ||
			''
		).trim();
		const familyMode = familyModeValue === 'family' ? 'family' : '';
		const familyFieldset = document.getElementById('pmpro_form_fieldset-partner-family');
		const familyAdultInput = document.getElementById('aac_partner_family_additional_adult');
		const familyDependentsInput = document.getElementById('aac_partner_family_dependents');
		const familyAdultPrice = Number.parseFloat(familyFieldset?.dataset.aacPartnerFamilyAdultPrice || '0') || 0;
		const familyDependentPrice = Number.parseFloat(familyFieldset?.dataset.aacPartnerFamilyDependentPrice || '0') || 0;
		const familyAdultAmount = familyMode === 'family' && familyAdultInput?.checked ? familyAdultPrice : 0;
		const familyDependentCount = familyMode === 'family' ? Math.max(0, Number.parseInt(familyDependentsInput?.value || '0', 10) || 0) : 0;
		const familyDependentsAmount = familyDependentCount * familyDependentPrice;
		const selectedDiscountInput = document.querySelector('input[name="aac_membership_discount"]:checked');
		const donationAmount = Math.max(0, Number.parseFloat(document.getElementById('donation')?.value || '0') || 0);
		const readPublicationPreferenceValue = (fallbackSelector) => {
			const fallbackValue = (document.querySelector(fallbackSelector)?.value || '').trim();
			return fallbackValue === 'Print' ? 'Print' : 'Digital';
		};
		const countryValue = getCheckoutCountryValue();
		const isInternationalCountry = !['', 'US', 'USA', 'UNITED STATES', 'UNITED STATES OF AMERICA'].includes(countryValue);
		const hasPrintPublicationSelection = [
			readPublicationPreferenceValue('#aaj_preference_div select'),
			readPublicationPreferenceValue('#anac_preference_div select'),
			readPublicationPreferenceValue('#american_climbing_journal_preference_div select'),
			readPublicationPreferenceValue('#guidebook_preferences_div select'),
		].includes('Print');
		const internationalSurcharge = currentLevelId === 3 && isInternationalCountry && hasPrintPublicationSelection ? 30 : 0;
		const selectedAddons = checkboxInputs
			.filter((input) => input.checked)
				.map((input) => ({
					label: input.closest('.aac-magazine-addons__card')?.querySelector('.aac-magazine-addons__copy strong')?.textContent?.trim() || 'Magazine subscription',
					amount: Number.parseFloat(input.dataset.aacMagazinePrice || '0') || 0,
			}));
		const pmproMembershipAmount = getPmproMembershipAmount(basePrice);
		const promoDiscountCode = getDiscountCodeState();
		const hasAppliedMembershipDiscount = Boolean(selectedDiscountInput || promoDiscountCode);
		const showsProratedMembershipAmount = !hasAppliedMembershipDiscount
			&& Number.isFinite(pmproMembershipAmount)
			&& pmproMembershipAmount >= 0
			&& Math.abs(basePrice - pmproMembershipAmount) >= 0.01;
		const membershipLineAmount = showsProratedMembershipAmount ? pmproMembershipAmount : basePrice;
		const promoDiscountAmount = hasAppliedMembershipDiscount
			? Math.max(0, Math.round((basePrice - pmproMembershipAmount) * 100) / 100)
			: 0;
		const membershipDiscountLabel = selectedDiscountInput
			? getMembershipDiscountLabelForCode(selectedDiscountInput.dataset.aacMembershipDiscountCode)
			: getMembershipDiscountLabelForCode(promoDiscountCode);
		const promoDiscountLabel = membershipDiscountLabel || (promoDiscountCode ? `Promo code (${promoDiscountCode})` : 'Promo code discount');
		const membershipSummaryLabel = showsProratedMembershipAmount
			? `${buildMembershipLineItemLabel(membershipName)} (prorated amount due today)`
			: getProratedMembershipSummaryLabel(membershipName);
		const lineItems = [
			{ label: membershipSummaryLabel, amount: membershipLineAmount },
			...(promoDiscountAmount > 0 ? [{ label: promoDiscountLabel, amount: 0 - promoDiscountAmount, isDiscount: true }] : []),
			...(familyAdultAmount > 0 ? [{ label: 'Additional adult', amount: familyAdultAmount }] : []),
			...(familyDependentsAmount > 0 ? [{ label: `${familyDependentCount} ${familyDependentCount === 1 ? 'dependent' : 'dependents'}`, amount: familyDependentsAmount }] : []),
			...(internationalSurcharge > 0 ? [{ label: 'International surcharge for print copies', amount: internationalSurcharge }] : []),
			...(donationAmount > 0 ? [{ label: 'Donation', amount: donationAmount }] : []),
			...selectedAddons,
		];
		const grandTotal = lineItems.reduce((total, item) => total + (Number.isFinite(item.amount) ? item.amount : 0), 0);
		if (summary) {
			const isBillingUpdate = document.body.classList.contains('pmpro-billing');
			summary.innerHTML = `
				<div class="aac-magazine-addons__summary-header">
					<p class="aac-magazine-addons__summary-title">${isBillingUpdate ? 'Update your payment method' : 'Order summary'}</p>
					<p class="aac-magazine-addons__summary-caption">${isBillingUpdate ? 'Enter your new card details below. Saving this form will replace the card used for future membership payments. You will not be charged today.' : 'Review everything included before entering payment details.'}</p>
				</div>
				${buildDiscountCodeMarkup()}
				${isBillingUpdate ? '' : `<div class="aac-magazine-addons__summary-rows">
					${lineItems.map((item) => `
						<div class="aac-magazine-addons__summary-row${item.isDiscount ? ' aac-magazine-addons__summary-row--discount' : ''}">
							<span>${item.label}</span>
							<strong>${formatUsd(item.amount)}</strong>
						</div>
					`).join('')}
						<div class="aac-magazine-addons__summary-row aac-magazine-addons__summary-row--total">
							<span>Grand total</span>
							<strong>${formatUsd(grandTotal)}</strong>
					</div>
				</div>`}
			`;
			bindDiscountCodeForm(summary);
		}

			const priceText = document.querySelector('#pmpro_level_cost .pmpro_level-price');
			if (priceText) {
				const baseText = priceText.dataset.aacBaseText || (priceText.textContent || '').trim();
				if (!priceText.dataset.aacBaseText) {
					priceText.dataset.aacBaseText = baseText;
				}

				priceText.textContent = baseText;

				let note = document.getElementById('aac-magazine-total-note');
				if (note) {
					note.remove();
				}
			}

		checkboxInputs.forEach((input) => {
			if (input.dataset.aacMagazineBound === 'true') {
				return;
			}

			input.addEventListener('change', syncMagazineAddonSummary);
			input.dataset.aacMagazineBound = 'true';
		});

		document.querySelectorAll('input[name="aac_membership_discount"]').forEach((input) => {
			if (input.dataset.aacMembershipDiscountBound === 'true') {
				return;
			}

			input.addEventListener('change', syncMagazineAddonSummary);
			input.dataset.aacMembershipDiscountBound = 'true';
		});

		if (familyAdultInput && familyAdultInput.dataset.aacPartnerFamilyBound !== 'true') {
			familyAdultInput.addEventListener('change', syncMagazineAddonSummary);
			familyAdultInput.dataset.aacPartnerFamilyBound = 'true';
		}

		if (familyDependentsInput && familyDependentsInput.dataset.aacPartnerFamilyBound !== 'true') {
			familyDependentsInput.addEventListener('change', syncMagazineAddonSummary);
			familyDependentsInput.dataset.aacPartnerFamilyBound = 'true';
		}

		const countryField = getCheckoutCountryControl();
		if (countryField && countryField.dataset.aacOrderSummaryBound !== 'true') {
			countryField.addEventListener('change', syncMagazineAddonSummary);
			countryField.dataset.aacOrderSummaryBound = 'true';
		}

		document.querySelectorAll('#publications_preference_div select, #aaj_preference_div select, #anac_preference_div select, #american_climbing_journal_preference_div select, #guidebook_preferences_div select').forEach((select) => {
			if (select.dataset.aacOrderSummaryBound === 'true') {
				return;
			}

			select.addEventListener('change', syncMagazineAddonSummary);
			select.dataset.aacOrderSummaryBound = 'true';
		});

		const nativeDiscountMessage = getNativeDiscountCodeMessage();
		if (nativeDiscountMessage && nativeDiscountMessage.dataset.aacSummaryObserved !== 'true') {
			new MutationObserver(() => {
				window.setTimeout(syncMagazineAddonSummary, 50);
			}).observe(nativeDiscountMessage, {
				childList: true,
				subtree: true,
				characterData: true,
				attributes: true,
			});
			nativeDiscountMessage.dataset.aacSummaryObserved = 'true';
		}

		const priceContainer = document.getElementById('pmpro_level_cost');
		if (priceContainer && priceContainer.dataset.aacSummaryObserved !== 'true') {
			new MutationObserver(() => {
				window.setTimeout(syncMagazineAddonSummary, 50);
			}).observe(priceContainer, {
				childList: true,
				subtree: true,
				characterData: true,
			});
			priceContainer.dataset.aacSummaryObserved = 'true';
		}
	};

	const applyNativeDiscountCode = (nextCode, membershipCode = false) => {
		const normalizedCode = String(nextCode || '').trim();
		window.__aacAppliedDiscountCode = normalizedCode;
		if (membershipCode) {
			window.__aacMembershipDiscountCode = normalizedCode;
		} else if (!normalizedCode) {
			window.__aacMembershipDiscountCode = '';
		}

		getNativeDiscountCodeInputs().forEach((nativeInput) => {
			nativeInput.value = normalizedCode;
		});

		const form = getPmproCheckoutForm();
		['discount_code', 'pmpro_discount_code', 'other_discount_code'].forEach((name) => {
			if (!normalizedCode) {
				removeGeneratedCheckoutHiddenInput(form, name);
				return;
			}

			const input = ensureCheckoutHiddenInput(form, name);
			if (input) {
				input.value = normalizedCode;
			}
		});

		suppressDiscountMessageSync();
		getNativeDiscountCodeButton()?.click();
		[80, 250, 600, 1200].forEach((delay) => {
			window.setTimeout(scrubPmproDiscountMessages, delay);
		});
		window.setTimeout(syncMagazineAddonSummary, 250);
		window.setTimeout(syncMagazineAddonSummary, 900);
	};

		const syncMembershipDiscountCodeSelection = (selectedInput) => {
			const selectedCode = selectedInput?.checked ? String(selectedInput.dataset.aacMembershipDiscountCode || '').trim() : '';
			if (selectedCode) {
				if (normalizeDiscountCode(getDiscountCodeState()) !== normalizeDiscountCode(selectedCode)) {
					applyNativeDiscountCode(selectedCode, true);
			}
			return;
		}

		const currentCode = normalizeDiscountCode(getDiscountCodeState());
		const previousMembershipCode = normalizeDiscountCode(window.__aacMembershipDiscountCode);
		if (currentCode && (currentCode === previousMembershipCode || getMembershipDiscountLabelForCode(currentCode))) {
				applyNativeDiscountCode('', false);
			}
		};

		const syncSelectedMembershipDiscountForSubmit = () => {
			const form = getPmproCheckoutForm();
			if (!form) {
				return;
			}

			const selectedInput = document.querySelector('input[name="aac_membership_discount"][data-aac-toggleable-choice="true"]:checked');
			const selectedCode = selectedInput ? String(selectedInput.dataset.aacMembershipDiscountCode || '').trim() : '';
			const selectedValue = selectedInput ? String(selectedInput.value || '').trim() : '';
			const presentInput = ensureCheckoutHiddenInput(form, 'aac_membership_discount_present');
			const typeInput = ensureCheckoutHiddenInput(form, 'aac_membership_discount');
			if (presentInput) {
				presentInput.value = selectedInput ? '1' : '';
			}
			if (typeInput) {
				typeInput.value = selectedValue;
			}

			['discount_code', 'pmpro_discount_code', 'other_discount_code'].forEach((name) => {
				if (!selectedCode) {
					removeGeneratedCheckoutHiddenInput(form, name);
					return;
				}

				const input = ensureCheckoutHiddenInput(form, name);
				if (input) {
					input.value = selectedCode;
				}
			});

			if (selectedCode) {
				window.__aacAppliedDiscountCode = selectedCode;
				window.__aacMembershipDiscountCode = selectedCode;
			}
		};

		const syncCheckoutAutoRenewForSubmit = () => {
			const fieldset = document.getElementById('pmpro_autorenewal_checkbox');
			const checkbox = fieldset?.querySelector('input[type="checkbox"][name="autorenew"]');
			const toggle = fieldset?.querySelector('[data-aac-checkout-autorenew-toggle]');
			const presentInput = fieldset?.querySelector('input[type="hidden"][name="autorenew_present"]');
			if (!checkbox || !toggle) {
				return;
			}

			checkbox.checked = Boolean(toggle.checked);
			if (checkbox.checked) {
				checkbox.setAttribute('checked', 'checked');
			} else {
				checkbox.removeAttribute('checked');
			}
			if (presentInput) {
				presentInput.value = '1';
			}
		};

		const setTShirtToNoSelection = () => {
			const tshirtField = document.getElementById('t_shirt_div');
			const tshirtControl = tshirtField?.querySelector('select, input:not([type="checkbox"]):not([type="radio"])');
			const noShirtOption = Array.from(tshirtControl?.options || []).find((option) => /no\s*t-?shirt|none/i.test(option.textContent || option.value || ''));
			if (tshirtControl) {
				tshirtControl.value = noShirtOption?.value || 'No T-shirt';
				tshirtControl.dispatchEvent(new Event('change', { bubbles: true }));
			}
		};

		const setPublicationPreferencesToDigital = () => {
			document.querySelectorAll('#publications_preference_div select, #aaj_preference_div select, #anac_preference_div select, #american_climbing_journal_preference_div select, #guidebook_preferences_div select').forEach((select) => {
				select.value = 'Digital';
				select.dispatchEvent(new Event('change', { bubbles: true }));
			});
		};

		const syncInternationalFulfillmentNotice = () => {
			const countryField = document.getElementById('pmpro_scountry_div') || document.getElementById('scountry_div') || document.getElementById('bcountry_div') || getCheckoutCountryControl()?.closest('.pmpro_form_field');
			if (!countryField) {
				return;
			}

			let notice = document.getElementById('aac-international-fulfillment-notice');
			if (!notice) {
				notice = document.createElement('p');
				notice.id = 'aac-international-fulfillment-notice';
				notice.className = 'pmpro_message aac-international-fulfillment-notice';
				notice.textContent = 'International members do not receive a t-shirt or print publication.';
				countryField.insertAdjacentElement('afterend', notice);
			}

			const selectedCountry = getCheckoutCountryValue();
			const showNotice = selectedCountry !== '' && !isCheckoutCountryUS();
			notice.hidden = !showNotice;
			notice.style.display = showNotice ? '' : 'none';
		};

		const clearInternationalRestrictedCheckoutOptions = () => {
			document.querySelectorAll('input[name="aac_membership_discount"]').forEach((input) => {
				input.checked = false;
				input.removeAttribute('checked');
			});
			syncMembershipDiscountCodeSelection(null);

			const familyShortcut = document.getElementById('aac_partner_family_shortcut');
			const modeInput = document.getElementById('aac_partner_family_mode');
			const familyAdultInput = document.getElementById('aac_partner_family_additional_adult');
			const familyDependentsInput = document.getElementById('aac_partner_family_dependents');
			if (familyShortcut) {
				familyShortcut.checked = false;
				familyShortcut.removeAttribute('checked');
			}
			if (modeInput) {
				modeInput.value = '';
			}
			if (familyAdultInput) {
				familyAdultInput.checked = false;
				familyAdultInput.removeAttribute('checked');
			}
			if (familyDependentsInput) {
				familyDependentsInput.value = '0';
				familyDependentsInput.dispatchEvent(new Event('change', { bubbles: true }));
			}

			setPublicationPreferencesToDigital();

			setTShirtToNoSelection();
			setFamilyFieldsetVisibility(false);
		};

		const syncCountryLimitedSignupOptions = () => {
			syncCountryRoutedPartnerLevel();
			syncInternationalFulfillmentNotice();
			const isUS = isCheckoutCountryUS();
			const tshirtField = document.getElementById('t_shirt_div');
			const discountFieldset = document.getElementById('pmpro_form_fieldset-membership-discounts');
			const familyFieldset = document.getElementById('pmpro_form_fieldset-partner-family');
			const memberPreferencesFieldset =
				document.getElementById('pmpro_form_fieldset-publication-preferences') ||
				document.getElementById('pmpro_form_fieldset-member-preferences') ||
				document.getElementById('pmpro_form_fieldset-more-information');

			if (tshirtField) {
				const showTshirt = isUS && getCurrentCheckoutLevelId() >= 2;
				tshirtField.hidden = !showTshirt;
				tshirtField.style.display = showTshirt ? '' : 'none';
				if (!showTshirt) {
					setTShirtToNoSelection();
				}
			}

			if (memberPreferencesFieldset) {
				const showPublications = isUS && getCurrentCheckoutLevelId() > 2;
				memberPreferencesFieldset.hidden = !showPublications;
				memberPreferencesFieldset.style.display = showPublications ? '' : 'none';
				if (!showPublications) {
					setPublicationPreferencesToDigital();
				}
			}

			if (!isUS) {
				clearInternationalRestrictedCheckoutOptions();
			}

			if (discountFieldset) {
				const showDiscounts = isUS && currentLevelSupportsDiscountTiers();
				discountFieldset.hidden = !showDiscounts;
				discountFieldset.style.display = showDiscounts ? '' : 'none';
			}

			if (familyFieldset && !isUS) {
				familyFieldset.hidden = true;
				familyFieldset.style.display = 'none';
			}

			syncConditionalDiscountDetailFields();
			syncMagazineAddonSummary();
		};

		const bindCountryLimitedSignupOptions = () => {
			const countryField = getCheckoutCountryControl();
			if (countryField && countryField.dataset.aacCountryLimitedSignupBound !== 'true') {
				countryField.addEventListener('change', syncCountryLimitedSignupOptions);
				countryField.dataset.aacCountryLimitedSignupBound = 'true';
			}
			syncCountryLimitedSignupOptions();
		};

	const getSelectedMembershipDiscountType = () => {
		const selectedInput = document.querySelector('input[name="aac_membership_discount"]:checked');
		const rawValue = selectedInput?.value || selectedInput?.dataset?.aacMembershipDiscountLabel || '';
		return String(rawValue).trim().toLowerCase();
	};

			const findDiscountDetailField = (fieldset, selectors, labelPattern) => {
				for (const selector of selectors) {
					const field = fieldset?.querySelector(selector) || document.querySelector(selector);
					if (field) {
						return field;
			}
		}

		const roots = [fieldset, document].filter(Boolean);
		for (const root of roots) {
			const match = Array.from(root.querySelectorAll('.pmpro_form_field')).find((field) => {
			const labelText = (field.querySelector('label')?.textContent || '').trim();
			return labelPattern.test(labelText);
			});
			if (match) {
				return match;
			}
		}

		return null;
			};

		const findStudentUniversityField = (fieldset) => {
			const roots = [fieldset, document].filter(Boolean);

			const candidates = roots.flatMap((root) => Array.from(root.querySelectorAll('.pmpro_form_field')).filter((field) => {
				const labelText = (field.querySelector('label')?.textContent || '').trim();
				return field.querySelector('input[name="student_university"], input[id="student_university"], input[name="university_or_school"], input[id="university_or_school"]') || /university|school/i.test(labelText);
			}));
			const pmproField = candidates.find((field) => field.dataset.aacSyntheticStudentUniversityField !== 'true');
			const syntheticField = candidates.find((field) => field.dataset.aacSyntheticStudentUniversityField === 'true');

		if (pmproField && syntheticField && pmproField !== syntheticField) {
			syntheticField.remove();
		}

			return pmproField || syntheticField || null;
		};

		const moveDiscountDetailFieldsToCheckoutDiscountArea = () => {
			const fieldset = document.getElementById('pmpro_form_fieldset-discount-fields');
			const discountFieldset = document.getElementById('pmpro_form_fieldset-membership-discounts');
			const paymentInformationFieldset = document.getElementById('pmpro_payment_information_fields');
			const paymentParent = paymentInformationFieldset?.parentNode || discountFieldset?.parentNode || fieldset?.parentNode;
			if (!fieldset || !paymentParent) {
				return;
			}

			let detailContainer = document.querySelector('[data-aac-checkout-discount-details]');
			if (!detailContainer) {
				detailContainer = document.createElement('div');
				detailContainer.className = 'aac-checkout-discount-detail-fields';
				detailContainer.dataset.aacCheckoutDiscountDetails = 'true';
			}

			if (discountFieldset?.parentNode) {
				discountFieldset.insertAdjacentElement('afterend', detailContainer);
			} else if (paymentInformationFieldset?.parentNode) {
				paymentInformationFieldset.parentNode.insertBefore(detailContainer, paymentInformationFieldset);
			} else if (detailContainer.parentElement !== paymentParent) {
				paymentParent.appendChild(detailContainer);
			}

			const heading = fieldset.querySelector('.pmpro_form_legend, .pmpro_form_heading');
			if (heading) {
				heading.hidden = true;
				heading.style.display = 'none';
			}

			const serviceField = findDiscountDetailField(fieldset, [
					'#service_branch_div',
					'#service_component_div',
					'#military_service_component_div',
					'.pmpro_form_field-service_branch',
					'.pmpro_form_field-service_component',
				], /service\s*(component|branch)|military/i);
			const graduationField = findDiscountDetailField(fieldset, [
					'#graduation_date_div',
					'#student_graduation_date_div',
					'.pmpro_form_field-graduation_date',
					'.pmpro_form_field-student_graduation_date',
				], /graduation/i);
			const studentUniversityField = findStudentUniversityField(fieldset);

			const studentFields = [graduationField, studentUniversityField].filter(Boolean);
			if (studentFields.length) {
				let studentRow = detailContainer.querySelector(':scope > .aac-contact-discount-detail-row');
				if (!studentRow) {
					studentRow = document.createElement('div');
					studentRow.className = 'aac-contact-discount-detail-row';
					detailContainer.appendChild(studentRow);
				}

				studentRow.hidden = true;
				studentRow.style.display = 'none';
				studentFields.forEach((field) => {
					field.classList.add('aac-contact-discount-detail-field');
					if (field === graduationField) {
						field.classList.add('aac-contact-discount-detail-field--graduation');
					}
					if (field === studentUniversityField) {
						field.classList.add('aac-contact-discount-detail-field--university');
					}
					if (field.parentElement !== studentRow) {
						studentRow.appendChild(field);
					}
				});
				}

			if (serviceField) {
				serviceField.classList.add('aac-contact-discount-detail-field', 'aac-contact-discount-detail-field--service');
				if (serviceField.parentElement !== detailContainer) {
					detailContainer.appendChild(serviceField);
				}
			}

			fieldset.hidden = true;
			fieldset.style.display = 'none';
		};

	const setDiscountDetailFieldVisibility = (field, visible) => {
		if (!field) {
			return;
		}

		field.hidden = !visible;
		field.style.display = visible ? '' : 'none';
		field.querySelectorAll('input, select, textarea').forEach((control) => {
			if (control.dataset.aacDiscountOriginalRequired === undefined) {
				control.dataset.aacDiscountOriginalRequired = control.required ? 'true' : 'false';
			}
			if (control.dataset.aacDiscountOriginalDisabled === undefined) {
				control.dataset.aacDiscountOriginalDisabled = control.disabled ? 'true' : 'false';
			}

			if (visible) {
				control.disabled = control.dataset.aacDiscountOriginalDisabled === 'true';
				control.required = control.dataset.aacDiscountOriginalRequired === 'true';
			} else {
				control.required = false;
				control.disabled = true;
			}
		});
	};

		const militaryServiceComponentOptions = ['Active', 'Reserve', 'Veteran', 'Retired'];

		const hydrateDiscountDetailSelectOptions = (field, fixedOptions = null) => {
			const select = field?.querySelector('select');
			if (!select) {
				return;
			}

			const hint = field.querySelector('.pmpro_form_hint');
			const optionLabels = Array.isArray(fixedOptions)
				? fixedOptions
				: (hint?.textContent || '')
					.split(/\r?\n/)
					.map((label) => label.trim())
					.filter(Boolean);
			if (!optionLabels.length) {
				return;
			}

			const currentValue = select.value;
			if (Array.isArray(fixedOptions)) {
				select.replaceChildren();
				const placeholder = document.createElement('option');
				placeholder.value = '';
				placeholder.textContent = 'Select service component';
				select.appendChild(placeholder);
			} else if (select.options.length > 1 || select.dataset.aacDiscountOptionsHydrated === 'true') {
				return;
			}

			optionLabels.forEach((label) => {
				const option = document.createElement('option');
				option.value = label;
				option.textContent = label;
				select.appendChild(option);
			});
			if (Array.isArray(fixedOptions)) {
				select.value = optionLabels.includes(currentValue) ? currentValue : '';
			}
			select.dataset.aacDiscountOptionsHydrated = 'true';
			if (hint) {
				hint.hidden = true;
				hint.style.display = 'none';
			}
		};

		let studentUniversityValueMap = new Map();
		let studentUniversityRequestSequence = 0;

		const searchStudentUniversities = (query) => {
			const normalizedQuery = normalizeStudentUniversitySearch(query);
			if (normalizedQuery.length < 2) {
				return Promise.resolve([]);
			}

			const requestUrl = new URL(studentUniversitySearchEndpoint);
			requestUrl.searchParams.set('q', query);
			requestUrl.searchParams.set('limit', '30');

			return fetch(requestUrl.toString(), { credentials: 'same-origin' })
				.then((response) => response.ok ? response.json() : Promise.reject(new Error('Unable to search universities')))
				.then((payload) => Array.isArray(payload?.schools) ? payload.schools : [])
				.catch(() => []);
		};

		const normalizeStudentUniversitySearch = (value) => String(value || '')
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, ' ')
			.trim();

		const formatStudentUniversityOption = (school) => {
			const name = String(school?.name || '').trim();
			const city = String(school?.city || '').trim();
			const state = String(school?.state || '').trim();
			const parent = String(school?.parent || '').trim();
			const location = [city, state].filter(Boolean).join(', ');
			const campusLabel = parent && parent !== name ? `${name} (${parent})` : name;
			return [campusLabel, location].filter(Boolean).join(' - ');
		};

		const ensureStudentUniversityIdInput = (input) => {
			let idInput = document.querySelector('input[name="student_university_id"]');
			if (!idInput) {
				idInput = document.createElement('input');
				idInput.type = 'hidden';
				idInput.name = 'student_university_id';
				idInput.id = 'student_university_id';
				input.insertAdjacentElement('afterend', idInput);
			}

			return idInput;
		};

		const ensureStudentUniversityDropdown = (input) => {
			const field = input?.closest('.aac-student-university-field, .pmpro_form_field') || input?.parentElement;
			if (!field) {
				return null;
			}

			field.classList.add('aac-student-university-field');
			let dropdown = field.querySelector('[data-aac-student-university-dropdown]');
			if (!dropdown) {
				dropdown = document.createElement('div');
				dropdown.className = 'aac-student-university-dropdown';
				dropdown.dataset.aacStudentUniversityDropdown = 'true';
				dropdown.setAttribute('role', 'listbox');
				dropdown.hidden = true;
				field.appendChild(dropdown);
			}

			return dropdown;
		};

		const hideStudentUniversityDropdown = (input) => {
			const dropdown = input?.closest('.aac-student-university-field, .pmpro_form_field')?.querySelector('[data-aac-student-university-dropdown]');
			if (dropdown) {
				dropdown.hidden = true;
			}
		};

		const renderStudentUniversityOptions = (input, schools) => {
			const dropdown = ensureStudentUniversityDropdown(input);
			const query = normalizeStudentUniversitySearch(input?.value || '');
			const matches = query.length >= 2 && Array.isArray(schools) ? schools.slice(0, 30) : [];

			studentUniversityValueMap = new Map();

			if (dropdown) {
				dropdown.replaceChildren();
			}

			const addDropdownOption = (label, schoolId = '') => {
				if (!dropdown) {
					return;
				}
				const optionButton = document.createElement('button');
				optionButton.type = 'button';
				optionButton.className = 'aac-student-university-dropdown__option';
				optionButton.textContent = label;
				optionButton.setAttribute('role', 'option');
				optionButton.addEventListener('mousedown', (event) => {
					event.preventDefault();
				});
				optionButton.addEventListener('click', () => {
					input.value = label;
					const idInput = ensureStudentUniversityIdInput(input);
					idInput.value = schoolId;
					hideStudentUniversityDropdown(input);
					input.dispatchEvent(new Event('change', { bubbles: true }));
				});
				dropdown.appendChild(optionButton);
			};

			addDropdownOption('Other / not listed', '');

			matches.forEach((school) => {
				const value = formatStudentUniversityOption(school);
				if (!value) {
					return;
				}
				studentUniversityValueMap.set(value, String(school.id || ''));
				addDropdownOption(value, String(school.id || ''));
			});

			if (dropdown) {
				if (!matches.length && query.length >= 1) {
					const empty = document.createElement('div');
					empty.className = 'aac-student-university-dropdown__empty';
					empty.textContent = 'No matching schools. Choose Other / not listed if needed.';
					dropdown.appendChild(empty);
				}
				dropdown.hidden = document.activeElement !== input || input.disabled;
			}
		};

		const createStudentUniversityField = (fieldset) => {
			if (!fieldset) {
				return null;
			}

			const existingField = findStudentUniversityField(fieldset);
			if (existingField) {
				return existingField;
			}

			const field = document.createElement('div');
			field.id = 'student_university_div';
			field.className = 'pmpro_form_field pmpro_form_field-text pmpro_form_field-student_university aac-student-university-field';
			field.dataset.aacSyntheticStudentUniversityField = 'true';
			field.innerHTML = `
				<label class="pmpro_form_label" for="university_or_school">University / School</label>
				<input id="university_or_school" name="university_or_school" type="text" class="pmpro_form_input pmpro_form_input-text aac-student-university-input" autocomplete="off" placeholder="Start typing your university" />
				<p class="pmpro_form_hint">Start typing your U.S. college or university. Choose Other / not listed if your school is not listed.</p>
			`;

			const graduationField = findDiscountDetailField(fieldset, [
				'#graduation_date_div',
				'#student_graduation_date_div',
				'.pmpro_form_field-graduation_date',
				'.pmpro_form_field-student_graduation_date',
			], /graduation/i);
			(graduationField || fieldset.querySelector('.pmpro_form_fields') || fieldset).insertAdjacentElement(graduationField ? 'afterend' : 'beforeend', field);

			return field;
		};

		const hydrateStudentUniversityField = (field) => {
			const input = field?.querySelector('input[name="university_or_school"], input[id="university_or_school"], input[name="student_university"], input[id="student_university"]');
			if (!input) {
				return;
			}

			field.classList.add('aac-student-university-field');
			input.removeAttribute('list');
			input.setAttribute('autocomplete', 'off');
			input.placeholder = input.placeholder || 'Start typing your university';
			input.style.backgroundColor = '#ffffff';
			input.style.color = '#030000';
			input.style.colorScheme = 'light';
			const idInput = ensureStudentUniversityIdInput(input);

			const syncSelectedId = () => {
				idInput.value = studentUniversityValueMap.get(input.value) || '';
			};

			const runStudentUniversitySearch = () => {
				const requestId = ++studentUniversityRequestSequence;
				searchStudentUniversities(input.value).then((schools) => {
					if (requestId !== studentUniversityRequestSequence) {
						return;
					}
					renderStudentUniversityOptions(input, schools);
					syncSelectedId();
				});
			};

			const scheduleStudentUniversitySearch = () => {
				window.clearTimeout(input._aacStudentUniversitySearchTimer);
				input._aacStudentUniversitySearchTimer = window.setTimeout(runStudentUniversitySearch, 180);
			};

			if (input.dataset.aacUniversityAutocompleteBound !== 'true') {
				input.addEventListener('input', () => {
					scheduleStudentUniversitySearch();
				});
				input.addEventListener('change', syncSelectedId);
				input.addEventListener('focus', () => {
					renderStudentUniversityOptions(input, []);
					runStudentUniversitySearch();
				});
				input.addEventListener('blur', () => {
					window.setTimeout(() => hideStudentUniversityDropdown(input), 140);
				});
				input.addEventListener('keydown', (event) => {
					if (event.key !== 'Enter') {
						return;
					}
					const dropdown = input.closest('.aac-student-university-field, .pmpro_form_field')?.querySelector('[data-aac-student-university-dropdown]');
					const firstOption = dropdown && !dropdown.hidden ? dropdown.querySelector('.aac-student-university-dropdown__option') : null;
					if (firstOption) {
						event.preventDefault();
						firstOption.click();
					}
				});
				input.dataset.aacUniversityAutocompleteBound = 'true';
			}

			renderStudentUniversityOptions(input, []);
		};

		const hydrateAllStudentUniversityFields = () => {
			document.querySelectorAll('input[name="university_or_school"], input[id="university_or_school"], input[name="student_university"], input[id="student_university"]').forEach((input) => {
				hydrateStudentUniversityField(input.closest('.pmpro_form_field') || input.parentElement);
			});
		};

		const syncConditionalDiscountDetailFields = () => {
			const fieldset = document.getElementById('pmpro_form_fieldset-discount-fields');
			if (!fieldset) {
				return;
		}

		moveDiscountDetailFieldsToCheckoutDiscountArea();

		const selectedDiscount = getSelectedMembershipDiscountType();
		const serviceField = findDiscountDetailField(fieldset, [
			'#service_branch_div',
			'#service_component_div',
			'#military_service_component_div',
			'.pmpro_form_field-service_branch',
			'.pmpro_form_field-service_component',
		], /service\s*(component|branch)|military/i);
			const graduationField = findDiscountDetailField(fieldset, [
				'#graduation_date_div',
				'#student_graduation_date_div',
				'.pmpro_form_field-graduation_date',
				'.pmpro_form_field-student_graduation_date',
			], /graduation/i);
			let studentUniversityField = findStudentUniversityField(fieldset);

			const showService = selectedDiscount === 'military';
			const showGraduation = selectedDiscount === 'student' && isCheckoutCountryUS() && currentLevelSupportsDiscountTiers();
			const showStudentUniversity = showGraduation;
			if (showStudentUniversity && !studentUniversityField) {
				studentUniversityField = createStudentUniversityField(fieldset);
				moveDiscountDetailFieldsToCheckoutDiscountArea();
			}
			hydrateDiscountDetailSelectOptions(serviceField, militaryServiceComponentOptions);
			hydrateStudentUniversityField(studentUniversityField);
			hydrateAllStudentUniversityFields();
			setDiscountDetailFieldVisibility(serviceField, showService);
			setDiscountDetailFieldVisibility(graduationField, showGraduation);
			setDiscountDetailFieldVisibility(studentUniversityField, showStudentUniversity);
			const detailContainer = document.querySelector('[data-aac-checkout-discount-details]');
			const studentDetailRow = detailContainer?.querySelector('.aac-contact-discount-detail-row') || document.querySelector('.aac-contact-discount-detail-row');
			if (studentDetailRow) {
				const showStudentDetailRow = showGraduation || showStudentUniversity;
				studentDetailRow.hidden = !showStudentDetailRow;
				studentDetailRow.style.display = showStudentDetailRow ? '' : 'none';
			}
			if (detailContainer) {
				const showDetailContainer = showService || showGraduation || showStudentUniversity;
				detailContainer.hidden = !showDetailContainer;
				detailContainer.style.display = showDetailContainer ? '' : 'none';
			}
			if (showStudentUniversity) {
				studentUniversityField?.querySelectorAll('input[name="university_or_school"], input[name="student_university"]').forEach((input) => {
					input.required = true;
				});
			}

			const shouldShowFieldset = showService || showGraduation || showStudentUniversity;
			fieldset.hidden = true;
			fieldset.style.display = 'none';
		};

		const bindStudentUniversityFieldObserver = () => {
			const fieldset = document.getElementById('pmpro_form_fieldset-discount-fields');
			if (!fieldset || fieldset.dataset.aacStudentUniversityObserverBound === 'true') {
				return;
			}

			let syncScheduled = false;
			const scheduleSync = () => {
				if (syncScheduled) {
					return;
				}
				syncScheduled = true;
				window.setTimeout(() => {
					syncScheduled = false;
					syncConditionalDiscountDetailFields();
					hydrateAllStudentUniversityFields();
				}, 0);
			};

			new MutationObserver(scheduleSync).observe(fieldset, {
				childList: true,
				subtree: true,
			});
			fieldset.dataset.aacStudentUniversityObserverBound = 'true';
			[100, 500, 1500].forEach((delay) => {
				window.setTimeout(() => {
					syncConditionalDiscountDetailFields();
					hydrateAllStudentUniversityFields();
				}, delay);
			});
			window.addEventListener('load', () => {
				[0, 500, 1500, 3000].forEach((delay) => {
					window.setTimeout(() => {
						syncConditionalDiscountDetailFields();
						hydrateAllStudentUniversityFields();
					}, delay);
				});
			}, { once: true });
		};

	const bindToggleableMembershipDiscounts = () => {
		const syncDiscountCardSelectedClasses = () => {
			document.querySelectorAll('.aac-membership-discounts__label').forEach((label) => {
				const input = label.querySelector('.aac-membership-discounts__input');
				const card = label.querySelector('.aac-membership-discounts__card');
				const selected = Boolean(input?.checked);
				label.classList.toggle('is-selected', selected);
				card?.classList.toggle('is-selected', selected);
				card?.querySelectorAll('.aac-membership-discounts__copy, .aac-membership-discounts__copy strong, .aac-membership-discounts__copy span, .aac-membership-discounts__price').forEach((node) => {
					node.style.color = '#16130f';
					node.style.webkitTextFillColor = '#16130f';
				});
				card?.querySelectorAll('.aac-membership-discounts__icon, .aac-membership-discounts__icon *').forEach((node) => {
					node.style.color = selected ? '#9e1b1e' : '#16130f';
					node.style.webkitTextFillColor = selected ? '#9e1b1e' : '#16130f';
				});
			});
		};

		syncDiscountCardSelectedClasses();

		document.querySelectorAll('input[name="aac_membership_discount"][data-aac-toggleable-choice="true"]').forEach((input) => {
			if (input.dataset.aacToggleableBound === 'true') {
				return;
			}

			const clearFamilySelection = () => {
				const familyShortcut = document.getElementById('aac_partner_family_shortcut');
				const modeInput = document.getElementById('aac_partner_family_mode');
				const familyFieldset = document.getElementById('pmpro_form_fieldset-partner-family');
				const details = document.querySelector('[data-aac-partner-family-details]');
				const familyAdultInput = document.getElementById('aac_partner_family_additional_adult');
				const familyDependentsInput = document.getElementById('aac_partner_family_dependents');

				if (familyShortcut) {
					familyShortcut.checked = false;
					familyShortcut.removeAttribute('checked');
				}

				if (modeInput) {
					modeInput.value = '';
				}

				if (familyFieldset) {
					familyFieldset.hidden = true;
					familyFieldset.style.display = 'none';
				}

				if (details) {
					details.hidden = true;
					details.style.display = 'none';
				}

				if (familyAdultInput) {
					familyAdultInput.checked = false;
					familyAdultInput.removeAttribute('checked');
				}

				if (familyDependentsInput) {
					familyDependentsInput.value = '0';
				}
			};

			const syncExclusiveDiscountSelection = () => {
				if (input.checked) {
					document.querySelectorAll(`input[name="${input.name}"]`).forEach((candidate) => {
						if (candidate === input) {
							candidate.setAttribute('checked', 'checked');
						} else {
							candidate.checked = false;
								candidate.removeAttribute('checked');
							}
						});

				clearFamilySelection();
				syncMembershipDiscountCodeSelection(input);
			} else {
				input.removeAttribute('checked');
				syncMembershipDiscountCodeSelection(null);
			}

			syncConditionalDiscountDetailFields();
			syncMagazineAddonSummary();
			syncDiscountCardSelectedClasses();
			};

			input.addEventListener('click', () => {
				window.setTimeout(syncExclusiveDiscountSelection, 0);
			});

			input.addEventListener('change', syncExclusiveDiscountSelection);

			input.dataset.aacToggleableBound = 'true';
		});
	};

	const setFamilyFieldsetVisibility = (active) => {
		const familyFieldset = document.getElementById('pmpro_form_fieldset-partner-family');
		const details = document.querySelector('[data-aac-partner-family-details]');
		if (!familyFieldset || !details) {
			return;
		}

		familyFieldset.hidden = !active;
		familyFieldset.style.display = active ? '' : 'none';
		details.hidden = !active;
		details.style.display = active ? 'grid' : 'none';
	};

	const enhanceFamilyDependentButtons = () => {
		const select = document.getElementById('aac_partner_family_dependents');
		if (!select) {
			return;
		}

		select.classList.add('aac-partner-family__dependent-select');
		let buttonGroup = select.parentElement?.querySelector('[data-aac-dependent-buttons]');
		if (!buttonGroup) {
			buttonGroup = document.createElement('div');
			buttonGroup.className = 'aac-partner-family__dependent-buttons';
			buttonGroup.dataset.aacDependentButtons = 'true';
			buttonGroup.setAttribute('role', 'group');
			buttonGroup.setAttribute('aria-label', 'Family dependents');
			Array.from(select.options).forEach((option) => {
				const button = document.createElement('button');
				button.type = 'button';
				button.className = 'aac-partner-family__dependent-button';
				button.dataset.aacDependentValue = option.value;
				button.textContent = option.textContent;
				button.addEventListener('click', () => {
					select.value = option.value;
					select.dispatchEvent(new Event('change', { bubbles: true }));
				});
				buttonGroup.appendChild(button);
			});
			select.insertAdjacentElement('afterend', buttonGroup);
		}

		const syncButtons = () => {
			buttonGroup.querySelectorAll('[data-aac-dependent-value]').forEach((button) => {
				const isSelected = button.dataset.aacDependentValue === select.value;
				button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
			});
		};

		if (select.dataset.aacDependentButtonsBound !== 'true') {
			select.addEventListener('change', syncButtons);
			select.dataset.aacDependentButtonsBound = 'true';
		}
		syncButtons();
	};

	const applyExternalFamilyDiscount = (familyConfig = {}) => {
		const active = familyConfig?.active === true;
		const dependentCount = Math.max(0, Math.min(3, Number.parseInt(familyConfig?.dependentCount ?? 0, 10) || 0));
		const shortcut = document.getElementById('aac_partner_family_shortcut');
		const modeInput = document.getElementById('aac_partner_family_mode');
		const familyAdultInput = document.getElementById('aac_partner_family_additional_adult');
		const familyDependentsInput = document.getElementById('aac_partner_family_dependents');

		if (shortcut) {
			shortcut.checked = active;
			if (active) {
				shortcut.setAttribute('checked', 'checked');
			} else {
				shortcut.removeAttribute('checked');
			}
			shortcut.dispatchEvent(new Event('change', { bubbles: true }));
		}

		if (modeInput) {
			modeInput.value = active ? 'family' : '';
		}

		if (familyAdultInput) {
			familyAdultInput.checked = active;
			if (active) {
				familyAdultInput.setAttribute('checked', 'checked');
			} else {
				familyAdultInput.removeAttribute('checked');
			}
			familyAdultInput.dispatchEvent(new Event('change', { bubbles: true }));
		}

		if (familyDependentsInput) {
			familyDependentsInput.value = active ? String(dependentCount) : '0';
			familyDependentsInput.dispatchEvent(new Event('change', { bubbles: true }));
		}

		setFamilyFieldsetVisibility(active);
		syncConditionalDiscountDetailFields();
		syncMagazineAddonSummary();
	};

	const applyExternalMembershipDiscount = (discountValue, familyConfig = {}) => {
		const normalizedValue = String(discountValue || '').trim().toLowerCase();
		const isFamilyDiscount = normalizedValue === 'family';
		const discountInputs = Array.from(document.querySelectorAll('input[name="aac_membership_discount"][data-aac-toggleable-choice="true"]'));

		if (isFamilyDiscount) {
			discountInputs.forEach((input) => {
				input.checked = false;
				input.removeAttribute('checked');
				input.dispatchEvent(new Event('change', { bubbles: true }));
			});
			applyExternalFamilyDiscount({
				...familyConfig,
				active: true,
			});
			return;
		}

		applyExternalFamilyDiscount({ active: false, dependentCount: 0 });

		const targetInput = normalizedValue
			? discountInputs.find((input) => {
				const inputValue = String(input.value || '').trim().toLowerCase();
				const inputLabel = String(input.dataset.aacMembershipDiscountLabel || '').trim().toLowerCase();
				return inputValue === normalizedValue || inputLabel.includes(normalizedValue);
			})
			: null;

		discountInputs.forEach((input) => {
			const shouldCheck = input === targetInput;
			input.checked = shouldCheck;
			if (shouldCheck) {
				input.setAttribute('checked', 'checked');
			} else {
				input.removeAttribute('checked');
			}
			input.dispatchEvent(new Event('change', { bubbles: true }));
		});

		syncConditionalDiscountDetailFields();
		syncMagazineAddonSummary();
	};

	const bindFamilySelectionShortcut = () => {
		const shortcut = document.getElementById('aac_partner_family_shortcut');
		const modeInput = document.getElementById('aac_partner_family_mode');
		const familyFieldset = document.getElementById('pmpro_form_fieldset-partner-family');
		const details = document.querySelector('[data-aac-partner-family-details]');
		if (!shortcut || !modeInput || !details || !familyFieldset) {
			return;
		}
		const familyAdultInput = document.getElementById('aac_partner_family_additional_adult');
		const familyDependentsInput = document.getElementById('aac_partner_family_dependents');

		const syncFamilyState = () => {
			const active = shortcut.checked;
			if (active) {
				document.querySelectorAll('input[name="aac_membership_discount"][data-aac-toggleable-choice="true"]').forEach((input) => {
					input.checked = false;
					input.removeAttribute('checked');
				});
				document.querySelectorAll('.aac-membership-discounts__label').forEach((label) => {
					const input = label.querySelector('.aac-membership-discounts__input');
					const card = label.querySelector('.aac-membership-discounts__card');
					const selected = input === shortcut;
					label.classList.toggle('is-selected', selected);
					card?.classList.toggle('is-selected', selected);
				});
				syncMembershipDiscountCodeSelection(null);
			} else {
				if (familyAdultInput) {
					familyAdultInput.checked = false;
					familyAdultInput.removeAttribute('checked');
				}
				if (familyDependentsInput) {
					familyDependentsInput.value = '0';
				}
			}
			modeInput.value = active ? 'family' : '';
			setFamilyFieldsetVisibility(active);
			document.querySelectorAll('.aac-membership-discounts__label').forEach((label) => {
				const input = label.querySelector('.aac-membership-discounts__input');
				const card = label.querySelector('.aac-membership-discounts__card');
				const selected = Boolean(input?.checked);
				label.classList.toggle('is-selected', selected);
				card?.classList.toggle('is-selected', selected);
			});
		};

		if (shortcut.dataset.aacFamilyShortcutBound !== 'true') {
			shortcut.addEventListener('change', () => {
				syncFamilyState();
				syncConditionalDiscountDetailFields();
				syncMagazineAddonSummary();
			});
			shortcut.dataset.aacFamilyShortcutBound = 'true';
		}

		syncFamilyState();
		syncConditionalDiscountDetailFields();
	};

	const enhancePublicationPreferenceCards = () => {
		const memberPreferencesFieldset =
			document.getElementById('pmpro_form_fieldset-publication-preferences') ||
			document.getElementById('pmpro_form_fieldset-member-preferences') ||
			document.getElementById('pmpro_form_fieldset-more-information');
		if (!memberPreferencesFieldset) {
			return;
		}

		const serverBlock =
			memberPreferencesFieldset.querySelector('.aac-server-member-preferences') ||
			document.querySelector('.aac-server-member-preferences');
		const targetFields = memberPreferencesFieldset.querySelector('.pmpro_form_fields');
		if (serverBlock && targetFields && !targetFields.contains(serverBlock)) {
			targetFields.prepend(serverBlock);
		}

		if (memberPreferencesFieldset.querySelector('.aac-server-member-preferences')) {
			memberPreferencesFieldset.querySelectorAll('#publications_preference_div select, #aaj_preference_div select, #anac_preference_div select, #american_climbing_journal_preference_div select, #guidebook_preferences_div select').forEach((select) => {
				select.disabled = true;
			});
			return;
		}

		const levelInput = document.getElementById('pmpro_level');
		const currentLevelId = Number.parseInt(levelInput?.value || '0', 10) || 0;
		buildMemberPreferenceCards(memberPreferencesFieldset, currentLevelId);
	};

	const syncStandaloneFamilyVisibility = () => {
		const shortcut = document.getElementById('aac_partner_family_shortcut');
		const modeInput = document.getElementById('aac_partner_family_mode');
		const familyFieldset = document.getElementById('pmpro_form_fieldset-partner-family');
		const details = document.querySelector('[data-aac-partner-family-details]');
		if (!shortcut || !modeInput || !familyFieldset || !details) {
			return;
		}

		const familyAdultInput = document.getElementById('aac_partner_family_additional_adult');
		const familyDependentsInput = document.getElementById('aac_partner_family_dependents');
		const active = shortcut.checked;
		modeInput.value = active ? 'family' : '';
			setFamilyFieldsetVisibility(active);

		if (!active) {
			if (familyAdultInput) {
				familyAdultInput.checked = false;
				familyAdultInput.removeAttribute('checked');
			}
			if (familyDependentsInput) {
				familyDependentsInput.value = '0';
			}
		}
	};

	const relabelTShirtSizeOptions = () => {
		const configuredOptions = getConfiguredTshirtSizeOptions();
		const configuredOptionMap = new Map(configuredOptions.map((option) => [option.value, option.label]));
		const allowedValues = new Set(configuredOptions.map((option) => option.value));
		const tshirtValueMap = {
			'none': 'No T-shirt',
			'no t-shirt': 'No T-shirt',
			'xs': 'Unisex X-Small',
			's': 'Unisex Small',
			'm': 'Unisex Medium',
			'l': 'Unisex Large',
			'xl': 'Unisex X-Large',
			'xxl': 'Unisex XX-Large',
			'2xl': 'Unisex XX-Large',
			'unisex x-small': 'Unisex X-Small',
			'unisex small': 'Unisex Small',
			'unisex medium': 'Unisex Medium',
			'unisex large': 'Unisex Large',
			'unisex x-large': 'Unisex X-Large',
			'unisex xx-large': 'Unisex XX-Large',
		};
		const normalizeTshirtValue = (value) => {
			const rawValue = String(value || '').trim();
			if (!rawValue) {
				return 'No T-shirt';
			}

			const lowered = rawValue.toLowerCase();
			if (tshirtValueMap[lowered]) {
				return tshirtValueMap[lowered];
			}

			if (lowered.startsWith('unisex ')) {
				const compact = lowered.replace(/^unisex\s+/, '').replace(/[\s-]+/g, '');
				return tshirtValueMap[compact] || 'No T-shirt';
			}

			return 'No T-shirt';
		};

		document.querySelectorAll('select[name="t_shirt"]').forEach((select) => {
			if (select.dataset.aacTshirtEnhanced !== 'true') {
				select.required = false;
				select.classList.remove('pmpro_form_input-required');

				const field = select.closest('.pmpro_form_field');
				field?.classList.remove('pmpro_form_field-required');
				field?.querySelector('.pmpro_asterisk')?.remove();

				select.querySelectorAll('option').forEach((option) => {
					if ((option.value || '').trim() === '') {
						option.remove();
						return;
					}

					const normalizedValue = normalizeTshirtValue(option.value || option.textContent || '');
					option.value = normalizedValue;
					option.textContent = configuredOptionMap.get(normalizedValue) || normalizedValue;
				});

				const seenValues = new Set();
				Array.from(select.options).forEach((option) => {
					if (seenValues.has(option.value)) {
						option.remove();
						return;
					}
					seenValues.add(option.value);
				});

				Array.from(select.options).forEach((option) => {
					if (!allowedValues.has(option.value)) {
						option.remove();
					}
				});

				const desiredTshirtValue = normalizeTshirtValue(checkoutProfileDefaults.size || 'No T-shirt');
				if (select.querySelector(`option[value="${desiredTshirtValue}"]`)) {
					select.value = desiredTshirtValue;
				} else {
					select.value = 'No T-shirt';
				}

				select.dispatchEvent(new Event('change', { bubbles: true }));
				select.dataset.aacTshirtEnhanced = 'true';
			}
		});
	};

		const syncPmproStateDropdown = () => {
			const countryField = getCheckoutCountryControl();
			const stateField = getCheckoutStateControl();
			const stateMap = window.pmprosd_states;
			if (!countryField || !stateField || !stateMap || typeof stateMap !== 'object') {
				return;
			}

			const labelMap = window.pmpro_state_labels || {};
			const currentCountry = countryField.value || (window.pmpro_state_dropdowns && (window.pmpro_state_dropdowns.pmpro_scountry || window.pmpro_state_dropdowns.scountry || window.pmpro_state_dropdowns.bcountry)) || 'US';
			const countryStates = stateMap[currentCountry] || {};
			const hasDropdownOptions = typeof countryStates === 'object' && Object.keys(countryStates).length > 0;
			const currentValue = stateField.value || (window.pmpro_state_dropdowns && (window.pmpro_state_dropdowns.pmpro_sstate || window.pmpro_state_dropdowns.sstate || window.pmpro_state_dropdowns.bstate)) || '';
			const wrapper = stateField.closest('.pmpro_form_field');
			if (!wrapper) {
				return;
			}

			wrapper.querySelectorAll('.select2-container').forEach((node) => node.remove());

			const buildSelect = () => {
				const select = document.createElement('select');
				select.id = stateField.id || 'pmpro_sstate';
				select.name = stateField.name || select.id;
				select.className = stateField.className.replace(/\bpmpro_form_input-text\b/g, ' ').trim();
				select.classList.add('pmpro_form_input-select');
				if (stateField.required) {
					select.required = true;
					select.classList.add('pmpro_form_input-required');
				}
				if (stateField.autocomplete) {
					select.autocomplete = stateField.autocomplete;
				}

				const placeholderOption = document.createElement('option');
				placeholderOption.value = '';
				placeholderOption.textContent = labelMap.region || 'Select state';
				select.appendChild(placeholderOption);

				Object.entries(countryStates).forEach(([value, label]) => {
					const option = document.createElement('option');
					option.value = value;
					option.textContent = label;
					select.appendChild(option);
				});

				if (Object.prototype.hasOwnProperty.call(countryStates, currentValue)) {
					select.value = currentValue;
				} else {
					const matchingEntry = Object.entries(countryStates).find(([, label]) => label === currentValue);
					if (matchingEntry) {
						select.value = matchingEntry[0];
					}
				}

				return select;
			};

			const buildInput = () => {
				const input = document.createElement('input');
				input.id = stateField.id || 'pmpro_sstate';
				input.name = stateField.name || input.id;
				input.type = 'text';
				input.className = stateField.className.replace(/\bpmpro_form_input-select\b/g, ' ').trim();
				input.value = currentValue;
				if (stateField.required) {
					input.required = true;
					input.classList.add('pmpro_form_input-required');
				}
				if (stateField.autocomplete) {
					input.autocomplete = stateField.autocomplete;
				}
				return input;
			};

			if (hasDropdownOptions && stateField.tagName !== 'SELECT') {
				stateField.replaceWith(buildSelect());
			} else if (!hasDropdownOptions && stateField.tagName === 'SELECT') {
				stateField.replaceWith(buildInput());
			} else if (hasDropdownOptions && stateField.tagName === 'SELECT') {
				stateField.classList.add('pmpro_form_input-select');
			}

			if (!countryField.dataset.aacStateDropdownBound) {
				countryField.addEventListener('change', () => {
					window.requestAnimationFrame(syncPmproStateDropdown);
				});
				countryField.dataset.aacStateDropdownBound = 'true';
			}
		};

		const getPmproDonationFieldset = () => {
			const existingFieldset = document.getElementById('pmpro_form_fieldset-donation');
			if (existingFieldset) {
				return existingFieldset;
			}

			const pluginControl = document.getElementById('donation_dropdown')
				|| document.getElementById('donation')
				|| document.getElementById('pmprodon_donation_input');
			const fieldset = pluginControl?.closest('fieldset, .pmpro_checkout-fields, .pmpro_form_fieldset');
			if (!fieldset) {
				return null;
			}

			fieldset.id = 'pmpro_form_fieldset-donation';
			fieldset.classList.add('pmpro_checkout-fields', 'pmpro_form_fieldset');
			return fieldset;
		};

		const enhancePmproDonationFieldset = () => {
			const fieldset = getPmproDonationFieldset();
			const dropdown = document.getElementById('donation_dropdown');
			const amountInput = document.getElementById('donation');
			const amountWrapper = document.getElementById('pmprodon_donation_input');
			if (!fieldset || !dropdown || !amountInput || !amountWrapper) {
				return;
			}

			const normalizeWholeDollarDonationValue = (value) => {
				const rawValue = String(value || '').trim();
				const dollarPart = rawValue.split('.')[0];
				const digits = dollarPart.replace(/\D+/g, '');
				return digits || '0';
			};
			const sanitizeCustomDonationInput = () => {
				if (amountInput.value === '') {
					return;
				}
				amountInput.value = normalizeWholeDollarDonationValue(amountInput.value);
			};
			const presetValues = Array.from(dropdown.options)
				.filter((option) => option.value !== '' && option.value !== 'other')
				.map((option) => normalizeWholeDollarDonationValue(option.value));
			const hasSelectedAttribute = Array.from(dropdown.options).some((option) => option.hasAttribute('selected'));
			const currentAmount = Number.parseFloat(amountInput.value || '0') || 0;
			const defaultPluginAmount = 10;

			if (!dropdown.querySelector('option[value="0"]')) {
				const noDonationOption = document.createElement('option');
				noDonationOption.value = '0';
				noDonationOption.textContent = 'No thank you';
				dropdown.insertBefore(noDonationOption, dropdown.firstChild);
			}

			if (!dropdown.querySelector('option[value="other"]')) {
				const customOption = document.createElement('option');
				customOption.value = 'other';
				customOption.textContent = 'Custom amount';
				dropdown.appendChild(customOption);
			}

			Array.from(dropdown.options).forEach((option) => {
				if (option.value === 'other') {
					return;
				}
				option.value = normalizeWholeDollarDonationValue(option.value);
			});

			if (!fieldset.querySelector('.aac-donation-helper')) {
				const helper = document.createElement('p');
				helper.className = 'aac-donation-helper';
				helper.textContent = 'Choose a preset gift, enter a custom amount, or opt out of adding a donation.';
				const formFields = fieldset.querySelector('.pmpro_form_fields');
				formFields?.appendChild(helper);
			}

			const inlineWrapper = dropdown.closest('.pmpro_form_fields-inline');
			if (!inlineWrapper) {
				return;
			}

			const visibleOptions = Array.from(dropdown.options)
				.filter((option) => option.value !== 'other')
				.map((option) => ({
					value: option.value,
					label: option.value === '0' ? 'No thanks' : option.textContent.trim(),
				}));

			amountInput.inputMode = 'numeric';
			amountInput.min = '0';
			amountInput.step = '1';
			amountInput.pattern = '[0-9]*';
			amountInput.placeholder = 'Enter whole dollars';

			const syncDonationMode = () => {
				const selectedValue = dropdown.value;
				fieldset.dataset.aacDonationMode = selectedValue === 'other' ? 'custom' : 'preset';

				if (selectedValue === 'other') {
					sanitizeCustomDonationInput();
					return;
				}

				amountInput.value = normalizeWholeDollarDonationValue(selectedValue);
			};

			const syncDonationButtons = () => {
				const selectedValue = dropdown.value;
				fieldset.querySelectorAll('[data-aac-donation-value]').forEach((button) => {
					button.dataset.selected = button.getAttribute('data-aac-donation-value') === selectedValue ? 'true' : 'false';
				});
			};

			if (fieldset.dataset.aacDonationEnhanced !== 'true') {
				const shouldDefaultToNone = !hasSelectedAttribute && (currentAmount <= 0 || currentAmount === defaultPluginAmount);
				const shouldUseCustom = !hasSelectedAttribute && currentAmount > 0 && !presetValues.includes(String(currentAmount));

				if (!inlineWrapper.querySelector('.aac-donation-picker')) {
					const picker = document.createElement('div');
					picker.className = 'aac-donation-picker';

					visibleOptions.forEach((option) => {
						const button = document.createElement('button');
						button.type = 'button';
						button.className = 'aac-donation-option';
						button.textContent = option.label;
						button.setAttribute('data-aac-donation-value', option.value);
						button.addEventListener('click', () => {
							dropdown.value = option.value;
							amountInput.value = normalizeWholeDollarDonationValue(option.value);
							dropdown.dispatchEvent(new Event('change', { bubbles: true }));
							amountInput.dispatchEvent(new Event('change', { bubbles: true }));
						});
						picker.appendChild(button);
					});

					const customButton = document.createElement('button');
					customButton.type = 'button';
					customButton.className = 'aac-donation-option';
					customButton.textContent = 'Custom amount';
					customButton.setAttribute('data-aac-donation-value', 'other');
					customButton.addEventListener('click', () => {
						dropdown.value = 'other';
						if (!amountInput.value || Number.parseFloat(amountInput.value || '0') === 0) {
							amountInput.value = '';
						}
						dropdown.dispatchEvent(new Event('change', { bubbles: true }));
						window.requestAnimationFrame(() => amountInput.focus());
					});
					picker.appendChild(customButton);

					inlineWrapper.insertBefore(picker, inlineWrapper.firstChild);
				}

				if (shouldUseCustom) {
					dropdown.value = 'other';
				} else if (shouldDefaultToNone || !dropdown.value) {
					dropdown.value = '0';
				}

				dropdown.addEventListener('change', () => {
					syncDonationMode();
					syncDonationButtons();
					syncMagazineAddonSummary();
				});
				amountInput.addEventListener('input', () => {
					if (dropdown.value === 'other') {
						sanitizeCustomDonationInput();
						amountInput.dispatchEvent(new Event('change', { bubbles: true }));
					}
					syncMagazineAddonSummary();
				});
				amountInput.addEventListener('change', () => {
					if (dropdown.value === 'other') {
						sanitizeCustomDonationInput();
					}
					syncMagazineAddonSummary();
				});
				fieldset.dataset.aacDonationEnhanced = 'true';
			}

			syncDonationMode();
			syncDonationButtons();

			if (fieldset.dataset.aacDonationInitialized !== 'true') {
				dropdown.dispatchEvent(new Event('change', { bubbles: true }));
				fieldset.dataset.aacDonationInitialized = 'true';
			}
		};

		const preparePmproUsernameField = () => {
			const hideUsernameWrapper = (field) => {
				if (!field) {
					return;
				}

				field.hidden = true;
				field.style.display = 'none';
				field.style.visibility = 'hidden';
				field.classList.remove('pmpro_form_field-required');
				field.querySelectorAll('.pmpro_asterisk').forEach((asterisk) => asterisk.remove());
			};

			document.querySelectorAll('input[name="username"], input[name="user_login"], #username').forEach((usernameInput) => {
				usernameInput.type = 'hidden';
				usernameInput.autocomplete = 'off';
				usernameInput.required = false;
				usernameInput.removeAttribute('required');
				usernameInput.removeAttribute('aria-required');
				usernameInput.classList.remove('pmpro_form_input-required');

				hideUsernameWrapper(usernameInput.closest('#username_div, .pmpro_form_field-username, .pmpro_checkout-field-username, .pmpro_checkout-field-user_login, .pmpro_form_field, .pmpro_checkout-field, .pmpro_checkout-field-wrap'));
			});

			document.querySelectorAll('#username_div, .pmpro_form_field-username, .pmpro_checkout-field-username, .pmpro_checkout-field-user_login').forEach(hideUsernameWrapper);
		};

		const bindEmailAvailabilityCheck = () => {
			const emailInput = document.querySelector('input[name="bemail"]');
			if (!emailInput || emailInput.dataset.aacEmailAvailabilityBound === 'true') {
				return;
			}

			const emailField = emailInput.closest('.pmpro_form_field');
			if (!emailField) {
				return;
			}

			let statusNode = emailField.querySelector('.aac-email-availability');
			if (!statusNode) {
				statusNode = document.createElement('p');
				statusNode.className = 'aac-email-availability';
				statusNode.dataset.state = 'idle';
				statusNode.setAttribute('role', 'status');
				statusNode.setAttribute('aria-live', 'polite');
				emailField.appendChild(statusNode);
			}

			let requestCounter = 0;
			let debounceTimer = null;

			const setStatus = (state, message) => {
				statusNode.dataset.state = state;
				statusNode.textContent = message || '';
			};

			const runAvailabilityCheck = async () => {
				const email = String(emailInput.value || '').trim();
				emailInput.setCustomValidity('');

				if (!email) {
					setStatus('idle', '');
					return;
				}

				if (!emailInput.checkValidity()) {
					setStatus('idle', 'Enter a valid email address.');
					return;
				}

				const currentRequest = ++requestCounter;
				setStatus('checking', 'Checking email availability...');

				try {
					const url = new URL(emailAvailabilityEndpoint);
					url.searchParams.set('email', email);

					const response = await fetch(url.toString(), {
						credentials: 'same-origin',
						headers: {
							Accept: 'application/json',
						},
					});

					if (!response.ok) {
						throw new Error(`Email check failed with status ${response.status}`);
					}

					const result = await response.json();
					if (currentRequest !== requestCounter) {
						return;
					}

					if (result?.valid && result?.available) {
						emailInput.setCustomValidity('');
						setStatus('available', result.message || 'Email address is available.');
						return;
					}

					const message = result?.message || 'An account with this email already exists.';
					emailInput.setCustomValidity(message);
					setStatus('unavailable', message);
				} catch (error) {
					if (currentRequest !== requestCounter) {
						return;
					}

					emailInput.setCustomValidity('');
					setStatus('idle', 'Unable to check email availability right now.');
				}
			};

			const scheduleAvailabilityCheck = () => {
				window.clearTimeout(debounceTimer);
				debounceTimer = window.setTimeout(runAvailabilityCheck, 280);
			};

			emailInput.addEventListener('input', scheduleAvailabilityCheck);
			emailInput.addEventListener('change', runAvailabilityCheck);
			emailInput.dataset.aacEmailAvailabilityBound = 'true';
		};

		const enhanceCheckoutAutoRenewFieldset = () => {
			const fieldset = document.getElementById('pmpro_autorenewal_checkbox');
			if (!fieldset) {
				return;
			}

			const checkbox = fieldset.querySelector('input[type="checkbox"]');
			if (!checkbox) {
				return;
			}

			const originalField = checkbox.closest('.pmpro_form_field');
			if (originalField) {
				originalField.hidden = true;
				originalField.style.display = 'none';
			}

			let toggle = fieldset.querySelector('[data-aac-checkout-autorenew-toggle]');
			if (!toggle) {
				const wrapper = document.createElement('div');
				wrapper.className = 'aac-checkout-autorenew';
				wrapper.innerHTML = `
					<div class="aac-checkout-autorenew__copy">
						<strong>Automatic Renewals</strong>
						<span>Keep this membership active with recurring annual renewal.</span>
					</div>
					<label class="aac-managed-toggle">
						<input type="checkbox" data-aac-checkout-autorenew-toggle />
						<span class="aac-managed-toggle__track" aria-hidden="true"></span>
						<span class="aac-managed-toggle__state">On</span>
					</label>
				`;
				fieldset.querySelector('.pmpro_form_fields')?.appendChild(wrapper);
				toggle = wrapper.querySelector('[data-aac-checkout-autorenew-toggle]');
			}

			const stateNode = fieldset.querySelector('.aac-managed-toggle__state');

			const syncVisualState = () => {
				const checked = checkbox.checked;
				toggle.checked = checked;
				if (checked) {
					checkbox.setAttribute('checked', 'checked');
				} else {
					checkbox.removeAttribute('checked');
				}
				if (stateNode) {
					stateNode.textContent = checked ? 'On' : 'Off';
				}
			};

			if (!fieldset.dataset.aacCheckoutAutoRenewInitialized) {
				syncVisualState();
				fieldset.dataset.aacCheckoutAutoRenewInitialized = 'true';
			}

			if (toggle.dataset.aacCheckoutAutoRenewBound !== 'true') {
				toggle.addEventListener('change', () => {
					checkbox.checked = toggle.checked;
					if (toggle.checked) {
						checkbox.setAttribute('checked', 'checked');
					} else {
						checkbox.removeAttribute('checked');
					}
					checkbox.dispatchEvent(new Event('input', { bubbles: true }));
					checkbox.dispatchEvent(new Event('change', { bubbles: true }));
					syncVisualState();
				});
				checkbox.addEventListener('change', syncVisualState);
				toggle.dataset.aacCheckoutAutoRenewBound = 'true';
			}
		};

		const replacePmproLoggedInAccountUsername = () => {
			const preferredAccountLabel = String(currentUserEmail || '').trim() || buildPreferredLoggedInName();
			if (!preferredAccountLabel) {
				return;
			}

			const accountContainers = Array.from(document.querySelectorAll(
				'#pmpro_user_fields, #pmpro_account_loggedin, .pmpro_checkout-h3-msg, .pmpro_logged_in_welcome_wrap, .aac-managed-card'
			)).filter(Boolean);
			if (!accountContainers.length) {
				return;
			}

			for (const accountContainer of accountContainers) {
				if (accountContainer.dataset.aacLoggedInDisplayPatched === 'true') {
					continue;
				}

				const accountParagraphs = accountContainer.matches('p, .pmpro_checkout-h3-msg')
					? [accountContainer]
					: Array.from(accountContainer.querySelectorAll('p, .pmpro_checkout-h3-msg'));
				for (const paragraph of accountParagraphs) {
					const text = (paragraph.textContent || '').trim();
					if (!/You are logged in as/i.test(text) || !/different account/i.test(text)) {
						continue;
					}

					const logoutLink = paragraph.querySelector('a[href*="logout"], a[href*="log-out"], a[href*="action=logout"]');
					const logoutHref = logoutLink?.getAttribute('href') || '';
					const logoutText = (logoutLink?.textContent || 'log out now').trim();
					const escapedName = String(preferredAccountLabel)
						.replace(/&/g, '&amp;')
						.replace(/</g, '&lt;')
						.replace(/>/g, '&gt;')
						.replace(/"/g, '&quot;')
						.replace(/'/g, '&#039;');

					paragraph.innerHTML = logoutHref
						? `You are logged in as <strong>${escapedName}</strong>. If you would like to use a different account for this membership, <a href="${logoutHref}">${logoutText}</a>.`
						: `You are logged in as <strong>${escapedName}</strong>. If you would like to use a different account for this membership, log out now.`;
					accountContainer.dataset.aacLoggedInDisplayPatched = 'true';
					return;
				}
			}
		};

		const enhanceCheckoutWizard = () => {
			const params = new URLSearchParams(window.location.search);
			const form = document.querySelector('form.pmpro_form');
			if (!form) {
				return;
			}

			document.body.dataset.aacCheckoutWizard = 'true';
			delete document.body.dataset.aacCheckoutLayout;

			const membershipDiscountFieldset = document.getElementById('pmpro_form_fieldset-membership-discounts');
			if (membershipDiscountFieldset) {
				membershipDiscountFieldset.dataset.aacMovedToPaymentStep = 'true';
				delete membershipDiscountFieldset.dataset.aacMovedToPlanStep;
				const showMembershipDiscounts = isCheckoutCountryUS() && currentLevelSupportsDiscountTiers();
				membershipDiscountFieldset.hidden = !showMembershipDiscounts;
				membershipDiscountFieldset.style.display = showMembershipDiscounts ? '' : 'none';
			}

			const partnerFamilyFieldset = document.getElementById('pmpro_form_fieldset-partner-family');
			if (partnerFamilyFieldset) {
				partnerFamilyFieldset.dataset.aacMovedToPaymentStep = 'true';
				delete partnerFamilyFieldset.dataset.aacMovedToDetailsStep;
				delete partnerFamilyFieldset.dataset.aacMovedToPlanStep;
			}

			const findNodes = (selectors) => {
				const nodes = [];
				selectors.forEach((selector) => {
					document.querySelectorAll(selector).forEach((node) => {
						if (node && form.contains(node) && !nodes.includes(node)) {
							nodes.push(node);
						}
					});
				});
				return nodes;
			};

			enhancePmproDonationFieldset();
			syncConditionalDiscountDetailFields();
			bindStudentUniversityFieldObserver();

			const showPublicationStep = getCurrentCheckoutLevelId() > 2 && isCheckoutCountryUS();
			const stepDefinitions = [
				{
					label: 'Account Information',
					nodes: findNodes([
						'#pmpro_user_fields',
						'#pmpro_account_loggedin',
					]),
				},
						{
								label: 'Member Information',
								nodes: findNodes([
									'#pmpro_billing_address_fields',
									'[data-aac-native-member-info="true"]',
									'#aac_pmpro_native_member_information_fields',
								]),
							},
				{
					label: 'Publications Preferences',
					enabled: true,
					nodes: findNodes([
						'#pmpro_form_fieldset-publication-preferences',
						'#pmpro_form_fieldset-member-preferences',
						'#pmpro_form_fieldset-more-information',
						'.aac-server-member-preferences',
						'#pmpro_form_fieldset-magazine-addons',
					]),
				},
					{
							label: 'Discounts, promo, and checkout',
							nodes: findNodes([
								'#pmpro_form_fieldset-membership-discounts',
								'#pmpro_form_fieldset-discount-fields',
								'[data-aac-checkout-discount-details]',
								'#pmpro_form_fieldset-partner-family',
								'#pmpro_form_fieldset-donation',
								'.aac-promo-code-section',
								'#pmpro_pricing_fields',
								'[data-aac-magazine-summary]',
								'#pmpro_autorenewal_checkbox',
								'#pmpro_payment_information_fields',
							'.pmpro_form_submit',
					]),
				},
			].map((step) => ({
				...step,
				nodes: step.nodes.filter((node) => node && node.parentNode),
			})).filter((step) => step.enabled !== false && step.nodes.length);

			if (stepDefinitions.length < 2) {
				return;
			}

			const wizard = document.createElement('div');
			wizard.className = 'aac-checkout-wizard';

			const stepsNav = document.createElement('div');
			stepsNav.className = 'aac-checkout-wizard__steps';
			stepsNav.setAttribute('aria-label', 'Checkout steps');

			const progress = document.createElement('div');
			progress.className = 'aac-checkout-wizard__progress';
			progress.setAttribute('aria-hidden', 'true');
			const progressFill = document.createElement('div');
			progressFill.className = 'aac-checkout-wizard__progress-fill';
			progress.appendChild(progressFill);

			const panels = document.createElement('div');
			panels.className = 'aac-checkout-wizard__panels';

			const wizardNotice = document.createElement('div');
			wizardNotice.className = 'aac-checkout-wizard__notice pmpro_message';
			wizardNotice.setAttribute('role', 'alert');
			wizardNotice.hidden = true;

			const processingNotice = document.createElement('div');
			processingNotice.className = 'aac-checkout-wizard__processing';
			processingNotice.setAttribute('role', 'status');
			processingNotice.setAttribute('aria-live', 'polite');
			processingNotice.textContent = 'Processing payment...';
			processingNotice.hidden = true;

			const nav = document.createElement('div');
			nav.className = 'aac-checkout-wizard__nav';

			const backButton = document.createElement('button');
			backButton.type = 'button';
			backButton.className = 'aac-checkout-wizard__back';
			backButton.textContent = 'Back';

			const nextButton = document.createElement('button');
			nextButton.type = 'button';
			nextButton.className = 'aac-checkout-wizard__next';
			nextButton.textContent = 'Continue';

			const hint = document.createElement('span');
			hint.className = 'aac-checkout-wizard__hint';

			nav.append(backButton, nextButton, hint);
			wizard.append(stepsNav, progress, wizardNotice, processingNotice, panels, nav);
			form.insertBefore(wizard, form.firstElementChild);

			const panelsByIndex = stepDefinitions.map((step, index) => {
				const panel = document.createElement('section');
				panel.className = 'aac-checkout-wizard__panel';
				panel.dataset.stepIndex = String(index);
				panel.setAttribute('aria-label', step.label);
				step.nodes.forEach((node) => panel.appendChild(node));
				panels.appendChild(panel);

				const stepButton = document.createElement('button');
				stepButton.type = 'button';
				stepButton.className = 'aac-checkout-wizard__step';
				stepButton.innerHTML = `<span class="aac-checkout-wizard__step-mark">${index + 1}</span><span class="aac-checkout-wizard__step-label">${step.label}</span>`;
				stepButton.addEventListener('click', () => {
					if (index <= currentStep || validateCurrentStep()) {
						goToStep(index);
					}
				});
				stepsNav.appendChild(stepButton);

				return { panel, stepButton, label: step.label };
			});

			let currentStep = 0;
			const isWizardEntryEnabled = () => true;
			const getEnabledWizardEntries = () => panelsByIndex.filter(isWizardEntryEnabled);
			const getNearestEnabledStepIndex = (targetIndex, direction = 1) => {
				const boundedIndex = Math.max(0, Math.min(targetIndex, panelsByIndex.length - 1));
				if (isWizardEntryEnabled(panelsByIndex[boundedIndex])) {
					return boundedIndex;
				}

				for (let index = boundedIndex + direction; index >= 0 && index < panelsByIndex.length; index += direction) {
					if (isWizardEntryEnabled(panelsByIndex[index])) {
						return index;
					}
				}

				const fallback = getEnabledWizardEntries()[0];
				return fallback ? panelsByIndex.indexOf(fallback) : 0;
			};
			const getPreviousEnabledStepIndex = () => {
				for (let index = currentStep - 1; index >= 0; index -= 1) {
					if (isWizardEntryEnabled(panelsByIndex[index])) {
						return index;
					}
				}
				return currentStep;
			};
			const getNextEnabledStepIndex = () => {
				for (let index = currentStep + 1; index < panelsByIndex.length; index += 1) {
					if (isWizardEntryEnabled(panelsByIndex[index])) {
						return index;
					}
				}
				return currentStep;
			};
			const getWizardTshirtField = () =>
				document.getElementById('t_shirt_div') ||
				document.getElementById('tshirt_div') ||
				document.getElementById('t_shirt_size_div') ||
				document.getElementById('tshirt_size_div') ||
				document.getElementById('shirt_size_div') ||
				document.querySelector('select[name="t_shirt"], select[name="tshirt"], select[name="t_shirt_size"], select[name="tshirt_size"], select[name="shirt_size"]')?.closest('.pmpro_form_field');
			const syncWizardTshirtVisibility = () => {
				const tshirtField = getWizardTshirtField();
				if (!tshirtField) {
					return;
				}

				const isDetailsStep = panelsByIndex[currentStep]?.label === 'Member Information';
				const showTshirt = isDetailsStep && isCheckoutCountryUS() && getCurrentCheckoutLevelId() >= 2;
				tshirtField.hidden = !showTshirt;
				tshirtField.style.display = showTshirt ? '' : 'none';
			};

			const postCheckoutHeight = () => {
				try {
					const root = document.querySelector('.aac-managed-card--embed') || document.querySelector('.aac-managed-card') || document.body;
					const rootRect = root.getBoundingClientRect();
					const wizardRect = wizard.getBoundingClientRect();
					const activePanelRect = panelsByIndex[currentStep]?.panel?.getBoundingClientRect();
					const navRect = nav.getBoundingClientRect();
					const submitRect = document.querySelector('.pmpro_form_submit')?.getBoundingClientRect();
					const documentHeight = Math.max(
						document.body?.scrollHeight || 0,
						document.documentElement?.scrollHeight || 0,
						document.body?.offsetHeight || 0,
						document.documentElement?.offsetHeight || 0
					);
					const measuredHeight = Math.ceil(Math.max(
						wizardRect.bottom - rootRect.top,
						activePanelRect ? activePanelRect.bottom - rootRect.top : 0,
						navRect.bottom - rootRect.top,
						submitRect ? submitRect.bottom - rootRect.top : 0,
						documentHeight,
						460
					));
					window.parent?.postMessage({
						type: 'aac-pmpro-checkout-height',
						height: measuredHeight,
					}, window.location.origin);
				} catch (error) {
					// Ignore cross-frame height sync failures.
				}
			};

			const scheduleCheckoutHeight = () => {
				[0, 80, 180, 360, 720, 1200, 2000].forEach((delay) => {
					window.setTimeout(postCheckoutHeight, delay);
				});
			};

			const postCheckoutStep = () => {
				try {
					window.parent?.postMessage({
						type: 'aac-pmpro-checkout-step',
						stepIndex: currentStep,
						stepLabel: panelsByIndex[currentStep]?.label || '',
						stepCount: panelsByIndex.length,
					}, window.location.origin);
				} catch (error) {
					// Ignore cross-frame step sync failures.
				}
			};

			let lastWizardNoticeText = '';
			let lastWizardNoticeClass = wizardNotice.className;
			let lastWizardNoticeHidden = true;
			let processingTimeoutId = null;
			let originalSubmitButtonValue = 'Submit and Check Out';

			const resetSubmitButton = () => {
				const submitButton = document.getElementById('pmpro_btn-submit');
				if (submitButton && submitButton.value === 'Processing...') {
					submitButton.value = originalSubmitButtonValue;
					return true;
				}
				return false;
			};

			const stopProcessingNotice = () => {
				let changed = false;
				if (processingTimeoutId) {
					window.clearTimeout(processingTimeoutId);
					processingTimeoutId = null;
					changed = true;
				}
				if (!processingNotice.hidden) {
					processingNotice.hidden = true;
					changed = true;
				}
				changed = resetSubmitButton() || changed;
				if (changed) {
					postCheckoutHeight();
				}
			};

			const startProcessingNotice = () => {
				const submitButton = document.getElementById('pmpro_btn-submit');
				if (submitButton && submitButton.value && submitButton.value !== 'Processing...') {
					originalSubmitButtonValue = submitButton.value;
				}
				processingNotice.textContent = 'Processing payment...';
				processingNotice.hidden = false;
				if (submitButton) {
					submitButton.value = 'Processing...';
				}
				if (processingTimeoutId) {
					window.clearTimeout(processingTimeoutId);
				}
				const submittedUrl = window.location.href;
				processingTimeoutId = window.setTimeout(() => {
					if (window.location.href !== submittedUrl || processingNotice.hidden) {
						return;
					}
					processingNotice.hidden = true;
					resetSubmitButton();
					syncCheckoutMessage();
					postCheckoutHeight();
				}, 18000);
				scheduleCheckoutHeight();
			};

			const isDiscountCodeMessage = (message, messageText = '') => {
				if (!message) {
					return false;
				}

				return Boolean(
					message.id === 'discount_code_message'
					|| message.closest?.('[data-aac-discount-code]')
					|| /discount code|promo code|code has been applied|applied code/i.test(messageText)
				);
			};

			const getCheckoutMessage = () => {
				const messages = Array.from(document.querySelectorAll('#pmpro_message, #pmpro_message_bottom, .pmpro_message, .pmpro_error, [role="alert"]'))
					.filter((message) => {
						const text = (message?.textContent || '').trim();
						return message && message !== wizardNotice && !isDiscountCodeMessage(message, text);
					});

				return messages.find((message) => {
					const text = (message.textContent || '').trim();
					if (!text) {
						return false;
					}

					const style = window.getComputedStyle(message);
					return style.display !== 'none' && style.visibility !== 'hidden';
				}) || messages.find((message) => (message.textContent || '').trim());
			};

			const syncCheckoutMessage = ({ scroll = false } = {}) => {
				scrubPmproDiscountMessages();
				const sourceMessage = getCheckoutMessage();
				const messageText = cleanCheckoutMessageText(sourceMessage?.textContent || '');
				if (sourceMessage && isDiscountCodeMessage(sourceMessage, messageText)) {
					postCheckoutHeight();
					return;
				}
				const sourceMessageHidden = !sourceMessage || sourceMessage.hidden || sourceMessage.style.display === 'none' || window.getComputedStyle(sourceMessage).display === 'none';
				if (sourceMessageHidden || !messageText) {
					if (!lastWizardNoticeHidden || lastWizardNoticeText) {
						wizardNotice.hidden = true;
						wizardNotice.textContent = '';
						lastWizardNoticeText = '';
					lastWizardNoticeHidden = true;
					}
					postCheckoutHeight();
					return;
				}
				stopProcessingNotice();

				const nextClassName = `aac-checkout-wizard__notice ${sourceMessage.className || 'pmpro_message'}`.trim();
				if (
					lastWizardNoticeText !== messageText ||
					lastWizardNoticeClass !== nextClassName ||
					lastWizardNoticeHidden
				) {
					wizardNotice.textContent = messageText;
					wizardNotice.className = nextClassName;
					wizardNotice.hidden = false;
					lastWizardNoticeText = messageText;
					lastWizardNoticeClass = nextClassName;
					lastWizardNoticeHidden = false;
				}

				window.setTimeout(() => {
					postCheckoutHeight();
					if (scroll && !isDiscountCodeMessage(sourceMessage, messageText)) {
						wizardNotice.scrollIntoView({ block: 'start', behavior: 'smooth' });
					}
				}, 40);
			};

			const isControlVisible = (element) => {
				if (element.type === 'hidden' || element.disabled) {
					return false;
				}
				if (typeof element.checkVisibility === 'function') {
					return element.checkVisibility({ checkOpacity: false, checkVisibilityCSS: true });
				}
				return Boolean(element.offsetWidth || element.offsetHeight || element.getClientRects().length);
			};

			const getControlLabelText = (control) => {
				const controlId = control.id && window.CSS?.escape ? CSS.escape(control.id) : '';
				const explicitLabel = controlId ? document.querySelector(`label[for="${controlId}"]`)?.textContent || '' : '';
				const fieldLabel = control.closest?.('.pmpro_checkout-field, .pmpro_form_field, .pmpro_checkout-field-wrap, .pmpro_checkout-field-row')?.querySelector('label')?.textContent || '';
				return `${explicitLabel} ${fieldLabel}`.trim();
			};

			const getCleanControlLabelText = (control) => {
				const label = getControlLabelText(control)
					.replace(/\s*\*\s*/g, ' ')
					.replace(/\s+/g, ' ')
					.trim();
				if (label) {
					return label;
				}
				return control.getAttribute('aria-label') || control.name || control.id || 'This field';
			};

			const showWizardFieldError = (control, message) => {
				const messageText = message || `${getCleanControlLabelText(control)} is required.`;
				stopProcessingNotice();
				wizardNotice.textContent = messageText;
				wizardNotice.className = 'aac-checkout-wizard__notice pmpro_message pmpro_error';
				wizardNotice.hidden = false;
				lastWizardNoticeText = messageText;
				lastWizardNoticeClass = wizardNotice.className;
				lastWizardNoticeHidden = false;
				window.setTimeout(() => {
					postCheckoutHeight();
					wizardNotice.scrollIntoView({ block: 'start', behavior: 'smooth' });
				}, 40);
			};

			const isMarkupRequiredControl = (control) => {
				if (control.required || control.getAttribute('aria-required') === 'true') {
					return true;
				}
				const isLoggedInCheckout = document.body.classList.contains('logged-in') || document.body.classList.contains('admin-bar') || Boolean(document.querySelector('.pmpro_logged_in_welcome_wrap, .pmpro_checkout-h3-msg a[href*="logout"]'));
				if (isLoggedInCheckout) {
					return false;
				}
				return /\*/.test(getControlLabelText(control));
			};

			const isEmptyRequiredControl = (control) => {
				if (!isControlVisible(control)) {
					return false;
				}
				if (!isMarkupRequiredControl(control)) {
					return false;
				}
				if (control.type === 'checkbox') {
					return !control.checked;
				}
				if (control.type === 'radio' && control.name) {
					const radioName = window.CSS?.escape ? CSS.escape(control.name) : control.name;
					return !form.querySelector(`input[type="radio"][name="${radioName}"]:checked`);
				}
				return !String(control.value || '').trim();
			};

			const validateStep = (stepIndex) => {
				syncCheckoutAccountHiddenFields();
				const panel = panelsByIndex[stepIndex]?.panel;
				if (!panel) {
					return true;
				}

				const controls = Array.from(panel.querySelectorAll('input, select, textarea'))
					.filter((control) => control.type !== 'hidden' && !control.disabled);
				controls.forEach((control) => {
					if (typeof control.setCustomValidity === 'function') {
						control.setCustomValidity('');
					}
				});
				const invalidControl = controls.find(isEmptyRequiredControl)
					|| controls.find((control) => isControlVisible(control) && typeof control.checkValidity === 'function' && !control.checkValidity());
				if (invalidControl) {
					const isEmpty = isEmptyRequiredControl(invalidControl);
					const fieldMessage = isEmpty
						? `${getCleanControlLabelText(invalidControl)} is required.`
						: (invalidControl.validationMessage || `Please check ${getCleanControlLabelText(invalidControl)}.`);
					if (typeof invalidControl.setCustomValidity === 'function' && isEmpty) {
						invalidControl.setCustomValidity(fieldMessage);
					}
					if (stepIndex !== currentStep) {
						goToStep(stepIndex);
					}
					showWizardFieldError(invalidControl, fieldMessage);
					if (typeof invalidControl.reportValidity === 'function') {
						invalidControl.reportValidity();
					}
					invalidControl.focus({ preventScroll: true });
					return false;
				}

				return true;
			};

			const validateCurrentStep = () => validateStep(currentStep);

				const validateCompletedSteps = () => {
					for (let stepIndex = 0; stepIndex <= currentStep; stepIndex += 1) {
						if (!isWizardEntryEnabled(panelsByIndex[stepIndex])) {
							continue;
					}
					if (!validateStep(stepIndex)) {
						return false;
					}
				}
					return true;
				};

				const syncWizardNativeRequiredStates = () => {
					panelsByIndex.forEach((entry, stepIndex) => {
						const active = isWizardEntryEnabled(entry) && stepIndex === currentStep;
						entry.panel.querySelectorAll('input, select, textarea').forEach((control) => {
							if (control.type === 'hidden') {
								return;
							}

							if (!control.dataset.aacWizardOriginalRequired) {
								control.dataset.aacWizardOriginalRequired = control.required ? 'true' : 'false';
								control.dataset.aacWizardOriginalAriaRequired = control.getAttribute('aria-required') || '';
							}

							if (active && isControlVisible(control)) {
								if (control.dataset.aacWizardOriginalRequired === 'true') {
									control.required = true;
								} else {
									control.required = false;
									control.removeAttribute('required');
								}

								if (control.dataset.aacWizardOriginalAriaRequired) {
									control.setAttribute('aria-required', control.dataset.aacWizardOriginalAriaRequired);
								} else {
									control.removeAttribute('aria-required');
								}
								return;
							}

							control.required = false;
							control.removeAttribute('required');
							control.removeAttribute('aria-required');
						});
					});
				};

				const goToStep = (index) => {
					const direction = index >= currentStep ? 1 : -1;
					currentStep = getNearestEnabledStepIndex(index, direction);
					const enabledEntries = getEnabledWizardEntries();
				const currentEnabledIndex = enabledEntries.indexOf(panelsByIndex[currentStep]);
				panelsByIndex.forEach((entry, stepIndex) => {
					const { panel, stepButton } = entry;
					const enabled = isWizardEntryEnabled(entry);
					const visibleIndex = enabledEntries.indexOf(entry);
					const active = enabled && stepIndex === currentStep;
					panel.hidden = !active;
					panel.style.display = active ? '' : 'none';
					panel.classList.toggle('is-active', active);
					stepButton.hidden = !enabled;
					stepButton.style.display = enabled ? '' : 'none';
					stepButton.setAttribute('aria-current', active ? 'step' : 'false');
					stepButton.dataset.complete = enabled && visibleIndex < currentEnabledIndex ? 'true' : 'false';
					const mark = stepButton.querySelector('.aac-checkout-wizard__step-mark');
						if (mark) {
							mark.textContent = enabled && visibleIndex < currentEnabledIndex ? '✓' : String(visibleIndex + 1);
						}
					});
					syncWizardTshirtVisibility();
					syncWizardNativeRequiredStates();

					const lastEnabledEntry = enabledEntries[enabledEntries.length - 1];
					const isLastStep = panelsByIndex[currentStep] === lastEnabledEntry;
				backButton.hidden = currentStep === panelsByIndex.indexOf(enabledEntries[0]);
				nextButton.hidden = isLastStep;
				hint.textContent = isLastStep ? 'Review your order and complete payment.' : '';
					progressFill.style.width = `${((currentEnabledIndex + 1) / Math.max(1, enabledEntries.length)) * 100}%`;
					postCheckoutStep();
					syncCheckoutMessage();
					scheduleCheckoutHeight();
					window.scrollTo({ top: 0, behavior: 'smooth' });
				};

			backButton.addEventListener('click', () => goToStep(getPreviousEnabledStepIndex()));
				nextButton.addEventListener('click', () => {
					syncCheckoutAccountHiddenFields();
					if (validateCurrentStep()) {
						goToStep(getNextEnabledStepIndex());
					}
				});
				const refreshWizardForCountryChange = () => {
					syncCountryLimitedSignupOptions();
					syncWizardTshirtVisibility();
					if (!isWizardEntryEnabled(panelsByIndex[currentStep])) {
						goToStep(getNearestEnabledStepIndex(currentStep, -1));
						return;
					}
					goToStep(currentStep);
				};

					getCheckoutCountryControl()?.addEventListener('change', () => {
						refreshWizardForCountryChange();
						window.setTimeout(refreshWizardForCountryChange, 80);
					});

			form.addEventListener('keydown', (event) => {
				if (event.key !== 'Enter') {
					return;
				}

				const target = event.target;
				if (!target || target.closest('[data-aac-discount-code-form]') || target.matches('textarea, button, input[type="submit"], input[type="button"]')) {
					return;
				}

				if (target.matches('input, select')) {
					event.preventDefault();
				}
			});

					form.addEventListener('submit', (event) => {
						syncCheckoutAccountHiddenFields();
						syncNativePmproMemberFieldsToLegacyBilling();
						syncSelectedMembershipDiscountForSubmit();
						syncCheckoutAutoRenewForSubmit();
						syncWizardNativeRequiredStates();
					if (!validateCompletedSteps()) {
						event.preventDefault();
						stopProcessingNotice();
					return;
				}
				if (currentStep === panelsByIndex.length - 1) {
					startProcessingNotice();
				}
				[150, 450, 900, 1400].forEach((delay) => {
					window.setTimeout(() => syncCheckoutMessage({ scroll: true }), delay);
				});
			});

			form.addEventListener('click', (event) => {
				if (event.target?.id === 'pmpro_btn-submit') {
					[150, 450, 900, 1400].forEach((delay) => {
						window.setTimeout(() => syncCheckoutMessage({ scroll: true }), delay);
					});
				}
			});

			let checkoutMessageSyncTimeoutId = null;
			const scheduleCheckoutMessageSync = () => {
				if (isDiscountMessageSyncSuppressed()) {
					window.setTimeout(scrubPmproDiscountMessages, 80);
					return;
				}
				if (checkoutMessageSyncTimeoutId) {
					window.clearTimeout(checkoutMessageSyncTimeoutId);
				}
				checkoutMessageSyncTimeoutId = window.setTimeout(() => {
					checkoutMessageSyncTimeoutId = null;
					syncCheckoutMessage();
				}, 120);
			};

			const messageObserver = new MutationObserver(scheduleCheckoutMessageSync);
			messageObserver.observe(form.parentElement || document.body, {
				attributes: true,
				childList: true,
				subtree: true,
				characterData: true,
				attributeFilter: ['class', 'style', 'hidden'],
			});

			window.addEventListener('message', (event) => {
				if (event.origin !== window.location.origin) {
					return;
				}

				if (event.data?.type === 'aac-pmpro-membership-discount') {
					applyExternalMembershipDiscount(event.data.discount, event.data.family);
					return;
				}

				if (event.data?.type !== 'aac-pmpro-checkout-go-step') {
					return;
				}

				const requestedStep = Number(event.data.stepIndex);
				if (!Number.isInteger(requestedStep)) {
					return;
				}

				goToStep(requestedStep);
			});

			form.dataset.aacCheckoutWizardEnhanced = 'true';
			const initialCheckoutMessageText = cleanCheckoutMessageText(getCheckoutMessage()?.textContent || '');
			const requestedWizardStep = String(params.get('aac_wizard_step') || '').trim().toLowerCase();
			const requestedStepIndex = panelsByIndex.findIndex((entry) => {
				const label = String(entry.label || '').toLowerCase();
				return label === requestedWizardStep || label.startsWith(requestedWizardStep);
			});
			const initialStep = /card|payment|declined|stripe|cvc|expiration/i.test(initialCheckoutMessageText)
				? panelsByIndex.length - 1
				: requestedStepIndex >= 0 ? requestedStepIndex : 0;
			goToStep(initialStep);
		};

		const bindManagedAutoRenewToggle = () => {
			const toggle = document.querySelector('[data-aac-autorenew-toggle]');
			if (!toggle || toggle.dataset.aacAutoRenewBound === 'true') {
				return;
			}

			toggle.addEventListener('change', () => {
				const stateLabel = toggle.closest('.aac-managed-toggle')?.querySelector('.aac-managed-toggle__state');
				if (stateLabel) {
					stateLabel.textContent = toggle.checked ? 'On' : 'Off';
				}

				const enableUrl = toggle.dataset.enableUrl || '';
				const disableUrl = toggle.dataset.disableUrl || '';
				const targetUrl = toggle.checked ? enableUrl : disableUrl;

				if (targetUrl) {
					toggle.disabled = true;
					window.location.assign(targetUrl);
					return;
				}

				toggle.checked = !toggle.checked;
				if (stateLabel) {
					stateLabel.textContent = toggle.checked ? 'On' : 'Off';
				}
			});

			toggle.dataset.aacAutoRenewBound = 'true';
		};

		const removePmproMemberLinksSection = () => {
			const managedCard = document.querySelector('.aac-managed-card');
			if (!managedCard) {
				return;
			}

			const candidateNodes = managedCard.querySelectorAll('h1, h2, h3, h4, h5, h6, legend, strong, p');
			for (const candidate of candidateNodes) {
				const text = (candidate.textContent || '').trim().toLowerCase();
				if (text !== 'member links') {
					continue;
				}

				const removableSection =
					candidate.closest('#pmpro_account-links') ||
					candidate.closest('.pmpro_section') ||
					candidate.closest('.pmpro_card');

				if (removableSection && managedCard.contains(removableSection) && removableSection !== managedCard) {
					removableSection.remove();
				}
			}
		};

		const dedupePmproMessages = () => {
			const messages = Array.from(document.querySelectorAll('.aac-managed-card .pmpro_message'));
			const seenMessages = new Set();

			messages.forEach((message) => {
				const text = (message.textContent || '').replace(/\s+/g, ' ').trim();
				if (!text) {
					return;
				}

				const key = text.toLowerCase();
				if (seenMessages.has(key)) {
					if (message.dataset.aacDuplicatePmproMessage !== 'true') {
						message.hidden = true;
						message.style.display = 'none';
						message.dataset.aacDuplicatePmproMessage = 'true';
					}
					return;
				}

				seenMessages.add(key);
				if (message.dataset.aacDuplicatePmproMessage === 'true') {
					message.hidden = false;
					message.style.display = '';
					message.dataset.aacDuplicatePmproMessage = 'false';
				}
			});
		};

		const bindPmproMessageDedupe = () => {
			if (document.body.dataset.aacPmproMessageDedupeBound === 'true') {
				dedupePmproMessages();
				return;
			}

			let dedupeTimer = null;
			const scheduleDedupe = () => {
				window.clearTimeout(dedupeTimer);
				dedupeTimer = window.setTimeout(dedupePmproMessages, 40);
			};

			new MutationObserver(scheduleDedupe).observe(document.body, {
				childList: true,
				characterData: true,
				subtree: true,
			});

			document.body.dataset.aacPmproMessageDedupeBound = 'true';
			dedupePmproMessages();
		};

		const moveMembershipDiscountsBeforeOrderSummary = () => {
			const discounts = document.getElementById('pmpro_form_fieldset-membership-discounts');
			const summary = document.getElementById('pmpro_pricing_fields') || document.querySelector('[data-aac-magazine-summary]');
			if (discounts && summary?.parentNode && summary.previousElementSibling !== discounts) {
				summary.parentNode.insertBefore(discounts, summary);
			}
		};

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', () => {
				preparePmproUsernameField();
				bindEmailAvailabilityCheck();
				enhancePmproProfileInformation();
				enhanceCheckoutAutoRenewFieldset();
				enhancePmproDonationFieldset();
				enhancePublicationPreferenceCards();
				bindToggleableMembershipDiscounts();
				bindFamilySelectionShortcut();
				enhanceFamilyDependentButtons();
				syncStandaloneFamilyVisibility();
				bindCountryLimitedSignupOptions();
				relabelTShirtSizeOptions();
				syncMagazineAddonSummary();
				syncPmproStateDropdown();
				replacePmproLoggedInAccountUsername();
				enhanceCheckoutWizard();
				bindManagedAutoRenewToggle();
				removePmproMemberLinksSection();
				bindPmproMessageDedupe();
			});
		} else {
			preparePmproUsernameField();
			bindEmailAvailabilityCheck();
			enhancePmproProfileInformation();
			enhanceCheckoutAutoRenewFieldset();
			enhancePmproDonationFieldset();
			enhancePublicationPreferenceCards();
			bindToggleableMembershipDiscounts();
			bindFamilySelectionShortcut();
			enhanceFamilyDependentButtons();
			syncStandaloneFamilyVisibility();
			bindCountryLimitedSignupOptions();
			relabelTShirtSizeOptions();
			syncMagazineAddonSummary();
			syncPmproStateDropdown();
			replacePmproLoggedInAccountUsername();
			enhanceCheckoutWizard();
			bindManagedAutoRenewToggle();
			removePmproMemberLinksSection();
			bindPmproMessageDedupe();
		}

		window.addEventListener('load', preparePmproUsernameField);
		window.addEventListener('load', bindEmailAvailabilityCheck);
		window.addEventListener('load', enhancePmproProfileInformation);
		window.addEventListener('load', enhanceCheckoutAutoRenewFieldset);
		window.addEventListener('load', enhancePmproDonationFieldset);
		window.addEventListener('load', enhancePublicationPreferenceCards);
		window.addEventListener('load', bindToggleableMembershipDiscounts);
		window.addEventListener('load', bindFamilySelectionShortcut);
		window.addEventListener('load', enhanceFamilyDependentButtons);
		window.addEventListener('load', syncStandaloneFamilyVisibility);
		window.addEventListener('load', bindCountryLimitedSignupOptions);
		window.addEventListener('load', relabelTShirtSizeOptions);
		window.addEventListener('load', syncMagazineAddonSummary);
		window.addEventListener('load', syncPmproStateDropdown);
		window.addEventListener('load', replacePmproLoggedInAccountUsername);
		window.addEventListener('load', enhanceCheckoutWizard);
		window.addEventListener('load', bindManagedAutoRenewToggle);
		window.addEventListener('load', removePmproMemberLinksSection);
		window.addEventListener('load', bindPmproMessageDedupe);
		window.addEventListener('load', moveMembershipDiscountsBeforeOrderSummary);
		window.setTimeout(moveMembershipDiscountsBeforeOrderSummary, 250);
		window.setTimeout(moveMembershipDiscountsBeforeOrderSummary, 1000);
	}());
</script>
<script>
	(function () {
		const placeDiscounts = function () {
			const discounts = document.getElementById('pmpro_form_fieldset-membership-discounts');
			const summary = document.getElementById('pmpro_pricing_fields');
			if (discounts && summary && summary.parentNode && summary.previousElementSibling !== discounts) {
				summary.parentNode.insertBefore(discounts, summary);
			}
		};
		document.addEventListener('DOMContentLoaded', placeDiscounts);
		window.addEventListener('load', placeDiscounts);
		window.setTimeout(placeDiscounts, 500);
		window.setTimeout(placeDiscounts, 1500);
	}());
</script>
