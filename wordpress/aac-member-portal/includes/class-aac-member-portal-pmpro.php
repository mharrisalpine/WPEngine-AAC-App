<?php
/**
 * Paid Memberships Pro integration for tier, renewal, benefits, and membership URLs.
 *
 * Name PMPro levels to match benefit tiers where possible:
 * Supporter, Partner, Leader, Advocate.
 *
 * @package AAC_Member_Portal
 */

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Member_Portal_PMPro {

	public static function is_available() {
		return function_exists('pmpro_getMembershipLevelForUser') && function_exists('pmpro_url');
	}

	/**
	 * Primary active membership for portal tier display.
	 *
	 * @return array{level_id:int,tier:string,renewal_date:string,expiration_date:string,valid_through_date:string,joined_date:string,status:string}|null
	 */
	public static function get_primary_membership($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !self::is_available()) {
			return null;
		}

		$membership = pmpro_getMembershipLevelForUser($user_id);
		if (!$membership || empty($membership->id)) {
			$membership = self::get_latest_membership_snapshot($user_id);
			if (!$membership || empty($membership->id)) {
				return null;
			}
		}

		$level_id = (int) $membership->id;
		$renewal_date = '';
		$subscription_id = self::find_subscription_id($user_id, $level_id, ['active', 'trialing']);
		if ($subscription_id) {
			$renewal_date = self::get_subscription_next_payment_date($subscription_id);
		}

		$status = self::normalize_membership_status($membership->status ?? '');
		if ($status === '') {
			$status = 'active';
		}
		if ($subscription_id) {
			$status = 'active';
		}

		$expiration_date = self::get_membership_end_date($user_id, $level_id, ['active']);
		if ($expiration_date === '') {
			$expiration_date = self::normalize_subscription_date_value($membership->enddate ?? '');
		}
		if ($expiration_date === '') {
			$expiration_date = self::get_membership_end_date($user_id, $level_id);
		}
		if ($expiration_date === '' && $renewal_date !== '') {
			$expiration_date = $renewal_date;
		}
		if ($expiration_date === '') {
			$expiration_date = self::get_projected_membership_end_date($user_id, $level_id, $membership);
		}

		// AAC memberships remain valid through the final day of their ending month.
		// Normalize both values here so every API response and profile surface uses
		// the same month-end date, including subscription-backed renewals.
		$renewal_date = self::normalize_date_to_month_end($renewal_date);
		$expiration_date = self::normalize_date_to_month_end($expiration_date);

		$joined_date = self::get_first_membership_start_date($user_id);
		$valid_through_date = self::get_later_membership_date($expiration_date, $renewal_date);
		if ($status !== 'active' && (self::is_future_membership_date($expiration_date) || self::is_future_membership_date($renewal_date))) {
			$status = 'active';
		}

		return [
			'level_id' => $level_id,
			'tier' => !empty($membership->name) ? (string) $membership->name : 'Supporter',
			'renewal_date' => $renewal_date,
			'expiration_date' => $expiration_date,
			'valid_through_date' => $valid_through_date,
			'joined_date' => $joined_date,
			'status' => $status,
		];
	}

	/**
	 * Membership URLs for the React app based on PMPro pages and checkout levels.
	 *
	 * @param int   $user_id
	 * @param array $profile_info
	 * @return array
	 */
	public static function build_membership_actions($user_id, $profile_info) {
		$empty = [
			'account_url' => '',
			'billing_url' => '',
			'cancel_url' => '',
			'current_level_id' => null,
			'current_subscription_id' => null,
			'current_level_checkout_url' => '',
			'add_dependent_checkout_url' => '',
			'pending_downgrade' => null,
			'levels' => new stdClass(),
		];

		if (!self::is_available()) {
			return $empty;
		}

		$primary = self::get_primary_membership($user_id);
		$current_level_id = $primary ? (int) $primary['level_id'] : null;
		$current_rank = $current_level_id ? self::get_tier_rank_for_level_id($current_level_id) : 0;
		$current_subscription_id = self::find_subscription_id($user_id, (int) $current_level_id, ['active', 'trialing']);
		$can_schedule_downgrade = self::can_schedule_membership_downgrade($user_id, $primary);
		$pending_downgrade = self::get_pending_membership_downgrade($user_id);
		if (self::is_pending_membership_downgrade_stale($pending_downgrade, $current_level_id, $current_rank)) {
			self::clear_pending_membership_downgrade($user_id, 'current_membership_supersedes_pending_downgrade');
			$pending_downgrade = null;
		}

		$levels = [];
		foreach (self::get_all_levels() as $level) {
			$level_id = isset($level->id) ? (int) $level->id : 0;
			$name = isset($level->name) ? (string) $level->name : '';
			if ($level_id <= 0 || $name === '') {
				continue;
			}

			$target_rank = self::get_tier_rank_from_name($name);
			$change_type = 'join';
			if ($current_rank > 0 && $target_rank > 0) {
				if ($target_rank > $current_rank) {
					$change_type = 'upgrade';
				} elseif ($target_rank < $current_rank) {
					$change_type = $can_schedule_downgrade ? 'downgrade_at_renewal' : 'downgrade_unavailable';
				} elseif ($level_id === $current_level_id) {
					$change_type = 'current';
				}
			}

			$levels[$name] = [
				'checkout_url' => $change_type === 'downgrade_unavailable' ? '' : self::pmpro_page_url('checkout', ['level' => $level_id]),
				'level_id' => $level_id,
				'action_type' => $change_type,
				'effective_timing' => $change_type === 'downgrade_at_renewal' ? 'renewal' : 'immediate',
			];
		}

		$current_level_checkout_url = $current_level_id ? self::pmpro_page_url('checkout', ['level' => $current_level_id]) : '';
		$add_dependent_checkout_url = $current_level_checkout_url !== ''
			? add_query_arg('aac_add_dependent', '1', $current_level_checkout_url)
			: '';
		if (
			$current_level_checkout_url !== ''
			&& !$current_subscription_id
			&& self::has_future_membership_term($primary)
		) {
			// This is the "turn auto-renew back on without charging twice" lane.
			$current_level_checkout_url = add_query_arg('aac_reactivate_autorenew', '1', $current_level_checkout_url);
		}

		$billing_url = self::pmpro_page_url('billing');
		if ($current_subscription_id) {
			$billing_order_reference = self::find_latest_billing_order_reference($user_id, (int) $current_level_id);
			$billing_url = self::pmpro_page_url(
				'billing',
				$billing_order_reference !== '' ? ['order' => $billing_order_reference] : []
			);
		}
		$cancel_url = '';
		if ($current_level_id && $current_subscription_id) {
			$cancel_url = self::pmpro_page_url('cancel', ['levelstocancel' => $current_level_id]);
			$cancel_path = untrailingslashit((string) wp_parse_url($cancel_url, PHP_URL_PATH));
			if ($cancel_path === untrailingslashit('/membership-levels')) {
				$cancel_url = home_url('/membership-account/membership-cancel/');
				$cancel_url = add_query_arg('levelstocancel', $current_level_id, $cancel_url);
			}
		}

		return [
			'account_url' => self::portal_manage_membership_url(),
			'billing_url' => $billing_url,
			'cancel_url' => $cancel_url,
			'current_level_id' => $current_level_id,
			'current_subscription_id' => $current_subscription_id,
			'current_level_checkout_url' => $current_level_checkout_url,
			'add_dependent_checkout_url' => $add_dependent_checkout_url,
			'pending_downgrade' => $pending_downgrade,
			'levels' => (object) $levels,
		];
	}

	private static function is_pending_membership_downgrade_stale($pending_downgrade, $current_level_id, $current_rank) {
		if (!is_array($pending_downgrade) || !$current_level_id || $current_rank <= 0) {
			return false;
		}

		$pending_level_id = isset($pending_downgrade['target_level_id']) ? (int) $pending_downgrade['target_level_id'] : 0;
		if ($pending_level_id > 0 && $pending_level_id === (int) $current_level_id) {
			return true;
		}

		$pending_rank = $pending_level_id > 0
			? self::get_tier_rank_for_level_id($pending_level_id)
			: self::get_tier_rank_from_name($pending_downgrade['target_tier'] ?? '');

		return $pending_rank > 0 && $current_rank >= $pending_rank;
	}

	public static function normalize_tier_name($name) {
		$name = trim((string) $name);
		if ($name === '') {
			return '';
		}

		$normalized = preg_replace('/\s+membership$/i', '', $name);
		$normalized = is_string($normalized) ? trim($normalized) : $name;

		if (strcasecmp($normalized, 'Partner Family') === 0) {
			return 'Partner';
		}

		foreach (['Supporter', 'Partner', 'Leader', 'Advocate', 'GRF', 'Lifetime', 'Free'] as $tier) {
			if (strcasecmp($normalized, $tier) === 0) {
				return $tier;
			}
		}

		return $normalized;
	}

	public static function get_tier_rank_from_name($name) {
		$normalized = self::normalize_tier_name($name);
		$ranks = [
			'Free' => 0,
			'Supporter' => 1,
			'Partner' => 2,
			'Leader' => 3,
			'Advocate' => 4,
			'GRF' => 5,
			'Lifetime' => 5,
		];

		return array_key_exists($normalized, $ranks) ? (int) $ranks[$normalized] : 0;
	}

	public static function get_tier_rank_for_level_id($level_id) {
		$level_id = (int) $level_id;
		if ($level_id <= 0) {
			return 0;
		}

		$level = function_exists('pmpro_getLevel') ? pmpro_getLevel($level_id) : null;
		if (is_object($level) && !empty($level->name)) {
			return self::get_tier_rank_from_name($level->name);
		}

		return 0;
	}

	public static function find_level_by_tier($tier) {
		$normalized_tier = self::normalize_tier_name($tier);
		if ($normalized_tier === '') {
			return null;
		}

		foreach (self::get_all_levels() as $level) {
			if (!is_object($level) || empty($level->id) || empty($level->name)) {
				continue;
			}

			if (self::normalize_tier_name($level->name) === $normalized_tier) {
				return $level;
			}
		}

		return null;
	}

	public static function get_pending_membership_downgrade($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return null;
		}

		$pending = get_user_meta($user_id, 'aac_pending_membership_downgrade', true);
		if (!is_array($pending) || empty($pending['target_tier']) || empty($pending['effective_date'])) {
			return null;
		}

		return [
			'target_level_id' => isset($pending['target_level_id']) ? (int) $pending['target_level_id'] : 0,
			'target_tier' => sanitize_text_field((string) $pending['target_tier']),
			'effective_date' => sanitize_text_field((string) $pending['effective_date']),
			'requested_at' => sanitize_text_field((string) ($pending['requested_at'] ?? '')),
			'status' => sanitize_text_field((string) ($pending['status'] ?? 'scheduled')),
		];
	}

	public static function clear_pending_membership_downgrade($user_id, $reason = '') {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return false;
		}

		$pending = get_user_meta($user_id, 'aac_pending_membership_downgrade', true);
		if (!is_array($pending) || empty($pending)) {
			return false;
		}

		delete_user_meta($user_id, 'aac_pending_membership_downgrade');
		do_action('aac_member_portal_pending_membership_downgrade_cleared', $user_id, $pending, sanitize_key((string) $reason));

		return true;
	}

	/**
	 * Get a member-friendly payment summary for the card currently on file in PMPro.
	 *
	 * PMPro stores card details on membership orders. We prefer the newest order with
	 * a masked account number, and gracefully fall back to a non-card payment type.
	 *
	 * @param int $user_id
	 * @return string
	 */
	public static function get_payment_method_summary($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !self::is_available()) {
			return '';
		}

		$order = self::get_latest_payment_order($user_id);
		if (empty($order) || !is_object($order)) {
			return '';
		}

		$card_type = self::normalize_card_label($order->card_type ?? '');
		$last4 = self::normalize_last4($order->accountnumber ?? '');
		$expiration_month = self::normalize_expiration_month($order->expirationmonth ?? '');
		$expiration_year = self::normalize_expiration_year($order->expirationyear ?? '');
		$payment_type = sanitize_text_field((string) ($order->payment_type ?? ''));

		if ($last4 !== '') {
			$summary = trim(($card_type !== '' ? $card_type : 'Card') . ' ending in ' . $last4);
			$expiration = trim($expiration_month . ($expiration_year !== '' ? '/' . $expiration_year : ''), '/');

			if ($expiration !== '') {
				$summary .= ' exp ' . $expiration;
			}

			return $summary;
		}

		return $payment_type;
	}

	/**
	 * Whether the member currently has an active recurring PMPro subscription.
	 *
	 * @param int      $user_id
	 * @param int|null $level_id
	 * @return bool
	 */
	public static function has_active_auto_renewal($user_id, $level_id = null) {
		$user_id = (int) $user_id;
		$level_id = $level_id !== null ? (int) $level_id : 0;

		if ($user_id <= 0 || !self::is_available()) {
			return false;
		}

		return (bool) self::find_subscription_id($user_id, $level_id, ['active', 'trialing']);
	}

	public static function can_schedule_membership_downgrade($user_id, $membership = null) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !self::is_available()) {
			return false;
		}

		if (!is_array($membership)) {
			$membership = self::get_primary_membership($user_id);
		}

		if (!is_array($membership) || empty($membership['level_id'])) {
			return false;
		}

		if (!self::has_active_auto_renewal($user_id, (int) $membership['level_id'])) {
			return false;
		}

		return self::is_membership_renewal_within_days($membership, 30);
	}

	/**
	 * Membership purchase transactions for the account register.
	 *
	 * @param int $user_id
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_membership_transactions($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !self::is_available()) {
			return [];
		}

		global $wpdb;
		if (!$wpdb || empty($wpdb->pmpro_membership_orders)) {
			return [];
		}

		$table = $wpdb->pmpro_membership_orders;
		$statuses = ['success', 'pending', 'review', 'refunded'];
		$status_placeholders = implode(', ', array_fill(0, count($statuses), '%s'));
		$query = $wpdb->prepare(
			"SELECT id, code, membership_id, total, status, gateway, payment_transaction_id, subscription_transaction_id, timestamp
			FROM {$table}
			WHERE user_id = %d
				AND membership_id > 0
				AND status IN ({$status_placeholders})
			ORDER BY timestamp DESC, id DESC
			LIMIT 50",
			array_merge([$user_id], $statuses)
		);

		if (!is_string($query) || $query === '') {
			return [];
		}

		$rows = $wpdb->get_results($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		if (!is_array($rows) || !$rows) {
			return [];
		}

		$levels_by_id = self::get_level_names_by_id();
		$transactions = [];

		foreach ($rows as $row) {
			if (!is_object($row)) {
				continue;
			}

			$membership_id = isset($row->membership_id) ? (int) $row->membership_id : 0;
			$level_name = $levels_by_id[$membership_id] ?? ($membership_id > 0 ? sprintf('Level %d', $membership_id) : 'Membership');
			$reference_id = sanitize_text_field((string) ($row->payment_transaction_id ?: $row->subscription_transaction_id ?: $row->code ?: $row->id));

			$transactions[] = [
				'id' => 'pmpro_order_' . intval($row->id),
				'kind' => 'Membership',
				'amount' => isset($row->total) ? (float) $row->total : 0,
				'description' => sprintf('%s membership', $level_name),
				'lineItems' => self::get_membership_transaction_line_items($row, $level_name),
				'referenceId' => $reference_id,
				'status' => self::normalize_transaction_status($row->status ?? ''),
				'createdAt' => self::normalize_transaction_timestamp($row->timestamp ?? ''),
				'metadata' => [
					'pmpro_order_id' => intval($row->id),
					'gateway' => sanitize_text_field((string) ($row->gateway ?? '')),
					'membership_id' => $membership_id,
				],
			];
		}

		return $transactions;
	}

	private static function get_membership_transaction_line_items($order, $level_name) {
		$order_id = is_object($order) && isset($order->id) ? absint($order->id) : 0;
		$order_code = is_object($order) && isset($order->code) ? sanitize_key((string) $order->code) : '';
		$storage_keys = [];

		if ($order_id > 0) {
			$storage_keys[] = 'aac_pmpro_order_breakdown_id_' . $order_id;
		}
		if ($order_code !== '') {
			$storage_keys[] = 'aac_pmpro_order_breakdown_code_' . $order_code;
		}

		foreach ($storage_keys as $storage_key) {
			$breakdown = get_option($storage_key, null);
			if (!is_array($breakdown) || empty($breakdown['items']) || !is_array($breakdown['items'])) {
				continue;
			}

			$items = [];
			foreach ($breakdown['items'] as $item) {
				if (!is_array($item)) {
					continue;
				}

				$label = sanitize_text_field((string) ($item['label'] ?? ''));
				if ($label === '') {
					continue;
				}

				$items[] = [
					'label' => $label,
					'amount' => round((float) ($item['amount'] ?? 0), 2),
				];
			}

			if ($items) {
				return $items;
			}
		}

		$total = is_object($order) && isset($order->total) ? round((float) $order->total, 2) : 0.0;
		return [[
			'label' => sprintf('%s membership', sanitize_text_field((string) $level_name)),
			'amount' => $total,
		]];
	}

	private static function get_latest_payment_order($user_id) {
		global $wpdb;

		if (!$wpdb || empty($wpdb->pmpro_membership_orders)) {
			return null;
		}

		$table = $wpdb->pmpro_membership_orders;
		$statuses = ['success', 'token', 'review', 'pending'];
		$status_placeholders = implode(', ', array_fill(0, count($statuses), '%s'));

		$query = $wpdb->prepare(
			"SELECT card_type, accountnumber, expirationmonth, expirationyear, payment_type
			FROM {$table}
			WHERE user_id = %d
				AND status IN ({$status_placeholders})
				AND (
					(accountnumber IS NOT NULL AND accountnumber <> '')
					OR (payment_type IS NOT NULL AND payment_type <> '')
				)
			ORDER BY id DESC
			LIMIT 1",
			array_merge([$user_id], $statuses)
		);

		if (!is_string($query) || $query === '') {
			return null;
		}

		$order = $wpdb->get_row($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		return is_object($order) ? $order : null;
	}

	private static function get_first_membership_start_date($user_id) {
		global $wpdb;

		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return '';
		}

		$order_date = self::get_membership_order_date($user_id, 0, 'ASC');
		if ($order_date !== '') {
			return $order_date;
		}

		if ($wpdb && !empty($wpdb->pmpro_memberships_users)) {
			$table = $wpdb->pmpro_memberships_users;
			$available_columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no user input.
			$available_columns = is_array($available_columns) ? array_map('strval', $available_columns) : [];

			if (in_array('startdate', $available_columns, true)) {
				$query = $wpdb->prepare(
					"SELECT startdate
					FROM {$table}
					WHERE user_id = %d
						AND startdate IS NOT NULL
						AND startdate <> ''
						AND startdate <> '0000-00-00 00:00:00'
						AND startdate <> '0000-00-00'
					ORDER BY startdate ASC, id ASC
					LIMIT 1",
					$user_id
				);

				if (is_string($query) && $query !== '') {
					$startdate = $wpdb->get_var($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
					$startdate = sanitize_text_field((string) $startdate);
					$timestamp = $startdate !== '' ? strtotime($startdate) : false;
					if ($timestamp !== false) {
						return gmdate('Y-m-d', $timestamp);
					}
				}
			}
		}

		$user = get_userdata($user_id);
		if ($user instanceof WP_User) {
			return self::normalize_subscription_date_value($user->user_registered ?? '');
		}

		return '';
	}

	private static function get_latest_membership_snapshot($user_id) {
		global $wpdb;

		$user_id = (int) $user_id;
		if ($user_id <= 0 || !$wpdb || empty($wpdb->pmpro_memberships_users)) {
			return null;
		}

		$table = $wpdb->pmpro_memberships_users;
		$available_columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no user input.
		$available_columns = is_array($available_columns) ? array_map('strval', $available_columns) : [];
		if (!in_array('user_id', $available_columns, true) || !in_array('membership_id', $available_columns, true)) {
			return null;
		}

		$order_column = in_array('enddate', $available_columns, true) ? 'enddate' : 'id';
		$query = $wpdb->prepare(
			"SELECT *
			FROM {$table}
			WHERE user_id = %d
				AND membership_id > 0
			ORDER BY
				CASE
					WHEN {$order_column} IS NULL OR {$order_column} = '' OR {$order_column} = '0000-00-00 00:00:00' OR {$order_column} = '0000-00-00' THEN 0
					ELSE 1
				END DESC,
				{$order_column} DESC,
				id DESC
			LIMIT 1",
			$user_id
		);

		if (!is_string($query) || $query === '') {
			return null;
		}

		$row = $wpdb->get_row($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		if (!is_object($row) || empty($row->membership_id)) {
			return null;
		}

		$level_id = (int) $row->membership_id;
		$level = function_exists('pmpro_getLevel') ? pmpro_getLevel($level_id) : null;
		$snapshot = is_object($level) ? clone $level : new stdClass();
		$snapshot->id = $level_id;
		$snapshot->name = !empty($snapshot->name) ? (string) $snapshot->name : self::get_level_name_from_id($level_id);
		$snapshot->status = isset($row->status) ? (string) $row->status : '';
		$snapshot->startdate = isset($row->startdate) ? (string) $row->startdate : '';
		$snapshot->enddate = isset($row->enddate) ? (string) $row->enddate : '';

		return $snapshot;
	}

	private static function get_level_name_from_id($level_id) {
		global $wpdb;

		$level_id = (int) $level_id;
		if ($level_id <= 0 || !$wpdb || empty($wpdb->pmpro_membership_levels)) {
			return '';
		}

		$table = $wpdb->pmpro_membership_levels;
		$name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$table} WHERE id = %d LIMIT 1", $level_id)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		return sanitize_text_field((string) $name);
	}

	private static function get_membership_order_date($user_id, $level_id = 0, $direction = 'ASC') {
		global $wpdb;

		$user_id = (int) $user_id;
		$level_id = (int) $level_id;
		$direction = strtoupper((string) $direction) === 'DESC' ? 'DESC' : 'ASC';
		if ($user_id <= 0 || !$wpdb || empty($wpdb->pmpro_membership_orders)) {
			return '';
		}

		$table = $wpdb->pmpro_membership_orders;
		$available_columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no user input.
		$available_columns = is_array($available_columns) ? array_map('strval', $available_columns) : [];
		if (!in_array('user_id', $available_columns, true) || !in_array('timestamp', $available_columns, true)) {
			return '';
		}

		$where = [
			'user_id = %d',
			"`timestamp` IS NOT NULL",
			"`timestamp` <> ''",
			"`timestamp` <> '0000-00-00 00:00:00'",
			"`timestamp` <> '0000-00-00'",
		];
		$params = [$user_id];

		if ($level_id > 0 && in_array('membership_id', $available_columns, true)) {
			$where[] = 'membership_id = %d';
			$params[] = $level_id;
		}

		if (in_array('status', $available_columns, true)) {
			$statuses = ['success', 'token'];
			$where[] = 'status IN (' . implode(', ', array_fill(0, count($statuses), '%s')) . ')';
			$params = array_merge($params, $statuses);
		}

		$query = $wpdb->prepare(
			"SELECT `timestamp`
			FROM {$table}
			WHERE " . implode(' AND ', $where) . "
			ORDER BY `timestamp` {$direction}, id {$direction}
			LIMIT 1",
			$params
		);

		if (!is_string($query) || $query === '') {
			return '';
		}

		return self::normalize_subscription_date_value($wpdb->get_var($query)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
	}

	private static function get_membership_end_date($user_id, $level_id = 0, $statuses = []) {
		global $wpdb;

		$user_id = (int) $user_id;
		$level_id = (int) $level_id;
		$statuses = array_values(array_filter(array_map([self::class, 'normalize_membership_status'], (array) $statuses)));
		if ($user_id <= 0 || !$wpdb || empty($wpdb->pmpro_memberships_users)) {
			return '';
		}

		$table = $wpdb->pmpro_memberships_users;
		$available_columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no user input.
		$available_columns = is_array($available_columns) ? array_map('strval', $available_columns) : [];

		if (!in_array('enddate', $available_columns, true)) {
			return '';
		}

		$where = [
			'user_id = %d',
			'enddate IS NOT NULL',
			"enddate <> ''",
			"enddate <> '0000-00-00 00:00:00'",
			"enddate <> '0000-00-00'",
		];
		$params = [$user_id];

		if ($level_id > 0 && in_array('membership_id', $available_columns, true)) {
			$where[] = 'membership_id = %d';
			$params[] = $level_id;
		}
		if ($statuses && in_array('status', $available_columns, true)) {
			$status_placeholders = implode(', ', array_fill(0, count($statuses), '%s'));
			$where[] = "LOWER(status) IN ({$status_placeholders})";
			$params = array_merge($params, $statuses);
		}

		$query = $wpdb->prepare(
			"SELECT enddate
			FROM {$table}
			WHERE " . implode(' AND ', $where) . '
			ORDER BY enddate DESC, id DESC
			LIMIT 1',
			$params
		);

		if (!is_string($query) || $query === '') {
			return '';
		}

		$enddate = $wpdb->get_var($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		return self::normalize_subscription_date_value($enddate);
	}

	private static function get_projected_membership_end_date($user_id, $level_id, $membership) {
		$user_id = (int) $user_id;
		$level_id = (int) $level_id;
		if ($user_id <= 0) {
			return '';
		}

		$base_date = self::get_membership_order_date($user_id, $level_id, 'DESC');
		if ($base_date === '') {
			$base_date = self::normalize_subscription_date_value($membership->startdate ?? '');
		}
		if ($base_date === '') {
			$base_date = self::get_first_membership_start_date($user_id);
		}
		if ($base_date === '') {
			return '';
		}

		$number = isset($membership->cycle_number) ? absint($membership->cycle_number) : 0;
		$period = isset($membership->cycle_period) ? sanitize_text_field((string) $membership->cycle_period) : '';
		if ($number <= 0 || $period === '') {
			$number = isset($membership->expiration_number) ? absint($membership->expiration_number) : 0;
			$period = isset($membership->expiration_period) ? sanitize_text_field((string) $membership->expiration_period) : '';
		}
		if ($number <= 0 || $period === '') {
			$number = 1;
			$period = 'Year';
		}

		$projected_date = self::add_membership_period_to_date($base_date, $number, $period);
		if ($projected_date === '') {
			return '';
		}

		return self::normalize_date_to_month_end($projected_date);
	}

	private static function add_membership_period_to_date($date, $number, $period) {
		$date = self::normalize_subscription_date_value($date);
		$number = absint($number);
		$period = strtolower(sanitize_text_field((string) $period));
		if ($date === '' || $number <= 0) {
			return '';
		}

		$period_map = [
			'day' => 'day',
			'days' => 'day',
			'week' => 'week',
			'weeks' => 'week',
			'month' => 'month',
			'months' => 'month',
			'year' => 'year',
			'years' => 'year',
		];
		$interval_period = $period_map[$period] ?? '';
		if ($interval_period === '') {
			return '';
		}

		$timestamp = strtotime(sprintf('%s +%d %s', $date, $number, $interval_period));
		if ($timestamp === false) {
			return '';
		}

		return gmdate('Y-m-d', $timestamp);
	}

	private static function get_subscription_next_payment_date($subscription_id) {
		global $wpdb;

		$subscription_id = (int) $subscription_id;
		if ($subscription_id <= 0 || !$wpdb || empty($wpdb->pmpro_subscriptions)) {
			return '';
		}

		$table = $wpdb->pmpro_subscriptions;
		$date_columns = self::get_subscription_date_columns();
		if (!$date_columns) {
			return '';
		}

		$selected_columns = implode(', ', array_map(function ($column) {
			return '`' . esc_sql($column) . '`';
		}, $date_columns));

		$query = $wpdb->prepare(
			"SELECT {$selected_columns}
			FROM {$table}
			WHERE id = %d
			LIMIT 1",
			$subscription_id
		);

		if (!is_string($query) || $query === '') {
			return '';
		}

		$row = $wpdb->get_row($query, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		if (!is_array($row) || !$row) {
			return '';
		}

		foreach ($date_columns as $column) {
			$normalized = self::normalize_subscription_date_value($row[$column] ?? '');
			if ($normalized !== '') {
				return $normalized;
			}
		}

		return '';
	}

	private static function find_subscription_id($user_id, $level_id = 0, $statuses = []) {
		global $wpdb;

		if (!$wpdb || empty($wpdb->pmpro_subscriptions)) {
			return null;
		}

		$user_id = (int) $user_id;
		$level_id = (int) $level_id;
		$statuses = array_values(array_filter(array_map('sanitize_text_field', (array) $statuses)));

		$table = $wpdb->pmpro_subscriptions;
		$where = ['user_id = %d'];
		$params = [$user_id];

		if ($level_id > 0) {
			$where[] = 'membership_level_id = %d';
			$params[] = $level_id;
		}

		if ($statuses) {
			$status_placeholders = implode(', ', array_fill(0, count($statuses), '%s'));
			$where[] = "status IN ({$status_placeholders})";
			$params = array_merge($params, $statuses);
		}

		$query = $wpdb->prepare(
			"SELECT id
			FROM {$table}
			WHERE " . implode(' AND ', $where) . "
			ORDER BY id DESC
			LIMIT 1",
			$params
		);

		if (!is_string($query) || $query === '') {
			return null;
		}

		$subscription_id = $wpdb->get_var($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		$subscription_id = absint($subscription_id);

		return $subscription_id > 0 ? $subscription_id : null;
	}

	private static function find_latest_billing_order_reference($user_id, $level_id = 0) {
		global $wpdb;

		$user_id = (int) $user_id;
		$level_id = (int) $level_id;
		if ($user_id <= 0 || !$wpdb || empty($wpdb->pmpro_membership_orders)) {
			return '';
		}

		$table = $wpdb->pmpro_membership_orders;
		$where = [
			'user_id = %d',
			'status IN (%s, %s, %s)',
		];
		$params = [$user_id, 'success', 'token', 'review'];

		if ($level_id > 0) {
			$where[] = 'membership_id = %d';
			$params[] = $level_id;
		}

		$query = $wpdb->prepare(
			"SELECT id, code
			FROM {$table}
			WHERE " . implode(' AND ', $where) . "
			ORDER BY timestamp DESC, id DESC
			LIMIT 1",
			$params
		);

		if (!is_string($query) || $query === '') {
			return '';
		}

		$order = $wpdb->get_row($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		if (!is_object($order)) {
			return '';
		}

		$code = sanitize_text_field((string) ($order->code ?? ''));
		if ($code !== '') {
			return $code;
		}

		$id = absint($order->id ?? 0);
		return $id > 0 ? (string) $id : '';
	}

	private static function get_level_names_by_id() {
		$levels_by_id = [];
		foreach (self::get_all_levels() as $level) {
			$level_id = isset($level->id) ? (int) $level->id : 0;
			$name = isset($level->name) ? (string) $level->name : '';
			if ($level_id > 0 && $name !== '') {
				$levels_by_id[$level_id] = $name;
			}
		}

		return $levels_by_id;
	}

	private static function get_subscription_date_columns() {
		static $columns = null;

		if (is_array($columns)) {
			return $columns;
		}

		global $wpdb;
		if (!$wpdb || empty($wpdb->pmpro_subscriptions)) {
			$columns = [];
			return $columns;
		}

		$table = $wpdb->pmpro_subscriptions;
		$available = $wpdb->get_col("SHOW COLUMNS FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no user input.
		$available = is_array($available) ? array_map('strval', $available) : [];
		$candidates = [
			'next_payment_date',
			'next_payment',
			'next_payment_datetime',
			'next_payment_at',
			'billing_next_payment',
			'billing_next_payment_date',
			'cycle_enddate',
			'enddate',
		];

		$columns = array_values(array_filter($candidates, function ($candidate) use ($available) {
			return in_array($candidate, $available, true);
		}));

		return $columns;
	}

	private static function normalize_subscription_date_value($value) {
		$value = sanitize_text_field((string) $value);
		if ($value === '' || $value === '0000-00-00 00:00:00' || $value === '0000-00-00') {
			return '';
		}

		$timestamp = strtotime($value);
		if ($timestamp === false) {
			return '';
		}

		return gmdate('Y-m-d', $timestamp);
	}

	private static function get_later_membership_date($first_date, $second_date) {
		$first_date = self::normalize_subscription_date_value($first_date);
		$second_date = self::normalize_subscription_date_value($second_date);

		if ($first_date === '') {
			return $second_date;
		}
		if ($second_date === '') {
			return $first_date;
		}

		$first_timestamp = strtotime($first_date . ' 23:59:59');
		$second_timestamp = strtotime($second_date . ' 23:59:59');

		if ($first_timestamp === false) {
			return $second_date;
		}
		if ($second_timestamp === false) {
			return $first_date;
		}

		return $second_timestamp > $first_timestamp ? $second_date : $first_date;
	}

	public static function normalize_date_to_month_end($value, $include_time = false) {
		$value = sanitize_text_field((string) $value);
		if ($value === '' || $value === '0000-00-00 00:00:00' || $value === '0000-00-00') {
			return '';
		}

		$timestamp = strtotime($value);
		if ($timestamp === false) {
			return '';
		}

		$month_end = strtotime(gmdate('Y-m-t', $timestamp) . ' 23:59:59');
		if ($month_end === false) {
			return '';
		}

		return $include_time ? gmdate('Y-m-d 23:59:59', $month_end) : gmdate('Y-m-d', $month_end);
	}

	public static function normalize_date_to_day_end($value, $include_time = false) {
		$value = sanitize_text_field((string) $value);
		if ($value === '' || $value === '0000-00-00 00:00:00' || $value === '0000-00-00') {
			return '';
		}

		$timestamp = strtotime($value);
		if ($timestamp === false) {
			return '';
		}

		return $include_time ? gmdate('Y-m-d 23:59:59', $timestamp) : gmdate('Y-m-d', $timestamp);
	}

	private static function normalize_membership_status($status) {
		$status = strtolower(sanitize_key((string) $status));
		return $status;
	}

	private static function normalize_card_label($card_type) {
		$card_type = sanitize_text_field((string) $card_type);
		if ($card_type === '') {
			return '';
		}

		$compact = strtolower(preg_replace('/[^a-z0-9]+/', '', $card_type));
		$map = [
			'americanexpress' => 'American Express',
			'amex' => 'American Express',
			'mastercard' => 'Mastercard',
			'mastercarddebit' => 'Mastercard',
			'visa' => 'Visa',
			'discover' => 'Discover',
		];

		if (isset($map[$compact])) {
			return $map[$compact];
		}

		return ucwords(strtolower($card_type));
	}

	private static function normalize_last4($value) {
		$digits = preg_replace('/\D+/', '', (string) $value);
		if (!is_string($digits) || $digits === '') {
			return '';
		}

		return substr($digits, -4);
	}

	private static function normalize_expiration_month($value) {
		$month = preg_replace('/\D+/', '', (string) $value);
		if (!is_string($month) || $month === '') {
			return '';
		}

		return str_pad(substr($month, -2), 2, '0', STR_PAD_LEFT);
	}

	private static function normalize_expiration_year($value) {
		$year = preg_replace('/\D+/', '', (string) $value);
		if (!is_string($year) || $year === '') {
			return '';
		}

		return strlen($year) > 2 ? substr($year, -2) : str_pad($year, 2, '0', STR_PAD_LEFT);
	}

	private static function normalize_transaction_status($status) {
		$status = sanitize_text_field((string) $status);
		$map = [
			'success' => 'Paid',
			'pending' => 'Pending',
			'review' => 'Under Review',
			'refunded' => 'Refunded',
		];

		return $map[$status] ?? ($status !== '' ? ucwords(str_replace(['_', '-'], ' ', strtolower($status))) : 'Processed');
	}

	private static function normalize_transaction_timestamp($timestamp) {
		$timestamp = sanitize_text_field((string) $timestamp);
		$unix = strtotime($timestamp);
		if ($unix === false) {
			return gmdate('c');
		}

		return gmdate('c', $unix);
	}

	private static function pmpro_page_url($page, $args = []) {
		$page = sanitize_key((string) $page);
		$url = '';

		if (function_exists('pmpro_url')) {
			$pmpro_url = pmpro_url($page);
			$url = is_string($pmpro_url) ? $pmpro_url : '';
		}

		$fallback = self::pmpro_page_fallback_url($page);
		if ($url === '') {
			$url = $fallback;
		}

		if ($page !== 'account' && function_exists('pmpro_url')) {
			$account_url = pmpro_url('account');
			$url_path = untrailingslashit((string) wp_parse_url($url, PHP_URL_PATH));
			$account_path = is_string($account_url) ? untrailingslashit((string) wp_parse_url($account_url, PHP_URL_PATH)) : '';
			if (($account_path && $url_path === $account_path) || ($page === 'cancel' && $url_path === untrailingslashit('/membership-levels'))) {
				$url = $fallback;
			}
		}

		return !empty($args) ? add_query_arg($args, $url) : $url;
	}

	private static function pmpro_page_fallback_url($page) {
		$fallback_paths = [
			'account' => '/membership-account/',
			'billing' => '/membership-account/membership-billing/',
			'invoice' => '/membership-account/membership-orders/',
			'cancel' => '/membership-account/membership-cancel/',
			'checkout' => '/membership-checkout/',
			'confirmation' => '/membership-checkout/membership-confirmation/',
		];

		$path = $fallback_paths[$page] ?? '/membership-account/';
		return home_url($path);
	}

	private static function portal_manage_membership_url() {
		return home_url('/membership/#/membership');
	}

	private static function is_future_membership_date($date_string) {
		$date_string = self::normalize_subscription_date_value($date_string);
		if ($date_string === '') {
			return false;
		}

		$expiration_unix = strtotime($date_string . ' 23:59:59');
		if ($expiration_unix === false) {
			return false;
		}

		return $expiration_unix >= current_time('timestamp');
	}

	private static function is_membership_renewal_within_days($membership, $days) {
		if (!is_array($membership)) {
			return false;
		}

		$renewal_date = self::normalize_subscription_date_value($membership['renewal_date'] ?? '');
		if ($renewal_date === '') {
			return false;
		}

		$renewal_unix = strtotime($renewal_date . ' 23:59:59');
		if ($renewal_unix === false) {
			return false;
		}

		$now = current_time('timestamp');
		if ($renewal_unix < $now) {
			return false;
		}

		$window_seconds = max(0, (int) $days) * DAY_IN_SECONDS;
		return $renewal_unix <= ($now + $window_seconds);
	}

	private static function has_future_membership_term($membership) {
		if (!is_array($membership)) {
			return false;
		}

		if (($membership['status'] ?? '') !== 'active') {
			return false;
		}

		return self::is_future_membership_date($membership['renewal_date'] ?? '')
			|| self::is_future_membership_date($membership['expiration_date'] ?? '');
	}

	private static function get_all_levels() {
		if (function_exists('pmpro_getAllLevels')) {
			$levels = pmpro_getAllLevels(false, true);
			if (is_array($levels)) {
				return $levels;
			}
		}

		global $wpdb;
		if (!$wpdb || empty($wpdb->pmpro_membership_levels)) {
			return [];
		}

		$table = $wpdb->pmpro_membership_levels;
		$query = "SELECT id, name FROM {$table} ORDER BY id ASC";

		$levels = $wpdb->get_results($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no user input.
		return is_array($levels) ? $levels : [];
	}
}
