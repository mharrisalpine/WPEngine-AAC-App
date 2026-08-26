<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Member_Portal_Error_Log {
	const SCHEMA_OPTION = 'aac_member_portal_error_log_schema_version';
	const SCHEMA_VERSION = '2026-07-15-1';

	public static function init() {
		add_action('admin_init', [__CLASS__, 'maybe_install_schema']);
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'aac_member_portal_error_log';
	}

	public static function maybe_install_schema() {
		if (get_option(self::SCHEMA_OPTION) === self::SCHEMA_VERSION) {
			return;
		}

		self::install_schema();
	}

	public static function install_schema() {
		global $wpdb;

		if (!$wpdb) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		dbDelta("
			CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				created_at datetime NOT NULL,
				severity varchar(24) NOT NULL DEFAULT 'error',
				area varchar(64) NOT NULL DEFAULT '',
				event_type varchar(96) NOT NULL DEFAULT '',
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				member_id varchar(100) NOT NULL DEFAULT '',
				pmpro_order_id bigint(20) unsigned NOT NULL DEFAULT 0,
				pmpro_order_code varchar(100) NOT NULL DEFAULT '',
				pmpro_level_id bigint(20) unsigned NOT NULL DEFAULT 0,
				stripe_customer_id varchar(191) NOT NULL DEFAULT '',
				stripe_subscription_id varchar(191) NOT NULL DEFAULT '',
				request_uri text NULL,
				route varchar(191) NOT NULL DEFAULT '',
				message longtext NULL,
				error_code varchar(100) NOT NULL DEFAULT '',
				context_json longtext NULL,
				PRIMARY KEY  (id),
				KEY created_at (created_at),
				KEY area_event (area, event_type),
				KEY user_lookup (user_id),
				KEY order_lookup (pmpro_order_id)
			) {$charset_collate};
		");

		update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION);
	}

	public static function record($args) {
		global $wpdb;

		if (!$wpdb) {
			return false;
		}

		self::maybe_install_schema();

		$args = is_array($args) ? $args : [];
		$context = isset($args['context']) && is_array($args['context']) ? self::sanitize_context($args['context']) : [];

		$row = [
			'created_at' => current_time('mysql'),
			'severity' => self::sanitize_choice($args['severity'] ?? 'error', ['debug', 'info', 'warning', 'error', 'critical'], 'error'),
			'area' => sanitize_key((string) ($args['area'] ?? 'member_portal')),
			'event_type' => sanitize_key((string) ($args['event_type'] ?? 'unknown')),
			'user_id' => absint($args['user_id'] ?? get_current_user_id()),
			'member_id' => sanitize_text_field((string) ($args['member_id'] ?? '')),
			'pmpro_order_id' => absint($args['pmpro_order_id'] ?? 0),
			'pmpro_order_code' => sanitize_text_field((string) ($args['pmpro_order_code'] ?? '')),
			'pmpro_level_id' => absint($args['pmpro_level_id'] ?? 0),
			'stripe_customer_id' => sanitize_text_field((string) ($args['stripe_customer_id'] ?? '')),
			'stripe_subscription_id' => sanitize_text_field((string) ($args['stripe_subscription_id'] ?? '')),
			'request_uri' => self::get_request_uri($args['request_uri'] ?? null),
			'route' => sanitize_text_field((string) ($args['route'] ?? '')),
			'message' => sanitize_textarea_field((string) ($args['message'] ?? '')),
			'error_code' => sanitize_text_field((string) ($args['error_code'] ?? '')),
			'context_json' => wp_json_encode($context),
		];

		return (bool) $wpdb->insert(
			self::table_name(),
			$row,
			['%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
		);
	}

	public static function list_rows($limit = 250) {
		global $wpdb;

		if (!$wpdb) {
			return [];
		}

		self::maybe_install_schema();

		$limit = max(1, min(5000, absint($limit)));
		return (array) $wpdb->get_results("SELECT * FROM " . self::table_name() . " ORDER BY created_at DESC, id DESC LIMIT {$limit}", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal table and bounded integer limit.
	}

	public static function get_stats() {
		global $wpdb;

		if (!$wpdb) {
			return [
				'total' => 0,
				'last_24_hours' => 0,
				'critical' => 0,
			];
		}

		self::maybe_install_schema();

		$table = self::table_name();
		$since = date('Y-m-d H:i:s', current_time('timestamp') - DAY_IN_SECONDS);

		return [
			'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}"), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			'last_24_hours' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", $since)), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			'critical' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE severity = 'critical'"), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		];
	}

	public static function clear_rows($days = 0) {
		global $wpdb;

		if (!$wpdb) {
			return 0;
		}

		self::maybe_install_schema();

		$days = absint($days);
		if ($days > 0) {
			$cutoff = date('Y-m-d H:i:s', current_time('timestamp') - ($days * DAY_IN_SECONDS));
			$deleted = $wpdb->query($wpdb->prepare("DELETE FROM " . self::table_name() . " WHERE created_at < %s", $cutoff)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$deleted = $wpdb->query("TRUNCATE TABLE " . self::table_name()); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal table only.
		}

		return max(0, (int) $deleted);
	}

	public static function output_csv($rows, $filename = '') {
		$filename = $filename ?: 'aac-member-portal-error-log-' . gmdate('Ymd-His') . '.csv';

		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');

		$output = fopen('php://output', 'w');
		if ($output === false) {
			wp_die('Unable to open the AAC Member App error export stream.');
		}

		self::write_csv($output, $rows);
		fclose($output);
		exit;
	}

	private static function write_csv($output, $rows) {
		fputcsv($output, [
			'id',
			'created_at',
			'severity',
			'area',
			'event_type',
			'user_id',
			'member_id',
			'pmpro_order_id',
			'pmpro_order_code',
			'pmpro_level_id',
			'stripe_customer_id',
			'stripe_subscription_id',
			'request_uri',
			'route',
			'message',
			'error_code',
			'context_json',
		]);

		foreach ((array) $rows as $row) {
			fputcsv($output, [
				$row['id'] ?? '',
				$row['created_at'] ?? '',
				$row['severity'] ?? '',
				$row['area'] ?? '',
				$row['event_type'] ?? '',
				$row['user_id'] ?? '',
				$row['member_id'] ?? '',
				$row['pmpro_order_id'] ?? '',
				$row['pmpro_order_code'] ?? '',
				$row['pmpro_level_id'] ?? '',
				$row['stripe_customer_id'] ?? '',
				$row['stripe_subscription_id'] ?? '',
				$row['request_uri'] ?? '',
				$row['route'] ?? '',
				$row['message'] ?? '',
				$row['error_code'] ?? '',
				$row['context_json'] ?? '',
			]);
		}
	}

	private static function sanitize_context($context) {
		$clean = [];

		foreach ((array) $context as $key => $value) {
			$key = sanitize_key((string) $key);
			if ($key === '') {
				continue;
			}

			if (self::is_sensitive_key($key)) {
				$clean[$key] = '[redacted]';
				continue;
			}

			if (is_array($value)) {
				$clean[$key] = self::sanitize_context($value);
				continue;
			}

			if (is_bool($value)) {
				$clean[$key] = $value;
				continue;
			}

			if (is_int($value) || is_float($value)) {
				$clean[$key] = $value;
				continue;
			}

			$clean[$key] = sanitize_text_field((string) $value);
		}

		return $clean;
	}

	private static function is_sensitive_key($key) {
		foreach (['password', 'pass', 'cvv', 'card', 'accountnumber', 'address', 'street', 'birth', 'phone', 'email', 'token', 'secret'] as $needle) {
			if (strpos($key, $needle) !== false) {
				return true;
			}
		}

		return false;
	}

	private static function sanitize_choice($value, $allowed, $fallback) {
		$value = sanitize_key((string) $value);
		return in_array($value, $allowed, true) ? $value : $fallback;
	}

	private static function get_request_uri($override = null) {
		if ($override !== null) {
			return esc_url_raw((string) $override);
		}

		if (empty($_SERVER['REQUEST_URI'])) {
			return '';
		}

		return esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']));
	}
}
