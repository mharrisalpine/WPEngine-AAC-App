<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Member_Portal_Admin {
	const OPTION_KEY = 'aac_member_portal_settings';
	const MENU_SLUG = 'aac-member-portal-settings';
	const DISCOUNT_CARD_IMPORT_VERSION = '2026-06-25-benefit-sections-v4';
	const WONDROUS_VISUAL_SETTINGS_VERSION = '2026-08-13-billing-navigation-v1';
	const SIGNUP_COPY_SETTINGS_VERSION = '2026-07-27-signup-copy-cleanup-v1';

	public function __construct() {
		add_action('init', [$this, 'maybe_apply_wondrous_visual_settings'], 12);
		add_action('init', [$this, 'maybe_apply_signup_copy_settings'], 13);
		add_action('init', [$this, 'maybe_seed_discount_cards'], 20);
		add_action('admin_menu', [$this, 'register_admin_page']);
		add_action('admin_init', [$this, 'register_settings']);
		add_action('admin_post_aac_member_portal_backfill_pmpro_fields', [$this, 'handle_backfill_pmpro_fields']);
		add_action('admin_post_aac_member_portal_link_family_invite', [$this, 'handle_link_family_invite']);
		add_action('admin_post_aac_member_portal_add_family_member', [$this, 'handle_add_family_member']);
		add_action('admin_post_aac_member_portal_export_error_log', [$this, 'handle_export_error_log']);
		add_action('admin_post_aac_member_portal_clear_error_log', [$this, 'handle_clear_error_log']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
	}

	public static function get_defaults() {
		return AAC_Member_Portal_Settings_Schema::get_defaults();
	}

	public static function get_default_home_sections() {
		return AAC_Member_Portal_Settings_Schema::get_default_home_sections();
	}

	public static function get_signup_level_benefit_catalog() {
		return AAC_Member_Portal_Settings_Schema::get_signup_level_benefit_catalog();
	}

	public static function get_signup_level_labels() {
		return AAC_Member_Portal_Settings_Schema::get_signup_level_labels();
	}

	public static function get_default_signup_level_benefits() {
		return AAC_Member_Portal_Settings_Schema::get_default_signup_level_benefits();
	}

	public static function get_default_home_involvement_cards() {
		return AAC_Member_Portal_Settings_Schema::get_default_home_involvement_cards();
	}

	public static function get_default_home_publication_cards() {
		return AAC_Member_Portal_Settings_Schema::get_default_home_publication_cards();
	}

	public static function get_default_home_partner_logos() {
		return AAC_Member_Portal_Settings_Schema::get_default_home_partner_logos();
	}

	public static function get_default_discount_cards() {
		return AAC_Member_Portal_Settings_Schema::get_default_discount_cards();
	}

	public static function get_default_benefits_gallery_items() {
		return AAC_Member_Portal_Settings_Schema::get_default_benefits_gallery_items();
	}

	public static function get_default_contact_issue_types() {
		return AAC_Member_Portal_Settings_Schema::get_default_contact_issue_types();
	}

	public static function get_default_rescue_levels() {
		return AAC_Member_Portal_Settings_Schema::get_default_rescue_levels();
	}

	public static function get_default_top_nav_items() {
		return AAC_Member_Portal_Settings_Schema::get_default_top_nav_items();
	}

	public static function get_default_member_profile_card_sections() {
		return AAC_Member_Portal_Settings_Schema::get_default_member_profile_card_sections();
	}

	public static function get_default_sidebar_items() {
		return AAC_Member_Portal_Settings_Schema::get_default_sidebar_items();
	}

	public static function get_settings() {
		return AAC_Member_Portal_Settings_Schema::get_settings(self::OPTION_KEY);
	}

	public static function get_contact_recipient_email() {
		$settings = self::get_settings();
		$recipient_email = sanitize_email($settings['content']['contact_recipient_email'] ?? '');

		if ($recipient_email && is_email($recipient_email)) {
			return $recipient_email;
		}

		return sanitize_email(get_option('admin_email'));
	}

	public static function get_contact_issue_types() {
		$settings = self::get_settings();
		$issue_types = isset($settings['content']['contact_issue_types']) && is_array($settings['content']['contact_issue_types'])
			? $settings['content']['contact_issue_types']
			: [];

		return AAC_Member_Portal_Settings_Schema::normalize_contact_issue_types($issue_types);
	}

	public function maybe_apply_wondrous_visual_settings() {
		if (get_option('aac_member_portal_wondrous_visual_settings_version') === self::WONDROUS_VISUAL_SETTINGS_VERSION) {
			return;
		}

		$settings = self::get_settings();

		$settings['design']['sidebar_background_url'] = 'https://wallpapers.com/images/high/abstract-black-topographic-map-q34pt7luthso1030.webp';
		$settings['design']['sidebar_overlay_start'] = '0.18';
		$settings['design']['sidebar_overlay_end'] = '0.30';
		$settings['design']['page_background'] = '#ffffff';
		$settings['design']['panel_background'] = '#ffffff';

		$settings['components']['section_titles'] = [
			'your_portal' => 'Member',
		];
		$settings['components']['sidebar_items'] = array_merge(
			isset($settings['components']['sidebar_items']) && is_array($settings['components']['sidebar_items'])
				? $settings['components']['sidebar_items']
				: [],
			[
				'member_profile' => ['label' => 'Member Profile', 'section' => 'your_portal', 'order' => 1, 'visible' => 1],
				'account' => ['label' => 'Settings', 'section' => 'your_portal', 'order' => 2, 'visible' => 1],
				'manage' => ['label' => 'Billing', 'section' => 'your_portal', 'order' => 3, 'visible' => 1],
				'discounts' => ['label' => 'Benefits', 'section' => 'your_portal', 'order' => 4, 'visible' => 1],
				'contact' => ['label' => 'Contact Us', 'section' => 'your_portal', 'order' => 5, 'visible' => 1],
				'publications' => ['label' => 'Books & Media', 'section' => 'your_portal', 'order' => 6, 'visible' => 0],
			]
		);

		update_option(self::OPTION_KEY, $settings, false);
		update_option('aac_member_portal_wondrous_visual_settings_version', self::WONDROUS_VISUAL_SETTINGS_VERSION, false);
	}

	public function maybe_apply_signup_copy_settings() {
		if (get_option('aac_member_portal_signup_copy_settings_version') === self::SIGNUP_COPY_SETTINGS_VERSION) {
			return;
		}

		$settings = self::get_settings();
		$settings['content']['join_application_kicker'] = '';

		update_option(self::OPTION_KEY, $settings, false);
		update_option('aac_member_portal_signup_copy_settings_version', self::SIGNUP_COPY_SETTINGS_VERSION, false);
	}

	public function maybe_seed_discount_cards() {
		if (get_option('aac_member_portal_discount_cards_seed_version') === self::DISCOUNT_CARD_IMPORT_VERSION) {
			return;
		}

		$seed_cards = self::get_default_discount_cards();
		if (empty($seed_cards)) {
			return;
		}

		$settings = self::get_settings();
		$existing_cards = isset($settings['content']['discount_cards']) && is_array($settings['content']['discount_cards'])
			? array_values($settings['content']['discount_cards'])
			: [];
		$existing_cards = array_values(array_filter($existing_cards, function ($existing_card) {
			$existing_brand = sanitize_text_field($existing_card['brand'] ?? '');
			$existing_category = AAC_Member_Portal_Settings_Schema::normalize_discount_category($existing_card['category'] ?? '');
			return !($existing_category === 'climbing-guides' && $existing_brand === 'Guide Discounts');
		}));
		$existing_cards_by_key = [];
		foreach ($existing_cards as $existing_card) {
			$existing_brand = sanitize_text_field($existing_card['brand'] ?? '');
			if ($existing_brand !== '') {
				$existing_category = AAC_Member_Portal_Settings_Schema::normalize_discount_category($existing_card['category'] ?? '');
				$existing_cards_by_key[$existing_category . '|' . strtolower($existing_brand)] = $existing_card;
			}
		}

		$merged_cards = array_values($existing_cards);
		foreach ($seed_cards as $seed_card) {
			$brand = sanitize_text_field($seed_card['brand'] ?? '');
			$category = AAC_Member_Portal_Settings_Schema::normalize_discount_category($seed_card['category'] ?? '');
			$key = $category . '|' . strtolower($brand);
			if ($brand === '' || isset($existing_cards_by_key[$key])) {
				continue;
			}

			$merged_cards[] = $seed_card;
		}

		$settings['content']['discount_cards'] = array_map(
			function ($seed_card) use ($existing_cards_by_key) {
				$brand = sanitize_text_field($seed_card['brand'] ?? '');
				$category = AAC_Member_Portal_Settings_Schema::normalize_discount_category($seed_card['category'] ?? '');
				$key = $category . '|' . strtolower($brand);
				if ($brand === '' || !isset($existing_cards_by_key[$key])) {
					return $seed_card;
				}

				$existing_card = $existing_cards_by_key[$key];
				if (!empty($existing_card['image_url'])) {
					$seed_card['image_url'] = esc_url_raw($existing_card['image_url']);
				}

				return $seed_card;
			},
			$merged_cards
		);

		update_option(self::OPTION_KEY, $settings, false);
		update_option('aac_member_portal_discount_cards_seed_version', self::DISCOUNT_CARD_IMPORT_VERSION, false);
	}

	public function register_admin_page() {
		add_menu_page(
			'AAC Portal Settings',
			'AAC Portal',
			'manage_options',
			self::MENU_SLUG,
			[$this, 'render_admin_page'],
			'dashicons-admin-generic',
			56
		);

		add_submenu_page(
			self::MENU_SLUG,
			'Member Portal Settings',
			'Member Portal Settings',
			'manage_options',
			self::MENU_SLUG,
			[$this, 'render_admin_page']
		);
	}

	public function register_settings() {
		register_setting(
			'aac_member_portal_settings_group',
			self::OPTION_KEY,
			[$this, 'sanitize_settings']
		);
	}

	public function enqueue_admin_assets($hook_suffix) {
		if ($hook_suffix !== 'toplevel_page_' . self::MENU_SLUG) {
			return;
		}

		wp_enqueue_media();
	}

	public function sanitize_settings($input) {
		return AAC_Member_Portal_Settings_Schema::sanitize_settings($input, self::get_settings());
	}

	public function render_admin_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$settings = self::get_settings();
		$tabs = [
			'global' => 'Global',
			'home' => 'Home',
			'experience' => 'Experience',
			'discounts' => 'Discounts',
			'level_benefits' => 'Level Benefits',
			'publications' => 'Publications',
			'linked_accounts' => 'Linked Accounts',
			'error_log' => 'Error Log',
		];
		$tab_aliases = [
			'join' => 'experience',
			'login' => 'experience',
			'profile' => 'experience',
			'design' => 'experience',
			'navigation' => 'experience',
			'layout' => 'experience',
		];
		$tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'global';
		if (isset($tab_aliases[$tab])) {
			$tab = $tab_aliases[$tab];
		}
		if (!isset($tabs[$tab])) {
			$tab = 'global';
		}
		?>
		<div class="wrap">
			<h1>AAC Portal Settings</h1>
			<p>Manage member portal copy, page images, colors, and navigation. Settings are organized by portal page so content updates are easier to manage over time.</p>
			<?php $this->render_admin_notices(); ?>

			<nav class="nav-tab-wrapper" style="margin-bottom:20px;">
				<?php foreach ($tabs as $tab_key => $tab_label) : ?>
					<a class="nav-tab <?php echo $tab === $tab_key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['page' => self::MENU_SLUG, 'tab' => $tab_key], admin_url('admin.php'))); ?>">
						<?php echo esc_html($tab_label); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php if ($tab === 'error_log') : ?>
				<?php $this->render_error_log_tab(); ?>
			<?php else : ?>
				<form method="post" action="options.php" novalidate>
					<?php settings_fields('aac_member_portal_settings_group'); ?>
					<div style="display:grid;gap:24px;max-width:1100px;">
						<?php
						switch ($tab) {
							case 'home':
								$this->render_home_tab($settings);
								break;
							case 'discounts':
								$this->render_discounts_tab($settings);
								break;
							case 'level_benefits':
								$this->render_level_benefits_tab($settings);
								break;
							case 'publications':
								$this->render_publications_tab($settings);
								break;
							case 'linked_accounts':
								$this->render_linked_accounts_tab($settings);
								break;
							case 'experience':
								$this->render_experience_tab($settings);
								break;
							case 'global':
							default:
								$this->render_global_tab($settings);
								break;
						}
						?>
					</div>
					<?php submit_button('Save Portal Settings'); ?>
				</form>
			<?php endif; ?>
			<?php
			if ($tab === 'linked_accounts') {
				$this->render_family_invite_admin_panel();
			}
			?>
			<?php $this->render_shared_admin_scripts(); ?>
		</div>
		<?php
	}

	private function render_admin_notices() {
		$result = isset($_GET['aac_family_link_result']) ? sanitize_key(wp_unslash($_GET['aac_family_link_result'])) : '';
		if ($result === '') {
			return;
		}

		$is_success = $result === 'success';
		$message = $is_success
			? 'Family invite linked successfully.'
			: (isset($_GET['aac_family_link_message']) ? sanitize_text_field(wp_unslash($_GET['aac_family_link_message'])) : 'Family invite could not be linked.');
		?>
		<div class="notice <?php echo $is_success ? 'notice-success' : 'notice-error'; ?> is-dismissible">
			<p><?php echo esc_html($message); ?></p>
		</div>
		<?php
	}

	private function render_experience_tab($settings) {
		$this->render_join_tab($settings);
		$this->render_login_tab($settings);
		$this->render_profile_tab($settings);
		$this->render_design_tab($settings);
		$this->render_navigation_tab($settings);
		$this->render_layout_tab($settings);
	}

	private function get_pmpro_backfill_notice() {
		if (empty($_GET['aac_pmpro_backfill']) || wp_unslash($_GET['aac_pmpro_backfill']) !== '1') {
			return '';
		}

		$candidate_count = isset($_GET['aac_pmpro_backfill_candidates']) ? (int) wp_unslash($_GET['aac_pmpro_backfill_candidates']) : 0;
		$synced_count = isset($_GET['aac_pmpro_backfill_synced']) ? (int) wp_unslash($_GET['aac_pmpro_backfill_synced']) : 0;

		return sprintf(
			'PMPro backfill complete. Synced %1$d of %2$d candidate member records into PMPro fields and PMPro User Fields.',
			$synced_count,
			$candidate_count
		);
	}

	private function render_global_tab($settings) {
		$this->open_panel('Global Portal Content', 'Settings used across the member portal regardless of page.');
		?>
		<table class="form-table" role="presentation"><tbody>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][account_settings_title]', 'Account Settings title', $settings['content']['account_settings_title']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][contact_recipient_email]', 'Contact form recipient email', $settings['content']['contact_recipient_email'], 'email', 'Messages from the member app Contact form will be sent to this address.'); ?>
			<?php $this->render_contact_issue_types_row($settings); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][portal_preferences_title]', 'Portal Preferences title', $settings['content']['portal_preferences_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][portal_preferences_description]', 'Portal Preferences description', $settings['content']['portal_preferences_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][quick_actions_title]', 'Quick Actions title', $settings['content']['quick_actions_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][quick_actions_description]', 'Quick Actions description', $settings['content']['quick_actions_description']); ?>
			<?php $this->render_confirmation_letter_format_row($settings); ?>
			<?php $this->render_long_textarea_row(
				self::OPTION_KEY . '[content][confirmation_letter_body]',
				'Confirmation letter text editor',
				$settings['content']['confirmation_letter_body'],
				'Available placeholders: {member_name}, {member_id}, {membership_level}, {membership_status}, {valid_through}, {expiration_date}, {renewal_date}, {rescue_coverage}, {medical_coverage}, {mortal_remains_transport}, {benefit_sentence}, {reimbursement_sentence}. Use **bold text** for emphasis.'
			); ?>
		</tbody></table>
		<?php
		$this->close_panel();
	}

	private function render_home_tab($settings) {
		$this->open_panel('Home Page', 'Control the public homepage hero, supporting sections, logos, and card content from WordPress.');
		$involvement_cards = isset($settings['content']['home_involvement_cards']) && is_array($settings['content']['home_involvement_cards'])
			? array_values($settings['content']['home_involvement_cards'])
			: self::get_default_home_involvement_cards();
		$publication_cards = isset($settings['content']['home_publication_cards']) && is_array($settings['content']['home_publication_cards'])
			? array_values($settings['content']['home_publication_cards'])
			: self::get_default_home_publication_cards();
		$partner_logos = isset($settings['content']['home_partner_logos']) && is_array($settings['content']['home_partner_logos'])
			? array_values($settings['content']['home_partner_logos'])
			: self::get_default_home_partner_logos();
		?>
		<table class="form-table" role="presentation"><tbody>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_hero_kicker]', 'Hero kicker', $settings['content']['home_hero_kicker']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_hero_title]', 'Hero title', $settings['content']['home_hero_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][home_hero_description]', 'Hero description', $settings['content']['home_hero_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_primary_cta_label]', 'Primary CTA label', $settings['content']['home_primary_cta_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_primary_cta_url]', 'Primary CTA URL', $settings['content']['home_primary_cta_url'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_secondary_cta_label]', 'Secondary CTA label', $settings['content']['home_secondary_cta_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_secondary_cta_url]', 'Secondary CTA URL', $settings['content']['home_secondary_cta_url'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_tertiary_cta_label]', 'Tertiary CTA label', $settings['content']['home_tertiary_cta_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_tertiary_cta_url]', 'Tertiary CTA URL', $settings['content']['home_tertiary_cta_url'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_membership_chip_kicker]', 'Hero supporting kicker', $settings['content']['home_membership_chip_kicker']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][home_membership_chip_description]', 'Hero supporting description', $settings['content']['home_membership_chip_description']); ?>
			<?php $this->render_media_row(self::OPTION_KEY . '[design][home_hero_video_url]', 'Hero video URL', $settings['design']['home_hero_video_url'], 'Paste a Vimeo background URL or another embeddable media URL.'); ?>
			<?php $this->render_media_row(self::OPTION_KEY . '[design][home_intro_image_url]', 'Intro image URL', $settings['design']['home_intro_image_url']); ?>
			<?php $this->render_media_row(self::OPTION_KEY . '[design][home_intro_accent_image_url]', 'Intro accent image URL', $settings['design']['home_intro_accent_image_url']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_intro_kicker]', 'Intro kicker', $settings['content']['home_intro_kicker']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_intro_title]', 'Intro title', $settings['content']['home_intro_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][home_intro_description]', 'Intro description', $settings['content']['home_intro_description']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][home_intro_secondary_description]', 'Intro secondary description', $settings['content']['home_intro_secondary_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_intro_button_label]', 'Intro button label', $settings['content']['home_intro_button_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_intro_button_url]', 'Intro button URL', $settings['content']['home_intro_button_url'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_involvement_kicker]', 'Get involved kicker', $settings['content']['home_involvement_kicker']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_involvement_title]', 'Get involved title', $settings['content']['home_involvement_title']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_involvement_button_label]', 'Get involved button label', $settings['content']['home_involvement_button_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_involvement_button_url]', 'Get involved button URL', $settings['content']['home_involvement_button_url'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_publications_kicker]', 'Publications kicker', $settings['content']['home_publications_kicker']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_publications_title]', 'Publications title', $settings['content']['home_publications_title']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_publications_button_label]', 'Publications button label', $settings['content']['home_publications_button_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_publications_button_url]', 'Publications button URL', $settings['content']['home_publications_button_url'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_partners_kicker]', 'Partners kicker', $settings['content']['home_partners_kicker']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][home_partners_title]', 'Partners title', $settings['content']['home_partners_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][home_partners_description]', 'Partners description', $settings['content']['home_partners_description']); ?>
		</tbody></table>

		<h3 style="margin:24px 0 12px;">Get Involved Cards</h3>
		<div id="aac-home-involvement-cards" class="aac-home-repeater-list">
			<?php foreach ($involvement_cards as $index => $card) : ?>
				<?php $this->render_home_involvement_card_editor($index, $card); ?>
			<?php endforeach; ?>
		</div>
		<p style="margin-top:16px;"><button type="button" class="button button-secondary" id="aac-add-home-involvement-card">Add Involvement Card</button></p>

		<h3 style="margin:28px 0 12px;">Publication Cards</h3>
		<div id="aac-home-publication-cards" class="aac-home-repeater-list">
			<?php foreach ($publication_cards as $index => $card) : ?>
				<?php $this->render_home_publication_card_editor($index, $card); ?>
			<?php endforeach; ?>
		</div>
		<p style="margin-top:16px;"><button type="button" class="button button-secondary" id="aac-add-home-publication-card">Add Publication Card</button></p>

		<h3 style="margin:28px 0 12px;">Partner Logos</h3>
		<div id="aac-home-partner-logos" class="aac-home-repeater-list">
			<?php foreach ($partner_logos as $index => $logo) : ?>
				<?php $this->render_home_partner_logo_editor($index, $logo); ?>
			<?php endforeach; ?>
		</div>
		<p style="margin-top:16px;"><button type="button" class="button button-secondary" id="aac-add-home-partner-logo">Add Partner Logo</button></p>
		<?php
		$this->render_home_repeater_templates();
		$this->close_panel();
	}

	private function render_join_tab($settings) {
		$this->open_panel('Join Page', 'Edit the public AAC membership signup experience.');
		?>
		<table class="form-table" role="presentation"><tbody>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][join_hero_kicker]', 'Hero kicker', $settings['content']['join_hero_kicker']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][join_hero_title]', 'Hero title', $settings['content']['join_hero_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][join_hero_description]', 'Hero description', $settings['content']['join_hero_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][join_primary_cta_label]', 'Primary CTA label', $settings['content']['join_primary_cta_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][join_benefits_cta_label]', 'Benefits CTA label', $settings['content']['join_benefits_cta_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][join_application_kicker]', 'Application kicker', $settings['content']['join_application_kicker']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][join_application_title]', 'Application title', $settings['content']['join_application_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][join_application_description]', 'Application description', $settings['content']['join_application_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][join_redeem_code_button_label]', 'Redeem code button label', $settings['content']['join_redeem_code_button_label']); ?>
			<?php $this->render_media_row(self::OPTION_KEY . '[design][join_hero_image_url]', 'Hero image URL', $settings['design']['join_hero_image_url']); ?>
			<?php $this->render_media_row(self::OPTION_KEY . '[design][join_hero_video_url]', 'Hero video URL', $settings['design']['join_hero_video_url'], 'Use a Vimeo background URL when you want motion instead of a still image.'); ?>
		</tbody></table>
		<?php
		$this->close_panel();
	}

	private function render_login_tab($settings) {
		$this->open_panel('Login Page', 'Control the member sign-in copy and post-purchase sign-in messaging.');
		?>
		<table class="form-table" role="presentation"><tbody>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][login_hero_kicker]', 'Hero kicker', $settings['content']['login_hero_kicker']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][login_hero_title]', 'Hero title', $settings['content']['login_hero_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][login_hero_description]', 'Hero description', $settings['content']['login_hero_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][login_form_kicker]', 'Form kicker', $settings['content']['login_form_kicker']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][login_form_title]', 'Form title', $settings['content']['login_form_title']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][login_submit_label]', 'Submit button label', $settings['content']['login_submit_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][login_forgot_password_label]', 'Forgot password label', $settings['content']['login_forgot_password_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][login_join_link_label]', 'Join link label', $settings['content']['login_join_link_label']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][login_purchase_success_message]', 'Purchase success message', $settings['content']['login_purchase_success_message']); ?>
			<?php $this->render_media_row(self::OPTION_KEY . '[design][login_background_image_url]', 'Background image URL', $settings['design']['login_background_image_url']); ?>
		</tbody></table>
		<?php
		$this->close_panel();
	}

	private function render_profile_tab($settings) {
		$this->open_panel('Member Profile Page', 'Manage the main member profile cards and button labels.');
		$member_profile_blocks = isset($settings['content']['member_profile_blocks']) && is_array($settings['content']['member_profile_blocks'])
			? array_values($settings['content']['member_profile_blocks'])
			: [];
		$member_profile_card_sections = isset($settings['content']['member_profile_card_sections']) && is_array($settings['content']['member_profile_card_sections'])
			? $settings['content']['member_profile_card_sections']
			: self::get_default_member_profile_card_sections();
		?>
		<table class="form-table" role="presentation"><tbody>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][profile_information_title]', 'Profile Information title', $settings['content']['profile_information_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][profile_information_description]', 'Profile Information description', $settings['content']['profile_information_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][membership_snapshot_title]', 'Membership Snapshot title', $settings['content']['membership_snapshot_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][membership_snapshot_description]', 'Membership Snapshot description', $settings['content']['membership_snapshot_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][linked_accounts_title]', 'Linked Accounts title', $settings['content']['linked_accounts_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][linked_accounts_description]', 'Linked Accounts description', $settings['content']['linked_accounts_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][update_profile_button_label]', 'Update profile button label', $settings['content']['update_profile_button_label']); ?>
		</tbody></table>

		<?php $backfill_notice = $this->get_pmpro_backfill_notice(); ?>
		<h3 style="margin:24px 0 12px;">PMPro Field Backfill</h3>
		<p class="description" style="margin-bottom:12px;">Copy legacy member-profile values from the member database pathway into PMPro core fields and PMPro User Fields, then keep the sync aligned going forward.</p>
		<?php if ($backfill_notice) : ?>
			<div class="notice notice-success inline"><p><?php echo esc_html($backfill_notice); ?></p></div>
		<?php endif; ?>
		<p style="margin:12px 0 18px;">
			<button
				type="submit"
				class="button button-secondary"
				formmethod="post"
				formaction="<?php echo esc_url(admin_url('admin-post.php?action=aac_member_portal_backfill_pmpro_fields')); ?>"
				name="aac_member_portal_backfill_submit"
				value="1"
			>
				Backfill PMPro Fields From Member Database
			</button>
		</p>
		<?php wp_nonce_field('aac_member_portal_backfill_pmpro_fields', 'aac_member_portal_backfill_nonce'); ?>

		<h3 style="margin:24px 0 12px;">Hide / Show Profile Cards</h3>
		<p class="description" style="margin-bottom:12px;">Control which built-in cards appear on the member profile page.</p>
		<table class="widefat striped" style="margin-top:16px;">
			<thead>
				<tr>
					<th>Card</th>
					<th>Visible</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($member_profile_card_sections as $section_id => $section_settings) : ?>
					<tr>
						<td><strong><?php echo esc_html($section_settings['label'] ?? $section_id); ?></strong></td>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY . '[content][member_profile_card_sections][' . $section_id . '][visible]'); ?>" value="1" <?php checked(!empty($section_settings['visible'])); ?> /> Visible</label>
							<input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY . '[content][member_profile_card_sections][' . $section_id . '][label]'); ?>" value="<?php echo esc_attr($section_settings['label'] ?? $section_id); ?>" />
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h3 style="margin:24px 0 12px;">Custom Member Profile Blocks</h3>
		<p class="description" style="margin-bottom:12px;">Add, remove, and edit custom cards and the entries inside them. These blocks appear on the member profile beneath the built-in AAC profile cards.</p>
		<div class="aac-discount-admin">
			<div id="aac-member-profile-blocks" class="aac-discount-admin__list">
				<?php foreach ($member_profile_blocks as $index => $block) : ?>
					<?php $this->render_member_profile_block_editor($index, $block); ?>
				<?php endforeach; ?>
			</div>
			<p style="margin-top:16px;">
				<button type="button" class="button button-secondary" id="aac-add-member-profile-block">Add Profile Block</button>
			</p>
		</div>
		<?php
		$this->render_member_profile_block_templates();
		$this->close_panel();
	}

	public function handle_backfill_pmpro_fields() {
		if (!current_user_can('manage_options')) {
			wp_die('You do not have permission to run this sync.');
		}

		check_admin_referer('aac_member_portal_backfill_pmpro_fields', 'aac_member_portal_backfill_nonce');

		if (function_exists('set_time_limit')) {
			@set_time_limit(0);
		}

		$plugin = function_exists('aac_member_portal') ? aac_member_portal() : null;
		$result = is_object($plugin) && method_exists($plugin, 'backfill_pmpro_fields_from_member_database')
			? $plugin->backfill_pmpro_fields_from_member_database()
			: ['candidate_count' => 0, 'synced_count' => 0];

		$redirect_url = add_query_arg(
			[
				'page' => self::MENU_SLUG,
				'tab' => 'experience',
				'aac_pmpro_backfill' => '1',
				'aac_pmpro_backfill_candidates' => isset($result['candidate_count']) ? (int) $result['candidate_count'] : 0,
				'aac_pmpro_backfill_synced' => isset($result['synced_count']) ? (int) $result['synced_count'] : 0,
			],
			admin_url('admin.php')
		);

		wp_safe_redirect($redirect_url);
		exit;
	}

	public function handle_export_error_log() {
		if (!current_user_can('manage_options')) {
			wp_die('You do not have permission to export the error log.');
		}

		check_admin_referer('aac_member_portal_export_error_log');

		$rows = class_exists('AAC_Member_Portal_Error_Log') ? AAC_Member_Portal_Error_Log::list_rows(5000) : [];
		AAC_Member_Portal_Error_Log::output_csv($rows);
	}

	public function handle_clear_error_log() {
		if (!current_user_can('manage_options')) {
			wp_die('You do not have permission to clear the error log.');
		}

		check_admin_referer('aac_member_portal_clear_error_log');

		$retention_days = isset($_POST['retention_days']) ? absint(wp_unslash($_POST['retention_days'])) : 0;
		$deleted = class_exists('AAC_Member_Portal_Error_Log') ? AAC_Member_Portal_Error_Log::clear_rows($retention_days) : 0;

		wp_safe_redirect(add_query_arg([
			'page' => self::MENU_SLUG,
			'tab' => 'error_log',
			'aac_error_log_cleared' => (int) $deleted,
		], admin_url('admin.php')));
		exit;
	}

	private function render_error_log_tab() {
		if (!class_exists('AAC_Member_Portal_Error_Log')) {
			echo '<div class="notice notice-error"><p>AAC Member App error logging is unavailable.</p></div>';
			return;
		}

		$stats = AAC_Member_Portal_Error_Log::get_stats();
		$rows = AAC_Member_Portal_Error_Log::list_rows(250);
		$cleared = isset($_GET['aac_error_log_cleared']) ? (int) wp_unslash($_GET['aac_error_log_cleared']) : null;
		if ($cleared !== null) {
			printf('<div class="notice notice-success is-dismissible"><p>Member App error log cleanup complete. Removed rows: %d.</p></div>', absint($cleared));
		}
		?>
		<div style="display:grid;gap:20px;max-width:1280px;">
			<section style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px;">
				<h2 style="margin-top:0;">Checkout & Payment Error Log</h2>
				<p style="max-width:820px;color:#50575e;">
					Logs AAC Member App checkout checkpoints, PMPro validation failures, payment success events, membership changes, and relevant fatal errors. Sensitive values such as passwords, card data, address fields, phone numbers, email addresses, and tokens are redacted or omitted.
				</p>
				<div style="display:flex;flex-wrap:wrap;gap:12px;margin:16px 0;">
					<span style="background:#f6f7f7;border:1px solid #dcdcde;padding:10px 12px;"><strong><?php echo esc_html((string) ($stats['total'] ?? 0)); ?></strong> total rows</span>
					<span style="background:#f6f7f7;border:1px solid #dcdcde;padding:10px 12px;"><strong><?php echo esc_html((string) ($stats['last_24_hours'] ?? 0)); ?></strong> last 24 hours</span>
					<span style="background:#f6f7f7;border:1px solid #dcdcde;padding:10px 12px;"><strong><?php echo esc_html((string) ($stats['critical'] ?? 0)); ?></strong> critical</span>
				</div>
				<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
					<a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'aac_member_portal_export_error_log'], admin_url('admin-post.php')), 'aac_member_portal_export_error_log')); ?>">Download CSV</a>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:8px;align-items:center;margin:0;">
						<input type="hidden" name="action" value="aac_member_portal_clear_error_log" />
						<?php wp_nonce_field('aac_member_portal_clear_error_log'); ?>
						<label for="aac-error-log-retention-days">Clear rows older than</label>
						<input id="aac-error-log-retention-days" type="number" name="retention_days" value="90" min="0" style="width:90px;" />
						<span>days (0 clears all)</span>
						<?php submit_button('Clear Log Rows', 'secondary', '', false); ?>
					</form>
				</div>
			</section>
			<section style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px;overflow:auto;">
				<h2 style="margin-top:0;">Recent Log Rows</h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>ID</th>
							<th>Created</th>
							<th>Severity</th>
							<th>Area</th>
							<th>Event</th>
							<th>User</th>
							<th>Order</th>
							<th>Level</th>
							<th>Message</th>
							<th>Context</th>
						</tr>
					</thead>
					<tbody>
						<?php if (!$rows) : ?>
							<tr><td colspan="10">No Member App checkout or payment log rows have been recorded yet.</td></tr>
						<?php else : ?>
							<?php foreach ($rows as $row) : ?>
								<tr>
									<td><?php echo esc_html((string) ($row['id'] ?? '')); ?></td>
									<td><?php echo esc_html((string) ($row['created_at'] ?? '')); ?></td>
									<td><code><?php echo esc_html((string) ($row['severity'] ?? '')); ?></code></td>
									<td><?php echo esc_html((string) ($row['area'] ?? '')); ?></td>
									<td><code><?php echo esc_html((string) ($row['event_type'] ?? '')); ?></code></td>
									<td><?php echo esc_html((string) ($row['user_id'] ?? '')); ?></td>
									<td>
										<?php echo esc_html((string) ($row['pmpro_order_id'] ?? '')); ?>
										<?php if (!empty($row['pmpro_order_code'])) : ?>
											<div><code><?php echo esc_html((string) $row['pmpro_order_code']); ?></code></div>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html((string) ($row['pmpro_level_id'] ?? '')); ?></td>
									<td style="max-width:320px;"><?php echo esc_html((string) ($row['message'] ?? '')); ?></td>
									<td style="max-width:360px;"><code style="white-space:normal;word-break:break-word;"><?php echo esc_html((string) ($row['context_json'] ?? '')); ?></code></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</section>
		</div>
		<?php
	}

	private function render_discounts_tab($settings) {
		$this->open_panel('Member Benefits Page', 'Manage the benefit cards shown inside the member profile area. Each card can have tier-specific values and tier visibility.');
		$discount_cards = isset($settings['content']['discount_cards']) && is_array($settings['content']['discount_cards'])
			? array_values($settings['content']['discount_cards'])
			: [];
		if (empty($discount_cards)) {
			$discount_cards = self::get_default_discount_cards();
		}
		?>
		<table class="form-table" role="presentation"><tbody>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][discounts_title]', 'Member benefits page title', $settings['content']['discounts_title']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][discounts_locked_title]', 'Locked-state title', $settings['content']['discounts_locked_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][discounts_locked_description]', 'Locked-state description', $settings['content']['discounts_locked_description']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][discounts_free_locked_description]', 'Free-tier locked description', $settings['content']['discounts_free_locked_description']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][discounts_upgrade_hint]', 'Upgrade hint', $settings['content']['discounts_upgrade_hint']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][discounts_button_label]', 'Card button label', $settings['content']['discounts_button_label']); ?>
		</tbody></table>

		<?php $this->render_benefits_gallery_editor($settings); ?>

		<h3 style="margin:24px 0 12px;">Benefit Cards</h3>
		<p class="description" style="margin-bottom:12px;">Add, remove, and edit member benefit cards. Discount Brands can use tier visibility and tier-specific values. ExpertVoice, Climbing Guides, and Climbing Gym Discounts are shown to all active paid members.</p>
		<div class="aac-discount-admin">
			<p class="aac-discount-admin__toolbar">
				<button type="button" class="button button-secondary" id="aac-add-discount-card">Add Benefit Card</button>
			</p>
			<div class="aac-discount-admin__tabs" aria-label="Benefit card categories">
				<?php foreach (AAC_Member_Portal_Settings_Schema::get_discount_categories() as $category_id => $category_label) : ?>
					<button type="button" class="button <?php echo $category_id === 'discount-brands' ? 'button-primary' : 'button-secondary'; ?>" data-aac-discount-admin-tab="<?php echo esc_attr($category_id); ?>">
						<?php echo esc_html($category_label); ?>
					</button>
				<?php endforeach; ?>
			</div>
			<div id="aac-discount-cards" class="aac-discount-admin__list">
				<?php foreach ($discount_cards as $index => $card) : ?>
					<?php $this->render_discount_card_editor($index, $card); ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		$this->render_discount_card_template();
		$this->close_panel();
	}

	private function render_benefits_gallery_editor($settings) {
		$gallery_items = isset($settings['content']['benefits_gallery_items']) && is_array($settings['content']['benefits_gallery_items'])
			? AAC_Member_Portal_Settings_Schema::normalize_benefits_gallery_items($settings['content']['benefits_gallery_items'])
			: self::get_default_benefits_gallery_items();
		?>
		<h3 style="margin:28px 0 12px;">Benefits Gallery</h3>
		<p class="description" style="margin-bottom:12px;">Edit the gallery cards shown on the Benefits landing page. The Discounts card opens the in-app discount sections; the other cards can link to WordPress pages or external URLs.</p>
		<div class="aac-benefits-gallery-admin" data-aac-benefits-gallery-admin>
			<div class="aac-discount-admin__tabs" aria-label="Benefits gallery sections">
				<?php foreach ($gallery_items as $index => $item) : ?>
					<button
						type="button"
						class="button <?php echo $index === 0 ? 'button-primary' : 'button-secondary'; ?>"
						data-aac-benefits-gallery-tab="<?php echo esc_attr($item['id']); ?>"
					>
						<?php echo esc_html($item['title']); ?>
					</button>
				<?php endforeach; ?>
			</div>
			<?php foreach ($gallery_items as $index => $item) : ?>
				<?php
				$item_id = sanitize_key($item['id'] ?? '');
				$base_name = self::OPTION_KEY . '[content][benefits_gallery_items][' . $item_id . ']';
				$image_url = esc_url($item['image_url'] ?? '');
				?>
				<section
					class="aac-benefits-gallery-admin__panel"
					data-aac-benefits-gallery-panel="<?php echo esc_attr($item_id); ?>"
					<?php echo $index === 0 ? '' : 'hidden'; ?>
				>
					<input type="hidden" name="<?php echo esc_attr($base_name . '[id]'); ?>" value="<?php echo esc_attr($item_id); ?>" />
					<div class="aac-discount-card-editor__grid">
						<label>
							<strong>Title</strong>
							<input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[title]'); ?>" value="<?php echo esc_attr($item['title'] ?? ''); ?>" />
						</label>
						<label>
							<strong>Action label</strong>
							<input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[action_label]'); ?>" value="<?php echo esc_attr($item['action_label'] ?? ''); ?>" />
						</label>
						<label class="aac-discount-card-editor__full">
							<strong>Image URL</strong>
							<div class="aac-benefits-gallery-admin__media-row">
								<input type="url" class="regular-text aac-benefits-gallery-admin__image-input" name="<?php echo esc_attr($base_name . '[image_url]'); ?>" value="<?php echo esc_attr($item['image_url'] ?? ''); ?>" />
								<button type="button" class="button button-secondary" data-aac-select-benefits-gallery-image>Select Image</button>
							</div>
						</label>
						<label class="aac-discount-card-editor__full">
							<strong>Destination URL</strong>
							<input type="url" class="regular-text" name="<?php echo esc_attr($base_name . '[url]'); ?>" value="<?php echo esc_attr($item['url'] ?? ''); ?>" />
							<?php if ($item_id === 'discounts') : ?>
								<p class="description">Leave blank to open the in-app discount detail sections.</p>
							<?php endif; ?>
						</label>
						<label class="aac-discount-card-editor__full">
							<strong>Description</strong>
							<textarea class="large-text" rows="4" name="<?php echo esc_attr($base_name . '[description]'); ?>"><?php echo esc_textarea($item['description'] ?? ''); ?></textarea>
						</label>
						<div class="aac-discount-card-editor__full aac-benefits-gallery-admin__preview">
							<?php if ($image_url) : ?>
								<img src="<?php echo $image_url; ?>" alt="" />
							<?php endif; ?>
						</div>
					</div>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private function render_level_benefits_tab($settings) {
		$this->open_panel('Signup Level Benefits', 'Choose which benefits appear on each membership level card in the signup form and set the Redpoint dollar amounts displayed for each level.');
		$catalog = self::get_signup_level_benefit_catalog();
		$levels = self::get_signup_level_labels();
		$selected_benefits = isset($settings['content']['signup_level_benefits']) && is_array($settings['content']['signup_level_benefits'])
			? AAC_Member_Portal_Settings_Schema::normalize_signup_level_benefits($settings['content']['signup_level_benefits'])
			: self::get_default_signup_level_benefits();
		?>
		<h3 style="margin:0 0 8px;">Signup Benefits Matrix</h3>
		<p class="description" style="margin-bottom:12px;">Choose the benefits comparison image displayed above the membership level buttons. Leave empty to show the placeholder grid.</p>
		<div class="aac-benefits-gallery-admin__media-row" data-aac-signup-matrix-media>
			<input type="url" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY . '[content][signup_benefits_matrix_image_url]'); ?>" value="<?php echo esc_attr($settings['content']['signup_benefits_matrix_image_url'] ?? ''); ?>" />
			<button type="button" class="button button-secondary" data-aac-select-signup-matrix-image>Select Matrix Image</button>
		</div>
		<div class="aac-benefits-gallery-admin__preview" data-aac-signup-matrix-preview>
			<?php if (!empty($settings['content']['signup_benefits_matrix_image_url'])) : ?>
				<img src="<?php echo esc_url($settings['content']['signup_benefits_matrix_image_url']); ?>" alt="" />
			<?php endif; ?>
		</div>
		<input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY . '[content][signup_level_benefits][_configured]'); ?>" value="1" />
		<table class="widefat striped" style="margin-top:16px;">
			<thead>
				<tr>
					<th style="width:42%;">Benefit</th>
					<?php foreach ($levels as $level_id => $level_label) : ?>
						<th style="text-align:center;"><?php echo esc_html($level_label); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($catalog as $benefit_key => $benefit_label) : ?>
					<tr>
						<td><strong><?php echo esc_html($benefit_label); ?></strong></td>
						<?php foreach ($levels as $level_id => $level_label) : ?>
							<td style="text-align:center;">
								<label aria-label="<?php echo esc_attr($level_label . ': ' . $benefit_label); ?>">
									<input
										type="checkbox"
										name="<?php echo esc_attr(self::OPTION_KEY . '[content][signup_level_benefits][' . $level_id . '][' . $benefit_key . ']'); ?>"
										value="1"
										<?php checked(in_array($benefit_key, $selected_benefits[$level_id] ?? [], true)); ?>
									/>
								</label>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description" style="margin-top:12px;">The card display follows the order shown here. Unchecking every benefit for a level will hide the benefit checklist for that level card.</p>
		<?php $this->render_redpoint_level_amounts_editor($settings); ?>
		<?php
		$this->close_panel();
	}

	private function render_redpoint_level_amounts_editor($settings) {
		$rescue_levels = isset($settings['content']['rescue_levels']) && is_array($settings['content']['rescue_levels'])
			? array_values($settings['content']['rescue_levels'])
			: self::get_default_rescue_levels();
		?>
		<h3 style="margin:32px 0 12px;">Redpoint Dollar Amounts</h3>
		<p class="description" style="margin-bottom:12px;">Set the Rescue Coverage, Medical Expense Coverage, and Mortal Remains Transport amounts used across the membership card, Redpoint benefits, signup level cards, and confirmation letter.</p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th>Membership Level</th>
					<th>Rescue Coverage</th>
					<th>Medical Expense Coverage</th>
					<th>Mortal Remains Transport</th>
					<th>Reimbursement Process</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($rescue_levels as $index => $level) : ?>
					<tr>
						<td>
							<input
								type="text"
								class="regular-text"
								name="<?php echo esc_attr(self::OPTION_KEY . '[content][rescue_levels][' . $index . '][level_name]'); ?>"
								value="<?php echo esc_attr($level['level_name'] ?? ''); ?>"
							/>
						</td>
						<td>
							<input
								type="number"
								min="0"
								step="1"
								name="<?php echo esc_attr(self::OPTION_KEY . '[content][rescue_levels][' . $index . '][rescue_amount]'); ?>"
								value="<?php echo esc_attr((int) ($level['rescue_amount'] ?? 0)); ?>"
								style="width:140px;"
							/>
						</td>
						<td>
							<input
								type="number"
								min="0"
								step="1"
								name="<?php echo esc_attr(self::OPTION_KEY . '[content][rescue_levels][' . $index . '][medical_amount]'); ?>"
								value="<?php echo esc_attr((int) ($level['medical_amount'] ?? 0)); ?>"
								style="width:140px;"
							/>
						</td>
						<td>
							<input
								type="number"
								min="0"
								step="1"
								name="<?php echo esc_attr(self::OPTION_KEY . '[content][rescue_levels][' . $index . '][mortal_remains_amount]'); ?>"
								value="<?php echo esc_attr((int) ($level['mortal_remains_amount'] ?? 0)); ?>"
								style="width:140px;"
							/>
						</td>
						<td>
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr(self::OPTION_KEY . '[content][rescue_levels][' . $index . '][rescue_reimbursement_process]'); ?>"
									value="1"
									<?php checked(!empty($level['rescue_reimbursement_process'])); ?>
								/>
								Included
							</label>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_publications_tab($settings) {
		$this->open_panel('Publications Page', 'Update member publication copy, view links, and locked-state messaging.');
		?>
		<table class="form-table" role="presentation"><tbody>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][publications_title]', 'Page title', $settings['content']['publications_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][publications_description]', 'Page description', $settings['content']['publications_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][publications_locked_title]', 'Locked-state title', $settings['content']['publications_locked_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][publications_locked_description]', 'Locked-state description', $settings['content']['publications_locked_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][publications_upgrade_button_label]', 'Upgrade button label', $settings['content']['publications_upgrade_button_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][publication_view_url_aaj]', 'AAJ View URL', $settings['content']['publication_view_url_aaj'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][publication_view_url_anac]', 'ANAC View URL', $settings['content']['publication_view_url_anac'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][publication_view_url_acj]', 'American Climbing Journal View URL', $settings['content']['publication_view_url_acj'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][publication_view_url_guidebook]', 'Guidebook View URL', $settings['content']['publication_view_url_guidebook'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][publication_tile_image_aaj]', 'AAJ tile image URL', $settings['design']['publication_tile_image_aaj'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][publication_tile_image_anac]', 'ANAC tile image URL', $settings['design']['publication_tile_image_anac'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][publication_tile_image_acj]', 'American Climbing Journal tile image URL', $settings['design']['publication_tile_image_acj'], 'url'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][publication_tile_image_guidebook]', 'Guidebook tile image URL', $settings['design']['publication_tile_image_guidebook'], 'url'); ?>
		</tbody></table>
		<?php
		$this->close_panel();
	}

	private function render_linked_accounts_tab($settings) {
		$this->open_panel('Linked Accounts Page', 'Update family invite redemption labels and success messaging.');
		?>
		<table class="form-table" role="presentation"><tbody>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][linked_accounts_page_title]', 'Page title', $settings['content']['linked_accounts_page_title']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][linked_accounts_page_description]', 'Page description', $settings['content']['linked_accounts_page_description']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][linked_accounts_lookup_button_label]', 'Check code button label', $settings['content']['linked_accounts_lookup_button_label']); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[content][linked_accounts_redeem_button_label]', 'Redeem button label', $settings['content']['linked_accounts_redeem_button_label']); ?>
			<?php $this->render_textarea_row(self::OPTION_KEY . '[content][linked_accounts_success_message]', 'Success message', $settings['content']['linked_accounts_success_message']); ?>
		</tbody></table>
		<?php
		$this->close_panel();
	}

	private function render_family_invite_admin_panel() {
		$rows = $this->get_family_invite_rows();
		?>
		<section style="background:#fff;border:1px solid #dcdcde;border-radius:12px;margin-top:24px;padding:24px;">
			<h2 style="margin-top:0;">Family Invite Codes</h2>
			<p>View pending and redeemed household invite codes. To redeem an invite on behalf of a dependent, create or locate the dependent's WordPress user first, then link that user to the pending invite below.</p>
			<p class="description">Manual linking connects the user account to the family slot and, when PMPro Group Accounts is active, assigns the matching Partner Adult or Partner Dependent child level. It does not update Stripe billing or create a paid child subscription.</p>
			<?php if (isset($_GET['aac_group_accounts_migrated'])) : ?>
				<?php $migration_success = sanitize_key(wp_unslash($_GET['aac_group_accounts_migrated'])) === 'success'; ?>
				<div class="<?php echo esc_attr($migration_success ? 'notice notice-success inline' : 'notice notice-error inline'); ?>">
					<p>
						<?php
						echo esc_html($migration_success
							? 'Family accounts migrated to PMPro Group Accounts.'
							: (isset($_GET['aac_group_accounts_message']) ? sanitize_text_field(wp_unslash($_GET['aac_group_accounts_message'])) : 'Family account migration could not run.'));
						?>
					</p>
				</div>
			<?php endif; ?>
			<?php if (class_exists('AAC_Member_Portal_Group_Accounts')) : ?>
				<?php $migration = get_option(AAC_Member_Portal_Group_Accounts::MIGRATION_OPTION, []); ?>
				<div style="align-items:center;background:#f6f7f7;border:1px solid #dcdcde;display:flex;gap:16px;justify-content:space-between;margin:16px 0;padding:14px 16px;">
					<div>
						<strong>PMPro Group Accounts migration</strong>
						<p class="description" style="margin:4px 0 0;">
							<?php if (is_array($migration) && !empty($migration['migrated_at'])) : ?>
								Last run <?php echo esc_html($migration['migrated_at']); ?>. Synced <?php echo esc_html((string) ($migration['groups_synced'] ?? 0)); ?> groups and <?php echo esc_html((string) ($migration['children_linked'] ?? 0)); ?> linked children. Imported children checked: <?php echo esc_html((string) ($migration['imported_children_checked'] ?? 0)); ?>; unresolved: <?php echo esc_html((string) ($migration['imported_children_unresolved'] ?? 0)); ?>.
							<?php else : ?>
								Creates PMPro group accounts for existing AAC family slots and repairs imported parent/child family accounts using parent login, parent email, parent AAC member ID, group ID, group code, or group order metadata.
							<?php endif; ?>
						</p>
					</div>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
						<?php wp_nonce_field('aac_member_portal_migrate_group_accounts'); ?>
						<input type="hidden" name="action" value="aac_member_portal_migrate_group_accounts" />
						<button type="submit" class="button button-primary">Run Migration</button>
					</form>
				</div>
				<?php if (isset($_GET['aac_family_group_links_imported'])) : ?>
					<?php $import_success = sanitize_key(wp_unslash($_GET['aac_family_group_links_imported'])) === 'success'; ?>
					<div class="<?php echo esc_attr($import_success ? 'notice notice-success inline' : 'notice notice-error inline'); ?>">
						<p>
							<?php echo esc_html(isset($_GET['aac_family_group_links_message']) ? sanitize_text_field(wp_unslash($_GET['aac_family_group_links_message'])) : ($import_success ? 'Family group links imported.' : 'Family group links could not be imported.')); ?>
						</p>
					</div>
				<?php endif; ?>
				<div style="background:#fff;border:1px solid #dcdcde;margin:16px 0 20px;padding:16px;">
					<h3 style="margin-top:0;">Import Family Group Links CSV</h3>
					<p class="description">Use this after PMPro member import if child accounts did not attach to the parent Group Account. Upload the family child/link CSV with parent login, parent email, parent member ID, group ID, group code, or group order columns. This links existing imported users; it does not create Stripe subscriptions.</p>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
						<?php wp_nonce_field('aac_member_portal_import_family_group_links'); ?>
						<input type="hidden" name="action" value="aac_member_portal_import_family_group_links" />
						<input type="file" name="family_group_links_csv" accept=".csv,text/csv" required />
						<button type="submit" class="button button-primary">Import Family Links</button>
					</form>
				</div>
			<?php endif; ?>
			<?php if (isset($_GET['aac_family_add_result'])) : ?>
				<?php $add_success = sanitize_key(wp_unslash($_GET['aac_family_add_result'])) === 'success'; ?>
				<div class="<?php echo esc_attr($add_success ? 'notice notice-success inline' : 'notice notice-error inline'); ?>">
					<p>
						<?php
						echo esc_html($add_success
							? 'Family member added and synced to the group account.'
							: (isset($_GET['aac_family_add_message']) ? sanitize_text_field(wp_unslash($_GET['aac_family_add_message'])) : 'Family member could not be added.'));
						?>
					</p>
				</div>
			<?php endif; ?>
			<div style="background:#fff;border:1px solid #dcdcde;margin:16px 0 20px;padding:16px;">
				<h3 style="margin-top:0;">Add Family / Group Member Manually</h3>
				<p class="description">Use this when there is no open invite slot. It creates a family slot, links the child user, and syncs them into PMPro Group Accounts. This does not update Stripe billing.</p>
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:grid;gap:12px;grid-template-columns:repeat(2,minmax(220px,1fr));max-width:980px;">
					<?php wp_nonce_field('aac_member_portal_add_family_member'); ?>
					<input type="hidden" name="action" value="aac_member_portal_add_family_member" />
					<label>
						<strong>Parent user</strong><br />
						<input type="text" name="parent_identifier" class="regular-text" placeholder="Parent ID, email, or username" required />
					</label>
					<label>
						<strong>Member type</strong><br />
						<select name="member_type">
							<option value="dependent">Dependent</option>
							<option value="adult">Additional adult</option>
						</select>
					</label>
					<label>
						<strong>Existing child user</strong><br />
						<input type="text" name="child_identifier" class="regular-text" placeholder="User ID, email, or username" />
					</label>
					<div class="description" style="align-self:end;">Or create a new WordPress user below.</div>
					<label>
						<strong>New user first name</strong><br />
						<input type="text" name="child_first_name" class="regular-text" />
					</label>
					<label>
						<strong>New user last name</strong><br />
						<input type="text" name="child_last_name" class="regular-text" />
					</label>
					<label>
						<strong>New user email</strong><br />
						<input type="email" name="child_email" class="regular-text" />
					</label>
					<label>
						<strong>New user password</strong><br />
						<input type="text" name="child_password" class="regular-text" placeholder="Leave blank to generate one" />
					</label>
					<div style="grid-column:1 / -1;">
						<button type="submit" class="button button-primary">Add Member to Family / Group</button>
					</div>
				</form>
			</div>
			<?php if (empty($rows)) : ?>
				<p>No family invite codes found yet.</p>
			<?php else : ?>
				<table class="widefat striped" style="margin-top:16px;">
					<thead>
						<tr>
							<th>Parent</th>
							<th>Slot</th>
							<th>Status</th>
							<th>Invite Code</th>
							<th>Redeem URL</th>
							<th>PMPro Group</th>
							<th>Group Invite Link</th>
							<th>Linked User</th>
							<th>Admin Link</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($rows as $row) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html($row['parent_name']); ?></strong><br />
									<a href="<?php echo esc_url($row['parent_edit_url']); ?>"><?php echo esc_html($row['parent_email']); ?></a>
								</td>
								<td>
									<?php echo esc_html($row['label']); ?><br />
									<span class="description"><?php echo esc_html(ucfirst($row['type'])); ?> · <?php echo esc_html($row['price']); ?></span>
								</td>
								<td><?php echo esc_html($row['status_label']); ?></td>
								<td><code style="font-size:13px;"><?php echo esc_html($row['invite_code']); ?></code></td>
								<td>
									<input type="text" readonly class="regular-text code" value="<?php echo esc_attr($row['redeem_url']); ?>" onclick="this.select();" />
								</td>
								<td>
									<?php if (!empty($row['group_summary'])) : ?>
										<strong>Group #<?php echo esc_html((string) $row['group_summary']['id']); ?></strong><br />
										<span class="description"><?php echo esc_html((string) $row['group_summary']['active_members']); ?>/<?php echo esc_html((string) $row['group_summary']['total_seats']); ?> seats</span><br />
										<code><?php echo esc_html($row['group_summary']['checkout_code']); ?></code>
										<?php if (!empty($row['group_summary']['manage_url'])) : ?>
											<br /><a href="<?php echo esc_url($row['group_summary']['manage_url']); ?>">Manage group</a>
										<?php endif; ?>
									<?php else : ?>
										<span class="description">Not synced</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if (!empty($row['group_invite_url'])) : ?>
										<input type="text" readonly class="regular-text code" value="<?php echo esc_attr($row['group_invite_url']); ?>" onclick="this.select();" />
									<?php else : ?>
										<span class="description">Run migration first</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ($row['child_user_id']) : ?>
										<strong><?php echo esc_html($row['child_name']); ?></strong><br />
										<a href="<?php echo esc_url($row['child_edit_url']); ?>"><?php echo esc_html($row['child_email']); ?></a>
									<?php else : ?>
										<span class="description">Not linked</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ($row['can_link']) : ?>
										<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:grid;gap:8px;min-width:220px;">
											<?php wp_nonce_field('aac_member_portal_link_family_invite'); ?>
											<input type="hidden" name="action" value="aac_member_portal_link_family_invite" />
											<input type="hidden" name="parent_user_id" value="<?php echo esc_attr((string) $row['parent_user_id']); ?>" />
											<input type="hidden" name="slot_id" value="<?php echo esc_attr($row['slot_id']); ?>" />
											<input type="text" name="child_identifier" class="regular-text" placeholder="User ID, email, or username" />
											<button type="submit" class="button button-secondary">Link User</button>
										</form>
									<?php else : ?>
										<span class="description">Already linked</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
		<?php
	}

	private function get_family_invite_rows() {
		$users = get_users([
			'meta_key' => 'aac_connected_accounts',
			'number' => 500,
			'fields' => 'all',
		]);
		$portal_url = function_exists('aac_member_portal') && aac_member_portal() && method_exists(aac_member_portal(), 'get_portal_page_url')
			? aac_member_portal()->get_portal_page_url()
			: home_url('/membership/');
		$portal_url = trailingslashit($portal_url);
		$rows = [];

		foreach ($users as $parent_user) {
			if (!$parent_user instanceof WP_User) {
				continue;
			}

			$accounts = get_user_meta($parent_user->ID, 'aac_connected_accounts', true);
			if (!is_array($accounts)) {
				continue;
			}
			$group_summary = class_exists('AAC_Member_Portal_Group_Accounts')
				? AAC_Member_Portal_Group_Accounts::get_group_summary_for_parent($parent_user->ID)
				: null;

			foreach ($accounts as $slot) {
				if (!is_array($slot)) {
					continue;
				}

				$invite_code = sanitize_text_field((string) ($slot['invite_code'] ?? ''));
				if ($invite_code === '') {
					continue;
				}

				$child_user_id = absint($slot['child_user_id'] ?? 0);
				$child_user = $child_user_id ? get_user_by('id', $child_user_id) : null;
				$child_name = sanitize_text_field((string) ($slot['child_name'] ?? ''));
				$child_email = sanitize_email((string) ($slot['child_email'] ?? ''));
				if ($child_user instanceof WP_User) {
					$child_name = trim($child_user->first_name . ' ' . $child_user->last_name) ?: $child_user->display_name;
					$child_email = $child_user->user_email;
				}

				$status = sanitize_key((string) ($slot['status'] ?? 'pending'));
				$rows[] = [
					'parent_user_id' => (int) $parent_user->ID,
					'parent_name' => $parent_user->display_name ?: $parent_user->user_login,
					'parent_email' => $parent_user->user_email,
					'parent_edit_url' => get_edit_user_link($parent_user->ID),
					'slot_id' => sanitize_text_field((string) ($slot['id'] ?? '')),
					'type' => sanitize_key((string) ($slot['type'] ?? 'dependent')) ?: 'dependent',
					'label' => sanitize_text_field((string) ($slot['label'] ?? 'Family member')),
					'status' => $status,
					'status_label' => $this->format_family_invite_status($status),
					'invite_code' => $invite_code,
					'redeem_url' => $portal_url . '#/linked-accounts?code=' . rawurlencode($invite_code),
					'group_summary' => $group_summary,
					'group_invite_url' => class_exists('AAC_Member_Portal_Group_Accounts')
						? AAC_Member_Portal_Group_Accounts::get_invite_url_for_parent_slot($parent_user->ID, $slot)
						: '',
					'child_user_id' => $child_user_id,
					'child_name' => $child_name,
					'child_email' => $child_email,
					'child_edit_url' => $child_user_id ? get_edit_user_link($child_user_id) : '',
					'price' => '$' . number_format((float) ($slot['price'] ?? 0), 2) . '/yr',
					'can_link' => $child_user_id <= 0 && $status !== 'connected',
				];
			}
		}

		usort($rows, static function ($a, $b) {
			return strcasecmp($a['parent_name'] . $a['label'], $b['parent_name'] . $b['label']);
		});

		return $rows;
	}

	private function format_family_invite_status($status) {
		switch (sanitize_key((string) $status)) {
			case 'connected':
				return 'Connected';
			case 'removal_pending':
				return 'Removing at renewal';
			case 'pending':
			default:
				return 'Pending';
		}
	}

	public function handle_link_family_invite() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to manage family invite codes.', 'aac-member-portal'));
		}

		check_admin_referer('aac_member_portal_link_family_invite');

		$parent_user_id = isset($_POST['parent_user_id']) ? absint(wp_unslash($_POST['parent_user_id'])) : 0;
		$slot_id = isset($_POST['slot_id']) ? sanitize_text_field(wp_unslash($_POST['slot_id'])) : '';
		$child_identifier = isset($_POST['child_identifier']) ? sanitize_text_field(wp_unslash($_POST['child_identifier'])) : '';
		$result = $this->link_family_invite_to_user($parent_user_id, $slot_id, $child_identifier);

		$redirect_args = [
			'page' => self::MENU_SLUG,
			'tab' => 'linked_accounts',
			'aac_family_link_result' => is_wp_error($result) ? 'error' : 'success',
		];
		if (is_wp_error($result)) {
			$redirect_args['aac_family_link_message'] = rawurlencode($result->get_error_message());
		}

		wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
		exit;
	}

	public function handle_add_family_member() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to add family members.', 'aac-member-portal'));
		}

		check_admin_referer('aac_member_portal_add_family_member');

		$parent_identifier = isset($_POST['parent_identifier']) ? sanitize_text_field(wp_unslash($_POST['parent_identifier'])) : '';
		$member_type = isset($_POST['member_type']) ? sanitize_key(wp_unslash($_POST['member_type'])) : 'dependent';
		$child_identifier = isset($_POST['child_identifier']) ? sanitize_text_field(wp_unslash($_POST['child_identifier'])) : '';
		$child_first_name = isset($_POST['child_first_name']) ? sanitize_text_field(wp_unslash($_POST['child_first_name'])) : '';
		$child_last_name = isset($_POST['child_last_name']) ? sanitize_text_field(wp_unslash($_POST['child_last_name'])) : '';
		$child_email = isset($_POST['child_email']) ? sanitize_email(wp_unslash($_POST['child_email'])) : '';
		$child_password = isset($_POST['child_password']) ? (string) wp_unslash($_POST['child_password']) : '';

		$parent_user = $this->find_user_for_family_link($parent_identifier);
		$result = $parent_user instanceof WP_User && $parent_user->exists()
			? $this->add_family_member_manually($parent_user, $member_type, $child_identifier, $child_first_name, $child_last_name, $child_email, $child_password)
			: new WP_Error('missing_parent', 'Enter an existing parent user ID, email, or username.');

		$redirect_args = [
			'page' => self::MENU_SLUG,
			'tab' => 'linked_accounts',
			'aac_family_add_result' => is_wp_error($result) ? 'error' : 'success',
		];
		if (is_wp_error($result)) {
			$redirect_args['aac_family_add_message'] = rawurlencode($result->get_error_message());
		}

		wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
		exit;
	}

	private function add_family_member_manually(WP_User $parent_user, $member_type, $child_identifier, $child_first_name, $child_last_name, $child_email, $child_password) {
		$member_type = $member_type === 'adult' ? 'adult' : 'dependent';
		$child_user = $this->find_user_for_family_link($child_identifier);
		if (!$child_user instanceof WP_User || !$child_user->exists()) {
			$child_user = $this->create_family_child_user($child_first_name, $child_last_name, $child_email, $child_password);
			if (is_wp_error($child_user)) {
				return $child_user;
			}
		}

		if ((int) $child_user->ID === (int) $parent_user->ID) {
			return new WP_Error('parent_child_match', 'The parent account cannot be linked as its own family member.');
		}

		$accounts = get_user_meta($parent_user->ID, 'aac_connected_accounts', true);
		$accounts = is_array($accounts) ? array_values(array_filter($accounts, 'is_array')) : [];
		foreach ($accounts as $slot) {
			if (absint($slot['child_user_id'] ?? 0) === (int) $child_user->ID && ($slot['status'] ?? '') === 'connected') {
				return new WP_Error('already_linked', 'This child user is already linked to this family membership.');
			}
		}

		$label = $member_type === 'adult' ? 'Additional adult' : $this->get_next_dependent_label($accounts);
		$slot = [
			'id' => wp_generate_uuid4(),
			'type' => $member_type,
			'label' => $label,
			'status' => 'pending',
			'invite_code' => $this->generate_admin_family_invite_code(),
			'child_user_id' => 0,
			'child_name' => '',
			'child_email' => '',
			'price' => $member_type === 'adult' ? 80.0 : 45.0,
			'scheduled_removal_date' => '',
		];
		$accounts[] = $slot;
		update_user_meta($parent_user->ID, 'aac_connected_accounts', array_values($accounts));
		$this->update_family_config_from_slots($parent_user->ID, $accounts);

		return $this->link_family_invite_to_user((int) $parent_user->ID, $slot['id'], (string) $child_user->ID);
	}

	private function create_family_child_user($first_name, $last_name, $email, $password) {
		$email = sanitize_email($email);
		if (!$email || !is_email($email)) {
			return new WP_Error('missing_child', 'Enter an existing child user or provide a valid email to create one.');
		}

		$existing_user = get_user_by('email', $email);
		if ($existing_user instanceof WP_User) {
			return $existing_user;
		}

		if ($password === '') {
			$password = wp_generate_password(14, true, false);
		}

		$username = sanitize_user(current(explode('@', $email)), true);
		if ($username === '') {
			$username = 'aac-member';
		}
		$base_username = $username;
		$suffix = 1;
		while (username_exists($username)) {
			$username = $base_username . $suffix;
			$suffix++;
		}

		$user_id = wp_create_user($username, $password, $email);
		if (is_wp_error($user_id)) {
			return $user_id;
		}

		wp_update_user([
			'ID' => $user_id,
			'first_name' => $first_name,
			'last_name' => $last_name,
			'display_name' => trim($first_name . ' ' . $last_name) ?: $email,
		]);
		update_user_meta($user_id, 'aac_account_info', [
			'first_name' => $first_name,
			'last_name' => $last_name,
			'name' => trim($first_name . ' ' . $last_name),
			'email' => $email,
		]);
		update_user_meta($user_id, 't_shirt', 'No T-shirt');
		update_user_meta($user_id, 'birthdate', '');

		return get_user_by('id', $user_id);
	}

	private function get_next_dependent_label($accounts) {
		$count = 0;
		foreach ($accounts as $slot) {
			if (is_array($slot) && sanitize_key((string) ($slot['type'] ?? '')) === 'dependent') {
				$count++;
			}
		}

		return sprintf('Dependent %d', $count + 1);
	}

	private function update_family_config_from_slots($parent_user_id, $accounts) {
		$dependent_count = 0;
		$has_adult = false;
		foreach ($accounts as $slot) {
			if (!is_array($slot) || ($slot['status'] ?? '') === 'removal_pending') {
				continue;
			}
			if (($slot['type'] ?? '') === 'adult') {
				$has_adult = true;
			} elseif (($slot['type'] ?? '') === 'dependent') {
				$dependent_count++;
			}
		}

		update_user_meta($parent_user_id, 'aac_partner_family_config', [
			'mode' => ($has_adult || $dependent_count > 0) ? 'family' : '',
			'additional_adult' => $has_adult,
			'dependent_count' => $dependent_count,
		]);
	}

	private function generate_admin_family_invite_code() {
		return 'AACF-' . strtoupper(wp_generate_password(8, false, false));
	}

	private function link_family_invite_to_user($parent_user_id, $slot_id, $child_identifier) {
		if ($parent_user_id <= 0 || $slot_id === '') {
			return new WP_Error('missing_invite', 'Select a valid family invite slot.');
		}

		$child_user = $this->find_user_for_family_link($child_identifier);
		if (!$child_user instanceof WP_User || !$child_user->exists()) {
			return new WP_Error('missing_child', 'Enter an existing dependent user ID, email, or username.');
		}

		if ((int) $child_user->ID === (int) $parent_user_id) {
			return new WP_Error('parent_child_match', 'The parent account cannot be linked as its own dependent.');
		}

		$existing_parent_id = absint(get_user_meta($child_user->ID, 'aac_linked_parent_user_id', true));
		if ($existing_parent_id > 0 && $existing_parent_id !== (int) $parent_user_id) {
			return new WP_Error('already_linked', 'This user is already linked to another family membership.');
		}

		$accounts = get_user_meta($parent_user_id, 'aac_connected_accounts', true);
		if (!is_array($accounts)) {
			return new WP_Error('missing_accounts', 'This parent account does not have family invite slots.');
		}

		$slot_index = null;
		foreach ($accounts as $index => $slot) {
			if (is_array($slot) && sanitize_text_field((string) ($slot['id'] ?? '')) === $slot_id) {
				$slot_index = $index;
				break;
			}
		}

		if ($slot_index === null || !isset($accounts[$slot_index]) || !is_array($accounts[$slot_index])) {
			return new WP_Error('missing_slot', 'Family invite slot not found.');
		}

		$slot = $accounts[$slot_index];
		$existing_child_id = absint($slot['child_user_id'] ?? 0);
		if ($existing_child_id > 0 && $existing_child_id !== (int) $child_user->ID) {
			return new WP_Error('slot_taken', 'This family invite slot is already linked to another user.');
		}

		$invite_code = sanitize_text_field((string) ($slot['invite_code'] ?? ''));
		if ($invite_code === '') {
			return new WP_Error('missing_code', 'This family invite slot does not have an invite code.');
		}

		$accounts[$slot_index] = array_merge($slot, [
			'status' => 'connected',
			'child_user_id' => (int) $child_user->ID,
			'child_name' => trim($child_user->first_name . ' ' . $child_user->last_name) ?: $child_user->display_name,
			'child_email' => $child_user->user_email,
			'scheduled_removal_date' => '',
		]);
		update_user_meta($parent_user_id, 'aac_connected_accounts', array_values($accounts));

		update_user_meta($child_user->ID, 'aac_linked_parent_user_id', (int) $parent_user_id);
		update_user_meta($child_user->ID, 'aac_linked_account_slot_id', $slot_id);
		update_user_meta($child_user->ID, 'aac_linked_account_invite_code', $invite_code);
		update_user_meta($child_user->ID, 'aac_linked_account_type', sanitize_key((string) ($slot['type'] ?? 'dependent')));
		update_user_meta($child_user->ID, 'aac_linked_account_label', sanitize_text_field((string) ($slot['label'] ?? 'Family member')));
		update_user_meta($parent_user_id, 'aac_family_account_role', 'Parent');
		update_user_meta($child_user->ID, 'aac_family_account_role', 'Child');
		delete_user_meta($child_user->ID, 'aac_family_membership_access_until');
		delete_user_meta($child_user->ID, 'aac_family_membership_pending_removal');

		do_action('aac_member_portal_family_account_linked', (int) $parent_user_id, (int) $child_user->ID);

		return true;
	}

	private function find_user_for_family_link($identifier) {
		$identifier = trim((string) $identifier);
		if ($identifier === '') {
			return null;
		}

		if (ctype_digit($identifier)) {
			$user = get_user_by('id', absint($identifier));
			if ($user instanceof WP_User) {
				return $user;
			}
		}

		if (is_email($identifier)) {
			$user = get_user_by('email', sanitize_email($identifier));
			if ($user instanceof WP_User) {
				return $user;
			}
		}

		$user = get_user_by('login', sanitize_user($identifier, true));
		return $user instanceof WP_User ? $user : null;
	}

	private function render_design_tab($settings) {
		$this->open_panel('Design', 'Update shared portal images, color controls, overlays, and member app sidebar styling.');
		?>
		<table class="form-table" role="presentation"><tbody>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][page_background]', 'Page background', $settings['design']['page_background'], 'text', 'Used for the lighter page background areas.'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][panel_background]', 'Panel background', $settings['design']['panel_background'], 'text', 'Default background for cards and surfaces.'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][panel_border_color]', 'Panel border color', $settings['design']['panel_border_color'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][sidebar_background_url]', 'Sidebar background image URL', $settings['design']['sidebar_background_url'], 'url', 'Leave blank to use the bundled topo background.'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][sidebar_overlay_start]', 'Sidebar overlay start opacity', $settings['design']['sidebar_overlay_start'], 'number', 'Lower values make the topo lines more visible.', '0', '1', '0.01'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][sidebar_overlay_end]', 'Sidebar overlay end opacity', $settings['design']['sidebar_overlay_end'], 'number', 'Used for the darker lower part of the overlay.', '0', '1', '0.01'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][sidebar_button_background]', 'Sidebar button background', $settings['design']['sidebar_button_background'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][sidebar_button_hover_background]', 'Sidebar button hover background', $settings['design']['sidebar_button_hover_background'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][sidebar_button_active_background]', 'Sidebar button active background', $settings['design']['sidebar_button_active_background'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][sidebar_accent_color]', 'Sidebar accent color', $settings['design']['sidebar_accent_color'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][primary_action_background]', 'Primary action background', $settings['design']['primary_action_background'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][primary_action_text]', 'Primary action text', $settings['design']['primary_action_text'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][secondary_action_background]', 'Secondary action background', $settings['design']['secondary_action_background'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][secondary_action_text]', 'Secondary action text', $settings['design']['secondary_action_text'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][hero_panel_background]', 'Hero text block background', $settings['design']['hero_panel_background'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][hero_panel_border_color]', 'Hero text block border color', $settings['design']['hero_panel_border_color'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][hero_chip_background]', 'Hero supporting chip background', $settings['design']['hero_chip_background'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][hero_chip_border_color]', 'Hero supporting chip border color', $settings['design']['hero_chip_border_color'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][login_form_background]', 'Login form background', $settings['design']['login_form_background'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][login_overlay]', 'Login page overlay CSS', $settings['design']['login_overlay'], 'text', 'Advanced: accepts a CSS background value.'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][home_hero_overlay]', 'Home hero overlay CSS', $settings['design']['home_hero_overlay'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][home_hero_tint_overlay]', 'Home hero tint CSS', $settings['design']['home_hero_tint_overlay'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][join_hero_overlay]', 'Join hero overlay CSS', $settings['design']['join_hero_overlay'], 'text'); ?>
			<?php $this->render_input_row(self::OPTION_KEY . '[design][join_hero_tint_overlay]', 'Join hero tint CSS', $settings['design']['join_hero_tint_overlay'], 'text'); ?>
		</tbody></table>
		<?php
		$this->close_panel();
	}

	private function render_navigation_tab($settings) {
		$this->open_panel('Member App Sidebar', 'Update the member app sidebar labels, section title, order, and visibility. The embedded member app no longer includes a website top navigation bar.');
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<?php foreach ($settings['components']['section_titles'] as $section_id => $title) : ?>
					<?php $this->render_input_row(self::OPTION_KEY . '[components][section_titles][' . $section_id . ']', sprintf('Section title: %s', $section_id), $title); ?>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h3 style="margin:24px 0 12px;">Sidebar Items</h3>
		<table class="widefat striped" style="margin-top:16px;">
			<thead>
				<tr>
					<th>Component</th>
					<th>Label</th>
					<th>Section</th>
					<th>Order</th>
					<th>Visible</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($settings['components']['sidebar_items'] as $item_id => $item_settings) : ?>
					<tr>
						<td><strong><?php echo esc_html($item_id); ?></strong></td>
						<td><input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY . '[components][sidebar_items][' . $item_id . '][label]'); ?>" value="<?php echo esc_attr($item_settings['label']); ?>" /></td>
						<td>
							<select name="<?php echo esc_attr(self::OPTION_KEY . '[components][sidebar_items][' . $item_id . '][section]'); ?>">
								<?php foreach ($settings['components']['section_titles'] as $section_id => $section_title) : ?>
									<option value="<?php echo esc_attr($section_id); ?>" <?php selected($item_settings['section'], $section_id); ?>>
										<?php echo esc_html($section_title); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><input type="number" name="<?php echo esc_attr(self::OPTION_KEY . '[components][sidebar_items][' . $item_id . '][order]'); ?>" value="<?php echo esc_attr($item_settings['order']); ?>" style="width:90px;" /></td>
						<td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY . '[components][sidebar_items][' . $item_id . '][visible]'); ?>" value="1" <?php checked(!empty($item_settings['visible'])); ?> /> Visible</label></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$this->close_panel();
	}

	private function render_layout_tab($settings) {
		$this->open_panel('Layout and Section Order', 'Rearrange homepage sections and control which big blocks show up. This gives the admin a little more furniture-moving power without opening the code editor.');
		?>
		<h3 style="margin:0 0 12px;">Homepage Sections</h3>
		<table class="widefat striped" style="margin-top:16px;">
			<thead>
				<tr>
					<th>Section</th>
					<th>Label</th>
					<th>Order</th>
					<th>Visible</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($settings['components']['home_sections'] as $section_id => $section_settings) : ?>
					<tr>
						<td><strong><?php echo esc_html($section_id); ?></strong></td>
						<td><input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY . '[components][home_sections][' . $section_id . '][label]'); ?>" value="<?php echo esc_attr($section_settings['label']); ?>" /></td>
						<td><input type="number" style="width:90px;" name="<?php echo esc_attr(self::OPTION_KEY . '[components][home_sections][' . $section_id . '][order]'); ?>" value="<?php echo esc_attr($section_settings['order']); ?>" /></td>
						<td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY . '[components][home_sections][' . $section_id . '][visible]'); ?>" value="1" <?php checked(!empty($section_settings['visible'])); ?> /> Visible</label></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$this->close_panel();
	}

	private function open_panel($title, $description = '') {
		?>
		<section style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:24px;">
			<h2 style="margin-top:0;"><?php echo esc_html($title); ?></h2>
			<?php if ($description) : ?>
				<p><?php echo esc_html($description); ?></p>
			<?php endif; ?>
		<?php
	}

	private function close_panel() {
		echo '</section>';
	}

	private function render_discount_card_editor($index, $card = []) {
		$field_index = ((string) $index === '__INDEX__') ? '__INDEX__' : (is_numeric($index) ? (string) (int) $index : sanitize_key((string) $index));
		$base_name = self::OPTION_KEY . '[content][discount_cards][' . $field_index . ']';
		$brand = $card['brand'] ?? '';
		$category = AAC_Member_Portal_Settings_Schema::normalize_discount_category($card['category'] ?? '');
		$brand_tier = AAC_Member_Portal_Settings_Schema::normalize_discount_brand_tier($card['brand_tier'] ?? 'middle');
		$discount_percent = $card['discount_percent'] ?? '';
		$discount_code_text = $card['discount_code_text'] ?? '';
		$discount_code_text_supporter = $card['discount_code_text_supporter'] ?? '';
		$discount_code_text_partner = $card['discount_code_text_partner'] ?? '';
		$discount_code_text_leader = $card['discount_code_text_leader'] ?? '';
		$discount_code_text_advocate = $card['discount_code_text_advocate'] ?? '';
		$discount_percent_supporter = $card['discount_percent_supporter'] ?? '';
		$discount_percent_partner = $card['discount_percent_partner'] ?? '';
		$discount_percent_leader = $card['discount_percent_leader'] ?? '';
		$discount_percent_advocate = $card['discount_percent_advocate'] ?? '';
		$discount_percent_supporter = $discount_percent_supporter !== '' ? $discount_percent_supporter : $discount_percent;
		$discount_percent_partner = $discount_percent_partner !== '' ? $discount_percent_partner : $discount_percent;
		$discount_percent_leader = $discount_percent_leader !== '' ? $discount_percent_leader : $discount_percent;
		$discount_percent_advocate = $discount_percent_advocate !== '' ? $discount_percent_advocate : $discount_percent;
		$display_text = $card['display_text'] ?? '';
		$button_url = $card['button_url'] ?? '';
		$image_url = $card['image_url'] ?? '';
		$visible_tiers = AAC_Member_Portal_Settings_Schema::normalize_discount_visible_tiers($card['visible_tiers'] ?? null);
		$tier_labels = AAC_Member_Portal_Settings_Schema::get_discount_visibility_tiers();
		?>
		<div class="aac-discount-card-editor" data-aac-discount-card data-aac-discount-category="<?php echo esc_attr($category); ?>">
			<div class="aac-discount-card-editor__header">
				<h4>Benefit Card</h4>
				<div class="aac-discount-card-editor__actions">
					<button type="submit" class="button button-primary" data-aac-save-discount-card formnovalidate>Save This Benefit</button>
					<button type="button" class="button-link-delete" data-aac-remove-discount-card>Remove</button>
				</div>
			</div>
			<div class="aac-discount-card-editor__grid">
				<p>
					<label>
						<strong>Category</strong><br />
						<select class="regular-text" name="<?php echo esc_attr($base_name . '[category]'); ?>" data-aac-discount-category-select>
							<?php foreach (AAC_Member_Portal_Settings_Schema::get_discount_categories() as $category_id => $category_label) : ?>
								<option value="<?php echo esc_attr($category_id); ?>" <?php selected($category, $category_id); ?>>
									<?php echo esc_html($category_label); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
				</p>
				<p>
					<label>
						<strong>Brand</strong><br />
						<input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[brand]'); ?>" value="<?php echo esc_attr($brand); ?>" />
					</label>
				</p>
				<p>
					<label>
						<strong>Brand Tier</strong><br />
						<select class="regular-text" name="<?php echo esc_attr($base_name . '[brand_tier]'); ?>">
							<?php foreach (AAC_Member_Portal_Settings_Schema::get_discount_brand_tiers() as $brand_tier_id => $brand_tier_label) : ?>
								<option value="<?php echo esc_attr($brand_tier_id); ?>" <?php selected($brand_tier, $brand_tier_id); ?>>
									<?php echo esc_html($brand_tier_label); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
				</p>
				<div class="aac-discount-card-editor__full">
					<strong>Visible to membership tiers</strong>
					<input type="hidden" name="<?php echo esc_attr($base_name . '[visible_tiers][_configured]'); ?>" value="1" />
					<div class="aac-discount-card-editor__tiers">
						<?php foreach ($tier_labels as $tier_key => $tier_label) : ?>
							<label>
								<input type="checkbox" name="<?php echo esc_attr($base_name . '[visible_tiers][' . $tier_key . ']'); ?>" value="1" <?php checked(!empty($visible_tiers[$tier_key])); ?> />
								<?php echo esc_html($tier_label); ?>
							</label>
						<?php endforeach; ?>
					</div>
					<p class="description" style="margin:6px 0 0;">Members only see cards enabled for their membership tier. The percentage shown to a logged-in member comes from that member's tier field below. GRF and Lifetime use Advocate visibility.</p>
				</div>
				<p>
					<label>
						<strong>Supporter %</strong><br />
						<input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[discount_percent_supporter]'); ?>" value="<?php echo esc_attr($discount_percent_supporter); ?>" placeholder="15%" />
					</label>
				</p>
				<p>
					<label>
						<strong>Partner %</strong><br />
						<input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[discount_percent_partner]'); ?>" value="<?php echo esc_attr($discount_percent_partner); ?>" placeholder="20%" />
					</label>
				</p>
				<p>
					<label>
						<strong>Leader %</strong><br />
						<input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[discount_percent_leader]'); ?>" value="<?php echo esc_attr($discount_percent_leader); ?>" placeholder="25%" />
					</label>
				</p>
				<p>
					<label>
						<strong>Advocate %</strong><br />
						<input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[discount_percent_advocate]'); ?>" value="<?php echo esc_attr($discount_percent_advocate); ?>" placeholder="30%" />
					</label>
				</p>
				<p class="aac-discount-card-editor__full">
					<label>
						<strong>Fallback Details</strong><br />
						<textarea rows="2" class="large-text" name="<?php echo esc_attr($base_name . '[discount_code_text]'); ?>" placeholder="Use code AACMEMBER at checkout."><?php echo esc_textarea($discount_code_text); ?></textarea>
						<span class="description">Used only when the selected member level details below are blank.</span>
					</label>
				</p>
				<p>
					<label>
						<strong>Supporter Details</strong><br />
						<textarea rows="2" class="large-text" name="<?php echo esc_attr($base_name . '[discount_code_text_supporter]'); ?>" placeholder="Supporter discount details."><?php echo esc_textarea($discount_code_text_supporter); ?></textarea>
					</label>
				</p>
				<p>
					<label>
						<strong>Partner Details</strong><br />
						<textarea rows="2" class="large-text" name="<?php echo esc_attr($base_name . '[discount_code_text_partner]'); ?>" placeholder="Partner discount details."><?php echo esc_textarea($discount_code_text_partner); ?></textarea>
					</label>
				</p>
				<p>
					<label>
						<strong>Leader Details</strong><br />
						<textarea rows="2" class="large-text" name="<?php echo esc_attr($base_name . '[discount_code_text_leader]'); ?>" placeholder="Leader discount details."><?php echo esc_textarea($discount_code_text_leader); ?></textarea>
					</label>
				</p>
				<p>
					<label>
						<strong>Advocate Details</strong><br />
						<textarea rows="2" class="large-text" name="<?php echo esc_attr($base_name . '[discount_code_text_advocate]'); ?>" placeholder="Advocate discount details."><?php echo esc_textarea($discount_code_text_advocate); ?></textarea>
					</label>
				</p>
				<p class="aac-discount-card-editor__full">
					<label>
						<strong>Short Card/Search Description</strong><br />
						<textarea rows="3" class="large-text" name="<?php echo esc_attr($base_name . '[display_text]'); ?>"><?php echo esc_textarea($display_text); ?></textarea>
						<span class="description">Used for searching/admin context. The public dropdown shows the member-level details above to avoid duplicate descriptions.</span>
					</label>
				</p>
				<p class="aac-discount-card-editor__full">
					<label>
						<strong>Button URL</strong><br />
						<input type="url" class="large-text" name="<?php echo esc_attr($base_name . '[button_url]'); ?>" value="<?php echo esc_attr($button_url); ?>" />
					</label>
				</p>
				<div class="aac-discount-card-editor__full">
					<label>
						<strong>Card Image</strong><br />
						<input type="url" class="large-text aac-discount-card-editor__image-input" name="<?php echo esc_attr($base_name . '[image_url]'); ?>" value="<?php echo esc_attr($image_url); ?>" />
					</label>
					<p style="margin:8px 0 0;">
						<button type="button" class="button button-secondary" data-aac-select-discount-image>Select Image</button>
					</p>
					<div class="aac-discount-card-editor__preview">
						<?php if ($image_url) : ?>
							<img src="<?php echo esc_url($image_url); ?>" alt="" />
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_discount_card_template() {
		ob_start();
		$this->render_discount_card_editor('__INDEX__', []);
		$template = ob_get_clean();
		?>
		<template id="aac-discount-card-template"><?php echo str_replace('__INDEX__', '__INDEX__', $template); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		<style>
			.aac-discount-card-editor{border:1px solid #dcdcde;border-radius:12px;padding:16px;background:#fff;margin-bottom:16px}
			.aac-discount-admin__toolbar{display:flex;justify-content:flex-start;margin:0 0 16px}
			.aac-discount-admin__tabs{display:flex;flex-wrap:wrap;gap:10px;margin:0 0 16px}
			.aac-discount-admin__tabs .button{min-width:180px;justify-content:center;text-align:center}
			.aac-discount-card-editor__header{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}
			.aac-discount-card-editor__header h4{margin:0}
			.aac-discount-card-editor__actions{display:flex;align-items:center;gap:12px}
			.aac-discount-card-editor__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
			.aac-discount-card-editor__full{grid-column:1 / -1}
			.aac-discount-card-editor__tiers{display:flex;flex-wrap:wrap;gap:12px;margin-top:8px}
			.aac-discount-card-editor__tiers label{display:inline-flex;align-items:center;gap:6px;border:1px solid #dcdcde;border-radius:999px;padding:6px 10px;background:#f6f7f7}
			.aac-discount-card-editor__preview{margin-top:12px;min-height:64px}
			.aac-discount-card-editor__preview img{display:block;max-width:220px;width:100%;height:auto;border-radius:8px;border:1px solid #dcdcde}
			.aac-benefits-gallery-admin{border:1px solid #dcdcde;background:#fff;padding:16px;margin-bottom:18px}
			.aac-benefits-gallery-admin__panel{border:1px solid #e0e0e0;background:#fff;padding:16px}
			.aac-benefits-gallery-admin__media-row{display:flex;gap:10px;align-items:center}
			.aac-benefits-gallery-admin__media-row input{flex:1}
			.aac-benefits-gallery-admin__preview img{display:block;max-width:260px;width:100%;height:auto;border:1px solid #dcdcde;background:#f6f7f7}
			@media (max-width: 782px){.aac-discount-card-editor__grid{grid-template-columns:1fr}}
		</style>
		<script>
			document.addEventListener('DOMContentLoaded', function () {
				const list = document.getElementById('aac-discount-cards');
				const template = document.getElementById('aac-discount-card-template');
				const addButton = document.getElementById('aac-add-discount-card');
				const tabButtons = Array.from(document.querySelectorAll('[data-aac-discount-admin-tab]'));
				document.querySelectorAll('[data-aac-benefits-gallery-admin]').forEach((root) => {
					const galleryTabs = Array.from(root.querySelectorAll('[data-aac-benefits-gallery-tab]'));
					const galleryPanels = Array.from(root.querySelectorAll('[data-aac-benefits-gallery-panel]'));
					const activateGalleryTab = (tabId) => {
						galleryTabs.forEach((button) => {
							const active = button.dataset.aacBenefitsGalleryTab === tabId;
							button.classList.toggle('button-primary', active);
							button.classList.toggle('button-secondary', !active);
						});
						galleryPanels.forEach((panel) => {
							panel.hidden = panel.dataset.aacBenefitsGalleryPanel !== tabId;
						});
					};

					galleryTabs.forEach((button) => {
						button.addEventListener('click', () => activateGalleryTab(button.dataset.aacBenefitsGalleryTab || 'discounts'));
					});

					root.querySelectorAll('[data-aac-select-benefits-gallery-image]').forEach((button) => {
						button.addEventListener('click', () => {
							if (!window.wp || !window.wp.media) {
								return;
							}

							const panel = button.closest('[data-aac-benefits-gallery-panel]');
							const imageInput = panel ? panel.querySelector('.aac-benefits-gallery-admin__image-input') : null;
							const preview = panel ? panel.querySelector('.aac-benefits-gallery-admin__preview') : null;
							const frame = window.wp.media({
								title: 'Select benefits gallery image',
								button: { text: 'Use image' },
								multiple: false,
							});

							frame.on('select', () => {
								const attachment = frame.state().get('selection').first().toJSON();
								const nextUrl = attachment.url || '';
								if (imageInput) {
									imageInput.value = nextUrl;
								}
								if (preview) {
									preview.innerHTML = nextUrl ? '<img src="' + nextUrl.replace(/"/g, '&quot;') + '" alt="" />' : '';
								}
							});

							frame.open();
						});
					});
				});
				document.querySelectorAll('[data-aac-select-signup-matrix-image]').forEach((button) => {
					button.addEventListener('click', () => {
						if (!window.wp || !window.wp.media) return;
						const row = button.closest('[data-aac-signup-matrix-media]');
						const imageInput = row ? row.querySelector('input[type="url"]') : null;
						const preview = document.querySelector('[data-aac-signup-matrix-preview]');
						const frame = window.wp.media({ title: 'Select signup benefits matrix', button: { text: 'Use image' }, multiple: false });
						frame.on('select', () => {
							const attachment = frame.state().get('selection').first().toJSON();
							const nextUrl = attachment.url || '';
							if (imageInput) imageInput.value = nextUrl;
							if (preview) preview.innerHTML = nextUrl ? '<img src="' + nextUrl.replace(/"/g, '&quot;') + '" alt="" />' : '';
						});
						frame.open();
					});
				});
				if (!list || !template || !addButton) {
					return;
				}
				let activeCategory = 'discount-brands';

				const applyCategoryFilter = () => {
					list.querySelectorAll('[data-aac-discount-card]').forEach((card) => {
						const cardCategory = card.dataset.aacDiscountCategory || 'discount-brands';
						card.hidden = cardCategory !== activeCategory;
					});
					tabButtons.forEach((button) => {
						const active = button.dataset.aacDiscountAdminTab === activeCategory;
						button.classList.toggle('button-primary', active);
						button.classList.toggle('button-secondary', !active);
					});
				};

				const refreshIndexes = () => {
					list.querySelectorAll('[data-aac-discount-card]').forEach((card, index) => {
						card.querySelectorAll('[name]').forEach((field) => {
							field.name = field.name.replace(/\[discount_cards\]\[[^\]]+\]/, '[discount_cards][' + index + ']');
						});
					});
				};

				const settingsForm = list.closest('form');
				if (settingsForm) {
					settingsForm.addEventListener('submit', refreshIndexes);
				}

				const updatePreview = (card) => {
					const input = card.querySelector('.aac-discount-card-editor__image-input');
					const preview = card.querySelector('.aac-discount-card-editor__preview');
					if (!input || !preview) {
						return;
					}

					const nextUrl = String(input.value || '').trim();
					preview.innerHTML = nextUrl ? '<img src=\"' + nextUrl.replace(/\"/g, '&quot;') + '\" alt=\"\" />' : '';
				};

				const bindCard = (card) => {
					const removeButton = card.querySelector('[data-aac-remove-discount-card]');
					const saveButton = card.querySelector('[data-aac-save-discount-card]');
					const selectButton = card.querySelector('[data-aac-select-discount-image]');
					const imageInput = card.querySelector('.aac-discount-card-editor__image-input');
					const categorySelect = card.querySelector('[data-aac-discount-category-select]');

					if (saveButton && settingsForm) {
						saveButton.addEventListener('click', () => {
							refreshIndexes();
						});
					}

					if (removeButton) {
						removeButton.addEventListener('click', () => {
							card.remove();
							refreshIndexes();
						});
					}

					if (imageInput) {
						imageInput.addEventListener('input', () => updatePreview(card));
					}

					if (categorySelect) {
						card.dataset.aacDiscountCategory = categorySelect.value || 'discount-brands';
						categorySelect.addEventListener('change', () => {
							card.dataset.aacDiscountCategory = categorySelect.value || 'discount-brands';
							applyCategoryFilter();
						});
					}

					if (selectButton && window.wp && window.wp.media) {
						selectButton.addEventListener('click', () => {
							const frame = window.wp.media({
								title: 'Select discount card image',
								button: { text: 'Use image' },
								multiple: false,
							});

							frame.on('select', () => {
								const attachment = frame.state().get('selection').first().toJSON();
								if (imageInput) {
									imageInput.value = attachment.url || '';
									updatePreview(card);
								}
							});

							frame.open();
						});
					}
				};

				tabButtons.forEach((button) => {
					button.addEventListener('click', () => {
						activeCategory = button.dataset.aacDiscountAdminTab || 'discount-brands';
						applyCategoryFilter();
					});
				});

				list.querySelectorAll('[data-aac-discount-card]').forEach(bindCard);
				applyCategoryFilter();

				addButton.addEventListener('click', () => {
					const nextIndex = list.querySelectorAll('[data-aac-discount-card]').length;
					const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
					const wrapper = document.createElement('div');
					wrapper.innerHTML = html.trim();
					const card = wrapper.firstElementChild;
					if (!card) {
						return;
					}
					list.appendChild(card);
					const categorySelect = card.querySelector('[data-aac-discount-category-select]');
					if (categorySelect) {
						categorySelect.value = activeCategory;
						card.dataset.aacDiscountCategory = activeCategory;
					}
					bindCard(card);
					refreshIndexes();
					applyCategoryFilter();
				});
			});
		</script>
		<?php
	}

	private function render_media_row($name, $label, $value, $help = '') {
		$field_id = sanitize_title($name);
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($label); ?></label></th>
			<td>
				<div style="display:flex;gap:8px;align-items:center;max-width:720px;">
					<input type="url" id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" class="large-text" data-aac-media-input />
					<button type="button" class="button button-secondary" data-aac-select-media data-target="<?php echo esc_attr($field_id); ?>">Select Media</button>
				</div>
				<?php if ($help) : ?>
					<p class="description"><?php echo esc_html($help); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function render_input_row($name, $label, $value, $type = 'text', $help = '', $min = null, $max = null, $step = null) {
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label></th>
			<td>
				<input
					type="<?php echo esc_attr($type); ?>"
					id="<?php echo esc_attr($name); ?>"
					name="<?php echo esc_attr($name); ?>"
					value="<?php echo esc_attr($value); ?>"
					class="regular-text"
					<?php echo $min !== null ? 'min="' . esc_attr($min) . '"' : ''; ?>
					<?php echo $max !== null ? 'max="' . esc_attr($max) . '"' : ''; ?>
					<?php echo $step !== null ? 'step="' . esc_attr($step) . '"' : ''; ?>
				/>
				<?php if ($help) : ?>
					<p class="description"><?php echo esc_html($help); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function render_contact_issue_types_row($settings) {
		$issue_types = isset($settings['content']['contact_issue_types']) && is_array($settings['content']['contact_issue_types'])
			? AAC_Member_Portal_Settings_Schema::normalize_contact_issue_types($settings['content']['contact_issue_types'])
			: self::get_default_contact_issue_types();
		$field_name = self::OPTION_KEY . '[content][contact_issue_types][]';
		?>
		<tr>
			<th scope="row">Contact form dropdown options</th>
			<td>
				<input type="hidden" name="<?php echo esc_attr($field_name); ?>" value="" />
				<div id="aac-contact-issue-types-list" style="display:flex;flex-direction:column;gap:8px;max-width:520px;">
					<?php foreach ($issue_types as $issue_type) : ?>
						<div style="display:flex;gap:8px;align-items:center;" data-aac-contact-issue-type-row>
							<input
								type="text"
								name="<?php echo esc_attr($field_name); ?>"
								value="<?php echo esc_attr($issue_type); ?>"
								class="regular-text"
								placeholder="Issue type label"
							/>
							<button type="button" class="button button-secondary" data-aac-remove-contact-issue-type>Remove</button>
						</div>
					<?php endforeach; ?>
				</div>
				<p style="margin-top:10px;">
					<button type="button" class="button button-secondary" id="aac-add-contact-issue-type">Add issue type</button>
				</p>
				<p class="description">These labels appear in the member app Contact Us dropdown and become the email subject category.</p>
				<script>
					(() => {
						const list = document.getElementById('aac-contact-issue-types-list');
						const addButton = document.getElementById('aac-add-contact-issue-type');
						if (!list || !addButton) {
							return;
						}

						const bindRemove = (row) => {
							const removeButton = row.querySelector('[data-aac-remove-contact-issue-type]');
							if (removeButton) {
								removeButton.addEventListener('click', () => row.remove());
							}
						};

						list.querySelectorAll('[data-aac-contact-issue-type-row]').forEach(bindRemove);
						addButton.addEventListener('click', () => {
							const row = document.createElement('div');
							row.style.display = 'flex';
							row.style.gap = '8px';
							row.style.alignItems = 'center';
							row.setAttribute('data-aac-contact-issue-type-row', '1');
							row.innerHTML = '<input type="text" name="<?php echo esc_js($field_name); ?>" value="" class="regular-text" placeholder="Issue type label" /> <button type="button" class="button button-secondary" data-aac-remove-contact-issue-type>Remove</button>';
							list.appendChild(row);
							bindRemove(row);
							const input = row.querySelector('input');
							if (input) {
								input.focus();
							}
						});
					})();
				</script>
			</td>
		</tr>
		<?php
	}

	private function render_textarea_row($name, $label, $value) {
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label></th>
			<td>
				<textarea id="<?php echo esc_attr($name); ?>" name="<?php echo esc_attr($name); ?>" rows="3" class="large-text"><?php echo esc_textarea($value); ?></textarea>
			</td>
		</tr>
		<?php
	}

	private function render_long_textarea_row($name, $label, $value, $help = '') {
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label></th>
			<td>
				<textarea id="<?php echo esc_attr($name); ?>" name="<?php echo esc_attr($name); ?>" rows="14" class="large-text code"><?php echo esc_textarea($value); ?></textarea>
				<?php if ($help) : ?>
					<p class="description"><?php echo esc_html($help); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function render_confirmation_letter_format_row($settings) {
		$current = isset($settings['content']['confirmation_letter_format']) ? sanitize_key($settings['content']['confirmation_letter_format']) : 'standard';
		$formats = [
			'standard' => 'Standard letter',
			'compact' => 'Compact letter',
		];
		$name = self::OPTION_KEY . '[content][confirmation_letter_format]';
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr($name); ?>">Confirmation letter format</label></th>
			<td>
				<select id="<?php echo esc_attr($name); ?>" name="<?php echo esc_attr($name); ?>">
					<?php foreach ($formats as $value => $label) : ?>
						<option value="<?php echo esc_attr($value); ?>" <?php selected($current, $value); ?>>
							<?php echo esc_html($label); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description">Controls the browser preview and modal letter spacing. The PDF keeps a standard letter-size layout.</p>
			</td>
		</tr>
		<?php
	}

	private function render_home_involvement_card_editor($index, $card = []) {
		$base_name = self::OPTION_KEY . '[content][home_involvement_cards][' . (int) $index . ']';
		?>
		<div class="aac-home-card-editor" data-aac-home-involvement-card>
			<div class="aac-home-card-editor__header">
				<h4>Involvement Card</h4>
				<button type="button" class="button-link-delete" data-aac-remove-home-card>Remove</button>
			</div>
			<div class="aac-home-card-editor__grid">
				<p><label><strong>Title</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[title]'); ?>" value="<?php echo esc_attr($card['title'] ?? ''); ?>" /></label></p>
				<p><label><strong>Accent Style</strong><br />
					<select name="<?php echo esc_attr($base_name . '[accent_style]'); ?>">
						<?php foreach (['gold' => 'Gold', 'light' => 'Light', 'sand' => 'Sand', 'dark' => 'Dark'] as $style_value => $style_label) : ?>
							<option value="<?php echo esc_attr($style_value); ?>" <?php selected($card['accent_style'] ?? '', $style_value); ?>><?php echo esc_html($style_label); ?></option>
						<?php endforeach; ?>
					</select>
				</label></p>
				<p class="aac-home-card-editor__full"><label><strong>Description</strong><br /><textarea rows="3" class="large-text" name="<?php echo esc_attr($base_name . '[description]'); ?>"><?php echo esc_textarea($card['description'] ?? ''); ?></textarea></label></p>
				<p><label><strong>Button Label</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[button_label]'); ?>" value="<?php echo esc_attr($card['button_label'] ?? ''); ?>" /></label></p>
				<p><label><strong>Button URL</strong><br /><input type="url" class="large-text" name="<?php echo esc_attr($base_name . '[button_url]'); ?>" value="<?php echo esc_attr($card['button_url'] ?? ''); ?>" /></label></p>
				<div class="aac-home-card-editor__full">
					<label><strong>Image URL</strong><br /><input type="url" class="large-text aac-home-card-editor__image-input" name="<?php echo esc_attr($base_name . '[image_url]'); ?>" value="<?php echo esc_attr($card['image_url'] ?? ''); ?>" /></label>
					<p style="margin:8px 0 0;"><button type="button" class="button button-secondary" data-aac-select-home-image>Select Image</button></p>
					<div class="aac-home-card-editor__preview"><?php if (!empty($card['image_url'])) : ?><img src="<?php echo esc_url($card['image_url']); ?>" alt="" /><?php endif; ?></div>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_home_publication_card_editor($index, $card = []) {
		$base_name = self::OPTION_KEY . '[content][home_publication_cards][' . (int) $index . ']';
		?>
		<div class="aac-home-card-editor" data-aac-home-publication-card>
			<div class="aac-home-card-editor__header">
				<h4>Publication Card</h4>
				<button type="button" class="button-link-delete" data-aac-remove-home-card>Remove</button>
			</div>
			<div class="aac-home-card-editor__grid">
				<p><label><strong>Title</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[title]'); ?>" value="<?php echo esc_attr($card['title'] ?? ''); ?>" /></label></p>
				<p><label><strong>Accent Color</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[accent_color]'); ?>" value="<?php echo esc_attr($card['accent_color'] ?? ''); ?>" placeholder="#f8c235" /></label></p>
				<p class="aac-home-card-editor__full"><label><strong>Description</strong><br /><textarea rows="3" class="large-text" name="<?php echo esc_attr($base_name . '[description]'); ?>"><?php echo esc_textarea($card['description'] ?? ''); ?></textarea></label></p>
				<p><label><strong>Button Label</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[button_label]'); ?>" value="<?php echo esc_attr($card['button_label'] ?? ''); ?>" /></label></p>
				<p><label><strong>Button URL</strong><br /><input type="url" class="large-text" name="<?php echo esc_attr($base_name . '[button_url]'); ?>" value="<?php echo esc_attr($card['button_url'] ?? ''); ?>" /></label></p>
				<div class="aac-home-card-editor__full">
					<label><strong>Image URL</strong><br /><input type="url" class="large-text aac-home-card-editor__image-input" name="<?php echo esc_attr($base_name . '[image_url]'); ?>" value="<?php echo esc_attr($card['image_url'] ?? ''); ?>" /></label>
					<p style="margin:8px 0 0;"><button type="button" class="button button-secondary" data-aac-select-home-image>Select Image</button></p>
					<div class="aac-home-card-editor__preview"><?php if (!empty($card['image_url'])) : ?><img src="<?php echo esc_url($card['image_url']); ?>" alt="" /><?php endif; ?></div>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_home_partner_logo_editor($index, $logo = []) {
		$base_name = self::OPTION_KEY . '[content][home_partner_logos][' . (int) $index . ']';
		?>
		<div class="aac-home-card-editor" data-aac-home-partner-logo>
			<div class="aac-home-card-editor__header">
				<h4>Partner Logo</h4>
				<button type="button" class="button-link-delete" data-aac-remove-home-card>Remove</button>
			</div>
			<div class="aac-home-card-editor__grid">
				<p><label><strong>Name</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[name]'); ?>" value="<?php echo esc_attr($logo['name'] ?? ''); ?>" /></label></p>
				<p><label><strong>Link URL</strong><br /><input type="url" class="large-text" name="<?php echo esc_attr($base_name . '[link_url]'); ?>" value="<?php echo esc_attr($logo['link_url'] ?? ''); ?>" /></label></p>
				<div class="aac-home-card-editor__full">
					<label><strong>Logo Image URL</strong><br /><input type="url" class="large-text aac-home-card-editor__image-input" name="<?php echo esc_attr($base_name . '[image_url]'); ?>" value="<?php echo esc_attr($logo['image_url'] ?? ''); ?>" /></label>
					<p style="margin:8px 0 0;"><button type="button" class="button button-secondary" data-aac-select-home-image>Select Image</button></p>
					<div class="aac-home-card-editor__preview"><?php if (!empty($logo['image_url'])) : ?><img src="<?php echo esc_url($logo['image_url']); ?>" alt="" /><?php endif; ?></div>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_member_profile_block_editor($index, $block = []) {
		$base_name = self::OPTION_KEY . '[content][member_profile_blocks][' . $index . ']';
		$entries = isset($block['entries']) && is_array($block['entries']) ? array_values($block['entries']) : [];
		$icon = sanitize_key($block['icon'] ?? 'receipt');
		if (!in_array($icon, ['receipt', 'user', 'shield', 'users', 'heart', 'credit-card', 'calendar'], true)) {
			$icon = 'receipt';
		}
		?>
		<div class="aac-home-card-editor" data-aac-member-profile-block>
			<div class="aac-home-card-editor__header">
				<h4>Member Profile Block</h4>
				<button type="button" class="button-link-delete" data-aac-remove-profile-block>Remove</button>
			</div>
			<div class="aac-home-card-editor__grid">
				<p><label><strong>Title</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[title]'); ?>" value="<?php echo esc_attr($block['title'] ?? ''); ?>" /></label></p>
				<p><label><strong>Icon</strong><br />
					<select name="<?php echo esc_attr($base_name . '[icon]'); ?>">
						<?php foreach (['receipt' => 'Receipt', 'user' => 'User', 'shield' => 'Shield', 'users' => 'Users', 'heart' => 'Heart', 'credit-card' => 'Credit Card', 'calendar' => 'Calendar'] as $icon_value => $icon_label) : ?>
							<option value="<?php echo esc_attr($icon_value); ?>" <?php selected($icon, $icon_value); ?>><?php echo esc_html($icon_label); ?></option>
						<?php endforeach; ?>
					</select>
				</label></p>
				<p class="aac-home-card-editor__full"><label><strong>Description</strong><br /><textarea rows="2" class="large-text" name="<?php echo esc_attr($base_name . '[description]'); ?>"><?php echo esc_textarea($block['description'] ?? ''); ?></textarea></label></p>
				<p><label><strong>Button label</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[button_label]'); ?>" value="<?php echo esc_attr($block['button_label'] ?? ''); ?>" /></label></p>
				<p><label><strong>Button URL</strong><br /><input type="url" class="regular-text" name="<?php echo esc_attr($base_name . '[button_url]'); ?>" value="<?php echo esc_attr($block['button_url'] ?? ''); ?>" /></label></p>
			</div>

			<div class="aac-profile-block-entries">
				<h5 style="margin:18px 0 10px;">Entries</h5>
				<div class="aac-profile-block-entries__list" data-aac-profile-entry-list>
					<?php foreach ($entries as $entry_index => $entry) : ?>
						<?php $this->render_member_profile_block_entry_editor($index, $entry_index, $entry); ?>
					<?php endforeach; ?>
				</div>
				<p style="margin-top:12px;">
					<button type="button" class="button button-secondary" data-aac-add-profile-entry>Add Entry</button>
				</p>
			</div>
		</div>
		<?php
	}

	private function render_member_profile_block_entry_editor($block_index, $entry_index, $entry = []) {
		$base_name = self::OPTION_KEY . '[content][member_profile_blocks][' . $block_index . '][entries][' . $entry_index . ']';
		?>
		<div class="aac-profile-entry-editor" data-aac-member-profile-entry>
			<div class="aac-profile-entry-editor__header">
				<h5>Entry</h5>
				<button type="button" class="button-link-delete" data-aac-remove-profile-entry>Remove</button>
			</div>
			<div class="aac-home-card-editor__grid">
				<p><label><strong>Label</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[label]'); ?>" value="<?php echo esc_attr($entry['label'] ?? ''); ?>" /></label></p>
				<p><label><strong>Value</strong><br /><input type="text" class="regular-text" name="<?php echo esc_attr($base_name . '[value]'); ?>" value="<?php echo esc_attr($entry['value'] ?? ''); ?>" /></label></p>
				<p class="aac-home-card-editor__full"><label><strong>Description</strong><br /><textarea rows="2" class="large-text" name="<?php echo esc_attr($base_name . '[description]'); ?>"><?php echo esc_textarea($entry['description'] ?? ''); ?></textarea></label></p>
			</div>
		</div>
		<?php
	}

	private function render_home_repeater_templates() {
		ob_start();
		$this->render_home_involvement_card_editor('__INDEX__', []);
		$involvement_template = ob_get_clean();
		ob_start();
		$this->render_home_publication_card_editor('__INDEX__', []);
		$publication_template = ob_get_clean();
		ob_start();
		$this->render_home_partner_logo_editor('__INDEX__', []);
		$partner_template = ob_get_clean();
		?>
		<template id="aac-home-involvement-card-template"><?php echo str_replace('__INDEX__', '__INDEX__', $involvement_template); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		<template id="aac-home-publication-card-template"><?php echo str_replace('__INDEX__', '__INDEX__', $publication_template); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		<template id="aac-home-partner-logo-template"><?php echo str_replace('__INDEX__', '__INDEX__', $partner_template); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		<style>
			.aac-home-card-editor{border:1px solid #dcdcde;border-radius:12px;padding:16px;background:#fff;margin-bottom:16px}
			.aac-home-card-editor__header{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}
			.aac-home-card-editor__header h4{margin:0}
			.aac-home-card-editor__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
			.aac-home-card-editor__full{grid-column:1 / -1}
			.aac-home-card-editor__preview{margin-top:12px;min-height:64px}
			.aac-home-card-editor__preview img{display:block;max-width:220px;width:100%;height:auto;border-radius:8px;border:1px solid #dcdcde}
			@media (max-width: 782px){.aac-home-card-editor__grid{grid-template-columns:1fr}}
		</style>
		<?php
	}

	private function render_member_profile_block_templates() {
		ob_start();
		$this->render_member_profile_block_editor('__INDEX__', []);
		$block_template = ob_get_clean();
		ob_start();
		$this->render_member_profile_block_entry_editor('__INDEX__', '__ENTRY_INDEX__', []);
		$entry_template = ob_get_clean();
		?>
		<template id="aac-member-profile-block-template"><?php echo str_replace('__INDEX__', '__INDEX__', $block_template); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		<template id="aac-member-profile-entry-template"><?php echo str_replace(['__INDEX__', '__ENTRY_INDEX__'], ['__INDEX__', '__ENTRY_INDEX__'], $entry_template); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
		<style>
			.aac-profile-block-entries{margin-top:18px;border-top:1px solid #dcdcde;padding-top:16px}
			.aac-profile-entry-editor{border:1px solid #dcdcde;border-radius:12px;padding:14px;background:#f8f8f8;margin-bottom:12px}
			.aac-profile-entry-editor__header{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:10px}
			.aac-profile-entry-editor__header h5{margin:0}
		</style>
		<?php
	}

	private function render_shared_admin_scripts() {
		?>
		<script>
			document.addEventListener('DOMContentLoaded', function () {
				const openMediaFrame = (onSelect) => {
					if (!(window.wp && window.wp.media)) {
						return;
					}
					const frame = window.wp.media({
						title: 'Select media',
						button: { text: 'Use media' },
						multiple: false,
					});
					frame.on('select', () => {
						const attachment = frame.state().get('selection').first().toJSON();
						onSelect(attachment);
					});
					frame.open();
				};

				document.querySelectorAll('[data-aac-select-media]').forEach((button) => {
					button.addEventListener('click', () => {
						const target = document.getElementById(button.getAttribute('data-target'));
						if (!target) {
							return;
						}
						openMediaFrame((attachment) => {
							target.value = attachment.url || '';
							target.dispatchEvent(new Event('input', { bubbles: true }));
						});
					});
				});

				const bindImagePickerList = ({ listId, addButtonId, templateId, marker, replacePattern }) => {
					const list = document.getElementById(listId);
					const template = document.getElementById(templateId);
					const addButton = document.getElementById(addButtonId);
					if (!list || !template || !addButton) {
						return;
					}

					const refreshIndexes = () => {
						list.querySelectorAll(marker).forEach((card, index) => {
							card.querySelectorAll('[name]').forEach((field) => {
								field.name = field.name.replace(replacePattern, '$1[' + index + ']');
							});
						});
					};

					const updatePreview = (card) => {
						const input = card.querySelector('.aac-home-card-editor__image-input');
						const preview = card.querySelector('.aac-home-card-editor__preview');
						if (!input || !preview) {
							return;
						}
						const nextUrl = String(input.value || '').trim();
						preview.innerHTML = nextUrl ? '<img src="' + nextUrl.replace(/"/g, '&quot;') + '" alt="" />' : '';
					};

					const bindCard = (card) => {
						const removeButton = card.querySelector('[data-aac-remove-home-card]');
						const imageInput = card.querySelector('.aac-home-card-editor__image-input');
						const selectButton = card.querySelector('[data-aac-select-home-image]');

						if (removeButton) {
							removeButton.addEventListener('click', () => {
								card.remove();
								refreshIndexes();
							});
						}

						if (imageInput) {
							imageInput.addEventListener('input', () => updatePreview(card));
						}

						if (selectButton) {
							selectButton.addEventListener('click', () => {
								openMediaFrame((attachment) => {
									if (imageInput) {
										imageInput.value = attachment.url || '';
										updatePreview(card);
									}
								});
							});
						}
					};

					list.querySelectorAll(marker).forEach(bindCard);
					addButton.addEventListener('click', () => {
						const nextIndex = list.querySelectorAll(marker).length;
						const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
						const wrapper = document.createElement('div');
						wrapper.innerHTML = html.trim();
						const card = wrapper.firstElementChild;
						if (!card) {
							return;
						}
						list.appendChild(card);
						bindCard(card);
						refreshIndexes();
					});
				};

				bindImagePickerList({
					listId: 'aac-home-involvement-cards',
					addButtonId: 'aac-add-home-involvement-card',
					templateId: 'aac-home-involvement-card-template',
					marker: '[data-aac-home-involvement-card]',
					replacePattern: /(\[home_involvement_cards\])\[[^\]]+\]/,
				});
				bindImagePickerList({
					listId: 'aac-home-publication-cards',
					addButtonId: 'aac-add-home-publication-card',
					templateId: 'aac-home-publication-card-template',
					marker: '[data-aac-home-publication-card]',
					replacePattern: /(\[home_publication_cards\])\[[^\]]+\]/,
				});
				bindImagePickerList({
					listId: 'aac-home-partner-logos',
					addButtonId: 'aac-add-home-partner-logo',
					templateId: 'aac-home-partner-logo-template',
					marker: '[data-aac-home-partner-logo]',
					replacePattern: /(\[home_partner_logos\])\[[^\]]+\]/,
				});
				const bindMemberProfileBlocks = () => {
					const list = document.getElementById('aac-member-profile-blocks');
					const template = document.getElementById('aac-member-profile-block-template');
					const entryTemplate = document.getElementById('aac-member-profile-entry-template');
					const addButton = document.getElementById('aac-add-member-profile-block');
					if (!list || !template || !entryTemplate || !addButton) {
						return;
					}

					const refreshIndexes = () => {
						list.querySelectorAll('[data-aac-member-profile-block]').forEach((block, blockIndex) => {
							block.querySelectorAll('[name]').forEach((field) => {
								field.name = field.name.replace(/(\[member_profile_blocks\])\[[^\]]+\]/, '$1[' + blockIndex + ']');
							});
							block.querySelectorAll('[data-aac-member-profile-entry]').forEach((entry, entryIndex) => {
								entry.querySelectorAll('[name]').forEach((field) => {
									field.name = field.name.replace(/(\[entries\])\[[^\]]+\]/, '$1[' + entryIndex + ']');
								});
							});
						});
					};

					const bindEntry = (entry) => {
						const removeButton = entry.querySelector('[data-aac-remove-profile-entry]');
						if (removeButton) {
							removeButton.addEventListener('click', () => {
								const block = entry.closest('[data-aac-member-profile-block]');
								entry.remove();
								if (block) {
									refreshIndexes();
								}
							});
						}
					};

					const addEntryToBlock = (block) => {
						const entryList = block.querySelector('[data-aac-profile-entry-list]');
						if (!entryList) {
							return;
						}
						const nextEntryIndex = entryList.querySelectorAll('[data-aac-member-profile-entry]').length;
						const html = entryTemplate.innerHTML
							.replace(/__INDEX__/g, '__INDEX__')
							.replace(/__ENTRY_INDEX__/g, String(nextEntryIndex));
						const wrapper = document.createElement('div');
						wrapper.innerHTML = html.trim();
						const entry = wrapper.firstElementChild;
						if (!entry) {
							return;
						}
						entryList.appendChild(entry);
						bindEntry(entry);
						refreshIndexes();
					};

					const bindBlock = (block) => {
						const removeButton = block.querySelector('[data-aac-remove-profile-block]');
						const addEntryButton = block.querySelector('[data-aac-add-profile-entry]');
						if (removeButton) {
							removeButton.addEventListener('click', () => {
								block.remove();
								refreshIndexes();
							});
						}
						if (addEntryButton) {
							addEntryButton.addEventListener('click', () => addEntryToBlock(block));
						}
						block.querySelectorAll('[data-aac-member-profile-entry]').forEach(bindEntry);
					};

					list.querySelectorAll('[data-aac-member-profile-block]').forEach(bindBlock);
					addButton.addEventListener('click', () => {
						const nextIndex = list.querySelectorAll('[data-aac-member-profile-block]').length;
						const html = template.innerHTML
							.replace(/__INDEX__/g, String(nextIndex))
							.replace(/__ENTRY_INDEX__/g, '0');
						const wrapper = document.createElement('div');
						wrapper.innerHTML = html.trim();
						const block = wrapper.firstElementChild;
						if (!block) {
							return;
						}
						list.appendChild(block);
						bindBlock(block);
						refreshIndexes();
					});
				};

				bindMemberProfileBlocks();
			});
		</script>
		<?php
	}


}
