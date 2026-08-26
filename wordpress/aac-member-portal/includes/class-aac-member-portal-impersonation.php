<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Member_Portal_Impersonation {
	const COOKIE_NAME = 'aac_member_portal_impersonation';
	const TRANSIENT_PREFIX = 'aac_member_portal_impersonation_';
	const MAX_AGE = 28800;

	private $banner_rendered = false;

	public function __construct() {
		add_action('admin_post_aac_member_portal_impersonate_member', [$this, 'handle_impersonate_member']);
		add_action('admin_post_aac_member_portal_return_to_admin', [$this, 'handle_return_to_admin']);
		add_action('wp_body_open', [$this, 'render_impersonation_banner']);
		add_action('wp_footer', [$this, 'render_impersonation_banner']);
		add_filter('user_row_actions', [$this, 'add_user_row_action'], 10, 2);
	}

	public static function get_switch_url($member_user_id, $return_url = '') {
		$member_user_id = absint($member_user_id);
		if ($member_user_id <= 0) {
			return '';
		}

		$args = [
			'action' => 'aac_member_portal_impersonate_member',
			'member_user_id' => $member_user_id,
		];

		if ($return_url) {
			$args['return_url'] = self::encode_return_url($return_url);
		}

		return wp_nonce_url(
			add_query_arg($args, admin_url('admin-post.php')),
			'aac_member_portal_impersonate_member_' . $member_user_id
		);
	}

	public function add_user_row_action($actions, $user_object) {
		if (!current_user_can('manage_options') || !$user_object instanceof WP_User) {
			return $actions;
		}

		if (user_can($user_object, 'manage_options')) {
			return $actions;
		}

		$switch_url = self::get_switch_url(
			$user_object->ID,
			admin_url('users.php')
		);

		if ($switch_url) {
			$actions['aac_view_as_member'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url($switch_url),
				esc_html__('View as Member', 'aac-member-portal')
			);
		}

		return $actions;
	}

	public function handle_impersonate_member() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to view members this way.', 'aac-member-portal'), '', ['response' => 403]);
		}

		$member_user_id = isset($_REQUEST['member_user_id']) ? absint(wp_unslash($_REQUEST['member_user_id'])) : 0;
		if ($member_user_id <= 0) {
			wp_die(esc_html__('A valid member is required.', 'aac-member-portal'), '', ['response' => 400]);
		}

		check_admin_referer('aac_member_portal_impersonate_member_' . $member_user_id);

		$member_user = get_user_by('id', $member_user_id);
		if (!$member_user instanceof WP_User) {
			wp_die(esc_html__('That member account could not be found.', 'aac-member-portal'), '', ['response' => 404]);
		}

		if (user_can($member_user, 'manage_options')) {
			wp_die(esc_html__('Admin accounts cannot be opened with member view.', 'aac-member-portal'), '', ['response' => 403]);
		}

		$admin_user_id = get_current_user_id();
		$return_url = isset($_REQUEST['return_url'])
			? self::decode_return_url((string) wp_unslash($_REQUEST['return_url']))
			: admin_url('admin.php?page=' . AAC_Member_Portal_Member_Database::PAGE_SLUG . '&member_id=' . $member_user_id);
		if (!$return_url) {
			$return_url = admin_url('admin.php?page=' . AAC_Member_Portal_Member_Database::PAGE_SLUG . '&member_id=' . $member_user_id);
		}

		$token = wp_generate_password(40, false, false);
		set_transient(self::TRANSIENT_PREFIX . $token, [
			'admin_user_id' => $admin_user_id,
			'member_user_id' => $member_user_id,
			'return_url' => $return_url,
			'created_at' => time(),
		], self::MAX_AGE);
		self::set_impersonation_cookie($token);

		wp_clear_auth_cookie();
		wp_set_current_user($member_user_id);
		wp_set_auth_cookie($member_user_id, true, is_ssl());
		do_action('wp_login', $member_user->user_login, $member_user);

		wp_safe_redirect(self::get_member_profile_url());
		exit;
	}

	public function handle_return_to_admin() {
		$current_user_id = get_current_user_id();
		if ($current_user_id <= 0) {
			wp_die(esc_html__('You must be signed in to return to admin.', 'aac-member-portal'), '', ['response' => 401]);
		}

		check_admin_referer('aac_member_portal_return_to_admin_' . $current_user_id);

		$context = self::get_active_context();
		if (!$context) {
			wp_die(esc_html__('The member view session has expired.', 'aac-member-portal'), '', ['response' => 410]);
		}

		$admin_user_id = absint($context['admin_user_id'] ?? 0);
		$member_user_id = absint($context['member_user_id'] ?? 0);
		if ($member_user_id !== $current_user_id && !current_user_can('manage_options')) {
			wp_die(esc_html__('This member view session does not match the current user.', 'aac-member-portal'), '', ['response' => 403]);
		}

		$admin_user = get_user_by('id', $admin_user_id);
		if (!$admin_user instanceof WP_User || !user_can($admin_user, 'manage_options')) {
			wp_die(esc_html__('The original admin account could not be restored.', 'aac-member-portal'), '', ['response' => 403]);
		}

		self::clear_active_context();

		wp_clear_auth_cookie();
		wp_set_current_user($admin_user_id);
		wp_set_auth_cookie($admin_user_id, true, is_ssl());
		do_action('wp_login', $admin_user->user_login, $admin_user);

		$return_url = esc_url_raw((string) ($context['return_url'] ?? ''));
		wp_safe_redirect($return_url ?: admin_url('admin.php?page=' . AAC_Member_Portal_Member_Database::PAGE_SLUG));
		exit;
	}

	public function render_impersonation_banner() {
		if ($this->banner_rendered) {
			return;
		}

		$context = self::get_active_context();
		if (!$context || get_current_user_id() !== absint($context['member_user_id'] ?? 0)) {
			return;
		}

		$member_user = wp_get_current_user();
		if (!$member_user instanceof WP_User || !$member_user->exists()) {
			return;
		}

		$this->banner_rendered = true;
		$return_url = wp_nonce_url(
			admin_url('admin-post.php?action=aac_member_portal_return_to_admin'),
			'aac_member_portal_return_to_admin_' . $member_user->ID
		);
		$member_label = trim($member_user->first_name . ' ' . $member_user->last_name) ?: $member_user->display_name;
		?>
		<style id="aac-member-portal-impersonation-style">
			html.aac-member-portal-impersonating body {
				padding-top: 54px !important;
			}
			.aac-member-portal-impersonation-banner {
				position: fixed;
				top: 0;
				left: 0;
				right: 0;
				z-index: 999999;
				display: flex;
				align-items: center;
				justify-content: space-between;
				gap: 1rem;
				min-height: 54px;
				background: #030000;
				color: #fff;
				padding: 0.65rem 1rem;
				box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
				font-size: 14px;
				line-height: 1.35;
			}
			.aac-member-portal-impersonation-banner strong {
				color: #f8c235;
			}
			.aac-member-portal-impersonation-banner a {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				min-height: 34px;
				background: #f8c235;
				color: #030000 !important;
				padding: 0 0.85rem;
				text-decoration: none !important;
				font-weight: 800;
				letter-spacing: 0.08em;
				text-transform: uppercase;
			}
			@media (max-width: 640px) {
				html.aac-member-portal-impersonating body {
					padding-top: 74px !important;
				}
				.aac-member-portal-impersonation-banner {
					align-items: flex-start;
					flex-direction: column;
					min-height: 74px;
				}
			}
		</style>
		<script>
			document.documentElement.classList.add('aac-member-portal-impersonating');
		</script>
		<div class="aac-member-portal-impersonation-banner" role="status">
			<span>
				<?php echo esc_html__('Admin member view:', 'aac-member-portal'); ?>
				<strong><?php echo esc_html($member_label ?: $member_user->user_email); ?></strong>
			</span>
			<a href="<?php echo esc_url($return_url); ?>"><?php echo esc_html__('Return to Admin', 'aac-member-portal'); ?></a>
		</div>
		<?php
	}

	private static function get_member_profile_url() {
		$portal_url = home_url('/membership/');
		return trailingslashit($portal_url) . '#/profile';
	}

	private static function encode_return_url($return_url) {
		$encoded = base64_encode((string) $return_url);
		return rtrim(strtr($encoded, '+/', '-_'), '=');
	}

	private static function decode_return_url($encoded_url) {
		$encoded_url = sanitize_text_field((string) $encoded_url);
		if ($encoded_url === '') {
			return '';
		}

		$padded = strtr($encoded_url, '-_', '+/');
		$padding = strlen($padded) % 4;
		if ($padding) {
			$padded .= str_repeat('=', 4 - $padding);
		}

		$decoded = base64_decode($padded, true);
		return $decoded ? esc_url_raw($decoded) : '';
	}

	private static function get_active_context() {
		$token = isset($_COOKIE[self::COOKIE_NAME]) ? sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_NAME])) : '';
		if ($token === '') {
			return null;
		}

		$context = get_transient(self::TRANSIENT_PREFIX . $token);
		return is_array($context) ? $context : null;
	}

	private static function clear_active_context() {
		$token = isset($_COOKIE[self::COOKIE_NAME]) ? sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_NAME])) : '';
		if ($token !== '') {
			delete_transient(self::TRANSIENT_PREFIX . $token);
		}
		self::set_impersonation_cookie('', time() - HOUR_IN_SECONDS);
	}

	private static function set_impersonation_cookie($token, $expires = null) {
		$expires = $expires ?? (time() + self::MAX_AGE);
		$args = [
			'expires' => $expires,
			'path' => defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/',
			'secure' => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		];

		if (defined('COOKIE_DOMAIN') && COOKIE_DOMAIN) {
			$args['domain'] = COOKIE_DOMAIN;
		}

		setcookie(self::COOKIE_NAME, (string) $token, $args);
		$_COOKIE[self::COOKIE_NAME] = (string) $token;
	}
}
