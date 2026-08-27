<?php
/**
 * Plugin Name: AAC Member Portal
 * Description: Embeds the AAC React member portal inside WordPress and exposes REST endpoints for member profile data (Paid Memberships Pro integration).
 * Version: 1.0.547
 * Author: AAC
 */

if (!defined('ABSPATH')) {
	exit;
}

define('AAC_MEMBER_PORTAL_VERSION', '1.0.547');
define('AAC_MEMBER_PORTAL_FILE', __FILE__);
define('AAC_MEMBER_PORTAL_DIR', plugin_dir_path(__FILE__));
define('AAC_MEMBER_PORTAL_URL', plugin_dir_url(__FILE__));

require_once AAC_MEMBER_PORTAL_DIR . 'includes/class-aac-member-portal-pmpro.php';
require_once AAC_MEMBER_PORTAL_DIR . 'includes/class-aac-member-portal-error-log.php';
require_once AAC_MEMBER_PORTAL_DIR . 'includes/class-aac-member-portal-group-accounts.php';
require_once AAC_MEMBER_PORTAL_DIR . 'includes/class-aac-member-portal-impersonation.php';
require_once AAC_MEMBER_PORTAL_DIR . 'includes/class-aac-member-portal-api.php';
require_once AAC_MEMBER_PORTAL_DIR . 'includes/class-aac-member-portal-redpoint-api.php';
require_once AAC_MEMBER_PORTAL_DIR . 'includes/class-aac-member-portal-settings-schema.php';
require_once AAC_MEMBER_PORTAL_DIR . 'includes/class-aac-member-portal-admin.php';
require_once AAC_MEMBER_PORTAL_DIR . 'includes/class-aac-member-portal-runtime-config.php';
require_once AAC_MEMBER_PORTAL_DIR . 'includes/class-aac-member-portal-member-database.php';
require_once AAC_MEMBER_PORTAL_DIR . 'includes/class-aac-member-portal-daily-member-export.php';
require_once AAC_MEMBER_PORTAL_DIR . 'includes/class-aac-member-portal-import-manager.php';
if (defined('WP_CLI') && WP_CLI) {
	require_once AAC_MEMBER_PORTAL_DIR . 'includes/class-aac-member-portal-wp-cli-importer.php';
}

final class AAC_Member_Portal_Null_WP_Fusion_User {
	public function push_user_meta(...$args) {
		return false;
	}

	public function __call($name, $arguments) {
		return null;
	}
}

final class AAC_Member_Portal_Plugin {
	const SHORTCODE = 'aac_member_portal';
	const LOGIN_SHORTCODE = 'aac_member_login';
	const SIGNUP_SHORTCODE = 'aac_member_signup';
	const BRAND_DISCOUNTS_SHORTCODE = 'aac_brand_discounts';
	const BRAND_DISCOUNTS_PAGE_SLUG = 'brand-discounts';
	const SIGNUP_PAGE_SLUG = 'signup';
	const SCRIPT_HANDLE = 'aac-member-portal-app';
	const STYLE_HANDLE = 'aac-member-portal-app';
	const MOUNT_ID = 'aac-member-portal-root';
	const ORDER_BREAKDOWN_OPTION_PREFIX = 'aac_pmpro_order_breakdown_';

	private $is_rendering_managed_fullscreen = false;
	private $add_dependent_checkout_context = null;
	private $checkout_membership_change_context = null;
	private $logged_checkout_error_signature = '';

	public function __construct() {
		// This plugin is juggling three jobs at once:
		// 1. be the backend/API for the React app
		// 2. give staff an admin/settings home
		// 3. keep a mirrored member database around for reporting and review
		// It is a lot, but at least the chaos is organized.
		new AAC_Member_Portal_API();
		new AAC_Member_Portal_Group_Accounts();
		new AAC_Member_Portal_Impersonation();
		new AAC_Member_Portal_Redpoint_API();
		new AAC_Member_Portal_Admin();
		new AAC_Member_Portal_Member_Database();
		new AAC_Member_Portal_Daily_Member_Export();
		new AAC_Member_Portal_Import_Manager();
		AAC_Member_Portal_Error_Log::init();

		add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
		add_shortcode(self::LOGIN_SHORTCODE, [$this, 'render_login_shortcode']);
		add_shortcode(self::SIGNUP_SHORTCODE, [$this, 'render_signup_shortcode']);
		add_shortcode(self::BRAND_DISCOUNTS_SHORTCODE, [$this, 'render_brand_discounts_shortcode']);
		add_action('template_redirect', [$this, 'set_public_join_checkout_context'], 0);
		add_action('plugins_loaded', [$this, 'maybe_repair_pmpro_user_fields_settings'], 5);
		add_action('plugins_loaded', [$this, 'maybe_disable_broken_wp_fusion_pmpro_hooks'], 100);
		add_action('init', [$this, 'maybe_shim_broken_wp_fusion_user_service'], 20);
		add_action('init', [$this, 'maybe_disable_broken_wp_fusion_pmpro_hooks'], 1000);
		add_action('profile_update', [$this, 'maybe_shim_broken_wp_fusion_user_service'], 1, 3);
		add_action('pmpro_after_change_membership_level', [$this, 'maybe_shim_broken_wp_fusion_user_service'], 1, 3);
		add_action('wp_enqueue_scripts', [$this, 'register_assets']);
		add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_portal_for_shortcode'], 15);
		add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_shell_styles'], 15);
		add_action('wp_enqueue_scripts', [$this, 'isolate_embedded_checkout_assets'], PHP_INT_MAX);
		add_action('wp_ajax_aac_validate_pmpro_discount_code', [$this, 'ajax_validate_pmpro_discount_code']);
		add_action('wp_ajax_nopriv_aac_validate_pmpro_discount_code', [$this, 'ajax_validate_pmpro_discount_code']);
		add_action('wp_footer', [$this, 'render_public_join_link_rewriter'], 99);
		add_filter('show_admin_bar', [$this, 'maybe_hide_frontend_admin_bar_for_members']);
		add_action('send_headers', [$this, 'maybe_send_nocache_headers'], 0);
		add_action('template_redirect', [$this, 'maybe_buffer_front_page_join_links'], 20);
		add_action('template_redirect', [$this, 'maybe_redirect_pmpro_account_to_portal_manage'], -8);
		add_action('template_redirect', [$this, 'maybe_render_embedded_checkout_template'], PHP_INT_MAX);
		add_action('template_redirect', [$this, 'maybe_redirect_wpengine_signup_to_native_checkout'], -7);
		add_action('template_redirect', [$this, 'maybe_redirect_non_autorenew_cancel_request'], -6);
		add_action('template_redirect', [$this, 'maybe_render_managed_fullscreen_template'], 0);
		add_action('template_redirect', [$this, 'maybe_capture_cancel_preserve_term'], -5);
		add_action('template_redirect', [$this, 'maybe_redirect_frontend_login_to_portal'], 1);
		add_action('template_redirect', [$this, 'maybe_redirect_pmpro_change_password_to_portal'], 1);
		add_action('init', [$this, 'capture_checkout_membership_change_context'], 0);
			add_action('init', [$this, 'maybe_seed_pmpro_checkout_username'], 1);
			add_action('init', [$this, 'maybe_apply_partner_family_checkout_level_override'], 2);
			add_action('init', [$this, 'maybe_apply_partner_country_checkout_level_override'], 3);
			add_action('init', [$this, 'maybe_apply_membership_discount_code_to_request'], 4);
			add_action('init', [$this, 'maybe_remove_partner_only_discount_from_non_partner_checkout'], 5);
			add_action('init', [$this, 'log_checkout_post_checkpoint'], 6);
			add_action('init', [$this, 'register_pmpro_student_university_field'], 12);
			add_action('init', [$this, 'maybe_normalize_existing_membership_enddates'], 40);
		add_action('shutdown', [$this, 'capture_checkout_shutdown_error'], PHP_INT_MAX - 1);
		add_action('shutdown', [$this, 'capture_relevant_fatal'], PHP_INT_MAX);
		add_filter('the_content', [$this, 'maybe_replace_pmpro_checkout_publication_fields'], 15);
		add_filter('the_content', [$this, 'maybe_replace_pmpro_logged_in_checkout_username'], 16);
		add_filter('the_content', [$this, 'maybe_wrap_managed_pmpro_content'], 20);
		add_filter('gettext', [$this, 'filter_pmpro_cancel_review_language'], 20, 3);
		add_action('admin_init', [$this, 'maybe_restore_pmpro_admin_capabilities']);
		add_filter('user_has_cap', [$this, 'maybe_grant_pmpro_admin_capabilities'], 20, 4);
		add_filter('login_url', [$this, 'filter_login_url_to_portal'], 20, 3);
		add_filter('login_redirect', [$this, 'filter_administrator_login_redirect'], 20, 3);
		add_action('login_enqueue_scripts', [$this, 'render_branded_wp_login_styles']);
		add_filter('login_headerurl', [$this, 'filter_wp_login_logo_url']);
		add_filter('login_headertext', [$this, 'filter_wp_login_logo_text']);
		add_filter('login_body_class', [$this, 'filter_wp_login_body_classes'], 10, 2);
		add_filter('pmpro_required_user_fields', [$this, 'filter_pmpro_required_user_fields']);
		add_filter('pmpro_show_discount_code', [$this, 'show_discount_code_on_checkout']);
			add_filter('pmpro_required_billing_fields', [$this, 'filter_pmpro_required_billing_fields']);
			add_filter('pmpro_registration_checks', [$this, 'validate_pmpro_required_profile_fields'], 20);
			add_filter('pmpro_registration_checks', [$this, 'validate_pmpro_student_university_field'], 21);
			add_filter('pmpro_registration_checks', [$this, 'log_pmpro_registration_failure'], 999);
		add_filter('pmpro_checkout_new_user_array', [$this, 'filter_pmpro_checkout_new_user_array']);
		add_action('pmpro_checkout_before_submit_button', [$this, 'render_pmpro_checkout_nonce'], 1);
		add_action('pmpro_checkout_after_billing_fields', [$this, 'render_pmpro_membership_discounts'], 9);
		add_action('wp_footer', [$this, 'render_checkout_donation_ui_script'], 99);
		add_action('pmpro_checkout_after_user_fields', [$this, 'render_pmpro_checkout_publication_preferences'], 10);
		add_action('pmpro_checkout_after_user_fields', [$this, 'render_pmpro_partner_family_options'], 12);
		// Magazine add-ons are retired from checkout; keep the custom order summary client-side.
		add_filter('pmpro_checkout_level', [$this, 'filter_pmpro_checkout_level_for_magazine_addons'], 20);
		add_filter('pmpro_checkout_start_date', [$this, 'filter_pmpro_checkout_start_date_for_autorenew_reactivation'], 20, 2);
		add_filter('pmpro_level_cost_text', [$this, 'filter_pmpro_level_cost_text_for_autorenew_reactivation'], 20, 4);
		add_action('pmpro_after_checkout', [$this, 'ensure_immediate_upgrade_checkout_level'], 15, 2);
		add_action('pmpro_after_checkout', [$this, 'capture_pmpro_checkout_order_breakdown'], 20, 2);
		add_action('pmpro_after_checkout', [$this, 'clear_scheduled_downgrade_after_checkout'], 30, 2);
		add_action('pmpro_after_checkout', [$this, 'log_pmpro_checkout_success'], 99, 2);
		add_action('pmpro_after_change_membership_level', [$this, 'normalize_membership_enddate_after_change'], 12, 2);
		add_action('pmpro_after_change_membership_level', [$this, 'maybe_restore_cancelled_membership_through_term'], 13, 2);
		add_action('pmpro_after_change_membership_level', [$this, 'clear_scheduled_downgrade_after_membership_change'], 14, 2);
		add_action('pmpro_after_change_membership_level', [$this, 'sync_pmpro_checkout_profile_fields'], 20, 2);
		add_action('pmpro_after_change_membership_level', [$this, 'clear_partner_only_discount_after_level_change'], 25, 2);
		add_action('pmpro_after_change_membership_level', [$this, 'sync_family_child_month_end_dates_after_parent_change'], 35, 2);
		add_action('pmpro_after_change_membership_level', [$this, 'log_pmpro_membership_level_change'], 99, 2);
		add_action('aac_member_portal_family_account_linked', [$this, 'sync_linked_child_month_end_date'], 20, 2);
		add_action('show_user_profile', [$this, 'render_pmpro_member_address_fields']);
		add_action('edit_user_profile', [$this, 'render_pmpro_member_address_fields']);
		add_action('personal_options_update', [$this, 'save_pmpro_member_address_fields']);
		add_action('edit_user_profile_update', [$this, 'save_pmpro_member_address_fields']);
		add_filter('pmpro_confirmation_message', [$this, 'append_pmpro_confirmation_line_items'], 20, 2);
		add_filter('template_include', [$this, 'maybe_use_fullscreen_template'], 99);
		add_action('admin_notices', [$this, 'maybe_render_missing_build_notice']);
		add_action('admin_init', [$this, 'maybe_install_brand_discounts_page']);
		add_action('admin_init', [$this, 'maybe_install_signup_page']);
		add_filter('script_loader_tag', [$this, 'mark_script_as_module'], 10, 3);
	}

	public function filter_wp_login_logo_url() {
		return untrailingslashit($this->get_portal_page_url()) . '/#/login';
	}

	/**
	 * Keep checkout submissions compatible with PMPro 3.x when the active site
	 * checkout template predates the nonce field introduced in template 3.0.
	 * PMPro's current template prints the same field immediately after this hook;
	 * duplicate fields contain the same token and are safe during transition.
	 */
	public function render_pmpro_checkout_nonce() {
		if (is_admin() || !$this->is_pmpro_checkout_request()) {
			return;
		}

		wp_nonce_field('pmpro_checkout_nonce', 'pmpro_checkout_nonce');
	}

	public function filter_pmpro_cancel_review_language($translation, $text, $domain) {
		if (is_admin() || !$this->is_pmpro_cancel_request()) {
			return $translation;
		}

		$replacements = [
			'Are you sure you want to cancel your %s membership?' => 'Turn off automatic renewal for your %s membership?',
			'Your subscription will be cancelled. You will not be billed again. Your membership will remain active until %s.' => 'Turning off automatic renewal stops future billing. Your membership will remain active through %s, its current expiration date.',
			'Your subscription will be cancelled.' => '',
			'What made you cancel? Please share your reason below and click the button to confirm cancellation.' => '',
			'What made you cancel?' => 'Why are you turning off automatic renewal?',
			'Cancel Membership' => 'Turn Off Automatic Renewal',
			'Yes, cancel this membership' => 'Turn Off Automatic Renewal',
			'Yes, cancel my membership' => 'Turn Off Automatic Renewal',
		];

		return $replacements[$text] ?? $translation;
	}

	public function filter_wp_login_logo_text() {
		return __('American Alpine Club Member Access', 'aac-member-portal');
	}

	public function filter_wp_login_body_classes($classes, $action) {
		$classes = is_array($classes) ? $classes : [];
		$classes[] = 'aac-branded-wp-login';
		$classes[] = 'aac-branded-wp-login--' . sanitize_html_class((string) $action);

		return array_values(array_unique($classes));
	}

	public function render_branded_wp_login_styles() {
		$settings = AAC_Member_Portal_Settings_Schema::get_settings(AAC_Member_Portal_Admin::OPTION_KEY);
		$design = isset($settings['design']) && is_array($settings['design']) ? $settings['design'] : [];
		$background_url = !empty($design['login_background_image_url'])
			? esc_url_raw((string) $design['login_background_image_url'])
			: AAC_MEMBER_PORTAL_URL . 'app/assets/join-hero-static-image.jpg';
		?>
		<style id="aac-branded-wp-login-css">
			@import url('https://use.typekit.net/veb7xhf.css');

			:root {
				--aac-login-red: #8f1515;
				--aac-login-red-dark: #6f1010;
				--aac-login-gold: #f8c235;
				--aac-login-cream: #f7f1e8;
			}

			body.aac-branded-wp-login {
				min-height: 100vh;
				display: flex;
				flex-direction: column;
				justify-content: center;
				background-color: #030000;
				background-image:
					linear-gradient(90deg, rgba(3, 0, 0, 0.9) 0%, rgba(3, 0, 0, 0.72) 43%, rgba(3, 0, 0, 0.52) 100%),
					url('<?php echo esc_url($background_url); ?>');
				background-position: center;
				background-repeat: no-repeat;
				background-size: cover;
				color: #fff;
				font-family: futura-pt, Futura, "Futura PT", "Century Gothic", "Trebuchet MS", "Gill Sans", ui-sans-serif, sans-serif;
				letter-spacing: .02em;
			}

			body.aac-branded-wp-login::before {
				content: "";
				position: fixed;
				inset: 0;
				z-index: 0;
				pointer-events: none;
				background: linear-gradient(180deg, rgba(3, 0, 0, 0.08), rgba(3, 0, 0, 0.5));
			}

			body.aac-branded-wp-login #login {
				box-sizing: border-box;
				position: relative;
				z-index: 2;
				width: min(440px, calc(100% - 32px));
				margin: auto;
				padding: 32px 0;
			}

			body.aac-branded-wp-login #login h1 {
				display: none;
			}

			body.aac-branded-wp-login #loginform,
			body.aac-branded-wp-login #lostpasswordform,
			body.aac-branded-wp-login #resetpassform,
			body.aac-branded-wp-login #registerform {
				box-sizing: border-box;
				margin-top: 0;
				padding: 30px;
				border: 1px solid rgba(255, 255, 255, .18);
				border-radius: 0;
				background: rgba(0, 0, 0, .62);
				box-shadow: 0 32px 80px rgba(0, 0, 0, .52);
				backdrop-filter: blur(14px);
			}

			body.aac-branded-wp-login #loginform::before,
			body.aac-branded-wp-login #lostpasswordform::before,
			body.aac-branded-wp-login #resetpassform::before,
			body.aac-branded-wp-login #registerform::before {
				display: block;
				margin-bottom: 22px;
				color: var(--aac-login-gold);
				font-size: 11px;
				font-weight: 700;
				letter-spacing: .24em;
				text-transform: uppercase;
			}

			body.aac-branded-wp-login #loginform::before { content: "Member sign in"; }
			body.aac-branded-wp-login #lostpasswordform::before { content: "Reset password"; }
			body.aac-branded-wp-login #resetpassform::before { content: "Create a new password"; }
			body.aac-branded-wp-login #registerform::before { content: "Member registration"; }

			body.aac-branded-wp-login label,
			body.aac-branded-wp-login .forgetmenot label {
				color: #fff;
				font-size: 14px;
				font-weight: 600;
			}

			body.aac-branded-wp-login input[type="text"],
			body.aac-branded-wp-login input[type="email"],
			body.aac-branded-wp-login input[type="password"] {
				box-sizing: border-box;
				min-height: 48px;
				margin: 7px 0 18px;
				padding: 10px 13px;
				border: 1px solid rgba(255, 255, 255, .28);
				border-radius: 0;
				background: #fff;
				box-shadow: none;
				color: #111;
				font-size: 16px;
			}

			body.aac-branded-wp-login input:focus {
				border-color: var(--aac-login-gold);
				box-shadow: 0 0 0 2px rgba(248, 194, 53, .28);
				outline: none;
			}

			body.aac-branded-wp-login .wp-pwd .button.wp-hide-pw {
				top: 7px;
				height: 48px;
				border-radius: 0;
				color: var(--aac-login-red);
			}

			body.aac-branded-wp-login .button-primary,
			body.aac-branded-wp-login .wp-core-ui .button-primary {
				min-height: 48px;
				padding: 0 24px;
				border: 1px solid var(--aac-login-red);
				border-radius: 0;
				background: var(--aac-login-red);
				box-shadow: none;
				color: #fff;
				font-size: 13px;
				font-weight: 700;
				letter-spacing: .1em;
				text-shadow: none;
				text-transform: uppercase;
			}

			body.aac-branded-wp-login .button-primary:hover,
			body.aac-branded-wp-login .button-primary:focus {
				border-color: var(--aac-login-red-dark);
				background: var(--aac-login-red-dark);
			}

			body.aac-branded-wp-login #nav,
			body.aac-branded-wp-login #backtoblog,
			body.aac-branded-wp-login .privacy-policy-page-link {
				margin: 18px 0 0;
				padding: 0;
				color: rgba(255, 255, 255, .74);
				text-align: left;
			}

			body.aac-branded-wp-login #nav a,
			body.aac-branded-wp-login #backtoblog a,
			body.aac-branded-wp-login .privacy-policy-page-link a {
				color: var(--aac-login-gold);
				font-weight: 600;
			}

			body.aac-branded-wp-login #nav a:hover,
			body.aac-branded-wp-login #backtoblog a:hover,
			body.aac-branded-wp-login .privacy-policy-page-link a:hover {
				color: #ffd86a;
			}

			body.aac-branded-wp-login .message,
			body.aac-branded-wp-login #login_error,
			body.aac-branded-wp-login .success {
				box-sizing: border-box;
				margin: 0 0 18px;
				border: 0;
				border-left: 4px solid var(--aac-login-gold);
				background: rgba(0, 0, 0, .72);
				box-shadow: none;
				color: #fff;
			}

			body.aac-branded-wp-login #login_error {
				border-left-color: #ef4444;
			}

			body.aac-branded-wp-login .message a,
			body.aac-branded-wp-login #login_error a {
				color: var(--aac-login-gold);
			}

			body.aac-branded-wp-login .language-switcher {
				position: relative;
				z-index: 2;
			}

			@media (max-width: 900px) {
				body.aac-branded-wp-login #login {
					margin: 0 auto;
					padding-top: 42px;
				}
			}

			@media (max-width: 480px) {
				body.aac-branded-wp-login #login {
					width: calc(100% - 24px);
					padding-top: 24px;
				}

				body.aac-branded-wp-login #loginform,
				body.aac-branded-wp-login #lostpasswordform,
				body.aac-branded-wp-login #resetpassform,
				body.aac-branded-wp-login #registerform {
					padding: 24px 20px;
				}
			}
		</style>
		<?php
	}

	public function ajax_validate_pmpro_discount_code() {
		check_ajax_referer('aac_validate_pmpro_discount_code', 'nonce');

		$code = isset($_POST['code']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['code']))) : '';
		$level_id = isset($_POST['level_id']) ? absint($_POST['level_id']) : 0;

		if ($code === '' || $level_id <= 0) {
			wp_send_json_error([
				'message' => __('Invalid discount code.', 'aac-member-portal'),
			]);
		}

		$validation = $this->validate_pmpro_discount_code_for_level($code, $level_id);
		if (is_wp_error($validation)) {
			wp_send_json_error([
				'message' => $validation->get_error_message(),
			]);
		}

		wp_send_json_success($validation);
	}

	private function validate_pmpro_discount_code_for_level($code, $level_id) {
		global $wpdb;

		$code = strtoupper(sanitize_text_field((string) $code));
		$level_id = absint($level_id);
		if ($code === '' || $level_id <= 0) {
			return new WP_Error('aac_discount_code_invalid', __('Invalid discount code.', 'aac-member-portal'));
		}

		$codes_table = $wpdb->prefix . 'pmpro_discount_codes';
		$levels_table = $wpdb->prefix . 'pmpro_discount_codes_levels';
		$codes_table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $codes_table));
		$levels_table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $levels_table));
		if (!$codes_table_exists || !$levels_table_exists) {
			return new WP_Error('aac_discount_code_unavailable', __('Discount code validation is unavailable.', 'aac-member-portal'));
		}

		$discount_code = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$codes_table} WHERE UPPER(code) = %s LIMIT 1", $code),
			ARRAY_A
		);
		if (!$discount_code || empty($discount_code['id'])) {
			return new WP_Error('aac_discount_code_not_found', __('Invalid discount code.', 'aac-member-portal'));
		}

		$now = current_time('timestamp');
		$starts = $this->parse_pmpro_discount_code_time($discount_code['starts'] ?? '');
		if ($starts && $starts > $now) {
			return new WP_Error('aac_discount_code_not_started', __('Invalid discount code.', 'aac-member-portal'));
		}

		$expires = $this->parse_pmpro_discount_code_time($discount_code['expires'] ?? '');
		if ($expires && $expires < $now) {
			return new WP_Error('aac_discount_code_expired', __('Invalid discount code.', 'aac-member-portal'));
		}

		$max_uses = isset($discount_code['max_uses']) ? (int) $discount_code['max_uses'] : 0;
		$uses = isset($discount_code['uses']) ? (int) $discount_code['uses'] : 0;
		if ($max_uses > 0 && $uses >= $max_uses) {
			return new WP_Error('aac_discount_code_used', __('Invalid discount code.', 'aac-member-portal'));
		}

		$discount_level = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$levels_table} WHERE code_id = %d AND level_id = %d LIMIT 1",
				(int) $discount_code['id'],
				$level_id
			),
			ARRAY_A
		);
		if (!$discount_level) {
			return new WP_Error('aac_discount_code_not_for_level', __('This discount code does not apply to the selected membership level.', 'aac-member-portal'));
		}

		$initial_payment = isset($discount_level['initial_payment']) && is_numeric($discount_level['initial_payment'])
			? round(max(0, (float) $discount_level['initial_payment']), 2)
			: null;
		$billing_amount = isset($discount_level['billing_amount']) && is_numeric($discount_level['billing_amount'])
			? round(max(0, (float) $discount_level['billing_amount']), 2)
			: null;

		return [
			'code' => $code,
			'level_id' => $level_id,
			'initial_payment' => $initial_payment,
			'billing_amount' => $billing_amount,
			'label' => sprintf(__('Promo code (%s)', 'aac-member-portal'), $code),
		];
	}

	private function parse_pmpro_discount_code_time($value) {
		$value = trim((string) $value);
		if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
			return null;
		}

		$timestamp = strtotime($value);
		return $timestamp ? $timestamp : null;
	}

	public function maybe_hide_frontend_admin_bar_for_members($show) {
		if (is_admin()) {
			return $show;
		}

		if (is_user_logged_in() && !current_user_can('manage_options')) {
			return false;
		}

		return $show;
	}

	/**
	 * Render the public Join iframe as a new-member checkout even when a staff
	 * member is signed into the surrounding WordPress site. Running this at
	 * template_redirect avoids altering WordPress authentication bootstrap while
	 * still preceding PMPro shortcode rendering.
	 */
	public function set_public_join_checkout_context() {
		$is_join_embed = isset($_REQUEST['aac_embed'], $_REQUEST['aac_signup'])
			&& sanitize_text_field(wp_unslash($_REQUEST['aac_embed'])) === '1'
			&& sanitize_text_field(wp_unslash($_REQUEST['aac_signup'])) === '1';

		if (!$is_join_embed) {
			return;
		}

		$request_path = !empty($_SERVER['REQUEST_URI'])
			? untrailingslashit((string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH))
			: '';

		if (strpos($request_path, '/membership-checkout') !== 0) {
			return;
		}

		wp_set_current_user(0);
	}

	public function show_discount_code_on_checkout($show) {
		return $this->is_pmpro_checkout_request() ? true : $show;
	}

	public function register_assets() {
		$asset_files = $this->locate_asset_files();
		if (!$asset_files['script']) {
			return;
		}

		wp_register_script(
			self::SCRIPT_HANDLE,
			$asset_files['script'],
			[],
			AAC_MEMBER_PORTAL_VERSION,
			true
		);
		wp_script_add_data(self::SCRIPT_HANDLE, 'type', 'module');

		if ($asset_files['style']) {
			wp_register_style(
				self::STYLE_HANDLE,
				$asset_files['style'],
				[],
				AAC_MEMBER_PORTAL_VERSION
			);
		}
	}

	/**
	 * Enqueue early when the main post content contains the shortcode so scripts are registered
	 * before aggressive optimizers reorder output (avoids module running before inline config).
	 */
	public function maybe_enqueue_portal_for_shortcode() {
		$post = $this->get_shortcode_post();
		if (!$post) {
			return;
		}

		// WP Engine uses PMPro's native checkout directly on /signup. Loading the
		// React signup bundle here would restore the retired iframe implementation.
		if ($this->should_render_native_signup($post)) {
			return;
		}

		$this->enqueue_portal_assets_and_config();
	}

	public function maybe_enqueue_shell_styles() {
		if (!$this->get_pmpro_shell_post() && !$this->get_public_shell_post()) {
			return;
		}

		$asset_files = $this->locate_asset_files();
		if ($asset_files['style']) {
			wp_enqueue_style(self::STYLE_HANDLE);
		}
	}

	/**
	 * Keep the React join and member-profile screens independent from the host
	 * theme and unrelated frontend plugins. The fullscreen portal supplies its
	 * own complete stylesheet, so the site's global CSS only introduces cascade
	 * collisions on this page.
	 */
	public function isolate_member_profile_styles() {
		if (is_admin() || !is_singular()) {
			return;
		}

		$post = get_queried_object();
		if (!$post instanceof WP_Post || (string) $post->post_name !== 'member-profile') {
			return;
		}

		global $wp_styles;
		if (!$wp_styles instanceof WP_Styles) {
			return;
		}

		$allowed_handles = [
			self::STYLE_HANDLE,
			'admin-bar',
			'dashicons',
		];

		foreach ((array) $wp_styles->queue as $handle) {
			if (!in_array($handle, $allowed_handles, true)) {
				wp_dequeue_style($handle);
			}
		}
	}

	/**
	 * Keep the embedded PMPro checkout independent from the host theme and
	 * unrelated event plugins. Those assets are not part of the checkout and
	 * can continuously change its measured height inside the parent iframe.
	 */
	public function isolate_embedded_checkout_assets() {
		if (
			is_admin()
			|| !isset($_GET['aac_embed'])
			|| sanitize_text_field(wp_unslash($_GET['aac_embed'])) !== '1'
		) {
			return;
		}

		$request_path = !empty($_SERVER['REQUEST_URI'])
			? untrailingslashit((string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH))
			: '';
		if (strpos($request_path, '/membership-checkout') !== 0 && $request_path !== '/membership-confirmation') {
			return;
		}

		global $wp_styles, $wp_scripts;
		$blocked_prefixes = [
			'tribe-',
			'tec-',
			'event-tickets',
			'app/',
			'fontawesome/',
			'flex-accordion/',
			'bootstrap/',
			'menu/',
			'footer/',
			'pmpro_frontend_variation_',
		];

		if ($wp_styles instanceof WP_Styles) {
			foreach ((array) $wp_styles->queue as $handle) {
				foreach ($blocked_prefixes as $prefix) {
					if (strpos((string) $handle, $prefix) === 0) {
						wp_dequeue_style($handle);
						break;
					}
				}
			}
		}

		if ($wp_scripts instanceof WP_Scripts) {
			foreach ((array) $wp_scripts->queue as $handle) {
				foreach ($blocked_prefixes as $prefix) {
					if (strpos((string) $handle, $prefix) === 0) {
						wp_dequeue_script($handle);
						break;
					}
				}
			}
		}
	}

	public function render_public_join_link_rewriter() {
		if (is_admin()) {
			return;
		}

		$join_url = untrailingslashit($this->get_portal_page_url()) . '/#/join';
		$account_logged_out_url = home_url('/login/');
		$account_logged_in_url = untrailingslashit($this->get_portal_page_url()) . '/#/profile';
		$is_logged_in = is_user_logged_in();
		?>
		<script>
			(function () {
				const joinUrl = <?php echo wp_json_encode($join_url); ?>;
				const accountLoggedOutUrl = <?php echo wp_json_encode($account_logged_out_url); ?>;
				const accountLoggedInUrl = <?php echo wp_json_encode($account_logged_in_url); ?>;
				const serverLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
				const joinLabels = new Set(['join', 'sign up', 'join the club', 'join the club.', 'become a member']);
				const accountLabels = new Set(['account', 'sign in', 'login', 'log in']);

				function normalizeLabel(anchor) {
					return (anchor.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
				}

				function isLoggedIn() {
					return serverLoggedIn || document.body.classList.contains('logged-in');
				}

				function getUtilityDestination(label, imageAlt) {
					if (joinLabels.has(label) || imageAlt.includes('join')) {
						return joinUrl;
					}

					if (accountLabels.has(label) || imageAlt.includes('account')) {
						return isLoggedIn() ? accountLoggedInUrl : accountLoggedOutUrl;
					}

					return '';
				}

				function relinkUtilityAnchors() {
					document.querySelectorAll([
						'a[href="#"]',
						'a[href="#join"]',
						'a[href="#account"]',
						'.utility-nav a',
						'.hero-with-video__buttons a'
					].join(', ')).forEach((anchor) => {
						const label = normalizeLabel(anchor);
						const imageAlt = Array.from(anchor.querySelectorAll('img'))
							.map((image) => image.getAttribute('alt') || '')
							.join(' ')
							.toLowerCase();
						const destination = getUtilityDestination(label, imageAlt);

						if (!destination) {
							return;
						}

						anchor.href = destination;
						anchor.dataset.aacUtilityRelinked = '1';
						anchor.dataset.aacUtilityDestination = destination;

						if (!anchor.dataset.aacUtilityClickBound) {
							anchor.dataset.aacUtilityClickBound = '1';
							anchor.addEventListener('click', function (event) {
								event.preventDefault();
								window.location.assign(anchor.dataset.aacUtilityDestination || anchor.href);
							}, true);
						}
					});
				}

				relinkUtilityAnchors();
				if (document.readyState === 'loading') {
					document.addEventListener('DOMContentLoaded', relinkUtilityAnchors);
				}
				window.addEventListener('load', relinkUtilityAnchors);
			})();
		</script>
		<?php
	}

	public function maybe_buffer_front_page_join_links() {
		if (
			is_admin() ||
			wp_doing_ajax() ||
			(defined('REST_REQUEST') && REST_REQUEST) ||
			!is_front_page()
		) {
			return;
		}

		ob_start([$this, 'rewrite_front_page_join_links']);
	}

	public function rewrite_front_page_join_links($html) {
		$join_url = esc_url(home_url('/signup/'));
		$account_url = esc_url(is_user_logged_in() ? untrailingslashit($this->get_portal_page_url()) . '/#/profile' : home_url('/login/'));

		return preg_replace_callback(
			'#<a\b([^>]*?)href=(["\'])(?:\#|\#join|\#account)\2([^>]*)>(.*?)</a>#is',
			static function ($matches) use ($join_url, $account_url) {
				$label = strtolower(trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($matches[4]))));
				$image_alt_text = '';
				if (preg_match_all('/<img\b[^>]*\balt=(["\'])(.*?)\1/is', $matches[4], $image_matches)) {
					$image_alt_text = strtolower(implode(' ', $image_matches[2]));
				}

				$join_labels = ['join', 'sign up', 'join the club', 'join the club.', 'become a member'];
				$account_labels = ['account', 'sign in', 'login', 'log in'];
				$target_url = '';

				if (in_array($label, $join_labels, true) || strpos($image_alt_text, 'join') !== false) {
					$target_url = $join_url;
				} elseif (in_array($label, $account_labels, true) || strpos($image_alt_text, 'account') !== false) {
					$target_url = $account_url;
				}

				if (!$target_url) {
					return $matches[0];
				}

				return '<a' . $matches[1] . 'href="' . $target_url . '"' . $matches[3] . '>' . $matches[4] . '</a>';
			},
			$html
		);
	}

	public function maybe_send_nocache_headers() {
		if (!$this->get_shortcode_post() && !$this->get_pmpro_shell_post() && !$this->get_public_shell_post()) {
			return;
		}

		nocache_headers();
		header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		header('Pragma: no-cache');
		header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
	}

	public function maybe_use_fullscreen_template($template) {
		$post = $this->get_fullscreen_shortcode_post();
		if (!$post) {
			$post = $this->get_pmpro_shell_post();
		}
		if (!$post) {
			$post = $this->get_public_shell_post();
		}

		if (!$post) {
			return $template;
		}

		if ((string) $post->post_name === 'member-profile') {
			$theme_wrapped_template = AAC_MEMBER_PORTAL_DIR . 'templates/theme-wrapped-portal.php';
			return file_exists($theme_wrapped_template) ? $theme_wrapped_template : $template;
		}

		$use_fullscreen_template = apply_filters(
			'aac_member_portal_use_fullscreen_template',
			true,
			$post
		);

		if (!$use_fullscreen_template) {
			return $template;
		}

		$portal_template = AAC_MEMBER_PORTAL_DIR . 'templates/fullscreen-portal.php';
		if (file_exists($portal_template)) {
			return $portal_template;
		}

		return $template;
	}

	public function maybe_render_managed_fullscreen_template() {
		$post = $this->get_pmpro_shell_post();
		if (!$post && is_singular()) {
			$current_post = get_post();
			if ($current_post instanceof WP_Post && (string) $current_post->post_name === 'member-profile') {
				return;
			}
			if ($current_post instanceof WP_Post && in_array((string) $current_post->post_name, ['member-profile', 'membership'], true)) {
				$post = $current_post;
			}
		}
		if (!$post) {
			return;
		}

		$portal_template = AAC_MEMBER_PORTAL_DIR . 'templates/fullscreen-portal.php';
		if (!file_exists($portal_template)) {
			return;
		}

		$this->is_rendering_managed_fullscreen = true;
		status_header(200);
		include $portal_template;
		exit;
	}

	/**
	 * Embedded checkout must never inherit the host theme header or footer. Run
	 * before the broader managed-shell detection so an iframe request cannot
	 * fall back to the site's normal page template when PMPro page assignments
	 * differ from the expected URL.
	 */
	public function maybe_render_embedded_checkout_template() {
		if (is_admin() || !isset($_GET['aac_embed']) || sanitize_text_field(wp_unslash($_GET['aac_embed'])) !== '1') {
			return;
		}

		$request_path = !empty($_SERVER['REQUEST_URI'])
			? untrailingslashit((string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH))
			: '';
		if (strpos($request_path, '/membership-checkout') !== 0 && $request_path !== '/membership-confirmation') {
			return;
		}

		$portal_template = AAC_MEMBER_PORTAL_DIR . 'templates/fullscreen-portal.php';
		if (!file_exists($portal_template)) {
			return;
		}

		$this->is_rendering_managed_fullscreen = true;
		status_header(200);
		include $portal_template;
		exit;
	}

	public function maybe_wrap_managed_pmpro_content($content) {
		if (is_admin() || !in_the_loop() || !is_main_query()) {
			return $content;
		}

		if ($this->is_rendering_managed_fullscreen) {
			return $content;
		}

		$post = $this->get_pmpro_shell_post();
		if (!$post) {
			return $content;
		}

		$shell_template = AAC_MEMBER_PORTAL_DIR . 'templates/managed-shell-content.php';
		if (!file_exists($shell_template)) {
			return $content;
		}

		$portal_url = untrailingslashit($this->get_portal_page_url()) . '/';
		$account_url = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url') ? pmpro_url('account') : home_url('/membership-account/');
		$billing_url = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url') ? pmpro_url('billing') : home_url('/membership-account/membership-billing/');
		$orders_url = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url') ? pmpro_url('invoice') : home_url('/membership-account/membership-orders/');
		$checkout_url = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url') ? pmpro_url('checkout') : home_url('/membership-checkout/');
		$cancel_url = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url') ? pmpro_url('cancel') : home_url('/membership-account/membership-cancel/');
		$confirmation_url = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url') ? pmpro_url('confirmation') : home_url('/membership-checkout/membership-confirmation/');
		$account_compare_path = untrailingslashit((string) wp_parse_url($account_url, PHP_URL_PATH));
		if ($account_compare_path && untrailingslashit((string) wp_parse_url($billing_url, PHP_URL_PATH)) === $account_compare_path) {
			$billing_url = home_url('/membership-account/membership-billing/');
		}
		if ($account_compare_path && untrailingslashit((string) wp_parse_url($orders_url, PHP_URL_PATH)) === $account_compare_path) {
			$orders_url = home_url('/membership-account/membership-orders/');
		}
		if ($account_compare_path && untrailingslashit((string) wp_parse_url($cancel_url, PHP_URL_PATH)) === $account_compare_path) {
			$cancel_url = home_url('/membership-account/membership-cancel/');
		}
		if (untrailingslashit((string) wp_parse_url($cancel_url, PHP_URL_PATH)) === untrailingslashit('/membership-levels')) {
			$cancel_url = home_url('/membership-account/membership-cancel/');
		}
		if ($account_compare_path && untrailingslashit((string) wp_parse_url($confirmation_url, PHP_URL_PATH)) === $account_compare_path) {
			$confirmation_url = home_url('/membership-checkout/membership-confirmation/');
		}
		$account_path = untrailingslashit((string) wp_parse_url($account_url, PHP_URL_PATH));
		$current_url = untrailingslashit(get_permalink($post));
		$request_path = '';
		if (!empty($_SERVER['REQUEST_URI'])) {
			$request_path = untrailingslashit((string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH));
		}
		$billing_path = untrailingslashit((string) wp_parse_url($billing_url, PHP_URL_PATH));
		$orders_path = untrailingslashit((string) wp_parse_url($orders_url, PHP_URL_PATH));
		$checkout_path = untrailingslashit((string) wp_parse_url($checkout_url, PHP_URL_PATH));
		$cancel_path = untrailingslashit((string) wp_parse_url($cancel_url, PHP_URL_PATH));
		$confirmation_path = untrailingslashit((string) wp_parse_url($confirmation_url, PHP_URL_PATH));
		$is_billing_page = $current_url === untrailingslashit($billing_url) || $post->post_name === 'membership-billing' || ($billing_path && $billing_path === $request_path) || in_array($request_path, [untrailingslashit('/membership-account/membership-billing'), untrailingslashit('/membership-billing')], true);
		$is_orders_page = $current_url === untrailingslashit($orders_url) || in_array($post->post_name, ['membership-orders', 'membership-invoice'], true) || ($orders_path && $orders_path === $request_path) || in_array($request_path, [untrailingslashit('/membership-account/membership-orders'), untrailingslashit('/membership-account/membership-invoice'), untrailingslashit('/membership-orders'), untrailingslashit('/membership-invoice')], true);
		$is_checkout_page = $current_url === untrailingslashit($checkout_url) || $post->post_name === 'membership-checkout' || ($checkout_path && $checkout_path === $request_path);
		$is_cancel_page = $current_url === untrailingslashit($cancel_url) || $post->post_name === 'membership-cancel' || ($cancel_path && $cancel_path === $request_path) || in_array($request_path, [untrailingslashit('/membership-account/membership-cancel'), untrailingslashit('/membership-cancel')], true) || isset($_GET['levelstocancel']);
		$is_confirmation_page = $current_url === untrailingslashit($confirmation_url) || $post->post_name === 'membership-confirmation' || ($confirmation_path && $confirmation_path === $request_path) || in_array($request_path, [untrailingslashit('/membership-checkout/membership-confirmation'), untrailingslashit('/membership-confirmation')], true);
		$is_account_page = ($current_url === untrailingslashit($account_url) || $post->post_name === 'membership-account' || ($account_path && $account_path === $request_path)) && !$is_billing_page && !$is_orders_page && !$is_cancel_page && !$is_checkout_page && !$is_confirmation_page;
		$is_account_section = $is_account_page || $is_billing_page || $is_orders_page;
		$page_title = $is_account_section
			? 'Membership Account'
			: ($is_cancel_page
				? 'Membership Cancellation'
				: ($is_confirmation_page ? 'Membership Confirmation' : 'Membership Checkout'));
		$page_kicker = $is_account_section
			? 'Account Overview'
			: ($is_cancel_page
				? 'Membership Options'
				: ($is_confirmation_page ? 'Confirmation' : 'Secure Checkout'));
		$page_description = $is_account_section
			? 'Review your current membership, billing summary, renewal timing, and recent account activity in the same AAC portal shell.'
			: ($is_cancel_page
				? 'Review cancellation options for any membership level without leaving the AAC portal shell.'
				: ($is_confirmation_page
					? 'Review your completed membership order in the same AAC portal shell with quick access back to your profile and account.'
					: 'Complete membership checkout in the same AAC portal shell with quick access back to your profile and account.'));

		ob_start();
		include $shell_template;
		return ob_get_clean();
	}

	public function maybe_replace_pmpro_checkout_publication_fields($content) {
		if (is_admin() || !in_the_loop() || !is_main_query()) {
			return $content;
		}

		if (!$this->is_pmpro_checkout_request() || trim((string) $content) === '') {
			return $content;
		}

		if (
			strpos($content, 'aaj_preference_div') === false &&
			strpos($content, 'anac_preference_div') === false &&
			strpos($content, 'american_climbing_journal_preference_div') === false &&
			strpos($content, 'guidebook_preferences_div') === false
		) {
			return $content;
		}

		$level = $this->get_level_at_checkout();
		$level_id = isset($level->id) ? (int) $level->id : 0;
		if ($level_id <= 2 || !class_exists('DOMDocument')) {
			return $content;
		}

		$previous_use_internal_errors = libxml_use_internal_errors(true);
		$dom = new DOMDocument('1.0', 'UTF-8');
		$wrapped = '<div id="aac-pmpro-content-root">' . $content . '</div>';
		$loaded = $dom->loadHTML(mb_convert_encoding($wrapped, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		if (!$loaded) {
			libxml_clear_errors();
			libxml_use_internal_errors($previous_use_internal_errors);
			return $content;
		}

		$xpath = new DOMXPath($dom);
		$fieldset_nodes = $xpath->query("//*[@id='pmpro_form_fieldset-member-preferences' or @id='pmpro_form_fieldset-more-information']");
		$fieldset = ($fieldset_nodes instanceof DOMNodeList && $fieldset_nodes->length > 0) ? $fieldset_nodes->item(0) : null;
		if (!$fieldset instanceof DOMElement) {
			libxml_clear_errors();
			libxml_use_internal_errors($previous_use_internal_errors);
			return $content;
		}

		$existing_server_cards = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' aac-server-member-preferences ')]", $fieldset);
		if ($existing_server_cards instanceof DOMNodeList && $existing_server_cards->length > 0) {
			libxml_clear_errors();
			libxml_use_internal_errors($previous_use_internal_errors);
			return $content;
		}

		$fields_nodes = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' pmpro_form_fields ')]", $fieldset);
		$fields_container = ($fields_nodes instanceof DOMNodeList && $fields_nodes->length > 0) ? $fields_nodes->item(0) : null;
		if (!$fields_container instanceof DOMElement) {
			libxml_clear_errors();
			libxml_use_internal_errors($previous_use_internal_errors);
			return $content;
		}

		$publication_definitions = [
			[
				'id' => 'aaj_preference_div',
				'name' => 'aaj_preference',
				'eyebrow' => 'Annual',
				'title' => 'American Alpine Journal',
				'description' => 'Annual climbing journal. Choose print delivery or digital-only access.',
				'image_key' => 'aaj',
				'theme_class' => 'aac-member-preferences__card--journal',
			],
			[
				'id' => 'anac_preference_div',
				'name' => 'anac_preference',
				'eyebrow' => 'Annual',
				'title' => 'Accidents in North American Climbing',
				'description' => 'Annual accident review. Choose print delivery or digital-only access.',
				'image_key' => 'anac',
				'theme_class' => 'aac-member-preferences__card--accidents',
			],
			[
				'id' => 'american_climbing_journal_preference_div',
				'name' => 'american_climbing_journal_preference',
				'eyebrow' => 'Journal',
				'title' => 'American Climbing Journal',
				'description' => 'Member stories and club updates. Choose print delivery or digital-only access.',
				'image_key' => 'acj',
				'theme_class' => 'aac-member-preferences__card--journal',
			],
			[
				'id' => 'guidebook_preferences_div',
				'name' => 'guidebook_preferences',
				'eyebrow' => 'Quarterly',
				'title' => 'Guidebook to Membership',
				'description' => 'Quarterly member publication. Choose print delivery or digital-only access.',
				'image_key' => 'guidebook',
				'theme_class' => 'aac-member-preferences__card--guidebook',
			],
		];

		$publication_images = $this->get_template_design_settings()['publication_tile_images'] ?? [];
		$fallback_images = [
			'aaj' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-95.jpeg',
			'anac' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-28.jpeg',
			'acj' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-4.jpg',
			'guidebook' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-2.jpg',
		];

		$cards = [];
		foreach ($publication_definitions as $definition) {
			$field_nodes = $xpath->query(".//*[@id='" . $definition['id'] . "']", $fieldset);
			$field_node = ($field_nodes instanceof DOMNodeList && $field_nodes->length > 0) ? $field_nodes->item(0) : null;
			if (!$field_node instanceof DOMElement) {
				continue;
			}

			$select_nodes = $xpath->query(".//select[@name='" . $definition['name'] . "']", $field_node);
			$select_node = ($select_nodes instanceof DOMNodeList && $select_nodes->length > 0) ? $select_nodes->item(0) : null;
			if (!$select_node instanceof DOMElement) {
				continue;
			}

			$request_value = isset($_REQUEST[$definition['name']]) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				? sanitize_text_field(wp_unslash($_REQUEST[$definition['name']])) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				: '';
			$stored_value = get_current_user_id() ? (string) get_user_meta(get_current_user_id(), $definition['name'], true) : '';
			$explicit_value = $request_value !== '' ? $request_value : $stored_value;
			$selected_value = $explicit_value === 'Print' ? 'Print' : 'Digital';

			$image_url = trim((string) ($publication_images[$definition['image_key']] ?? ''));
			if ($image_url === '') {
				$image_url = $fallback_images[$definition['image_key']] ?? '';
			}

			$cards[] = [
				'name' => $definition['name'],
				'eyebrow' => $definition['eyebrow'],
				'title' => $definition['title'],
				'description' => $definition['description'],
				'image_url' => $image_url,
				'theme_class' => $definition['theme_class'],
				'selected_value' => $selected_value,
			];

			$field_node->parentNode->removeChild($field_node);
		}

		if (empty($cards)) {
			libxml_clear_errors();
			libxml_use_internal_errors($previous_use_internal_errors);
			return $content;
		}

		$fragment = $dom->createDocumentFragment();
		$fragment->appendXML($this->build_pmpro_publication_preferences_markup($cards));
		$fields_container->appendChild($fragment);

		$root_nodes = $xpath->query("//*[@id='aac-pmpro-content-root']");
		$root = ($root_nodes instanceof DOMNodeList && $root_nodes->length > 0) ? $root_nodes->item(0) : null;
		$result = $root instanceof DOMElement ? $this->get_dom_inner_html($root) : $content;

		libxml_clear_errors();
		libxml_use_internal_errors($previous_use_internal_errors);

		return $result;
	}

	public function maybe_replace_pmpro_logged_in_checkout_username($content) {
		if (is_admin() || !in_the_loop() || !is_main_query()) {
			return $content;
		}

		if (!$this->is_pmpro_checkout_request() || !is_user_logged_in() || trim((string) $content) === '') {
			return $content;
		}

		$user = wp_get_current_user();
		if (!$user instanceof WP_User || !$user->exists() || !is_email($user->user_email)) {
			return $content;
		}

		$email_markup = '<strong>' . esc_html($user->user_email) . '</strong>';
		$updated = preg_replace(
			'/(\bYou are logged in as\s*)<strong>.*?<\/strong>(\.\s*If you would like to use a different account for this membership\b)/is',
			'$1' . $email_markup . '$2',
			(string) $content
		);

		return is_string($updated) && $updated !== '' ? $updated : $content;
	}

	public function render_pmpro_checkout_publication_preferences() {
		if (is_admin() || !$this->is_pmpro_checkout_request()) {
			return;
		}

		$level = $this->get_level_at_checkout();
		$level_id = isset($level->id) ? (int) $level->id : 0;
		if ($level_id <= 2) {
			return;
		}

		$cards = $this->get_pmpro_checkout_publication_preference_cards();
		if (empty($cards)) {
			return;
		}

		echo $this->build_pmpro_publication_preferences_markup($cards); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private function get_pmpro_checkout_publication_preference_cards() {
		$publication_definitions = [
			[
				'name' => 'aaj_preference',
				'eyebrow' => 'Annual',
				'title' => 'American Alpine Journal',
				'description' => 'Annual climbing journal. Choose print delivery or digital-only access.',
				'image_key' => 'aaj',
				'theme_class' => 'aac-member-preferences__card--journal',
			],
			[
				'name' => 'anac_preference',
				'eyebrow' => 'Annual',
				'title' => 'Accidents in North American Climbing',
				'description' => 'Annual accident review. Choose print delivery or digital-only access.',
				'image_key' => 'anac',
				'theme_class' => 'aac-member-preferences__card--accidents',
			],
			[
				'name' => 'american_climbing_journal_preference',
				'eyebrow' => 'Journal',
				'title' => 'American Climbing Journal',
				'description' => 'Member stories and club updates. Choose print delivery or digital-only access.',
				'image_key' => 'acj',
				'theme_class' => 'aac-member-preferences__card--journal',
			],
			[
				'name' => 'guidebook_preferences',
				'eyebrow' => 'Quarterly',
				'title' => 'Guidebook to Membership',
				'description' => 'Quarterly member publication. Choose print delivery or digital-only access.',
				'image_key' => 'guidebook',
				'theme_class' => 'aac-member-preferences__card--guidebook',
			],
		];

		$publication_images = $this->get_template_design_settings()['publication_tile_images'] ?? [];
		$fallback_images = [
			'aaj' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-95.jpeg',
			'anac' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/08/image-asset-28.jpeg',
			'acj' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-4.jpg',
			'guidebook' => 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Filler-2.jpg',
		];

		$current_user_id = get_current_user_id();
		$cards = [];
		foreach ($publication_definitions as $definition) {
			$request_value = '';
			if (isset($_REQUEST[$definition['name']])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$request_value = sanitize_text_field(wp_unslash($_REQUEST[$definition['name']])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			$stored_value = $current_user_id ? (string) get_user_meta($current_user_id, $definition['name'], true) : '';
			$selected_value = $request_value !== '' ? $request_value : $stored_value;
			$selected_value = $selected_value === 'Print' ? 'Print' : 'Digital';

			$image_url = trim((string) ($publication_images[$definition['image_key']] ?? ''));
			if ($image_url === '') {
				$image_url = $fallback_images[$definition['image_key']] ?? '';
			}

			$cards[] = [
				'name' => $definition['name'],
				'eyebrow' => $definition['eyebrow'],
				'title' => $definition['title'],
				'description' => $definition['description'],
				'image_url' => $image_url,
				'theme_class' => $definition['theme_class'],
				'selected_value' => $selected_value,
			];
		}

		return $cards;
	}

	private function build_pmpro_publication_preferences_markup($cards) {
		$markup = '<div class="aac-server-member-preferences">';
		$markup .= '<p class="aac-member-preferences__intro">' . esc_html__('Select a publication to receive a print copy. Publications you do not select for print will be delivered digitally and can be accessed through your member profile.', 'aac-member-portal') . '</p>';
		$markup .= '<div class="aac-member-preferences__grid">';

		foreach ($cards as $card) {
			$markup .= '<article class="aac-member-preferences__card ' . esc_attr($card['theme_class']) . '">';
			$markup .= '<div class="aac-member-preferences__art">';
			if (!empty($card['image_url'])) {
				$markup .= '<img src="' . esc_url($card['image_url']) . '" alt="' . esc_attr($card['title'] . ' cover') . '" class="aac-member-preferences__cover-image" />';
			}
			$markup .= '</div>';
			$markup .= '<div class="aac-member-preferences__content">';
			$markup .= '<div class="aac-member-preferences__title-block">';
			$markup .= '<span class="aac-member-preferences__eyebrow">' . esc_html($card['eyebrow']) . '</span>';
			$markup .= '<h3 class="aac-member-preferences__title">' . esc_html($card['title']) . '</h3>';
			$markup .= '</div>';
			$markup .= '<p class="aac-member-preferences__description">' . esc_html($card['description']) . '</p>';
			$markup .= '<div class="aac-member-preferences__choices">';

			foreach (['Print', 'Digital'] as $option) {
				$markup .= '<label class="aac-member-preferences__option">';
				$markup .= '<input class="aac-member-preferences__input" type="radio" name="' . esc_attr($card['name']) . '" value="' . esc_attr($option) . '" ' . checked($card['selected_value'], $option, false) . ' required />';
				$markup .= '<span class="aac-member-preferences__choice">' . esc_html($option) . '</span>';
				$markup .= '</label>';
			}

			$markup .= '</div></div></article>';
		}

		$markup .= '</div></div>';
		return $markup;
	}

	private function get_dom_inner_html(DOMNode $node) {
		$html = '';
		foreach ($node->childNodes as $child_node) {
			$html .= $node->ownerDocument->saveHTML($child_node);
		}
		return $html;
	}

	public function normalize_pmpro_checkout_publication_markup($content) {
		if (!is_string($content) || $content === '') {
			return $content;
		}

		if (
			strpos($content, 'aac-server-member-preferences') === false ||
			strpos($content, 'pmpro_form_fieldset-publication-preferences') === false ||
			!class_exists('DOMDocument')
		) {
			return $content;
		}

		$previous_use_internal_errors = libxml_use_internal_errors(true);
		$dom = new DOMDocument('1.0', 'UTF-8');
		$wrapped = '<div id="aac-publication-normalize-root">' . $content . '</div>';
		$loaded = $dom->loadHTML(mb_convert_encoding($wrapped, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		if (!$loaded) {
			libxml_clear_errors();
			libxml_use_internal_errors($previous_use_internal_errors);
			return $content;
		}

		$xpath = new DOMXPath($dom);
		$server_block_nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' aac-server-member-preferences ')]");
		$publication_fieldset_nodes = $xpath->query("//*[@id='pmpro_form_fieldset-publication-preferences']");
		$server_block = ($server_block_nodes instanceof DOMNodeList && $server_block_nodes->length > 0) ? $server_block_nodes->item(0) : null;
		$publication_fieldset = ($publication_fieldset_nodes instanceof DOMNodeList && $publication_fieldset_nodes->length > 0) ? $publication_fieldset_nodes->item(0) : null;

		if (!$server_block instanceof DOMElement || !$publication_fieldset instanceof DOMElement) {
			libxml_clear_errors();
			libxml_use_internal_errors($previous_use_internal_errors);
			return $content;
		}

		$publication_fields_nodes = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' pmpro_form_fields ')]", $publication_fieldset);
		$publication_fields = ($publication_fields_nodes instanceof DOMNodeList && $publication_fields_nodes->length > 0) ? $publication_fields_nodes->item(0) : null;
		if (!$publication_fields instanceof DOMElement) {
			libxml_clear_errors();
			libxml_use_internal_errors($previous_use_internal_errors);
			return $content;
		}

		if ($server_block->parentNode) {
			$server_block->parentNode->removeChild($server_block);
		}
		if ($publication_fields->firstChild) {
			$publication_fields->insertBefore($server_block, $publication_fields->firstChild);
		} else {
			$publication_fields->appendChild($server_block);
		}

		$root_nodes = $xpath->query("//*[@id='aac-publication-normalize-root']");
		$root = ($root_nodes instanceof DOMNodeList && $root_nodes->length > 0) ? $root_nodes->item(0) : null;
		$result = $root instanceof DOMElement ? $this->get_dom_inner_html($root) : $content;

		libxml_clear_errors();
		libxml_use_internal_errors($previous_use_internal_errors);

		return $result;
	}

	public function maybe_redirect_frontend_login_to_portal() {
		if (is_admin() || is_user_logged_in()) {
			return;
		}

		if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
			return;
		}

		if (!$this->is_frontend_login_request()) {
			return;
		}

		$queried = get_queried_object();
		if ($queried instanceof WP_Post && has_shortcode($queried->post_content, self::LOGIN_SHORTCODE)) {
			return;
		}

		$redirect_to = '';
		$raw_redirect_to = '';
		if (isset($_GET['redirect_to'])) {
			$raw_redirect_to = trim((string) wp_unslash($_GET['redirect_to']));
			$redirect_to = wp_validate_redirect($raw_redirect_to, '');
		}

		$admin_redirect = $redirect_to;
		if (!$admin_redirect && $raw_redirect_to && $this->is_wp_admin_url($raw_redirect_to)) {
			$admin_redirect = $raw_redirect_to;
		}

		if ($this->is_wp_admin_auth_request($admin_redirect) || $this->should_preserve_wp_login_url($this->get_current_request_url(), $admin_redirect)) {
			wp_safe_redirect($this->build_wp_login_url_from_current_request($admin_redirect));
			exit;
		}

		wp_safe_redirect($this->build_portal_login_url($redirect_to));
		exit;
	}

	public function maybe_redirect_pmpro_change_password_to_portal() {
		if (is_admin() || !$this->is_pmpro_change_password_request()) {
			return;
		}

		$target = $this->build_portal_app_url('change-password');
		if (!is_user_logged_in()) {
			$target = $this->build_portal_login_url($target);
		}

		wp_safe_redirect($target);
		exit;
	}

	public function filter_login_url_to_portal($login_url, $redirect, $force_reauth) {
		if (
			is_admin() ||
			wp_doing_ajax() ||
			$force_reauth ||
			$this->is_wp_admin_auth_request($redirect) ||
			$this->should_preserve_wp_login_url($login_url, $redirect)
		) {
			return $login_url;
		}

		if (!$this->should_use_portal_login($redirect)) {
			return $login_url;
		}

		return $this->build_portal_login_url($redirect);
	}

	public function filter_administrator_login_redirect($redirect_to, $requested_redirect_to, $user) {
		if (!$user instanceof WP_User || !$user->has_cap('edit_posts')) {
			return $redirect_to;
		}

		if ($requested_redirect_to && !$this->is_wp_admin_url($requested_redirect_to)) {
			return $redirect_to;
		}

		return admin_url();
	}

		public function filter_pmpro_required_user_fields($required_fields) {
			if (!is_array($required_fields)) {
				return $required_fields;
			}

		if (!is_user_logged_in()) {
			// The managed signup intentionally shows one email and one password field.
			// Populate PMPro's hidden confirmation values at the last possible point
			// before its required-field and equality checks run.
			$email = $this->get_checkout_request_value(['bemail', 'user_email', 'email']);
			$password = isset($_REQUEST['password']) ? (string) wp_unslash($_REQUEST['password']) : '';
			if ($email !== '') {
				$required_fields['bemail'] = $email;
				$required_fields['bconfirmemail'] = $email;
				$required_fields['username'] = $this->generate_unique_username_from_email($email);
			}
			if ($password !== '') {
				$required_fields['password'] = $password;
				$required_fields['password2'] = $password;
			}

			$required_profile_fields = [
				'bfirstname' => ['first_name', 'pmpro_sfirstname', 'bfirstname'],
				'blastname' => ['last_name', 'pmpro_slastname', 'blastname'],
				'bemail' => ['bemail', 'user_email', 'email'],
			];
			foreach ($required_profile_fields as $field_name => $request_keys) {
				$required_fields[$field_name] = $this->get_checkout_request_value($request_keys);
			}
		}

		$selected_discount = $this->get_checkout_request_value(['aac_membership_discount']);
		foreach ($required_fields as $field_name => $field_value) {
			$normalized_field_name = strtolower((string) $field_name);
			$is_always_optional_checkout_field = preg_match(
				'/(birthdate|t[_-]?shirt|publication|preference|guidebook|bphone|^phone$)/',
				$normalized_field_name
			);
			$is_inactive_student_field = $selected_discount !== 'student'
				&& preg_match('/(graduation|university|school)/', $normalized_field_name);
			$is_inactive_military_field = $selected_discount !== 'military'
				&& preg_match('/(service[_-]?(component|branch)|military)/', $normalized_field_name);

			if ($is_always_optional_checkout_field || $is_inactive_student_field || $is_inactive_military_field) {
				unset($required_fields[$field_name]);
				continue;
			}

			if ($field_value === 'bphone' || $field_value === 'phone') {
				unset($required_fields[$field_name]);
			}
		}

			return $required_fields;
		}

		public function register_pmpro_student_university_field() {
			// Discount-detail fields are managed in PMPro User Fields on the site.
			// The checkout shell creates temporary fallback inputs only when a field is absent.
			return;
		}

	public function filter_pmpro_required_billing_fields($required_fields) {
		if (!is_array($required_fields)) {
			return $required_fields;
		}

		foreach ($required_fields as $field_name => $field_value) {
			if ($field_name === 'bphone' || $field_name === 'phone' || $field_value === 'bphone' || $field_value === 'phone') {
				unset($required_fields[$field_name]);
			}
		}

		if (!is_user_logged_in()) {
			$required_billing_fields = [
				'baddress1' => ['pmpro_saddress1', 'saddress1', 'baddress1'],
				'bcity' => ['pmpro_scity', 'scity', 'bcity'],
				'bstate' => ['pmpro_sstate', 'sstate', 'bstate'],
				'bzipcode' => ['pmpro_szipcode', 'szipcode', 'bzipcode'],
				'bcountry' => ['pmpro_scountry', 'scountry', 'bcountry'],
				'bemail' => ['bemail', 'user_email', 'email'],
				'bconfirmemail' => ['bemail', 'user_email', 'email'],
			];
			foreach ($required_billing_fields as $field_name => $request_keys) {
				$required_fields[$field_name] = $this->get_checkout_request_value($request_keys);
			}
		}

		if ($this->is_stripe_checkout_request()) {
			foreach (['AccountNumber', 'ExpirationMonth', 'ExpirationYear', 'CVV'] as $stripe_elements_field) {
				unset($required_fields[$stripe_elements_field]);
			}
		}

		return $required_fields;
	}

		public function validate_pmpro_required_profile_fields($okay) {
			if (!$okay || !$this->is_pmpro_checkout_request()) {
				return $okay;
			}

		if (is_user_logged_in() || $this->is_autorenew_reactivation_checkout_request()) {
			return $okay;
		}

		$user = wp_get_current_user();
		$account_info = $this->get_checkout_account_info_from_request($user instanceof WP_User && $user->exists() ? $user : null);
		$required_fields = [
			'first_name' => __('First name', 'aac-member-portal'),
			'last_name' => __('Last name', 'aac-member-portal'),
			'email' => __('Email', 'aac-member-portal'),
			'street' => __('Street address', 'aac-member-portal'),
			'city' => __('City', 'aac-member-portal'),
			'state' => __('State / Province', 'aac-member-portal'),
			'zip' => __('ZIP / Postal code', 'aac-member-portal'),
			'country' => __('Country', 'aac-member-portal'),
		];

		foreach ($required_fields as $field_key => $field_label) {
			if (trim((string) ($account_info[$field_key] ?? '')) !== '') {
				continue;
			}

			global $pmpro_msg, $pmpro_msgt;
			$pmpro_msg = sprintf(__('%s is required.', 'aac-member-portal'), $field_label);
			$pmpro_msgt = 'pmpro_error';
			return false;
		}

		if (!is_email($account_info['email'] ?? '')) {
			global $pmpro_msg, $pmpro_msgt;
			$pmpro_msg = __('A valid email address is required.', 'aac-member-portal');
			$pmpro_msgt = 'pmpro_error';
			return false;
		}

			return $okay;
		}

		public function validate_pmpro_student_university_field($okay) {
			if (!$okay || !$this->is_pmpro_checkout_request()) {
				return $okay;
			}

			if (!$this->should_require_student_university_for_checkout()) {
				return $okay;
			}

			if ($this->get_requested_student_university() !== '') {
				return $okay;
			}

			global $pmpro_msg, $pmpro_msgt;
			$pmpro_msg = __('Please select your university or choose Other / not listed for the student discount.', 'aac-member-portal');
			$pmpro_msgt = 'pmpro_error';
			return false;
		}

	public function filter_pmpro_checkout_new_user_array($user_data) {
		if (!is_array($user_data)) {
			return $user_data;
		}

		$email = '';
		if (isset($_REQUEST['bemail'])) {
			$email = sanitize_email(wp_unslash($_REQUEST['bemail']));
		} elseif (!empty($user_data['user_email'])) {
			$email = sanitize_email($user_data['user_email']);
		}

		if ($email) {
			$user_data['user_email'] = $email;
			$username = $this->generate_unique_username_from_email($email);
			$user_data['user_login'] = $username;
			$_REQUEST['username'] = $username;
			$_POST['username'] = $username;
		}

		if (isset($_REQUEST['password'])) {
			$password = (string) wp_unslash($_REQUEST['password']);
			if ($password !== '') {
				$user_data['user_pass'] = $password;
			}
		}

		$first_name = $this->get_checkout_request_value(['first_name', 'pmpro_sfirstname', 'bfirstname']);
		$last_name = $this->get_checkout_request_value(['last_name', 'pmpro_slastname', 'blastname']);
		$display_name = trim($first_name . ' ' . $last_name);

		if ($display_name !== '') {
			$user_data['display_name'] = $display_name;
			$user_data['first_name'] = $first_name;
			$user_data['last_name'] = $last_name;
		} elseif ($email && empty($user_data['display_name'])) {
			$user_data['display_name'] = $email;
		}

		return $user_data;
	}

	public function maybe_seed_pmpro_checkout_username() {
		if (is_admin()) {
			return;
		}

		$request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : '';
		if ($request_method !== 'POST' || !$this->is_pmpro_checkout_request()) {
			return;
		}

		if (is_user_logged_in()) {
			return;
		}

		$email = isset($_REQUEST['bemail']) ? sanitize_email(wp_unslash($_REQUEST['bemail'])) : '';
		if ($email !== '' && empty($_REQUEST['bconfirmemail'])) {
			$_REQUEST['bconfirmemail'] = $email;
			$_POST['bconfirmemail'] = $email;
		}

		if (!empty($_REQUEST['password']) && empty($_REQUEST['password2'])) {
			$password = (string) wp_unslash($_REQUEST['password']);
			$_REQUEST['password2'] = $password;
			$_POST['password2'] = $password;
		}

		if ($email === '') {
			return;
		}

		$username = $this->generate_unique_username_from_email($email);
		$_REQUEST['username'] = $username;
		$_POST['username'] = $username;
	}

	public function maybe_apply_partner_family_checkout_level_override() {
		if (is_admin()) {
			return;
		}

		$request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : '';
		if ($request_method !== 'POST' || !$this->is_pmpro_checkout_request()) {
			return;
		}

		if (!$this->has_partner_family_request()) {
			return;
		}

		$requested_level_id = $this->get_requested_level_id();
		$partner_family_level_id = $this->get_partner_family_level_id();
		if (!$partner_family_level_id || $requested_level_id !== $partner_family_level_id) {
			return;
		}

		$target_level_id = $this->get_partner_level_id();

		if (!$target_level_id) {
			return;
		}

		$_REQUEST['level'] = $target_level_id;
		$_GET['level'] = $target_level_id;
		$_POST['level'] = $target_level_id;
	}

	public function maybe_apply_partner_country_checkout_level_override() {
		if (is_admin()) {
			return;
		}

		$request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : '';
		if ($request_method !== 'POST' || !$this->is_pmpro_checkout_request()) {
			return;
		}

		$requested_country = $this->get_checkout_request_value(['pmpro_scountry', 'scountry', 'bcountry']);
		if ($requested_country === '') {
			return;
		}

		$requested_level_id = $this->get_requested_level_id();
		if (!$this->is_partner_country_routed_level($requested_level_id)) {
			return;
		}

		$target_level_id = $this->get_partner_country_level_id($requested_country);
		if ($target_level_id <= 0 || $target_level_id === $requested_level_id) {
			return;
		}

		$_REQUEST['level'] = $target_level_id;
		$_GET['level'] = $target_level_id;
		$_POST['level'] = $target_level_id;
	}

	public function maybe_apply_membership_discount_code_to_request() {
		if (is_admin() || !$this->is_pmpro_checkout_request()) {
			return;
		}

		if (!$this->has_membership_discount_request()) {
			return;
		}

		$checkout_level = $this->get_level_at_checkout();
		if (!$this->supports_discount_tiers($checkout_level)) {
			return;
		}

		$partner_family_config = $this->has_partner_family_request()
			? $this->get_requested_partner_family_config()
			: $this->normalize_partner_family_config([]);
		if (($partner_family_config['mode'] ?? '') === 'family') {
			return;
		}

		$discount_type = $this->get_requested_membership_discount_type();
		$discount_code = $this->get_membership_discount_code($discount_type);
		if ($discount_code === '') {
			return;
		}

		foreach (['discount_code', 'pmpro_discount_code', 'other_discount_code'] as $request_key) {
			$_REQUEST[$request_key] = $discount_code;
			$_GET[$request_key] = $discount_code;
			$_POST[$request_key] = $discount_code;
		}
	}

	public function maybe_remove_partner_only_discount_from_non_partner_checkout() {
		if (is_admin() || !$this->is_pmpro_checkout_request()) {
			return;
		}

		$checkout_level = $this->get_level_at_checkout();
		if ($this->supports_discount_tiers($checkout_level)) {
			return;
		}

		$partner_only_codes = $this->get_partner_only_membership_discount_codes();
		foreach (['discount_code', 'pmpro_discount_code', 'other_discount_code'] as $request_key) {
			$requested_code = $this->get_uppercase_request_value($request_key);
			if ($requested_code !== '' && in_array($requested_code, $partner_only_codes, true)) {
				unset($_REQUEST[$request_key], $_GET[$request_key], $_POST[$request_key]);
			}
		}

		unset(
			$_REQUEST['aac_membership_discount_present'],
			$_REQUEST['aac_membership_discount'],
			$_GET['aac_membership_discount_present'],
			$_GET['aac_membership_discount'],
			$_POST['aac_membership_discount_present'],
			$_POST['aac_membership_discount']
		);
	}

	public function render_pmpro_membership_discounts() {
		$discount_options = $this->get_membership_discount_catalog();
		$current_user_id = get_current_user_id();
		$family_config = $this->get_effective_partner_family_config($current_user_id);
		if (empty($discount_options) && $family_config['mode'] !== 'family') {
			return;
		}

		$checkout_level = $this->get_level_at_checkout();
		$supports_discount_tiers = $this->supports_discount_tiers($checkout_level);
		$supports_family_plan = $this->supports_family_plan_tiers($checkout_level);
		if (!$supports_discount_tiers && !$supports_family_plan) {
			return;
		}

		$selected_discount = $this->has_membership_discount_request()
			? $this->get_requested_membership_discount_type()
			: '';
		$base_level_total = max(0, $this->get_level_checkout_initial_total($checkout_level));
		?>
		<div
			id="pmpro_form_fieldset-membership-discounts"
			class="pmpro_checkout-fields pmpro_form_fieldset aac-membership-discounts"
			data-aac-membership-base-price="<?php echo esc_attr(number_format($base_level_total, 2, '.', '')); ?>"
		>
			<div class="pmpro_card">
				<div class="pmpro_card_content">
					<legend class="pmpro_form_legend">
						<h2 class="pmpro_form_heading pmpro_font-large"><?php esc_html_e('Membership Discounts', 'aac-member-portal'); ?></h2>
					</legend>
					<div class="pmpro_form_fields">
						<input type="hidden" name="aac_membership_discount_present" value="1" />
						<p class="aac-membership-discounts__intro">
							<?php esc_html_e('Select one discount type if it applies to this membership. Click it again to remove it. Only one discount can be used at a time.', 'aac-member-portal'); ?>
						</p>
						<div class="aac-membership-discounts__picker" role="group" aria-label="<?php esc_attr_e('Membership discount selection', 'aac-member-portal'); ?>">
							<div class="aac-membership-discounts__grid">
								<?php foreach ($discount_options as $slug => $discount) : ?>
									<div class="pmpro_form_field pmpro_form_field-checkbox aac-membership-discounts__field">
										<label class="pmpro_form_label pmpro_form_label-inline aac-membership-discounts__label" for="<?php echo esc_attr('aac_membership_discount_' . $slug); ?>">
											<input
												id="<?php echo esc_attr('aac_membership_discount_' . $slug); ?>"
												class="aac-membership-discounts__input"
												type="checkbox"
												name="aac_membership_discount"
												value="<?php echo esc_attr($slug); ?>"
												data-aac-membership-discount-rate="<?php echo esc_attr(number_format((float) $discount['rate'], 2, '.', '')); ?>"
												data-aac-membership-discount-label="<?php echo esc_attr($discount['label']); ?>"
												data-aac-membership-discount-code="<?php echo esc_attr($discount['code']); ?>"
												data-aac-toggleable-choice="true"
												<?php checked($selected_discount, $slug); ?>
											/>
											<span class="aac-membership-discounts__card">
												<span class="aac-membership-discounts__icon" aria-hidden="true">
													<?php echo $discount['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
												</span>
													<span class="aac-membership-discounts__body">
														<span class="aac-membership-discounts__copy">
															<strong><?php echo esc_html($discount['label']); ?></strong>
															<span><?php echo esc_html($discount['description']); ?></span>
														</span>
														<span class="aac-membership-discounts__footer">
														<span class="aac-membership-discounts__price"><?php echo esc_html($discount['badge']); ?></span>
													</span>
												</span>
											</span>
										</label>
									</div>
								<?php endforeach; ?>
								<?php if ($supports_family_plan) : ?>
									<div class="pmpro_form_field pmpro_form_field-checkbox aac-membership-discounts__field">
										<label class="pmpro_form_label pmpro_form_label-inline aac-membership-discounts__label" for="aac_partner_family_shortcut">
											<input
												id="aac_partner_family_shortcut"
												class="aac-membership-discounts__input"
												type="checkbox"
												value="family"
												data-aac-family-shortcut="true"
												<?php checked($family_config['mode'], 'family'); ?>
											/>
											<span class="aac-membership-discounts__card">
												<span class="aac-membership-discounts__icon" aria-hidden="true">
													<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
												</span>
													<span class="aac-membership-discounts__body">
														<span class="aac-membership-discounts__copy">
															<strong><?php esc_html_e('Family Discount', 'aac-member-portal'); ?></strong>
															<span><?php esc_html_e('Add adult and dependents', 'aac-member-portal'); ?></span>
														</span>
														<span class="aac-membership-discounts__footer">
														<span class="aac-membership-discounts__price"><?php esc_html_e('Family plan pricing', 'aac-member-portal'); ?></span>
													</span>
												</span>
											</span>
										</label>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_checkout_donation_ui_script() {
		if (!$this->is_pmpro_checkout_request()) {
			return;
		}
		$checkout_level = $this->get_level_at_checkout();
		$summary_base_price = $checkout_level ? max(0, $this->get_level_checkout_initial_total($checkout_level)) : 0;
		$summary_level_name = $checkout_level && !empty($checkout_level->name) ? (string) $checkout_level->name : __('Membership', 'aac-member-portal');
		?>
		<style id="aac-checkout-order-summary-styles">
			#pmpro_pricing_fields .aac-checkout-summary-rows { margin-top: 18px; border-top: 1px solid #dedbd5; }
			#pmpro_pricing_fields .aac-checkout-summary-row { display: flex; align-items: baseline; justify-content: space-between; gap: 24px; padding: 13px 0; border-bottom: 1px solid #dedbd5; }
			#pmpro_pricing_fields .aac-checkout-summary-row span { color: #282522; }
			#pmpro_pricing_fields .aac-checkout-summary-row strong { color: #111; white-space: nowrap; }
			#pmpro_pricing_fields .aac-checkout-summary-row--discount strong { color: #a7191f; }
			#pmpro_pricing_fields .aac-checkout-summary-row--total { padding-top: 18px; border-bottom: 0; font-size: 1.15rem; }
			#pmpro_pricing_fields .aac-checkout-summary-intro { margin: 0; color: #6f6962; }
			.aac-promo-code-section { margin: 0 0 24px; padding: 24px; border: 1px solid #dedbd5; background: #fff; }
			.aac-promo-code-section .pmpro_card_actions { margin: 0; padding: 0; border: 0; }
			.aac-promo-code-section #other_discount_code_fields { margin: 0; }
			.aac-promo-code-section .pmpro_form_fields-inline { display: flex; align-items: stretch; gap: 14px; }
			.aac-promo-code-section .pmpro_form_fields-inline input[type="text"] { flex: 1 1 auto; min-width: 0; }
			.aac-promo-code-section .pmpro_form_fields-inline input[type="button"] { flex: 0 0 auto; }
			[data-aac-phone-shirt-row] { display: grid !important; grid-column: 1 / -1 !important; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) !important; gap: 24px !important; width: 100% !important; }
			[data-aac-phone-shirt-row] > .pmpro_form_field { box-sizing: border-box; min-width: 0 !important; width: 100% !important; max-width: none !important; }
			[data-aac-phone-shirt-row] input,
			[data-aac-phone-shirt-row] select { box-sizing: border-box; width: 100% !important; max-width: none !important; }
			.aac-simple-checkout-wizard { width: 100%; }
			.aac-simple-checkout-wizard__steps { display: none !important; }
			.aac-simple-checkout-wizard__step { display: flex; align-items: center; gap: 9px; min-height: 54px; padding: 10px 12px; border: 1px solid #d9d4cd; background: #fff; color: #615b55; font-size: 13px; font-weight: 700; line-height: 1.25; text-align: left; }
			.aac-simple-checkout-wizard__step[aria-current="step"] { border-color: #a7191f; background: #fff6f5; color: #81161a; }
			.aac-simple-checkout-wizard__step-mark { display: inline-flex; flex: 0 0 28px; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: #ede9e4; }
			.aac-simple-checkout-wizard__step[aria-current="step"] .aac-simple-checkout-wizard__step-mark { background: #a7191f; color: #fff; }
			.aac-simple-checkout-wizard__panel[hidden] { display: none !important; }
			.aac-simple-checkout-wizard__nav { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 24px; }
			.aac-simple-checkout-wizard__back,
			.aac-simple-checkout-wizard__continue { min-width: 132px; padding: 14px 22px; border: 1px solid #a7191f; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; }
			.aac-simple-checkout-wizard__back { border: 3px solid #111 !important; background: #fff !important; color: #111 !important; }
			.aac-simple-checkout-wizard__continue { margin-left: auto; border: 3px solid #ffc72c !important; background: #ffc72c !important; color: #111 !important; }
			.aac-simple-checkout-wizard__back[hidden],
			.aac-simple-checkout-wizard__continue[hidden] { display: none !important; }
			.aac-member-preferences__card { border: 1px solid #111 !important; }
			.aac-member-preferences__card.is-print-selected { border: 3px solid #ffc72c !important; }
			.aac-member-preferences__card[data-aac-card-toggle="true"] { cursor: pointer; }
			.aac-member-preferences__card[data-aac-card-toggle="true"]:focus-visible { outline: 3px solid #111; outline-offset: 3px; }
			.aac-member-preferences__card[data-aac-card-toggle="true"] .aac-member-preferences__choices { display: none !important; }
			.aac-publication-card-selection { display: flex; align-items: center; gap: 10px; min-height: 46px; padding: 8px 14px; background: #fff; color: #111; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
			.aac-publication-card-selection__box { display: none; flex: 0 0 24px; align-items: center; justify-content: center; width: 24px; height: 24px; border: 2px solid #111; background: #fff; color: #111; font-size: 18px; line-height: 1; }
			.aac-member-preferences__card.is-print-selected .aac-publication-card-selection__box { display: inline-flex; background: #ffc72c; border-color: #ffc72c; }
			#pmpro_payment_information_fields .pmpro_card_fields,
			#pmpro_payment_information_fields .pmpro_form_fields { border: 0 !important; box-shadow: none !important; }
			@media (max-width: 640px) {
				.aac-promo-code-section .pmpro_form_fields-inline { align-items: stretch; flex-direction: column; gap: 12px; }
				[data-aac-phone-shirt-row] { grid-template-columns: minmax(0, 1fr) !important; gap: 18px !important; }
				.aac-simple-checkout-wizard__nav { align-items: stretch; flex-direction: column-reverse; }
				.aac-simple-checkout-wizard__back,
				.aac-simple-checkout-wizard__continue { width: 100%; }
			}
		</style>
		<script id="aac-checkout-donation-ui">
		(function () {
			const checkoutBasePrice = <?php echo wp_json_encode($summary_base_price); ?>;
			const checkoutLevelName = <?php echo wp_json_encode($summary_level_name); ?>;
			const formatMoney = (amount) => `${amount < 0 ? '-' : ''}$${Math.abs(amount).toFixed(2)}`;
			const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (character) => ({
				'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
			}[character]));
			let simpleCheckoutWizardStep = 0;

			function ensureSignupAccountFields(form) {
				if (!form || !new URLSearchParams(window.location.search).has('aac_signup')) return null;
				const existing = document.getElementById('pmpro_user_fields') || document.getElementById('pmpro_account_loggedin');
				if (existing) return existing;

				const emailInput = form.querySelector('input[name="bemail"]');
				const emailField = emailInput?.closest('.pmpro_form_field');
				const billingFields = document.getElementById('pmpro_billing_address_fields');
				if (!emailInput || !emailField || !billingFields) return null;

				const fieldset = document.createElement('fieldset');
				fieldset.id = 'pmpro_user_fields';
				fieldset.className = 'pmpro_form_fieldset';
				fieldset.setAttribute('aria-labelledby', 'pmpro_user_fields-title');
				fieldset.innerHTML = '<div class="pmpro_card"><h2 id="pmpro_user_fields-title" class="pmpro_card_title pmpro_font-large">Account Information</h2><div class="pmpro_card_content"><div class="pmpro_form_fields"><div class="pmpro_cols-2 aac-managed-two-up" data-aac-account-row></div></div></div></div>';

				const accountRow = fieldset.querySelector('[data-aac-account-row]');
				const passwordField = document.createElement('div');
				passwordField.className = 'pmpro_form_field pmpro_form_field-password pmpro_form_field-required';
				passwordField.innerHTML = '<label for="password" class="pmpro_form_label">Password <span class="pmpro_asterisk" aria-hidden="true">*</span></label><div class="aac-password-input-wrap"><input type="password" name="password" id="password" class="pmpro_form_input pmpro_form_input-password pmpro_form_input-required" autocomplete="new-password" spellcheck="false" required><button type="button" class="pmpro_btn pmpro_btn-plain aac-password-toggle">Show Password</button></div>';
				accountRow.append(emailField, passwordField);

				const passwordInput = passwordField.querySelector('input[name="password"]');
				const passwordToggle = passwordField.querySelector('.aac-password-toggle');
				const confirmEmailInput = form.querySelector('input[name="bconfirmemail"]');
				let confirmPasswordInput = form.querySelector('input[name="password2"]');
				if (!confirmPasswordInput) {
					confirmPasswordInput = document.createElement('input');
					confirmPasswordInput.type = 'hidden';
					confirmPasswordInput.name = 'password2';
					form.appendChild(confirmPasswordInput);
				}

				const syncMirrors = function () {
					if (confirmEmailInput) confirmEmailInput.value = emailInput.value;
					confirmPasswordInput.value = passwordInput.value;
				};
				emailInput.addEventListener('input', syncMirrors);
				passwordInput.addEventListener('input', syncMirrors);
				form.addEventListener('submit', syncMirrors);
				passwordToggle.addEventListener('click', function () {
					const showing = passwordInput.type === 'text';
					passwordInput.type = showing ? 'password' : 'text';
					passwordToggle.textContent = showing ? 'Show Password' : 'Hide Password';
				});

				const confirmEmailField = confirmEmailInput?.closest('.pmpro_form_field');
				if (confirmEmailField) {
					confirmEmailField.hidden = true;
					confirmEmailField.style.display = 'none';
					confirmEmailInput.required = false;
				}

				billingFields.parentNode.insertBefore(fieldset, billingFields);
				syncMirrors();
				return fieldset;
			}

			function bindSignupEmailAvailability(form) {
				if (!form || !new URLSearchParams(window.location.search).has('aac_signup')) return;
				const emailInput = form.querySelector('input[name="bemail"]');
				const emailField = emailInput?.closest('.pmpro_form_field');
				if (!emailInput || !emailField || emailInput.dataset.aacEmailAvailabilityBound === 'true') return;

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
				const runCheck = async () => {
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
						const endpoint = new URL('/wp-json/aac/v1/email-availability', window.location.origin);
						endpoint.searchParams.set('email', email);
						const response = await fetch(endpoint.toString(), {
							credentials: 'same-origin',
							headers: { Accept: 'application/json' },
						});
						if (!response.ok) throw new Error('Email availability request failed.');
						const result = await response.json();
						if (currentRequest !== requestCounter) return;
						if (result?.valid && result?.available) {
							emailInput.setCustomValidity('');
							setStatus('available', result.message || 'Email address is available.');
							return;
						}
						const message = result?.message || 'An account with this email already exists.';
						emailInput.setCustomValidity(message);
						setStatus('unavailable', message);
					} catch (error) {
						if (currentRequest !== requestCounter) return;
						emailInput.setCustomValidity('');
						setStatus('idle', 'Unable to check email availability right now.');
					}
				};
				const scheduleCheck = () => {
					window.clearTimeout(debounceTimer);
					debounceTimer = window.setTimeout(runCheck, 280);
				};

				emailInput.addEventListener('input', scheduleCheck);
				emailInput.addEventListener('change', runCheck);
				emailInput.addEventListener('blur', runCheck);
				emailInput.dataset.aacEmailAvailabilityBound = 'true';
			}

			function enhanceSimpleCheckoutWizard() {
				const form = document.querySelector('form.pmpro_form');
				if (!form || form.dataset.aacSimpleCheckoutWizard === 'true' || form.querySelector('.aac-checkout-wizard')) return;
				const emailInput = form.querySelector('input[name="bemail"]');
				const confirmEmailInput = form.querySelector('input[name="bconfirmemail"]');
				const passwordInput = form.querySelector('input[name="password"]');
				const confirmPasswordInput = form.querySelector('input[name="password2"]');
				const syncHiddenConfirmations = () => {
					if (emailInput && confirmEmailInput) confirmEmailInput.value = emailInput.value;
					if (passwordInput && confirmPasswordInput) confirmPasswordInput.value = passwordInput.value;
				};
				if (form.dataset.aacConfirmationSync !== 'true') {
					emailInput?.addEventListener('input', syncHiddenConfirmations);
					passwordInput?.addEventListener('input', syncHiddenConfirmations);
					form.addEventListener('submit', syncHiddenConfirmations);
					form.dataset.aacConfirmationSync = 'true';
				}
				syncHiddenConfirmations();

				const accountFields = ensureSignupAccountFields(form)
					|| document.getElementById('pmpro_user_fields')
					|| document.getElementById('pmpro_account_loggedin');
				bindSignupEmailAvailability(form);
				const memberFields = document.getElementById('pmpro_billing_address_fields');
				const publicationFields = document.getElementById('pmpro_form_fieldset-publication-preferences');
				const checkoutNodes = [
					document.getElementById('pmpro_form_fieldset-membership-discounts'),
					document.getElementById('pmpro_form_fieldset-discount-fields'),
					document.getElementById('pmpro_form_fieldset-partner-family'),
					document.getElementById('pmpro_form_fieldset-donation'),
					document.querySelector('.aac-promo-code-section'),
					document.getElementById('pmpro_pricing_fields'),
					document.getElementById('pmpro_autorenewal_checkbox'),
					document.getElementById('pmpro_payment_information_fields'),
					form.querySelector('.pmpro_form_submit'),
				].filter((node, index, nodes) => node && form.contains(node) && nodes.indexOf(node) === index);

				const definitions = [
					{ label: 'Account Information', nodes: accountFields ? [accountFields] : [] },
					{ label: 'Member Information', nodes: memberFields ? [memberFields] : [] },
					{ label: 'Publications Preferences', nodes: publicationFields ? [publicationFields] : [] },
					{ label: 'Discounts, promo, and checkout', nodes: checkoutNodes },
				].filter((step) => step.nodes.length);
				if (definitions.length < 2) return;

				const wizard = document.createElement('div');
				wizard.className = 'aac-simple-checkout-wizard';
				const steps = document.createElement('div');
				steps.className = 'aac-simple-checkout-wizard__steps';
				steps.setAttribute('aria-label', 'Checkout steps');
				const panels = document.createElement('div');
				panels.className = 'aac-simple-checkout-wizard__panels';
				const nav = document.createElement('div');
				nav.className = 'aac-simple-checkout-wizard__nav';
				const back = document.createElement('button');
				back.type = 'button';
				back.className = 'aac-simple-checkout-wizard__back';
				back.textContent = 'Back';
				const continueButton = document.createElement('button');
				continueButton.type = 'button';
				continueButton.className = 'aac-simple-checkout-wizard__continue';
				continueButton.textContent = 'Continue';
				nav.append(back, continueButton);
				wizard.append(steps, panels, nav);
				form.insertBefore(wizard, definitions[0].nodes[0]);

				const entries = definitions.map((definition, index) => {
					const panel = document.createElement('section');
					panel.className = 'aac-simple-checkout-wizard__panel';
					panel.setAttribute('aria-label', definition.label);
					definition.nodes.forEach((node) => panel.appendChild(node));
					panels.appendChild(panel);
					const step = document.createElement('button');
					step.type = 'button';
					step.className = 'aac-simple-checkout-wizard__step';
					step.innerHTML = `<span class="aac-simple-checkout-wizard__step-mark">${index + 1}</span><span>${escapeHtml(definition.label)}</span>`;
					steps.appendChild(step);
					return { ...definition, panel, step };
				});

				const syncHeight = () => {
					const visibleBottom = wizard.getBoundingClientRect().bottom + window.scrollY;
					window.parent?.postMessage({
						type: 'aac-pmpro-checkout-height',
						height: Math.ceil(visibleBottom + 24),
						visibleContent: true,
					}, window.location.origin);
				};
				const validateCurrent = () => {
					const controls = Array.from(entries[simpleCheckoutWizardStep].panel.querySelectorAll('input, select, textarea'));
					const invalid = controls.find((control) => !control.disabled && control.type !== 'hidden' && control.offsetParent !== null && !control.checkValidity());
					if (!invalid) return true;
					invalid.reportValidity();
					invalid.focus({ preventScroll: false });
					return false;
				};
				const goToStep = (nextStep) => {
					simpleCheckoutWizardStep = Math.max(0, Math.min(nextStep, entries.length - 1));
					entries.forEach((entry, index) => {
						const active = index === simpleCheckoutWizardStep;
						entry.panel.hidden = !active;
						entry.panel.setAttribute('aria-hidden', active ? 'false' : 'true');
						entry.panel.inert = !active;
						if (active) {
							entry.panel.style.removeProperty('display');
						} else {
							entry.panel.style.setProperty('display', 'none', 'important');
						}
						entry.step.setAttribute('aria-current', active ? 'step' : 'false');
					});
					back.hidden = simpleCheckoutWizardStep === 0;
					continueButton.hidden = simpleCheckoutWizardStep === entries.length - 1;
					window.parent?.postMessage({ type: 'aac-pmpro-checkout-step', stepIndex: simpleCheckoutWizardStep, stepLabel: entries[simpleCheckoutWizardStep].label, stepCount: entries.length }, window.location.origin);
					window.parent?.postMessage({ type: 'aac-pmpro-checkout-scroll', deltaY: -100000 }, window.location.origin);
					[0, 80, 240, 600].forEach((delay) => window.setTimeout(syncHeight, delay));
				};

				entries.forEach((entry, index) => entry.step.addEventListener('click', () => {
					if (index <= simpleCheckoutWizardStep || validateCurrent()) goToStep(index);
				}));
				back.addEventListener('click', () => goToStep(simpleCheckoutWizardStep - 1));
				continueButton.addEventListener('click', () => {
					if (validateCurrent()) goToStep(simpleCheckoutWizardStep + 1);
				});
				form.dataset.aacSimpleCheckoutWizard = 'true';
				if ('ResizeObserver' in window) {
					const wizardResizeObserver = new ResizeObserver(syncHeight);
					wizardResizeObserver.observe(wizard);
				}
				goToStep(0);
			}

			function syncOrderSummary() {
				const summary = document.getElementById('pmpro_pricing_fields');
				const content = summary?.querySelector('.pmpro_card_content');
				if (!summary || !content) return;
				const summaryHeading = summary.querySelector('.pmpro_card_title');
				if (summaryHeading) summaryHeading.textContent = 'Order summary';
				const promoActions = summary.querySelector('.pmpro_card_actions')
					|| document.querySelector('.aac-promo-code-section .pmpro_card_actions');
				if (promoActions && summary.parentNode) {
					let promoSection = document.querySelector('.aac-promo-code-section');
					if (!promoSection) {
						promoSection = document.createElement('section');
						promoSection.className = 'aac-promo-code-section';
						promoSection.setAttribute('aria-label', 'Promo Code');
					}
					promoSection.appendChild(promoActions);
					const donationSection = document.getElementById('pmpro_form_fieldset-donation');
					if (donationSection?.parentNode === summary.parentNode) {
						donationSection.insertAdjacentElement('afterend', promoSection);
						promoSection.insertAdjacentElement('afterend', summary);
					} else {
						summary.parentNode.insertBefore(promoSection, summary);
					}
					const promoPrompt = promoActions.querySelector('#other_discount_code_p');
					const promoFields = promoActions.querySelector('#other_discount_code_fields');
					if (promoPrompt) {
						promoPrompt.hidden = true;
						promoPrompt.style.display = 'none';
					}
					if (promoFields) {
						promoFields.hidden = false;
						promoFields.style.display = '';
					}
				}

				const selectedDiscount = document.querySelector('input[name="aac_membership_discount"]:checked');
				const discountRate = Math.max(0, Math.min(1, Number.parseFloat(selectedDiscount?.dataset.aacMembershipDiscountRate || '0') || 0));
				const discountLabel = selectedDiscount?.dataset.aacMembershipDiscountLabel || 'Membership discount';
				const membershipDiscount = Math.round(checkoutBasePrice * discountRate * 100) / 100;
				const familyMode = document.getElementById('aac_partner_family_mode')?.value === 'family';
				const familyFields = document.getElementById('pmpro_form_fieldset-partner-family');
				const adultPrice = Number.parseFloat(familyFields?.dataset.aacPartnerFamilyAdultPrice || '0') || 0;
				const dependentPrice = Number.parseFloat(familyFields?.dataset.aacPartnerFamilyDependentPrice || '0') || 0;
				const adultAmount = familyMode && document.getElementById('aac_partner_family_additional_adult')?.checked ? adultPrice : 0;
				const dependentCount = familyMode ? Math.max(0, Number.parseInt(document.getElementById('aac_partner_family_dependents')?.value || '0', 10) || 0) : 0;
				const dependentAmount = dependentCount * dependentPrice;
				const donationAmount = Math.max(0, Number.parseFloat(document.getElementById('donation')?.value || '0') || 0);
				const membershipCode = String(selectedDiscount?.dataset.aacMembershipDiscountCode || '').trim().toLowerCase();
				const promoCode = Array.from(document.querySelectorAll('#pmpro_other_discount_code, input[name="discount_code"], input[name="pmpro_discount_code"], input[name="other_discount_code"]'))
					.map((input) => String(input.value || '').trim())
					.find(Boolean) || '';
				const hasSeparatePromo = promoCode !== '' && promoCode.toLowerCase() !== membershipCode;
				const nativeCostText = document.getElementById('pmpro_level_cost')?.innerText || '';
				const nativePriceMatch = nativeCostText.match(/\$\s*([0-9,]+(?:\.\d{1,2})?)/);
				const nativeMembershipPrice = nativePriceMatch ? Number.parseFloat(nativePriceMatch[1].replace(/,/g, '')) : checkoutBasePrice;
				const discountedMembership = Math.max(0, checkoutBasePrice - membershipDiscount);
				const promoDiscount = hasSeparatePromo && Number.isFinite(nativeMembershipPrice)
					? Math.max(0, Math.round((discountedMembership - nativeMembershipPrice) * 100) / 100)
					: 0;
				const rows = [
					{ label: `${checkoutLevelName} membership`, amount: checkoutBasePrice },
					...(membershipDiscount > 0 ? [{ label: discountLabel, amount: -membershipDiscount, discount: true }] : []),
					...(adultAmount > 0 ? [{ label: 'Additional adult', amount: adultAmount }] : []),
					...(dependentAmount > 0 ? [{ label: `${dependentCount} ${dependentCount === 1 ? 'dependent' : 'dependents'}`, amount: dependentAmount }] : []),
					...(donationAmount > 0 ? [{ label: 'Donation', amount: donationAmount }] : []),
					...(hasSeparatePromo ? [{ label: `Promo code (${promoCode})`, amount: -promoDiscount, discount: promoDiscount > 0 }] : []),
				];
				const total = Math.max(0, rows.reduce((sum, row) => sum + row.amount, 0));
				content.innerHTML = `
					<p class="aac-checkout-summary-intro">Review everything included before entering payment details.</p>
					<div class="aac-checkout-summary-rows">
						${rows.map((row) => `<div class="aac-checkout-summary-row${row.discount ? ' aac-checkout-summary-row--discount' : ''}"><span>${escapeHtml(row.label)}</span><strong>${formatMoney(row.amount)}</strong></div>`).join('')}
						<div class="aac-checkout-summary-row aac-checkout-summary-row--total"><span>Total</span><strong>${formatMoney(total)}</strong></div>
					</div>`;
				summary.dataset.aacItemized = 'true';
			}

			function syncDiscountDetailPanels() {
				const discounts = document.getElementById('pmpro_form_fieldset-membership-discounts');
				const discountFields = document.getElementById('pmpro_form_fieldset-discount-fields');
				const familyFields = document.getElementById('pmpro_form_fieldset-partner-family');
				if (!discounts || !discounts.parentNode) return;

				if (discountFields) discounts.insertAdjacentElement('afterend', discountFields);
				if (familyFields) {
					const familyAnchor = discountFields || discounts;
					familyAnchor.insertAdjacentElement('afterend', familyFields);
				}

				const selectedDiscount = document.querySelector('input[name="aac_membership_discount"]:checked')?.value || '';
				const familySelected = Boolean(document.getElementById('aac_partner_family_shortcut')?.checked)
					|| document.getElementById('aac_partner_family_mode')?.value === 'family';
				const showStudent = selectedDiscount === 'student' && !familySelected;
				const showMilitary = selectedDiscount === 'military' && !familySelected;

				if (discountFields) {
					const service = document.querySelector('#pmpro_form_fieldset-discount-fields .pmpro_form_field:has(select[name*="service_component"], select[id*="service_component"])');
					const graduation = document.querySelector('#pmpro_form_fieldset-discount-fields .pmpro_form_field:has(input[name*="graduation_date"], input[id*="graduation_date"])');
					const university = document.querySelector('#pmpro_form_fieldset-discount-fields .pmpro_form_field:has(input[name*="university_or_school"], input[id*="university_or_school"])');
					const serviceSelect = service?.querySelector('select');
					service?.querySelector('.pmpro_form_hint')?.remove();
					if (serviceSelect && serviceSelect.options.length <= 1) {
						['Active', 'Reserve', 'Veteran'].forEach(function (label) {
							const option = document.createElement('option');
							option.value = label;
							option.textContent = label;
							serviceSelect.appendChild(option);
						});
					}
					if (service) service.style.display = showMilitary ? '' : 'none';
					if (graduation) graduation.style.display = showStudent ? '' : 'none';
					if (university) university.style.display = showStudent ? '' : 'none';
					const showDiscountDetails = showStudent || showMilitary;
					discountFields.hidden = !showDiscountDetails;
					discountFields.classList.toggle('aac-embed-conditional-visible', showDiscountDetails);
					discountFields.style.display = showDiscountDetails ? '' : 'none';
				}

				if (familyFields) {
					familyFields.hidden = !familySelected;
					familyFields.classList.toggle('aac-embed-conditional-visible', familySelected);
					familyFields.style.display = familySelected ? '' : 'none';
				}

				const donation = document.getElementById('pmpro_form_fieldset-donation');
				const activePanel = familySelected ? familyFields : ((showStudent || showMilitary) ? discountFields : null);
				if (donation) (activePanel || discounts).insertAdjacentElement('afterend', donation);
				window.setTimeout(syncOrderSummary, 0);
			}

			function syncPublicationCardControls() {
				const mappings = [
					['aaj_preference', 'aaj_preference'],
					['anac_preference', 'anac_preference'],
					['american_climbing_journal_preference', 'american_climbing_journal_preference'],
					['guidebook_preferences', 'guidebook_preferences']
				];

				mappings.forEach(function (mapping) {
					const radioName = mapping[0];
					const fieldPrefix = mapping[1];
					const nativeSelect = Array.from(document.querySelectorAll('select')).find(function (select) {
						return select.name.indexOf(fieldPrefix) === 0 || select.id.indexOf(fieldPrefix) === 0;
					});
					if (!nativeSelect) return;

					const nativeField = nativeSelect.closest('.pmpro_form_field');
					if (nativeField) {
						nativeField.hidden = true;
						nativeField.style.display = 'none';
					}

					const radios = document.querySelectorAll('input[type="radio"][name="' + radioName + '"]');
					const printRadio = Array.from(radios).find(function (radio) { return radio.value === 'Print'; });
					const digitalRadio = Array.from(radios).find(function (radio) { return radio.value === 'Digital'; });
					const card = printRadio?.closest('.aac-member-preferences__card') || digitalRadio?.closest('.aac-member-preferences__card');
					const syncValue = function (radio) {
						if (!radio.checked) return;
						nativeSelect.value = radio.value;
						nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
					};
					const syncCard = function () {
						if (!card || !printRadio || !digitalRadio) return;
						const isPrint = printRadio.checked;
						card.classList.toggle('is-print-selected', isPrint);
						card.setAttribute('aria-checked', isPrint ? 'true' : 'false');
						let selection = card.querySelector('.aac-publication-card-selection');
						if (!selection) {
							selection = document.createElement('div');
							selection.className = 'aac-publication-card-selection';
							card.querySelector('.aac-member-preferences__art')?.insertAdjacentElement('afterend', selection);
						}
						selection.innerHTML = isPrint
							? '<span class="aac-publication-card-selection__box" aria-hidden="true">&#10003;</span><span>Print</span>'
							: '<span>Digital</span>';
					};
					if (card && printRadio && digitalRadio && card.dataset.aacCardToggle !== 'true') {
						card.dataset.aacCardToggle = 'true';
						card.tabIndex = 0;
						card.setAttribute('role', 'checkbox');
						const toggleCard = function () {
							const nextRadio = printRadio.checked ? digitalRadio : printRadio;
							nextRadio.checked = true;
							nextRadio.dispatchEvent(new Event('change', { bubbles: true }));
							syncCard();
						};
						card.addEventListener('click', toggleCard);
						card.addEventListener('keydown', function (event) {
							if (event.key !== 'Enter' && event.key !== ' ') return;
							event.preventDefault();
							toggleCard();
						});
					}
					radios.forEach(function (radio) {
						if (radio.dataset.aacNativePublicationBound !== 'true') {
							radio.addEventListener('change', function () { syncValue(radio); syncCard(); });
							radio.dataset.aacNativePublicationBound = 'true';
						}
						syncValue(radio);
					});
					syncCard();
				});
			}

			function organizeMemberDetails() {
				const personal = document.getElementById('pmpro_form_fieldset-personal-details');
				const memberInformation = document.getElementById('pmpro_billing_address_fields');
				const publicationPreferences = document.getElementById('pmpro_form_fieldset-publication-preferences');
				const birthdate = document.getElementById('birthdatebirthdate_div')
					|| document.getElementById('birthdate_div')
					|| document.querySelector('.pmpro_form_field:has(input[name*="birthdate"])');
				const shirtSelect = document.querySelector('select[name*="t_shirt"], select[id*="t_shirt"]');
				const shirtField = shirtSelect?.closest('.pmpro_form_field');
				const memberFields = document.querySelector('#pmpro_billing_address_fields .pmpro_form_fields');
				const phoneField = document.getElementById('bphone')?.closest('.pmpro_form_field');
				const paymentInformationHeading = document.getElementById('pmpro_payment_information_fields-title');

				paymentInformationHeading?.remove();

				if (birthdate) {
					birthdate.hidden = true;
					birthdate.style.display = 'none';
					birthdate.querySelectorAll('input, select, textarea').forEach(function (control) {
						control.required = false;
						control.removeAttribute('required');
						control.removeAttribute('aria-required');
					});
				}

				if (memberFields && phoneField && shirtField) {
					let row = memberFields.querySelector('[data-aac-phone-shirt-row]');
					if (!row) {
						row = document.createElement('div');
						row.className = 'pmpro_cols-2 aac-managed-two-up';
						row.dataset.aacPhoneShirtRow = 'true';
						memberFields.appendChild(row);
					}
					row.append(phoneField, shirtField);
				}

				if (personal) {
					personal.hidden = true;
					personal.style.display = 'none';
				}

				if (
					memberInformation &&
					publicationPreferences?.parentNode &&
					!memberInformation.closest('.aac-simple-checkout-wizard__panel')
				) {
					publicationPreferences.parentNode.insertBefore(memberInformation, publicationPreferences);
				}
			}

			function enhanceDonation() {
				const discounts = document.getElementById('pmpro_form_fieldset-membership-discounts');
				const donation = document.getElementById('pmpro_form_fieldset-donation');
				const orderSummary = document.getElementById('pmpro_pricing_fields');
				const dropdown = document.getElementById('donation_dropdown');
				const amount = document.getElementById('donation');
				const inline = dropdown && dropdown.closest('.pmpro_form_fields-inline');
				if (!donation || !dropdown || !amount || !inline) return;

				if (orderSummary?.parentNode) {
					const discountFields = document.getElementById('pmpro_form_fieldset-discount-fields');
					const familyFields = document.getElementById('pmpro_form_fieldset-partner-family');
					if (discountFields?.parentNode === orderSummary.parentNode) {
						orderSummary.parentNode.insertBefore(discountFields, orderSummary);
					}
					if (familyFields?.parentNode === orderSummary.parentNode) {
						orderSummary.parentNode.insertBefore(familyFields, orderSummary);
					}
					orderSummary.parentNode.insertBefore(donation, orderSummary);
				} else if (discounts?.parentNode) {
					discounts.insertAdjacentElement('afterend', donation);
				}

				if (inline.querySelector('.aac-donation-picker')) return;
				if (!dropdown.querySelector('option[value="0"]')) {
					const option = document.createElement('option');
					option.value = '0';
					option.textContent = 'No thank you';
					dropdown.insertBefore(option, dropdown.firstChild);
				}
				if (!dropdown.querySelector('option[value="other"]')) {
					const option = document.createElement('option');
					option.value = 'other';
					option.textContent = 'Custom amount';
					dropdown.appendChild(option);
				}

				const picker = document.createElement('div');
				picker.className = 'aac-donation-picker';
				Array.from(dropdown.options).forEach(function (option) {
					const button = document.createElement('button');
					button.type = 'button';
					button.className = 'aac-donation-option';
					button.dataset.aacDonationValue = option.value;
					button.textContent = option.value === '0' ? 'No thanks' : option.textContent.trim();
					button.addEventListener('click', function () {
						dropdown.value = option.value;
						donation.dataset.aacDonationMode = option.value === 'other' ? 'custom' : 'preset';
						if (option.value === 'other') {
							amount.value = '';
							amount.focus();
						} else {
							amount.value = option.value;
						}
						picker.querySelectorAll('button').forEach(function (item) {
							item.dataset.selected = item === button ? 'true' : 'false';
						});
						dropdown.dispatchEvent(new Event('change', { bubbles: true }));
						amount.dispatchEvent(new Event('change', { bubbles: true }));
					});
					picker.appendChild(button);
				});
				inline.insertBefore(picker, inline.firstChild);
				dropdown.value = '0';
				amount.value = '0';
				donation.dataset.aacDonationMode = 'preset';
				picker.querySelector('[data-aac-donation-value="0"]')?.setAttribute('data-selected', 'true');

				if (orderSummary?.parentNode) {
					const discountFields = document.getElementById('pmpro_form_fieldset-discount-fields');
					if (discountFields?.parentNode === orderSummary.parentNode) {
						orderSummary.parentNode.insertBefore(discountFields, orderSummary);
					}
					orderSummary.parentNode.insertBefore(donation, orderSummary);
				}
			}
			document.addEventListener('DOMContentLoaded', syncDiscountDetailPanels);
			document.addEventListener('DOMContentLoaded', enhanceDonation);
			document.addEventListener('DOMContentLoaded', syncPublicationCardControls);
			document.addEventListener('DOMContentLoaded', organizeMemberDetails);
			document.addEventListener('DOMContentLoaded', syncOrderSummary);
			document.addEventListener('DOMContentLoaded', function () { window.setTimeout(enhanceSimpleCheckoutWizard, 100); });
			window.addEventListener('load', syncDiscountDetailPanels);
			window.addEventListener('load', enhanceDonation);
			window.addEventListener('load', syncPublicationCardControls);
			window.addEventListener('load', organizeMemberDetails);
			window.addEventListener('load', syncOrderSummary);
			window.addEventListener('load', function () { window.setTimeout(enhanceSimpleCheckoutWizard, 100); });
			window.setTimeout(syncDiscountDetailPanels, 500);
			window.setTimeout(enhanceDonation, 500);
			window.setTimeout(syncPublicationCardControls, 500);
			window.setTimeout(organizeMemberDetails, 500);
			window.setTimeout(syncOrderSummary, 500);
			window.setTimeout(syncDiscountDetailPanels, 1500);
			window.setTimeout(enhanceDonation, 1500);
			window.setTimeout(syncPublicationCardControls, 1500);
			window.setTimeout(organizeMemberDetails, 1500);
			window.setTimeout(syncOrderSummary, 1500);
			window.setTimeout(enhanceSimpleCheckoutWizard, 1700);

			document.addEventListener('change', function (event) {
				if (event.target.matches('input, select') && event.target.id !== 'pmpro_other_discount_code') {
					window.setTimeout(syncOrderSummary, 0);
				}
				if (event.target.matches('input[name="aac_membership_discount"], #aac_partner_family_shortcut, #aac_partner_family_mode')) {
					window.setTimeout(syncDiscountDetailPanels, 0);
					window.setTimeout(enhanceDonation, 0);
					window.setTimeout(syncOrderSummary, 25);
				}
			});
			document.addEventListener('click', function (event) {
				if (event.target.closest('.aac-membership-discounts__label')) {
					window.setTimeout(syncDiscountDetailPanels, 0);
					window.setTimeout(enhanceDonation, 0);
					window.setTimeout(syncOrderSummary, 25);
				}
				if (event.target.closest('.aac-donation-option, #other_discount_code_button')) {
					window.setTimeout(syncOrderSummary, 100);
					window.setTimeout(syncOrderSummary, 800);
				}
			});
			document.addEventListener('input', function (event) {
				if (event.target.matches('#donation, #aac_partner_family_dependents')) {
					window.setTimeout(syncOrderSummary, 0);
				}
			});
		}());
		</script>
		<?php
	}

	public function render_pmpro_partner_family_options() {
		$checkout_level = $this->get_level_at_checkout();
		if (!$this->is_partner_family_checkout_level($checkout_level)) {
			return;
		}

		$current_user_id = get_current_user_id();
		$family_config = $this->get_effective_partner_family_config($current_user_id);
		$base_level_total = max(0, $this->get_level_checkout_initial_total($checkout_level));
		$pricing = $this->get_partner_family_pricing($base_level_total);
		?>
		<div
			id="pmpro_form_fieldset-partner-family"
			class="pmpro_checkout-fields pmpro_form_fieldset aac-partner-family"
			data-aac-partner-family-base-price="<?php echo esc_attr(number_format($base_level_total, 2, '.', '')); ?>"
			data-aac-partner-family-adult-price="<?php echo esc_attr(number_format((float) $pricing['additional_adult_price'], 2, '.', '')); ?>"
			data-aac-partner-family-dependent-price="<?php echo esc_attr(number_format((float) $pricing['dependent_price'], 2, '.', '')); ?>"
			<?php if ($family_config['mode'] !== 'family') : ?>hidden style="display:none;"<?php endif; ?>
		>
			<div class="pmpro_card">
				<div class="pmpro_card_content">
					<legend class="pmpro_form_legend">
						<h2 class="pmpro_form_heading pmpro_font-large"><?php esc_html_e('Family Membership Options', 'aac-member-portal'); ?></h2>
					</legend>
					<div class="pmpro_form_fields">
						<input type="hidden" name="aac_partner_family_present" value="1" />
						<input type="hidden" id="aac_partner_family_mode" name="aac_partner_family_mode" value="<?php echo esc_attr($family_config['mode']); ?>" />
						<p class="aac-partner-family__intro">
							<?php esc_html_e('Use the Family option above to activate these family plan add-ons for this membership.', 'aac-member-portal'); ?>
						</p>
						<div class="aac-partner-family__details" data-aac-partner-family-details <?php if ($family_config['mode'] !== 'family') : ?>hidden style="display:none;"<?php endif; ?>>
							<label class="aac-partner-family__card" for="aac_partner_family_additional_adult">
								<input
									id="aac_partner_family_additional_adult"
									type="checkbox"
									name="aac_partner_family_additional_adult"
									value="1"
									<?php checked(!empty($family_config['additional_adult'])); ?>
								/>
								<span class="aac-partner-family__card-inner">
									<span class="aac-partner-family__card-copy">
										<strong><?php esc_html_e('Additional adult', 'aac-member-portal'); ?></strong>
										<span><?php esc_html_e('Select one additional adult for $80 per year.', 'aac-member-portal'); ?></span>
									</span>
									<span class="aac-partner-family__card-price">
										<?php echo esc_html($this->format_price($pricing['additional_adult_price'])); ?>
									</span>
								</span>
							</label>
							<div class="aac-partner-family__dependents">
								<label class="pmpro_form_label" for="aac_partner_family_dependents">
									<?php esc_html_e('Dependents', 'aac-member-portal'); ?>
								</label>
								<select
									id="aac_partner_family_dependents"
									name="aac_partner_family_dependents"
									class="pmpro_form_input pmpro_form_input-select"
								>
									<?php for ($dependent_index = 0; $dependent_index <= 3; $dependent_index++) : ?>
										<option value="<?php echo esc_attr((string) $dependent_index); ?>" <?php selected((int) $family_config['dependent_count'], $dependent_index); ?>>
											<?php
											echo esc_html(
												$dependent_index === 1
													? __('1 dependent', 'aac-member-portal')
													: sprintf(__('%d dependents', 'aac-member-portal'), $dependent_index)
											);
											?>
										</option>
									<?php endfor; ?>
								</select>
								<p class="aac-partner-family__dependents-note">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s price */
											__('Each dependent is billed at %s per year.', 'aac-member-portal'),
											$this->format_price($pricing['dependent_price'])
										)
									);
									?>
								</p>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_pmpro_magazine_addons() {
		return;

		$magazine_addons = $this->get_magazine_addon_catalog();
		if (empty($magazine_addons)) {
			return;
		}

		$current_user_id = get_current_user_id();
		$selected_addons = $this->get_effective_magazine_addon_selection($current_user_id);
		$request_selected_addons = $this->get_requested_magazine_addons();
		$selected_addon_total = $this->get_magazine_addon_total(
			$this->has_magazine_addon_request() ? $request_selected_addons : []
		);
		$checkout_level = $this->get_level_at_checkout();
		$base_level_total = max(0, $this->get_level_checkout_initial_total($checkout_level) - $selected_addon_total);
		?>
		<div
			id="pmpro_form_fieldset-magazine-addons"
			class="pmpro_checkout-fields pmpro_form_fieldset aac-magazine-addons"
			data-aac-magazine-base-price="<?php echo esc_attr(number_format($base_level_total, 2, '.', '')); ?>"
		>
			<div class="pmpro_card">
				<div class="pmpro_card_content">
					<legend class="pmpro_form_legend">
						<h2 class="pmpro_form_heading pmpro_font-large"><?php esc_html_e('Magazine Subscriptions', 'aac-member-portal'); ?></h2>
					</legend>
					<div class="pmpro_form_fields">
						<input type="hidden" name="aac_magazine_addons_present" value="1" />
						<p class="aac-magazine-addons__intro">
							<?php esc_html_e('Add an annual magazine subscription to your membership before checkout.', 'aac-member-portal'); ?>
						</p>
						<div class="aac-magazine-addons__grid">
							<?php foreach ($magazine_addons as $slug => $addon) : ?>
								<div class="pmpro_form_field pmpro_form_field-checkbox aac-magazine-addons__field">
									<label class="pmpro_form_label pmpro_form_label-inline aac-magazine-addons__label" for="<?php echo esc_attr('aac_magazine_addons_' . $slug); ?>">
										<input
											id="<?php echo esc_attr('aac_magazine_addons_' . $slug); ?>"
											class="aac-magazine-addons__input"
											type="checkbox"
											name="aac_magazine_addons[]"
											value="<?php echo esc_attr($slug); ?>"
											data-aac-magazine-price="<?php echo esc_attr(number_format((float) $addon['price'], 2, '.', '')); ?>"
											<?php checked(in_array($slug, $selected_addons, true)); ?>
										/>
										<span class="aac-magazine-addons__card">
											<?php if (!empty($addon['cover_image_url'])) : ?>
												<span class="aac-magazine-addons__cover">
													<img
														class="aac-magazine-addons__cover-image"
														src="<?php echo esc_url($addon['cover_image_url']); ?>"
														alt="<?php echo esc_attr(sprintf(__('%s cover', 'aac-member-portal'), $addon['label'])); ?>"
														loading="lazy"
													/>
												</span>
											<?php endif; ?>
											<span class="aac-magazine-addons__body">
												<span class="aac-magazine-addons__copy">
													<strong><?php echo esc_html($addon['label']); ?></strong>
													<span><?php echo esc_html($addon['description']); ?></span>
												</span>
												<span class="aac-magazine-addons__footer">
													<span class="aac-magazine-addons__price"><?php echo esc_html($this->format_price($addon['price'])); ?> / year</span>
													<span class="aac-magazine-addons__selector">
														<span class="aac-magazine-addons__check" aria-hidden="true"></span>
														<span class="aac-magazine-addons__selector-copy"><?php esc_html_e('Add subscription', 'aac-member-portal'); ?></span>
													</span>
												</span>
											</span>
										</span>
									</label>
								</div>
							<?php endforeach; ?>
						</div>
						<div class="aac-magazine-addons__summary" data-aac-magazine-summary>
							<?php esc_html_e('No magazine subscriptions selected.', 'aac-member-portal'); ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function filter_pmpro_checkout_level_for_magazine_addons($level) {
		if (!$level || !is_object($level)) {
			return $level;
		}

		$requested_upgrade_level = $this->get_requested_immediate_upgrade_checkout_level($level);
		if ($requested_upgrade_level) {
			$level = $requested_upgrade_level;
		}

		$country_routed_level = $this->get_country_routed_partner_level_for_checkout($level);
		if ($country_routed_level) {
			$level = $country_routed_level;
		}

		$catalog_membership_total = $this->get_aac_membership_level_base_total($level);
		$is_public_signup_checkout = isset($_REQUEST['aac_signup'])
			&& sanitize_text_field(wp_unslash($_REQUEST['aac_signup'])) === '1';
		$base_membership_initial_total = $is_public_signup_checkout && $catalog_membership_total !== null
			? max(0, (float) $catalog_membership_total)
			: max(0, $this->get_level_checkout_initial_total($level));
		$base_membership_recurring_total = $is_public_signup_checkout && $catalog_membership_total !== null
			? max(0, (float) $catalog_membership_total)
			: max(0, $this->get_level_recurring_total($level));
		$incoming_base_membership_initial_total = $base_membership_initial_total;
		$prorated_upgrade_initial_total = $this->get_prorated_upgrade_initial_total_for_checkout(
			$level,
			$base_membership_initial_total,
			$base_membership_recurring_total
		);
		if ($prorated_upgrade_initial_total !== null) {
			$base_membership_initial_total = max(0, (float) $prorated_upgrade_initial_total);
		}
		$partner_family_config = $this->is_international_checkout_request()
			? $this->normalize_partner_family_config([])
			: $this->get_requested_partner_family_config();
		$supports_family_plan = $this->supports_family_plan_tiers($level);
		if (!$supports_family_plan) {
			$partner_family_config = $this->normalize_partner_family_config([]);
		}
		$is_partner_family_checkout = ($partner_family_config['mode'] ?? '') === 'family';
		$preserve_prorated_initial_total = $this->should_preserve_prorated_checkout_initial_total($level, $base_membership_initial_total);
		if ($is_partner_family_checkout) {
			$catalog_base_total = $this->get_aac_membership_level_base_total($level);
			if ($catalog_base_total !== null) {
				// Family pricing must stay deterministic even if PMPro or another add-on
				// has already filtered the level's initial_payment on this request.
				// For logged-in upgrades, keep PMPro Proration's initial payment and
				// only use the catalog price for recurring renewal/add-on math.
				if (!$preserve_prorated_initial_total) {
					$base_membership_initial_total = max(0, (float) $catalog_base_total);
				}
				$base_membership_recurring_total = max(0, (float) $catalog_base_total);
			}
		}
		$partner_family_total = $this->get_partner_family_addon_total($base_membership_recurring_total, $partner_family_config);
		$membership_discount_amount = $this->get_requested_membership_discount_amount($base_membership_initial_total, $level, $partner_family_config);
		$membership_recurring_discount_amount = $this->get_requested_membership_discount_amount($base_membership_recurring_total, $level, $partner_family_config);
		$selected_addons = [];
		$addon_total = 0.0;
		$checkout_account_info = $this->get_checkout_account_info_from_request();
		$international_surcharge = $this->get_international_print_surcharge_amount($checkout_account_info, isset($level->id) ? (int) $level->id : 0);
		$autorenew_reactivation_context = $this->get_autorenew_reactivation_checkout_context($level);
		$add_dependent_context = $this->get_add_dependent_checkout_context($level);
		if ($add_dependent_context) {
			$this->add_dependent_checkout_context = $add_dependent_context;
			$partner_family_config = $add_dependent_context['next_family_config'];
			$partner_family_total = $this->get_partner_family_addon_total($base_membership_recurring_total, $partner_family_config);
			$membership_discount_amount = 0.0;
			$membership_recurring_discount_amount = 0.0;
		}
		if (
			$addon_total <= 0
			&& $partner_family_total <= 0
			&& $international_surcharge <= 0
			&& $membership_discount_amount <= 0
			&& $membership_recurring_discount_amount <= 0
			&& $prorated_upgrade_initial_total === null
			&& !$this->is_checkout_autorenew_disabled_request()
			&& !$add_dependent_context
			&& !$autorenew_reactivation_context
		) {
			// PMPro Donations adds its amount later at priority 99. Reset public
			// signup pricing to the catalog amount on every filter pass so repeated
			// PMPro recalculations cannot compound the same donation.
			if ($is_public_signup_checkout && $catalog_membership_total !== null) {
				$level->initial_payment = $base_membership_initial_total;
				if (isset($level->billing_amount) && (float) $level->billing_amount > 0) {
					$level->billing_amount = $base_membership_recurring_total;
				}
			}
			return $level;
		}

		$adjusted_initial_total = round(
			max(0, $base_membership_initial_total - $membership_discount_amount) + $partner_family_total + $addon_total + $international_surcharge,
			2
		);
		$adjusted_recurring_total = round(
			max(0, $base_membership_recurring_total - $membership_recurring_discount_amount) + $partner_family_total + $addon_total + $international_surcharge,
			2
		);
		if ($add_dependent_context) {
			// Adding a dependent mid-term should charge only the prorated new child slot now.
			// The recurring amount still includes the full family configuration for renewal.
			$adjusted_initial_total = round((float) ($add_dependent_context['prorated_amount'] ?? 0), 2);
		}
		if ($autorenew_reactivation_context) {
			// Reactivating auto-renew should not double-charge the already-paid current term.
			$adjusted_initial_total = 0.0;
			$level->startdate = $autorenew_reactivation_context['start_date'];
		}
		if ($this->is_checkout_autorenew_disabled_request() && !$autorenew_reactivation_context) {
			$adjusted_recurring_total = 0.0;
		}

		if ($is_partner_family_checkout && !$add_dependent_context) {
			$expected_family_total = round($base_membership_recurring_total + $partner_family_total + $international_surcharge + $addon_total, 2);
			if (
				$expected_family_total >= 0
				&& (
					$adjusted_initial_total > ($expected_family_total + 0.01)
					|| $incoming_base_membership_initial_total > ($expected_family_total + 0.01)
				)
			) {
				$this->log_checkout_event([
					'severity' => 'warning',
					'area' => 'checkout',
					'event_type' => 'family_checkout_price_guard',
					'message' => 'Partner family checkout initial payment was capped to the AAC catalog family total.',
					'error_code' => 'aac_family_checkout_price_guard',
					'pmpro_level_id' => isset($level->id) ? (int) $level->id : $this->get_requested_level_id(),
					'context' => $this->get_checkout_log_context([
						'incoming_initial_total' => round($incoming_base_membership_initial_total, 2),
						'catalog_base_total' => round($base_membership_recurring_total, 2),
						'family_addon_total' => round($partner_family_total, 2),
						'international_surcharge' => round($international_surcharge, 2),
						'adjusted_initial_total' => round($adjusted_initial_total, 2),
						'guarded_initial_total' => $expected_family_total,
						'family_config' => $partner_family_config,
					]),
				]);
				$adjusted_initial_total = $expected_family_total;
			}
		}

		if (isset($level->initial_payment)) {
			$level->initial_payment = $adjusted_initial_total;
		}

		if (isset($level->billing_amount) && (float) $level->billing_amount > 0) {
			$level->billing_amount = $adjusted_recurring_total;
		}

		return $level;
	}

	private function get_requested_immediate_upgrade_checkout_level($level) {
		if (
			!is_user_logged_in()
			|| !$this->is_pmpro_checkout_request()
			|| !function_exists('pmpro_getLevel')
			|| !class_exists('AAC_Member_Portal_PMPro')
			|| !AAC_Member_Portal_PMPro::is_available()
		) {
			return null;
		}

		$user_id = get_current_user_id();
		$requested_level_id = $this->get_requested_checkout_level_id();
		$current_membership = AAC_Member_Portal_PMPro::get_primary_membership($user_id);
		$current_level_id = is_array($current_membership) ? (int) ($current_membership['level_id'] ?? 0) : 0;
		if ($user_id <= 0 || $requested_level_id <= 0 || $current_level_id <= 0 || $requested_level_id === $current_level_id) {
			return null;
		}

		$current_rank = AAC_Member_Portal_PMPro::get_tier_rank_for_level_id($current_level_id);
		$requested_rank = AAC_Member_Portal_PMPro::get_tier_rank_for_level_id($requested_level_id);
		if ($current_rank <= 0 || $requested_rank <= 0 || $requested_rank <= $current_rank) {
			return null;
		}

		$current_checkout_level_id = is_object($level) && isset($level->id) ? (int) $level->id : 0;
		if ($current_checkout_level_id === $requested_level_id) {
			return null;
		}

		$requested_level = pmpro_getLevel($requested_level_id);
		return is_object($requested_level) ? clone $requested_level : null;
	}

	public function filter_pmpro_checkout_start_date_for_autorenew_reactivation($startdate, $user_id = null) {
		$context = $this->get_autorenew_reactivation_checkout_context();
		if (!$context) {
			return $startdate;
		}

		return $context['start_date'];
	}

	public function filter_pmpro_level_cost_text_for_autorenew_reactivation($text, $level, $tags = true, $short = false) {
		$context = $this->get_autorenew_reactivation_checkout_context($level);
		if (!$context) {
			return $text;
		}

		$renewal_amount = $this->format_price($context['renewal_amount']);
		$renewal_date = date_i18n(get_option('date_format'), strtotime($context['start_date']));
		$message = sprintf(
			'$0 today. Your recurring renewal will restart on %1$s at %2$s.',
			$renewal_date,
			$renewal_amount
		);

		if (!$tags) {
			return wp_strip_all_tags($message);
		}

		return sprintf('<span class="pmpro_level-cost">%s</span>', esc_html($message));
	}

	public function maybe_capture_cancel_preserve_term() {
		if (is_admin() || !is_user_logged_in() || !$this->is_pmpro_cancel_request() || !class_exists('AAC_Member_Portal_PMPro')) {
			return;
		}

		$user_id = get_current_user_id();
		$primary_membership = AAC_Member_Portal_PMPro::get_primary_membership($user_id);
		if (!is_array($primary_membership) || empty($primary_membership['level_id'])) {
			return;
		}

		$term_end_date = sanitize_text_field((string) (
			$primary_membership['renewal_date']
			?: ($primary_membership['valid_through_date'] ?: $primary_membership['expiration_date'])
		));
		$term_end_date = AAC_Member_Portal_PMPro::normalize_date_to_day_end($term_end_date, true);
		if ($term_end_date === '' || strtotime($term_end_date) < current_time('timestamp')) {
			return;
		}

		update_user_meta($user_id, '_aac_cancel_preserve_term', [
			'level_id' => (int) $primary_membership['level_id'],
			'enddate' => $term_end_date,
			'captured_at' => time(),
		]);
	}

	public function maybe_redirect_non_autorenew_cancel_request() {
		if (is_admin() || !is_user_logged_in() || !$this->is_pmpro_cancel_request() || !class_exists('AAC_Member_Portal_PMPro')) {
			return;
		}

		$user_id = get_current_user_id();
		$primary_membership = AAC_Member_Portal_PMPro::get_primary_membership($user_id);
		$current_level_id = is_array($primary_membership) ? absint($primary_membership['level_id'] ?? 0) : 0;
		if ($user_id <= 0 || $current_level_id <= 0) {
			return;
		}

		if (AAC_Member_Portal_PMPro::has_active_auto_renewal($user_id, $current_level_id)) {
			return;
		}

		wp_safe_redirect(add_query_arg('aac_cancel_unavailable', '1', $this->get_portal_manage_membership_url()));
		exit;
	}

	public function maybe_restore_cancelled_membership_through_term($level_id, $user_id) {
		$user_id = (int) $user_id;
		$new_level_id = (int) $level_id;
		if ($user_id <= 0 || $new_level_id > 0 || !class_exists('AAC_Member_Portal_PMPro')) {
			return;
		}

		$preserve_term = get_user_meta($user_id, '_aac_cancel_preserve_term', true);
		if (!is_array($preserve_term)) {
			return;
		}

		delete_user_meta($user_id, '_aac_cancel_preserve_term');

		$captured_at = absint($preserve_term['captured_at'] ?? 0);
		if ($captured_at <= 0 || time() - $captured_at > DAY_IN_SECONDS) {
			return;
		}

		$cancelled_level_id = absint($preserve_term['level_id'] ?? 0);
		$term_end_date = AAC_Member_Portal_PMPro::normalize_date_to_day_end($preserve_term['enddate'] ?? '', true);
		if ($cancelled_level_id <= 0 || $term_end_date === '' || strtotime($term_end_date) < current_time('timestamp')) {
			return;
		}

		$this->restore_user_membership_row_through_term($user_id, $cancelled_level_id, $term_end_date);
	}

	public function maybe_normalize_existing_membership_enddates() {
		$migration_version = 'month-end-expiration-and-renewal-v2';
		$current_migration_version = get_option('aac_member_portal_month_end_expiration_version');
		if ($current_migration_version === $migration_version) {
			return;
		}

		$this->normalize_all_pmpro_membership_enddates_to_month_end();
		$this->normalize_all_pmpro_subscription_dates_to_month_end();
		$this->sync_all_family_child_month_end_dates();
		update_option('aac_member_portal_month_end_expiration_version', $migration_version, false);
	}

	public function capture_checkout_membership_change_context() {
		$request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
		if (
			is_admin()
			|| $request_method !== 'POST'
			|| !is_user_logged_in()
			|| !$this->is_pmpro_checkout_request()
			|| !class_exists('AAC_Member_Portal_PMPro')
			|| !AAC_Member_Portal_PMPro::is_available()
		) {
			return;
		}

		$user_id = get_current_user_id();
		$current_membership = AAC_Member_Portal_PMPro::get_primary_membership($user_id);
		$current_level_id = is_array($current_membership) ? (int) ($current_membership['level_id'] ?? 0) : 0;
		$requested_level_id = $this->get_requested_checkout_level_id();
		if ($user_id <= 0 || $current_level_id <= 0 || $requested_level_id <= 0 || $current_level_id === $requested_level_id) {
			return;
		}

		$current_rank = AAC_Member_Portal_PMPro::get_tier_rank_for_level_id($current_level_id);
		$requested_rank = AAC_Member_Portal_PMPro::get_tier_rank_for_level_id($requested_level_id);
		$change_type = 'level_change';
		if ($current_rank > 0 && $requested_rank > 0) {
			$change_type = $requested_rank > $current_rank ? 'upgrade' : 'downgrade';
		}

		$this->checkout_membership_change_context = [
			'user_id' => $user_id,
			'from_level_id' => $current_level_id,
			'to_level_id' => $requested_level_id,
			'change_type' => $change_type,
			'transaction_date' => current_time('Y-m-d'),
			'captured_at' => time(),
		];
	}

	public function normalize_membership_enddate_after_change($level_id, $user_id) {
		$user_id = (int) $user_id;
		$level_id = (int) $level_id;
		if ($user_id <= 0 || $level_id <= 0) {
			return;
		}

		if ($this->is_checkout_membership_change_for_user($user_id, $level_id)) {
			$renewal_enddate = $this->get_transaction_anchored_renewal_enddate($level_id);
			if ($renewal_enddate !== '') {
				$this->set_user_pmpro_membership_enddate_exact($user_id, $level_id, $renewal_enddate);
				$this->set_user_pmpro_subscription_renewal_date($user_id, $level_id, $renewal_enddate);
				clean_user_cache($user_id);
				return;
			}
		}

		$this->normalize_user_pmpro_membership_enddates_to_month_end($user_id, $level_id);
	}

	public function sync_family_child_month_end_dates_after_parent_change($level_id, $user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return;
		}

		$this->sync_family_child_month_end_dates($user_id, $this->is_checkout_membership_change_for_user($user_id, (int) $level_id));
	}

	public function sync_linked_child_month_end_date($parent_user_id, $child_user_id) {
		$parent_user_id = (int) $parent_user_id;
		$child_user_id = (int) $child_user_id;
		if ($parent_user_id <= 0 || $child_user_id <= 0) {
			return;
		}

		$parent_enddate = $this->get_parent_family_month_end_term_date($parent_user_id);
		if ($parent_enddate === '') {
			return;
		}

		$slot = $this->get_family_slot_for_child($parent_user_id, $child_user_id);
		$child_level_id = $this->get_child_level_id_for_family_slot($slot);
		if ($child_level_id <= 0) {
			return;
		}

		$this->set_user_pmpro_membership_enddate($child_user_id, $child_level_id, $parent_enddate . ' 23:59:59');
		if (get_user_meta($child_user_id, 'aac_family_membership_pending_removal', true) === '1') {
			update_user_meta($child_user_id, 'aac_family_membership_access_until', $parent_enddate);
		}
	}

	public function sync_pmpro_checkout_profile_fields($level_id, $user_id) {
		$request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
		if ($request_method !== 'POST' || !$this->is_pmpro_checkout_request() || !$user_id) {
			return;
		}

		$user = get_user_by('id', $user_id);
		if (!$user instanceof WP_User || !$user->exists()) {
			return;
		}

		$stored_account_info = $this->get_account_info_defaults_for_user($user);
		$request_account_info = $this->get_checkout_account_info_from_request($user);
		$next_account_info = array_merge($stored_account_info, $request_account_info, [
			'email' => $user->user_email,
			'size' => isset($_REQUEST['t_shirt'])
				? $this->normalize_tshirt_size_value(wp_unslash($_REQUEST['t_shirt']))
				: $this->normalize_tshirt_size_value($stored_account_info['size'] ?? ''),
			'photo_url' => $stored_account_info['photo_url'] ?? get_avatar_url($user_id),
			'auto_renew' => isset($_REQUEST['autorenew_present'])
				? !empty($_REQUEST['autorenew'])
				: !empty($stored_account_info['auto_renew']),
		]);

		$next_account_info = array_merge(
			$next_account_info,
			$this->get_checkout_publication_preferences($stored_account_info, 'Print')
		);
		$is_international_checkout = $this->is_international_country($next_account_info['country'] ?? 'US');
		if ($is_international_checkout) {
			$next_account_info['size'] = 'No T-shirt';
			$next_account_info['guidebook_pref'] = 'Digital';
		}

		$next_account_info['name'] = trim($next_account_info['first_name'] . ' ' . $next_account_info['last_name']);
		if ($next_account_info['name'] === '') {
			$next_account_info['name'] = $stored_account_info['name'] ?? $user->display_name;
		}

		$selected_magazine_addons = [];
		$checkout_level = $this->get_level_at_checkout();
		$checkout_level_supports_discount = $this->supports_discount_tiers($checkout_level);
		$checkout_level_supports_family = $this->supports_family_plan_tiers($checkout_level);
		$partner_family_config = $this->has_partner_family_request()
			? $this->get_requested_partner_family_config()
			: $this->get_effective_partner_family_config($user_id);
		$membership_discount_type = $checkout_level_supports_discount
			? ($this->has_membership_discount_request()
				? $this->get_requested_membership_discount_type()
				: $this->get_effective_membership_discount_type($user_id))
			: '';
		if (!$checkout_level_supports_family) {
			$partner_family_config = $this->normalize_partner_family_config([]);
		}
		if ($is_international_checkout) {
			$partner_family_config = $this->normalize_partner_family_config([]);
			$membership_discount_type = '';
		}
		if (($partner_family_config['mode'] ?? '') === 'family') {
			$membership_discount_type = '';
		}

		delete_user_meta($user_id, 'aac_magazine_addons');

			if ($this->has_membership_discount_request() || $is_international_checkout) {
				update_user_meta($user_id, 'aac_membership_discount_type', $membership_discount_type);
			}

			if ($membership_discount_type === 'student' && !$is_international_checkout && $checkout_level_supports_discount) {
				$next_account_info['student_university'] = $this->get_requested_student_university();
				$next_account_info['student_university_id'] = $this->get_requested_student_university_id();
				$next_account_info['graduation_date'] = $this->get_requested_graduation_date();
				update_user_meta($user_id, 'student_university', $next_account_info['student_university']);
				update_user_meta($user_id, 'university_or_school', $next_account_info['student_university']);
				update_user_meta($user_id, 'student_university_id', $next_account_info['student_university_id']);
				update_user_meta($user_id, 'graduation_date', $next_account_info['graduation_date']);
				update_user_meta($user_id, 'student_graduation_date', $next_account_info['graduation_date']);
				delete_user_meta($user_id, 'service_component');
				delete_user_meta($user_id, 'service_branch');
				delete_user_meta($user_id, 'military_service_component');
			} elseif ($membership_discount_type === 'military' && !$is_international_checkout && $checkout_level_supports_discount) {
				$next_account_info['service_component'] = $this->get_requested_service_component();
				update_user_meta($user_id, 'service_component', $next_account_info['service_component']);
				update_user_meta($user_id, 'military_service_component', $next_account_info['service_component']);
				delete_user_meta($user_id, 'student_university');
				delete_user_meta($user_id, 'university_or_school');
				delete_user_meta($user_id, 'student_university_id');
				delete_user_meta($user_id, 'graduation_date');
				delete_user_meta($user_id, 'student_graduation_date');
			} elseif ($this->has_membership_discount_request() || $is_international_checkout) {
				$next_account_info['student_university'] = '';
				$next_account_info['student_university_id'] = '';
				$next_account_info['graduation_date'] = '';
				$next_account_info['service_component'] = '';
				delete_user_meta($user_id, 'student_university');
				delete_user_meta($user_id, 'university_or_school');
				delete_user_meta($user_id, 'student_university_id');
				delete_user_meta($user_id, 'graduation_date');
				delete_user_meta($user_id, 'student_graduation_date');
				delete_user_meta($user_id, 'service_component');
				delete_user_meta($user_id, 'service_branch');
				delete_user_meta($user_id, 'military_service_component');
			}

			if ($this->has_partner_family_request() || $is_international_checkout) {
				update_user_meta($user_id, 'aac_partner_family_config', $partner_family_config);
			}

		update_user_meta($user_id, 'aac_account_info', $this->strip_pmpro_managed_account_fields_for_storage($next_account_info));
		$this->sync_reportable_member_fields($user_id, $next_account_info, $selected_magazine_addons, $membership_discount_type);
		$this->sync_partner_family_member_slots($user_id, $partner_family_config, $this->get_level_recurring_total($this->get_level_at_checkout()));

		wp_update_user([
			'ID' => $user_id,
			'first_name' => $next_account_info['first_name'],
			'last_name' => $next_account_info['last_name'],
			'display_name' => $next_account_info['name'],
		]);
	}

	public function clear_partner_only_discount_after_level_change($level_id, $user_id) {
		$user_id = absint($user_id);
		if ($user_id <= 0) {
			return;
		}

		if ($this->supports_discount_tiers((int) $level_id)) {
			return;
		}

		$this->clear_membership_discount_type($user_id);
	}

	public function ensure_immediate_upgrade_checkout_level($user_id, $morder) {
		$user_id = absint($user_id);
		if (
			$user_id <= 0
			|| !is_array($this->checkout_membership_change_context)
			|| !function_exists('pmpro_changeMembershipLevel')
			|| !class_exists('AAC_Member_Portal_PMPro')
			|| !AAC_Member_Portal_PMPro::is_available()
		) {
			return;
		}

		$context = $this->checkout_membership_change_context;
		if ((int) ($context['user_id'] ?? 0) !== $user_id || ($context['change_type'] ?? '') !== 'upgrade') {
			return;
		}

		$from_level_id = absint($context['from_level_id'] ?? 0);
		$target_level_id = absint($context['to_level_id'] ?? 0);
		if ($from_level_id <= 0 || $target_level_id <= 0 || $from_level_id === $target_level_id) {
			return;
		}

		$from_rank = AAC_Member_Portal_PMPro::get_tier_rank_for_level_id($from_level_id);
		$target_rank = AAC_Member_Portal_PMPro::get_tier_rank_for_level_id($target_level_id);
		if ($from_rank <= 0 || $target_rank <= $from_rank) {
			return;
		}

		$current_membership = AAC_Member_Portal_PMPro::get_primary_membership($user_id);
		$current_level_id = is_array($current_membership) ? absint($current_membership['level_id'] ?? 0) : 0;
		if ($current_level_id === $target_level_id) {
			$this->sync_checkout_order_membership_id($morder, $target_level_id);
			return;
		}

		if ($current_level_id !== $from_level_id) {
			return;
		}

		pmpro_changeMembershipLevel($target_level_id, $user_id);
		$this->sync_checkout_order_membership_id($morder, $target_level_id);

		$this->log_checkout_event([
			'severity' => 'warning',
			'area' => 'membership',
			'event_type' => 'immediate_upgrade_level_repaired',
			'user_id' => $user_id,
			'pmpro_level_id' => $target_level_id,
			'message' => 'Upgrade checkout completed but the active PMPro level still matched the prior level, so AAC moved the member to the requested upgrade level.',
			'context' => $this->get_checkout_log_context([
				'from_level_id' => $from_level_id,
				'target_level_id' => $target_level_id,
				'order_id' => is_object($morder) && isset($morder->id) ? absint($morder->id) : 0,
			]),
		]);
	}

	private function sync_checkout_order_membership_id($morder, $level_id) {
		$level_id = absint($level_id);
		if ($level_id <= 0 || !is_object($morder)) {
			return;
		}

		$morder->membership_id = $level_id;

		$order_id = isset($morder->id) ? absint($morder->id) : 0;
		if ($order_id <= 0) {
			return;
		}

		global $wpdb;
		if (!$wpdb) {
			return;
		}

		$wpdb->update(
			$wpdb->prefix . 'pmpro_membership_orders',
			['membership_id' => $level_id],
			['id' => $order_id],
			['%d'],
			['%d']
		);
	}

	public function clear_scheduled_downgrade_after_membership_change($level_id, $user_id) {
		$user_id = absint($user_id);
		$level_id = absint($level_id);
		if ($user_id <= 0 || !class_exists('AAC_Member_Portal_PMPro')) {
			return;
		}

		if ($level_id <= 0) {
			$this->clear_scheduled_membership_downgrade($user_id, 'membership_cancelled');
			return;
		}

		if ($this->should_clear_scheduled_downgrade_for_level($user_id, $level_id)) {
			$this->clear_scheduled_membership_downgrade($user_id, 'membership_level_changed');
		}
	}

	public function clear_scheduled_downgrade_after_checkout($user_id, $morder) {
		$user_id = absint($user_id);
		if ($user_id <= 0 || !class_exists('AAC_Member_Portal_PMPro')) {
			return;
		}

		$level_id = 0;
		if (is_object($morder)) {
			foreach (['membership_id', 'membership_level_id', 'level_id'] as $property) {
				if (!isset($morder->{$property})) {
					continue;
				}

				$level_id = absint($morder->{$property});
				if ($level_id > 0) {
					break;
				}
			}
		}

		if ($level_id <= 0) {
			$level_id = $this->get_requested_checkout_level_id();
		}

		if ($level_id > 0 && $this->should_clear_scheduled_downgrade_for_level($user_id, $level_id)) {
			$this->clear_scheduled_membership_downgrade($user_id, 'checkout_completed');
		}
	}

	private function should_clear_scheduled_downgrade_for_level($user_id, $level_id) {
		$user_id = absint($user_id);
		$level_id = absint($level_id);
		if ($user_id <= 0 || $level_id <= 0 || !class_exists('AAC_Member_Portal_PMPro')) {
			return false;
		}

		$pending = AAC_Member_Portal_PMPro::get_pending_membership_downgrade($user_id);
		if (!is_array($pending)) {
			return false;
		}

		$pending_level_id = absint($pending['target_level_id'] ?? 0);
		if ($pending_level_id > 0 && $pending_level_id === $level_id) {
			return true;
		}

		if (
			is_array($this->checkout_membership_change_context)
			&& (int) ($this->checkout_membership_change_context['user_id'] ?? 0) === $user_id
			&& ($this->checkout_membership_change_context['change_type'] ?? '') === 'upgrade'
		) {
			return true;
		}

		$level_rank = AAC_Member_Portal_PMPro::get_tier_rank_for_level_id($level_id);
		$pending_rank = $pending_level_id > 0
			? AAC_Member_Portal_PMPro::get_tier_rank_for_level_id($pending_level_id)
			: AAC_Member_Portal_PMPro::get_tier_rank_from_name($pending['target_tier'] ?? '');

		return $level_rank > 0 && $pending_rank > 0 && $level_rank >= $pending_rank;
	}

	private function clear_scheduled_membership_downgrade($user_id, $reason) {
		$user_id = absint($user_id);
		if ($user_id <= 0 || !class_exists('AAC_Member_Portal_PMPro')) {
			return false;
		}

		$cleared = AAC_Member_Portal_PMPro::clear_pending_membership_downgrade($user_id, $reason);
		if ($cleared && class_exists('AAC_Member_Portal_Error_Log')) {
			AAC_Member_Portal_Error_Log::record([
				'severity' => 'info',
				'area' => 'membership',
				'event_type' => 'scheduled_downgrade_cleared',
				'user_id' => $user_id,
				'message' => 'Scheduled membership downgrade was cleared.',
				'context' => [
					'reason' => sanitize_key((string) $reason),
				],
			]);
		}

		return $cleared;
	}

	public function capture_pmpro_checkout_order_breakdown($user_id, $morder) {
		if (!is_object($morder)) {
			return;
		}

		$order_breakdown = $this->build_pmpro_order_breakdown_payload($morder, (int) $user_id);
		if (empty($order_breakdown['items'])) {
			return;
		}

		foreach ($this->get_pmpro_order_breakdown_storage_keys($morder) as $storage_key) {
			update_option($storage_key, $order_breakdown, false);
		}
	}

	public function log_checkout_post_checkpoint() {
		if (!$this->is_checkout_post_request()) {
			return;
		}

		$this->log_checkout_event([
			'severity' => 'info',
			'area' => 'checkout',
			'event_type' => 'checkout_post_received',
			'message' => 'Checkout POST received before PMPro processing.',
			'pmpro_level_id' => $this->get_requested_level_id(),
			'context' => $this->get_checkout_log_context(),
		]);
	}

	public function log_pmpro_registration_failure($okay) {
		if ($okay || !$this->is_checkout_post_request()) {
			return $okay;
		}

		$this->log_checkout_error_once(
			'pmpro_registration_checks_failed',
			$this->get_pmpro_checkout_message('PMPro registration checks failed.'),
			'pmpro_registration_checks'
		);

		return $okay;
	}

	public function capture_checkout_shutdown_error() {
		if (!$this->is_checkout_post_request()) {
			return;
		}

		global $pmpro_msgt, $pmpro_error_fields, $pmpro_required_user_fields, $pmpro_required_billing_fields;
		$message_type = is_string($pmpro_msgt) ? sanitize_key($pmpro_msgt) : '';
		if ($message_type !== 'pmpro_error' && $message_type !== 'error') {
			return;
		}

		$missing_user_fields = [];
		foreach ((array) $pmpro_required_user_fields as $field_name => $field_value) {
			if ($field_value === '' || $field_value === null || $field_value === false) {
				$missing_user_fields[] = sanitize_key((string) $field_name);
			}
		}
		$missing_billing_fields = [];
		foreach ((array) $pmpro_required_billing_fields as $field_name => $field_value) {
			if ($field_value === '' || $field_value === null || $field_value === false) {
				$missing_billing_fields[] = sanitize_key((string) $field_name);
			}
		}

		$this->log_checkout_error_once(
			'checkout_shutdown_error',
			$this->get_pmpro_checkout_message('Checkout stopped with an error before completion.'),
			$message_type,
			[
				'pmpro_error_fields' => array_values(array_unique(array_map('sanitize_key', (array) $pmpro_error_fields))),
				'missing_required_user_fields' => array_values(array_unique($missing_user_fields)),
				'missing_required_billing_fields' => array_values(array_unique($missing_billing_fields)),
			]
		);
	}

	public function log_pmpro_checkout_success($user_id, $morder) {
		$user_id = absint($user_id);
		$order_fields = $this->get_pmpro_order_log_fields($morder, $user_id);

		$this->log_checkout_event(array_merge($order_fields, [
			'severity' => 'info',
			'area' => 'payment',
			'event_type' => 'pmpro_checkout_success',
			'user_id' => $user_id,
			'message' => 'PMPro checkout completed and returned an order object.',
			'context' => $this->get_checkout_log_context([
				'pmpro_order_status' => is_object($morder) && isset($morder->status) ? (string) $morder->status : '',
				'pmpro_order_total' => is_object($morder) && isset($morder->total) ? (string) $morder->total : '',
				'payment_transaction_id' => is_object($morder) && isset($morder->payment_transaction_id) ? (string) $morder->payment_transaction_id : '',
				'subscription_transaction_id' => is_object($morder) && isset($morder->subscription_transaction_id) ? (string) $morder->subscription_transaction_id : '',
			]),
		]));
	}

	public function log_pmpro_membership_level_change($level_id, $user_id) {
		$user_id = absint($user_id);
		$level_id = absint($level_id);

		$this->log_checkout_event([
			'severity' => 'info',
			'area' => 'membership',
			'event_type' => 'pmpro_membership_level_changed',
			'user_id' => $user_id,
			'pmpro_level_id' => $level_id,
			'stripe_customer_id' => $this->get_user_stripe_customer_id($user_id),
			'message' => 'PMPro membership level changed.',
			'context' => [
				'level_id' => $level_id,
				'request_method' => $this->get_request_method(),
				'request_uri' => $this->get_current_request_url(),
			],
		]);
	}

	public function sync_member_record_to_pmpro_fields($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return false;
		}

		$user = get_user_by('id', $user_id);
		if (!$user instanceof WP_User || !$user->exists()) {
			return false;
		}

		$account_info = $this->get_account_info_defaults_for_user($user);
		return $this->sync_account_info_to_pmpro_fields($user_id, $account_info);
	}

	public function sync_account_info_to_pmpro_fields($user_id, $account_info) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !is_array($account_info)) {
			return false;
		}

		$user = get_user_by('id', $user_id);
		if (!$user instanceof WP_User || !$user->exists()) {
			return false;
		}

		$selected_magazine_addons = $this->get_effective_magazine_addon_selection($user_id);
		$membership_discount_type = $this->get_effective_membership_discount_type($user_id);

		update_user_meta($user_id, 'aac_account_info', $this->strip_pmpro_managed_account_fields_for_storage($account_info));
		$this->sync_reportable_member_fields($user_id, $account_info, $selected_magazine_addons, $membership_discount_type);

		wp_update_user([
			'ID' => $user_id,
			'first_name' => $account_info['first_name'] ?? '',
			'last_name' => $account_info['last_name'] ?? '',
			'display_name' => $account_info['name'] ?? trim(($account_info['first_name'] ?? '') . ' ' . ($account_info['last_name'] ?? '')),
		]);

		return true;
	}

	public function backfill_pmpro_fields_from_member_database() {
		$user_ids = $this->get_member_ids_for_pmpro_field_backfill();
		$synced = 0;

		foreach (array_chunk($user_ids, 200) as $user_id_batch) {
			foreach ($user_id_batch as $user_id) {
				if ($this->sync_member_record_to_pmpro_fields($user_id)) {
					$synced++;
				}
			}
		}

		return [
			'candidate_count' => count($user_ids),
			'synced_count' => $synced,
		];
	}

	public function append_pmpro_confirmation_line_items($confirmation_message, $pmpro_invoice) {
		if (!is_object($pmpro_invoice)) {
			return $confirmation_message;
		}

		if (is_string($confirmation_message) && strpos($confirmation_message, 'aac-order-summary') !== false) {
			return $confirmation_message;
		}

		$order_breakdown = $this->get_pmpro_order_breakdown_payload($pmpro_invoice);
		if (empty($order_breakdown['items'])) {
			return $confirmation_message;
		}

		$summary_markup = $this->render_pmpro_order_breakdown_markup($order_breakdown);
		if ($summary_markup === '') {
			return $confirmation_message;
		}

		return $this->strip_pmpro_order_references_from_confirmation((string) $confirmation_message) . $summary_markup;
	}

	public function get_pmpro_checkout_profile_defaults() {
		$user = wp_get_current_user();
		$account_info = $this->get_account_info_defaults_for_user($user instanceof WP_User && $user->exists() ? $user : null);

		$account_info = array_merge(
			$account_info,
			$this->get_checkout_publication_preferences($account_info, 'Print')
		);

		if (isset($_REQUEST['t_shirt'])) {
			$account_info['size'] = $this->normalize_tshirt_size_value(wp_unslash($_REQUEST['t_shirt']));
		}

		return [
			'first_name' => $account_info['first_name'],
			'last_name' => $account_info['last_name'],
			'email' => $account_info['email'],
			'phone' => $account_info['phone'],
			'birthdate' => $account_info['birthdate'],
			'street' => $account_info['street'],
			'address2' => $account_info['address2'],
			'city' => $account_info['city'],
			'state' => $account_info['state'],
			'zip' => $account_info['zip'],
			'country' => $account_info['country'],
			'publication_pref' => $account_info['publication_pref'],
			'aaj_pref' => $account_info['aaj_pref'],
			'anac_pref' => $account_info['anac_pref'],
			'acj_pref' => $account_info['acj_pref'],
			'guidebook_pref' => $account_info['guidebook_pref'],
			'size' => $account_info['size'],
		];
	}

	public function render_pmpro_member_address_fields($user) {
		if (!$user instanceof WP_User || !$user->exists()) {
			return;
		}

		$address_fields = [
			'pmpro_sfirstname' => ['label' => 'First Name', 'autocomplete' => 'given-name'],
			'pmpro_slastname' => ['label' => 'Last Name', 'autocomplete' => 'family-name'],
			'pmpro_saddress1' => ['label' => 'Address Line 1', 'autocomplete' => 'address-line1'],
			'pmpro_saddress2' => ['label' => 'Address Line 2', 'autocomplete' => 'address-line2'],
			'pmpro_scity' => ['label' => 'City', 'autocomplete' => 'address-level2'],
			'pmpro_sstate' => ['label' => 'State / Province', 'autocomplete' => 'address-level1'],
			'pmpro_szipcode' => ['label' => 'Postal Code', 'autocomplete' => 'postal-code'],
			'pmpro_scountry' => ['label' => 'Country', 'autocomplete' => 'country-name'],
			'pmpro_sphone' => ['label' => 'Phone', 'autocomplete' => 'tel'],
		];
		?>
		<h2>AAC / PMPro Name &amp; Mailing Address</h2>
		<table class="form-table" role="presentation">
			<tbody>
				<?php foreach ($address_fields as $meta_key => $field) : ?>
					<tr>
						<th>
							<label for="<?php echo esc_attr($meta_key); ?>">
								<?php echo esc_html($field['label']); ?>
							</label>
						</th>
						<td>
							<input
								type="text"
								name="<?php echo esc_attr($meta_key); ?>"
								id="<?php echo esc_attr($meta_key); ?>"
								value="<?php echo esc_attr($this->get_pmpro_mailing_address_admin_field_value($user, $meta_key)); ?>"
								class="regular-text"
								autocomplete="<?php echo esc_attr($field['autocomplete']); ?>"
							/>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	public function save_pmpro_member_address_fields($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !current_user_can('edit_user', $user_id)) {
			return;
		}

		foreach (['pmpro_sfirstname', 'pmpro_slastname', 'pmpro_saddress1', 'pmpro_saddress2', 'pmpro_scity', 'pmpro_sstate', 'pmpro_szipcode', 'pmpro_scountry', 'pmpro_sphone'] as $meta_key) {
			if (!isset($_POST[$meta_key])) {
				continue;
			}

			update_user_meta(
				$user_id,
				$meta_key,
				sanitize_text_field(wp_unslash($_POST[$meta_key]))
			);
		}

		$first_name = sanitize_text_field(wp_unslash($_POST['pmpro_sfirstname'] ?? ''));
		$last_name = sanitize_text_field(wp_unslash($_POST['pmpro_slastname'] ?? ''));
		if ($first_name !== '' || $last_name !== '') {
			wp_update_user([
				'ID' => $user_id,
				'first_name' => $first_name,
				'last_name' => $last_name,
				'display_name' => trim($first_name . ' ' . $last_name),
			]);
		}

	}

	private function get_pmpro_mailing_address_admin_field_value(WP_User $user, $meta_key) {
		$meta_key = sanitize_key((string) $meta_key);
		$value = get_user_meta($user->ID, $meta_key, true);
		if (is_string($value) && trim($value) !== '') {
			return $value;
		}

		$fallbacks = [
			'pmpro_sfirstname' => (string) $user->first_name,
			'pmpro_slastname' => (string) $user->last_name,
			'pmpro_saddress1' => (string) get_user_meta($user->ID, 'baddress1', true),
			'pmpro_saddress2' => (string) get_user_meta($user->ID, 'baddress2', true),
			'pmpro_scity' => (string) get_user_meta($user->ID, 'bcity', true),
			'pmpro_sstate' => (string) get_user_meta($user->ID, 'bstate', true),
			'pmpro_szipcode' => (string) get_user_meta($user->ID, 'bzipcode', true),
			'pmpro_scountry' => (string) get_user_meta($user->ID, 'bcountry', true),
			'pmpro_sphone' => (string) get_user_meta($user->ID, 'bphone', true),
		];

		return $fallbacks[$meta_key] ?? '';
	}

	private function get_membership_discount_catalog() {
		return [
			'student' => [
				'label' => 'Student Discount',
				'description' => 'Eligible student rate',
				'badge' => '35% off membership',
				'rate' => 0.35,
				'code' => 'STUDENT',
				'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="m2 9 10-5 10 5-10 5-10-5Z"/><path d="M6 11.5v4.5c0 .8 2.7 3 6 3s6-2.2 6-3v-4.5"/><path d="M22 9v6"/></svg>',
			],
			'military' => [
				'label' => 'Military Discount',
				'description' => 'Eligible military rate',
				'badge' => '35% off membership',
				'rate' => 0.35,
				'code' => 'USMILITARY',
				'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4v16"/><path d="M4 5c3-2 6 2 9 0s6 2 7 0v8c-1 2-4-2-7 0s-6-2-9 0"/></svg>',
			],
		];
	}

	private function get_membership_discount_code($type) {
		$type = $this->normalize_membership_discount_type($type);
		if ($type === '') {
			return '';
		}

		$catalog = $this->get_membership_discount_catalog();
		return !empty($catalog[$type]['code']) ? strtoupper(sanitize_text_field((string) $catalog[$type]['code'])) : '';
	}

	private function get_partner_only_membership_discount_codes() {
		$codes = [];
		foreach (array_keys($this->get_membership_discount_catalog()) as $type) {
			$code = $this->get_membership_discount_code($type);
			if ($code !== '') {
				$codes[] = $code;
			}
		}

		return array_values(array_unique($codes));
	}

	private function get_uppercase_request_value($key) {
		if (!isset($_REQUEST[$key])) {
			return '';
		}

		$value = wp_unslash($_REQUEST[$key]);
		if (!is_scalar($value)) {
			return '';
		}

		return strtoupper(sanitize_text_field((string) $value));
	}

	private function clear_membership_discount_type($user_id) {
		delete_user_meta((int) $user_id, 'aac_membership_discount_type');
	}

	private function has_partner_family_request() {
		if ($this->is_international_checkout_request()) {
			return false;
		}

		return isset($_REQUEST['aac_partner_family_present']) && wp_unslash($_REQUEST['aac_partner_family_present']) === '1';
	}

	private function is_checkout_autorenew_disabled_request() {
		if (!isset($_REQUEST['autorenew_present'])) {
			return false;
		}

		return empty($_REQUEST['autorenew']);
	}

	private function get_requested_membership_discount_amount($base_amount, $level, $partner_family_config = null) {
		$base_amount = max(0, (float) $base_amount);
		if ($base_amount <= 0 || !$this->has_membership_discount_request() || !$this->supports_discount_tiers($level)) {
			return 0.0;
		}

		$partner_family_config = is_array($partner_family_config)
			? $this->normalize_partner_family_config($partner_family_config)
			: ($this->has_partner_family_request() ? $this->get_requested_partner_family_config() : $this->normalize_partner_family_config([]));
		if (($partner_family_config['mode'] ?? '') === 'family') {
			return 0.0;
		}

		$discount_type = $this->get_requested_membership_discount_type();
		$catalog = $this->get_membership_discount_catalog();
		$rate = isset($catalog[$discount_type]['rate']) ? (float) $catalog[$discount_type]['rate'] : 0.0;
		if ($rate <= 0 || $rate >= 1) {
			return 0.0;
		}

		$configured_base_amount = $this->get_aac_membership_level_base_total($level);
		if ($configured_base_amount !== null && $base_amount < ((float) $configured_base_amount - 0.01)) {
			return 0.0;
		}

		return round($base_amount * $rate, 2);
	}

	private function is_add_dependent_checkout_request() {
		$flag = isset($_REQUEST['aac_add_dependent']) ? sanitize_text_field(wp_unslash($_REQUEST['aac_add_dependent'])) : '';
		return $flag === '1';
	}

	private function get_requested_partner_family_config() {
		return $this->normalize_partner_family_config([
			'mode' => isset($_REQUEST['aac_partner_family_mode']) ? wp_unslash($_REQUEST['aac_partner_family_mode']) : '',
			'additional_adult' => !empty($_REQUEST['aac_partner_family_additional_adult']),
			'dependent_count' => isset($_REQUEST['aac_partner_family_dependents']) ? wp_unslash($_REQUEST['aac_partner_family_dependents']) : 0,
		]);
	}

	private function get_effective_partner_family_config($user_id = 0) {
		if ($this->has_partner_family_request()) {
			return $this->get_requested_partner_family_config();
		}

		if ($this->is_add_dependent_checkout_request()) {
			$context = $this->get_add_dependent_checkout_context($this->get_level_at_checkout(), $user_id);
			if ($context && !empty($context['next_family_config'])) {
				return $this->normalize_partner_family_config($context['next_family_config']);
			}
		}

		if (!$user_id) {
			return $this->normalize_partner_family_config([]);
		}

		return $this->normalize_partner_family_config(get_user_meta($user_id, 'aac_partner_family_config', true));
	}

	private function normalize_partner_family_config($config) {
		$config = is_array($config) ? $config : [];
		$mode = sanitize_key((string) ($config['mode'] ?? ''));
		$mode = $mode === 'family' ? 'family' : '';
		$additional_adult = !empty($config['additional_adult']) && $mode === 'family';
		$dependent_count = max(0, min(3, (int) ($config['dependent_count'] ?? 0)));

		if ($mode !== 'family') {
			$additional_adult = false;
			$dependent_count = 0;
		}

		return [
			'mode' => $mode,
			'additional_adult' => $additional_adult,
			'dependent_count' => $dependent_count,
		];
	}

	private function get_partner_level_id() {
		return $this->get_level_id_by_name('Partner', 3);
	}

	private function get_partner_north_america_level_id() {
		return $this->get_level_id_by_name('Partner North America', 0);
	}

	private function get_partner_international_level_id() {
		return $this->get_level_id_by_name('Partner International', 0);
	}

	private function get_partner_country_level_id($country) {
		$normalized_country = $this->normalize_country_code($country);
		if ($normalized_country === 'US') {
			return $this->get_partner_level_id();
		}

		if (in_array($normalized_country, ['CA', 'MX'], true)) {
			$north_america_level_id = $this->get_partner_north_america_level_id();
			return $north_america_level_id > 0 ? $north_america_level_id : $this->get_partner_level_id();
		}

		$international_level_id = $this->get_partner_international_level_id();
		return $international_level_id > 0 ? $international_level_id : $this->get_partner_level_id();
	}

	private function get_partner_country_routed_level_ids() {
		return array_values(array_filter(array_unique([
			$this->get_partner_level_id(),
			$this->get_partner_north_america_level_id(),
			$this->get_partner_international_level_id(),
		])));
	}

	private function is_partner_country_routed_level($level) {
		$level_id = 0;
		$level_name = '';

		if (is_object($level)) {
			$level_id = isset($level->id) ? (int) $level->id : 0;
			$level_name = isset($level->name) ? sanitize_text_field((string) $level->name) : '';
		} else {
			$level_id = (int) $level;
			if ($level_id > 0 && function_exists('pmpro_getLevel')) {
				$level_object = pmpro_getLevel($level_id);
				if (is_object($level_object) && isset($level_object->name)) {
					$level_name = sanitize_text_field((string) $level_object->name);
				}
			}
		}

		$normalized_name = strtolower(trim($level_name));
		if ($normalized_name !== '') {
			return in_array($normalized_name, ['partner', 'partner north america', 'partner international'], true);
		}

		return $level_id > 0 && in_array($level_id, $this->get_partner_country_routed_level_ids(), true);
	}

	private function get_country_routed_partner_level_for_checkout($level) {
		$requested_country = $this->get_checkout_request_value(['pmpro_scountry', 'scountry', 'bcountry']);
		if ($requested_country === '' || !$this->is_partner_country_routed_level($level)) {
			return null;
		}

		$target_level_id = $this->get_partner_country_level_id($requested_country);
		if ($target_level_id <= 0 || !function_exists('pmpro_getLevel')) {
			return null;
		}

		$target_level = pmpro_getLevel($target_level_id);
		return is_object($target_level) ? $target_level : null;
	}

	private function get_partner_family_level_id() {
		return $this->get_level_id_by_name('Partner Family', 6);
	}

	private function get_membership_level_ids() {
		return [
			'Free' => $this->get_level_id_by_name('Free', 1),
			'Supporter' => $this->get_level_id_by_name('Supporter', 2),
			'Partner' => $this->get_level_id_by_name('Partner', 3),
			'Leader' => $this->get_level_id_by_name('Leader', 4),
			'Advocate' => $this->get_level_id_by_name('Advocate', 5),
		];
	}

	private function get_pmpro_page_url($page, $fallback) {
		if (AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url')) {
			$url = pmpro_url($page);
			if (is_string($url) && $url !== '') {
				// The signup app embeds PMPro checkout. A checkout page accidentally
				// assigned to /signup would therefore iframe itself until the tab runs
				// out of memory. Never expose a portal mount page as the checkout URL.
				if ($page === 'checkout') {
					$url_path = untrailingslashit((string) wp_parse_url($url, PHP_URL_PATH));
					$unsafe_checkout_paths = [
						untrailingslashit((string) wp_parse_url(home_url('/signup/'), PHP_URL_PATH)),
						untrailingslashit((string) wp_parse_url(home_url('/member-profile/'), PHP_URL_PATH)),
						untrailingslashit((string) wp_parse_url(home_url('/membership/'), PHP_URL_PATH)),
					];
					if (in_array($url_path, array_filter($unsafe_checkout_paths), true)) {
						return home_url($fallback);
					}
				}

				if ($page !== 'account') {
					$account_url = pmpro_url('account');
					$url_path = untrailingslashit((string) wp_parse_url($url, PHP_URL_PATH));
					$account_path = is_string($account_url) ? untrailingslashit((string) wp_parse_url($account_url, PHP_URL_PATH)) : '';
					if ($account_path && $url_path === $account_path) {
						return home_url($fallback);
					}
					if ($page === 'cancel' && $url_path === untrailingslashit('/membership-levels')) {
						return home_url($fallback);
					}
				}

				return $url;
			}
		}

		return home_url($fallback);
	}

	public function render_pmpro_account_fallback($user_id = 0, $primary_membership = null, $membership_actions = []) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$user = $user_id ? get_userdata($user_id) : null;
		if (!$user instanceof WP_User) {
			return '';
		}

		$primary_membership = is_array($primary_membership) ? $primary_membership : AAC_Member_Portal_PMPro::get_primary_membership($user_id);
		$membership_actions = is_array($membership_actions) ? $membership_actions : [];
		$tier = $primary_membership['tier'] ?? 'Membership';
		$renewal_date = $primary_membership['renewal_date'] ?? '';
		$expiration_date = $primary_membership['expiration_date'] ?? '';
		$transactions = AAC_Member_Portal_PMPro::get_membership_transactions($user_id);
		$portal_url = untrailingslashit($this->get_portal_page_url());
		$billing_url = !empty($membership_actions['billing_url']) ? $membership_actions['billing_url'] : $this->get_pmpro_page_url('billing', '/membership-account/membership-billing/');
		$cancel_url = !empty($membership_actions['cancel_url']) ? $membership_actions['cancel_url'] : $this->get_pmpro_page_url('cancel', '/membership-account/membership-cancel/');
		$change_membership_url = $portal_url . '/#/membership';
		$pmpro_account_url = $this->get_pmpro_page_url('account', '/membership-account/');
		if (untrailingslashit((string) wp_parse_url($billing_url, PHP_URL_PATH)) === untrailingslashit((string) wp_parse_url($pmpro_account_url, PHP_URL_PATH))) {
			$billing_url = $this->get_pmpro_page_url('billing', '/membership-account/membership-billing/');
		}
		$pending_downgrade = is_array($membership_actions['pending_downgrade'] ?? null) ? $membership_actions['pending_downgrade'] : null;
		$is_active = $this->has_active_membership_term($primary_membership);

		ob_start();
		?>
		<div class="pmpro aac-pmpro-account-fallback">
			<section id="pmpro_account-profile" class="pmpro_section">
				<h2 class="pmpro_section_title pmpro_font-x-large"><?php esc_html_e('My Account', 'aac-member-portal'); ?></h2>
				<div class="pmpro_card">
					<h3 class="pmpro_card_title pmpro_font-large"><?php echo esc_html($user->display_name ?: $user->user_login); ?></h3>
					<div class="pmpro_card_content">
						<ul class="pmpro_list pmpro_list-plain">
							<li><strong><?php esc_html_e('Username:', 'aac-member-portal'); ?></strong> <?php echo esc_html($user->user_login); ?></li>
							<li><strong><?php esc_html_e('Email:', 'aac-member-portal'); ?></strong> <?php echo esc_html($user->user_email); ?></li>
						</ul>
					</div>
					<div class="pmpro_card_actions">
						<a class="pmpro_card_action" href="<?php echo esc_url($portal_url . '/#/account'); ?>"><?php esc_html_e('Edit Profile', 'aac-member-portal'); ?></a>
						<span class="pmpro_card_action_separator">|</span>
						<a class="pmpro_card_action" href="<?php echo esc_url($portal_url . '/#/change-password'); ?>"><?php esc_html_e('Change Password', 'aac-member-portal'); ?></a>
						<span class="pmpro_card_action_separator">|</span>
						<a class="pmpro_card_action" href="<?php echo esc_url(wp_logout_url($portal_url . '/#/login')); ?>"><?php esc_html_e('Log Out', 'aac-member-portal'); ?></a>
					</div>
				</div>
			</section>

			<section id="pmpro_account-membership" class="pmpro_section">
				<h2 class="pmpro_section_title pmpro_font-x-large"><?php esc_html_e('My Memberships', 'aac-member-portal'); ?></h2>
				<div class="pmpro_card">
					<h3 class="pmpro_card_title pmpro_font-large"><?php echo esc_html($tier); ?></h3>
					<div class="pmpro_card_content">
						<?php if ($pending_downgrade) : ?>
							<p class="pmpro_message pmpro_alert">
								<?php
								printf(
									/* translators: 1: target tier, 2: effective date. */
									esc_html__('A downgrade to %1$s is scheduled for %2$s. Your current membership remains active through the current term.', 'aac-member-portal'),
									esc_html($pending_downgrade['target_tier'] ?? __('the selected level', 'aac-member-portal')),
									esc_html($this->format_pmpro_display_date($pending_downgrade['effective_date'] ?? ''))
								);
								?>
							</p>
						<?php endif; ?>
						<ul class="pmpro_list pmpro_list-plain pmpro_list-with-labels pmpro_cols-3">
							<li><strong><?php esc_html_e('Renewal Date', 'aac-member-portal'); ?></strong> <?php echo esc_html($renewal_date ? date_i18n(get_option('date_format'), strtotime($renewal_date)) : __('Not scheduled', 'aac-member-portal')); ?></li>
							<li><strong><?php esc_html_e('Expiration Date', 'aac-member-portal'); ?></strong> <?php echo esc_html($expiration_date ? date_i18n(get_option('date_format'), strtotime($expiration_date)) : __('Not scheduled', 'aac-member-portal')); ?></li>
							<li><strong><?php esc_html_e('Status', 'aac-member-portal'); ?></strong> <?php echo esc_html($is_active ? __('Active', 'aac-member-portal') : __('Expired', 'aac-member-portal')); ?></li>
						</ul>
					</div>
					<div class="pmpro_card_actions">
						<a class="pmpro_card_action" href="<?php echo esc_url($change_membership_url); ?>"><?php esc_html_e('Change Membership', 'aac-member-portal'); ?></a>
						<span class="pmpro_card_action_separator">|</span>
						<a class="pmpro_card_action" href="<?php echo esc_url($billing_url); ?>"><?php esc_html_e('Billing', 'aac-member-portal'); ?></a>
						<span class="pmpro_card_action_separator">|</span>
						<a class="pmpro_card_action" href="<?php echo esc_url($cancel_url); ?>"><?php esc_html_e('Cancel', 'aac-member-portal'); ?></a>
					</div>
				</div>
			</section>

			<section id="pmpro_account-orders" class="pmpro_section">
				<h2 class="pmpro_section_title pmpro_font-x-large"><?php esc_html_e('Order History', 'aac-member-portal'); ?></h2>
				<div class="pmpro_card">
					<div class="pmpro_card_content">
						<?php if (!empty($transactions)) : ?>
							<table class="pmpro_table pmpro_table_orders">
								<thead>
									<tr>
										<th><?php esc_html_e('Date', 'aac-member-portal'); ?></th>
										<th><?php esc_html_e('Description', 'aac-member-portal'); ?></th>
										<th><?php esc_html_e('Total', 'aac-member-portal'); ?></th>
										<th><?php esc_html_e('Status', 'aac-member-portal'); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach (array_slice($transactions, 0, 10) as $transaction) : ?>
										<tr>
											<td><?php echo esc_html(!empty($transaction['createdAt']) ? date_i18n(get_option('date_format'), strtotime($transaction['createdAt'])) : ''); ?></td>
											<td><?php echo esc_html($transaction['description'] ?? __('Membership payment', 'aac-member-portal')); ?></td>
											<td><?php echo esc_html(function_exists('pmpro_formatPrice') ? pmpro_formatPrice((float) ($transaction['amount'] ?? 0)) : '$' . number_format((float) ($transaction['amount'] ?? 0), 2)); ?></td>
											<td><?php echo esc_html($transaction['status'] ?? ''); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php else : ?>
							<p class="pmpro_message"><?php esc_html_e('No membership orders are available yet.', 'aac-member-portal'); ?></p>
						<?php endif; ?>
					</div>
				</div>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	public function render_managed_pmpro_content($content, $context = []) {
		$content = (string) $content;
		$user_id = !empty($context['user_id']) ? absint($context['user_id']) : get_current_user_id();
		$primary_membership = is_array($context['primary_membership'] ?? null) ? $context['primary_membership'] : AAC_Member_Portal_PMPro::get_primary_membership($user_id);
		$membership_actions = is_array($context['membership_actions'] ?? null) ? $context['membership_actions'] : [];

		if (!empty($context['is_confirmation_page'])) {
			return $this->render_pmpro_confirmation_fallback($user_id);
		}

		if (!empty($context['is_cancel_page']) && (!$this->is_pmpro_cancel_review_request() || $this->should_replace_pmpro_cancel_content($content))) {
			return $this->render_pmpro_cancel_fallback($user_id, $primary_membership, $membership_actions);
		}

		if (!empty($context['is_billing_page'])) {
			if ($this->should_replace_pmpro_billing_content($content)) {
				return $this->render_pmpro_billing_fallback($user_id, $primary_membership, $membership_actions);
			}

			return $content;
		}

		if (!empty($context['is_orders_page'])) {
			return $this->render_pmpro_account_fallback($user_id, $primary_membership, $membership_actions);
		}

		if (!empty($context['is_account_page'])) {
			if ($this->should_replace_pmpro_account_content($content, $primary_membership)) {
				return $this->render_pmpro_account_fallback($user_id, $primary_membership, $membership_actions);
			}

			return $this->rewrite_pmpro_account_action_links($content, $membership_actions);
		}

		return $content;
	}

	private function rewrite_pmpro_account_action_links($content, $membership_actions = []) {
		$content = (string) $content;
		if ($content === '') {
			return $content;
		}

		$membership_actions = is_array($membership_actions) ? $membership_actions : [];
		$billing_url = !empty($membership_actions['billing_url'])
			? (string) $membership_actions['billing_url']
			: $this->get_pmpro_page_url('billing', '/membership-account/membership-billing/');
		$account_url = $this->get_portal_manage_membership_url();
		if (untrailingslashit((string) wp_parse_url($billing_url, PHP_URL_PATH)) === untrailingslashit((string) wp_parse_url($account_url, PHP_URL_PATH))) {
			$billing_url = $this->get_pmpro_page_url('billing', '/membership-account/membership-billing/');
		}

		if (!$billing_url) {
			return $content;
		}

		return preg_replace_callback(
			'/<a\b([^>]*)>(.*?)<\/a>/is',
			static function ($matches) use ($billing_url) {
				$attributes = (string) ($matches[1] ?? '');
				$link_text = strtolower(trim(wp_strip_all_tags((string) ($matches[2] ?? ''))));
				if (strpos($link_text, 'update billing') === false && strpos($link_text, 'billing information') === false) {
					return $matches[0];
				}

				$attributes = preg_replace('/\s+href=(["\']).*?\1/i', '', $attributes);
				return '<a href="' . esc_url($billing_url) . '"' . $attributes . '>' . $matches[2] . '</a>';
			},
			$content
		);
	}

	private function should_replace_pmpro_account_content($content, $primary_membership = null) {
		$content = (string) $content;
		if (strpos($content, 'pmpro_account-profile') === false) {
			return true;
		}

		$plain_text = strtolower(wp_strip_all_tags($content));
		return $this->has_active_membership_term($primary_membership) && strpos($plain_text, 'you do not have an active membership') !== false;
	}

	private function should_replace_pmpro_billing_content($content) {
		$content = (string) $content;
		$plain_text = strtolower(wp_strip_all_tags($content));
		if (strpos($plain_text, 'you do not have an active membership') !== false) {
			return true;
		}

		$has_billing_marker = strpos($content, 'pmpro_billing') !== false
			|| strpos($content, 'pmpro_payment') !== false
			|| (strpos($content, 'pmpro_form') !== false && (strpos($plain_text, 'update billing') !== false || strpos($plain_text, 'billing information') !== false));

		return !$has_billing_marker || strpos($content, 'pmpro_account-profile') !== false;
	}

	private function should_replace_pmpro_confirmation_content($content) {
		$content = (string) $content;
		$plain_text = strtolower(wp_strip_all_tags($content));
		$has_receipt_marker = strpos($content, 'pmpro_invoice') !== false
			|| strpos($content, 'pmpro_confirmation') !== false
			|| strpos($plain_text, 'invoice') !== false
			|| strpos($plain_text, 'receipt') !== false;

		return !$has_receipt_marker || strpos($content, 'pmpro_account-profile') !== false;
	}

	private function should_replace_pmpro_orders_content($content) {
		$content = (string) $content;
		$plain_text = strtolower(wp_strip_all_tags($content));
		$has_order_marker = strpos($content, 'pmpro_invoice') !== false
			|| strpos($content, 'pmpro_table') !== false
			|| strpos($plain_text, 'order history') !== false
			|| strpos($plain_text, 'invoice') !== false;

		return !$has_order_marker || strpos($content, 'pmpro_account-profile') !== false;
	}

	private function should_replace_pmpro_cancel_content($content) {
		$content = (string) $content;
		$plain_text = strtolower(wp_strip_all_tags($content));
		$has_cancel_marker = strpos($content, 'pmpro_cancel') !== false
			|| strpos($plain_text, 'cancel') !== false
			|| strpos($plain_text, 'membership cancellation') !== false;

		return !$has_cancel_marker || strpos($content, 'pmpro_account-profile') !== false || strpos($plain_text, 'order history') !== false;
	}

	private function is_pmpro_cancel_review_request() {
		$request = wp_unslash($_REQUEST); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing -- read-only routing check.
		$review_keys = [
			'aac_pmpro_native_cancel',
			'levelstocancel',
			'confirm',
			'confirm_cancel',
			'cancel_membership',
		];

		foreach ($review_keys as $key) {
			if (isset($request[$key]) && $request[$key] !== '') {
				return true;
			}
		}

		return false;
	}

	public function render_pmpro_billing_fallback($user_id = 0, $primary_membership = null, $membership_actions = []) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$primary_membership = is_array($primary_membership) ? $primary_membership : AAC_Member_Portal_PMPro::get_primary_membership($user_id);
		$membership_actions = is_array($membership_actions) ? $membership_actions : [];
		$account_url = $this->get_portal_manage_membership_url();
		$payment_summary = AAC_Member_Portal_PMPro::get_payment_method_summary($user_id);
		$subscription_id = sanitize_text_field((string) ($membership_actions['current_subscription_id'] ?? ''));
		$renewal_date = is_array($primary_membership) ? ($primary_membership['renewal_date'] ?? '') : '';
		$expiration_date = is_array($primary_membership) ? ($primary_membership['expiration_date'] ?? '') : '';
		$native_billing_form = '';
		if (shortcode_exists('pmpro_billing')) {
			$native_billing_form = do_shortcode('[pmpro_billing]');
			$native_billing_plain_text = strtolower(wp_strip_all_tags($native_billing_form));
			if (
				trim($native_billing_form) === '[pmpro_billing]' ||
				strpos($native_billing_form, 'pmpro_account-profile') !== false ||
				strpos($native_billing_plain_text, 'you do not have an active membership') !== false
			) {
				$native_billing_form = '';
			}
		}

		ob_start();
		?>
		<div class="pmpro aac-pmpro-billing-fallback">
			<?php if ($native_billing_form !== '') : ?>
				<section class="pmpro_section">
					<h2 class="pmpro_section_title pmpro_font-x-large"><?php esc_html_e('Update Billing Information', 'aac-member-portal'); ?></h2>
					<?php echo $native_billing_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- PMPro shortcode output. ?>
				</section>
			<?php endif; ?>
			<section class="pmpro_section">
				<h2 class="pmpro_section_title pmpro_font-x-large"><?php esc_html_e('Billing Information', 'aac-member-portal'); ?></h2>
				<div class="pmpro_card">
					<div class="pmpro_card_content">
						<ul class="pmpro_list pmpro_list-plain pmpro_list-with-labels pmpro_cols-3">
							<li><strong><?php esc_html_e('Membership', 'aac-member-portal'); ?></strong> <?php echo esc_html(is_array($primary_membership) ? ($primary_membership['tier'] ?? __('Membership', 'aac-member-portal')) : __('Membership', 'aac-member-portal')); ?></li>
							<li><strong><?php esc_html_e('Payment Method', 'aac-member-portal'); ?></strong> <?php echo esc_html($payment_summary ?: __('Not available', 'aac-member-portal')); ?></li>
							<li><strong><?php esc_html_e('Next Billing Date', 'aac-member-portal'); ?></strong> <?php echo esc_html($this->format_pmpro_display_date($renewal_date ?: $expiration_date, __('Not scheduled', 'aac-member-portal'))); ?></li>
						</ul>
						<?php if ($subscription_id) : ?>
							<?php if ($native_billing_form !== '') : ?>
								<p class="pmpro_message"><?php esc_html_e('This membership has an active recurring subscription. Use the form above to update the payment method PMPro has on file.', 'aac-member-portal'); ?></p>
							<?php else : ?>
								<p class="pmpro_message pmpro_alert"><?php esc_html_e('PMPro did not return an update billing form for this subscription. This usually means the PMPro Billing page is missing the Billing block/shortcode, the Stripe subscription is not attached to a successful PMPro order, or the gateway cannot update this payment method from the frontend.', 'aac-member-portal'); ?></p>
							<?php endif; ?>
						<?php else : ?>
							<p class="pmpro_message"><?php esc_html_e('No active recurring billing subscription is attached to this membership. Renew or enable auto-renewal from checkout to add one.', 'aac-member-portal'); ?></p>
						<?php endif; ?>
					</div>
					<?php if ($native_billing_form === '') : ?>
						<div class="pmpro_card_actions">
							<a class="pmpro_card_action" href="<?php echo esc_url($account_url); ?>"><?php esc_html_e('Return to Account', 'aac-member-portal'); ?></a>
						</div>
					<?php endif; ?>
				</div>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	public function render_pmpro_confirmation_fallback($user_id = 0) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$transactions = AAC_Member_Portal_PMPro::get_membership_transactions($user_id);
		$latest = !empty($transactions[0]) && is_array($transactions[0]) ? $transactions[0] : null;
		$account_url = $this->get_portal_manage_membership_url();
		$order_breakdown = $latest ? $this->get_pmpro_order_breakdown_payload_from_transaction($latest, $user_id) : [];
		if (empty($order_breakdown) && $latest) {
			$order_breakdown = $this->build_pmpro_transaction_receipt_payload($latest, $user_id);
		}

		ob_start();
		?>
		<div class="pmpro aac-pmpro-confirmation-fallback">
			<section class="aac-pmpro-confirmation-fallback__section">
				<div class="aac-pmpro-confirmation-fallback__heading">
					<h2><?php esc_html_e('Most Recent Receipt', 'aac-member-portal'); ?></h2>
				</div>
				<?php if ($latest) : ?>
					<?php echo $this->render_pmpro_order_breakdown_markup($order_breakdown); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside renderer. ?>
				<?php else : ?>
					<p class="pmpro_message"><?php esc_html_e('No completed membership receipt is available yet.', 'aac-member-portal'); ?></p>
				<?php endif; ?>
				<div class="pmpro_card_actions aac-pmpro-confirmation-fallback__actions">
					<a class="pmpro_card_action" href="<?php echo esc_url($account_url); ?>"><?php esc_html_e('Back to Account', 'aac-member-portal'); ?></a>
				</div>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	public function render_pmpro_orders_fallback($user_id = 0) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$transactions = AAC_Member_Portal_PMPro::get_membership_transactions($user_id);

		ob_start();
		?>
		<div class="pmpro aac-pmpro-orders-fallback">
			<section class="pmpro_section">
				<h2 class="pmpro_section_title pmpro_font-x-large"><?php esc_html_e('Order History', 'aac-member-portal'); ?></h2>
				<div class="pmpro_card">
					<div class="pmpro_card_content">
						<?php if (!empty($transactions)) : ?>
							<table class="pmpro_table pmpro_table_orders">
								<thead>
									<tr>
										<th><?php esc_html_e('Date', 'aac-member-portal'); ?></th>
										<th><?php esc_html_e('Description', 'aac-member-portal'); ?></th>
										<th><?php esc_html_e('Total', 'aac-member-portal'); ?></th>
										<th><?php esc_html_e('Status', 'aac-member-portal'); ?></th>
										<th><?php esc_html_e('Reference', 'aac-member-portal'); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach (array_slice($transactions, 0, 25) as $transaction) : ?>
										<tr>
											<td><?php echo esc_html($this->format_pmpro_display_date($transaction['createdAt'] ?? '')); ?></td>
											<td><?php echo esc_html($transaction['description'] ?? __('Membership payment', 'aac-member-portal')); ?></td>
											<td><?php echo esc_html(function_exists('pmpro_formatPrice') ? pmpro_formatPrice((float) ($transaction['amount'] ?? 0)) : '$' . number_format((float) ($transaction['amount'] ?? 0), 2)); ?></td>
											<td><?php echo esc_html($transaction['status'] ?? ''); ?></td>
											<td><?php echo esc_html($transaction['referenceId'] ?? ''); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php else : ?>
							<p class="pmpro_message"><?php esc_html_e('No membership orders are available yet.', 'aac-member-portal'); ?></p>
						<?php endif; ?>
					</div>
				</div>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	public function render_pmpro_cancel_fallback($user_id = 0, $primary_membership = null, $membership_actions = []) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$primary_membership = is_array($primary_membership) ? $primary_membership : AAC_Member_Portal_PMPro::get_primary_membership($user_id);
		$membership_actions = is_array($membership_actions) ? $membership_actions : [];
		$cancel_url = !empty($membership_actions['cancel_url']) ? $membership_actions['cancel_url'] : $this->get_pmpro_page_url('cancel', '/membership-account/membership-cancel/');
		$current_level_id = absint($membership_actions['current_level_id'] ?? ($primary_membership['level_id'] ?? 0));
		if (untrailingslashit((string) wp_parse_url($cancel_url, PHP_URL_PATH)) === untrailingslashit('/membership-levels')) {
			$cancel_url = home_url('/membership-account/membership-cancel/');
		}
		if ($current_level_id && strpos($cancel_url, 'levelstocancel=') === false) {
			$cancel_url = add_query_arg('levelstocancel', $current_level_id, $cancel_url);
		}
		if ($current_level_id) {
			$cancel_url = add_query_arg('aac_pmpro_native_cancel', '1', $cancel_url);
		}
		$account_url = $this->get_portal_manage_membership_url();
		$tier = is_array($primary_membership) ? sanitize_text_field((string) ($primary_membership['tier'] ?? __('Membership', 'aac-member-portal'))) : __('Membership', 'aac-member-portal');
		$has_active_auto_renewal = $user_id > 0 && $current_level_id > 0 && AAC_Member_Portal_PMPro::has_active_auto_renewal($user_id, $current_level_id);
		$expiration_date = is_array($primary_membership) ? sanitize_text_field((string) (
			$primary_membership['renewal_date']
			?: ($primary_membership['valid_through_date'] ?? ($primary_membership['expiration_date'] ?? ''))
		)) : '';

		ob_start();
		?>
		<div class="pmpro aac-pmpro-cancel-fallback">
			<section class="pmpro_section">
				<h2 class="pmpro_section_title pmpro_font-x-large"><?php esc_html_e('Turn Off Automatic Renewal', 'aac-member-portal'); ?></h2>
				<div class="pmpro_card">
					<div class="pmpro_card_content">
						<ul class="pmpro_list pmpro_list-plain pmpro_list-with-labels pmpro_cols-2">
							<li><strong><?php esc_html_e('Current Membership', 'aac-member-portal'); ?></strong> <?php echo esc_html($tier); ?></li>
							<li><strong><?php esc_html_e('Access Through', 'aac-member-portal'); ?></strong> <?php echo esc_html($this->format_pmpro_display_date($expiration_date, __('Current term', 'aac-member-portal'))); ?></li>
						</ul>
						<p class="pmpro_message">
							<?php
							echo esc_html(
								$has_active_auto_renewal
									? sprintf(
										__('Your account is not being cancelled today. Turning off automatic renewal prevents future billing, while your membership remains fully active through %s. It will expire at the end of that subscription period unless you renew it.', 'aac-member-portal'),
										$this->format_pmpro_display_date($expiration_date, __('the end of your current term', 'aac-member-portal'))
									)
									: __('Automatic renewal is already off. No cancellation is needed; paid membership access remains available through the current term.', 'aac-member-portal')
							);
							?>
						</p>
					</div>
					<div class="pmpro_card_actions aac-cancel-fallback-actions">
						<a class="pmpro_card_action aac-cancel-fallback-button aac-cancel-fallback-button--return" href="<?php echo esc_url($account_url); ?>"><?php esc_html_e('Return to Account', 'aac-member-portal'); ?></a>
						<?php if ($current_level_id && $has_active_auto_renewal) : ?>
							<a class="pmpro_card_action aac-cancel-fallback-button aac-cancel-fallback-button--continue" href="<?php echo esc_url($cancel_url); ?>"><?php esc_html_e('Review Automatic Renewal', 'aac-member-portal'); ?></a>
						<?php endif; ?>
					</div>
				</div>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	private function has_active_membership_term($primary_membership) {
		if (!is_array($primary_membership)) {
			return false;
		}

		if (($primary_membership['status'] ?? '') === 'active') {
			return true;
		}

		foreach (['expiration_date', 'renewal_date', 'valid_through_date'] as $date_key) {
			$date = trim((string) ($primary_membership[$date_key] ?? ''));
			if ($date !== '' && strtotime($date . ' 23:59:59') >= current_time('timestamp')) {
				return true;
			}
		}

		return false;
	}

	private function format_pmpro_display_date($date, $fallback = '') {
		$date = trim((string) $date);
		if ($fallback === '') {
			$fallback = __('Not available', 'aac-member-portal');
		}

		if ($date === '') {
			return $fallback;
		}

		$timestamp = strtotime($date);
		if (!$timestamp) {
			return $fallback;
		}

		return date_i18n(get_option('date_format'), $timestamp);
	}

	private function get_level_id_by_name($name, $fallback = 0) {
		if (!function_exists('pmpro_getAllLevels')) {
			return (int) $fallback;
		}

		$levels = pmpro_getAllLevels(false, true);
		if (!is_array($levels)) {
			return (int) $fallback;
		}

		$normalized_name = strtolower(trim((string) $name));
		foreach ($levels as $level) {
			if (is_object($level) && !empty($level->id) && isset($level->name) && strtolower(trim((string) $level->name)) === $normalized_name) {
				return (int) $level->id;
			}
		}

		return (int) $fallback;
	}

	private function get_requested_level_id() {
		if (!isset($_REQUEST['level'])) {
			return 0;
		}

		return absint(wp_unslash($_REQUEST['level']));
	}

	private function supports_discount_tiers($level) {
		$level_id = 0;
		$level_name = '';

		if (is_object($level)) {
			$level_id = isset($level->id) ? (int) $level->id : 0;
			$level_name = isset($level->name) ? sanitize_text_field((string) $level->name) : '';
		} else {
			$level_id = (int) $level;
			if ($level_id > 0 && function_exists('pmpro_getLevel')) {
				$level_object = pmpro_getLevel($level_id);
				if (is_object($level_object) && isset($level_object->name)) {
					$level_name = sanitize_text_field((string) $level_object->name);
				}
			}
		}

		$normalized_name = strtolower(trim($level_name));
		if ($normalized_name !== '') {
			return $normalized_name === 'partner';
		}

		$partner_level_id = $this->get_partner_level_id();
		return $partner_level_id > 0 && $level_id === $partner_level_id;
	}

	private function supports_family_plan_tiers($level) {
		$level_id = 0;
		$level_name = '';

		if (is_object($level)) {
			$level_id = isset($level->id) ? (int) $level->id : 0;
			$level_name = isset($level->name) ? sanitize_text_field((string) $level->name) : '';
		} else {
			$level_id = (int) $level;
			if ($level_id > 0 && function_exists('pmpro_getLevel')) {
				$level_object = pmpro_getLevel($level_id);
				if (is_object($level_object) && isset($level_object->name)) {
					$level_name = sanitize_text_field((string) $level_object->name);
				}
			}
		}

		$normalized_name = strtolower(trim($level_name));
		if ($normalized_name !== '') {
			return $normalized_name === 'partner';
		}

		$partner_level_id = $this->get_partner_level_id();
		return $partner_level_id > 0 && $level_id === $partner_level_id;
	}

	private function is_partner_family_checkout_level($level) {
		return $this->supports_family_plan_tiers($level);
	}

	private function get_partner_family_pricing($base_membership_total) {
		return [
			'additional_adult_price' => 80.0,
			'dependent_price' => 45.0,
		];
	}

	private function get_partner_family_addon_total($base_membership_total, $family_config) {
		$family_config = $this->normalize_partner_family_config($family_config);
		if ($family_config['mode'] !== 'family') {
			return 0.0;
		}

		$pricing = $this->get_partner_family_pricing($base_membership_total);
		$total = 0.0;
		if (!empty($family_config['additional_adult'])) {
			$total += (float) $pricing['additional_adult_price'];
		}

		$total += max(0, (int) $family_config['dependent_count']) * (float) $pricing['dependent_price'];

		return round($total, 2);
	}

	private function sync_partner_family_member_slots($user_id, $family_config, $base_membership_total = 0.0) {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return;
		}

		$family_config = $this->normalize_partner_family_config($family_config);
		update_user_meta($user_id, 'aac_partner_family_config', $family_config);

		$existing_slots = get_user_meta($user_id, 'aac_connected_accounts', true);
		$existing_slots = is_array($existing_slots) ? $existing_slots : [];
		$normalized_existing = [];

		foreach ($existing_slots as $slot) {
			if (!is_array($slot)) {
				continue;
			}

			$normalized_existing[] = [
				'id' => sanitize_text_field($slot['id'] ?? wp_generate_uuid4()),
				'type' => sanitize_key($slot['type'] ?? 'dependent'),
				'label' => sanitize_text_field($slot['label'] ?? 'Family member'),
				'status' => in_array(($slot['status'] ?? ''), ['pending', 'connected', 'removal_pending'], true) ? $slot['status'] : 'pending',
				'invite_code' => sanitize_text_field($slot['invite_code'] ?? $this->generate_family_invite_code()),
				'child_user_id' => absint($slot['child_user_id'] ?? 0),
				'child_name' => sanitize_text_field($slot['child_name'] ?? ''),
				'child_email' => sanitize_email($slot['child_email'] ?? ''),
				'price' => round((float) ($slot['price'] ?? 0), 2),
				'scheduled_removal_date' => sanitize_text_field($slot['scheduled_removal_date'] ?? ''),
			];
		}
		$next_slots = [];
		$pricing = $this->get_partner_family_pricing($base_membership_total);

		if ($family_config['mode'] === 'family') {
			if (!empty($family_config['additional_adult'])) {
				$next_slots[] = $this->preserve_or_create_family_slot(
					$user_id,
					$normalized_existing,
					'adult',
					'Additional adult',
					(float) $pricing['additional_adult_price']
				);
			}

			$dependent_count = max(0, (int) $family_config['dependent_count']);
			for ($dependent_index = 1; $dependent_index <= $dependent_count; $dependent_index++) {
				$next_slots[] = $this->preserve_or_create_family_slot(
					$user_id,
					$normalized_existing,
					'dependent',
					sprintf('Dependent %d', $dependent_index),
					(float) $pricing['dependent_price']
				);
			}
		}

		foreach ($normalized_existing as $slot) {
			$scheduled_slot = $this->schedule_family_slot_for_term_end($user_id, $slot);
			if ($scheduled_slot) {
				$next_slots[] = $scheduled_slot;
			}
		}

		if (empty($next_slots)) {
			delete_user_meta($user_id, 'aac_connected_accounts');
			if (class_exists('AAC_Member_Portal_Group_Accounts')) {
				AAC_Member_Portal_Group_Accounts::sync_parent_group($user_id, []);
			}
			return;
		}

		update_user_meta($user_id, 'aac_connected_accounts', array_values($next_slots));
		if (class_exists('AAC_Member_Portal_Group_Accounts')) {
			AAC_Member_Portal_Group_Accounts::sync_parent_group($user_id, array_values($next_slots));
		}
	}

	private function preserve_or_create_family_slot($parent_user_id, &$existing_slots, $type, $label, $price) {
		$preferred_slot_index = null;
		$fallback_slot_index = null;

		foreach ($existing_slots as $index => $slot) {
			if (($slot['type'] ?? '') !== $type) {
				continue;
			}

			if (($slot['status'] ?? '') !== 'removal_pending') {
				$preferred_slot_index = $index;
				break;
			}

			if ($fallback_slot_index === null) {
				$fallback_slot_index = $index;
			}
		}

		$target_index = $preferred_slot_index !== null ? $preferred_slot_index : $fallback_slot_index;
		if ($target_index !== null) {
			$slot = $existing_slots[$target_index];
			unset($existing_slots[$target_index]);
			$slot['label'] = $label;
			$slot['price'] = round((float) $price, 2);
			if (($slot['status'] ?? '') === 'removal_pending') {
				$slot = $this->restore_scheduled_family_slot($parent_user_id, $slot);
			}
			return $slot;
		}

		return [
			'id' => wp_generate_uuid4(),
			'type' => $type,
			'label' => $label,
			'status' => 'pending',
			'invite_code' => $this->generate_family_invite_code(),
			'child_user_id' => 0,
			'child_name' => '',
			'child_email' => '',
			'price' => round((float) $price, 2),
			'scheduled_removal_date' => '',
		];
	}

	private function schedule_family_slot_for_term_end($parent_user_id, $slot) {
		if (!is_array($slot)) {
			return null;
		}

		$child_user_id = absint($slot['child_user_id'] ?? 0);
		if ($child_user_id <= 0) {
			return null;
		}

		$existing_scheduled_date = sanitize_text_field((string) ($slot['scheduled_removal_date'] ?? ''));
		if (($slot['status'] ?? '') === 'removal_pending' && $existing_scheduled_date !== '') {
			update_user_meta($child_user_id, 'aac_family_membership_access_until', $existing_scheduled_date);
			update_user_meta($child_user_id, 'aac_family_membership_pending_removal', '1');
			$slot['scheduled_removal_date'] = $existing_scheduled_date;
			return $slot;
		}

		$term_end_date = $this->get_parent_family_term_end_date($parent_user_id);
		if ($term_end_date === '') {
			return null;
		}

		update_user_meta($child_user_id, 'aac_family_membership_access_until', $term_end_date);
		update_user_meta($child_user_id, 'aac_family_membership_pending_removal', '1');

		$slot['status'] = 'removal_pending';
		$slot['scheduled_removal_date'] = $term_end_date;

		return $slot;
	}

	private function restore_scheduled_family_slot($parent_user_id, $slot) {
		if (!is_array($slot)) {
			return $slot;
		}

		$child_user_id = absint($slot['child_user_id'] ?? 0);
		if ($child_user_id > 0) {
			delete_user_meta($child_user_id, 'aac_family_membership_access_until');
			delete_user_meta($child_user_id, 'aac_family_membership_pending_removal');
			update_user_meta($child_user_id, 'aac_linked_parent_user_id', (int) $parent_user_id);
			update_user_meta($child_user_id, 'aac_family_account_role', 'Child');
		}

		$slot['status'] = $child_user_id > 0 ? 'connected' : 'pending';
		$slot['scheduled_removal_date'] = '';
		return $slot;
	}

	private function get_parent_family_term_end_date($user_id) {
		return $this->get_parent_family_month_end_term_date($user_id);
	}

	private function get_parent_family_exact_term_end_date($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !class_exists('AAC_Member_Portal_PMPro') || !AAC_Member_Portal_PMPro::is_available()) {
			return '';
		}

		$primary_membership = AAC_Member_Portal_PMPro::get_primary_membership($user_id);
		if (!is_array($primary_membership) || empty($primary_membership)) {
			return '';
		}

		$term_end_date = sanitize_text_field((string) ($primary_membership['renewal_date'] ?: $primary_membership['expiration_date']));
		if ($term_end_date === '') {
			return '';
		}

		$timestamp = strtotime($term_end_date);
		return $timestamp === false ? '' : gmdate('Y-m-d', $timestamp);
	}

	private function get_requested_checkout_level_id() {
		foreach (['level', 'pmpro_level', 'membership_level', 'membership_id'] as $key) {
			if (!isset($_REQUEST[$key])) {
				continue;
			}

			$value = wp_unslash($_REQUEST[$key]);
			if (is_array($value)) {
				continue;
			}

			$level_id = absint($value);
			if ($level_id > 0) {
				return $level_id;
			}
		}

		$checkout_level = $this->get_level_at_checkout();
		return is_object($checkout_level) && isset($checkout_level->id) ? absint($checkout_level->id) : 0;
	}

	private function is_checkout_membership_change_for_user($user_id, $level_id) {
		if (!is_array($this->checkout_membership_change_context)) {
			return false;
		}

		$context = $this->checkout_membership_change_context;
		if ((int) ($context['user_id'] ?? 0) !== (int) $user_id) {
			return false;
		}

		$from_level_id = (int) ($context['from_level_id'] ?? 0);
		$to_level_id = (int) ($context['to_level_id'] ?? 0);
		$level_id = (int) $level_id;
		if ($from_level_id <= 0 || $level_id <= 0 || $from_level_id === $level_id) {
			return false;
		}

		if ($to_level_id === $level_id) {
			return true;
		}

		if (!class_exists('AAC_Member_Portal_PMPro')) {
			return false;
		}

		$requested_rank = AAC_Member_Portal_PMPro::get_tier_rank_for_level_id($to_level_id);
		$actual_rank = AAC_Member_Portal_PMPro::get_tier_rank_for_level_id($level_id);
		return $requested_rank > 0 && $actual_rank > 0 && $requested_rank === $actual_rank;
	}

	private function get_transaction_anchored_renewal_enddate($level_id) {
		$transaction_date = is_array($this->checkout_membership_change_context)
			? sanitize_text_field((string) ($this->checkout_membership_change_context['transaction_date'] ?? ''))
			: '';
		if ($transaction_date === '') {
			$transaction_date = current_time('Y-m-d');
		}

		$base_timestamp = strtotime($transaction_date . ' 00:00:00');
		if ($base_timestamp === false) {
			return '';
		}

		$cycle_number = 1;
		$cycle_period = 'Year';
		if (function_exists('pmpro_getLevel')) {
			$level = pmpro_getLevel((int) $level_id);
			if (is_object($level)) {
				$level_cycle_number = absint($level->cycle_number ?? 0);
				$level_cycle_period = sanitize_text_field((string) ($level->cycle_period ?? ''));
				if ($level_cycle_number > 0 && $level_cycle_period !== '') {
					$cycle_number = $level_cycle_number;
					$cycle_period = $level_cycle_period;
				}
			}
		}

		$cycle_period = strtolower(trim($cycle_period));
		$cycle_period = rtrim($cycle_period, 's');
		if (!in_array($cycle_period, ['day', 'week', 'month', 'year'], true)) {
			$cycle_period = 'year';
		}

		$modifier = sprintf('+%d %s%s', $cycle_number, $cycle_period, $cycle_number === 1 ? '' : 's');
		$renewal_timestamp = strtotime($modifier, $base_timestamp);
		if ($renewal_timestamp === false) {
			return '';
		}

		return AAC_Member_Portal_PMPro::normalize_date_to_month_end(
			gmdate('Y-m-d', $renewal_timestamp),
			true
		);
	}

	private function get_parent_family_month_end_term_date($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !class_exists('AAC_Member_Portal_PMPro') || !AAC_Member_Portal_PMPro::is_available()) {
			return '';
		}

		$primary_membership = AAC_Member_Portal_PMPro::get_primary_membership($user_id);
		if (!is_array($primary_membership) || empty($primary_membership)) {
			return '';
		}

		$term_end_date = sanitize_text_field((string) ($primary_membership['renewal_date'] ?: $primary_membership['expiration_date']));
		if ($term_end_date === '') {
			return '';
		}

		return AAC_Member_Portal_PMPro::normalize_date_to_month_end($term_end_date);
	}

	private function normalize_all_pmpro_membership_enddates_to_month_end() {
		global $wpdb;

		if (!$wpdb || empty($wpdb->pmpro_memberships_users) || !class_exists('AAC_Member_Portal_PMPro')) {
			return;
		}

		$table = $wpdb->pmpro_memberships_users;
		$available_columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$available_columns = is_array($available_columns) ? array_map('strval', $available_columns) : [];
		if (!in_array('id', $available_columns, true) || !in_array('enddate', $available_columns, true)) {
			return;
		}

		$where = [
			'enddate IS NOT NULL',
			"enddate <> ''",
			"enddate <> '0000-00-00 00:00:00'",
			"enddate <> '0000-00-00'",
		];
		if (in_array('status', $available_columns, true)) {
			$where[] = "LOWER(status) IN ('active', 'cancelled')";
		}

		$rows = $wpdb->get_results(
			"SELECT id, enddate FROM {$table} WHERE " . implode(' AND ', $where),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if (!is_array($rows)) {
			return;
		}

		foreach ($rows as $row) {
			$row_id = absint($row['id'] ?? 0);
			$month_end = AAC_Member_Portal_PMPro::normalize_date_to_month_end($row['enddate'] ?? '', true);
			if ($row_id <= 0 || $month_end === '' || $month_end === (string) ($row['enddate'] ?? '')) {
				continue;
			}

			$wpdb->update($table, ['enddate' => $month_end], ['id' => $row_id], ['%s'], ['%d']);
		}
	}

	private function normalize_all_pmpro_subscription_dates_to_month_end() {
		global $wpdb;

		if (!$wpdb || empty($wpdb->pmpro_subscriptions) || !class_exists('AAC_Member_Portal_PMPro')) {
			return;
		}

		$table = $wpdb->pmpro_subscriptions;
		$available_columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$available_columns = is_array($available_columns) ? array_map('strval', $available_columns) : [];
		if (!in_array('id', $available_columns, true)) {
			return;
		}

		$date_columns = array_values(array_filter([
			'next_payment_date',
			'next_payment',
			'next_payment_datetime',
			'next_payment_at',
			'billing_next_payment',
			'billing_next_payment_date',
			'cycle_enddate',
			'enddate',
		], function ($column) use ($available_columns) {
			return in_array($column, $available_columns, true);
		}));

		foreach ($date_columns as $column) {
			$rows = $wpdb->get_results(
				"SELECT id, {$column} AS membership_date FROM {$table} WHERE {$column} IS NOT NULL AND {$column} <> '' AND {$column} <> '0000-00-00' AND {$column} <> '0000-00-00 00:00:00'", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				ARRAY_A
			);
			foreach ((array) $rows as $row) {
				$row_id = absint($row['id'] ?? 0);
				$month_end = AAC_Member_Portal_PMPro::normalize_date_to_month_end($row['membership_date'] ?? '');
				if ($row_id <= 0 || $month_end === '' || $month_end === (string) ($row['membership_date'] ?? '')) {
					continue;
				}
				$wpdb->update($table, [$column => $month_end], ['id' => $row_id], ['%s'], ['%d']);
			}
		}
	}

	private function normalize_user_pmpro_membership_enddates_to_month_end($user_id, $level_id = 0) {
		$this->update_user_pmpro_membership_enddates($user_id, $level_id);
	}

	private function set_user_pmpro_membership_enddate($user_id, $level_id, $enddate) {
		$month_end = class_exists('AAC_Member_Portal_PMPro')
			? AAC_Member_Portal_PMPro::normalize_date_to_month_end($enddate, true)
			: '';
		if ($month_end === '') {
			return;
		}

		$this->update_user_pmpro_membership_enddates($user_id, $level_id, $month_end);
	}

	private function set_user_pmpro_membership_enddate_exact($user_id, $level_id, $enddate) {
		global $wpdb;

		$user_id = (int) $user_id;
		$level_id = (int) $level_id;
		$enddate = sanitize_text_field((string) $enddate);
		if ($user_id <= 0 || $level_id <= 0 || $enddate === '' || !$wpdb || empty($wpdb->pmpro_memberships_users)) {
			return;
		}

		$timestamp = strtotime($enddate);
		if ($timestamp === false) {
			return;
		}
		$enddate = gmdate('Y-m-d', $timestamp) . ' 23:59:59';

		$table = $wpdb->pmpro_memberships_users;
		$available_columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$available_columns = is_array($available_columns) ? array_map('strval', $available_columns) : [];
		if (
			!in_array('id', $available_columns, true)
			|| !in_array('user_id', $available_columns, true)
			|| !in_array('membership_id', $available_columns, true)
			|| !in_array('enddate', $available_columns, true)
		) {
			return;
		}

		$where = [
			'user_id = %d',
			'membership_id = %d',
		];
		$params = [$user_id, $level_id];
		if (in_array('status', $available_columns, true)) {
			$where[] = "LOWER(status) = 'active'";
		}

		$query = $wpdb->prepare(
			"SELECT id
			FROM {$table}
			WHERE " . implode(' AND ', $where) . '
			ORDER BY id DESC
			LIMIT 1',
			$params
		);
		$row_id = is_string($query) && $query !== '' ? absint($wpdb->get_var($query)) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ($row_id <= 0) {
			return;
		}

		$data = [
			'enddate' => $enddate,
		];
		$formats = ['%s'];
		if (in_array('modified', $available_columns, true)) {
			$data['modified'] = current_time('mysql');
			$formats[] = '%s';
		}

		$wpdb->update($table, $data, ['id' => $row_id], $formats, ['%d']);
	}

	private function set_user_pmpro_subscription_renewal_date($user_id, $level_id, $enddate) {
		global $wpdb;

		$user_id = (int) $user_id;
		$level_id = (int) $level_id;
		$timestamp = strtotime((string) $enddate);
		if ($user_id <= 0 || $level_id <= 0 || $timestamp === false || !$wpdb || empty($wpdb->pmpro_subscriptions)) {
			return;
		}

		$table = $wpdb->pmpro_subscriptions;
		$available_columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$available_columns = is_array($available_columns) ? array_map('strval', $available_columns) : [];
		if (
			!in_array('id', $available_columns, true)
			|| !in_array('user_id', $available_columns, true)
			|| !in_array('membership_level_id', $available_columns, true)
		) {
			return;
		}

		$date_columns = array_values(array_filter([
			'next_payment_date',
			'next_payment',
			'next_payment_datetime',
			'next_payment_at',
			'billing_next_payment',
			'billing_next_payment_date',
			'cycle_enddate',
			'enddate',
		], function ($column) use ($available_columns) {
			return in_array($column, $available_columns, true);
		}));
		if (!$date_columns) {
			return;
		}

		$where = [
			'user_id = %d',
			'membership_level_id = %d',
		];
		$params = [$user_id, $level_id];
		if (in_array('status', $available_columns, true)) {
			$where[] = "LOWER(status) IN ('active', 'trialing')";
		}

		$query = $wpdb->prepare(
			"SELECT id
			FROM {$table}
			WHERE " . implode(' AND ', $where) . '
			ORDER BY id DESC
			LIMIT 1',
			$params
		);
		$row_id = is_string($query) && $query !== '' ? absint($wpdb->get_var($query)) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ($row_id <= 0) {
			return;
		}

		$renewal_date = AAC_Member_Portal_PMPro::normalize_date_to_month_end(
			gmdate('Y-m-d', $timestamp)
		);
		if ($renewal_date === '') {
			return;
		}
		$data = [];
		$formats = [];
		foreach ($date_columns as $column) {
			$data[$column] = $renewal_date;
			$formats[] = '%s';
		}
		if (in_array('modified', $available_columns, true)) {
			$data['modified'] = current_time('mysql');
			$formats[] = '%s';
		}

		$wpdb->update($table, $data, ['id' => $row_id], $formats, ['%d']);
	}

	private function restore_user_membership_row_through_term($user_id, $level_id, $enddate) {
		global $wpdb;

		$user_id = (int) $user_id;
		$level_id = (int) $level_id;
		$enddate = class_exists('AAC_Member_Portal_PMPro')
			? AAC_Member_Portal_PMPro::normalize_date_to_day_end($enddate, true)
			: '';
		if ($user_id <= 0 || $level_id <= 0 || $enddate === '' || !$wpdb || empty($wpdb->pmpro_memberships_users)) {
			return;
		}

		$table = $wpdb->pmpro_memberships_users;
		$available_columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$available_columns = is_array($available_columns) ? array_map('strval', $available_columns) : [];
		if (
			!in_array('id', $available_columns, true)
			|| !in_array('user_id', $available_columns, true)
			|| !in_array('membership_id', $available_columns, true)
			|| !in_array('enddate', $available_columns, true)
		) {
			return;
		}

		$query = $wpdb->prepare(
			"SELECT id
			FROM {$table}
			WHERE user_id = %d
				AND membership_id = %d
			ORDER BY id DESC
			LIMIT 1",
			$user_id,
			$level_id
		);
		$row_id = is_string($query) && $query !== '' ? absint($wpdb->get_var($query)) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ($row_id <= 0) {
			return;
		}

		$data = [
			'enddate' => $enddate,
		];
		$formats = ['%s'];
		if (in_array('status', $available_columns, true)) {
			$data['status'] = 'active';
			$formats[] = '%s';
		}
		if (in_array('modified', $available_columns, true)) {
			$data['modified'] = current_time('mysql');
			$formats[] = '%s';
		}

		$wpdb->update($table, $data, ['id' => $row_id], $formats, ['%d']);
		clean_user_cache($user_id);
	}

	private function update_user_pmpro_membership_enddates($user_id, $level_id = 0, $forced_enddate = '') {
		global $wpdb;

		$user_id = (int) $user_id;
		$level_id = (int) $level_id;
		if ($user_id <= 0 || !$wpdb || empty($wpdb->pmpro_memberships_users) || !class_exists('AAC_Member_Portal_PMPro')) {
			return;
		}

		$table = $wpdb->pmpro_memberships_users;
		$available_columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$available_columns = is_array($available_columns) ? array_map('strval', $available_columns) : [];
		if (!in_array('id', $available_columns, true) || !in_array('user_id', $available_columns, true) || !in_array('enddate', $available_columns, true)) {
			return;
		}

		$where = [
			'user_id = %d',
		];
		$params = [$user_id];
		if ($level_id > 0 && in_array('membership_id', $available_columns, true)) {
			$where[] = 'membership_id = %d';
			$params[] = $level_id;
		}
		if ($forced_enddate === '') {
			$where[] = 'enddate IS NOT NULL';
			$where[] = "enddate <> ''";
			$where[] = "enddate <> '0000-00-00 00:00:00'";
			$where[] = "enddate <> '0000-00-00'";
		}
		if (in_array('status', $available_columns, true)) {
			$where[] = "LOWER(status) IN ('active', 'cancelled')";
		}

		$query = $wpdb->prepare(
			"SELECT id, enddate FROM {$table} WHERE " . implode(' AND ', $where) . ' ORDER BY id DESC',
			$params
		);
		$rows = is_string($query) && $query !== '' ? $wpdb->get_results($query, ARRAY_A) : []; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if (!is_array($rows)) {
			return;
		}

		foreach ($rows as $row) {
			$row_id = absint($row['id'] ?? 0);
			if ($row_id <= 0) {
				continue;
			}

			$month_end = $forced_enddate !== ''
				? AAC_Member_Portal_PMPro::normalize_date_to_month_end($forced_enddate, true)
				: AAC_Member_Portal_PMPro::normalize_date_to_month_end($row['enddate'] ?? '', true);
			if ($month_end === '' || $month_end === (string) ($row['enddate'] ?? '')) {
				continue;
			}

			$wpdb->update($table, ['enddate' => $month_end], ['id' => $row_id], ['%s'], ['%d']);
		}
	}

	private function sync_all_family_child_month_end_dates() {
		$parent_user_ids = get_users([
			'meta_key' => 'aac_connected_accounts',
			'fields' => 'ids',
			'number' => -1,
			'count_total' => false,
		]);

		foreach ((array) $parent_user_ids as $parent_user_id) {
			$this->sync_family_child_month_end_dates((int) $parent_user_id);
		}
	}

	private function sync_family_child_month_end_dates($parent_user_id, $preserve_exact_parent_date = false) {
		$parent_user_id = (int) $parent_user_id;
		if ($parent_user_id <= 0) {
			return;
		}

		$parent_enddate = $preserve_exact_parent_date
			? $this->get_parent_family_exact_term_end_date($parent_user_id)
			: $this->get_parent_family_month_end_term_date($parent_user_id);
		if ($parent_enddate === '') {
			return;
		}

		$accounts = get_user_meta($parent_user_id, 'aac_connected_accounts', true);
		if (!is_array($accounts)) {
			return;
		}

		foreach ($accounts as $slot) {
			if (!is_array($slot)) {
				continue;
			}

			$child_user_id = absint($slot['child_user_id'] ?? 0);
			if ($child_user_id <= 0) {
				continue;
			}

			$child_level_id = $this->get_child_level_id_for_family_slot($slot);
			if ($child_level_id <= 0) {
				continue;
			}

			if ($preserve_exact_parent_date) {
				$this->set_user_pmpro_membership_enddate_exact($child_user_id, $child_level_id, $parent_enddate . ' 23:59:59');
			} else {
				$this->set_user_pmpro_membership_enddate($child_user_id, $child_level_id, $parent_enddate . ' 23:59:59');
			}
			if (($slot['status'] ?? '') === 'removal_pending' || get_user_meta($child_user_id, 'aac_family_membership_pending_removal', true) === '1') {
				update_user_meta($child_user_id, 'aac_family_membership_access_until', $parent_enddate);
			}
		}
	}

	private function get_family_slot_for_child($parent_user_id, $child_user_id) {
		$accounts = get_user_meta((int) $parent_user_id, 'aac_connected_accounts', true);
		if (!is_array($accounts)) {
			return [];
		}

		$slot_id = sanitize_text_field((string) get_user_meta((int) $child_user_id, 'aac_linked_account_slot_id', true));
		foreach ($accounts as $slot) {
			if (!is_array($slot)) {
				continue;
			}
			if ($slot_id !== '' && sanitize_text_field((string) ($slot['id'] ?? '')) === $slot_id) {
				return $slot;
			}
			if (absint($slot['child_user_id'] ?? 0) === (int) $child_user_id) {
				return $slot;
			}
		}

		return [];
	}

	private function get_child_level_id_for_family_slot($slot) {
		$type = is_array($slot) ? sanitize_key((string) ($slot['type'] ?? 'dependent')) : 'dependent';
		$target_level_name = $type === 'adult' ? 'Partner Adult' : 'Partner Dependent';
		if (class_exists('AAC_Member_Portal_PMPro')) {
			$level = AAC_Member_Portal_PMPro::find_level_by_tier($target_level_name);
			if (is_object($level) && !empty($level->id)) {
				return (int) $level->id;
			}
		}

		if (function_exists('pmpro_getAllLevels')) {
			foreach ((array) pmpro_getAllLevels(true, true) as $level) {
				if (!empty($level->name) && strcasecmp(trim((string) $level->name), $target_level_name) === 0) {
					return (int) $level->id;
				}
			}
		}

		return 0;
	}

	private function generate_family_invite_code() {
		return 'AACF-' . strtoupper(wp_generate_password(8, false, false));
	}

	private function get_magazine_addon_catalog() {
		return [
			'alpinist' => [
				'label' => 'Alpinist magazine',
				'description' => 'Annual subscription add-on',
				'cover_image_url' => 'https://files.coverscdn.com/covers/289691/extralow/0000.jpg',
				'price' => 45.0,
			],
			'backcountry' => [
				'label' => 'Backcountry magazine',
				'description' => 'Annual subscription add-on',
				'cover_image_url' => 'https://files.coverscdn.com/covers/290430/extralow/0000.jpg',
				'price' => 30.0,
			],
		];
	}

	private function has_magazine_addon_request() {
		return isset($_REQUEST['aac_magazine_addons_present']) && wp_unslash($_REQUEST['aac_magazine_addons_present']) === '1';
	}

	private function has_membership_discount_request() {
		if ($this->is_international_checkout_request()) {
			return false;
		}

		if (isset($_REQUEST['aac_membership_discount_present']) && wp_unslash($_REQUEST['aac_membership_discount_present']) === '1') {
			return true;
		}

		if ($this->get_requested_membership_discount_type() !== '') {
			return true;
		}

		$partner_only_codes = $this->get_partner_only_membership_discount_codes();
		foreach (['discount_code', 'pmpro_discount_code', 'other_discount_code'] as $request_key) {
			$requested_code = $this->get_uppercase_request_value($request_key);
			if ($requested_code !== '' && in_array($requested_code, $partner_only_codes, true)) {
				return true;
			}
		}

		return false;
	}

	private function get_requested_membership_discount_type() {
		if (isset($_REQUEST['aac_membership_discount'])) {
			$type = $this->normalize_membership_discount_type(wp_unslash($_REQUEST['aac_membership_discount']));
			if ($type !== '') {
				return $type;
			}
		}

		$requested_codes = [];
		foreach (['discount_code', 'pmpro_discount_code', 'other_discount_code'] as $request_key) {
			$requested_code = $this->get_uppercase_request_value($request_key);
			if ($requested_code !== '') {
				$requested_codes[] = $requested_code;
			}
		}

		if (empty($requested_codes)) {
			return '';
		}

		foreach (array_keys($this->get_membership_discount_catalog()) as $type) {
			if (in_array($this->get_membership_discount_code($type), $requested_codes, true)) {
				return $type;
			}
		}

		return '';
	}

	private function get_requested_student_university() {
		foreach (['university_or_school', 'student_university', 'school_university', 'school_or_university', 'university_school'] as $request_key) {
			if (isset($_REQUEST[$request_key])) {
				$value = sanitize_text_field(wp_unslash($_REQUEST[$request_key]));
				if ($value !== '') {
					return $value;
				}
			}
		}

		return '';
	}

	private function get_requested_student_university_id() {
		if (!isset($_REQUEST['student_university_id'])) {
			return '';
		}

		return sanitize_text_field(wp_unslash($_REQUEST['student_university_id']));
	}

	private function get_requested_graduation_date() {
		foreach (['graduation_date', 'student_graduation_date'] as $request_key) {
			if (!isset($_REQUEST[$request_key])) {
				continue;
			}

			$value = sanitize_text_field(wp_unslash($_REQUEST[$request_key]));
			if ($value !== '') {
				return $value;
			}
		}

		return '';
	}

	private function get_requested_service_component() {
		foreach (['service_component', 'service_branch', 'military_service_component'] as $request_key) {
			if (!isset($_REQUEST[$request_key])) {
				continue;
			}

			$value = sanitize_text_field(wp_unslash($_REQUEST[$request_key]));
			if ($value !== '') {
				return $value;
			}
		}

		return '';
	}

	private function should_require_student_university_for_checkout() {
		$checkout_level = $this->get_level_at_checkout();
		if (!$this->supports_discount_tiers($checkout_level)) {
			return false;
		}

		if ($this->get_requested_membership_discount_type() !== 'student') {
			return false;
		}

		$account_info = $this->get_checkout_account_info_from_request(is_user_logged_in() ? wp_get_current_user() : null);
		if ($this->is_international_country($account_info['country'] ?? 'US')) {
			return false;
		}

		$partner_family_config = $this->has_partner_family_request()
			? $this->get_requested_partner_family_config()
			: $this->normalize_partner_family_config([]);

		return ($partner_family_config['mode'] ?? '') !== 'family';
	}

	private function get_effective_membership_discount_type($user_id = 0) {
		if ($this->has_membership_discount_request()) {
			return $this->get_requested_membership_discount_type();
		}

		if (!$user_id) {
			return '';
		}

		$discount_type = $this->normalize_membership_discount_type(get_user_meta($user_id, 'aac_membership_discount_type', true));
		if ($discount_type === '') {
			return '';
		}

		$primary_membership = AAC_Member_Portal_PMPro::get_primary_membership($user_id);
		if (!is_array($primary_membership) || !$this->supports_discount_tiers((int) ($primary_membership['level_id'] ?? 0))) {
			return '';
		}

		return $discount_type;
	}

	private function normalize_membership_discount_type($value) {
		$type = sanitize_key((string) $value);
		return array_key_exists($type, $this->get_membership_discount_catalog()) ? $type : '';
	}

	private function get_requested_magazine_addons() {
		if (!isset($_REQUEST['aac_magazine_addons'])) {
			return [];
		}

		return $this->normalize_magazine_addon_selection(wp_unslash($_REQUEST['aac_magazine_addons']));
	}

	private function get_effective_magazine_addon_selection($user_id = 0) {
		if ($this->has_magazine_addon_request()) {
			return $this->get_requested_magazine_addons();
		}

		if (!$user_id) {
			return [];
		}

		$stored = get_user_meta($user_id, 'aac_magazine_addons', true);
		return $this->normalize_magazine_addon_selection($stored);
	}

	private function normalize_magazine_addon_selection($selection) {
		$catalog = $this->get_magazine_addon_catalog();
		$allowed = array_keys($catalog);
		$raw_values = is_array($selection) ? $selection : [$selection];
		$normalized = [];

		foreach ($raw_values as $value) {
			$slug = sanitize_key((string) $value);
			if ($slug !== '' && in_array($slug, $allowed, true)) {
				$normalized[] = $slug;
			}
		}

		return array_values(array_unique($normalized));
	}

	private function get_magazine_addon_total($selection) {
		$catalog = $this->get_magazine_addon_catalog();
		$total = 0.0;

		foreach ($this->normalize_magazine_addon_selection($selection) as $slug) {
			$total += isset($catalog[$slug]['price']) ? (float) $catalog[$slug]['price'] : 0.0;
		}

		return round($total, 2);
	}

	private function get_requested_donation_amount() {
		if (!isset($_REQUEST['donation'])) {
			return 0.0;
		}

		return $this->normalize_whole_dollar_amount(wp_unslash($_REQUEST['donation']));
	}

	private function get_checkout_account_info_from_request($user = null) {
		$user = $user instanceof WP_User && $user->exists() ? $user : null;
		$account_info = $this->get_account_info_defaults_for_user($user);

		$request_field_map = [
			'first_name' => ['first_name', 'pmpro_sfirstname', 'bfirstname'],
			'last_name' => ['last_name', 'pmpro_slastname', 'blastname'],
			'email' => ['bemail', 'user_email', 'email'],
			'phone' => ['pmpro_sphone', 'bphone', 'phone'],
			'birthdate' => 'birthdate',
			'street' => ['pmpro_saddress1', 'saddress1', 'baddress1'],
			'address2' => ['pmpro_saddress2', 'saddress2', 'baddress2'],
			'city' => ['pmpro_scity', 'scity', 'bcity'],
			'state' => ['pmpro_sstate', 'sstate', 'bstate'],
			'zip' => ['pmpro_szipcode', 'szipcode', 'bzipcode'],
			'country' => ['pmpro_scountry', 'scountry', 'bcountry'],
		];

		foreach ($request_field_map as $account_key => $request_keys) {
			$request_keys = (array) $request_keys;
			$request_key = '';
			foreach ($request_keys as $candidate_key) {
				if (isset($_REQUEST[$candidate_key])) {
					$request_key = $candidate_key;
					break;
				}
			}

			if ($request_key === '') {
				continue;
			}

			$value = wp_unslash($_REQUEST[$request_key]);
			if ($account_key === 'email') {
				$value = sanitize_email($value);
			} elseif ($account_key === 'birthdate') {
				$value = $this->sanitize_birthdate_value($value);
			} else {
				$value = sanitize_text_field($value);
			}

			if ($value !== '') {
				$account_info[$account_key] = $value;
			}
		}

		if (empty($account_info['country'])) {
			$account_info['country'] = 'US';
		}
		if (empty($account_info['email']) && $user) {
			$account_info['email'] = $user->user_email;
		}

		return array_merge(
			$account_info,
			$this->get_checkout_publication_preferences($account_info, 'Print')
		);
	}

	private function get_checkout_publication_preferences($account_info = [], $default = 'Print') {
		$account_info = is_array($account_info) ? $account_info : [];
		$default = $this->normalize_print_digital_value($default, 'Print');
		$legacy_fallback = $account_info['aaj_pref'] ?? ($account_info['publication_pref'] ?? $default);

		$preferences = $this->get_normalized_publication_preferences([
			'aaj_pref' => isset($_REQUEST['aaj_preference'])
				? sanitize_text_field(wp_unslash($_REQUEST['aaj_preference']))
				: (isset($_REQUEST['aac_aaj_pref'])
					? sanitize_text_field(wp_unslash($_REQUEST['aac_aaj_pref']))
					: ($account_info['aaj_pref'] ?? $legacy_fallback)),
			'anac_pref' => isset($_REQUEST['anac_preference']) || isset($_REQUEST['anan_preference'])
				? sanitize_text_field(wp_unslash($_REQUEST['anac_preference'] ?? $_REQUEST['anan_preference']))
				: (isset($_REQUEST['aac_anac_pref'])
					? sanitize_text_field(wp_unslash($_REQUEST['aac_anac_pref']))
					: ($account_info['anac_pref'] ?? $legacy_fallback)),
			'acj_pref' => isset($_REQUEST['american_climbing_journal_preference']) || isset($_REQUEST['acj_preference'])
				? sanitize_text_field(wp_unslash($_REQUEST['american_climbing_journal_preference'] ?? $_REQUEST['acj_preference']))
				: (isset($_REQUEST['aac_acj_pref'])
					? sanitize_text_field(wp_unslash($_REQUEST['aac_acj_pref']))
					: ($account_info['acj_pref'] ?? $legacy_fallback)),
			'guidebook_pref' => isset($_REQUEST['guidebook_preferences']) || isset($_REQUEST['guidebook_preference'])
				? sanitize_text_field(wp_unslash($_REQUEST['guidebook_preferences'] ?? $_REQUEST['guidebook_preference']))
				: (isset($_REQUEST['aac_guidebook_pref'])
					? sanitize_text_field(wp_unslash($_REQUEST['aac_guidebook_pref']))
					: ($account_info['guidebook_pref'] ?? $default)),
		]);

		$country = $this->get_checkout_request_value(['pmpro_scountry', 'scountry', 'bcountry']);
		$country = $country !== ''
			? $country
			: ($account_info['country'] ?? 'US');
		if ($this->is_international_country($country)) {
			$preferences['aaj_pref'] = 'Digital';
			$preferences['anac_pref'] = 'Digital';
			$preferences['acj_pref'] = 'Digital';
			$preferences['guidebook_pref'] = 'Digital';
		}

		return $preferences;
	}

	private function is_international_country($country) {
		return $this->normalize_country_code($country) !== 'US';
	}

	private function normalize_country_code($country) {
		$normalized = strtoupper(trim((string) $country));
		$normalized = preg_replace('/[^A-Z ]+/', '', $normalized);
		$normalized = preg_replace('/\s+/', ' ', $normalized);
		$normalized = trim((string) $normalized);

		if (in_array($normalized, ['', 'US', 'USA', 'UNITED STATES', 'UNITED STATES OF AMERICA'], true)) {
			return 'US';
		}

		if (in_array($normalized, ['CA', 'CAN', 'CANADA'], true)) {
			return 'CA';
		}

		if (in_array($normalized, ['MX', 'MEX', 'MEXICO'], true)) {
			return 'MX';
		}

		return $normalized;
	}

	private function is_international_checkout_request() {
		$country = $this->get_checkout_request_value(['pmpro_scountry', 'scountry', 'bcountry']);
		if ($country === '') {
			return false;
		}

		return $this->is_international_country($country);
	}

	private function has_print_publication_selection($account_info) {
		if (!is_array($account_info)) {
			return false;
		}

		$preferences = $this->get_normalized_publication_preferences($account_info);
		foreach (['aaj_pref', 'anac_pref', 'acj_pref', 'guidebook_pref'] as $field) {
			if (($preferences[$field] ?? 'Print') === 'Print') {
				return true;
			}
		}

		return false;
	}

	public function maybe_repair_pmpro_user_fields_settings() {
		$current_settings = get_option('pmpro_user_fields_settings', null);
		if (!is_array($current_settings)) {
			return;
		}

		$needs_update = false;
		$normalized_settings = [];

		foreach ($current_settings as $group) {
			$group_data = is_object($group) ? get_object_vars($group) : (is_array($group) ? $group : []);
			if (!$group_data) {
				$needs_update = true;
				continue;
			}

			$group_name = sanitize_text_field((string) ($group_data['name'] ?? ''));
			if ($group_name === 'Emergency Contact') {
				$needs_update = true;
				continue;
			}

			$fields = $group_data['fields'] ?? [];
			$normalized_fields = [];
			if (is_array($fields)) {
				foreach ($fields as $field) {
					$field_data = is_object($field) ? get_object_vars($field) : (is_array($field) ? $field : []);
					if (!$field_data) {
						$needs_update = true;
						continue;
					}

					if (is_array($field)) {
						$needs_update = true;
					}

					$normalized_fields[] = (object) $field_data;
				}
			} else {
				$needs_update = true;
			}

			$group_data['fields'] = $normalized_fields;
			if (is_array($group)) {
				$needs_update = true;
			}

			$normalized_settings[] = (object) $group_data;
		}

		if ($needs_update) {
			update_option('pmpro_user_fields_settings', $normalized_settings, false);
		}
	}

	private function should_apply_international_print_surcharge($level_id = 0) {
		return (int) $level_id === 3;
	}

	private function get_international_print_surcharge_amount($account_info, $level_id = 0) {
		if (!$this->should_apply_international_print_surcharge($level_id)) {
			return 0.0;
		}

		if (!$this->is_international_country($account_info['country'] ?? 'US')) {
			return 0.0;
		}

		if (!$this->has_print_publication_selection($account_info)) {
			return 0.0;
		}

		return 30.0;
	}

	private function normalize_money_amount($value) {
		$normalized = preg_replace('/[^0-9.\-]+/', '', (string) $value);
		if (!is_string($normalized) || $normalized === '' || !is_numeric($normalized)) {
			return 0.0;
		}

		return round(max(0, (float) $normalized), 2);
	}

	private function normalize_whole_dollar_amount($value) {
		$normalized = preg_replace('/[^0-9.\-]+/', '', (string) $value);
		if (!is_string($normalized) || $normalized === '' || !is_numeric($normalized)) {
			return 0.0;
		}

		return (float) floor(max(0, (float) $normalized));
	}

	private function get_pmpro_order_breakdown_storage_keys($morder) {
		$keys = [];
		$order_id = is_object($morder) && isset($morder->id) ? absint($morder->id) : 0;
		$order_code = is_object($morder) && isset($morder->code) ? sanitize_key((string) $morder->code) : '';

		if ($order_id > 0) {
			$keys[] = self::ORDER_BREAKDOWN_OPTION_PREFIX . 'id_' . $order_id;
		}

		if ($order_code !== '') {
			$keys[] = self::ORDER_BREAKDOWN_OPTION_PREFIX . 'code_' . $order_code;
		}

		return array_values(array_unique($keys));
	}

	private function get_pmpro_order_breakdown_payload($morder) {
		foreach ($this->get_pmpro_order_breakdown_storage_keys($morder) as $storage_key) {
			$stored = get_option($storage_key, null);
			if (is_array($stored) && !empty($stored['items'])) {
				return $this->hydrate_pmpro_order_breakdown_from_order($stored, $morder, is_object($morder) && isset($morder->user_id) ? (int) $morder->user_id : 0);
			}
		}

		return $this->build_pmpro_order_breakdown_payload($morder, is_object($morder) && isset($morder->user_id) ? (int) $morder->user_id : 0);
	}

	private function hydrate_pmpro_order_breakdown_from_order($order_breakdown, $morder = null, $user_id = 0) {
		if (!is_array($order_breakdown) || empty($order_breakdown['items']) || !is_array($order_breakdown['items'])) {
			return is_array($order_breakdown) ? $order_breakdown : [];
		}

		$order_id = is_object($morder) && isset($morder->id) ? absint($morder->id) : absint($order_breakdown['order_id'] ?? 0);
		if ($order_id > 0) {
			$order_breakdown['order_id'] = $order_id;
		}

		$order_code = is_object($morder) && !empty($morder->code) ? sanitize_text_field((string) $morder->code) : sanitize_text_field((string) ($order_breakdown['order_code'] ?? ''));
		if ($order_code !== '') {
			$order_breakdown['order_code'] = $order_code;
		}

		$pmpro_discount_code = $this->get_pmpro_order_discount_code($morder);
		$items = $order_breakdown['items'];
		$membership_discount_type = $this->get_membership_discount_type_from_code($pmpro_discount_code);
		$discount_label = $pmpro_discount_code !== ''
			? $this->format_membership_discount_line_item_label(
				$membership_discount_type,
				$this->get_membership_discount_catalog(),
				$pmpro_discount_code
			)
			: __('Promo discount', 'aac-member-portal');

		$existing_discount_index = $this->find_receipt_discount_line_item_index($items);
		if ($existing_discount_index !== null) {
			$items[$existing_discount_index]['label'] = $discount_label;
			$order_breakdown['items'] = $items;
			$this->maybe_update_stored_pmpro_order_breakdown($morder, $order_breakdown);

			return $order_breakdown;
		}

		$membership_id = is_object($morder) && isset($morder->membership_id) ? (int) $morder->membership_id : (int) ($order_breakdown['membership_id'] ?? 0);
		$level = $membership_id > 0 && function_exists('pmpro_getLevel') ? pmpro_getLevel($membership_id) : null;
		$level_name = $this->get_pmpro_level_name($membership_id);
		if ($level_name === '' && !empty($order_breakdown['level_name'])) {
			$level_name = sanitize_text_field((string) $order_breakdown['level_name']);
		}
		if ($level_name === '' && !empty($order_breakdown['benefits']['level_name'])) {
			$level_name = sanitize_text_field((string) $order_breakdown['benefits']['level_name']);
		}
		if ($level_name === '') {
			$membership_index_for_label = $this->find_receipt_membership_line_item_index($items);
			if ($membership_index_for_label !== null && !empty($items[$membership_index_for_label]['label'])) {
				$level_name = preg_replace('/\s+membership$/i', '', (string) $items[$membership_index_for_label]['label']);
				$level_name = is_string($level_name) ? sanitize_text_field(trim($level_name)) : '';
			}
		}
		$base_membership_amount = max(
			0,
			$this->get_aac_membership_level_base_total($level) ?? $this->get_aac_membership_level_base_total_by_name($level_name) ?? $this->get_level_checkout_initial_total($level)
		);
		if ($base_membership_amount <= 0) {
			return $order_breakdown;
		}

		$total_amount = isset($morder->total) ? round((float) $morder->total, 2) : round((float) ($order_breakdown['total'] ?? 0), 2);
		if ($total_amount <= 0) {
			return $order_breakdown;
		}

		$membership_index = $this->find_receipt_membership_line_item_index($items, $level_name);
		if ($membership_index === null) {
			$membership_index = 0;
		}

		$other_positive_total = 0.0;
		foreach ($items as $index => $item) {
			if ((int) $index === (int) $membership_index || $this->is_receipt_discount_line_item($item)) {
				continue;
			}

			$amount = isset($item['amount']) ? round((float) $item['amount'], 2) : 0.0;
			if ($amount > 0) {
				$other_positive_total += $amount;
			}
		}

		$stored_membership_amount = isset($items[$membership_index]['amount']) ? round((float) $items[$membership_index]['amount'], 2) : 0.0;
		$actual_membership_amount = $stored_membership_amount > 0
			? $stored_membership_amount
			: round(max(0, $total_amount - $other_positive_total), 2);
		$discount_amount = round(max(0, $base_membership_amount - $actual_membership_amount), 2);
		if ($discount_amount <= 0) {
			return $order_breakdown;
		}

		$items[$membership_index]['amount'] = round($base_membership_amount, 2);
		array_splice($items, $membership_index + 1, 0, [[
			'label' => $discount_label,
			'amount' => 0 - $discount_amount,
		]]);

		$order_breakdown['items'] = $items;
		$order_breakdown['total'] = $total_amount;
		$this->maybe_update_stored_pmpro_order_breakdown($morder, $order_breakdown);

		return $order_breakdown;
	}

	private function maybe_update_stored_pmpro_order_breakdown($morder, $order_breakdown) {
		if (!is_object($morder) || empty($order_breakdown['items'])) {
			return;
		}

		foreach ($this->get_pmpro_order_breakdown_storage_keys($morder) as $storage_key) {
			update_option($storage_key, $order_breakdown, false);
		}
	}

	private function find_receipt_discount_line_item_index($items) {
		foreach ($items as $index => $item) {
			if ($this->is_receipt_discount_line_item($item)) {
				return (int) $index;
			}
		}

		return null;
	}

	private function is_receipt_discount_line_item($item) {
		if (!is_array($item)) {
			return false;
		}

		$amount = isset($item['amount']) ? (float) $item['amount'] : 0.0;
		$label = strtolower((string) ($item['label'] ?? ''));

		return $amount < 0 || strpos($label, 'discount') !== false || strpos($label, 'promo') !== false;
	}

	private function find_receipt_membership_line_item_index($items, $level_name = '') {
		$level_name = strtolower(trim((string) $level_name));
		foreach ($items as $index => $item) {
			if (!is_array($item) || $this->is_receipt_discount_line_item($item)) {
				continue;
			}

			$label = strtolower((string) ($item['label'] ?? ''));
			if (strpos($label, 'membership') !== false || ($level_name !== '' && strpos($label, $level_name) !== false)) {
				return (int) $index;
			}
		}

		return null;
	}

	private function build_pmpro_order_breakdown_payload($morder, $user_id = 0) {
		if (!is_object($morder)) {
			return [];
		}

		$total_amount = isset($morder->total) ? round((float) $morder->total, 2) : 0.0;
		$membership_id = isset($morder->membership_id) ? (int) $morder->membership_id : 0;
		$level_name = $this->get_pmpro_level_name($membership_id);
		$level = $membership_id > 0 && function_exists('pmpro_getLevel') ? pmpro_getLevel($membership_id) : null;
		$base_membership_amount = max(
			0,
			$this->get_aac_membership_level_base_total($level) ?? $this->get_level_checkout_initial_total($level)
		);
		$membership_discount_type = $this->get_receipt_membership_discount_type($user_id, $level);
		$membership_discount_catalog = $this->get_membership_discount_catalog();
		$pmpro_discount_code = $this->get_pmpro_order_discount_code($morder);
		$membership_discount_type_from_code = $this->get_membership_discount_type_from_code($pmpro_discount_code);
		if ($membership_discount_type === '' && $membership_discount_type_from_code !== '') {
			$membership_discount_type = $membership_discount_type_from_code;
		}
		if (!$this->supports_discount_tiers($level)) {
			$membership_discount_type = '';
		}
		$partner_family_config = $this->has_partner_family_request()
			? $this->get_requested_partner_family_config()
			: $this->get_effective_partner_family_config($user_id);
		if (($partner_family_config['mode'] ?? '') === 'family') {
			$membership_discount_type = '';
		}
		$account_info = $this->get_checkout_account_info_from_request($user_id > 0 ? get_user_by('id', $user_id) : null);
		if ($this->is_international_country($account_info['country'] ?? 'US')) {
			$membership_discount_type = '';
			$partner_family_config = $this->normalize_partner_family_config([]);
		}
		$partner_family_pricing = $this->get_partner_family_pricing(max(0, $this->get_level_recurring_total($level)));
		$partner_family_additional_adult_amount = !empty($partner_family_config['additional_adult']) ? (float) $partner_family_pricing['additional_adult_price'] : 0.0;
		$partner_family_dependents_amount = max(0, (int) ($partner_family_config['dependent_count'] ?? 0)) * (float) $partner_family_pricing['dependent_price'];
		$selected_addons = $this->has_magazine_addon_request()
			? $this->get_requested_magazine_addons()
			: $this->get_effective_magazine_addon_selection($user_id);
		$catalog = $this->get_magazine_addon_catalog();
		$magazine_total = $this->get_magazine_addon_total($selected_addons);
		$donation_amount = $this->get_requested_donation_amount();
		$international_surcharge = $this->get_international_print_surcharge_amount($account_info, $membership_id);
		$add_dependent_context = is_array($this->add_dependent_checkout_context)
			? $this->add_dependent_checkout_context
			: $this->get_add_dependent_checkout_context($level, $user_id);
		$items = [];

		if ($add_dependent_context) {
			$items[] = [
				'label' => 'Add dependent',
				'amount' => $total_amount > 0 ? $total_amount : round((float) ($add_dependent_context['prorated_amount'] ?? 0), 2),
			];

			return [
					'order_id' => isset($morder->id) ? absint($morder->id) : 0,
					'order_code' => isset($morder->code) ? sanitize_text_field((string) $morder->code) : '',
					'user_id' => $user_id,
					'membership_id' => $membership_id,
					'level_name' => $level_name,
					'date' => $this->get_pmpro_order_display_date($morder),
					'member' => $this->get_pmpro_order_receipt_member_info($user_id),
				'payment_summary' => $this->get_pmpro_order_payment_summary($morder, $user_id),
				'benefits' => $this->get_pmpro_level_receipt_benefits($level_name),
				'total' => $total_amount,
				'items' => $items,
			];
		}

		$membership_label = $this->format_membership_line_item_label($level_name);

		$actual_membership_line_amount = round(
			max(
				0,
				$total_amount
				- $partner_family_additional_adult_amount
				- $partner_family_dependents_amount
				- $international_surcharge
				- $magazine_total
				- $donation_amount
			),
			2
		);
		$membership_discount_amount = round(max(0, $base_membership_amount - $actual_membership_line_amount), 2);
		$membership_line_amount = $membership_discount_amount > 0
			? round($base_membership_amount, 2)
			: $actual_membership_line_amount;

		if ($membership_line_amount > 0 || (!$selected_addons && $donation_amount <= 0)) {
			$items[] = [
				'label' => $membership_label,
				'amount' => $membership_line_amount > 0 ? $membership_line_amount : $total_amount,
			];
		}

		if ($membership_discount_amount > 0) {
			$items[] = [
				'label' => ($membership_discount_type !== '' || $pmpro_discount_code !== '')
					? $this->format_membership_discount_line_item_label($membership_discount_type, $membership_discount_catalog, $pmpro_discount_code)
					: __('Promo discount', 'aac-member-portal'),
				'amount' => 0 - $membership_discount_amount,
			];
		}

		if ($partner_family_additional_adult_amount > 0) {
			$items[] = [
				'label' => 'Additional adult',
				'amount' => round($partner_family_additional_adult_amount, 2),
			];
		}

		if ($partner_family_dependents_amount > 0) {
			$dependent_count = max(0, (int) ($partner_family_config['dependent_count'] ?? 0));
			$items[] = [
				'label' => sprintf(
					_n('%d dependent', '%d dependents', $dependent_count, 'aac-member-portal'),
					$dependent_count
				),
				'amount' => round($partner_family_dependents_amount, 2),
			];
		}

		if ($international_surcharge > 0) {
			$items[] = [
				'label' => 'International surcharge for print copies',
				'amount' => round($international_surcharge, 2),
			];
		}

		foreach ($selected_addons as $slug) {
			if (empty($catalog[$slug]['price'])) {
				continue;
			}

			$items[] = [
				'label' => $catalog[$slug]['label'],
				'amount' => round((float) $catalog[$slug]['price'], 2),
			];
		}

		if ($donation_amount > 0) {
			$items[] = [
				'label' => 'Donation',
				'amount' => $donation_amount,
			];
		}

		if (empty($items) && $total_amount > 0) {
			$items[] = [
				'label' => $membership_label,
				'amount' => $total_amount,
			];
		}

		return [
			'order_id' => isset($morder->id) ? absint($morder->id) : 0,
			'order_code' => isset($morder->code) ? sanitize_text_field((string) $morder->code) : '',
			'user_id' => $user_id,
			'membership_id' => $membership_id,
			'level_name' => $level_name,
			'date' => $this->get_pmpro_order_display_date($morder),
			'member' => $this->get_pmpro_order_receipt_member_info($user_id),
			'payment_summary' => $this->get_pmpro_order_payment_summary($morder, $user_id),
			'benefits' => $this->get_pmpro_level_receipt_benefits($level_name),
			'total' => $total_amount,
			'items' => $items,
		];
	}

	private function get_receipt_membership_discount_type($user_id = 0, $level = null) {
		$discount_type = $this->has_membership_discount_request()
			? $this->get_requested_membership_discount_type()
			: $this->normalize_membership_discount_type(get_user_meta((int) $user_id, 'aac_membership_discount_type', true));

		if ($discount_type === '') {
			return '';
		}

		return $this->supports_discount_tiers($level) ? $discount_type : '';
	}

	private function get_pmpro_order_discount_code($morder = null) {
		$candidate_keys = ['discount_code', 'pmpro_discount_code', 'other_discount_code', 'discountcode'];
		if (is_object($morder)) {
			foreach ($candidate_keys as $property) {
				if (!empty($morder->{$property}) && is_scalar($morder->{$property})) {
					return strtoupper(sanitize_text_field((string) $morder->{$property}));
				}
			}

			foreach (['discount_code_id', 'discountcode_id', 'discount_id'] as $property) {
				if (!empty($morder->{$property})) {
					$code = $this->get_pmpro_discount_code_by_id((int) $morder->{$property});
					if ($code !== '') {
						return $code;
					}
				}
			}

			$order_id = isset($morder->id) ? absint($morder->id) : 0;
			if ($order_id > 0) {
				$code = $this->get_pmpro_discount_code_by_order_id($order_id);
				if ($code !== '') {
					return $code;
				}
			}
		}

		foreach ($candidate_keys as $request_key) {
			$value = $this->get_uppercase_request_value($request_key);
			if ($value !== '') {
				return $value;
			}
		}

		return '';
	}

	private function get_pmpro_discount_code_by_order_id($order_id) {
		global $wpdb;
		$order_id = absint($order_id);
		if ($order_id <= 0 || !$wpdb) {
			return '';
		}

		foreach ([$wpdb->prefix . 'pmpro_discount_codes_uses', $wpdb->prefix . 'pmpro_discountcodes_uses'] as $uses_table) {
			$uses_table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $uses_table)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is generated.
			if ($uses_table_exists !== $uses_table) {
				continue;
			}

			$discount_code_id = $wpdb->get_var($wpdb->prepare("SELECT code_id FROM {$uses_table} WHERE order_id = %d ORDER BY id DESC LIMIT 1", $order_id)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$code = $this->get_pmpro_discount_code_by_id((int) $discount_code_id);
			if ($code !== '') {
				return $code;
			}
		}

		return '';
	}

	private function get_pmpro_discount_code_by_id($discount_code_id) {
		global $wpdb;
		$discount_code_id = (int) $discount_code_id;
		if ($discount_code_id <= 0 || !$wpdb) {
			return '';
		}

		foreach ([$wpdb->prefix . 'pmpro_discount_codes', $wpdb->prefix . 'pmpro_discountcodes'] as $table) {
			$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is generated.
			if ($table_exists !== $table) {
				continue;
			}

			$code = $wpdb->get_var($wpdb->prepare("SELECT code FROM {$table} WHERE id = %d LIMIT 1", $discount_code_id)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if (is_scalar($code) && (string) $code !== '') {
				return strtoupper(sanitize_text_field((string) $code));
			}
		}

		return '';
	}

	private function get_membership_discount_type_from_code($discount_code) {
		$discount_code = strtoupper(sanitize_text_field((string) $discount_code));
		if ($discount_code === '') {
			return '';
		}

		foreach (array_keys($this->get_membership_discount_catalog()) as $type) {
			if ($this->get_membership_discount_code($type) === $discount_code) {
				return $type;
			}
		}

		return '';
	}

	private function format_membership_discount_line_item_label($membership_discount_type, $membership_discount_catalog = null, $pmpro_discount_code = '') {
		$membership_discount_type = $this->normalize_membership_discount_type($membership_discount_type);
		$membership_discount_catalog = is_array($membership_discount_catalog) ? $membership_discount_catalog : $this->get_membership_discount_catalog();
		$pmpro_discount_code = strtoupper(sanitize_text_field((string) $pmpro_discount_code));
		if ($membership_discount_type === '' || empty($membership_discount_catalog[$membership_discount_type]['label'])) {
			return $pmpro_discount_code !== ''
				? sprintf(__('Promo code (%s)', 'aac-member-portal'), $pmpro_discount_code)
				: __('Membership discount', 'aac-member-portal');
		}

		$rate = isset($membership_discount_catalog[$membership_discount_type]['rate'])
			? (float) $membership_discount_catalog[$membership_discount_type]['rate']
			: 0.0;
		$percent = $rate > 0 ? (int) round($rate * 100) : 0;
		return $percent > 0
			? sprintf('%s (%d%%)', $membership_discount_catalog[$membership_discount_type]['label'], $percent)
			: (string) $membership_discount_catalog[$membership_discount_type]['label'];
	}

	private function get_pmpro_level_name($membership_id) {
		$membership_id = (int) $membership_id;
		if ($membership_id <= 0) {
			return '';
		}

		if (function_exists('pmpro_getLevel')) {
			$level = pmpro_getLevel($membership_id);
			if (is_object($level) && !empty($level->name)) {
				return sanitize_text_field((string) $level->name);
			}
		}

		return '';
	}

	private function get_pmpro_order_breakdown_payload_from_transaction($transaction, $user_id = 0) {
		if (!is_array($transaction)) {
			return [];
		}

		$order_id = absint($transaction['metadata']['pmpro_order_id'] ?? 0);
		if ($order_id <= 0) {
			return [];
		}

		$morder = $this->get_pmpro_order_object_by_id($order_id);

		$storage_key = self::ORDER_BREAKDOWN_OPTION_PREFIX . 'id_' . $order_id;
		$stored = get_option($storage_key, null);
		if (is_array($stored) && !empty($stored['items'])) {
			$stored['user_id'] = !empty($stored['user_id']) ? (int) $stored['user_id'] : (int) $user_id;
			$stored['member'] = !empty($stored['member']) && is_array($stored['member'])
				? $stored['member']
				: $this->get_pmpro_order_receipt_member_info((int) $user_id);
			$stored['payment_summary'] = !empty($stored['payment_summary'])
				? (string) $stored['payment_summary']
				: $this->get_pmpro_order_payment_summary(null, (int) $user_id);
			$stored['benefits'] = !empty($stored['benefits']) && is_array($stored['benefits'])
				? $stored['benefits']
				: $this->get_pmpro_level_receipt_benefits($this->get_pmpro_level_name((int) ($transaction['metadata']['membership_id'] ?? 0)));
			$stored['date'] = !empty($stored['date']) ? (string) $stored['date'] : (string) ($transaction['createdAt'] ?? '');

			return $this->hydrate_pmpro_order_breakdown_from_order($stored, $morder, (int) $user_id);
		}

		if (is_object($morder)) {
			return $this->build_pmpro_order_breakdown_payload($morder, (int) $user_id);
		}

		return [];
	}

	private function get_pmpro_order_object_by_id($order_id) {
		global $wpdb;
		$order_id = absint($order_id);
		if ($order_id <= 0 || !$wpdb || empty($wpdb->pmpro_membership_orders)) {
			return null;
		}

		$table = $wpdb->pmpro_membership_orders;
		$query = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $order_id); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$order = $wpdb->get_row($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.

		return is_object($order) ? $order : null;
	}

	private function build_pmpro_transaction_receipt_payload($transaction, $user_id = 0) {
		if (!is_array($transaction)) {
			return [];
		}

		$membership_id = (int) ($transaction['metadata']['membership_id'] ?? 0);
		$level_name = $this->get_pmpro_level_name($membership_id);
		if ($level_name === '' && !empty($transaction['description'])) {
			$level_name = preg_replace('/\s+membership$/i', '', (string) $transaction['description']);
			$level_name = is_string($level_name) ? trim($level_name) : '';
		}

		$total = isset($transaction['amount']) ? (float) $transaction['amount'] : 0.0;
		$level = $membership_id > 0 && function_exists('pmpro_getLevel') ? pmpro_getLevel($membership_id) : null;
		$base_membership_amount = max(
			0,
			$this->get_aac_membership_level_base_total($level) ?? $this->get_level_checkout_initial_total($level)
		);
		$membership_discount_type = $this->get_receipt_membership_discount_type((int) $user_id, $level);
		$membership_discount_catalog = $this->get_membership_discount_catalog();
		$membership_discount_amount = round(max(0, $base_membership_amount - $total), 2);
		$items = [
			[
				'label' => $this->format_membership_line_item_label($level_name),
				'amount' => $membership_discount_amount > 0 ? round($base_membership_amount, 2) : $total,
			],
		];
		if ($membership_discount_amount > 0) {
			$items[] = [
				'label' => $membership_discount_type !== ''
					? $this->format_membership_discount_line_item_label($membership_discount_type, $membership_discount_catalog)
					: __('Promo discount', 'aac-member-portal'),
				'amount' => 0 - $membership_discount_amount,
			];
		}

		return [
			'user_id' => (int) $user_id,
			'date' => (string) ($transaction['createdAt'] ?? ''),
			'member' => $this->get_pmpro_order_receipt_member_info((int) $user_id),
			'payment_summary' => $this->get_pmpro_order_payment_summary(null, (int) $user_id),
			'benefits' => $this->get_pmpro_level_receipt_benefits($level_name),
			'total' => $total,
			'items' => $items,
		];
	}

	private function get_pmpro_order_receipt_member_info($user_id) {
		$user_id = (int) $user_id;
		$user = $user_id > 0 ? get_user_by('id', $user_id) : null;
		$account_info = $this->get_account_info_defaults_for_user($user instanceof WP_User && $user->exists() ? $user : null);
		$name = trim((string) ($account_info['name'] ?? ''));
		if ($name === '') {
			$name = trim((string) ($account_info['first_name'] ?? '') . ' ' . (string) ($account_info['last_name'] ?? ''));
		}

		$address_parts = array_filter([
			trim((string) ($account_info['street'] ?? '')),
			trim((string) ($account_info['address2'] ?? '')),
			trim(implode(', ', array_filter([
				trim((string) ($account_info['city'] ?? '')),
				trim((string) ($account_info['state'] ?? '')),
			]))),
			trim(implode(' ', array_filter([
				trim((string) ($account_info['zip'] ?? '')),
				trim((string) ($account_info['country'] ?? '')),
			]))),
		]);

		return [
			'name' => sanitize_text_field($name),
			'email' => sanitize_email((string) ($account_info['email'] ?? '')),
			'phone' => sanitize_text_field((string) ($account_info['phone'] ?? '')),
			'address' => sanitize_text_field(implode(', ', $address_parts)),
		];
	}

	private function get_pmpro_order_display_date($morder) {
		if (!is_object($morder)) {
			return '';
		}

		foreach (['timestamp', 'Timestamp', 'date', 'Date', 'checkout_date'] as $property) {
			if (!empty($morder->{$property})) {
				return sanitize_text_field((string) $morder->{$property});
			}
		}

		return '';
	}

	private function get_pmpro_order_payment_summary($morder = null, $user_id = 0) {
		$summary = '';
		if (is_object($morder)) {
			$last4 = $this->normalize_payment_last4($morder->accountnumber ?? '');
			$card_type = sanitize_text_field((string) ($morder->card_type ?? ''));
			$payment_type = sanitize_text_field((string) ($morder->payment_type ?? ''));
			if ($last4 !== '') {
				$summary = trim(($card_type !== '' ? ucwords(strtolower($card_type)) : 'Card') . ' ending in ' . $last4);
			} elseif ($payment_type !== '') {
				$summary = $payment_type;
			}
		}

		if ($summary === '' && $user_id > 0) {
			$summary = AAC_Member_Portal_PMPro::get_payment_method_summary((int) $user_id);
		}

		return sanitize_text_field($summary);
	}

	private function normalize_payment_last4($value) {
		$digits = preg_replace('/\D+/', '', (string) $value);
		if (!is_string($digits) || $digits === '') {
			return '';
		}

		return substr($digits, -4);
	}

	private function get_pmpro_level_receipt_benefits($level_name) {
		$level_name = trim((string) $level_name);
		$matched_level = $this->get_receipt_rescue_level_for_name($level_name);
		if (!$matched_level) {
			return [
				'level_name' => $level_name,
				'items' => [],
			];
		}

		$items = [
			[
				'label' => __('Rescue coverage', 'aac-member-portal'),
				'value' => $this->format_price((float) ($matched_level['rescue_amount'] ?? 0)),
			],
			[
				'label' => __('Medical coverage', 'aac-member-portal'),
				'value' => $this->format_price((float) ($matched_level['medical_amount'] ?? 0)),
			],
			[
				'label' => __('Mortal remains transport', 'aac-member-portal'),
				'value' => $this->format_price((float) ($matched_level['mortal_remains_amount'] ?? 0)),
			],
			[
				'label' => __('Redpoint rescue reimbursement process', 'aac-member-portal'),
				'value' => !empty($matched_level['rescue_reimbursement_process']) ? __('Included', 'aac-member-portal') : __('Not included', 'aac-member-portal'),
			],
		];

		return [
			'level_name' => sanitize_text_field((string) ($matched_level['level_name'] ?? $level_name)),
			'items' => $items,
		];
	}

	private function get_receipt_rescue_level_for_name($level_name) {
		$settings = $this->get_portal_ui_settings();
		$rescue_levels = isset($settings['content']['rescueLevels']) && is_array($settings['content']['rescueLevels'])
			? $settings['content']['rescueLevels']
			: [];
		$target = $this->normalize_receipt_level_name($level_name);

		foreach ($rescue_levels as $level) {
			if (!is_array($level)) {
				continue;
			}

			if ($this->normalize_receipt_level_name((string) ($level['level_name'] ?? '')) === $target) {
				return $level;
			}
		}

		if (strpos($target, 'partner') !== false) {
			foreach ($rescue_levels as $level) {
				if (is_array($level) && $this->normalize_receipt_level_name((string) ($level['level_name'] ?? '')) === 'partner') {
					return $level;
				}
			}
		}

		return null;
	}

	private function normalize_receipt_level_name($level_name) {
		$normalized = strtolower(trim((string) $level_name));
		$normalized = preg_replace('/\s+membership$/', '', $normalized);
		$normalized = preg_replace('/[^a-z0-9]+/', ' ', is_string($normalized) ? $normalized : '');
		return trim((string) $normalized);
	}

	private function strip_pmpro_order_references_from_confirmation($confirmation_message) {
		$message = (string) $confirmation_message;
		$message = preg_replace('/<li[^>]*>\s*<strong[^>]*>\s*(?:order|invoice|pmpro order|order number|invoice number)[^<]*<\/strong>.*?<\/li>/is', '', $message);
		$message = preg_replace('/<p[^>]*>\s*(?:Order|Invoice|PMPro Order|Order Number|Invoice Number)\s*(?:#|:).*?<\/p>/is', '', (string) $message);
		$message = preg_replace('/<p[^>]*>[^<]*(?:order|invoice|pmpro order)\s*(?:number|#|:)[^<]*<\/p>/is', '', (string) $message);
		return is_string($message) ? $message : (string) $confirmation_message;
	}

	private function render_pmpro_order_breakdown_markup($order_breakdown) {
		$items = isset($order_breakdown['items']) && is_array($order_breakdown['items']) ? $order_breakdown['items'] : [];
		if (empty($items)) {
			return '';
		}
		$member = isset($order_breakdown['member']) && is_array($order_breakdown['member']) ? $order_breakdown['member'] : [];
		$benefits = isset($order_breakdown['benefits']) && is_array($order_breakdown['benefits']) ? $order_breakdown['benefits'] : [];
		$benefit_items = isset($benefits['items']) && is_array($benefits['items']) ? $benefits['items'] : [];
		$payment_summary = trim((string) ($order_breakdown['payment_summary'] ?? ''));
		$display_date = trim((string) ($order_breakdown['date'] ?? ''));

		ob_start();
		?>
		<section class="aac-order-summary" aria-label="<?php esc_attr_e('Transaction summary', 'aac-member-portal'); ?>">
			<div class="aac-order-summary__header">
				<h2><?php esc_html_e('Member Receipt', 'aac-member-portal'); ?></h2>
				<p><?php esc_html_e('Review the member contact details, payment summary, and membership benefits for this transaction.', 'aac-member-portal'); ?></p>
			</div>
			<?php if (!empty($member)) : ?>
				<div class="aac-order-summary__section">
					<h3><?php esc_html_e('Member Information', 'aac-member-portal'); ?></h3>
					<div class="aac-order-summary__details">
						<?php if (!empty($member['name'])) : ?>
							<div><span><?php esc_html_e('Name', 'aac-member-portal'); ?></span><strong><?php echo esc_html((string) $member['name']); ?></strong></div>
						<?php endif; ?>
						<?php if (!empty($member['email'])) : ?>
							<div><span><?php esc_html_e('Email', 'aac-member-portal'); ?></span><strong><?php echo esc_html((string) $member['email']); ?></strong></div>
						<?php endif; ?>
						<?php if (!empty($member['phone'])) : ?>
							<div><span><?php esc_html_e('Phone', 'aac-member-portal'); ?></span><strong><?php echo esc_html((string) $member['phone']); ?></strong></div>
						<?php endif; ?>
						<?php if (!empty($member['address'])) : ?>
							<div><span><?php esc_html_e('Address', 'aac-member-portal'); ?></span><strong><?php echo esc_html((string) $member['address']); ?></strong></div>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
			<div class="aac-order-summary__section">
				<h3><?php esc_html_e('Charges', 'aac-member-portal'); ?></h3>
				<?php if ($display_date !== '') : ?>
					<p class="aac-order-summary__meta"><?php echo esc_html(sprintf(__('Payment date: %s', 'aac-member-portal'), $this->format_pmpro_display_date($display_date))); ?></p>
				<?php endif; ?>
				<div class="aac-order-summary__rows">
					<?php foreach ($items as $item) : ?>
						<div class="aac-order-summary__row">
							<span><?php echo esc_html((string) ($item['label'] ?? 'Item')); ?></span>
							<strong><?php echo esc_html($this->format_line_item_price((float) ($item['amount'] ?? 0))); ?></strong>
						</div>
					<?php endforeach; ?>
					<div class="aac-order-summary__row aac-order-summary__row--total">
						<span><?php esc_html_e('Total charged', 'aac-member-portal'); ?></span>
						<strong><?php echo esc_html($this->format_price((float) ($order_breakdown['total'] ?? 0))); ?></strong>
					</div>
				</div>
				<p class="aac-order-summary__meta">
					<?php
					echo esc_html($payment_summary !== ''
						? sprintf(__('Paid with %s.', 'aac-member-portal'), $payment_summary)
						: __('Payment method details are not available for this transaction.', 'aac-member-portal'));
					?>
				</p>
			</div>
			<?php if (!empty($benefit_items)) : ?>
				<div class="aac-order-summary__section">
					<h3>
						<?php
						echo esc_html(sprintf(
							/* translators: %s membership level name */
							__('%s Benefits', 'aac-member-portal'),
							(string) ($benefits['level_name'] ?? __('Membership', 'aac-member-portal'))
						));
						?>
					</h3>
					<div class="aac-order-summary__benefits">
						<?php foreach ($benefit_items as $benefit_item) : ?>
							<div class="aac-order-summary__benefit">
								<span><?php echo esc_html((string) ($benefit_item['label'] ?? 'Benefit')); ?></span>
								<strong><?php echo esc_html((string) ($benefit_item['value'] ?? '')); ?></strong>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	private function format_price($amount) {
		if (function_exists('pmpro_formatPrice')) {
			return pmpro_formatPrice((float) $amount);
		}

		return '$' . number_format((float) $amount, 2);
	}

	private function format_line_item_price($amount) {
		$amount = (float) $amount;
		if ($amount < 0) {
			return '-' . $this->format_price(abs($amount));
		}

		return $this->format_price($amount);
	}

	private function format_membership_line_item_label($level_name) {
		$level_name = trim((string) $level_name);
		if ($level_name === '') {
			return 'Membership';
		}

		$normalized = preg_replace('/\s+membership(?:\s+membership)+$/i', ' Membership', $level_name);
		$normalized = is_string($normalized) ? trim($normalized) : $level_name;

		if (preg_match('/^membership$/i', $normalized)) {
			return 'Membership';
		}

		if (preg_match('/membership$/i', $normalized)) {
			return $normalized;
		}

		return sprintf('%s Membership', $normalized);
	}

	private function get_current_membership_term_start_date($user_id, $level_id) {
		global $wpdb;

		$user_id = (int) $user_id;
		$level_id = (int) $level_id;
		if ($user_id <= 0 || $level_id <= 0 || !$wpdb || empty($wpdb->pmpro_memberships_users)) {
			return '';
		}

		$table = $wpdb->pmpro_memberships_users;
		$query = $wpdb->prepare(
			"SELECT startdate
			FROM {$table}
			WHERE user_id = %d
				AND membership_id = %d
				AND status = 'active'
				AND startdate IS NOT NULL
				AND startdate <> ''
				AND startdate <> '0000-00-00 00:00:00'
				AND startdate <> '0000-00-00'
			ORDER BY startdate DESC, id DESC
			LIMIT 1",
			$user_id,
			$level_id
		);

		if (!is_string($query) || $query === '') {
			return '';
		}

		$startdate = sanitize_text_field((string) $wpdb->get_var($query)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		if ($startdate === '') {
			return '';
		}

		$timestamp = strtotime($startdate);
		return $timestamp === false ? '' : gmdate('Y-m-d', $timestamp);
	}

	private function get_add_dependent_checkout_context($level = null, $user_id = 0) {
		if (!$this->is_pmpro_checkout_request() || !$this->is_add_dependent_checkout_request() || !class_exists('AAC_Member_Portal_PMPro')) {
			return null;
		}

		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ($user_id <= 0 || !AAC_Member_Portal_PMPro::is_available()) {
			return null;
		}

		$level = is_object($level) ? $level : $this->get_level_at_checkout();
		if (!$this->supports_family_plan_tiers($level)) {
			return null;
		}

		$target_level_id = isset($level->id) ? (int) $level->id : 0;
		$current_membership = AAC_Member_Portal_PMPro::get_primary_membership($user_id);
		$current_level_id = is_array($current_membership) && !empty($current_membership['level_id'])
			? (int) $current_membership['level_id']
			: 0;
		if ($target_level_id <= 0 || $current_level_id <= 0 || $target_level_id !== $current_level_id) {
			return null;
		}

		$current_family_config = get_user_meta($user_id, 'aac_partner_family_config', true);
		$current_family_config = $this->normalize_partner_family_config(is_array($current_family_config) ? $current_family_config : []);
		$current_dependent_count = max(0, (int) ($current_family_config['dependent_count'] ?? 0));
		if ($current_dependent_count >= 3) {
			return null;
		}

		$term_end_date = sanitize_text_field((string) ($current_membership['renewal_date'] ?: $current_membership['expiration_date']));
		if ($term_end_date === '') {
			return null;
		}

		$term_end_timestamp = strtotime($term_end_date . ' 23:59:59');
		$now_timestamp = current_time('timestamp');
		if ($term_end_timestamp === false || $term_end_timestamp <= $now_timestamp) {
			return null;
		}

		$term_start_date = $this->get_current_membership_term_start_date($user_id, $current_level_id);
		$term_start_timestamp = $term_start_date !== '' ? strtotime($term_start_date . ' 00:00:00') : false;
		$total_days = 365;
		if ($term_start_timestamp !== false && $term_start_timestamp < $term_end_timestamp) {
			$total_days = max(1, (int) ceil(($term_end_timestamp - $term_start_timestamp) / DAY_IN_SECONDS));
		}

		$remaining_days = max(0, (int) ceil(($term_end_timestamp - $now_timestamp) / DAY_IN_SECONDS));
		$remaining_ratio = min(1, max(0, $remaining_days / $total_days));
		$pricing = $this->get_partner_family_pricing(max(0, $this->get_level_recurring_total($level)));
		$dependent_price = (float) ($pricing['dependent_price'] ?? 45.0);
		$prorated_amount = round($dependent_price * $remaining_ratio, 2);

		$next_family_config = [
			'mode' => 'family',
			'additional_adult' => !empty($current_family_config['additional_adult']),
			'dependent_count' => $current_dependent_count + 1,
		];

		return [
			'current_family_config' => $current_family_config,
			'next_family_config' => $this->normalize_partner_family_config($next_family_config),
			'dependent_price' => round($dependent_price, 2),
			'prorated_amount' => $prorated_amount,
			'remaining_days' => $remaining_days,
			'total_days' => $total_days,
			'term_end_date' => gmdate('Y-m-d', $term_end_timestamp),
		];
	}

	private function get_autorenew_reactivation_checkout_context($level = null) {
		if (!$this->is_pmpro_checkout_request()) {
			return null;
		}

		if (!$this->is_autorenew_reactivation_checkout_request()) {
			return null;
		}

		$user_id = get_current_user_id();
		if ($user_id <= 0 || !AAC_Member_Portal_PMPro::is_available()) {
			return null;
		}

		$level = is_object($level) ? $level : $this->get_level_at_checkout();
		$level_id = isset($level->id) ? (int) $level->id : 0;
		if ($level_id <= 0) {
			return null;
		}

		$current_membership = AAC_Member_Portal_PMPro::get_primary_membership($user_id);
		if (
			!is_array($current_membership)
			|| (int) ($current_membership['level_id'] ?? 0) !== $level_id
		) {
			return null;
		}

		if (AAC_Member_Portal_PMPro::has_active_auto_renewal($user_id, $level_id)) {
			return null;
		}

		$start_date = $this->normalize_deferred_checkout_date($current_membership['expiration_date'] ?? '');
		if ($start_date === '') {
			$start_date = $this->normalize_deferred_checkout_date($current_membership['renewal_date'] ?? '');
		}
		if ($start_date === '') {
			return null;
		}

		return [
			'level_id' => $level_id,
			'start_date' => $start_date,
			'renewal_amount' => max(0, $this->get_level_recurring_total($level)),
		];
	}

	private function normalize_deferred_checkout_date($date_string) {
		$date_string = sanitize_text_field((string) $date_string);
		$date_string = trim($date_string);
		if ($date_string === '') {
			return '';
		}

		$unix = strtotime($date_string);
		if ($unix === false) {
			return '';
		}

		$today = strtotime(current_time('Y-m-d'));
		if ($today === false || $unix < $today) {
			return '';
		}

		return gmdate('Y-m-d', $unix);
	}

	private function is_autorenew_reactivation_checkout_request() {
		$flag = isset($_REQUEST['aac_reactivate_autorenew']) ? sanitize_text_field(wp_unslash($_REQUEST['aac_reactivate_autorenew'])) : '';
		return $flag === '1';
	}

	private function get_level_at_checkout() {
		if (function_exists('pmpro_getLevelAtCheckout')) {
			$level = pmpro_getLevelAtCheckout();
			if (is_object($level)) {
				return $level;
			}
		}

		global $pmpro_level;
		return is_object($pmpro_level) ? $pmpro_level : null;
	}

	private function get_level_recurring_total($level) {
		if (!is_object($level)) {
			return 0.0;
		}

		$aac_membership_total = $this->get_aac_membership_level_base_total($level);
		if ($aac_membership_total !== null) {
			return $aac_membership_total;
		}

		return $this->get_raw_level_recurring_total($level);
	}

	private function get_level_checkout_initial_total($level) {
		if (!is_object($level)) {
			return 0.0;
		}

		if (isset($level->initial_payment) && $level->initial_payment !== '') {
			return max(0, (float) $level->initial_payment);
		}

		return $this->get_raw_level_recurring_total($level);
	}

	private function should_preserve_prorated_checkout_initial_total($level, $initial_total) {
		if (!is_user_logged_in() || !is_array($this->checkout_membership_change_context) || !is_object($level)) {
			return false;
		}

		$context = $this->checkout_membership_change_context;
		if ((int) ($context['user_id'] ?? 0) !== get_current_user_id()) {
			return false;
		}

		$change_type = (string) ($context['change_type'] ?? '');
		if (!in_array($change_type, ['upgrade', 'level_change'], true)) {
			return false;
		}

		$catalog_total = $this->get_aac_membership_level_base_total($level);
		if ($catalog_total === null) {
			return false;
		}

		$initial_total = max(0, (float) $initial_total);
		return $initial_total < (max(0, (float) $catalog_total) - 0.01);
	}

	private function get_prorated_upgrade_initial_total_for_checkout($level, $incoming_initial_total, $target_recurring_total) {
		if (!is_user_logged_in() || !$this->is_pmpro_checkout_request() || !class_exists('AAC_Member_Portal_PMPro') || !AAC_Member_Portal_PMPro::is_available()) {
			return null;
		}

		$user_id = get_current_user_id();
		$requested_level_id = $this->get_requested_checkout_level_id();
		$target_level_id = is_object($level) && isset($level->id) ? (int) $level->id : $requested_level_id;
		if (
			is_array($this->checkout_membership_change_context)
			&& (int) ($this->checkout_membership_change_context['user_id'] ?? 0) === $user_id
			&& (int) ($this->checkout_membership_change_context['to_level_id'] ?? 0) > 0
		) {
			$target_level_id = (int) $this->checkout_membership_change_context['to_level_id'];
		} elseif ($requested_level_id > 0) {
			$target_level_id = $requested_level_id;
		}
		$current_membership = AAC_Member_Portal_PMPro::get_primary_membership($user_id);
		$current_level_id = is_array($current_membership) ? (int) ($current_membership['level_id'] ?? 0) : 0;
		if ($user_id <= 0 || $target_level_id <= 0 || $current_level_id <= 0 || $target_level_id === $current_level_id) {
			return null;
		}

		$current_rank = AAC_Member_Portal_PMPro::get_tier_rank_for_level_id($current_level_id);
		$target_rank = AAC_Member_Portal_PMPro::get_tier_rank_for_level_id($target_level_id);
		if ($current_rank <= 0 || $target_rank <= 0 || $target_rank <= $current_rank) {
			return null;
		}

		$target_total = $this->get_aac_membership_level_base_total($level);
		if (is_object($level) && isset($level->id) && (int) $level->id !== $target_level_id && function_exists('pmpro_getLevel')) {
			$target_level = pmpro_getLevel($target_level_id);
			if (is_object($target_level)) {
				$target_total = $this->get_aac_membership_level_base_total($target_level);
			}
		}
		if ($target_total === null) {
			$target_total = max(0, (float) $target_recurring_total);
		}

		$current_total = $this->get_aac_membership_level_base_total_by_name((string) ($current_membership['tier'] ?? ''));
		if ($current_total === null && function_exists('pmpro_getLevel')) {
			$current_level = pmpro_getLevel($current_level_id);
			if (is_object($current_level)) {
				$current_total = $this->get_level_recurring_total($current_level);
			}
		}

		$target_total = max(0, (float) $target_total);
		$current_total = max(0, (float) $current_total);
		$annual_difference = $target_total - $current_total;
		if ($annual_difference <= 0) {
			return null;
		}

		$incoming_initial_total = max(0, (float) $incoming_initial_total);
		if ($incoming_initial_total > 0.01 && $incoming_initial_total < ($target_total - 0.01)) {
			return round($incoming_initial_total, 2);
		}

		$term_end_date = sanitize_text_field((string) (($current_membership['renewal_date'] ?? '') ?: ($current_membership['expiration_date'] ?? '')));
		if ($term_end_date === '') {
			return null;
		}

		$term_end_timestamp = strtotime($term_end_date . ' 23:59:59');
		$now_timestamp = current_time('timestamp');
		if ($term_end_timestamp === false || $term_end_timestamp <= $now_timestamp) {
			return null;
		}

		$term_start_date = $this->get_current_membership_term_start_date($user_id, $current_level_id);
		$term_start_timestamp = $term_start_date !== '' ? strtotime($term_start_date . ' 00:00:00') : false;
		$total_seconds = 365 * DAY_IN_SECONDS;
		if ($term_start_timestamp !== false && $term_start_timestamp < $term_end_timestamp) {
			$total_seconds = max(DAY_IN_SECONDS, $term_end_timestamp - $term_start_timestamp);
		}

		$remaining_seconds = max(0, $term_end_timestamp - $now_timestamp);
		$remaining_ratio = min(1, max(0, $remaining_seconds / $total_seconds));
		$prorated_total = round($annual_difference * $remaining_ratio, 2);

		return $prorated_total > 0 ? $prorated_total : null;
	}

	private function get_raw_level_recurring_total($level) {
		if (!is_object($level)) {
			return 0.0;
		}

		$billing_amount = isset($level->billing_amount) ? (float) $level->billing_amount : 0.0;
		if ($billing_amount > 0) {
			return $billing_amount;
		}

		return isset($level->initial_payment) ? (float) $level->initial_payment : 0.0;
	}

	private function get_aac_membership_level_base_total($level) {
		if (!is_object($level)) {
			return null;
		}

		return $this->get_aac_membership_level_base_total_by_name((string) ($level->name ?? ''));
	}

	private function get_aac_membership_level_base_total_by_name($level_name) {
		$level_name = trim((string) $level_name);
		if ($level_name === '') {
			return null;
		}

		$normalized_level_name = $this->normalize_receipt_level_name($level_name);

		$mapped_totals = [
			'free' => 0.0,
			'supporter' => 45.0,
			'partner' => 100.0,
			'partner family' => 100.0,
			'partner adult' => 80.0,
			'partner dependent' => 45.0,
			'partner north america' => 130.0,
			'partner international' => 140.0,
			'leader' => 250.0,
			'advocate' => 500.0,
		];

		return array_key_exists($normalized_level_name, $mapped_totals) ? (float) $mapped_totals[$normalized_level_name] : null;
	}

	public function capture_relevant_fatal() {
		$error = error_get_last();
		if (!$this->is_fatal_error($error)) {
			return;
		}

		$request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
		if (!$this->should_capture_fatal_for_request($request_uri)) {
			return;
		}

		$post_keys = [];
		if (!empty($_POST) && is_array($_POST)) {
			$post_keys = array_values(array_filter(array_keys($_POST), static function ($key) {
				return !in_array($key, ['password', 'password2', 'CVV', 'AccountNumber'], true);
			}));
		}

		update_option('aac_member_portal_last_fatal', [
			'time' => current_time('mysql'),
			'request_uri' => $request_uri,
			'message' => (string) ($error['message'] ?? ''),
			'file' => (string) ($error['file'] ?? ''),
			'line' => (int) ($error['line'] ?? 0),
			'user_id' => get_current_user_id(),
			'post_keys' => $post_keys,
		], false);

		if (class_exists('AAC_Member_Portal_Error_Log')) {
			AAC_Member_Portal_Error_Log::record([
				'severity' => 'critical',
				'area' => $this->is_pmpro_checkout_request() ? 'checkout' : 'member_portal',
				'event_type' => 'fatal_error',
				'user_id' => get_current_user_id(),
				'pmpro_level_id' => $this->get_requested_level_id(),
				'request_uri' => $this->get_current_request_url(),
				'route' => $this->is_pmpro_checkout_request() ? 'membership-checkout' : 'member-portal',
				'message' => (string) ($error['message'] ?? ''),
				'error_code' => 'php_fatal',
				'context' => [
					'file' => (string) ($error['file'] ?? ''),
					'line' => (int) ($error['line'] ?? 0),
					'post_keys' => $post_keys,
					'request_method' => $this->get_request_method(),
				],
			]);
		}
	}

	public function maybe_disable_broken_wp_fusion_pmpro_hooks() {
		if (!$this->is_wp_fusion_pmpro_request_context()) {
			return;
		}

		if (!$this->should_disable_wp_fusion_pmpro_hooks()) {
			return;
		}

		$hooks = [
			'profile_update',
			'pmpro_after_change_membership_level',
		];

		foreach ($hooks as $hook_name) {
			$this->remove_class_callbacks($hook_name, 'WPF_PMPro_Hooks');
		}
	}

	public function maybe_shim_broken_wp_fusion_user_service(...$args) {
		if (!$this->is_wp_fusion_shim_context()) {
			return;
		}

		if (!function_exists('wp_fusion')) {
			return;
		}

		try {
			$fusion = wp_fusion();
		} catch (Throwable $throwable) {
			return;
		}

		if (!is_object($fusion)) {
			return;
		}

		$user = isset($fusion->user) ? $fusion->user : null;
		if (is_object($user) && method_exists($user, 'push_user_meta')) {
			return;
		}

		$fusion->user = new AAC_Member_Portal_Null_WP_Fusion_User();
	}

	public function get_portal_page_url() {
		static $portal_url = null;
		if ($portal_url !== null) {
			return $portal_url;
		}

		$portal_url = home_url('/');

		foreach (['member-profile', 'member-portal', 'membership'] as $preferred_slug) {
			$preferred_page = get_page_by_path($preferred_slug, OBJECT, 'page');
			if (!$preferred_page instanceof WP_Post) {
				$preferred_page_id = url_to_postid(home_url('/' . trim($preferred_slug, '/') . '/'));
				$preferred_page = $preferred_page_id ? get_post($preferred_page_id) : null;
			}

			if ($preferred_page instanceof WP_Post && has_shortcode($preferred_page->post_content, self::SHORTCODE)) {
				$portal_url = get_permalink($preferred_page);
				return $portal_url;
			}
		}

		$query = new WP_Query([
			'post_type' => ['page'],
			'post_status' => 'publish',
			'posts_per_page' => -1,
			's' => '[' . self::SHORTCODE,
			'no_found_rows' => true,
		]);

		if (!empty($query->posts)) {
			foreach ($query->posts as $post) {
				if ($post instanceof WP_Post && has_shortcode($post->post_content, self::SHORTCODE)) {
					$portal_url = get_permalink($post);
					break;
				}
			}
		}

		wp_reset_postdata();

		return $portal_url;
	}

	public function get_portal_manage_membership_url() {
		return untrailingslashit($this->get_portal_page_url() ?: home_url('/membership/')) . '/#/membership';
	}

	public function maybe_redirect_pmpro_account_to_portal_manage() {
		if (is_admin() || wp_doing_ajax()) {
			return;
		}

		$request_path = '';
		if (!empty($_SERVER['REQUEST_URI'])) {
			$request_path = untrailingslashit((string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH));
		}

		$account_url = $this->get_pmpro_page_url('account', '/membership-account/');
		$account_path = untrailingslashit((string) wp_parse_url($account_url, PHP_URL_PATH));
		$fallback_account_path = untrailingslashit('/membership-account');
		$billing_url = $this->get_pmpro_page_url('billing', '/membership-account/membership-billing/');
		$billing_path = untrailingslashit((string) wp_parse_url($billing_url, PHP_URL_PATH));
		$fallback_billing_paths = [
			untrailingslashit('/membership-account/membership-billing'),
			untrailingslashit('/membership-billing'),
		];

		if (
			$request_path !== ''
			&& ($request_path === $account_path || $request_path === $fallback_account_path)
		) {
			wp_safe_redirect($this->get_portal_manage_membership_url());
			exit;
		}

		if (
			is_user_logged_in()
			&& $request_path !== ''
			&& ($request_path === $billing_path || in_array($request_path, $fallback_billing_paths, true))
		) {
			$user_id = get_current_user_id();
			$primary_membership = AAC_Member_Portal_PMPro::get_primary_membership($user_id);
			$actions = AAC_Member_Portal_PMPro::build_membership_actions($user_id, [
				'tier' => is_array($primary_membership) ? ($primary_membership['tier'] ?? '') : '',
			]);
			if (empty($actions['current_subscription_id'])) {
				wp_safe_redirect($this->get_portal_manage_membership_url());
				exit;
			}
		}
	}

	public function maybe_redirect_wpengine_signup_to_native_checkout() {
		if (is_admin() || wp_doing_ajax()) {
			return;
		}

		$request_path = '';
		if (!empty($_SERVER['REQUEST_URI'])) {
			$request_path = untrailingslashit((string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH));
		}
		$is_signup_request = basename(trim($request_path, '/')) === self::SIGNUP_PAGE_SLUG;
		$native_checkout_url = $this->get_pmpro_page_url('checkout', '/membership-checkout/');
		$native_checkout_path = untrailingslashit((string) wp_parse_url($native_checkout_url, PHP_URL_PATH));
		$is_checkout_request = $native_checkout_path && $request_path === $native_checkout_path;
		$is_embedded_checkout = isset($_GET['aac_embed']) && sanitize_text_field(wp_unslash($_GET['aac_embed'])) === '1';

		if ($is_embedded_checkout || (!$is_signup_request && !$is_checkout_request)) {
			return;
		}

		wp_safe_redirect(untrailingslashit($this->get_portal_page_url()) . '/#/join', 302, 'AAC Member Portal');
		exit;
	}

	public function render_shortcode($atts = []) {
		$atts = shortcode_atts(
			[
				'mode' => '',
				'embed' => '',
			],
			is_array($atts) ? $atts : [],
			self::SHORTCODE
		);

		$embed_mode = sanitize_key((string) ($atts['mode'] ?: $atts['embed']));
		if (in_array($embed_mode, ['signup', 'join'], true)) {
			return $this->render_app_mount('signup');
		}
		if (in_array($embed_mode, ['login', 'signin', 'sign-in'], true)) {
			return $this->render_app_mount('login');
		}

		return $this->render_app_mount();
	}

	public function render_login_shortcode() {
		return $this->render_app_mount('login');
	}

	public function render_signup_shortcode() {
		return $this->render_app_mount('signup');
	}

	public function should_render_native_signup($post = null) {
		return false;
	}

	public function render_native_signup_level_selector() {
		$checkout_url = $this->get_pmpro_page_url('checkout', '/membership-checkout/');
		$level_ids = $this->get_membership_level_ids();
		$level_names = ['Supporter', 'Partner', 'Leader', 'Advocate'];
		$cards = '';

		foreach ($level_names as $level_name) {
			$level_id = isset($level_ids[$level_name]) ? (int) $level_ids[$level_name] : 0;
			if ($level_id <= 0) {
				continue;
			}

			$level = function_exists('pmpro_getLevel') ? pmpro_getLevel($level_id) : null;
			$display_name = is_object($level) && !empty($level->name) ? (string) $level->name : $level_name;
			$description = is_object($level) && !empty($level->description) ? (string) $level->description : '';
			$billing_amount = is_object($level) && isset($level->billing_amount) ? (float) $level->billing_amount : 0;
			$cycle_period = is_object($level) && !empty($level->cycle_period) ? strtolower((string) $level->cycle_period) : 'year';
			$price = $billing_amount > 0
				? '$' . number_format_i18n($billing_amount, 0) . ' / ' . $cycle_period
				: 'Free';
			$level_url = add_query_arg(
				[
					'level' => $level_id,
					'aac_wizard' => '0',
				],
				$checkout_url
			);
			$is_featured = strtolower($level_name) === 'partner';

			$cards .= '<article class="aac-native-level-card' . ($is_featured ? ' is-featured' : '') . '">';
			if ($is_featured) {
				$cards .= '<p class="aac-native-level-card__eyebrow">Most Popular</p>';
			}
			$cards .= '<h2>' . esc_html($display_name) . '</h2>';
			$cards .= '<p class="aac-native-level-card__price">' . esc_html($price) . '</p>';
			if ($description !== '') {
				$cards .= '<div class="aac-native-level-card__description">' . wp_kses_post($description) . '</div>';
			}
			$cards .= '<a class="aac-native-level-card__button" href="' . esc_url($level_url) . '">Choose ' . esc_html($display_name) . '</a>';
			$cards .= '</article>';
		}

		return '<style id="aac-native-signup-selector-styles">
			.aac-native-signup-page{margin:0;background:#fff;color:#030000;font-family:futura-pt,Futura,"Futura PT","Century Gothic","Trebuchet MS","Gill Sans",ui-sans-serif,sans-serif;font-size:16px;line-height:24px}
			.aac-native-member-header{border-top:1px solid rgba(0,0,0,.08);border-bottom:1px solid rgba(0,0,0,.08);background:#fff}
			.aac-native-member-header__inner{display:flex;max-width:1024px;min-height:64px;align-items:center;justify-content:space-between;gap:1.5rem;margin:0 auto;padding:0 24px}
			.aac-native-member-header__brand{color:#030000;font-family:futura-pt-bold,futura-pt,sans-serif;font-size:.75rem;font-weight:700;letter-spacing:.18em;text-decoration:none;text-transform:uppercase}
			.aac-native-member-header__actions{display:flex;align-items:center;gap:1.5rem}
			.aac-native-member-header__actions a{color:#030000;font-size:.875rem;text-decoration:none}
			.aac-native-member-header__actions a:hover{color:#b71c1c}
			.aac-native-signup-selector{max-width:1024px;margin:0 auto;padding:32px 24px;color:#030000;font-size:16px!important;line-height:24px!important}
			.aac-native-signup-selector__header{margin-bottom:32px;border-bottom:2px solid #b71c1c;padding:24px 0}
			.aac-native-signup-selector__kicker,.aac-native-level-card__eyebrow{margin:0 0 8px;color:#b71c1c;font-size:.68rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase}
			.aac-native-signup-selector h1{margin:0;color:#000;font-family:futura-pt-bold,futura-pt,sans-serif;font-size:clamp(2rem,4vw,3rem)!important;font-weight:700;line-height:1.1!important}
			.aac-native-signup-selector__intro{max-width:720px;margin:12px 0 0;color:#666;font-size:1rem;line-height:1.5}
			.aac-native-signup-selector__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px 32px;align-items:stretch}
			.aac-native-level-card{display:flex;min-width:0;flex-direction:column;border:0;border-top:2px solid #d9d9d9;border-radius:0;background:#fff;padding:24px 0;box-shadow:none}
			.aac-native-level-card.is-featured{border-top-color:#b71c1c}
			.aac-native-level-card h2{margin:0;color:#000;font-family:futura-pt-bold,futura-pt,sans-serif;font-size:1.25rem;font-weight:700;line-height:1.4}
			.aac-native-level-card__price{margin:8px 0 16px;color:#000;font-size:1.25rem;font-weight:700}
			.aac-native-level-card__description{margin-bottom:24px;color:#666;font-size:1rem;line-height:1.5}
			.aac-native-level-card__description p:first-child{margin-top:0}
			.aac-native-level-card__description p:last-child{margin-bottom:0}
			.aac-native-level-card__button{display:flex;min-height:48px;align-items:center;justify-content:center;margin-top:auto;border:0;border-radius:0;background:#b71c1c;color:#fff!important;font-size:1rem;font-weight:400;line-height:24px;text-align:center;text-decoration:none;padding:8px 24px;box-shadow:none}
			.aac-native-level-card__button:hover{background:#8f1515}
			.aac-native-level-card__button:focus{outline:2px solid #c8a43a;outline-offset:2px}
			@media(max-width:640px){.aac-native-member-header__inner{min-height:56px;padding:0 16px}.aac-native-signup-selector{padding:24px 16px}.aac-native-signup-selector__grid{grid-template-columns:1fr;gap:8px}.aac-native-level-card{padding:24px 0}.aac-native-member-header__actions a:first-child{display:none}}
		</style><header class="aac-native-member-header" aria-label="Member portal navigation"><div class="aac-native-member-header__inner"><a class="aac-native-member-header__brand" href="' . esc_url(home_url('/')) . '">AAC Member App</a><nav class="aac-native-member-header__actions" aria-label="Account links"><a href="' . esc_url(home_url('/member-profile/#/discounts')) . '">Member Benefits</a><a href="' . esc_url(home_url('/member-profile/#/login')) . '">Sign In</a></nav></div></header><section class="aac-native-signup-selector"><header class="aac-native-signup-selector__header"><p class="aac-native-signup-selector__kicker">AAC Membership</p><h1>Choose your membership</h1><p class="aac-native-signup-selector__intro">Select the membership level that fits you. You will complete account details and payment securely through the native AAC checkout.</p></header><div class="aac-native-signup-selector__grid">' . $cards . '</div></section>';
	}

	private function render_native_signup_level_page() {
		status_header(200);
		nocache_headers();
		?><!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo('charset'); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php echo esc_html__('Choose Your AAC Membership', 'aac-member-portal'); ?></title>
			<?php wp_head(); ?>
		</head>
		<body <?php body_class('aac-native-signup-page'); ?>>
		<?php wp_body_open(); ?>
		<main id="main-content">
			<?php echo $this->render_native_signup_level_selector(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</main>
		<?php wp_footer(); ?>
		</body>
		</html><?php
	}

	private function is_wpengine_signup_site() {
		// WP Engine rewrites the public host to the canonical domain before PHP
		// runs. The install path is the stable, environment-specific identifier.
		if (defined('ABSPATH') && strpos(wp_normalize_path(ABSPATH), '/nas/content/live/americanalpine/') === 0) {
			return true;
		}

		$request_host = '';
		if (!empty($_SERVER['HTTP_HOST'])) {
			$request_host = strtolower(preg_replace('/:\\d+$/', '', (string) wp_unslash($_SERVER['HTTP_HOST'])));
		}
		if ($request_host === 'americanalpine.wpenginepowered.com') {
			return true;
		}

		$host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
		return $host === 'americanalpine.wpenginepowered.com';
	}

	public static function install_brand_discounts_page() {
		if (!function_exists('get_page_by_path') || !function_exists('wp_insert_post')) {
			return;
		}

		self::ensure_brand_discounts_page(self::BRAND_DISCOUNTS_PAGE_SLUG, 'Member Discounts');
	}

	public static function install_signup_page() {
		if (!function_exists('get_page_by_path') || !function_exists('wp_insert_post')) {
			return;
		}

		$page = get_page_by_path(self::SIGNUP_PAGE_SLUG, OBJECT, 'page');
		if ($page instanceof WP_Post) {
			$is_managed = get_post_meta($page->ID, '_aac_member_portal_managed_signup_page', true) === '1';
			if ($is_managed && !has_shortcode($page->post_content, self::SIGNUP_SHORTCODE)) {
				wp_update_post([
					'ID' => $page->ID,
					'post_content' => '[' . self::SIGNUP_SHORTCODE . ']',
				]);
			}
			return;
		}

		$page_id = wp_insert_post([
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_title' => 'Sign Up',
			'post_name' => self::SIGNUP_PAGE_SLUG,
			'post_content' => '[' . self::SIGNUP_SHORTCODE . ']',
			'comment_status' => 'closed',
			'ping_status' => 'closed',
		], true);

		if (!is_wp_error($page_id) && $page_id) {
			update_post_meta($page_id, '_aac_member_portal_managed_signup_page', '1');
		}
	}

	private static function ensure_brand_discounts_page($slug, $title) {
		$page = get_page_by_path($slug, OBJECT, 'page');
		if ($page instanceof WP_Post) {
			$is_managed = get_post_meta($page->ID, '_aac_member_portal_managed_discount_page', true) === '1';
			if ($is_managed && !has_shortcode($page->post_content, self::BRAND_DISCOUNTS_SHORTCODE)) {
				wp_update_post([
					'ID' => $page->ID,
					'post_content' => '[' . self::BRAND_DISCOUNTS_SHORTCODE . ']',
				]);
				update_post_meta($page->ID, '_aac_member_portal_managed_discount_page', '1');
			}
			return;
		}

		$page_id = wp_insert_post([
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_title' => $title,
			'post_name' => $slug,
			'post_content' => '[' . self::BRAND_DISCOUNTS_SHORTCODE . ']',
			'comment_status' => 'closed',
			'ping_status' => 'closed',
		], true);

		if (!is_wp_error($page_id) && $page_id) {
			update_post_meta((int) $page_id, '_aac_member_portal_managed_discount_page', '1');
		}
	}

	public function maybe_install_brand_discounts_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		if (get_option('aac_member_portal_brand_discounts_page_version') === AAC_MEMBER_PORTAL_VERSION) {
			return;
		}

		self::install_brand_discounts_page();
		update_option('aac_member_portal_brand_discounts_page_version', AAC_MEMBER_PORTAL_VERSION, false);
	}

	public function maybe_install_signup_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$installed_version = (string) get_option('aac_member_portal_signup_page_version', '');
		if ($installed_version === AAC_MEMBER_PORTAL_VERSION) {
			return;
		}

		self::install_signup_page();
		update_option('aac_member_portal_signup_page_version', AAC_MEMBER_PORTAL_VERSION, false);
	}

	public function render_brand_discounts_shortcode($atts = []) {
		$atts = shortcode_atts(
			[
				'show_locked' => 'true',
				'show_search' => 'true',
			],
			is_array($atts) ? $atts : [],
			self::BRAND_DISCOUNTS_SHORTCODE
		);

		$settings = AAC_Member_Portal_Admin::get_settings();
		$content = isset($settings['content']) && is_array($settings['content']) ? $settings['content'] : [];
		$title = sanitize_text_field($content['discounts_title'] ?? 'Partner Discounts');
		$button_label = sanitize_text_field($content['discounts_button_label'] ?? 'Visit Website');
		$cards = isset($content['discount_cards']) && is_array($content['discount_cards'])
			? array_values($content['discount_cards'])
			: [];
		if (empty($cards)) {
			$cards = AAC_Member_Portal_Admin::get_default_discount_cards();
		}

		$base_benefits_url = get_permalink();
		if (!$base_benefits_url) {
			$base_benefits_url = home_url('/benefits/');
		}
		$benefits_view = isset($_GET['aac_benefits_view'])
			? sanitize_key(wp_unslash($_GET['aac_benefits_view']))
			: '';
		$discounts_view_url = add_query_arg('aac_benefits_view', 'discounts', $base_benefits_url);
		$gallery_view_url = remove_query_arg('aac_benefits_view', $base_benefits_url);

		if ($benefits_view !== 'discounts') {
			ob_start();
			echo $this->render_brand_discounts_styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $this->render_member_benefits_gallery($discounts_view_url); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return ob_get_clean();
		}

		$member_context = $this->get_brand_discounts_member_context();
		$show_locked = $atts['show_locked'] !== 'false';
		$show_search = $atts['show_search'] !== 'false';
		$normalized_cards = $this->normalize_brand_discount_cards($cards);
		$visible_cards = $member_context['can_access']
			? array_values(array_filter($normalized_cards, function ($card) use ($member_context) {
				return $this->is_brand_discount_card_visible_for_tier($card, $member_context['tier']);
			}))
			: $normalized_cards;
		$card_count = count($visible_cards);
		$categories = $this->get_brand_discount_categories();
		$brand_tier_labels = $this->get_brand_discount_brand_tiers();
		$brand_tier_groups = $this->get_brand_discount_cards_grouped_by_brand_tier($visible_cards);

		ob_start();
		echo $this->render_brand_discounts_styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<section class="aac-brand-discounts-page" data-aac-brand-discounts>
			<header class="aac-brand-discounts-page__header">
				<p class="aac-brand-discounts-page__kicker"><?php esc_html_e('Member Benefits', 'aac-member-portal'); ?></p>
				<div class="aac-brand-discounts-page__heading-row">
					<div>
						<h1><?php echo esc_html($title); ?></h1>
						<p><?php esc_html_e('Browse AAC member benefits and partner offers for your membership level. Benefits unlock for active paid members.', 'aac-member-portal'); ?></p>
					</div>
					<div class="aac-brand-discounts-page__meta">
						<a class="aac-brand-discounts-page__gallery-link" href="<?php echo esc_url($gallery_view_url); ?>"><?php esc_html_e('Benefit Gallery', 'aac-member-portal'); ?></a>
						<span class="aac-brand-discounts-page__count"><?php echo esc_html(sprintf(_n('%d benefit', '%d benefits', $card_count, 'aac-member-portal'), $card_count)); ?></span>
					</div>
				</div>
			</header>

			<?php if (!$member_context['can_access'] && $show_locked) : ?>
				<?php echo $this->render_brand_discounts_locked_state($member_context, $content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php else : ?>
				<div class="aac-brand-discounts-tabs" role="tablist" aria-label="<?php esc_attr_e('Benefit categories', 'aac-member-portal'); ?>">
					<?php foreach ($categories as $category_id => $category_label) : ?>
						<button type="button" class="aac-brand-discounts-tab<?php echo $category_id === 'discount-brands' ? ' is-active' : ''; ?>" data-aac-brand-discounts-tab="<?php echo esc_attr($category_id); ?>">
							<?php echo esc_html($category_label); ?>
						</button>
					<?php endforeach; ?>
				</div>
				<?php if ($show_search) : ?>
					<div class="aac-brand-discounts-page__tools">
						<label for="aac-brand-discounts-search"><?php esc_html_e('Search brands', 'aac-member-portal'); ?></label>
						<input id="aac-brand-discounts-search" type="search" placeholder="<?php esc_attr_e('Search by brand or offer', 'aac-member-portal'); ?>" data-aac-brand-discounts-search>
					</div>
				<?php endif; ?>

				<div class="aac-brand-discounts-panel is-active" data-aac-brand-discounts-panel="discount-brands">
					<header class="aac-benefit-directory__hero aac-benefit-directory__hero--brands">
						<img src="https://images.unsplash.com/photo-1516592673884-4a382d1124c2?auto=format&fit=crop&w=1800&q=82" alt="" loading="lazy">
						<div>
							<p><?php esc_html_e('Discount Brands', 'aac-member-portal'); ?></p>
							<h2><?php esc_html_e('Climbing gear and partner offers', 'aac-member-portal'); ?></h2>
							<span><?php esc_html_e('Browse AAC member discounts on climbing gear, outdoor equipment, apparel, footwear, and partner products.', 'aac-member-portal'); ?></span>
						</div>
					</header>
					<?php foreach ($brand_tier_groups as $brand_tier_id => $tier_cards) : ?>
						<?php if (empty($tier_cards)) { continue; } ?>
						<section class="aac-brand-discounts-tier" data-aac-brand-tier="<?php echo esc_attr($brand_tier_id); ?>">
							<h2><?php echo esc_html($brand_tier_labels[$brand_tier_id] ?? __('Middle Brand', 'aac-member-portal')); ?></h2>
							<div class="aac-brand-discounts-grid">
								<?php foreach ($tier_cards as $index => $card) : ?>
									<?php echo $this->render_brand_discount_card($card, $index, $member_context, $button_label); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php endforeach; ?>
							</div>
						</section>
					<?php endforeach; ?>
				</div>
				<div class="aac-brand-discounts-panel" data-aac-brand-discounts-panel="expertvoice" hidden>
					<?php echo $this->render_expertvoice_benefit_panel($visible_cards); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="aac-brand-discounts-panel" data-aac-brand-discounts-panel="climbing-guides" hidden>
					<?php echo $this->render_directory_benefit_panel($visible_cards, $member_context, 'climbing-guides'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="aac-brand-discounts-panel" data-aac-brand-discounts-panel="climbing-gyms" hidden>
					<?php echo $this->render_directory_benefit_panel($visible_cards, $member_context, 'climbing-gyms'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>

				<p class="aac-brand-discounts-page__empty" data-aac-brand-discounts-empty hidden><?php esc_html_e('No matching brands found.', 'aac-member-portal'); ?></p>
				<?php echo $this->render_brand_discounts_script(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	private function render_member_benefits_gallery($discounts_url) {
		$settings = AAC_Member_Portal_Admin::get_settings();
		$items = isset($settings['content']['benefits_gallery_items']) && is_array($settings['content']['benefits_gallery_items'])
			? array_values($settings['content']['benefits_gallery_items'])
			: AAC_Member_Portal_Admin::get_default_benefits_gallery_items();

		ob_start();
		?>
		<section class="aac-brand-discounts-page aac-benefits-gallery-page">
			<header class="aac-brand-discounts-page__header">
				<p class="aac-brand-discounts-page__kicker"><?php esc_html_e('Member Benefits', 'aac-member-portal'); ?></p>
				<div class="aac-brand-discounts-page__heading-row">
					<div>
						<h1><?php esc_html_e('AAC Benefits', 'aac-member-portal'); ?></h1>
						<p><?php esc_html_e('Explore membership benefits across discounts, rescue support, publications, library access, lodging, and grants.', 'aac-member-portal'); ?></p>
					</div>
				</div>
			</header>
			<div class="aac-benefits-gallery-grid">
				<?php foreach ($items as $item) : ?>
					<?php
					$item_id = sanitize_key($item['id'] ?? '');
					$item_url = esc_url_raw($item['url'] ?? '');
					if ($item_id === 'discounts' && $item_url === '') {
						$item_url = $discounts_url;
					}
					$tag_name = $item_url !== '' ? 'a' : 'article';
					?>
					<<?php echo esc_html($tag_name); ?> class="aac-benefits-gallery-card"<?php echo $item_url !== '' ? ' href="' . esc_url($item_url) . '"' : ''; ?>>
						<div class="aac-benefits-gallery-card__image">
							<img src="<?php echo esc_url($item['image_url']); ?>" alt="" loading="lazy">
						</div>
						<div class="aac-benefits-gallery-card__body">
							<h2 class="aac-benefits-gallery-card__title"><?php echo esc_html($item['title']); ?></h2>
							<p class="aac-benefits-gallery-card__description"><?php echo esc_html($item['description'] ?? ''); ?></p>
							<?php if (!empty($item['action_label'])) : ?>
								<span class="aac-benefits-gallery-card__cta"><?php echo esc_html($item['action_label']); ?></span>
							<?php endif; ?>
						</div>
					</<?php echo esc_html($tag_name); ?>>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	private function get_brand_discounts_member_context() {
		$context = [
			'is_logged_in' => is_user_logged_in(),
			'status' => 'Inactive',
			'tier' => '',
			'tier_label' => 'Member',
			'is_free' => false,
			'can_access' => false,
		];

		if (!$context['is_logged_in']) {
			return $context;
		}

		$user_id = get_current_user_id();
		$membership = $user_id ? AAC_Member_Portal_PMPro::get_primary_membership($user_id) : null;
		if (!$membership) {
			return $context;
		}

		$tier = $this->normalize_discount_membership_tier($membership['tier'] ?? '');
		$is_free = $tier === 'Free';

		return [
			'is_logged_in' => true,
			'status' => 'Active',
			'tier' => $tier,
			'tier_label' => $tier ?: 'Member',
			'is_free' => $is_free,
			'can_access' => !$is_free,
		];
	}

	private function normalize_discount_membership_tier($tier) {
		$tier = trim((string) $tier);
		if ($tier === '') {
			return '';
		}

		$lower = strtolower($tier);
		if (strpos($lower, 'free') !== false) {
			return 'Free';
		}
		if (strpos($lower, 'supporter') !== false) {
			return 'Supporter';
		}
		if (strpos($lower, 'partner') !== false) {
			return 'Partner';
		}
		if (strpos($lower, 'leader') !== false) {
			return 'Leader';
		}
		if (strpos($lower, 'advocate') !== false) {
			return 'Advocate';
		}
		if (strpos($lower, 'grf') !== false) {
			return 'GRF';
		}
		if (strpos($lower, 'lifetime') !== false) {
			return 'Lifetime';
		}

		return $tier;
	}

	private function normalize_brand_discount_cards($cards) {
		$normalized = [];
		foreach ((array) $cards as $index => $card) {
			if (!is_array($card)) {
				continue;
			}

			$next = [
				'id' => sanitize_title(($card['brand'] ?? 'discount') . '-' . $index),
				'brand' => sanitize_text_field($card['brand'] ?? ''),
				'category' => $this->normalize_brand_discount_category($card['category'] ?? ''),
				'brand_tier' => $this->normalize_brand_discount_brand_tier($card['brand_tier'] ?? 'middle'),
				'discount_percent' => sanitize_text_field($card['discount_percent'] ?? ''),
				'discount_code_text' => sanitize_textarea_field($card['discount_code_text'] ?? ''),
				'discount_code_text_supporter' => sanitize_textarea_field($card['discount_code_text_supporter'] ?? ''),
				'discount_code_text_partner' => sanitize_textarea_field($card['discount_code_text_partner'] ?? ''),
				'discount_code_text_leader' => sanitize_textarea_field($card['discount_code_text_leader'] ?? ''),
				'discount_code_text_advocate' => sanitize_textarea_field($card['discount_code_text_advocate'] ?? ''),
				'discount_percent_supporter' => sanitize_text_field($card['discount_percent_supporter'] ?? ''),
				'discount_percent_partner' => sanitize_text_field($card['discount_percent_partner'] ?? ''),
				'discount_percent_leader' => sanitize_text_field($card['discount_percent_leader'] ?? ''),
				'discount_percent_advocate' => sanitize_text_field($card['discount_percent_advocate'] ?? ''),
				'visible_tiers' => $this->normalize_brand_discount_visible_tiers($card['visible_tiers'] ?? null),
				'display_text' => sanitize_textarea_field($card['display_text'] ?? ''),
				'button_url' => esc_url_raw($card['button_url'] ?? ''),
				'image_url' => esc_url_raw($card['image_url'] ?? ''),
			];

			$has_content = false;
			foreach ($next as $key => $value) {
				if (!in_array($key, ['id', 'visible_tiers', 'brand_tier'], true) && $value !== '') {
					$has_content = true;
					break;
				}
			}

			if (!$has_content) {
				continue;
			}

			foreach (['supporter', 'partner', 'leader', 'advocate'] as $tier_key) {
				$field = 'discount_percent_' . $tier_key;
				if ($next[$field] === '' && $next['discount_percent'] !== '') {
					$next[$field] = $next['discount_percent'];
				}
			}

			$normalized[] = $next;
		}

		return $normalized;
	}

	private function normalize_brand_discount_visible_tiers($visible_tiers) {
		$tier_keys = ['supporter', 'partner', 'leader', 'advocate'];
		if (!is_array($visible_tiers)) {
			return array_fill_keys($tier_keys, true);
		}

		$normalized = [];
		foreach ($tier_keys as $tier_key) {
			$normalized[$tier_key] = !empty($visible_tiers[$tier_key]);
		}

		return $normalized;
	}

	private function get_brand_discount_brand_tiers() {
		return [
			'top' => __('Top Brand', 'aac-member-portal'),
			'middle' => __('Middle Brand', 'aac-member-portal'),
			'lower' => __('Lower Brand', 'aac-member-portal'),
		];
	}

	private function normalize_brand_discount_brand_tier($brand_tier) {
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

		return array_key_exists($brand_tier, $this->get_brand_discount_brand_tiers()) ? $brand_tier : 'middle';
	}

	private function get_brand_discount_cards_grouped_by_brand_tier($cards) {
		$groups = [];
		foreach (array_keys($this->get_brand_discount_brand_tiers()) as $brand_tier_id) {
			$groups[$brand_tier_id] = [];
		}

		foreach ($cards as $card) {
			if (($card['category'] ?? 'discount-brands') !== 'discount-brands') {
				continue;
			}

			$brand_tier = $this->normalize_brand_discount_brand_tier($card['brand_tier'] ?? 'middle');
			$groups[$brand_tier][] = $card;
		}

		return $groups;
	}

	private function get_brand_discount_categories() {
		return [
			'discount-brands' => __('Discount Brands', 'aac-member-portal'),
			'expertvoice' => __('ExpertVoice', 'aac-member-portal'),
			'climbing-guides' => __('Climbing Guides', 'aac-member-portal'),
			'climbing-gyms' => __('Climbing Gym Discounts', 'aac-member-portal'),
		];
	}

	private function normalize_brand_discount_category($category) {
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

		return array_key_exists($category, $this->get_brand_discount_categories()) ? $category : 'discount-brands';
	}

	private function get_brand_discount_tier_key($tier) {
		switch ($tier) {
			case 'Supporter':
				return 'supporter';
			case 'Partner':
				return 'partner';
			case 'Leader':
				return 'leader';
			case 'Advocate':
			case 'GRF':
			case 'Lifetime':
				return 'advocate';
			default:
				return '';
		}
	}

	private function is_brand_discount_card_visible_for_tier($card, $tier) {
		if (($card['category'] ?? 'discount-brands') !== 'discount-brands') {
			return true;
		}

		$tier_key = $this->get_brand_discount_tier_key($tier);
		if ($tier_key === '') {
			return false;
		}

		$visible_tiers = isset($card['visible_tiers']) && is_array($card['visible_tiers'])
			? $card['visible_tiers']
			: $this->normalize_brand_discount_visible_tiers(null);

		return !empty($visible_tiers[$tier_key]);
	}

	private function resolve_brand_discount_percent($card, $tier) {
		if (($card['category'] ?? 'discount-brands') !== 'discount-brands') {
			return $card['discount_percent']
				?: ($card['discount_percent_supporter']
					?: ($card['discount_percent_partner']
						?: ($card['discount_percent_leader'] ?: $card['discount_percent_advocate'])));
		}

		switch ($tier) {
			case 'Supporter':
				return $card['discount_percent_supporter'];
			case 'Partner':
				return $card['discount_percent_partner'];
			case 'Leader':
				return $card['discount_percent_leader'];
			case 'Advocate':
			case 'GRF':
			case 'Lifetime':
				return $card['discount_percent_advocate'];
			default:
				return '';
		}
	}

	private function resolve_brand_discount_code_text($card, $tier) {
		if (($card['category'] ?? 'discount-brands') !== 'discount-brands') {
			return $card['discount_code_text']
				?: ($card['discount_code_text_supporter']
					?: ($card['discount_code_text_partner']
						?: ($card['discount_code_text_leader'] ?: $card['discount_code_text_advocate'])));
		}

		switch ($tier) {
			case 'Supporter':
				return $card['discount_code_text_supporter'] ?: $card['discount_code_text'];
			case 'Partner':
				return $card['discount_code_text_partner'] ?: $card['discount_code_text'];
			case 'Leader':
				return $card['discount_code_text_leader'] ?: $card['discount_code_text'];
			case 'Advocate':
			case 'GRF':
			case 'Lifetime':
				return $card['discount_code_text_advocate'] ?: ($card['discount_code_text_leader'] ?: $card['discount_code_text']);
			default:
				return $card['discount_code_text'];
		}
	}

	private function render_brand_discounts_locked_state($context, $content) {
		$title = sanitize_text_field($content['discounts_locked_title'] ?? 'Discounts Locked');
		$description = $context['is_free']
			? sanitize_textarea_field($content['discounts_free_locked_description'] ?? '')
			: sanitize_textarea_field($content['discounts_locked_description'] ?? '');
		$hint = sanitize_textarea_field($content['discounts_upgrade_hint'] ?? '');
		$portal_url = untrailingslashit($this->get_portal_page_url() ?: home_url('/membership/'));
		$login_url = $portal_url . '/#/login';
		$upgrade_url = $portal_url . '/#/membership';

		ob_start();
		?>
		<div class="aac-brand-discounts-lock">
			<h2><?php echo esc_html($title); ?></h2>
			<?php if ($description) : ?>
				<p><?php echo esc_html($description); ?></p>
			<?php endif; ?>
			<?php if ($context['is_free'] && $hint) : ?>
				<p class="aac-brand-discounts-lock__hint"><?php echo esc_html($hint); ?></p>
			<?php endif; ?>
			<div class="aac-brand-discounts-lock__actions">
				<?php if ($context['is_logged_in']) : ?>
					<a href="<?php echo esc_url($upgrade_url); ?>"><?php esc_html_e('Upgrade Membership', 'aac-member-portal'); ?></a>
				<?php else : ?>
					<a href="<?php echo esc_url($login_url); ?>"><?php esc_html_e('Sign In', 'aac-member-portal'); ?></a>
					<a href="<?php echo esc_url($upgrade_url); ?>" class="aac-brand-discounts-lock__secondary"><?php esc_html_e('Join AAC', 'aac-member-portal'); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_brand_discount_card($card, $index, $context, $button_label) {
		$percent = $this->resolve_brand_discount_percent($card, $context['tier']);
		$code_text = $this->resolve_brand_discount_code_text($card, $context['tier']);
		$details = $card['display_text'];
		$detail_text = $code_text ?: $details;
		$is_tier_specific = ($card['category'] ?? 'discount-brands') === 'discount-brands';
		$search_text = strtolower(trim($card['brand'] . ' ' . $percent . ' ' . $code_text . ' ' . $details));
		$initial = $card['brand'] !== '' ? strtoupper(substr($card['brand'], 0, 1)) : 'A';

		ob_start();
		?>
		<article class="aac-brand-discount-card" data-aac-brand-discount-card data-category="<?php echo esc_attr($card['category']); ?>" data-search="<?php echo esc_attr($search_text); ?>">
			<div class="aac-brand-discount-card__media">
				<?php if ($card['image_url']) : ?>
					<img src="<?php echo esc_url($card['image_url']); ?>" alt="<?php echo esc_attr($card['brand'] ?: __('AAC discount partner', 'aac-member-portal')); ?>" loading="lazy">
				<?php else : ?>
					<div class="aac-brand-discount-card__placeholder" aria-hidden="true"><?php echo esc_html($initial); ?></div>
				<?php endif; ?>
			</div>
			<div class="aac-brand-discount-card__body">
				<p class="aac-brand-discount-card__eyebrow"><?php esc_html_e('Member Benefit', 'aac-member-portal'); ?></p>
				<h2><?php echo esc_html($card['brand'] ?: __('AAC Partner', 'aac-member-portal')); ?></h2>
				<?php if ($percent) : ?>
					<p class="aac-brand-discount-card__percent">
						<?php echo esc_html($percent); ?>
						<?php if ($is_tier_specific) : ?>
							<span><?php echo esc_html($context['tier_label']); ?></span>
						<?php endif; ?>
					</p>
				<?php endif; ?>
				<?php if ($detail_text || $card['button_url']) : ?>
					<details class="aac-brand-discount-card__details">
						<summary><?php esc_html_e('More Details', 'aac-member-portal'); ?></summary>
						<?php if ($detail_text) : ?>
							<div class="aac-brand-discount-card__detail-copy"><?php echo wp_kses_post(wpautop(esc_html($detail_text))); ?></div>
						<?php endif; ?>
						<?php if ($card['button_url']) : ?>
							<a class="aac-brand-discount-card__button" href="<?php echo esc_url($card['button_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($button_label); ?></a>
						<?php endif; ?>
					</details>
				<?php endif; ?>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}

	private function render_expertvoice_benefit_panel($cards) {
		$expertvoice_cards = array_values(array_filter($cards, static function ($card) {
			return ($card['category'] ?? '') === 'expertvoice';
		}));
		$card = $expertvoice_cards[0] ?? [];
		$image_url = 'https://cdn.expertvoice.com/static-forever/public-web/c418c16bb8a97490.svg';
		$button_url = 'https://www.expertvoice.com/';
		$details = [
			__('ExpertVoice connects qualified members, professionals, and enthusiasts with brands that want trusted product recommendations in the field. AAC members can use the platform to verify eligibility and browse member-only offers from participating brands.', 'aac-member-portal'),
			__('Offers commonly include pro pricing, limited-time campaigns, product education, and discounts on outdoor gear, climbing equipment, apparel, footwear, training tools, travel essentials, and other active-lifestyle products.', 'aac-member-portal'),
			__('Create or sign in to ExpertVoice, complete the AAC member verification steps, and follow the current ExpertVoice instructions to unlock eligible offers.', 'aac-member-portal'),
		];

		ob_start();
		?>
		<section class="aac-benefit-feature aac-benefit-feature--expertvoice">
			<header class="aac-benefit-directory__hero aac-benefit-directory__hero--expertvoice">
				<img src="https://images.unsplash.com/photo-1522163182402-834f871fd851?auto=format&fit=crop&w=1800&q=82" alt="" loading="lazy">
				<div>
					<p><?php esc_html_e('ExpertVoice', 'aac-member-portal'); ?></p>
					<h2><?php esc_html_e('Climbing gear offers through ExpertVoice', 'aac-member-portal'); ?></h2>
					<span><?php esc_html_e('Access member-only outdoor, climbing, training, and active-lifestyle offers through the ExpertVoice platform.', 'aac-member-portal'); ?></span>
				</div>
			</header>
			<div class="aac-benefit-feature__content">
				<a class="aac-benefit-feature__logo-link" href="<?php echo esc_url($button_url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('Visit ExpertVoice', 'aac-member-portal'); ?>">
					<img src="<?php echo esc_url($image_url); ?>" alt="<?php esc_attr_e('ExpertVoice', 'aac-member-portal'); ?>" loading="lazy">
				</a>
				<div class="aac-benefit-feature__copy">
					<p class="aac-benefit-feature__kicker"><?php esc_html_e('Partner Platform', 'aac-member-portal'); ?></p>
					<h2><?php esc_html_e('Unlock ExpertVoice offers with your AAC membership', 'aac-member-portal'); ?></h2>
					<?php foreach ($details as $detail) : ?>
						<p><?php echo esc_html($detail); ?></p>
					<?php endforeach; ?>
					<a href="<?php echo esc_url($button_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Visit ExpertVoice', 'aac-member-portal'); ?></a>
				</div>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	private function render_directory_benefit_panel($cards, $context, $category) {
		$items = array_values(array_filter($cards, static function ($card) use ($category) {
			return ($card['category'] ?? '') === $category;
		}));
		$is_guides = $category === 'climbing-guides';
		if (!$is_guides) {
			usort($items, static function ($first, $second) {
				return strcasecmp($first['brand'] ?? '', $second['brand'] ?? '');
			});
		}
		$header_image = $is_guides
			? 'https://images.unsplash.com/photo-1522163182402-834f871fd851?auto=format&fit=crop&w=1600&q=80'
			: 'https://images.unsplash.com/photo-1546016365-9b38a1b97164?auto=format&fit=crop&w=1600&q=80';
		$title = $is_guides ? __('Guide services and outdoor instruction', 'aac-member-portal') : __('Gym discounts by state', 'aac-member-portal');
		$kicker = $is_guides ? __('Guide Discounts', 'aac-member-portal') : __('Climbing Gym Discounts', 'aac-member-portal');
		$description = $is_guides
			? __('AAC guide partners offer discounts on instruction, guiding, avalanche education, wilderness medicine, and mountain programs. Expand a guide entry for discount details and booking notes.', 'aac-member-portal')
			: __('AAC partners with climbing gyms across the country to offer member discounts on day passes, memberships, punch passes, and initiation fees. Expand a state to review available gym offers, locations, and links where available.', 'aac-member-portal');
		$media_images = [
			'https://images.unsplash.com/photo-1516592673884-4a382d1124c2?auto=format&fit=crop&w=900&q=80',
			'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=80',
			'https://images.unsplash.com/photo-1454496522488-7a8e488e8606?auto=format&fit=crop&w=900&q=80',
		];

		ob_start();
		?>
		<section class="aac-benefit-directory">
			<header class="aac-benefit-directory__hero">
				<img src="<?php echo esc_url($header_image); ?>" alt="" loading="lazy">
				<div>
					<p><?php echo esc_html($kicker); ?></p>
					<h2><?php echo esc_html($title); ?></h2>
					<span><?php echo esc_html($description); ?></span>
				</div>
			</header>
			<?php if ($is_guides) : ?>
				<div class="aac-benefit-directory__media">
					<?php foreach ($media_images as $media_image) : ?>
						<img src="<?php echo esc_url($media_image); ?>" alt="" loading="lazy">
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<div class="aac-benefit-directory__list<?php echo $is_guides ? '' : ' aac-benefit-directory__list--columns'; ?>">
				<?php foreach ($items as $item) : ?>
					<?php
					$percent = $this->resolve_brand_discount_percent($item, $context['tier']);
					$details = $this->resolve_brand_discount_code_text($item, $context['tier']) ?: ($item['display_text'] ?? '');
					?>
					<details class="aac-benefit-directory__item">
						<summary>
							<span>
								<strong><?php echo esc_html($item['brand'] ?: __('AAC Partner', 'aac-member-portal')); ?></strong>
								<?php if ($percent) : ?>
									<em><?php echo esc_html($percent); ?></em>
								<?php endif; ?>
							</span>
							<b aria-hidden="true">+</b>
						</summary>
						<div>
							<?php if ($details) : ?>
								<p><?php echo nl2br(esc_html($details)); ?></p>
							<?php endif; ?>
							<?php if (!empty($item['button_url'])) : ?>
								<a href="<?php echo esc_url($item['button_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Website', 'aac-member-portal'); ?></a>
							<?php endif; ?>
						</div>
					</details>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	private function render_brand_discounts_styles() {
		static $rendered = false;
		if ($rendered) {
			return '';
		}
		$rendered = true;

		return <<<'CSS'
<style id="aac-brand-discounts-native-css">
	.aac-brand-discounts-page{width:min(100%,1280px);margin:0 auto;padding:clamp(2rem,4vw,4rem) 1rem;background:#fff;color:#030000;font-family:futura-pt,Futura,"Futura PT","Century Gothic","Trebuchet MS","Gill Sans",sans-serif;letter-spacing:.02em}
	.aac-brand-discounts-page *{box-sizing:border-box}
	.aac-brand-discounts-page__header{display:grid;gap:1rem;margin-bottom:2rem;border-bottom:3px solid #b71c1c;padding-bottom:1.5rem}
	.aac-brand-discounts-page__kicker,.aac-brand-discount-card__eyebrow{margin:0;color:#b71c1c;font-size:.72rem;font-weight:800;letter-spacing:.28em;text-transform:uppercase}
	.aac-brand-discounts-page h1{margin:.4rem 0 0;font-size:clamp(2.4rem,6vw,5rem);line-height:.96}
	.aac-brand-discounts-page__heading-row{display:flex;align-items:end;justify-content:space-between;gap:1.25rem}
	.aac-brand-discounts-page__heading-row p{max-width:48rem;margin:.75rem 0 0;color:#5f574f;font-size:1.08rem;line-height:1.65}
	.aac-brand-discounts-page__meta{display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:.6rem}
	.aac-brand-discounts-page__count{display:inline-flex;align-items:center;min-height:2.5rem;border:1px solid #d8d2c8;padding:0 .9rem;font-size:.72rem;font-weight:800;letter-spacing:.16em;text-transform:uppercase;white-space:nowrap}
	.aac-brand-discounts-page__gallery-link{display:inline-flex;align-items:center;justify-content:center;min-height:2.5rem;border:1px solid #8f1515;background:#fff;color:#8f1515!important;padding:0 .9rem;font-size:.72rem;font-weight:900;letter-spacing:.14em;text-decoration:none!important;text-transform:uppercase;white-space:nowrap}
	.aac-brand-discounts-page__gallery-link:hover{background:#8f1515;color:#fff!important}
	.aac-brand-discounts-page__tools{display:grid;gap:.45rem;margin:0 0 1.25rem}
	.aac-brand-discounts-page__tools label{font-size:.8rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
	.aac-brand-discounts-page__tools input{width:100%;min-height:3.25rem;border:1px solid #d8d2c8;border-radius:0;padding:0 1rem;background:#fff;color:#030000;font:inherit}
	.aac-benefits-gallery-page{width:min(100%,1480px)}
	.aac-benefits-gallery-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1.25rem;background:#fff}
	.aac-benefits-gallery-card{display:flex;min-width:0;min-height:100%;flex-direction:column;border:1px solid #d8d2c8;background:#fff;color:#030000!important;text-decoration:none!important;transition:border-color .18s ease,box-shadow .18s ease,transform .18s ease}
	a.aac-benefits-gallery-card:hover{border-color:#b71c1c;box-shadow:0 18px 36px rgba(0,0,0,.08);transform:translateY(-2px)}
	.aac-benefits-gallery-card__image{width:100%;aspect-ratio:4/3;overflow:hidden;background:#f4f1ea}
	.aac-benefits-gallery-card__image img{display:block;width:100%;height:100%;object-fit:cover}
	.aac-benefits-gallery-card__body{display:flex;flex:1;flex-direction:column;gap:.8rem;padding:1.15rem}
	.aac-benefits-gallery-card__title{margin:0;color:#030000;font-size:1.45rem;line-height:1.1}
	.aac-benefits-gallery-card__description{margin:0;color:#5f574f;font-size:.98rem;line-height:1.65}
	.aac-benefits-gallery-card__cta{display:inline-flex;align-items:center;width:max-content;min-height:2.45rem;margin-top:auto;border-bottom:3px solid #b71c1c;color:#8f1515;font-size:.72rem;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
	.aac-brand-discounts-tabs{display:flex;flex-wrap:wrap;gap:.5rem;margin:0 0 1.25rem;border-bottom:1px solid #e7e0d4;background:#fff;padding-bottom:1rem}
	.aac-brand-discounts-tab{min-width:13rem;border:1px solid #d8d2c8;background:#fff;color:#030000;cursor:pointer;padding:.9rem 1.35rem;text-align:center;font-size:.72rem;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
	.aac-brand-discounts-tab:hover{border-color:#8f1515;color:#8f1515}
	.aac-brand-discounts-tab.is-active{border-color:#8f1515;background:#8f1515;color:#fff}
	.aac-brand-discounts-panel{background:#fff}
	.aac-brand-discounts-panel[hidden]{display:none!important}
	.aac-brand-discounts-tier{display:grid;gap:.75rem;margin:0 0 2rem;background:#fff}
	.aac-brand-discounts-tier:last-child{margin-bottom:0}
	.aac-brand-discounts-tier h2{margin:0;border-bottom:2px solid #b71c1c;padding-bottom:.55rem;color:#8f1515;font-size:.72rem;font-weight:900;letter-spacing:.18em;text-transform:uppercase}
	.aac-brand-discounts-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem;background:#fff}
	@media(min-width:1100px){.aac-brand-discounts-grid{grid-template-columns:repeat(5,minmax(0,1fr))}}
	.aac-brand-discount-card{display:flex;min-width:0;min-height:100%;flex-direction:column;overflow:hidden;border:1px solid #d8d2c8;border-top:4px solid #b71c1c;background:#fff;box-shadow:none}
	.aac-brand-discount-card[hidden]{display:none}
	.aac-brand-discount-card__media{position:relative;display:flex;aspect-ratio:1.28;align-items:center;justify-content:center;overflow:hidden;background:#fff}
	.aac-brand-discount-card__media img{width:100%;height:100%;object-fit:contain;padding:.75rem}
	.aac-brand-discount-card__placeholder{display:flex;width:100%;height:100%;align-items:center;justify-content:center;background:#fff;color:#8f877a;font-size:3rem;font-weight:900}
	.aac-brand-discount-card__body{display:flex;flex:1;flex-direction:column;padding:.8rem}
	.aac-brand-discount-card h2{margin:.35rem 0 0;font-size:1.05rem;line-height:1.12}
	.aac-brand-discount-card__percent{margin:.35rem 0 .85rem;color:#8f1515;font-size:.9rem;font-weight:900;letter-spacing:.16em;text-transform:uppercase}
	.aac-brand-discount-card__percent span{margin-left:.25rem;color:rgba(3,0,0,.45);font-size:.68rem}
	.aac-brand-discount-card__details{margin-top:auto;border:1px solid #e7e0d4;background:#fff}
	.aac-brand-discount-card__details summary{display:flex;cursor:pointer;list-style:none;justify-content:space-between;padding:.78rem .85rem;color:rgba(3,0,0,.68);font-size:.68rem;font-weight:900;letter-spacing:.16em;text-transform:uppercase}
	.aac-brand-discount-card__details summary::-webkit-details-marker{display:none}
	.aac-brand-discount-card__detail-copy{border-top:1px solid #e7e0d4;padding:.85rem}
	.aac-brand-discount-card__detail-copy p{margin:.55rem 0 0;color:rgba(3,0,0,.7);font-size:.86rem;line-height:1.55}
	.aac-brand-discount-card__button{display:flex;align-items:center;justify-content:center;min-height:2.75rem;margin:.85rem;border:1px solid #8f1515;background:#8f1515;color:#fff!important;font-size:.72rem;font-weight:900;letter-spacing:.14em;text-decoration:none!important;text-transform:uppercase}
	.aac-brand-discount-card__button:hover{background:#6b1010;border-color:#6b1010}
	.aac-benefit-feature{display:grid;gap:1.5rem;background:#fff}
	.aac-benefit-feature__content{border-top:3px solid #b71c1c;border-bottom:3px solid #b71c1c;background:#fff;padding:clamp(1.5rem,4vw,2.75rem);text-align:center}
	.aac-benefit-feature__logo-link{display:block;width:min(100%,34rem);margin:0 auto 1.75rem;background:#fff;text-align:center;transition:opacity .2s ease}
	.aac-benefit-feature__logo-link:hover{opacity:.9}
	.aac-benefit-feature__logo-link img{max-width:32rem;max-height:9rem;width:100%;object-fit:contain}
	.aac-benefit-feature__copy{display:flex;flex-direction:column;align-items:center;justify-content:center}
	.aac-benefit-feature__kicker{margin:0;color:#b71c1c;font-size:.72rem;font-weight:900;letter-spacing:.24em;text-transform:uppercase}
	.aac-benefit-feature__copy h2{margin:.75rem 0 0;font-size:clamp(2rem,4vw,3.2rem);line-height:1}
	.aac-benefit-feature__copy p:not(.aac-benefit-feature__kicker){max-width:54rem;margin:1rem 0 0;color:rgba(3,0,0,.72);font-size:1rem;line-height:1.7}
	.aac-benefit-feature__copy a,.aac-benefit-directory__item a{display:inline-flex;align-items:center;justify-content:center;width:max-content;min-height:2.85rem;margin-top:1.25rem;background:#8f1515;color:#fff!important;padding:0 1.25rem;font-size:.72rem;font-weight:900;letter-spacing:.14em;text-decoration:none!important;text-transform:uppercase}
	.aac-benefit-directory{display:grid;gap:1.5rem;background:#fff}
	.aac-benefit-directory__hero{position:relative;min-height:18rem;overflow:hidden;background:#030000;color:#fff}
	.aac-benefit-directory__hero img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.78}
	.aac-benefit-directory__hero:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(3,0,0,.86),rgba(3,0,0,.48),rgba(3,0,0,.18))}
	.aac-benefit-directory__hero div{position:relative;z-index:1;max-width:56rem;padding:clamp(1.5rem,4vw,2.75rem)}
	.aac-benefit-directory__hero p{margin:0;color:#f8c235;font-size:.72rem;font-weight:900;letter-spacing:.24em;text-transform:uppercase}
	.aac-benefit-directory__hero h2{margin:.75rem 0 0;font-size:clamp(2rem,4vw,3.2rem);line-height:1}
	.aac-benefit-directory__hero span{display:block;max-width:50rem;margin:1rem 0 0;color:rgba(255,255,255,.82);font-size:1rem;line-height:1.7}
	.aac-benefit-directory__media{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}
	.aac-benefit-directory__media img{width:100%;aspect-ratio:1.55;object-fit:cover}
	.aac-benefit-directory__list{border-top:2px solid #b71c1c;border-bottom:2px solid #b71c1c;background:#fff}
	.aac-benefit-directory__list--columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));column-gap:2rem}
	.aac-benefit-directory__item{border-bottom:2px solid #b71c1c;background:#fff}
	.aac-benefit-directory__item:last-child{border-bottom:0}
	.aac-benefit-directory__item summary{display:flex;align-items:center;justify-content:space-between;gap:1rem;cursor:pointer;list-style:none;padding:1.15rem 0}
	.aac-benefit-directory__item summary::-webkit-details-marker{display:none}
	.aac-benefit-directory__item strong{display:block;color:#030000;font-size:1.2rem;line-height:1.25}
	.aac-benefit-directory__item em{display:block;margin-top:.35rem;color:#8f1515;font-size:.72rem;font-style:normal;font-weight:900;letter-spacing:.16em;text-transform:uppercase}
	.aac-benefit-directory__item b{display:flex;width:2.25rem;height:2.25rem;align-items:center;justify-content:center;border:1px solid #b71c1c;color:#b71c1c;font-size:1.4rem;line-height:1;transition:transform .2s ease}
	.aac-benefit-directory__item[open] b{transform:rotate(45deg)}
	.aac-benefit-directory__item div{padding:0 0 1.25rem}
	.aac-benefit-directory__item p{margin:0;white-space:normal;color:rgba(3,0,0,.72);font-size:.95rem;line-height:1.7}
	.aac-brand-discounts-lock{max-width:44rem;border-top:3px solid #b71c1c;border-bottom:3px solid #b71c1c;padding:2rem 0}
	.aac-brand-discounts-lock h2{margin:0 0 .65rem;font-size:2rem}
	.aac-brand-discounts-lock p{margin:.7rem 0 0;color:#5f574f;font-size:1.02rem;line-height:1.65}
	.aac-brand-discounts-lock__hint{font-size:.92rem!important}
	.aac-brand-discounts-lock__actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:1.25rem}
	.aac-brand-discounts-lock__actions a{display:inline-flex;align-items:center;justify-content:center;min-height:3rem;border:1px solid #8f1515;background:#8f1515;color:#fff!important;padding:0 1.15rem;font-size:.75rem;font-weight:900;letter-spacing:.14em;text-decoration:none!important;text-transform:uppercase}
	.aac-brand-discounts-lock__actions .aac-brand-discounts-lock__secondary{background:#fff;color:#030000!important;border-color:#d8d2c8}
	.aac-brand-discounts-page__empty{margin:1.5rem 0 0;color:#5f574f;font-weight:700}
	@media(max-width:980px){.aac-benefits-gallery-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
	@media(max-width:700px){.aac-brand-discounts-page__heading-row{align-items:start;flex-direction:column}.aac-brand-discounts-page__meta{align-items:flex-start;justify-content:flex-start}.aac-brand-discounts-page__count,.aac-brand-discounts-page__gallery-link{white-space:normal}.aac-benefits-gallery-grid{grid-template-columns:1fr}.aac-brand-discounts-grid{grid-template-columns:1fr}.aac-benefit-directory__media{grid-template-columns:1fr}.aac-benefit-directory__list--columns{grid-template-columns:1fr;column-gap:0}.aac-brand-discounts-tab{width:100%}}
</style>
CSS;
	}

	private function render_brand_discounts_script() {
		static $rendered = false;
		if ($rendered) {
			return '';
		}
		$rendered = true;

		return <<<'HTML'
<script>
	function aacBrandDiscountsUpdatePanels(root) {
		const activeTab = root.dataset.aacActiveDiscountCategory || 'discount-brands';
		root.querySelectorAll('[data-aac-brand-discounts-panel]').forEach(function (panel) {
			const active = (panel.dataset.aacBrandDiscountsPanel || 'discount-brands') === activeTab;
			panel.hidden = !active;
			panel.classList.toggle('is-active', active);
		});
	}
	document.addEventListener('input', function (event) {
		if (!event.target.matches('[data-aac-brand-discounts-search]')) {
			return;
		}
		const root = event.target.closest('[data-aac-brand-discounts]');
		if (!root) {
			return;
		}
		const query = event.target.value.trim().toLowerCase();
		const activeTab = root.dataset.aacActiveDiscountCategory || 'discount-brands';
		let visibleCount = 0;
		root.querySelectorAll('[data-aac-brand-discount-card]').forEach(function (card) {
			const categoryMatch = (card.dataset.category || 'discount-brands') === activeTab;
			const match = categoryMatch && (!query || String(card.dataset.search || '').includes(query));
			card.hidden = !match;
			if (match) {
				visibleCount += 1;
			}
		});
		const empty = root.querySelector('[data-aac-brand-discounts-empty]');
		if (empty) {
			empty.hidden = visibleCount !== 0;
		}
	});
	document.addEventListener('click', function (event) {
		const tab = event.target.closest('[data-aac-brand-discounts-tab]');
		if (!tab) {
			return;
		}
		const root = tab.closest('[data-aac-brand-discounts]');
		if (!root) {
			return;
		}
		root.dataset.aacActiveDiscountCategory = tab.dataset.aacBrandDiscountsTab || 'discount-brands';
		root.querySelectorAll('[data-aac-brand-discounts-tab]').forEach(function (button) {
			button.classList.toggle('is-active', button === tab);
		});
		aacBrandDiscountsUpdatePanels(root);
		const search = root.querySelector('[data-aac-brand-discounts-search]');
		if (search) {
			search.dispatchEvent(new Event('input', { bubbles: true }));
			return;
		}
		let visibleCount = 0;
		root.querySelectorAll('[data-aac-brand-discount-card]').forEach(function (card) {
			const match = (card.dataset.category || 'discount-brands') === root.dataset.aacActiveDiscountCategory;
			card.hidden = !match;
			if (match) {
				visibleCount += 1;
			}
		});
		const empty = root.querySelector('[data-aac-brand-discounts-empty]');
		if (empty) {
			empty.hidden = visibleCount !== 0;
		}
	});
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-aac-brand-discounts]').forEach(function (root) {
			root.dataset.aacActiveDiscountCategory = root.dataset.aacActiveDiscountCategory || 'discount-brands';
			aacBrandDiscountsUpdatePanels(root);
			const search = root.querySelector('[data-aac-brand-discounts-search]');
			if (search) {
				search.dispatchEvent(new Event('input', { bubbles: true }));
			} else {
				root.querySelectorAll('[data-aac-brand-discount-card]').forEach(function (card) {
					card.hidden = (card.dataset.category || 'discount-brands') !== root.dataset.aacActiveDiscountCategory;
				});
			}
		});
	});
	document.querySelectorAll('[data-aac-brand-discounts]').forEach(function (root) {
		root.dataset.aacActiveDiscountCategory = root.dataset.aacActiveDiscountCategory || 'discount-brands';
		aacBrandDiscountsUpdatePanels(root);
		const search = root.querySelector('[data-aac-brand-discounts-search]');
		if (search) {
			search.dispatchEvent(new Event('input', { bubbles: true }));
		} else {
			root.querySelectorAll('[data-aac-brand-discount-card]').forEach(function (card) {
				card.hidden = (card.dataset.category || 'discount-brands') !== root.dataset.aacActiveDiscountCategory;
			});
		}
	});
</script>
HTML;
	}

	private function render_signup_embed_style_override() {
		return <<<'CSS'
<script>
	(function () {
		document.documentElement.classList.add('aac-member-signup-page');
		if (document.body) {
			document.body.classList.add('aac-member-signup-page');
		} else {
			document.addEventListener('DOMContentLoaded', function () {
				document.body.classList.add('aac-member-signup-page');
			});
		}
	}());
</script>
<style id="aac-member-signup-page-override">
	html.aac-member-signup-page,
	body.aac-member-signup-page,
	body.aac-member-signup-page .wp-site-blocks,
	body.aac-member-signup-page .wp-site-blocks > main,
	body.aac-member-signup-page main,
	body.aac-member-signup-page article,
	body.aac-member-signup-page .entry-content,
	body.aac-member-signup-page .entry-content > * {
		margin-top: 0 !important;
		padding-top: 0 !important;
	}

	#aac-member-portal-root.aac-member-portal-shell--signup,
		#aac-member-portal-root.aac-member-portal-shell--signup .aac-signup-embed-surface {
			width: 100vw !important;
			max-width: 100vw !important;
			margin-left: calc(50% - 50vw) !important;
			margin-right: calc(50% - 50vw) !important;
			background: #ffffff !important;
			overflow-x: clip !important;
		}

		#aac-member-portal-root.aac-member-portal-shell--signup {
			position: relative !important;
			padding-top: 0 !important;
		}

		#aac-member-portal-root.aac-member-portal-shell--signup .aac-join-layout {
			grid-template-columns: minmax(0, 1fr) !important;
		}

		#aac-member-portal-root.aac-member-portal-shell--signup .aac-join-sidebar {
			display: none !important;
		}

		#aac-member-portal-root.aac-member-portal-shell--signup .aac-join-main {
			width: 100% !important;
			padding-left: clamp(1rem, 4vw, 4rem) !important;
			padding-right: clamp(1rem, 4vw, 4rem) !important;
		}

		#aac-member-portal-root.aac-member-portal-shell--signup #membership-form {
			max-width: 90rem !important;
		}

		body.admin-bar #aac-member-portal-root.aac-member-portal-shell--signup {
			padding-top: 0 !important;
		}

		#aac-member-portal-root.aac-member-portal-shell--signup aside,
		#aac-member-portal-root.aac-member-portal-shell--signup main {
			padding-top: clamp(11rem, 10vw, 14rem) !important;
		}

		#aac-member-portal-root.aac-member-portal-shell--signup aside > div {
			gap: 1.4rem !important;
		}

		#aac-member-portal-root.aac-member-portal-shell--signup .aac-signup-sidebar-intro > p:first-child,
		#aac-member-portal-root.aac-member-portal-shell--signup .aac-signup-form-intro > p:first-child {
			display: none !important;
		}

		#aac-member-portal-root.aac-member-portal-shell--signup .aac-signup-sidebar-intro h1 {
			margin-top: 0 !important;
		}

		#aac-member-portal-root.aac-member-portal-shell--signup .aac-signup-sidebar-intro h1 + p {
			margin-top: 1rem !important;
		}

	#aac-member-portal-root.aac-member-portal-shell--signup *,
	#aac-member-portal-root.aac-member-portal-shell--signup *::before,
	#aac-member-portal-root.aac-member-portal-shell--signup *::after {
		box-sizing: border-box;
	}

	#aac-member-portal-root.aac-member-portal-shell--signup button,
	#aac-member-portal-root.aac-member-portal-shell--signup a[data-aac-button="true"] {
		font-family: futura-pt, Futura, "Futura PT", "Century Gothic", "Trebuchet MS", "Gill Sans", ui-sans-serif, sans-serif !important;
		font-size: inherit !important;
		line-height: inherit !important;
		text-transform: none !important;
		letter-spacing: inherit !important;
	}

	#aac-member-portal-root.aac-member-portal-shell--signup .aac-membership-tier-card {
		height: auto !important;
		min-height: 320px !important;
		padding: 1.5rem !important;
		align-items: stretch !important;
		justify-content: flex-start !important;
		text-align: left !important;
	}

	@media (min-width: 640px) {
		#aac-member-portal-root.aac-member-portal-shell--signup .aac-membership-tier-card {
			min-height: 380px !important;
			padding: 1.75rem !important;
		}
	}

	#aac-member-portal-root.aac-member-portal-shell--signup .aac-signup-step-button {
		min-height: 0 !important;
		padding: 0.625rem 0.75rem !important;
		text-align: left !important;
	}

	#aac-member-portal-root.aac-member-portal-shell--signup .aac-membership-discount-card {
		height: auto !important;
		min-height: 6.25rem !important;
		padding: 1rem !important;
		text-align: center !important;
	}

	#aac-member-portal-root.aac-member-portal-shell--signup .aac-family-dependent-button {
		height: auto !important;
		min-height: 2.75rem !important;
		padding: 0 1rem !important;
		text-transform: uppercase !important;
		letter-spacing: 0.08em !important;
	}

		#aac-member-portal-root.aac-member-portal-shell--signup [data-aac-button="true"] {
			width: auto !important;
			height: auto !important;
			min-height: 2.85rem !important;
			padding: 0.5rem 1.25rem !important;
			text-transform: uppercase !important;
			letter-spacing: 0.12em !important;
		}

		/* Compact the comparison table so the grid and tier controls share one desktop frame. */
		@media (min-width: 1024px) {
			#aac-member-portal-root.aac-member-portal-shell--signup .aac-signup-form-intro {
				margin-bottom: 0.5rem !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup .aac-signup-form-intro h2 {
				font-size: 1.75rem !important;
				line-height: 1.1 !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup .aac-signup-form-intro p {
				margin-top: 0.25rem !important;
				font-size: 0.875rem !important;
				line-height: 1.25rem !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup .aac-signup-benefits-matrix {
				margin-bottom: 0.75rem !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup .aac-signup-benefits-matrix > div {
				padding-bottom: 0 !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup .aac-signup-benefits-matrix thead button {
				min-height: 3.5rem !important;
				padding: 0.35rem 0.65rem !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup .aac-signup-benefits-matrix thead button span:last-child {
				font-size: 1.125rem !important;
				line-height: 1.25rem !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup .aac-signup-benefits-matrix thead button span:first-child:not(:last-child) {
				margin-bottom: 0 !important;
				font-size: 0.58rem !important;
				line-height: 0.75rem !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup .aac-signup-benefits-matrix tbody th,
			#aac-member-portal-root.aac-member-portal-shell--signup .aac-signup-benefits-matrix tbody td {
				height: 2rem !important;
				padding: 0.2rem 0.65rem !important;
				font-size: 0.78rem !important;
				line-height: 1rem !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup .aac-signup-benefits-matrix tbody tr:first-child th,
			#aac-member-portal-root.aac-member-portal-shell--signup .aac-signup-benefits-matrix tbody tr:first-child td {
				height: 2.25rem !important;
				font-size: 1rem !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup .aac-signup-benefits-matrix tbody svg {
				width: 1.05rem !important;
				height: 1.05rem !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup .aac-signup-benefits-matrix > p {
				margin-top: 0.25rem !important;
				font-size: 0.65rem !important;
				line-height: 0.9rem !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup .aac-membership-tier-button {
				min-height: 2.75rem !important;
				padding: 0.5rem 0.75rem !important;
				font-size: 0.95rem !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup #membership-form > section > div:last-child {
				margin-top: 0.75rem !important;
			}
		}

		@media (max-width: 1023px) {
			#aac-member-portal-root.aac-member-portal-shell--signup {
				padding-top: 0 !important;
			}

			body.admin-bar #aac-member-portal-root.aac-member-portal-shell--signup {
				padding-top: 0 !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup aside {
				min-height: 0 !important;
				padding-top: 2.35rem !important;
				padding-bottom: 1rem !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup aside > div {
				gap: 1rem !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup aside h1 {
				font-size: 1.85rem !important;
				line-height: 1 !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup aside p:not(:first-child) {
				display: none !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup aside nav {
				display: grid !important;
				grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
				gap: 0.35rem !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup .aac-signup-step-button {
				justify-content: center !important;
				gap: 0 !important;
				padding: 0.55rem 0.35rem !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup .aac-signup-step-button > span:last-child {
				display: none !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup main {
				min-height: 0 !important;
				padding: 1rem !important;
				padding-top: 2.35rem !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup #membership-form,
			#aac-member-portal-root.aac-member-portal-shell--signup #membership-form > section {
				min-height: 0 !important;
				align-content: start !important;
			}
		}

		@media (max-width: 639px) {
			#aac-member-portal-root.aac-member-portal-shell--signup {
				padding-top: 0 !important;
			}

			body.admin-bar #aac-member-portal-root.aac-member-portal-shell--signup {
				padding-top: 0 !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup .aac-membership-tier-card {
				min-height: 0 !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup aside,
			#aac-member-portal-root.aac-member-portal-shell--signup main {
				padding-top: 1.85rem !important;
			}

			#aac-member-portal-root.aac-member-portal-shell--signup .aac-membership-discount-card {
				min-height: 5.35rem !important;
			}
		}
	</style>
CSS;
	}

	private function render_portal_background_override() {
		return <<<'CSS'
<style id="aac-member-portal-background-override">
	html,
	body,
	body.aac-portal-theme,
	body.aac-member-portal-fullscreen,
	body.aac-member-portal-managed-shell,
	.aac-theme-shell,
	.aac-theme-main,
	.aac-page-shell,
	.aac-page-shell__inner,
	.wp-site-blocks,
	.wp-site-blocks > main,
	.wp-site-blocks > main > .wp-block-group,
	.wp-site-blocks > main .wp-block-group,
	.wp-site-blocks > main .wp-block-columns,
	.wp-site-blocks > main .wp-block-column,
	.wp-site-blocks > main .entry-content,
	.entry-content,
	#aac-member-portal-root,
	#aac-member-portal-root.aac-member-portal-shell,
	#aac-member-portal-root .topo-lines,
	#aac-member-portal-root .member-app-surface,
	#aac-member-portal-root .portal-main-surface {
		background: #ffffff !important;
		background-color: #ffffff !important;
		background-image: none !important;
	}
</style>
CSS;
	}

	private function render_profile_theme_style_override() {
		return <<<'CSS'
<script>
	(function () {
		document.documentElement.classList.add('aac-member-profile-page');
		if (document.body) {
			document.body.classList.add('aac-member-profile-page');
		} else {
			document.addEventListener('DOMContentLoaded', function () {
				document.body.classList.add('aac-member-profile-page');
			});
		}
	}());
</script>
<style id="aac-member-profile-page-override">
	html.aac-member-profile-page #site-header,
	body.aac-member-profile-page #site-header {
		background: #030000 !important;
		background-color: #030000 !important;
		color: #ffffff !important;
		display: block !important;
		opacity: 1 !important;
		visibility: visible !important;
		transform: none !important;
		pointer-events: auto !important;
		z-index: 1000 !important;
		transition: background-color 220ms ease, background 220ms ease !important;
	}

	html.aac-member-profile-page #aac-live-site-header,
	body.aac-member-profile-page #aac-live-site-header {
		display: block !important;
		opacity: 1 !important;
		visibility: visible !important;
		min-height: var(--aac-site-header-height, 120px) !important;
		background: #030000 !important;
	}

	html.aac-member-profile-page #site-header::before,
	body.aac-member-profile-page #site-header::before {
		opacity: 1;
		transition: opacity 220ms ease !important;
	}

	html.aac-member-profile-page #site-header.aac-site-header--scrolled,
	body.aac-member-profile-page #site-header.aac-site-header--scrolled {
		background: #030000 !important;
		background-color: #030000 !important;
	}

	html.aac-member-profile-page #site-header.aac-site-header--scrolled::before,
	body.aac-member-profile-page #site-header.aac-site-header--scrolled::before {
		opacity: 1;
	}

	html.aac-member-profile-page #site-header .top-level-link,
	html.aac-member-profile-page #site-header .utility-nav-item,
	html.aac-member-profile-page #site-header a,
	body.aac-member-profile-page #site-header .top-level-link,
	body.aac-member-profile-page #site-header .utility-nav-item,
	body.aac-member-profile-page #site-header a {
		color: #ffffff !important;
	}

	html.aac-member-profile-page #site-header .mega-menu-toggle::after,
	body.aac-member-profile-page #site-header .mega-menu-toggle::after {
		color: #f8c235 !important;
	}

	html.aac-member-profile-page #site-header .utility-icon--light,
	body.aac-member-profile-page #site-header .utility-icon--light,
	html.aac-member-profile-page #site-header .light-header-logo,
	body.aac-member-profile-page #site-header .light-header-logo {
		display: inline-block !important;
	}

	html.aac-member-profile-page #site-header .utility-icon--dark,
	body.aac-member-profile-page #site-header .utility-icon--dark {
		display: none !important;
	}

	#aac-member-portal-root.aac-member-portal-shell--profile {
		display: block !important;
		width: 100vw !important;
		max-width: 100vw !important;
		margin-left: calc(50% - 50vw) !important;
		margin-right: calc(50% - 50vw) !important;
		padding-top: 0 !important;
		background: #ffffff !important;
		overflow-x: clip !important;
		overflow-y: auto !important;
		height: 100vh !important;
	}

	body.admin-bar #aac-member-portal-root.aac-member-portal-shell--profile {
		padding-top: 0 !important;
	}

#aac-member-portal-root.aac-member-portal-shell--profile .member-app-surface {
	height: auto !important;
	min-height: 100vh !important;
}

body.admin-bar #aac-member-portal-root.aac-member-portal-shell--profile .member-app-surface {
	height: auto !important;
	min-height: 100vh !important;
}

	@media (max-width: 1023px) {
		#aac-member-portal-root.aac-member-portal-shell--profile {
			padding-top: 0 !important;
		}

		body.admin-bar #aac-member-portal-root.aac-member-portal-shell--profile {
			padding-top: 0 !important;
		}

	#aac-member-portal-root.aac-member-portal-shell--profile .member-app-surface {
		height: auto !important;
		min-height: 100vh !important;
	}

		body.admin-bar #aac-member-portal-root.aac-member-portal-shell--profile .member-app-surface {
			height: auto !important;
			min-height: 100vh !important;
		}
	}

	@media (max-width: 639px) {
		#aac-member-portal-root.aac-member-portal-shell--profile {
			padding-top: 0 !important;
		}

		body.admin-bar #aac-member-portal-root.aac-member-portal-shell--profile {
			padding-top: 0 !important;
		}

	#aac-member-portal-root.aac-member-portal-shell--profile .member-app-surface {
		height: auto !important;
		min-height: 100vh !important;
	}

		body.admin-bar #aac-member-portal-root.aac-member-portal-shell--profile .member-app-surface {
			height: auto !important;
			min-height: 100vh !important;
		}
	}
</style>
CSS;
	}

	private function render_app_mount($embed_mode = '') {
		$asset_files = $this->locate_asset_files();
		if (!$asset_files['script']) {
			return '<div class="aac-member-portal-error">AAC Member Portal assets have not been packaged yet.</div>';
		}

		$this->enqueue_portal_assets_and_config($embed_mode);
		$config = $this->get_runtime_config($embed_mode);

		$mount_classes = ['aac-member-portal-shell'];
		if ($embed_mode === 'signup') {
			$mount_classes[] = 'aac-member-portal-shell--signup';
		}
		if ($embed_mode === 'login') {
			$mount_classes[] = 'aac-member-portal-shell--login';
		}
		if ($embed_mode === '') {
			$mount_classes[] = 'aac-member-portal-shell--profile';
		}

		$style_override = $this->render_portal_background_override();
		if ($embed_mode === 'signup') {
			$style_override .= $this->render_signup_embed_style_override();
		}
		if ($embed_mode === '') {
			$style_override .= $this->render_profile_theme_style_override();
		}

		return sprintf(
			'%s<script>window.AAC_MEMBER_PORTAL_CONFIG = %s;</script><div id="%s" class="%s"></div>',
			$style_override,
			wp_json_encode($config),
			esc_attr(self::MOUNT_ID),
			esc_attr(implode(' ', $mount_classes))
		);
	}

	/**
	 * @return bool True if portal config was attached (once per request).
	 */
	private function enqueue_portal_assets_and_config($embed_mode = '') {
		$asset_files = $this->locate_asset_files();
		if (!$asset_files['script']) {
			return false;
		}

		wp_enqueue_script(self::SCRIPT_HANDLE);
		if ($asset_files['style']) {
			wp_enqueue_style(self::STYLE_HANDLE);
		}

		static $config_injected = false;
		if ($config_injected) {
			return true;
		}

		$config_injected = true;

		$config = $this->get_runtime_config($embed_mode);

		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'window.AAC_MEMBER_PORTAL_CONFIG = ' . wp_json_encode($config) . ';',
			'before'
		);

		return true;
	}

	public function maybe_render_missing_build_notice() {
		if (!current_user_can('activate_plugins')) {
			return;
		}

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!$screen || $screen->base !== 'plugins') {
			return;
		}

		$asset_files = $this->locate_asset_files();
		if ($asset_files['script']) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html('AAC Member Portal is installed, but the frontend build assets are missing. Run `npm run package:wordpress` in the app project before zipping or deploying the plugin.');
		echo '</p></div>';
	}

	public function maybe_restore_pmpro_admin_capabilities() {
		if (!current_user_can('manage_options') || !AAC_Member_Portal_PMPro::is_available()) {
			return;
		}

		$administrator = get_role('administrator');
		if (!$administrator) {
			return;
		}

		foreach ($this->pmpro_admin_capabilities() as $capability) {
			if (!$administrator->has_cap($capability)) {
				$administrator->add_cap($capability);
			}
		}
	}

	public function maybe_grant_pmpro_admin_capabilities($allcaps, $caps, $args, $user) {
		if (!AAC_Member_Portal_PMPro::is_available() || !($user instanceof WP_User)) {
			return $allcaps;
		}

		if (empty($allcaps['manage_options']) && empty($allcaps['activate_plugins'])) {
			return $allcaps;
		}

		foreach ($this->pmpro_admin_capabilities() as $capability) {
			$allcaps[$capability] = true;
		}

		return $allcaps;
	}

	private function pmpro_admin_capabilities() {
		return [
			'pmpro_addons',
			'pmpro_advancedsettings',
			'pmpro_dashboard',
			'pmpro_discountcodes',
			'pmpro_edit_members',
			'pmpro_emailsettings',
			'pmpro_emailtemplates',
			'pmpro_logincsv',
			'pmpro_manage_pause_mode',
			'pmpro_membershiplevels',
			'pmpro_memberships_menu',
			'pmpro_memberslist',
			'pmpro_memberslistcsv',
			'pmpro_orders',
			'pmpro_orderscsv',
			'pmpro_pagesettings',
			'pmpro_paymentsettings',
			'pmpro_reportcsv',
			'pmpro_reports',
			'pmpro_sales_report_csv',
			'pmpro_updates',
			'pmpro_userfields',
			'pmpro_wizard',
		];
	}

	private function get_account_info_defaults_for_user($user = null) {
		$user_id = $user instanceof WP_User && $user->exists() ? $user->ID : 0;
		$stored = $user_id ? get_user_meta($user_id, 'aac_account_info', true) : [];
		$stored = is_array($stored) ? $stored : [];
		$stored = $this->strip_pmpro_managed_account_fields_for_storage($stored);
		$member_database_fallback = $user_id > 0 ? $this->get_member_database_account_info_fallback($user_id) : [];

		$defaults = [
			'first_name' => $user instanceof WP_User ? $user->first_name : '',
			'last_name' => $user instanceof WP_User ? $user->last_name : '',
			'name' => $user instanceof WP_User ? $user->display_name : '',
			'email' => $user instanceof WP_User ? $user->user_email : '',
			'photo_url' => $user_id ? get_avatar_url($user_id) : '',
			'phone' => '',
			'birthdate' => '',
			'street' => '',
			'address2' => '',
			'city' => '',
			'state' => '',
			'zip' => '',
			'country' => 'US',
			'size' => 'No T-shirt',
			'publication_pref' => 'Print',
			'aaj_pref' => 'Print',
			'anac_pref' => 'Print',
			'acj_pref' => 'Print',
				'guidebook_pref' => 'Print',
				'magazine_subscriptions' => [],
				'membership_discount_type' => '',
				'student_university' => '',
				'student_university_id' => '',
				'graduation_date' => '',
				'service_component' => '',
				'partner_family_mode' => '',
			'partner_family_additional_adult' => false,
			'partner_family_dependents' => 0,
			'auto_renew' => true,
		];

		$merged = array_merge($defaults, $stored);
		if (!empty($member_database_fallback)) {
			$merged = array_merge($merged, array_filter(
				$member_database_fallback,
				static function ($value) {
					if (is_bool($value)) {
						return true;
					}

					if (is_array($value)) {
						return !empty($value);
					}

					return trim((string) $value) !== '';
				}
			));
		}
		unset($merged['phone_type'], $merged['payment_method']);
		if ($user_id > 0) {
			$merged['first_name'] = $this->get_preferred_user_meta_value($user_id, ['pmpro_sfirstname', 'first_name', 'bfirstname'], $merged['first_name']);
			$merged['last_name'] = $this->get_preferred_user_meta_value($user_id, ['pmpro_slastname', 'last_name', 'blastname'], $merged['last_name']);
			$merged['name'] = trim((string) $merged['first_name'] . ' ' . (string) $merged['last_name']) ?: $merged['name'];
			$merged['birthdate'] = $this->sanitize_birthdate_value(
				$this->get_preferred_user_meta_value($user_id, ['birthdate'], $merged['birthdate'])
			);
			$merged['phone'] = $this->get_preferred_user_meta_value($user_id, ['pmpro_sphone', 'bphone'], $merged['phone']);
			$merged['street'] = $this->get_preferred_user_meta_value($user_id, ['pmpro_saddress1', 'saddress1', 'baddress1'], $merged['street']);
			$merged['address2'] = $this->get_preferred_user_meta_value($user_id, ['pmpro_saddress2', 'saddress2', 'baddress2'], $merged['address2']);
			$merged['city'] = $this->get_preferred_user_meta_value($user_id, ['pmpro_scity', 'scity', 'bcity'], $merged['city']);
			$merged['state'] = $this->get_preferred_user_meta_value($user_id, ['pmpro_sstate', 'sstate', 'bstate'], $merged['state']);
				$merged['zip'] = $this->get_preferred_user_meta_value($user_id, ['pmpro_szipcode', 'szipcode', 'bzipcode'], $merged['zip']);
				$merged['country'] = $this->get_preferred_user_meta_value($user_id, ['pmpro_scountry', 'scountry', 'bcountry'], $merged['country']);
				$merged['student_university'] = $this->get_preferred_user_meta_value($user_id, ['student_university', 'university_or_school'], $merged['student_university']);
				$merged['student_university_id'] = $this->get_preferred_user_meta_value($user_id, ['student_university_id', 'university_school_id'], $merged['student_university_id']);
				$merged['graduation_date'] = $this->get_preferred_user_meta_value($user_id, ['graduation_date', 'student_graduation_date'], $merged['graduation_date']);
				$merged['service_component'] = $this->get_preferred_user_meta_value($user_id, ['service_component', 'service_branch', 'military_service_component'], $merged['service_component']);
				$merged['size'] = $this->get_preferred_user_meta_value($user_id, ['t_shirt', 't_shirt_size', 'tshirt_size', 'shirt_size'], $merged['size']);
			$merged['aaj_pref'] = $this->get_preferred_user_meta_value($user_id, ['aaj_preference'], $merged['aaj_pref']);
			$merged['anac_pref'] = $this->get_preferred_user_meta_value($user_id, ['anac_preference', 'anan_preference'], $merged['anac_pref']);
			$merged['acj_pref'] = $this->get_preferred_user_meta_value($user_id, ['american_climbing_journal_preference', 'acj_preference'], $merged['acj_pref']);
			$merged['guidebook_pref'] = $this->get_preferred_user_meta_value($user_id, ['guidebook_preferences', 'guidebook_preference'], $merged['guidebook_pref']);
		}
		$merged['size'] = $this->normalize_tshirt_size_value($merged['size'] ?? 'No T-shirt');

		return array_merge($merged, $this->get_normalized_publication_preferences($merged));
	}

	private function get_member_database_account_info_fallback($user_id) {
		global $wpdb;

		$user_id = (int) $user_id;
		if ($user_id <= 0 || !$wpdb) {
			return [];
		}

		$table = $wpdb->prefix . 'aac_member_db_profiles';
		$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ($table_exists !== $table) {
			return [];
		}

		$raw_profile = $wpdb->get_var(
			$wpdb->prepare("SELECT raw_profile FROM {$table} WHERE user_id = %d LIMIT 1", $user_id)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$decoded = json_decode((string) $raw_profile, true);
		if (!is_array($decoded)) {
			return [];
		}

		$account_info = is_array($decoded['account_info'] ?? null) ? $decoded['account_info'] : [];
		if (!$account_info) {
			return [];
		}

		return [
			'phone' => sanitize_text_field((string) ($account_info['phone'] ?? '')),
			'birthdate' => $this->sanitize_birthdate_value($account_info['birthdate'] ?? ''),
			'street' => sanitize_text_field((string) ($account_info['street'] ?? '')),
			'address2' => sanitize_text_field((string) ($account_info['address2'] ?? '')),
			'city' => sanitize_text_field((string) ($account_info['city'] ?? '')),
			'state' => sanitize_text_field((string) ($account_info['state'] ?? '')),
			'zip' => sanitize_text_field((string) ($account_info['zip'] ?? '')),
			'country' => sanitize_text_field((string) ($account_info['country'] ?? '')),
			'size' => $this->normalize_tshirt_size_value($account_info['size'] ?? 'No T-shirt'),
			'aaj_pref' => sanitize_text_field((string) ($account_info['aaj_pref'] ?? '')),
			'anac_pref' => sanitize_text_field((string) ($account_info['anac_pref'] ?? '')),
			'acj_pref' => sanitize_text_field((string) ($account_info['acj_pref'] ?? '')),
			'guidebook_pref' => sanitize_text_field((string) ($account_info['guidebook_pref'] ?? '')),
		];
	}

	private function get_member_ids_for_pmpro_field_backfill() {
		$user_ids = get_users([
			'fields' => 'ids',
			'number' => -1,
			'orderby' => 'ID',
			'order' => 'ASC',
			'count_total' => false,
		]);

		global $wpdb;
		if ($wpdb) {
			$table = $wpdb->prefix . 'aac_member_db_profiles';
			$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ($table_exists === $table) {
				$mirror_user_ids = $wpdb->get_col("SELECT user_id FROM {$table} WHERE user_id > 0"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if (is_array($mirror_user_ids) && !empty($mirror_user_ids)) {
					$user_ids = array_merge($user_ids, $mirror_user_ids);
				}
			}
		}

		return array_values(array_unique(array_map('intval', is_array($user_ids) ? $user_ids : [])));
	}

	private function get_pmpro_managed_account_info_keys() {
		return [
			'first_name',
			'last_name',
			'name',
			'phone',
			'birthdate',
			'street',
			'address2',
			'city',
			'state',
			'zip',
			'country',
			'size',
			'publication_pref',
			'aaj_pref',
			'anac_pref',
			'acj_pref',
			'guidebook_pref',
			'phone_type',
			'payment_method',
		];
	}

	private function strip_pmpro_managed_account_fields_for_storage($account_info) {
		if (!is_array($account_info)) {
			return [];
		}

		foreach ($this->get_pmpro_managed_account_info_keys() as $key) {
			unset($account_info[$key]);
		}

		return $account_info;
	}

	private function get_preferred_user_meta_value($user_id, $keys, $fallback = '') {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !is_array($keys)) {
			return $fallback;
		}

		foreach ($keys as $key) {
			$key = sanitize_key((string) $key);
			if ($key === '') {
				continue;
			}

			$value = get_user_meta($user_id, $key, true);
			if (is_array($value)) {
				if (!empty($value)) {
					return $value;
				}
				continue;
			}

			if ($value === null) {
				continue;
			}

			if (is_string($value)) {
				if (trim($value) === '') {
					continue;
				}
				return $value;
			}

			if ($value !== '') {
				return $value;
			}
		}

		return $fallback;
	}

	private function get_preferred_user_meta_flag($user_id, $keys, $fallback = false) {
		$value = $this->get_preferred_user_meta_value($user_id, $keys, null);
		if ($value === null) {
			return (bool) $fallback;
		}

		if (is_bool($value)) {
			return $value;
		}

		if (is_numeric($value)) {
			return (int) $value === 1;
		}

		$normalized = strtolower(trim((string) $value));
		return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
	}

	private function normalize_tshirt_size_value($value, $fallback = 'No T-shirt') {
		$normalized = sanitize_text_field((string) $value);
		$normalized = trim($normalized);
		if ($normalized === '') {
			return $fallback;
		}

		$lowered = strtolower($normalized);
		if (in_array($lowered, ['none', 'no t-shirt', 'no t shirt', 'n/a', 'na'], true)) {
			return 'No T-shirt';
		}

		$direct_label_map = [
			'unisex x-small' => 'Unisex X-Small',
			'unisex small' => 'Unisex Small',
			'unisex medium' => 'Unisex Medium',
			'unisex large' => 'Unisex Large',
			'unisex x-large' => 'Unisex X-Large',
			'unisex xx-large' => 'Unisex XX-Large',
		];
		if (isset($direct_label_map[$lowered])) {
			return $direct_label_map[$lowered];
		}

		$compact_size_map = [
			'xs' => 'Unisex X-Small',
			'xsmall' => 'Unisex X-Small',
			's' => 'Unisex Small',
			'm' => 'Unisex Medium',
			'l' => 'Unisex Large',
			'xl' => 'Unisex X-Large',
			'xlarge' => 'Unisex X-Large',
			'xxl' => 'Unisex XX-Large',
			'xxlarge' => 'Unisex XX-Large',
			'2xl' => 'Unisex XX-Large',
		];
		if (isset($compact_size_map[$lowered])) {
			return $compact_size_map[$lowered];
		}

		if (strpos($lowered, 'unisex ') === 0) {
			$compact = str_replace([' ', '-'], '', substr($lowered, 8));
			return $compact_size_map[$compact] ?? $fallback;
		}

		return $fallback;
	}

	public function get_pmpro_tshirt_size_options() {
		$field = $this->get_pmpro_user_field_definition(['t_shirt', 'tshirt', 't_shirt_size', 'tshirt_size', 'shirt_size'], ['t-shirt', 'tshirt', 'shirt size']);
		$options = $field ? $this->normalize_pmpro_field_options($field['options'] ?? []) : [];
		$normalized_options = [];
		$seen_values = [];

		foreach ($options as $option) {
			$raw_value = $option['value'] ?? '';
			$raw_label = $option['label'] ?? $raw_value;
			$value = $this->normalize_tshirt_size_value($raw_value, '');
			$label = $this->normalize_tshirt_size_value($raw_label, $value);

			if ($value === '' && $label !== '') {
				$value = $label;
			}
			if ($label === '' && $value !== '') {
				$label = $value;
			}
			if ($value === '' || $label === '' || isset($seen_values[$value])) {
				continue;
			}

			$normalized_options[] = [
				'value' => $value,
				'label' => $label,
			];
			$seen_values[$value] = true;
		}

		return !empty($normalized_options) ? $normalized_options : $this->get_default_tshirt_size_options();
	}

	private function get_default_tshirt_size_options() {
		return array_map(
			static function ($value) {
				return [
					'value' => $value,
					'label' => $value,
				];
			},
			[
				'No T-shirt',
				'Unisex Small',
				'Unisex Medium',
				'Unisex Large',
				'Unisex X-Large',
				'Unisex XX-Large',
			]
		);
	}

	private function get_pmpro_user_field_definition($candidate_keys, $candidate_label_fragments = []) {
		$candidate_keys = array_map('strtolower', array_map('strval', (array) $candidate_keys));
		$candidate_label_fragments = array_map('strtolower', array_map('strval', (array) $candidate_label_fragments));
		$groups = get_option('pmpro_user_fields_settings', []);
		if (is_object($groups)) {
			$groups = get_object_vars($groups);
		}
		if (!is_array($groups)) {
			return null;
		}

		foreach ($groups as $group) {
			if (is_object($group)) {
				$group = get_object_vars($group);
			}
			if (!is_array($group)) {
				continue;
			}

			$fields = $group['fields'] ?? [];
			if (is_object($fields)) {
				$fields = get_object_vars($fields);
			}
			if (!is_array($fields)) {
				continue;
			}

			foreach ($fields as $field) {
				if (is_object($field)) {
					$field = get_object_vars($field);
				}
				if (!is_array($field)) {
					continue;
				}

				$meta_key = strtolower(trim((string) ($field['meta_key'] ?? $field['name'] ?? $field['id'] ?? '')));
				$label = strtolower(trim((string) ($field['label'] ?? $field['title'] ?? '')));
				if (in_array($meta_key, $candidate_keys, true)) {
					return $field;
				}

				foreach ($candidate_label_fragments as $fragment) {
					if ($fragment !== '' && strpos($label, $fragment) !== false) {
						return $field;
					}
				}
			}
		}

		return null;
	}

	private function normalize_print_digital_value($value, $fallback = 'Print') {
		return $value === 'Print' ? 'Print' : ($value === 'Digital' ? 'Digital' : $fallback);
	}

	private function get_normalized_publication_preferences($values) {
		$values = is_array($values) ? $values : [];
		$legacy_publication_pref = $this->normalize_print_digital_value(
			$values['publication_pref'] ?? '',
			$this->normalize_print_digital_value(
				$values['aaj_pref'] ?? '',
				$this->normalize_print_digital_value(
					$values['anac_pref'] ?? '',
					$this->normalize_print_digital_value(
						$values['acj_pref'] ?? '',
						$this->normalize_print_digital_value($values['guidebook_pref'] ?? 'Print')
					)
				)
			)
		);
		$guidebook_pref = $this->normalize_print_digital_value($values['guidebook_pref'] ?? 'Print');

		return [
			'publication_pref' => $legacy_publication_pref,
			'aaj_pref' => $this->normalize_print_digital_value($values['aaj_pref'] ?? $legacy_publication_pref),
			'anac_pref' => $this->normalize_print_digital_value($values['anac_pref'] ?? $legacy_publication_pref),
			'acj_pref' => $this->normalize_print_digital_value($values['acj_pref'] ?? $legacy_publication_pref),
			'guidebook_pref' => $guidebook_pref,
		];
	}

	private function sync_reportable_member_fields($user_id, $account_info, $magazine_addons = null, $membership_discount_type = null) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !is_array($account_info)) {
			return;
		}

		$first_name = sanitize_text_field($account_info['first_name'] ?? '');
		$last_name = sanitize_text_field($account_info['last_name'] ?? '');
		update_user_meta($user_id, 'first_name', $first_name);
		update_user_meta($user_id, 'last_name', $last_name);
		update_user_meta($user_id, 'pmpro_sfirstname', $first_name);
		update_user_meta($user_id, 'pmpro_slastname', $last_name);
		update_user_meta($user_id, 't_shirt', sanitize_text_field($account_info['size'] ?? ''));
		update_user_meta($user_id, 'birthdate', $this->sanitize_birthdate_value($account_info['birthdate'] ?? ''));
		$phone = sanitize_text_field($account_info['phone'] ?? '');
		$street = sanitize_text_field($account_info['street'] ?? '');
		$address2 = sanitize_text_field($account_info['address2'] ?? '');
		$city = sanitize_text_field($account_info['city'] ?? '');
		$state = sanitize_text_field($account_info['state'] ?? '');
		$zip = sanitize_text_field($account_info['zip'] ?? '');
		$country = sanitize_text_field($account_info['country'] ?? '');
		update_user_meta($user_id, 'pmpro_sphone', $phone);
		update_user_meta($user_id, 'pmpro_saddress1', $street);
		update_user_meta($user_id, 'pmpro_saddress2', $address2);
		update_user_meta($user_id, 'pmpro_scity', $city);
		update_user_meta($user_id, 'pmpro_sstate', $state);
		update_user_meta($user_id, 'pmpro_szipcode', $zip);
		update_user_meta($user_id, 'pmpro_scountry', $country);
		$this->update_emergency_contact_user_meta($user_id, [
			'emergency_contact_first_name' => sanitize_text_field($account_info['emergency_contact_first_name'] ?? ''),
			'emergency_contact_last_name' => sanitize_text_field($account_info['emergency_contact_last_name'] ?? ''),
			'emergency_contact_phone' => sanitize_text_field($account_info['emergency_contact_phone'] ?? ''),
			'emergency_contact_email' => sanitize_email($account_info['emergency_contact_email'] ?? ''),
			'emergency_contact_relationship' => sanitize_text_field($account_info['emergency_contact_relationship'] ?? ''),
		]);
		update_user_meta($user_id, 'aaj_preference', $this->normalize_print_digital_value($account_info['aaj_pref'] ?? 'Print'));
		update_user_meta($user_id, 'anac_preference', $this->normalize_print_digital_value($account_info['anac_pref'] ?? 'Print'));
		update_user_meta($user_id, 'anan_preference', $this->normalize_print_digital_value($account_info['anac_pref'] ?? 'Print'));
		update_user_meta($user_id, 'american_climbing_journal_preference', $this->normalize_print_digital_value($account_info['acj_pref'] ?? 'Print'));
		update_user_meta($user_id, 'acj_preference', $this->normalize_print_digital_value($account_info['acj_pref'] ?? 'Print'));
		update_user_meta($user_id, 'guidebook_preferences', $this->normalize_print_digital_value($account_info['guidebook_pref'] ?? 'Print'));
		update_user_meta($user_id, 'guidebook_preference', $this->normalize_print_digital_value($account_info['guidebook_pref'] ?? 'Print'));
		delete_user_meta($user_id, 'aac_tshirt_size');
		delete_user_meta($user_id, 'aac_birthdate');
		delete_user_meta($user_id, 'aac_publication_pref');
		delete_user_meta($user_id, 'aac_aaj_pref');
		delete_user_meta($user_id, 'aac_anac_pref');
		delete_user_meta($user_id, 'aac_acj_pref');
		delete_user_meta($user_id, 'aac_guidebook_pref');

		$selected_addons = $magazine_addons === null
			? $this->get_effective_magazine_addon_selection($user_id)
			: $this->normalize_magazine_addon_selection($magazine_addons);

		update_user_meta($user_id, 'aac_magazine_addons', $selected_addons);

		$catalog = $this->get_magazine_addon_catalog();
		$labels = [];
		foreach ($selected_addons as $slug) {
			if (!empty($catalog[$slug]['label'])) {
				$labels[] = (string) $catalog[$slug]['label'];
			}
		}

		update_user_meta($user_id, 'aac_magazine_subscription_labels', implode(', ', $labels));
		update_user_meta($user_id, 'aac_has_alpinist_subscription', in_array('alpinist', $selected_addons, true) ? '1' : '0');
		update_user_meta($user_id, 'aac_has_backcountry_subscription', in_array('backcountry', $selected_addons, true) ? '1' : '0');

			$normalized_discount_type = $membership_discount_type === null
				? $this->get_effective_membership_discount_type($user_id)
				: $this->normalize_membership_discount_type($membership_discount_type);
			update_user_meta($user_id, 'aac_membership_discount_type', $normalized_discount_type);
			$student_university = sanitize_text_field($account_info['student_university'] ?? '');
			$student_university_id = sanitize_text_field($account_info['student_university_id'] ?? '');
			$graduation_date = sanitize_text_field($account_info['graduation_date'] ?? '');
			$service_component = sanitize_text_field($account_info['service_component'] ?? '');
			update_user_meta($user_id, 'student_university', $student_university);
			update_user_meta($user_id, 'university_or_school', $student_university);
			update_user_meta($user_id, 'student_university_id', $student_university_id);
			update_user_meta($user_id, 'graduation_date', $graduation_date);
			update_user_meta($user_id, 'student_graduation_date', $graduation_date);
			update_user_meta($user_id, 'service_component', $service_component);
			update_user_meta($user_id, 'military_service_component', $service_component);

			$family_config = $this->get_effective_partner_family_config($user_id);
		update_user_meta($user_id, 'aac_partner_family_mode', $family_config['mode']);
		update_user_meta($user_id, 'aac_partner_family_additional_adult', !empty($family_config['additional_adult']) ? '1' : '0');
		update_user_meta($user_id, 'aac_partner_family_dependents', max(0, (int) ($family_config['dependent_count'] ?? 0)));
		update_user_meta($user_id, 'aac_family_account_role', $this->get_family_account_role($user_id, $family_config));
	}

	public function get_emergency_contact_meta_key_candidates($logical_key) {
		$fallback_map = [
			'emergency_contact_first_name' => ['emergency_contact_first_name', 'emergency_first_name', 'emergency_first'],
			'emergency_contact_last_name' => ['emergency_contact_last_name', 'emergency_last_name', 'emergency_last'],
			'emergency_contact_phone' => ['emergency_contact_phone', 'emergency_phone', 'emergency_contact_phone_number'],
			'emergency_contact_email' => ['emergency_contact_email', 'emergency_email'],
			'emergency_contact_relationship' => ['emergency_contact_relationship', 'emergency_relationship'],
		];

		$candidates = $fallback_map[$logical_key] ?? [$logical_key];
		$config = $this->get_pmpro_emergency_contact_field_config();
		if (!empty($config[$logical_key]['meta_key'])) {
			array_unshift($candidates, $config[$logical_key]['meta_key']);
		}

		$normalized = [];
		foreach ($candidates as $candidate) {
			$normalized_candidate = sanitize_key((string) $candidate);
			if ($normalized_candidate !== '' && !in_array($normalized_candidate, $normalized, true)) {
				$normalized[] = $normalized_candidate;
			}
		}

		return $normalized;
	}

	public function get_emergency_contact_relationship_options() {
		$config = $this->get_pmpro_emergency_contact_field_config();
		$options = $config['emergency_contact_relationship']['options'] ?? [];

		return array_values(array_filter(array_map(static function ($option) {
			if (is_string($option)) {
				$value = trim($option);
				return $value === '' ? null : ['value' => $value, 'label' => $value];
			}

			if (!is_array($option)) {
				return null;
			}

			$value = sanitize_text_field($option['value'] ?? $option['label'] ?? '');
			$label = sanitize_text_field($option['label'] ?? $option['value'] ?? '');
			if ($value === '' || $label === '') {
				return null;
			}

			return ['value' => $value, 'label' => $label];
		}, $options)));
	}

	private function update_emergency_contact_user_meta($user_id, $account_info) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !is_array($account_info)) {
			return;
		}

		foreach ($this->get_pmpro_emergency_contact_field_config() as $logical_key => $field) {
			$meta_key = sanitize_key((string) ($field['meta_key'] ?? ''));
			if ($meta_key === '') {
				continue;
			}

			$value = $account_info[$logical_key] ?? '';
			update_user_meta($user_id, $meta_key, $value);
		}
	}

	private function get_pmpro_emergency_contact_field_config() {
		$config = [
			'emergency_contact_first_name' => ['meta_key' => 'emergency_contact_first_name', 'label' => 'First Name', 'options' => []],
			'emergency_contact_last_name' => ['meta_key' => 'emergency_contact_last_name', 'label' => 'Last Name', 'options' => []],
			'emergency_contact_phone' => ['meta_key' => 'emergency_contact_phone', 'label' => 'Phone Number', 'options' => []],
			'emergency_contact_email' => ['meta_key' => 'emergency_contact_email', 'label' => 'Email', 'options' => []],
			'emergency_contact_relationship' => ['meta_key' => 'emergency_contact_relationship', 'label' => 'Relationship', 'options' => []],
		];

		$groups = get_option('pmpro_user_fields_settings', []);
		if (!is_array($groups)) {
			return $config;
		}

		foreach ($groups as $group) {
			$group_data = is_object($group) ? get_object_vars($group) : (is_array($group) ? $group : []);
			$group_name = sanitize_text_field($group_data['name'] ?? '');
			if (strcasecmp($group_name, 'Emergency Contact') !== 0) {
				continue;
			}

			$fields = $group_data['fields'] ?? [];
			if (is_object($fields)) {
				$fields = get_object_vars($fields);
			}

			if (!is_array($fields)) {
				break;
			}

			foreach ($fields as $field) {
				$field_data = is_object($field) ? get_object_vars($field) : (is_array($field) ? $field : []);
				$meta_key = sanitize_key((string) ($field_data['name'] ?? ''));
				$label = sanitize_text_field($field_data['label'] ?? '');
				if ($meta_key === '' && $label === '') {
					continue;
				}

				$slot = $this->match_emergency_contact_field_slot($meta_key, $label);
				if (!$slot || !isset($config[$slot])) {
					continue;
				}

				if ($meta_key !== '') {
					$config[$slot]['meta_key'] = $meta_key;
				}
				if ($label !== '') {
					$config[$slot]['label'] = $label;
				}
				if ($slot === 'emergency_contact_relationship') {
					$config[$slot]['options'] = $this->normalize_pmpro_field_options($field_data['options'] ?? []);
				}
			}

			break;
		}

		return $config;
	}

	private function match_emergency_contact_field_slot($meta_key, $label) {
		$haystack = strtolower(trim($meta_key . ' ' . $label));
		$haystack = str_replace(['-', '_'], ' ', $haystack);

		if (strpos($haystack, 'relationship') !== false) {
			return 'emergency_contact_relationship';
		}
		if (strpos($haystack, 'phone') !== false) {
			return 'emergency_contact_phone';
		}
		if (strpos($haystack, 'email') !== false) {
			return 'emergency_contact_email';
		}
		if (strpos($haystack, 'last') !== false) {
			return 'emergency_contact_last_name';
		}
		if (strpos($haystack, 'first') !== false) {
			return 'emergency_contact_first_name';
		}

		return null;
	}

	private function normalize_pmpro_field_options($options) {
		if (is_object($options)) {
			$options = get_object_vars($options);
		}

		if (!is_array($options)) {
			return [];
		}

		$normalized = [];
		foreach ($options as $key => $option) {
			if (is_object($option)) {
				$option = get_object_vars($option);
			}

			if (is_array($option)) {
				$value = sanitize_text_field($option['value'] ?? $option['label'] ?? $option['text'] ?? $key);
				$label = sanitize_text_field($option['label'] ?? $option['text'] ?? $option['value'] ?? $key);
			} else {
				$value = sanitize_text_field(is_string($key) ? $key : (string) $option);
				$label = sanitize_text_field((string) $option);
			}

			if ($value === '' || $label === '') {
				continue;
			}

			$normalized[] = ['value' => $value, 'label' => $label];
		}

		return $normalized;
	}

	private function sanitize_birthdate_value($value) {
		$normalized = sanitize_text_field((string) $value);
		$normalized = trim($normalized);
		if ($normalized === '') {
			return '';
		}

		return preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized) ? $normalized : '';
	}

	private function get_family_account_role($user_id, $family_config = null) {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return '';
		}

		if ($this->get_linked_parent_user_id($user_id) > 0) {
			return 'Child';
		}

		$family_config = is_array($family_config) ? $family_config : $this->get_effective_partner_family_config($user_id);
		$connected_accounts = get_user_meta($user_id, 'aac_connected_accounts', true);
		if (($family_config['mode'] ?? '') === 'family' || (is_array($connected_accounts) && !empty($connected_accounts))) {
			return 'Parent';
		}

		return '';
	}

	private function get_linked_parent_user_id($user_id) {
		return absint(get_user_meta((int) $user_id, 'aac_linked_parent_user_id', true));
	}

	private function generate_unique_username_from_email($email) {
		$base_username = sanitize_user(str_replace(['@', '.', '+', '-'], '_', strtolower($email)), true);
		if ($base_username === '') {
			$base_username = 'aac_member';
		}

		$username = $base_username;
		$suffix = 1;

		while (username_exists($username)) {
			$username = sprintf('%s%d', $base_username, $suffix);
			$suffix++;
		}

		return $username;
	}

	public function mark_script_as_module($tag, $handle, $src) {
		if ($handle !== self::SCRIPT_HANDLE) {
			return $tag;
		}

		return sprintf(
			'<script type="module" src="%s" id="%s-js"></script>',
			esc_url($src),
			esc_attr($handle)
		);
	}

	private function locate_asset_files() {
		$asset_dir = AAC_MEMBER_PORTAL_DIR . 'app/assets/';
		$asset_url = AAC_MEMBER_PORTAL_URL . 'app/assets/';
		$index_html_path = AAC_MEMBER_PORTAL_DIR . 'app/index.html';
		$script_path = null;
		$style_path = null;

		if (file_exists($index_html_path) && is_readable($index_html_path)) {
			$index_html = (string) file_get_contents($index_html_path);
			if (preg_match('#src="/?assets/(index-[^"]+\.js)"#', $index_html, $script_match)) {
				$candidate = $asset_dir . $script_match[1];
				if (file_exists($candidate)) {
					$script_path = $candidate;
				}
			}

			if (preg_match('#href="/?assets/(index-[^"]+\.css)"#', $index_html, $style_match)) {
				$candidate = $asset_dir . $style_match[1];
				if (file_exists($candidate)) {
					$style_path = $candidate;
				}
			}
		}

		if (!$script_path) {
			$script_path = $this->first_glob_match($asset_dir . 'index-*.js');
		}

		if (!$style_path) {
			$style_path = $this->first_glob_match($asset_dir . 'index-*.css');
		}

		return [
			'script' => $script_path ? $asset_url . basename($script_path) : null,
			'style' => $style_path ? $asset_url . basename($style_path) : null,
		];
	}

	private function get_runtime_config($embed_mode = '') {
		$embed_mode = sanitize_key((string) $embed_mode);
		$initial_auth = null;
		if (is_user_logged_in() && class_exists('AAC_Member_Portal_API')) {
			$api = AAC_Member_Portal_API::get_instance();
			if ($api && method_exists($api, 'get_current_user_auth_payload')) {
				$initial_auth = $api->get_current_user_auth_payload();
			}
		}

		// This config blob is the frontend's treasure map. Without it, the React app
		// would have no clue where the API lives, which nonce to use, or what staff
		// just changed in WordPress.
		return [
			'mountId' => self::MOUNT_ID,
			'routerMode' => 'hash',
			'embedMode' => $embed_mode,
			'initialRoute' => $embed_mode === 'signup' ? '/join' : ($embed_mode === 'login' ? '/login' : ''),
			'apiBase' => untrailingslashit(rest_url('aac/v1')),
			'restNonce' => wp_create_nonce('wp_rest'),
			'isLoggedIn' => is_user_logged_in(),
			'initialAuth' => $initial_auth,
			'portalPageUrl' => untrailingslashit($this->get_portal_page_url()),
			'mainWebsiteBaseUrl' => untrailingslashit(home_url()),
			'pmproCheckoutUrl' => untrailingslashit($this->get_pmpro_page_url('checkout', '/membership-checkout/')),
			'pmproConfirmationUrl' => untrailingslashit($this->get_pmpro_page_url('confirmation', '/membership-checkout/membership-confirmation/')),
			'pmproLevelIds' => $this->get_membership_level_ids(),
			'assetBaseUrl' => trailingslashit(AAC_MEMBER_PORTAL_URL . 'app/assets'),
			'pmproSocialLoginHtml' => $this->get_pmpro_social_login_markup(),
			'portalSettings' => $this->get_portal_ui_settings(),
		];
	}

	private function get_pmpro_social_login_markup() {
		$candidate_shortcodes = [
			'pmpro_social_login',
			'pmpro_social_logins',
			'pmprosl_login',
			'pmprosl_social_login',
			'nextend_social_login',
		];

		foreach ($candidate_shortcodes as $shortcode_tag) {
			if (!shortcode_exists($shortcode_tag)) {
				continue;
			}

			$markup = trim((string) do_shortcode('[' . $shortcode_tag . ']'));
			if (strpos($markup, '[nextend_social_login]') !== false && shortcode_exists('nextend_social_login')) {
				$markup = trim((string) do_shortcode($markup));
			}
			if ($markup !== '') {
				return $markup;
			}
		}

		if (shortcode_exists('nextend_social_login')) {
			$markup = trim((string) do_shortcode('[nextend_social_login]'));
			if ($markup !== '') {
				return $markup;
			}
		}

		return '';
	}

	public function get_portal_ui_settings() {
		$settings = AAC_Member_Portal_Admin::get_settings();
		$runtime_config = new AAC_Member_Portal_Runtime_Config(
			$this->get_portal_page_url(),
			$this->get_top_nav_item_registry($this->get_portal_page_url()),
			$this->get_sidebar_item_registry()
		);

		return $runtime_config->get_portal_ui_settings($settings);
	}

	public function get_template_top_nav_sections($portal_url) {
		$settings = AAC_Member_Portal_Admin::get_settings();
		$registry = $this->get_top_nav_item_registry($portal_url);
		$sections = [];

		foreach ($settings['components']['top_nav_items'] as $item_id => $item_settings) {
			if ($item_id === 'get_involved') {
				continue;
			}

			if (empty($item_settings['visible']) || empty($registry[$item_id])) {
				continue;
			}

			$section = $registry[$item_id];
			$section['id'] = $item_id;
			$section['label'] = $item_settings['label'];
			$section['children'] = isset($item_settings['children']) && is_array($item_settings['children']) && !empty($item_settings['children'])
				? array_values($item_settings['children'])
				: $section['children'];
			$section['children'] = $this->normalize_join_nav_children($section['children']);
			if ($item_id === 'membership') {
				$section['children'] = $this->ensure_membership_sign_in_nav_child($section['children'], $portal_url);
			}
			$section['order'] = (int) $item_settings['order'];
			$sections[] = $section;
		}

		usort($sections, static function ($left, $right) {
			return ($left['order'] ?? 0) <=> ($right['order'] ?? 0);
		});

		return $sections;
	}

	private function normalize_join_nav_children($children) {
		$join_url = home_url('/signup/');
		foreach ($children as $child_index => $child) {
			$label = strtolower(trim((string) ($child['label'] ?? '')));
			$href = rtrim(trim((string) ($child['href'] ?? '')), '/');
			if ($label === 'join' || $label === 'sign up' || in_array($href, ['/join', 'https://membership.americanalpineclub.org/join'], true)) {
				$children[$child_index]['href'] = $join_url;
				$children[$child_index]['external'] = false;
				unset($children[$child_index]['path']);
			}
		}
		return $children;
	}

	public function get_template_sidebar_sections($portal_url) {
		$settings = AAC_Member_Portal_Admin::get_settings();
		$registry = $this->get_sidebar_item_registry();
		$sections = [];

		foreach ($settings['components']['section_titles'] as $section_id => $section_title) {
			$sections[$section_id] = [
				'id' => $section_id,
				'title' => $section_title,
				'items' => [],
			];
		}

		foreach ($settings['components']['sidebar_items'] as $item_id => $item_settings) {
			if (empty($item_settings['visible']) || empty($registry[$item_id])) {
				continue;
			}
			$section_id = $item_settings['section'];
			if (!isset($sections[$section_id])) {
				continue;
			}

			$href = !empty($registry[$item_id]['href'])
				? $registry[$item_id]['href']
				: untrailingslashit($portal_url) . '/#' . ltrim($registry[$item_id]['route'], '/');
			$sections[$section_id]['items'][] = [
				'id' => $item_id,
				'label' => $item_settings['label'],
				'href' => $href,
				'icon' => $registry[$item_id]['icon'],
				'order' => (int) $item_settings['order'],
				'active' => false,
			];
		}

		foreach ($sections as &$section) {
			usort($section['items'], static function ($left, $right) {
				return ($left['order'] ?? 0) <=> ($right['order'] ?? 0);
			});
		}
		unset($section);

		return array_values(array_filter($sections, static function ($section) {
			return !empty($section['items']);
		}));
	}

	public function get_template_design_settings() {
		$settings = AAC_Member_Portal_Admin::get_settings();

		return [
			'sidebar_background_url' => AAC_Member_Portal_Runtime_Config::resolve_sidebar_background_url($settings),
			'sidebar_overlay_start' => $settings['design']['sidebar_overlay_start'],
			'sidebar_overlay_end' => $settings['design']['sidebar_overlay_end'],
			'sidebar_button_background' => $settings['design']['sidebar_button_background'],
			'sidebar_button_hover_background' => $settings['design']['sidebar_button_hover_background'],
			'sidebar_button_active_background' => $settings['design']['sidebar_button_active_background'],
			'sidebar_accent_color' => $settings['design']['sidebar_accent_color'],
			'publication_tile_images' => [
				'aaj' => $settings['design']['publication_tile_image_aaj'],
				'anac' => $settings['design']['publication_tile_image_anac'],
				'acj' => $settings['design']['publication_tile_image_acj'],
				'guidebook' => $settings['design']['publication_tile_image_guidebook'],
			],
		];
	}

	private function get_current_member_billing_url() {
		$account_url = $this->get_pmpro_page_url('account', '/membership-account/');
		$billing_url = $this->get_pmpro_page_url('billing', '/membership-account/membership-billing/');

		if (!is_user_logged_in()) {
			return $billing_url ?: $account_url;
		}

		$user_id = get_current_user_id();
		$primary_membership = $user_id ? AAC_Member_Portal_PMPro::get_primary_membership($user_id) : null;
		if (!$primary_membership) {
			return $account_url;
		}

		$actions = AAC_Member_Portal_PMPro::build_membership_actions($user_id, ['tier' => $primary_membership['tier']]);
		if (!empty($actions['billing_url'])) {
			$action_billing_url = (string) $actions['billing_url'];
			if (untrailingslashit((string) wp_parse_url($action_billing_url, PHP_URL_PATH)) !== untrailingslashit((string) wp_parse_url($account_url, PHP_URL_PATH))) {
				return $action_billing_url;
			}
		}

		return $billing_url ?: $account_url;
	}

	private function ensure_membership_sign_in_nav_child($children, $portal_url) {
		$children = is_array($children) ? array_values($children) : [];
		foreach ($children as $child) {
			$label = isset($child['label']) ? strtolower(trim((string) $child['label'])) : '';
			if (in_array($label, ['sign in', 'login', 'log in'], true)) {
				return $children;
			}
		}

		$portal_url = untrailingslashit((string) $portal_url);
		$sign_in_child = ['label' => 'Sign In', 'href' => $portal_url . '#/login'];
		$insert_after = null;
		foreach ($children as $index => $child) {
			$label = isset($child['label']) ? strtolower(trim((string) $child['label'])) : '';
			if ($label === 'join') {
				$insert_after = $index;
				break;
			}
		}

		if ($insert_after === null) {
			$children[] = $sign_in_child;
			return $children;
		}

		array_splice($children, $insert_after + 1, 0, [$sign_in_child]);
		return $children;
	}

	public function get_top_nav_item_registry($portal_url) {
		$portal_url = untrailingslashit((string) $portal_url);

		return [
			'get_involved' => [
				'label' => 'Get Involved',
				'href' => home_url('/get-involved/'),
				'children' => [
					['label' => 'Volunteer', 'href' => home_url('/volunteer/')],
					['label' => 'Donate', 'href' => 'https://membership.americanalpineclub.org/donate', 'external' => true],
					['label' => 'Sign Up', 'href' => home_url('/signup/'), 'external' => false],
				],
			],
			'membership' => [
				'label' => 'Membership',
				'href' => home_url('/membership/'),
				'children' => [
					['label' => 'Benefits', 'href' => $portal_url . '#/discounts'],
					['label' => 'Join', 'href' => home_url('/signup/')],
					['label' => 'Sign In', 'href' => $portal_url . '#/login'],
					['label' => 'Renew', 'href' => 'https://membership.americanalpineclub.org/renew', 'external' => true],
				],
			],
			'stories_news' => [
				'label' => 'Stories & News',
				'href' => home_url('/stories/'),
				'children' => [
					['label' => 'Articles & News', 'href' => home_url('/stories/')],
					['label' => 'The Prescription', 'href' => home_url('/prescription/')],
					['label' => 'The Line', 'href' => home_url('/line-archive/')],
				],
			],
			'publications' => [
				'label' => 'Publications',
				'href' => home_url('/publications/'),
				'children' => [
					['label' => 'AAJ', 'href' => home_url('/publications/aaj/')],
					['label' => 'Accidents', 'href' => home_url('/publications/accidents/')],
				],
			],
			'our_work' => [
				'label' => 'Our Work',
				'href' => home_url('/our-work/'),
				'children' => [
					['label' => "Gov't Affairs", 'href' => home_url('/advocacy/')],
					['label' => 'Grief Fund', 'href' => home_url('/grieffund/')],
					['label' => 'Library', 'href' => home_url('/library/')],
					['label' => 'Chapters', 'href' => home_url('/chapters/')],
				],
			],
		];
	}

	private function get_sidebar_item_registry() {
		return [
			'member_profile' => ['icon' => 'user', 'route' => '/profile'],
			'account' => ['icon' => 'pen', 'route' => '/account'],
			'publications' => ['icon' => 'book', 'route' => '/publications'],
			'manage' => ['icon' => 'settings', 'route' => '/membership'],
			'discounts' => ['icon' => 'badge-percent', 'route' => '/discounts'],
			'contact' => ['icon' => 'mail', 'route' => '/contact'],
		];
	}

	private function get_shortcode_post() {
		if (!is_singular()) {
			return null;
		}

		$post = get_post();
		if (!$post instanceof WP_Post) {
			return null;
		}

		if (in_array((string) $post->post_name, ['member-profile', 'membership'], true)) {
			return $post;
		}

		if (
			!has_shortcode($post->post_content, self::SHORTCODE) &&
			!has_shortcode($post->post_content, self::SIGNUP_SHORTCODE)
		) {
			return null;
		}

		return $post;
	}

	private function get_fullscreen_shortcode_post() {
		if (!is_singular()) {
			return null;
		}

		$post = get_post();
		if (!$post instanceof WP_Post) {
			return null;
		}

		if (
			!has_shortcode($post->post_content, self::SHORTCODE) &&
			!has_shortcode($post->post_content, self::SIGNUP_SHORTCODE)
		) {
			return null;
		}

		return $post;
	}

	private function post_has_member_portal_signup_mode($content) {
		if (!has_shortcode($content, self::SHORTCODE)) {
			return false;
		}

		$pattern = get_shortcode_regex([self::SHORTCODE]);
		if (!preg_match_all('/' . $pattern . '/', $content, $matches, PREG_SET_ORDER)) {
			return false;
		}

		foreach ($matches as $shortcode_match) {
			if (($shortcode_match[2] ?? '') !== self::SHORTCODE) {
				continue;
			}

			$atts = shortcode_parse_atts($shortcode_match[3] ?? '');
			$mode = sanitize_key((string) (($atts['mode'] ?? '') ?: ($atts['embed'] ?? '')));
			if (in_array($mode, ['signup', 'join'], true)) {
				return true;
			}
		}

		return false;
	}

	private function get_pmpro_shell_post() {
		if (!AAC_Member_Portal_PMPro::is_available() || !function_exists('pmpro_url')) {
			return null;
		}

		$post = get_post();
		// A portal page must retain its own rendering mode even if a broken PMPro
		// page assignment points checkout or account at the same WordPress page.
		if (
			$post instanceof WP_Post
			&& (
				has_shortcode($post->post_content, self::SIGNUP_SHORTCODE)
				|| has_shortcode($post->post_content, self::SHORTCODE)
			)
		) {
			return null;
		}

		$current_permalink = $post instanceof WP_Post ? untrailingslashit(get_permalink($post)) : '';
		$current_path = $current_permalink ? untrailingslashit((string) wp_parse_url($current_permalink, PHP_URL_PATH)) : '';
		$request_path = '';
		if (!empty($_SERVER['REQUEST_URI'])) {
			$request_path = untrailingslashit((string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH));
		}
		$managed_pages = [
			untrailingslashit(pmpro_url('account')),
			untrailingslashit(pmpro_url('billing')),
			untrailingslashit(pmpro_url('invoice')),
			untrailingslashit(pmpro_url('cancel')),
			untrailingslashit(pmpro_url('checkout')),
			untrailingslashit(pmpro_url('confirmation')),
		];

		foreach ($managed_pages as $managed_page) {
			if (!$managed_page) {
				continue;
			}

			$managed_path = untrailingslashit((string) wp_parse_url($managed_page, PHP_URL_PATH));
			if (
				$managed_page === $current_permalink ||
				($managed_path && $managed_path === $current_path) ||
				($managed_path && $managed_path === $request_path)
			) {
				if ($post instanceof WP_Post) {
					return $post;
				}

				$queried = get_queried_object();
				return $queried instanceof WP_Post ? $queried : null;
			}
		}

		$managed_paths = [
			'membership-account',
			'membership-account/membership-billing',
			'membership-account/membership-orders',
			'membership-account/membership-invoice',
			'membership-account/membership-cancel',
			'membership-billing',
			'membership-orders',
			'membership-invoice',
			'membership-cancel',
			'membership-checkout',
			'membership-checkout/membership-confirmation',
			'membership-confirmation',
		];
		$normalized_request_path = ltrim($request_path, '/');
		if ($normalized_request_path) {
			foreach ($managed_paths as $managed_path) {
				if ($normalized_request_path !== $managed_path) {
					continue;
				}

				$managed_post = get_page_by_path($managed_path, OBJECT, 'page');
				if ($managed_post instanceof WP_Post) {
					return $managed_post;
				}

				$fallback_path = strpos($managed_path, 'membership-checkout') === 0 || $managed_path === 'membership-confirmation'
					? 'membership-checkout'
					: 'membership-account';
				$fallback_post = get_page_by_path($fallback_path, OBJECT, 'page');
				if ($fallback_post instanceof WP_Post) {
					return $fallback_post;
				}
			}
		}

		if (isset($_GET['levelstocancel'])) {
			$account_post = get_page_by_path('membership-account', OBJECT, 'page');
			if ($account_post instanceof WP_Post) {
				return $account_post;
			}
		}

		$managed_slugs = ['membership-account', 'membership-billing', 'membership-orders', 'membership-invoice', 'membership-cancel', 'membership-checkout', 'membership-confirmation'];
		if ($post instanceof WP_Post && in_array($post->post_name, $managed_slugs, true)) {
			return $post;
		}

		return null;
	}

	private function get_public_shell_post() {
		if (!is_singular('page')) {
			return null;
		}

		$post = get_post();
		if (!$post instanceof WP_Post) {
			return null;
		}

		$public_slugs = ['benefits'];
		if (!in_array($post->post_name, $public_slugs, true)) {
			return null;
		}

		return $post;
	}

	private function should_use_portal_login($redirect = '') {
		if ($this->is_wp_admin_auth_request($redirect)) {
			return false;
		}

		if ($this->is_pmpro_frontend_request()) {
			return true;
		}

		return $this->is_pmpro_frontend_url($redirect);
	}

	private function is_frontend_login_request() {
		$login_path = $this->normalize_path(home_url('/login/'));
		$request_path = $this->get_current_request_path();

		return $request_path && $login_path && $request_path === $login_path;
	}

	private function is_pmpro_frontend_request() {
		$request_path = $this->get_current_request_path();
		if (!$request_path) {
			return false;
		}

		foreach ($this->get_pmpro_frontend_paths() as $managed_path) {
			if ($managed_path && $managed_path === $request_path) {
				return true;
			}
		}

		return false;
	}

	private function is_pmpro_frontend_url($url) {
		if (!$url) {
			return false;
		}

		$target_path = $this->normalize_path($url);
		if (!$target_path) {
			return false;
		}

		foreach ($this->get_pmpro_frontend_paths() as $managed_path) {
			if ($managed_path && $managed_path === $target_path) {
				return true;
			}
		}

		return false;
	}

	private function is_pmpro_confirmation_url($url) {
		$target_path = $this->normalize_path($url);
		if (!$target_path) {
			return false;
		}

		$confirmation_paths = [
			$this->normalize_path(home_url('/membership-checkout/membership-confirmation/')),
			$this->normalize_path(home_url('/membership-confirmation/')),
		];

		if (AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url')) {
			$confirmation_paths[] = $this->normalize_path(pmpro_url('confirmation'));
		}

		return in_array($target_path, array_filter(array_unique($confirmation_paths)), true);
	}

	private function is_wp_admin_auth_request($redirect = '') {
		if (isset($_REQUEST['interim-login']) || isset($_REQUEST['reauth'])) {
			return true;
		}

		$request_path = $this->get_current_request_path();
		if ($request_path && ($this->is_wp_admin_path($request_path) || $request_path === $this->normalize_path($this->get_wp_login_base_url()))) {
			return true;
		}

		if ($redirect && $this->is_wp_admin_url($redirect)) {
			return true;
		}

		return false;
	}

	private function should_preserve_wp_login_url($login_url, $redirect = '') {
		if (!$login_url) {
			return false;
		}

		$query = wp_parse_url($login_url, PHP_URL_QUERY);
		if (!is_string($query) || $query === '') {
			return $redirect && $this->is_wp_admin_url($redirect);
		}

		parse_str($query, $query_args);
		if (!empty($query_args['interim-login']) || !empty($query_args['reauth'])) {
			return true;
		}

		$action = isset($query_args['action']) ? sanitize_key((string) $query_args['action']) : '';
		if (in_array($action, ['lostpassword', 'retrievepassword', 'rp', 'resetpass'], true)) {
			return true;
		}

		if (!empty($query_args['checkemail']) || !empty($query_args['key']) || !empty($query_args['login']) || !empty($query_args['resetpass'])) {
			return true;
		}

		if (!empty($query_args['redirect_to']) && $this->is_wp_admin_url($query_args['redirect_to'])) {
			return true;
		}

		return $redirect && $this->is_wp_admin_url($redirect);
	}

	private function build_wp_login_url_from_current_request($redirect = '') {
		$query_args = [];

		if ($redirect) {
			$query_args['redirect_to'] = $redirect;
		}

		if (isset($_GET['interim-login'])) {
			$query_args['interim-login'] = sanitize_text_field(wp_unslash($_GET['interim-login']));
		}

		if (isset($_GET['reauth'])) {
			$query_args['reauth'] = sanitize_text_field(wp_unslash($_GET['reauth']));
		}

		if (isset($_GET['wp_lang'])) {
			$query_args['wp_lang'] = sanitize_text_field(wp_unslash($_GET['wp_lang']));
		}

		return add_query_arg($query_args, $this->get_wp_login_base_url());
	}

	private function is_wp_admin_url($url) {
		$target_path = $this->normalize_path($url);
		if (!$target_path) {
			return false;
		}

		return $this->is_wp_admin_path($target_path);
	}

	private function is_wp_admin_path($path) {
		$admin_path = $this->normalize_path(admin_url());
		if (!$path || !$admin_path) {
			return false;
		}

		return $path === $admin_path || strpos($path, $admin_path . '/') === 0;
	}

	private function get_pmpro_frontend_paths() {
		$managed_paths = [];
		$pmpro_pages = ['account', 'billing', 'cancel', 'checkout'];

		foreach ($pmpro_pages as $page) {
			$page_url = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url')
				? pmpro_url($page)
				: '';
			$page_path = $this->normalize_path($page_url);
			if ($page_path) {
				$managed_paths[] = $page_path;
			}
		}

		return array_values(array_unique($managed_paths));
	}

	private function build_portal_login_url($redirect_to = '') {
		$portal_url = $this->get_portal_page_url();
		if (!$portal_url) {
			$portal_url = home_url('/membership/');
		}

		$portal_url = untrailingslashit($portal_url) . '/';
		$validated_redirect = $redirect_to ? wp_validate_redirect($redirect_to, '') : '';
		if ($validated_redirect && $this->is_pmpro_confirmation_url($validated_redirect)) {
			return $portal_url . '#/login?purchase_success=1';
		}

		$target = $portal_url;
		if ($validated_redirect) {
			$target = add_query_arg('redirect_to', $validated_redirect, $target);
		}

		return $target . '#/login';
	}

	private function build_portal_app_url($route = '') {
		$portal_url = untrailingslashit($this->get_portal_page_url());
		$normalized_route = trim((string) $route, '/');

		if ($normalized_route === '') {
			return $portal_url . '/';
		}

		return $portal_url . '/#/' . $normalized_route;
	}

	private function get_current_request_path() {
		if (empty($_SERVER['REQUEST_URI'])) {
			return '';
		}

		return $this->normalize_path(wp_unslash($_SERVER['REQUEST_URI']));
	}

	private function get_current_request_url() {
		if (empty($_SERVER['REQUEST_URI'])) {
			return '';
		}

		return home_url(wp_unslash($_SERVER['REQUEST_URI']));
	}

	private function get_wp_login_base_url() {
		return home_url('/wp-login.php');
	}

	private function normalize_path($url) {
		if (!$url) {
			return '';
		}

		$path = wp_parse_url((string) $url, PHP_URL_PATH);
		if (!is_string($path) || $path === '') {
			return '';
		}

		return untrailingslashit($path);
	}

	private function is_pmpro_change_password_request() {
		$request_path = $this->get_current_request_path();
		$expected_path = $this->normalize_path(home_url('/membership-account/your-profile/'));
		$view = isset($_GET['view']) ? sanitize_text_field(wp_unslash($_GET['view'])) : '';

		return $request_path !== '' && $request_path === $expected_path && $view === 'change-password';
	}

	private function is_pmpro_checkout_request() {
		$request_path = $this->get_current_request_path();
		$checkout_path = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url')
			? $this->normalize_path(pmpro_url('checkout'))
			: $this->normalize_path(home_url('/membership-checkout/'));

		return $request_path !== '' && $checkout_path !== '' && $request_path === $checkout_path;
	}

	private function is_pmpro_cancel_request() {
		$request_path = $this->get_current_request_path();
		$cancel_path = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url')
			? $this->normalize_path(pmpro_url('cancel'))
			: $this->normalize_path(home_url('/membership-account/membership-cancel/'));

		return $request_path !== '' && $cancel_path !== '' && $request_path === $cancel_path;
	}

	private function is_stripe_checkout_request() {
		$checkout_gateway = '';
		if (isset($_REQUEST['gateway'])) {
			$checkout_gateway = sanitize_key(wp_unslash($_REQUEST['gateway']));
		}

		if ($checkout_gateway === '') {
			global $gateway;
			$checkout_gateway = is_string($gateway) ? sanitize_key($gateway) : '';
		}

		if ($checkout_gateway === '' && function_exists('pmpro_getOption')) {
			$checkout_gateway = sanitize_key((string) pmpro_getOption('gateway'));
		}

		return $checkout_gateway === 'stripe';
	}

	private function is_checkout_post_request() {
		return $this->get_request_method() === 'POST' && $this->is_pmpro_checkout_request();
	}

	private function get_request_method() {
		return isset($_SERVER['REQUEST_METHOD']) ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))) : '';
	}

	private function log_checkout_error_once($event_type, $message, $error_code = '', $diagnostic_context = []) {
		$signature = md5($event_type . '|' . $message . '|' . $error_code . '|' . $this->get_current_request_url());
		if ($signature === $this->logged_checkout_error_signature) {
			return false;
		}

		$this->logged_checkout_error_signature = $signature;

		return $this->log_checkout_event([
			'severity' => 'error',
			'area' => 'checkout',
			'event_type' => $event_type,
			'message' => $message,
			'error_code' => $error_code,
			'pmpro_level_id' => $this->get_requested_level_id(),
			'context' => $this->get_checkout_log_context(array_merge([
				'pmpro_message' => $message,
			], is_array($diagnostic_context) ? $diagnostic_context : [])),
		]);
	}

	private function log_checkout_event($args) {
		if (!class_exists('AAC_Member_Portal_Error_Log')) {
			return false;
		}

		$args = is_array($args) ? $args : [];
		$args['route'] = $args['route'] ?? 'membership-checkout';
		$args['request_uri'] = $args['request_uri'] ?? $this->get_current_request_url();

		return AAC_Member_Portal_Error_Log::record($args);
	}

	private function get_checkout_log_context($extra = []) {
		$context = [
			'request_method' => $this->get_request_method(),
			'request_keys' => $this->get_safe_checkout_request_keys(),
			'level_id' => $this->get_requested_level_id(),
			'gateway' => $this->get_checkout_gateway_name(),
			'is_stripe' => $this->is_stripe_checkout_request(),
			'logged_in' => is_user_logged_in(),
			'country' => $this->get_checkout_request_value(['pmpro_scountry', 'scountry', 'bcountry', 'country']),
			'discount_present' => isset($_REQUEST['aac_membership_discount_present']) || isset($_REQUEST['discount_code']) || isset($_REQUEST['pmpro_discount_code']),
			'discount_type' => $this->get_checkout_request_value(['aac_membership_discount']),
			'family_mode' => $this->get_checkout_request_value(['aac_partner_family_mode']),
			'dependent_count' => $this->get_checkout_request_value(['aac_partner_family_dependent_count']),
			'autorenew_requested' => isset($_REQUEST['autorenew']) || isset($_REQUEST['pmpro_autorenewal_checkbox']) || isset($_REQUEST['aac_autorenew']),
		];

		return array_merge($context, is_array($extra) ? $extra : []);
	}

	private function get_safe_checkout_request_keys() {
		if (empty($_REQUEST) || !is_array($_REQUEST)) {
			return [];
		}

		$keys = [];
		foreach (array_keys($_REQUEST) as $key) {
			$key = (string) $key;
			$lower_key = strtolower($key);
			if (preg_match('/(password|pass|cvv|card|accountnumber|token|secret|nonce)/', $lower_key)) {
				continue;
			}

			$keys[] = sanitize_key($key);
		}

		sort($keys);
		return array_values(array_filter(array_unique($keys)));
	}

	private function get_checkout_request_value($keys) {
		foreach ((array) $keys as $key) {
			if (isset($_REQUEST[$key])) {
				return sanitize_text_field(wp_unslash($_REQUEST[$key]));
			}
		}

		return '';
	}

	private function get_checkout_gateway_name() {
		if (isset($_REQUEST['gateway'])) {
			return sanitize_key(wp_unslash($_REQUEST['gateway']));
		}

		global $gateway;
		if (is_string($gateway) && $gateway !== '') {
			return sanitize_key($gateway);
		}

		return function_exists('pmpro_getOption') ? sanitize_key((string) pmpro_getOption('gateway')) : '';
	}

	private function get_pmpro_checkout_message($fallback = '') {
		global $pmpro_msg;
		$message = is_string($pmpro_msg) ? trim(wp_strip_all_tags($pmpro_msg)) : '';
		return $message !== '' ? $message : $fallback;
	}

	private function get_pmpro_order_log_fields($morder, $user_id = 0) {
		$user_id = absint($user_id);
		if (!$user_id && is_object($morder) && isset($morder->user_id)) {
			$user_id = absint($morder->user_id);
		}

		return [
			'user_id' => $user_id,
			'pmpro_order_id' => is_object($morder) && isset($morder->id) ? absint($morder->id) : 0,
			'pmpro_order_code' => is_object($morder) && isset($morder->code) ? sanitize_text_field((string) $morder->code) : '',
			'pmpro_level_id' => is_object($morder) && isset($morder->membership_id) ? absint($morder->membership_id) : $this->get_requested_level_id(),
			'stripe_customer_id' => $this->get_user_stripe_customer_id($user_id),
			'stripe_subscription_id' => is_object($morder) && isset($morder->subscription_transaction_id) ? sanitize_text_field((string) $morder->subscription_transaction_id) : '',
		];
	}

	private function get_user_stripe_customer_id($user_id) {
		$user_id = absint($user_id);
		if (!$user_id) {
			return '';
		}

		foreach (['pmpro_stripe_customerid', 'pmpro_stripe_customer_id', '_pmpro_stripe_customerid', '_pmpro_stripe_customer_id'] as $meta_key) {
			$value = trim((string) get_user_meta($user_id, $meta_key, true));
			if ($value !== '') {
				return sanitize_text_field($value);
			}
		}

		return '';
	}

	private function should_capture_fatal_for_request($request_uri) {
		$request_uri = (string) $request_uri;
		if ($request_uri === '') {
			return false;
		}

		if (strpos($request_uri, '/wp-json/aac/v1/register') !== false) {
			return true;
		}

		$checkout_path = AAC_Member_Portal_PMPro::is_available() && function_exists('pmpro_url')
			? $this->normalize_path(pmpro_url('checkout'))
			: $this->normalize_path(home_url('/membership-checkout/'));
		$request_path = $this->normalize_path($request_uri);

		return $request_path !== '' && $checkout_path !== '' && $request_path === $checkout_path;
	}

	private function is_fatal_error($error) {
		if (!is_array($error) || !isset($error['type'])) {
			return false;
		}

		return in_array((int) $error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true);
	}

	private function should_disable_wp_fusion_pmpro_hooks() {
		if (!class_exists('WPF_PMPro_Hooks')) {
			return false;
		}

		if (!function_exists('wp_fusion')) {
			return true;
		}

		try {
			$fusion = wp_fusion();
		} catch (Throwable $throwable) {
			return true;
		}

		if (!is_object($fusion)) {
			return true;
		}

		$user = isset($fusion->user) ? $fusion->user : null;

		return !is_object($user) || !method_exists($user, 'push_user_meta');
	}

	private function remove_class_callbacks($hook_name, $class_name) {
		if (empty($GLOBALS['wp_filter'][$hook_name])) {
			return;
		}

		$wp_hook = $GLOBALS['wp_filter'][$hook_name];
		$callbacks = is_object($wp_hook) && isset($wp_hook->callbacks) ? $wp_hook->callbacks : [];
		if (!is_array($callbacks)) {
			return;
		}

		foreach ($callbacks as $priority => $group) {
			if (!is_array($group)) {
				continue;
			}

			foreach ($group as $callback_config) {
				$callback = $callback_config['function'] ?? null;
				if (!is_array($callback) || !is_object($callback[0]) || !isset($callback[1])) {
					continue;
				}

				if (get_class($callback[0]) !== $class_name) {
					continue;
				}

				remove_action($hook_name, [$callback[0], $callback[1]], $priority);
			}
		}
	}

	private function is_wp_fusion_pmpro_request_context() {
		if (is_admin()) {
			return false;
		}

		$request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
		if ($request_uri === '') {
			return false;
		}

		if (strpos($request_uri, '/wp-json/aac/v1/register') !== false) {
			return true;
		}

		return $this->is_pmpro_checkout_request();
	}

	private function is_wp_fusion_shim_context() {
		$current_filter = current_filter();
		if (in_array($current_filter, ['profile_update', 'pmpro_after_change_membership_level'], true)) {
			return true;
		}

		return $this->is_wp_fusion_pmpro_request_context();
	}

	/**
	 * Prefer the newest matching file so stale hashed bundles are not chosen when
	 * multiple index-*.js (or .css) files exist after partial uploads.
	 */
	private function first_glob_match($pattern) {
		$matches = glob($pattern);
		if (!$matches) {
			return null;
		}

		usort($matches, static function ($a, $b) {
			$ma = @filemtime($a) ?: 0;
			$mb = @filemtime($b) ?: 0;
			if ($ma === $mb) {
				return strcmp($b, $a);
			}
			return $mb <=> $ma;
		});

		return $matches[0];
	}
}

register_activation_hook(AAC_MEMBER_PORTAL_FILE, ['AAC_Member_Portal_Member_Database', 'activate']);
register_activation_hook(AAC_MEMBER_PORTAL_FILE, ['AAC_Member_Portal_Daily_Member_Export', 'activate']);
register_activation_hook(AAC_MEMBER_PORTAL_FILE, ['AAC_Member_Portal_Import_Manager', 'activate']);
register_activation_hook(AAC_MEMBER_PORTAL_FILE, ['AAC_Member_Portal_Plugin', 'install_brand_discounts_page']);
register_activation_hook(AAC_MEMBER_PORTAL_FILE, ['AAC_Member_Portal_Plugin', 'install_signup_page']);
register_deactivation_hook(AAC_MEMBER_PORTAL_FILE, ['AAC_Member_Portal_Daily_Member_Export', 'deactivate']);
$GLOBALS['aac_member_portal_plugin'] = new AAC_Member_Portal_Plugin();

function aac_member_portal() {
	return isset($GLOBALS['aac_member_portal_plugin']) && $GLOBALS['aac_member_portal_plugin'] instanceof AAC_Member_Portal_Plugin
		? $GLOBALS['aac_member_portal_plugin']
		: null;
}
