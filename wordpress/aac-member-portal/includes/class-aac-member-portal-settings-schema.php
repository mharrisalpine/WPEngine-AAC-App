<?php
/**
 * Settings defaults, normalization, and sanitization for the AAC Member Portal.
 */

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Member_Portal_Settings_Schema {
	private static function get_join_page_url() {
		return function_exists('home_url') ? home_url('/signup/') : '/signup/';
	}

	private static function is_legacy_join_url($url) {
		$normalized = trim((string) $url);
		$normalized = rtrim($normalized, '/');
		if (in_array($normalized, ['/join', 'https://membership.americanalpineclub.org/join'], true)) {
			return true;
		}

		$path = untrailingslashit((string) wp_parse_url($normalized, PHP_URL_PATH));
		$fragment = trim((string) wp_parse_url($normalized, PHP_URL_FRAGMENT), '/');

		return in_array($path, ['/membership-sign-up-test', '/signup'], true)
			|| (in_array($path, ['/membership', '/member-profile'], true) && $fragment === 'join');
	}

	public static function get_defaults() {
		// This settings tree is grand central station for admin-controlled portal
		// content. The admin UI edits it, and the React app reads the cleaned version.
		return [
			'content' => [
				'home_hero_kicker' => 'Home',
				'home_hero_title' => "United\nWe Climb.",
				'home_hero_description' => 'Explore AAC membership, publications, benefits, and community resources through the same member-focused experience that powers the portal.',
				'home_primary_cta_label' => 'Join',
				'home_primary_cta_url' => self::get_join_page_url(),
				'home_secondary_cta_label' => 'Renew',
				'home_secondary_cta_url' => 'https://membership.americanalpineclub.org/renew',
				'home_tertiary_cta_label' => 'Learn More About Membership',
				'home_tertiary_cta_url' => 'https://americanalpine.wpenginepowered.com/learn-more/',
				'home_membership_chip_kicker' => 'Membership',
				'home_membership_chip_description' => 'Climbing advocacy, publications, benefits, and member resources all live here.',
				'home_intro_kicker' => 'Since 1902',
				'home_intro_title' => 'Built for climbers.',
				'home_intro_description' => 'Founded in 1902, the American Alpine Club is a nonprofit that champions climbing knowledge, inspiration, advocacy, and community support for people who care deeply about the mountains.',
				'home_intro_secondary_description' => 'From member publications to partner benefits and account tools, the Club keeps building practical resources that help climbers stay connected and better supported.',
				'home_intro_button_label' => 'Learn More About The AAC',
				'home_intro_button_url' => 'https://americanalpine.wpenginepowered.com/learn-more/',
				'home_involvement_kicker' => 'Explore',
				'home_involvement_title' => 'How To Get Involved',
				'home_involvement_button_label' => 'Join the Club',
				'home_involvement_button_url' => self::get_join_page_url(),
				'home_publications_kicker' => 'Library',
				'home_publications_title' => 'Our Publications',
				'home_publications_button_label' => 'All Publications',
				'home_publications_button_url' => 'https://americanalpine.wpenginepowered.com/publications/',
				'home_partners_kicker' => 'Network',
				'home_partners_title' => 'Our Partners',
				'home_partners_description' => 'Partner brands and community collaborators help AAC extend member value across climbing gear, publications, and advocacy work.',
				'home_involvement_cards' => self::get_default_home_involvement_cards(),
				'home_publication_cards' => self::get_default_home_publication_cards(),
				'home_partner_logos' => self::get_default_home_partner_logos(),
				'account_settings_title' => 'Account Settings',
				'contact_recipient_email' => 'mharris@americanalpineclub.org',
				'contact_issue_types' => self::get_default_contact_issue_types(),
				'profile_information_title' => 'Profile Information',
				'profile_information_description' => 'Primary contact and profile information used across the AAC portal. You may update your details and preferences in Account Settings.',
				'membership_snapshot_title' => 'Membership Snapshot',
				'membership_snapshot_description' => 'Live membership and benefit details coming from WordPress and Paid Memberships Pro.',
				'linked_accounts_title' => 'Linked Accounts',
				'linked_accounts_description' => 'Manage household members connected to this AAC membership and redeem invite codes for child accounts.',
				'update_profile_button_label' => 'Update Profile Information',
				'member_profile_card_sections' => self::get_default_member_profile_card_sections(),
				'member_profile_blocks' => [],
				'publications_title' => 'Books & Media',
				'publications_description' => 'Access AAC digital publications, podcasts, and member stories from the member portal.',
				'publications_locked_title' => 'Books & Media Unlocks at Partner',
				'publications_locked_description' => 'The AAC publication library is available to Partner members and above. Upgrade your membership to open digital issues and manage your publication preferences.',
				'publications_upgrade_button_label' => 'Upgrade Membership',
				'publication_view_url_aaj' => 'https://aac-publications.s3.us-east-1.amazonaws.com/aaj/AAJ+2025.pdf',
				'publication_view_url_anac' => 'https://aac-publications.s3.us-east-1.amazonaws.com/ANAC+2025+Book_Digital_reduced.pdf',
				'publication_view_url_acj' => 'https://americanalpineclub.org/publications/',
				'publication_view_url_guidebook' => 'https://www.flipsnack.com/americanalpineclub/guidebook-xv/full-view.html',
				'join_hero_kicker' => 'Membership',
				'join_hero_title' => "United\nWe Climb.",
				'join_hero_description' => 'Join the American Alpine Club to support climbing advocacy, publications, partner benefits, and a member experience built for the people who keep showing up for the mountains.',
				'join_primary_cta_label' => 'Join Now',
				'join_benefits_cta_label' => 'Member Benefits',
				'join_application_kicker' => '',
				'join_application_title' => 'Choose your membership and complete checkout.',
				'join_application_description' => 'Select a membership level above, then complete the real AAC checkout form below.',
				'join_redeem_code_button_label' => 'Redeem Family Member Invite',
				'signup_benefits_matrix_image_url' => '',
				'signup_level_benefits' => self::get_default_signup_level_benefits(),
				'login_hero_kicker' => 'Member access',
				'login_hero_title' => "United\nWe Climb.",
				'login_hero_description' => 'Access your membership details, benefits, discounts, publications, and account settings in one place.',
				'login_form_kicker' => 'Login',
				'login_form_title' => 'Welcome back.',
				'login_submit_label' => 'Sign in',
				'login_forgot_password_label' => 'Forgot your password?',
				'login_join_link_label' => 'Need to join?',
				'login_purchase_success_message' => 'Purchase successful. Please sign in to access your member profile.',
				'rescue_levels' => self::get_default_rescue_levels(),
				'linked_accounts_page_title' => 'Linked Accounts',
				'linked_accounts_page_description' => 'Enter a family invite code to create or claim a connected household account. If the email already has an AAC account, we will link that existing account after verifying the password.',
				'linked_accounts_lookup_button_label' => 'Check Code',
				'linked_accounts_redeem_button_label' => 'Redeem Invite Code',
				'linked_accounts_success_message' => 'Invite redeemed successfully. Redirecting to your member profile...',
				'discounts_title' => 'Member Benefits',
				'discounts_locked_title' => 'Discounts Locked',
				'discounts_locked_description' => 'Discounts are available to active members only. Renew or rejoin your membership to unlock partner offers.',
				'discounts_free_locked_description' => 'Free memberships include portal preview access and promo emails, but partner discounts unlock with a paid membership.',
				'discounts_upgrade_hint' => 'Upgrade from Free to Supporter or above whenever you are ready.',
				'discounts_button_label' => 'Visit Website',
				'benefits_gallery_items' => self::get_default_benefits_gallery_items(),
				'discount_cards' => self::get_default_discount_cards(),
				'portal_preferences_title' => 'Portal Preferences',
				'portal_preferences_description' => 'Settings the portal is currently storing for your member record.',
				'quick_actions_title' => 'Quick Actions',
				'quick_actions_description' => 'Jump straight into the next member task.',
				'confirmation_letter_format' => 'standard',
				'confirmation_letter_body' => "To Whom it May Concern,\n\nThis letter confirms that **{member_name}** is a member of The American Alpine Club.\n{benefit_sentence}\n\n{reimbursement_sentence}\n\nIn case of rescue contact Redpoint Travel Protection: +01-628-251-1510\n\nPlease contact the American Alpine Club with any questions at 303-384-0110 or email us at info@americanalpineclub.org.\n\nRegards,\n\nThe American Alpine Club\n710 Tenth Street Suite 100\nGolden, CO 80401 USA",
			],
			'design' => [
				'sidebar_background_url' => 'https://wallpapers.com/images/high/abstract-black-topographic-map-q34pt7luthso1030.webp',
				'sidebar_overlay_start' => '0.18',
				'sidebar_overlay_end' => '0.30',
				'sidebar_button_background' => '#000000',
				'sidebar_button_hover_background' => '#111111',
				'sidebar_button_active_background' => '#000000',
				'sidebar_accent_color' => '#f8c235',
				'primary_action_background' => '#8f1515',
				'primary_action_text' => '#ffffff',
				'secondary_action_background' => '#f8c235',
				'secondary_action_text' => '#000000',
				'page_background' => '#ffffff',
				'panel_background' => '#ffffff',
				'panel_border_color' => '#d6d3d1',
				'hero_panel_background' => 'rgba(0,0,0,0.34)',
				'hero_panel_border_color' => 'rgba(255,255,255,0.14)',
				'hero_chip_background' => 'rgba(0,0,0,0.38)',
				'hero_chip_border_color' => 'rgba(255,255,255,0.18)',
				'login_form_background' => 'rgba(247,241,232,0.94)',
				'login_overlay' => 'linear-gradient(180deg,rgba(3,0,0,0.24),rgba(3,0,0,0.72)),radial-gradient(circle_at_top,rgba(248,194,53,0.12),transparent 24%)',
				'home_hero_overlay' => 'linear-gradient(90deg,rgba(3,0,0,0.88) 0%,rgba(3,0,0,0.72) 38%,rgba(3,0,0,0.4) 62%,rgba(3,0,0,0.58) 100%)',
				'home_hero_tint_overlay' => 'linear-gradient(to top, rgba(3,0,0,0.5), transparent, rgba(3,0,0,0.16))',
				'join_hero_overlay' => 'linear-gradient(90deg,rgba(3,0,0,0.88) 0%,rgba(3,0,0,0.72) 38%,rgba(3,0,0,0.4) 62%,rgba(3,0,0,0.58) 100%)',
				'join_hero_tint_overlay' => 'linear-gradient(to top, rgba(3,0,0,0.56), transparent, rgba(3,0,0,0.18))',
				'nav_background' => '#030000',
				'nav_text_color' => '#ffffff',
				'nav_hover_text_color' => '#f8c235',
				'nav_icon_color' => '#f8c235',
				'nav_dropdown_background' => 'rgba(11,9,8,0.95)',
				'nav_dropdown_text_color' => '#f4efe7',
				'join_hero_image_url' => plugins_url('../app/assets/join-hero-static-image.jpg', __FILE__),
				'home_hero_video_url' => 'https://player.vimeo.com/video/1166009381?h=c4c3248b38&background=1&autoplay=1&muted=1&loop=1&autopause=0&controls=0&title=0&byline=0&portrait=0',
				'join_hero_video_url' => '',
				'login_background_image_url' => plugins_url('../app/assets/join-hero-static-image.jpg', __FILE__),
				'home_intro_image_url' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-2.jpg',
				'home_intro_accent_image_url' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-3.jpg',
				'publication_tile_image_aaj' => '',
				'publication_tile_image_anac' => '',
				'publication_tile_image_acj' => '',
				'publication_tile_image_guidebook' => '',
			],
			'components' => [
				'section_titles' => [
					'your_portal' => 'Member',
				],
				'home_sections' => self::get_default_home_sections(),
				'top_nav_items' => self::get_default_top_nav_items(),
				'sidebar_items' => self::get_default_sidebar_items(),
			],
		];
	}

	public static function get_default_home_sections() {
		return [
			'hero' => ['label' => 'Hero', 'order' => 1, 'visible' => 1],
			'intro' => ['label' => 'Intro', 'order' => 2, 'visible' => 1],
			'involvement' => ['label' => 'Get Involved', 'order' => 3, 'visible' => 1],
			'publications' => ['label' => 'Publications', 'order' => 4, 'visible' => 1],
			'partners' => ['label' => 'Partners', 'order' => 5, 'visible' => 1],
		];
	}

	public static function get_signup_level_benefit_catalog() {
		return [
			'aac_support' => 'Support for AAC Advocacy, Education, & Member Services',
			'tshirt' => 'AAC T-shirt',
			'discounts' => 'Discounts: Gear, Gym, & Guide Services',
			'library' => 'AAC Library',
			'rescue_coverage' => 'Rescue Coverage',
			'medical_expense_coverage' => 'Medical Expense Coverage',
			'mortal_remains_transport' => 'Mortal Remains Transport',
			'publications' => 'AAC Publications (AAJ+Accidents+Guidebook+ACJ)',
			'limited_hardcover_aaj' => 'Limited Edition Hardcover AAJ',
		];
	}

	public static function get_signup_level_labels() {
		return [
			'Supporter' => 'Supporter',
			'Partner' => 'Partner',
			'Leader' => 'Leader',
			'Advocate' => 'Advocate',
		];
	}

	public static function get_default_signup_level_benefits() {
		return [
			'Supporter' => [
				'aac_support',
				'tshirt',
				'discounts',
				'library',
			],
			'Partner' => [
				'aac_support',
				'tshirt',
				'discounts',
				'library',
				'rescue_coverage',
				'medical_expense_coverage',
				'mortal_remains_transport',
				'publications',
			],
			'Leader' => [
				'aac_support',
				'tshirt',
				'discounts',
				'library',
				'rescue_coverage',
				'medical_expense_coverage',
				'mortal_remains_transport',
				'publications',
			],
			'Advocate' => [
				'aac_support',
				'tshirt',
				'discounts',
				'library',
				'rescue_coverage',
				'medical_expense_coverage',
				'mortal_remains_transport',
				'publications',
				'limited_hardcover_aaj',
			],
		];
	}

	public static function get_default_home_involvement_cards() {
		return [
			[
				'title' => 'Join the Club',
				'description' => 'Membership supports AAC advocacy, climbing knowledge, publications, partner benefits, and the wider climbing community.',
				'button_label' => 'Join Now',
				'button_url' => self::get_join_page_url(),
				'image_url' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-4.jpg',
				'accent_style' => 'gold',
			],
		];
	}

	public static function get_default_home_publication_cards() {
		return [
			[
				'title' => 'American Alpine Journal',
				'description' => 'Long-form reporting on major climbs around the world, presented in AAC’s flagship publication.',
				'button_label' => 'View Publication',
				'button_url' => 'https://americanalpine.wpenginepowered.com/publications/aaj/',
				'image_url' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-95.jpeg',
				'accent_color' => '#f8c235',
			],
			[
				'title' => 'Accidents in North American Climbing',
				'description' => 'Annual accident analysis and takeaways that help climbers learn from the year’s most important incidents.',
				'button_label' => 'View Publication',
				'button_url' => 'https://americanalpine.wpenginepowered.com/publications/accidents/',
				'image_url' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-28.jpeg',
				'accent_color' => '#b20710',
			],
		];
	}

	public static function get_default_home_partner_logos() {
		return [
			[
				'name' => 'American Alpine Club',
				'image_url' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/09/dark-header-logo.svg',
				'link_url' => 'https://americanalpine.wpenginepowered.com/',
			],
			[
				'name' => 'Backcountry',
				'image_url' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Filler-Logo-2.png',
				'link_url' => '',
			],
			[
				'name' => 'Black Diamond',
				'image_url' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Filler-Logo-1.png',
				'link_url' => '',
			],
		];
	}

	public static function get_default_discount_cards() {
		$seed_path = __DIR__ . '/data/discount-cards-seed.json';
		if (!file_exists($seed_path)) {
			return [];
		}

		$seed_cards = json_decode((string) file_get_contents($seed_path), true);
		return is_array($seed_cards) ? $seed_cards : [];
	}

	public static function get_default_benefits_gallery_items() {
		return [
			[
				'id' => 'discounts',
				'title' => 'Discounts',
				'image_url' => 'https://images.unsplash.com/photo-1516592673884-4a382d1124c2?auto=format&fit=crop&w=1200&q=82',
				'url' => '',
				'action_label' => 'Explore Discounts',
				'description' => 'Climbing can be expensive. AAC members get deep discounts from 300+ outdoor brands, as well as savings at AAC lodging facilities, partner climbing gyms, and guide services across the country. Discounts may vary by membership level.',
			],
			[
				'id' => 'rescue',
				'title' => 'Rescue',
				'image_url' => 'https://images.unsplash.com/photo-1534621107955-b06bbc17b043?auto=format&fit=crop&w=1200&q=82',
				'url' => '/rescue',
				'action_label' => 'Open Rescue Benefits',
				'description' => 'With your newly enhanced rescue and medical expense coverage, you can tie in a little easier knowing the Club has got your back. As a Leader level member, you receive a $300,000 rescue benefit and $5,000 in medical expense coverage.',
			],
			[
				'id' => 'publications',
				'title' => 'Books & Media',
				'image_url' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-95.jpeg',
				'url' => '/publications',
				'action_label' => 'Open Books & Media',
				'description' => 'Download digital publications, explore AAC podcasts, and catch recent climbing stories from the Club.',
			],
			[
				'id' => 'member-store',
				'title' => 'Member Store',
				'image_url' => 'https://images.unsplash.com/photo-1501555088652-021faa106b9b?auto=format&fit=crop&w=1200&q=82',
				'url' => 'https://americanalpineclub.myshopify.com/',
				'action_label' => 'Open Member Store',
				'description' => 'Shop AAC apparel, books, gifts, and member merchandise from the American Alpine Club store.',
			],
			[
				'id' => 'library',
				'title' => 'Library',
				'image_url' => 'https://images.unsplash.com/photo-1521587760476-6c12a4b040da?auto=format&fit=crop&w=1200&q=82',
				'url' => 'https://americanalpine.wpenginepowered.com/library/',
				'action_label' => 'Open Library',
				'description' => 'For climbing bibliophiles. The Club has one of the most extensive climbing libraries in the world. The library is housed in Golden, CO, but we’ll ship you whatever you want to read, for free!',
			],
			[
				'id' => 'lodging',
				'title' => 'Lodging',
				'image_url' => 'https://images.unsplash.com/photo-1518780664697-55e3ad937233?auto=format&fit=crop&w=1200&q=82',
				'url' => 'https://americanalpine.wpenginepowered.com/lodging/',
				'action_label' => 'Open Lodging',
				'description' => 'Leader level members receive deep discounts at AAC lodging facilities across the country, as well as huts and affiliates throughout the globe.',
			],
			[
				'id' => 'grants',
				'title' => 'Grants',
				'image_url' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1200&q=82',
				'url' => 'https://americanalpine.wpenginepowered.com/grants/',
				'action_label' => 'Open Grants',
				'description' => 'Cash for climbing. The Club has a storied legacy of funding climbing-related projects-from expeditions to research-in support of our mission. With more than $175,000 in annual awards, you do not have to be a pro to get your climbing dream funded.',
			],
		];
	}

	public static function get_default_contact_issue_types() {
		return [
			'Membership Issues',
			'Cancellation',
			'Discount Codes',
		];
	}

	public static function normalize_contact_issue_types($items) {
		$items = is_array($items) ? $items : [];
		$normalized = [];

		foreach ($items as $item) {
			$label = sanitize_text_field((string) $item);
			$label = trim($label);
			if ($label === '') {
				continue;
			}

			if (!in_array($label, $normalized, true)) {
				$normalized[] = $label;
			}
		}

		return !empty($normalized) ? $normalized : self::get_default_contact_issue_types();
	}

	public static function normalize_benefits_gallery_items($items) {
		$items = is_array($items) ? $items : [];
		$normalized = [];

		foreach (self::get_default_benefits_gallery_items() as $default_item) {
			$id = sanitize_key($default_item['id'] ?? '');
			if ($id === '') {
				continue;
			}

			$raw_item = [];
			if (isset($items[$id]) && is_array($items[$id])) {
				$raw_item = $items[$id];
			} else {
				foreach ($items as $candidate) {
					if (is_array($candidate) && sanitize_key($candidate['id'] ?? '') === $id) {
						$raw_item = $candidate;
						break;
					}
				}
			}

			$raw_title = trim((string) ($raw_item['title'] ?? ''));
			$raw_url = trim((string) ($raw_item['url'] ?? ''));
			$raw_action_label = trim((string) ($raw_item['action_label'] ?? $raw_item['actionLabel'] ?? ''));
			$raw_description = (string) ($raw_item['description'] ?? '');
			$is_books_media_item = $id === 'publications';
			$title = ($is_books_media_item && ($raw_title === '' || $raw_title === 'Publications'))
				? $default_item['title']
				: ($raw_item['title'] ?? $default_item['title']);
			$url = ($is_books_media_item && ($raw_url === '' || strpos($raw_url, '/publications/') !== false))
				? $default_item['url']
				: ($raw_item['url'] ?? $default_item['url']);
			$action_label = ($is_books_media_item && $raw_action_label === 'Open Publications')
				? $default_item['action_label']
				: ($raw_item['action_label'] ?? $raw_item['actionLabel'] ?? $default_item['action_label']);
			$description = ($is_books_media_item && strpos($raw_description, 'digital copies of Accidents') !== false)
				? $default_item['description']
				: ($raw_item['description'] ?? $default_item['description']);

			$normalized[] = [
				'id' => $id,
				'title' => sanitize_text_field($title),
				'image_url' => esc_url_raw($raw_item['image_url'] ?? $raw_item['imageUrl'] ?? $default_item['image_url']),
				'url' => esc_url_raw($url),
				'action_label' => sanitize_text_field($action_label),
				'description' => sanitize_textarea_field($description),
			];
		}

		return $normalized;
	}

	public static function get_default_rescue_levels() {
		// Rescue benefits are editable in admin, but these defaults make sure a fresh
		// install still has a sane matrix instead of a blank screen and some panic.
		return [
			[
				'level_name' => 'Free',
				'rescue_amount' => 0,
				'medical_amount' => 0,
				'mortal_remains_amount' => 0,
				'rescue_reimbursement_process' => false,
			],
			[
				'level_name' => 'Supporter',
				'rescue_amount' => 0,
				'medical_amount' => 0,
				'mortal_remains_amount' => 0,
				'rescue_reimbursement_process' => false,
			],
			[
				'level_name' => 'Partner',
				'rescue_amount' => 7500,
				'medical_amount' => 5000,
				'mortal_remains_amount' => 15000,
				'rescue_reimbursement_process' => true,
			],
			[
				'level_name' => 'Leader',
				'rescue_amount' => 300000,
				'medical_amount' => 5000,
				'mortal_remains_amount' => 15000,
				'rescue_reimbursement_process' => true,
			],
			[
				'level_name' => 'Advocate',
				'rescue_amount' => 300000,
				'medical_amount' => 5000,
				'mortal_remains_amount' => 15000,
				'rescue_reimbursement_process' => true,
			],
			[
				'level_name' => 'GRF',
				'rescue_amount' => 300000,
				'medical_amount' => 5000,
				'mortal_remains_amount' => 15000,
				'rescue_reimbursement_process' => true,
			],
			[
				'level_name' => 'Lifetime',
				'rescue_amount' => 300000,
				'medical_amount' => 5000,
				'mortal_remains_amount' => 15000,
				'rescue_reimbursement_process' => true,
			],
		];
	}

	public static function get_default_top_nav_items() {
		return [
			'membership' => ['label' => 'Membership', 'order' => 1, 'visible' => 1, 'children' => []],
			'stories_news' => ['label' => 'Stories & News', 'order' => 2, 'visible' => 1, 'children' => []],
			'publications' => ['label' => 'Publications', 'order' => 3, 'visible' => 1, 'children' => []],
			'our_work' => ['label' => 'Our Work', 'order' => 4, 'visible' => 1, 'children' => []],
		];
	}

	public static function get_default_member_profile_card_sections() {
		return [
			'membership_card' => ['label' => 'Membership Card', 'visible' => 1],
			'profile_information' => ['label' => 'Profile Information', 'visible' => 1],
			'membership_snapshot' => ['label' => 'Membership Snapshot', 'visible' => 1],
			'redpoint_benefits' => ['label' => 'Redpoint Benefits', 'visible' => 1],
			'linked_accounts' => ['label' => 'Linked Accounts', 'visible' => 1],
			'custom_blocks' => ['label' => 'Custom Member Profile Blocks', 'visible' => 1],
		];
	}

	public static function get_default_sidebar_items() {
		return [
			'member_profile' => ['label' => 'Member Profile', 'section' => 'your_portal', 'order' => 1, 'visible' => 1],
			'account' => ['label' => 'Settings', 'section' => 'your_portal', 'order' => 2, 'visible' => 1],
			'manage' => ['label' => 'Billing', 'section' => 'your_portal', 'order' => 3, 'visible' => 1],
			'discounts' => ['label' => 'Benefits', 'section' => 'your_portal', 'order' => 4, 'visible' => 1],
			'contact' => ['label' => 'Contact Us', 'section' => 'your_portal', 'order' => 5, 'visible' => 1],
			'publications' => ['label' => 'Books & Media', 'section' => 'your_portal', 'order' => 6, 'visible' => 0],
		];
	}

	private static function normalize_ordered_component_items($items, $defaults) {
		$items = is_array($items) ? $items : [];
		$normalized = [];
		$count = max(1, count($defaults));
		$has_legacy_order = false;

		foreach ($defaults as $item_id => $item_defaults) {
			$item = isset($items[$item_id]) && is_array($items[$item_id])
				? array_merge($item_defaults, $items[$item_id])
				: $item_defaults;
			$item['order'] = isset($item['order']) ? (int) $item['order'] : (int) $item_defaults['order'];
			$item['visible'] = empty($item['visible']) ? 0 : 1;
			$normalized[$item_id] = $item;

			if ($item['order'] < 1 || $item['order'] > $count) {
				$has_legacy_order = true;
			}
		}

		if ($has_legacy_order) {
			foreach ($defaults as $item_id => $item_defaults) {
				$normalized[$item_id]['order'] = (int) $item_defaults['order'];
			}
			return $normalized;
		}

		uasort($normalized, static function ($left, $right) {
			return ((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0));
		});

		$order = 1;
		foreach ($normalized as $item_id => $item) {
			$normalized[$item_id]['order'] = $order++;
		}

		return $normalized;
	}

	private static function normalize_component_settings($settings) {
		$defaults = self::get_defaults();
		$settings['components']['section_titles'] = [
			'your_portal' => sanitize_text_field($settings['components']['section_titles']['your_portal'] ?? 'Member'),
		];

		$settings['components']['home_sections'] = self::normalize_ordered_component_items(
			$settings['components']['home_sections'] ?? [],
			$defaults['components']['home_sections']
		);
		$settings['components']['top_nav_items'] = self::normalize_ordered_component_items(
			$settings['components']['top_nav_items'] ?? [],
			$defaults['components']['top_nav_items']
		);
		$settings['components']['sidebar_items'] = self::normalize_ordered_component_items(
			$settings['components']['sidebar_items'] ?? [],
			$defaults['components']['sidebar_items']
		);

		if (
			isset($settings['components']['sidebar_items']['account']['label']) &&
			in_array($settings['components']['sidebar_items']['account']['label'], ['Account', 'Member Details', 'Profile Information'], true)
		) {
			$settings['components']['sidebar_items']['account']['label'] = 'Settings';
		}
		if (
			isset($settings['components']['sidebar_items']['publications']['label']) &&
			$settings['components']['sidebar_items']['publications']['label'] === 'Publications'
		) {
			$settings['components']['sidebar_items']['publications']['label'] = 'Books & Media';
		}
		if (isset($settings['components']['sidebar_items']['discounts'])) {
			$settings['components']['sidebar_items']['discounts']['label'] = 'Benefits';
			$settings['components']['sidebar_items']['discounts']['visible'] = 1;
		}

		foreach ($settings['components']['sidebar_items'] as $item_id => $item_settings) {
			$settings['components']['sidebar_items'][$item_id]['section'] = 'your_portal';
		}

		return $settings;
	}

	public static function get_settings($option_key) {
		$stored = get_option($option_key, []);
		$stored = is_array($stored) ? $stored : [];
		$defaults = self::get_defaults();
		$settings = self::prune_to_default_keys(self::merge_with_defaults($defaults, $stored), $defaults);
		$join_page_url = self::get_join_page_url();

		foreach (['home_primary_cta_url', 'home_involvement_button_url'] as $join_url_field) {
			if (isset($settings['content'][$join_url_field]) && self::is_legacy_join_url($settings['content'][$join_url_field])) {
				$settings['content'][$join_url_field] = $join_page_url;
			}
		}

		$settings = self::normalize_component_settings($settings);

		$settings['content']['rescue_levels'] = isset($settings['content']['rescue_levels']) && is_array($settings['content']['rescue_levels']) && !empty($settings['content']['rescue_levels'])
			? array_values($settings['content']['rescue_levels'])
			: self::get_default_rescue_levels();
		$settings['content']['signup_level_benefits'] = isset($settings['content']['signup_level_benefits']) && is_array($settings['content']['signup_level_benefits'])
			? self::normalize_signup_level_benefits($settings['content']['signup_level_benefits'])
			: self::get_default_signup_level_benefits();
		$settings['content']['contact_issue_types'] = isset($settings['content']['contact_issue_types']) && is_array($settings['content']['contact_issue_types'])
			? self::normalize_contact_issue_types($settings['content']['contact_issue_types'])
			: self::get_default_contact_issue_types();
		$settings['content']['benefits_gallery_items'] = isset($settings['content']['benefits_gallery_items']) && is_array($settings['content']['benefits_gallery_items'])
			? self::normalize_benefits_gallery_items($settings['content']['benefits_gallery_items'])
			: self::get_default_benefits_gallery_items();
		$settings['content']['home_involvement_cards'] = isset($settings['content']['home_involvement_cards']) && is_array($settings['content']['home_involvement_cards']) && !empty($settings['content']['home_involvement_cards'])
			? array_values($settings['content']['home_involvement_cards'])
			: self::get_default_home_involvement_cards();
		foreach ($settings['content']['home_involvement_cards'] as $card_index => $card) {
			if (isset($card['button_url']) && self::is_legacy_join_url($card['button_url'])) {
				$settings['content']['home_involvement_cards'][$card_index]['button_url'] = $join_page_url;
			}
		}
		$settings['content']['home_publication_cards'] = isset($settings['content']['home_publication_cards']) && is_array($settings['content']['home_publication_cards']) && !empty($settings['content']['home_publication_cards'])
			? array_values($settings['content']['home_publication_cards'])
			: self::get_default_home_publication_cards();
		$settings['content']['home_partner_logos'] = isset($settings['content']['home_partner_logos']) && is_array($settings['content']['home_partner_logos']) && !empty($settings['content']['home_partner_logos'])
			? array_values($settings['content']['home_partner_logos'])
			: self::get_default_home_partner_logos();
		return $settings;
	}

	public static function sanitize_settings($input, $current = []) {
		$defaults = self::get_defaults();
		$current = is_array($current) ? $current : [];
		$input = is_array($input) ? $input : [];
		$settings = self::merge_with_defaults($defaults, $current);

		$content_input = isset($input['content']) && is_array($input['content']) ? $input['content'] : [];
		$text_fields = [
			'home_hero_kicker',
			'home_hero_title',
			'home_primary_cta_label',
			'home_secondary_cta_label',
			'home_tertiary_cta_label',
			'home_membership_chip_kicker',
			'home_intro_kicker',
			'home_intro_title',
			'home_intro_button_label',
			'home_involvement_kicker',
			'home_involvement_title',
			'home_involvement_button_label',
			'home_publications_kicker',
			'home_publications_title',
			'home_publications_button_label',
			'home_partners_kicker',
			'home_partners_title',
			'account_settings_title',
			'profile_information_title',
			'membership_snapshot_title',
			'linked_accounts_title',
			'discounts_title',
			'discounts_locked_title',
			'discounts_button_label',
			'update_profile_button_label',
			'publications_title',
			'publications_locked_title',
			'publications_upgrade_button_label',
			'join_hero_kicker',
			'join_hero_title',
			'join_primary_cta_label',
			'join_benefits_cta_label',
			'join_application_kicker',
			'join_application_title',
			'join_redeem_code_button_label',
			'login_hero_kicker',
			'login_hero_title',
			'login_form_kicker',
			'login_form_title',
			'login_submit_label',
			'login_forgot_password_label',
			'login_join_link_label',
			'linked_accounts_page_title',
			'linked_accounts_lookup_button_label',
			'linked_accounts_redeem_button_label',
			'portal_preferences_title',
			'quick_actions_title',
			'confirmation_letter_format',
		];
		foreach ($text_fields as $field) {
			if (array_key_exists($field, $content_input)) {
				$settings['content'][$field] = sanitize_text_field($content_input[$field]);
			}
		}

		$textarea_fields = [
			'home_hero_description',
			'home_membership_chip_description',
			'home_intro_description',
			'home_intro_secondary_description',
			'home_partners_description',
			'profile_information_description',
			'membership_snapshot_description',
			'linked_accounts_description',
			'discounts_locked_description',
			'discounts_free_locked_description',
			'discounts_upgrade_hint',
			'publications_description',
			'publications_locked_description',
			'join_hero_description',
			'join_application_description',
			'login_hero_description',
			'login_purchase_success_message',
			'linked_accounts_page_description',
			'linked_accounts_success_message',
			'portal_preferences_description',
			'quick_actions_description',
			'confirmation_letter_body',
		];
		foreach ($textarea_fields as $field) {
			if (array_key_exists($field, $content_input)) {
				$settings['content'][$field] = sanitize_textarea_field($content_input[$field]);
			}
		}

		if (array_key_exists('confirmation_letter_format', $content_input)) {
			$format = sanitize_key($content_input['confirmation_letter_format']);
			$settings['content']['confirmation_letter_format'] = in_array($format, ['standard', 'compact'], true)
				? $format
				: $defaults['content']['confirmation_letter_format'];
		}

		if (array_key_exists('contact_recipient_email', $content_input)) {
			$contact_email = sanitize_email($content_input['contact_recipient_email']);
			$settings['content']['contact_recipient_email'] = $contact_email && is_email($contact_email)
				? $contact_email
				: $defaults['content']['contact_recipient_email'];
		}

		if (array_key_exists('contact_issue_types', $content_input)) {
			$settings['content']['contact_issue_types'] = self::normalize_contact_issue_types($content_input['contact_issue_types']);
		}

		$url_fields = [
			'home_primary_cta_url',
			'home_secondary_cta_url',
			'home_tertiary_cta_url',
			'home_intro_button_url',
			'home_involvement_button_url',
			'home_publications_button_url',
			'publication_view_url_aaj',
			'publication_view_url_anac',
			'publication_view_url_acj',
			'publication_view_url_guidebook',
			'signup_benefits_matrix_image_url',
		];
		foreach ($url_fields as $field) {
			if (array_key_exists($field, $content_input)) {
				$settings['content'][$field] = esc_url_raw($content_input[$field]);
			}
		}

		// Repeater fields are the feral cousins of simple text fields. They come in
		// as list arrays, so we sanitize them in their own lane before saving.
		if (isset($content_input['benefits_gallery_items']) && is_array($content_input['benefits_gallery_items'])) {
			$settings['content']['benefits_gallery_items'] = self::sanitize_benefits_gallery_items($content_input['benefits_gallery_items']);
		}

		if (isset($content_input['discount_cards']) && is_array($content_input['discount_cards'])) {
			$settings['content']['discount_cards'] = self::sanitize_discount_cards($content_input['discount_cards']);
		}

		if (isset($content_input['rescue_levels']) && is_array($content_input['rescue_levels'])) {
			$settings['content']['rescue_levels'] = self::sanitize_rescue_levels($content_input['rescue_levels']);
		}
		if (isset($content_input['signup_level_benefits']) && is_array($content_input['signup_level_benefits'])) {
			$settings['content']['signup_level_benefits'] = self::sanitize_signup_level_benefits($content_input['signup_level_benefits']);
		}
		if (isset($content_input['home_involvement_cards']) && is_array($content_input['home_involvement_cards'])) {
			$settings['content']['home_involvement_cards'] = self::sanitize_home_involvement_cards($content_input['home_involvement_cards']);
		}
		if (isset($content_input['home_publication_cards']) && is_array($content_input['home_publication_cards'])) {
			$settings['content']['home_publication_cards'] = self::sanitize_home_publication_cards($content_input['home_publication_cards']);
		}
		if (isset($content_input['home_partner_logos']) && is_array($content_input['home_partner_logos'])) {
			$settings['content']['home_partner_logos'] = self::sanitize_home_partner_logos($content_input['home_partner_logos']);
		}
		if (isset($content_input['member_profile_blocks']) && is_array($content_input['member_profile_blocks'])) {
			$settings['content']['member_profile_blocks'] = self::sanitize_member_profile_blocks($content_input['member_profile_blocks']);
		}
		if (isset($content_input['member_profile_card_sections']) && is_array($content_input['member_profile_card_sections'])) {
			$settings['content']['member_profile_card_sections'] = self::sanitize_member_profile_card_sections($content_input['member_profile_card_sections']);
		}

		$design_input = isset($input['design']) && is_array($input['design']) ? $input['design'] : [];
		$design_url_fields = [
			'sidebar_background_url',
			'join_hero_image_url',
			'home_hero_video_url',
			'join_hero_video_url',
			'login_background_image_url',
			'home_intro_image_url',
			'home_intro_accent_image_url',
			'publication_tile_image_aaj',
			'publication_tile_image_anac',
			'publication_tile_image_acj',
			'publication_tile_image_guidebook',
		];
		foreach ($design_url_fields as $field) {
			if (array_key_exists($field, $design_input)) {
				$settings['design'][$field] = esc_url_raw($design_input[$field]);
			}
		}

		$color_fields = [
			'sidebar_button_background',
			'sidebar_button_hover_background',
			'sidebar_button_active_background',
			'sidebar_accent_color',
			'primary_action_background',
			'primary_action_text',
			'secondary_action_background',
			'secondary_action_text',
		];
		foreach ($color_fields as $field) {
			if (array_key_exists($field, $design_input)) {
				$settings['design'][$field] = self::sanitize_hex_color_or_default($design_input[$field], $defaults['design'][$field]);
			}
		}

		$token_fields = [
			'page_background',
			'panel_background',
			'panel_border_color',
			'hero_panel_background',
			'hero_panel_border_color',
			'hero_chip_background',
			'hero_chip_border_color',
			'login_form_background',
			'login_overlay',
			'home_hero_overlay',
			'home_hero_tint_overlay',
			'join_hero_overlay',
			'join_hero_tint_overlay',
			'nav_background',
			'nav_text_color',
			'nav_hover_text_color',
			'nav_icon_color',
			'nav_dropdown_background',
			'nav_dropdown_text_color',
		];
		foreach ($token_fields as $field) {
			if (array_key_exists($field, $design_input)) {
				$settings['design'][$field] = sanitize_text_field($design_input[$field]);
			}
		}
		if (array_key_exists('sidebar_overlay_start', $design_input)) {
			$settings['design']['sidebar_overlay_start'] = self::sanitize_opacity($design_input['sidebar_overlay_start']);
		}
		if (array_key_exists('sidebar_overlay_end', $design_input)) {
			$settings['design']['sidebar_overlay_end'] = self::sanitize_opacity($design_input['sidebar_overlay_end']);
		}

		$components_input = isset($input['components']) && is_array($input['components']) ? $input['components'] : [];
		$section_titles = isset($components_input['section_titles']) && is_array($components_input['section_titles']) ? $components_input['section_titles'] : null;
		if ($section_titles !== null) {
			foreach ($defaults['components']['section_titles'] as $section_id => $default_title) {
				if (array_key_exists($section_id, $section_titles)) {
					$settings['components']['section_titles'][$section_id] = sanitize_text_field($section_titles[$section_id]);
				}
			}
		}

		$top_nav_items = isset($components_input['top_nav_items']) && is_array($components_input['top_nav_items']) ? $components_input['top_nav_items'] : null;
		if ($top_nav_items !== null) {
			foreach ($defaults['components']['top_nav_items'] as $item_id => $item_defaults) {
				$item_input = isset($top_nav_items[$item_id]) && is_array($top_nav_items[$item_id]) ? $top_nav_items[$item_id] : [];
				$children = self::sanitize_top_nav_children(isset($item_input['children_text']) ? (string) $item_input['children_text'] : (isset($item_input['children']) && is_array($item_input['children']) ? $item_input['children'] : []));
				$settings['components']['top_nav_items'][$item_id] = [
					'label' => sanitize_text_field($item_input['label'] ?? $settings['components']['top_nav_items'][$item_id]['label']),
					'order' => isset($item_input['order']) ? (int) $item_input['order'] : (int) $settings['components']['top_nav_items'][$item_id]['order'],
					'visible' => empty($item_input['visible']) ? 0 : 1,
					'children' => $children,
				];
			}
		}

		$sidebar_items = isset($components_input['sidebar_items']) && is_array($components_input['sidebar_items']) ? $components_input['sidebar_items'] : null;
		if ($sidebar_items !== null) {
			foreach ($defaults['components']['sidebar_items'] as $item_id => $item_defaults) {
				$item_input = isset($sidebar_items[$item_id]) && is_array($sidebar_items[$item_id]) ? $sidebar_items[$item_id] : [];
				$section = sanitize_key($item_input['section'] ?? $settings['components']['sidebar_items'][$item_id]['section']);
				if (!isset($defaults['components']['section_titles'][$section])) {
					$section = $item_defaults['section'];
				}

				$settings['components']['sidebar_items'][$item_id] = [
					'label' => sanitize_text_field($item_input['label'] ?? $settings['components']['sidebar_items'][$item_id]['label']),
					'section' => $section,
					'order' => isset($item_input['order']) ? (int) $item_input['order'] : (int) $settings['components']['sidebar_items'][$item_id]['order'],
					'visible' => empty($item_input['visible']) ? 0 : 1,
				];
			}
		}

		$home_sections = isset($components_input['home_sections']) && is_array($components_input['home_sections']) ? $components_input['home_sections'] : null;
		if ($home_sections !== null) {
			foreach ($defaults['components']['home_sections'] as $section_id => $section_defaults) {
				$section_input = isset($home_sections[$section_id]) && is_array($home_sections[$section_id]) ? $home_sections[$section_id] : [];
				$settings['components']['home_sections'][$section_id] = [
					'label' => sanitize_text_field($section_input['label'] ?? $settings['components']['home_sections'][$section_id]['label']),
					'order' => isset($section_input['order']) ? (int) $section_input['order'] : (int) $settings['components']['home_sections'][$section_id]['order'],
					'visible' => empty($section_input['visible']) ? 0 : 1,
				];
			}
		}

		return self::normalize_component_settings(self::merge_with_defaults($defaults, $settings));
	}

	public static function sanitize_discount_cards($cards) {
		$sanitized_cards = [];
		foreach ($cards as $card) {
			if (!is_array($card)) {
				continue;
			}

			$brand = sanitize_text_field($card['brand'] ?? '');
			$category = self::normalize_discount_category($card['category'] ?? '');
			$brand_tier = self::normalize_discount_brand_tier($card['brand_tier'] ?? 'middle');
			$legacy_discount_percent = sanitize_text_field($card['discount_percent'] ?? '');
			$discount_code_text = sanitize_textarea_field($card['discount_code_text'] ?? '');
			$discount_code_text_supporter = sanitize_textarea_field($card['discount_code_text_supporter'] ?? '');
			$discount_code_text_partner = sanitize_textarea_field($card['discount_code_text_partner'] ?? '');
			$discount_code_text_leader = sanitize_textarea_field($card['discount_code_text_leader'] ?? '');
			$discount_code_text_advocate = sanitize_textarea_field($card['discount_code_text_advocate'] ?? '');
			$discount_percent_supporter = sanitize_text_field($card['discount_percent_supporter'] ?? '');
			$discount_percent_partner = sanitize_text_field($card['discount_percent_partner'] ?? '');
			$discount_percent_leader = sanitize_text_field($card['discount_percent_leader'] ?? '');
			$discount_percent_advocate = sanitize_text_field($card['discount_percent_advocate'] ?? '');
			$display_text = sanitize_textarea_field($card['display_text'] ?? '');
			$button_url = esc_url_raw($card['button_url'] ?? '');
			$image_url = esc_url_raw($card['image_url'] ?? '');
			$visible_tiers = self::normalize_discount_visible_tiers($card['visible_tiers'] ?? null);

			if (
				$brand === '' &&
				$legacy_discount_percent === '' &&
				$discount_code_text === '' &&
				$discount_code_text_supporter === '' &&
				$discount_code_text_partner === '' &&
				$discount_code_text_leader === '' &&
				$discount_code_text_advocate === '' &&
				$discount_percent_supporter === '' &&
				$discount_percent_partner === '' &&
				$discount_percent_leader === '' &&
				$discount_percent_advocate === '' &&
				$display_text === '' &&
				$button_url === '' &&
				$image_url === ''
			) {
				continue;
			}

			$sanitized_cards[] = [
				'brand' => $brand,
				'category' => $category,
				'brand_tier' => $brand_tier,
				'discount_percent' => '',
				'discount_code_text' => $discount_code_text,
				'discount_code_text_supporter' => $discount_code_text_supporter !== '' ? $discount_code_text_supporter : $discount_code_text,
				'discount_code_text_partner' => $discount_code_text_partner !== '' ? $discount_code_text_partner : $discount_code_text,
				'discount_code_text_leader' => $discount_code_text_leader !== '' ? $discount_code_text_leader : $discount_code_text,
				'discount_code_text_advocate' => $discount_code_text_advocate !== '' ? $discount_code_text_advocate : $discount_code_text,
				'discount_percent_supporter' => $discount_percent_supporter !== '' ? $discount_percent_supporter : $legacy_discount_percent,
				'discount_percent_partner' => $discount_percent_partner !== '' ? $discount_percent_partner : $legacy_discount_percent,
				'discount_percent_leader' => $discount_percent_leader !== '' ? $discount_percent_leader : $legacy_discount_percent,
				'discount_percent_advocate' => $discount_percent_advocate !== '' ? $discount_percent_advocate : $legacy_discount_percent,
				'visible_tiers' => $visible_tiers,
				'display_text' => $display_text,
				'button_url' => $button_url,
				'image_url' => $image_url,
			];
		}

		return $sanitized_cards;
	}

	public static function sanitize_benefits_gallery_items($items) {
		return self::normalize_benefits_gallery_items($items);
	}

	public static function get_discount_visibility_tiers() {
		return [
			'supporter' => 'Supporter',
			'partner' => 'Partner',
			'leader' => 'Leader',
			'advocate' => 'Advocate / GRF / Lifetime',
		];
	}

	public static function get_discount_categories() {
		return [
			'discount-brands' => 'Discount Brands',
			'expertvoice' => 'ExpertVoice',
			'climbing-guides' => 'Climbing Guides',
			'climbing-gyms' => 'Climbing Gym Discounts',
		];
	}

	public static function get_discount_brand_tiers() {
		return [
			'top' => 'Top Brand',
			'middle' => 'Middle Brand',
			'lower' => 'Lower Brand',
		];
	}

	public static function normalize_discount_brand_tier($brand_tier) {
		$brand_tier = sanitize_key(str_replace('_', '-', (string) $brand_tier));
		$aliases = [
			'top-brand' => 'top',
			'featured' => 'top',
			'primary' => 'top',
			'middle-brand' => 'middle',
			'lower-brand' => 'lower',
			'secondary' => 'lower',
		];
		if (isset($aliases[$brand_tier])) {
			$brand_tier = $aliases[$brand_tier];
		}

		return array_key_exists($brand_tier, self::get_discount_brand_tiers()) ? $brand_tier : 'middle';
	}

	public static function normalize_discount_category($category) {
		$category = sanitize_key(str_replace('_', '-', (string) $category));
		$aliases = [
			'brands' => 'discount-brands',
			'brand-discounts' => 'discount-brands',
			'discounts' => 'discount-brands',
			'expert-voice' => 'expertvoice',
			'guides' => 'climbing-guides',
			'guide-discounts' => 'climbing-guides',
			'gyms' => 'climbing-gyms',
			'gym-discounts' => 'climbing-gyms',
			'climbing-gym-discounts' => 'climbing-gyms',
		];
		if (isset($aliases[$category])) {
			$category = $aliases[$category];
		}

		return array_key_exists($category, self::get_discount_categories()) ? $category : 'discount-brands';
	}

	public static function normalize_discount_visible_tiers($visible_tiers) {
		$tier_keys = array_keys(self::get_discount_visibility_tiers());
		if (!is_array($visible_tiers)) {
			return array_fill_keys($tier_keys, 1);
		}

		$normalized = [];
		foreach ($tier_keys as $tier_key) {
			$normalized[$tier_key] = empty($visible_tiers[$tier_key]) ? 0 : 1;
		}

		return $normalized;
	}

	public static function sanitize_home_involvement_cards($cards) {
		$sanitized = [];
		foreach ($cards as $card) {
			if (!is_array($card)) {
				continue;
			}

			$title = sanitize_text_field($card['title'] ?? '');
			$description = sanitize_textarea_field($card['description'] ?? '');
			$button_label = sanitize_text_field($card['button_label'] ?? '');
			$button_url = esc_url_raw($card['button_url'] ?? '');
			$image_url = esc_url_raw($card['image_url'] ?? '');
			$accent_style = sanitize_key($card['accent_style'] ?? 'gold');
			if (!in_array($accent_style, ['gold', 'light', 'sand', 'dark'], true)) {
				$accent_style = 'gold';
			}

			if ($title === '' && $description === '' && $button_label === '' && $button_url === '' && $image_url === '') {
				continue;
			}

			$sanitized[] = [
				'title' => $title,
				'description' => $description,
				'button_label' => $button_label,
				'button_url' => $button_url,
				'image_url' => $image_url,
				'accent_style' => $accent_style,
			];
		}

		return !empty($sanitized) ? $sanitized : self::get_default_home_involvement_cards();
	}

	public static function sanitize_home_publication_cards($cards) {
		$sanitized = [];
		foreach ($cards as $card) {
			if (!is_array($card)) {
				continue;
			}

			$title = sanitize_text_field($card['title'] ?? '');
			$description = sanitize_textarea_field($card['description'] ?? '');
			$button_label = sanitize_text_field($card['button_label'] ?? '');
			$button_url = esc_url_raw($card['button_url'] ?? '');
			$image_url = esc_url_raw($card['image_url'] ?? '');
			$accent_color = sanitize_text_field($card['accent_color'] ?? '');

			if ($title === '' && $description === '' && $button_label === '' && $button_url === '' && $image_url === '') {
				continue;
			}

			$sanitized[] = [
				'title' => $title,
				'description' => $description,
				'button_label' => $button_label,
				'button_url' => $button_url,
				'image_url' => $image_url,
				'accent_color' => $accent_color,
			];
		}

		return !empty($sanitized) ? $sanitized : self::get_default_home_publication_cards();
	}

	public static function sanitize_home_partner_logos($logos) {
		$sanitized = [];
		foreach ($logos as $logo) {
			if (!is_array($logo)) {
				continue;
			}

			$name = sanitize_text_field($logo['name'] ?? '');
			$image_url = esc_url_raw($logo['image_url'] ?? '');
			$link_url = esc_url_raw($logo['link_url'] ?? '');

			if ($name === '' && $image_url === '' && $link_url === '') {
				continue;
			}

			$sanitized[] = [
				'name' => $name,
				'image_url' => $image_url,
				'link_url' => $link_url,
			];
		}

		return !empty($sanitized) ? $sanitized : self::get_default_home_partner_logos();
	}

	public static function sanitize_member_profile_blocks($blocks) {
		$sanitized = [];
		foreach ($blocks as $block) {
			if (!is_array($block)) {
				continue;
			}

			$title = sanitize_text_field($block['title'] ?? '');
			$description = sanitize_textarea_field($block['description'] ?? '');
			$button_label = sanitize_text_field($block['button_label'] ?? '');
			$button_url = esc_url_raw($block['button_url'] ?? '');
			$icon = sanitize_key($block['icon'] ?? 'receipt');
			if (!in_array($icon, ['receipt', 'user', 'shield', 'users', 'heart', 'credit-card', 'calendar'], true)) {
				$icon = 'receipt';
			}

			$entries = [];
			if (isset($block['entries']) && is_array($block['entries'])) {
				foreach ($block['entries'] as $entry) {
					if (!is_array($entry)) {
						continue;
					}

					$label = sanitize_text_field($entry['label'] ?? '');
					$value = sanitize_text_field($entry['value'] ?? '');
					$entry_description = sanitize_textarea_field($entry['description'] ?? '');
					if ($label === '' && $value === '' && $entry_description === '') {
						continue;
					}

					$entries[] = [
						'label' => $label,
						'value' => $value,
						'description' => $entry_description,
					];
				}
			}

			if ($title === '' && $description === '' && $button_label === '' && $button_url === '' && empty($entries)) {
				continue;
			}

			$sanitized[] = [
				'title' => $title,
				'description' => $description,
				'button_label' => $button_label,
				'button_url' => $button_url,
				'icon' => $icon,
				'entries' => $entries,
			];
		}

		return $sanitized;
	}

	public static function sanitize_member_profile_card_sections($sections) {
		$sanitized = self::get_default_member_profile_card_sections();

		foreach ($sanitized as $section_id => $defaults) {
			$section_input = isset($sections[$section_id]) && is_array($sections[$section_id]) ? $sections[$section_id] : [];
			$sanitized[$section_id] = [
				'label' => sanitize_text_field($section_input['label'] ?? $defaults['label']),
				'visible' => empty($section_input['visible']) ? 0 : 1,
			];
		}

		return $sanitized;
	}

	public static function sanitize_top_nav_children($children_input) {
		if (is_string($children_input)) {
			$children_input = self::parse_top_nav_children_textarea($children_input);
		}

		if (!is_array($children_input)) {
			return [];
		}

		$sanitized = [];
		foreach ($children_input as $child) {
			if (!is_array($child)) {
				continue;
			}

			$label = sanitize_text_field($child['label'] ?? '');
			$href = esc_url_raw($child['href'] ?? '');
			$external = !empty($child['external']) ? 1 : 0;

			if ($label === '' || $href === '') {
				continue;
			}

			$sanitized[] = [
				'label' => $label,
				'href' => $href,
				'external' => $external,
			];
		}

		return $sanitized;
	}

	public static function parse_top_nav_children_textarea($value) {
		$lines = preg_split('/\r\n|\r|\n/', (string) $value);
		$children = [];

		foreach ((array) $lines as $line) {
			$line = trim((string) $line);
			if ($line === '') {
				continue;
			}

			$parts = array_map('trim', explode('|', $line));
			$label = $parts[0] ?? '';
			$href = $parts[1] ?? '';
			$external_flag = strtolower($parts[2] ?? '');

			if ($label === '' || $href === '') {
				continue;
			}

			$children[] = [
				'label' => $label,
				'href' => $href,
				'external' => in_array($external_flag, ['1', 'yes', 'true', 'external'], true) ? 1 : 0,
			];
		}

		return $children;
	}

	public static function format_top_nav_children_for_textarea($children) {
		if (!is_array($children) || empty($children)) {
			return '';
		}

		$lines = [];
		foreach ($children as $child) {
			if (!is_array($child)) {
				continue;
			}

			$label = sanitize_text_field($child['label'] ?? '');
			$href = esc_url_raw($child['href'] ?? '');
			if ($label === '' || $href === '') {
				continue;
			}

			$line = $label . ' | ' . $href;
			if (!empty($child['external'])) {
				$line .= ' | external';
			}
			$lines[] = $line;
		}

		return implode("\n", $lines);
	}

	public static function normalize_signup_level_benefits($benefits_by_level) {
		$catalog = self::get_signup_level_benefit_catalog();
		$levels = self::get_signup_level_labels();
		$defaults = self::get_default_signup_level_benefits();
		$is_configured_submission = !empty($benefits_by_level['_configured']);
		$normalized = [];

		foreach ($levels as $level_id => $level_label) {
			$raw_level_benefits = isset($benefits_by_level[$level_id]) && is_array($benefits_by_level[$level_id])
				? $benefits_by_level[$level_id]
				: ($is_configured_submission ? [] : ($defaults[$level_id] ?? []));

			$selected_keys = [];
			foreach ($raw_level_benefits as $key => $value) {
				if (is_string($key) && $key !== '') {
					if (!empty($value)) {
						$selected_keys[] = sanitize_key($key);
					}
					continue;
				}

				if (is_scalar($value)) {
					$selected_keys[] = sanitize_key((string) $value);
				}
			}

			$selected_lookup = array_fill_keys($selected_keys, true);
			$normalized[$level_id] = [];
			foreach ($catalog as $benefit_key => $benefit_label) {
				if (!empty($selected_lookup[$benefit_key])) {
					$normalized[$level_id][] = $benefit_key;
				}
			}
		}

		return $normalized;
	}

	public static function sanitize_signup_level_benefits($benefits_by_level) {
		return self::normalize_signup_level_benefits($benefits_by_level);
	}

	public static function sanitize_rescue_levels($levels) {
		$sanitized_levels = [];
		foreach ($levels as $level) {
			if (!is_array($level)) {
				continue;
			}

			$level_name = sanitize_text_field($level['level_name'] ?? '');
			$rescue_amount = max(0, (int) ($level['rescue_amount'] ?? 0));
			$medical_amount = max(0, (int) ($level['medical_amount'] ?? 0));
			$mortal_remains_amount = max(0, (int) ($level['mortal_remains_amount'] ?? 0));
			$rescue_reimbursement_process = !empty($level['rescue_reimbursement_process']);

			if (
				$level_name === '' &&
				$rescue_amount === 0 &&
				$medical_amount === 0 &&
				$mortal_remains_amount === 0 &&
				!$rescue_reimbursement_process
			) {
				continue;
			}

			if ($level_name === '') {
				continue;
			}

			$sanitized_levels[] = [
				'level_name' => $level_name,
				'rescue_amount' => $rescue_amount,
				'medical_amount' => $medical_amount,
				'mortal_remains_amount' => $mortal_remains_amount,
				'rescue_reimbursement_process' => $rescue_reimbursement_process,
			];
		}

		return !empty($sanitized_levels) ? $sanitized_levels : self::get_default_rescue_levels();
	}

	public static function sanitize_opacity($value) {
		$value = is_scalar($value) ? (float) $value : 0.18;
		$value = max(0, min(1, $value));
		return number_format($value, 2, '.', '');
	}

	public static function sanitize_hex_color_or_default($value, $default) {
		$sanitized = sanitize_hex_color($value);
		return $sanitized ? $sanitized : $default;
	}

	public static function merge_with_defaults($defaults, $values) {
		foreach ($defaults as $key => $default_value) {
			if (is_array($default_value)) {
				if (self::is_list_array($default_value)) {
					$values[$key] = isset($values[$key]) && is_array($values[$key]) ? array_values($values[$key]) : $default_value;
					continue;
				}

				$values[$key] = self::merge_with_defaults($default_value, isset($values[$key]) && is_array($values[$key]) ? $values[$key] : []);
				continue;
			}

			if (!array_key_exists($key, $values)) {
				$values[$key] = $default_value;
			}
		}

		return $values;
	}

	public static function prune_to_default_keys($values, $defaults) {
		if (!is_array($values) || !is_array($defaults)) {
			return $values;
		}

		if (self::is_list_array($defaults)) {
			return self::is_list_array($values) ? array_values($values) : $defaults;
		}

		foreach (array_keys($values) as $key) {
			if (!array_key_exists($key, $defaults)) {
				unset($values[$key]);
				continue;
			}

			if (is_array($values[$key]) && is_array($defaults[$key])) {
				$values[$key] = self::prune_to_default_keys($values[$key], $defaults[$key]);
			}
		}

		return $values;
	}

	public static function is_list_array($value) {
		if (!is_array($value)) {
			return false;
		}

		if (function_exists('array_is_list')) {
			return array_is_list($value);
		}

		return array_keys($value) === range(0, count($value) - 1);
	}
}
