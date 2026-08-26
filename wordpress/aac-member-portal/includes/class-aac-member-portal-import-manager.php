<?php

if (!defined('ABSPATH')) {
	exit;
}

final class AAC_Member_Portal_Import_Manager {
	const PAGE_SLUG = 'aac-member-portal-import-manager';
	const SCHEMA_VERSION = '1.0.0';
	const SCHEMA_OPTION = 'aac_member_portal_import_manager_schema_version';
	const DEFAULT_SYNC_LIMIT = 20;
	const MAX_SYNC_LIMIT = 50;

	public function __construct() {
		add_action('admin_menu', [$this, 'register_admin_page']);
		add_action('init', [$this, 'maybe_install_schema']);
		add_action('admin_post_aac_member_portal_import_upload', [$this, 'handle_upload']);
		add_action('admin_post_aac_member_portal_import_sync', [$this, 'handle_sync']);
		add_action('admin_post_aac_member_portal_import_clear', [$this, 'handle_clear']);
		add_action('admin_post_aac_member_portal_import_cleanup_test_data', [$this, 'handle_cleanup_test_data']);
		add_action('admin_post_aac_member_portal_import_repair_placeholders', [$this, 'handle_repair_placeholder_gateway_rows']);
		add_action('admin_post_aac_member_portal_import_errors', [$this, 'handle_error_export']);
	}

	public static function activate() {
		self::install_schema();
	}

	public function maybe_install_schema() {
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
				batch_id varchar(64) NOT NULL DEFAULT '',
				source_row bigint(20) unsigned NOT NULL DEFAULT 0,
				row_type varchar(32) NOT NULL DEFAULT '',
				status varchar(32) NOT NULL DEFAULT 'staged',
				match_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				aac_member_id varchar(100) NOT NULL DEFAULT '',
				email varchar(190) NOT NULL DEFAULT '',
				user_login varchar(100) NOT NULL DEFAULT '',
				membership_id bigint(20) unsigned NOT NULL DEFAULT 0,
				membership_level varchar(120) NOT NULL DEFAULT '',
				parent_aac_member_id varchar(100) NOT NULL DEFAULT '',
				parent_email varchar(190) NOT NULL DEFAULT '',
				parent_user_login varchar(100) NOT NULL DEFAULT '',
				import_action varchar(32) NOT NULL DEFAULT '',
				error_message text NULL,
				raw_row longtext NULL,
				processed_at datetime NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY batch_id (batch_id),
				KEY status (status),
				KEY email (email),
				KEY aac_member_id (aac_member_id),
				KEY membership_id (membership_id)
			) {$charset_collate};
		");

		update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
	}

	public function register_admin_page() {
		add_submenu_page(
			null,
			'Member Import Manager',
			'Member Import Manager',
			'manage_options',
			self::PAGE_SLUG,
			[$this, 'render_admin_page']
		);
	}

	public function render_admin_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'aac-member-portal'));
		}

		self::install_schema();
		$stats = $this->get_stats();
		$rows = $this->get_recent_rows();
		$notice = $this->get_admin_notice();
		?>
		<div class="wrap">
			<h1>Member Import Manager</h1>
			<?php
			if (class_exists('AAC_Member_Portal_Member_Database')) {
				AAC_Member_Portal_Member_Database::render_database_tools_nav(self::PAGE_SLUG);
			}
			?>
			<p>Stage an external member CSV first, review row-level errors, then sync valid rows into WordPress users, PMPro memberships, family/group links, and the AAC Member Database mirror.</p>

			<?php if ($notice) : ?>
				<div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible"><p><?php echo wp_kses_post($notice['message']); ?></p></div>
			<?php endif; ?>

			<div style="display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:12px;max-width:900px;margin:16px 0;">
				<?php foreach ($stats as $label => $value) : ?>
					<div style="background:#fff;border:1px solid #ccd0d4;padding:14px;">
						<strong style="display:block;font-size:22px;"><?php echo esc_html((string) $value); ?></strong>
						<span><?php echo esc_html($label); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<h2>1. Upload External Member CSV</h2>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="aac_member_portal_import_upload" />
				<?php wp_nonce_field('aac_member_portal_import_upload'); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="aac_import_kind">Import type</label></th>
						<td>
							<select id="aac_import_kind" name="aac_import_kind">
								<option value="current_members">Current members / WordPress users</option>
								<option value="family_links">Family group links</option>
								<option value="membership_history">PMPro membership history</option>
								<option value="subscriptions">PMPro subscriptions</option>
								<option value="orders">PMPro orders / payments</option>
							</select>
							<p class="description">Only use Current members for the main member creation files. Use the PMPro-specific options for history, subscriptions, and payment/order files.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aac_import_file">CSV file</label></th>
						<td><input type="file" id="aac_import_file" name="aac_import_file" accept=".csv,text/csv" required /></td>
					</tr>
				</table>
				<?php submit_button('Upload and Stage Rows'); ?>
			</form>

			<h2>2. Sync Staged Rows</h2>
			<p>Dry run validates matching and PMPro level resolution without creating or updating members. Live sync writes the valid rows. Keep batches small to avoid gateway timeouts on WPE/WordPress.com hosting.</p>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:10px;">
				<input type="hidden" name="action" value="aac_member_portal_import_sync" />
				<input type="hidden" name="dry_run" value="1" />
				<?php wp_nonce_field('aac_member_portal_import_sync'); ?>
				<label style="display:inline-flex;align-items:center;gap:6px;margin-right:8px;">
					Batch size
					<input type="number" name="import_batch_size" min="1" max="<?php echo esc_attr((string) self::MAX_SYNC_LIMIT); ?>" value="<?php echo esc_attr((string) self::DEFAULT_SYNC_LIMIT); ?>" style="width:72px;" />
				</label>
				<?php submit_button('Run Dry Run', 'secondary', 'submit', false); ?>
			</form>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:10px;">
				<input type="hidden" name="action" value="aac_member_portal_import_sync" />
				<?php wp_nonce_field('aac_member_portal_import_sync'); ?>
				<label style="display:inline-flex;align-items:center;gap:6px;margin-right:8px;">
					Batch size
					<input type="number" name="import_batch_size" min="1" max="<?php echo esc_attr((string) self::MAX_SYNC_LIMIT); ?>" value="<?php echo esc_attr((string) self::DEFAULT_SYNC_LIMIT); ?>" style="width:72px;" />
				</label>
				<?php submit_button('Sync Next Batch to WordPress and PMPro', 'primary', 'submit', false); ?>
			</form>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:10px;">
				<input type="hidden" name="action" value="aac_member_portal_import_errors" />
				<?php wp_nonce_field('aac_member_portal_import_errors'); ?>
				<?php submit_button('Download Error CSV', 'secondary', 'submit', false); ?>
			</form>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
				<input type="hidden" name="action" value="aac_member_portal_import_clear" />
				<?php wp_nonce_field('aac_member_portal_import_clear'); ?>
				<?php submit_button('Clear Staged Rows', 'delete', 'submit', false, ['onclick' => "return confirm('Clear all staged import rows? This does not delete WordPress users or PMPro memberships.');"]); ?>
			</form>

			<h2>Cleanup Test Imports</h2>
			<p>Deletes generated test accounts and their PMPro data when the user email ends in <code>@example.invalid</code>. This is intended for cleaning up the fake import dataset only.</p>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<input type="hidden" name="action" value="aac_member_portal_import_cleanup_test_data" />
				<input type="hidden" name="email_domain" value="example.invalid" />
				<?php wp_nonce_field('aac_member_portal_import_cleanup_test_data'); ?>
				<?php submit_button('Delete @example.invalid Import Data', 'delete', 'submit', false, ['onclick' => "return confirm('Delete all @example.invalid imported users and their PMPro orders, subscriptions, membership history, group links, and staged rows?');"]); ?>
			</form>

			<h2>Repair Imported Placeholder Stripe IDs</h2>
			<p>Use this after importing generated test data. It changes placeholder IDs like <code>sub_aac_import_...</code> and <code>pi_aac_import_...</code> from Stripe-managed rows to imported/manual rows so PMPro does not try to sync them with Stripe.</p>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<input type="hidden" name="action" value="aac_member_portal_import_repair_placeholders" />
				<?php wp_nonce_field('aac_member_portal_import_repair_placeholders'); ?>
				<?php submit_button('Repair Placeholder Stripe IDs', 'secondary', 'submit', false); ?>
			</form>

			<h2>Accepted Headers</h2>
			<p>Minimum for new members: <code>user_email</code>, <code>first_name</code>, <code>last_name</code>, and either <code>membership_id</code> or <code>membership_level</code>. To preserve AAC member numbers, include <code>aac_member_id</code> or <code>member_id</code>.</p>
			<p>Family child rows can include <code>row_type=child</code>, plus one parent matcher: <code>parent_member_id</code>, <code>parent_email</code>, or <code>parent_user_login</code>. Stripe references can be stored with <code>stripe_customer_id</code>, <code>stripe_subscription_id</code>, and <code>stripe_payment_transaction_id</code>.</p>
			<p>Do not upload membership history, subscriptions, or orders using the Current members import type. Those files write to different PMPro tables and will not create WordPress users.</p>

			<h2>Recent Staged Rows</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Batch</th>
						<th>Row</th>
						<th>Email</th>
						<th>Member ID</th>
						<th>Level</th>
						<th>Type</th>
						<th>Status</th>
						<th>Action</th>
						<th>Error</th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($rows)) : ?>
						<tr><td colspan="9">No staged rows yet.</td></tr>
					<?php endif; ?>
					<?php foreach ($rows as $row) : ?>
						<tr>
							<td><?php echo esc_html($row['batch_id']); ?></td>
							<td><?php echo esc_html((string) $row['source_row']); ?></td>
							<td><?php echo esc_html($row['email']); ?></td>
							<td><?php echo esc_html($row['aac_member_id']); ?></td>
							<td><?php echo esc_html($row['membership_level'] ?: (string) $row['membership_id']); ?></td>
							<td><?php echo esc_html($row['row_type']); ?></td>
							<td><?php echo esc_html($row['status']); ?></td>
							<td><?php echo esc_html($row['import_action']); ?></td>
							<td><?php echo esc_html($row['error_message']); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function handle_upload() {
		$this->require_admin_post('aac_member_portal_import_upload');

		if (empty($_FILES['aac_import_file']['tmp_name'])) {
			$this->redirect_with_notice('error', 'No CSV file was uploaded.');
		}

		$kind = isset($_POST['aac_import_kind']) ? sanitize_key(wp_unslash($_POST['aac_import_kind'])) : 'current_members';
		$file_path = (string) $_FILES['aac_import_file']['tmp_name'];
		if ($kind === 'family_links') {
			$result = $this->import_family_links_csv_file($file_path);
		} elseif ($kind === 'membership_history') {
			$result = $this->import_pmpro_membership_history_csv($file_path);
		} elseif ($kind === 'subscriptions') {
			$result = $this->import_pmpro_subscriptions_csv($file_path);
		} elseif ($kind === 'orders') {
			$result = $this->import_pmpro_orders_csv($file_path);
		} else {
			$result = $this->stage_csv($file_path);
		}

		if (is_wp_error($result)) {
			$this->redirect_with_notice('error', $result->get_error_message());
		}

		if ($kind !== 'current_members') {
			$this->redirect_with_notice('success', sprintf(
				'%s import complete. Imported/updated: %d. Skipped: %d.',
				ucwords(str_replace('_', ' ', $kind)),
				(int) ($result['imported'] ?? 0),
				(int) ($result['skipped'] ?? 0)
			));
		} else {
			$this->redirect_with_notice('success', sprintf(
				'Staged %d rows in batch %s. %d row(s) need attention.',
				(int) $result['staged'],
				esc_html($result['batch_id']),
				(int) $result['errors']
			));
		}
	}

	public function handle_sync() {
		$this->require_admin_post('aac_member_portal_import_sync');

		$dry_run = !empty($_POST['dry_run']);
		$limit = $this->get_requested_batch_limit();
		$result = $this->sync_staged_rows($dry_run, $limit);
		if (is_wp_error($result)) {
			$this->redirect_with_notice('error', $result->get_error_message());
		}

		$this->redirect_with_notice('success', sprintf(
			'%s batch complete. Batch size: %d. Synced: %d. Validated: %d. Errors: %d. Family links: %d. Remaining rows: %d.',
			$dry_run ? 'Dry run' : 'Sync',
			(int) $result['limit'],
			(int) $result['synced'],
			(int) $result['validated'],
			(int) $result['errors'],
			(int) $result['family_links'],
			(int) $result['remaining']
		));
	}

	public function handle_clear() {
		$this->require_admin_post('aac_member_portal_import_clear');
		global $wpdb;
		$wpdb->query('TRUNCATE TABLE ' . self::table_name()); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table.
		$this->redirect_with_notice('success', 'Staged import rows cleared. Existing WordPress users and PMPro memberships were not changed.');
	}

	public function handle_cleanup_test_data() {
		$this->require_admin_post('aac_member_portal_import_cleanup_test_data');
		$email_domain = isset($_POST['email_domain']) ? sanitize_text_field(wp_unslash($_POST['email_domain'])) : 'example.invalid';
		$result = self::cleanup_imported_data([
			'email_domain' => $email_domain,
			'import_source' => '',
			'dry_run' => false,
		]);

		if (is_wp_error($result)) {
			$this->redirect_with_notice('error', $result->get_error_message());
		}

		$this->redirect_with_notice('success', sprintf(
			'Cleanup complete. Deleted %d users, %d membership rows, %d order rows, %d subscription rows, %d group-member rows, %d group rows, %d AAC mirror rows, and %d staged rows.',
			(int) ($result['users_deleted'] ?? 0),
			(int) ($result['membership_rows_deleted'] ?? 0),
			(int) ($result['order_rows_deleted'] ?? 0),
			(int) ($result['subscription_rows_deleted'] ?? 0),
			(int) ($result['group_member_rows_deleted'] ?? 0),
			(int) ($result['group_rows_deleted'] ?? 0),
			(int) ($result['mirror_rows_deleted'] ?? 0),
			(int) ($result['staged_rows_deleted'] ?? 0)
		));
	}

	public function handle_repair_placeholder_gateway_rows() {
		$this->require_admin_post('aac_member_portal_import_repair_placeholders');

		$result = self::repair_placeholder_gateway_rows(false);
		if (is_wp_error($result)) {
			$this->redirect_with_notice('error', $result->get_error_message());
		}

		$this->redirect_with_notice('success', sprintf(
			'Placeholder Stripe ID repair complete. Updated %d subscription rows, %d order rows, and %d membership history rows.',
			(int) ($result['subscription_rows_updated'] ?? 0),
			(int) ($result['order_rows_updated'] ?? 0),
			(int) ($result['membership_history_rows_updated'] ?? 0)
		));
	}

	public function handle_error_export() {
		$this->require_admin_post('aac_member_portal_import_errors');
		global $wpdb;

		$rows = $wpdb->get_results("SELECT * FROM " . self::table_name() . " WHERE status = 'error' ORDER BY id ASC", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table.
		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=aac-member-import-errors-' . gmdate('Ymd-His') . '.csv');
		$out = fopen('php://output', 'w');
		fputcsv($out, ['batch_id', 'source_row', 'email', 'aac_member_id', 'membership_id', 'membership_level', 'error_message', 'raw_row']);
		foreach ((array) $rows as $row) {
			fputcsv($out, [
				$row['batch_id'],
				$row['source_row'],
				$row['email'],
				$row['aac_member_id'],
				$row['membership_id'],
				$row['membership_level'],
				$row['error_message'],
				$row['raw_row'],
			]);
		}
		fclose($out);
		exit;
	}

	public function import_family_links_csv_file($file_path) {
		if (!class_exists('AAC_Member_Portal_Group_Accounts')) {
			return new WP_Error('group_accounts_missing', 'The PMPro Group Accounts integration is not available.');
		}

		$result = AAC_Member_Portal_Group_Accounts::import_family_group_links_csv($file_path);
		if (is_wp_error($result)) {
			return $result;
		}

		return [
			'imported' => (int) ($result['children_linked'] ?? 0),
			'skipped' => (int) ($result['rows_skipped'] ?? 0),
		];
	}

	public function import_pmpro_membership_history_csv($file_path) {
		global $wpdb;

		if (!$wpdb || empty($wpdb->pmpro_memberships_users)) {
			return new WP_Error('pmpro_history_table_missing', 'The PMPro membership history table is not available.');
		}

		return $this->import_pmpro_rows_from_csv($file_path, $wpdb->pmpro_memberships_users, function ($row, $user_id, $columns) {
			$level_id = $this->resolve_membership_level_id($row);
			if ($user_id <= 0 || $level_id <= 0) {
				return new WP_Error('missing_history_user_or_level', 'Could not resolve user or membership level for history row.');
			}

			$data = [
				'user_id' => $user_id,
				'membership_id' => $level_id,
				'code_id' => absint($this->row_value($row, ['membership_code_id', 'code_id'])),
				'initial_payment' => $this->money_value($this->row_value($row, ['initial_payment', 'membership_initial_payment'])),
				'billing_amount' => $this->money_value($this->row_value($row, ['billing_amount', 'membership_billing_amount'])),
				'cycle_number' => absint($this->row_value($row, ['cycle_number', 'membership_cycle_number'])),
				'cycle_period' => sanitize_text_field($this->row_value($row, ['cycle_period', 'membership_cycle_period'])),
				'billing_limit' => absint($this->row_value($row, ['billing_limit', 'membership_billing_limit'])),
				'trial_amount' => $this->money_value($this->row_value($row, ['trial_amount', 'membership_trial_amount'])),
				'trial_limit' => absint($this->row_value($row, ['trial_limit', 'membership_trial_limit'])),
				'status' => sanitize_text_field($this->row_value($row, ['status', 'membership_status'])) ?: 'active',
				'startdate' => $this->normalize_date($this->row_value($row, ['startdate', 'membership_startdate', 'start_date'])),
				'enddate' => $this->normalize_date($this->row_value($row, ['enddate', 'membership_enddate', 'end_date', 'expiration_date'])),
				'modified' => $this->normalize_date($this->row_value($row, ['modified', 'membership_timestamp', 'timestamp'])),
			];
			$optional = [
				'subscription_transaction_id' => ['subscription_transaction_id', 'membership_subscription_transaction_id', 'stripe_subscription_id'],
				'payment_transaction_id' => ['payment_transaction_id', 'membership_payment_transaction_id', 'stripe_payment_transaction_id'],
				'gateway' => ['gateway', 'membership_gateway'],
				'gateway_environment' => ['gateway_environment', 'membership_gateway_environment'],
			];
			foreach ($optional as $column => $keys) {
				if (in_array($column, $columns, true)) {
					$data[$column] = sanitize_text_field($this->row_value($row, $keys));
				}
			}

			return [
				'data' => $this->filter_data_to_columns($data, $columns),
				'match' => [
					'user_id' => $user_id,
					'membership_id' => $level_id,
					'startdate' => $data['startdate'],
				],
			];
		});
	}

	public function import_pmpro_subscriptions_csv($file_path) {
		global $wpdb;

		if (!$wpdb || empty($wpdb->pmpro_subscriptions)) {
			return new WP_Error('pmpro_subscription_table_missing', 'The PMPro subscriptions table is not available.');
		}

		return $this->import_pmpro_rows_from_csv($file_path, $wpdb->pmpro_subscriptions, function ($row, $user_id, $columns) {
			$level_id = $this->resolve_membership_level_id($row);
			$subscription_id = sanitize_text_field($this->row_value($row, ['subscription_transaction_id', 'membership_subscription_transaction_id', 'stripe_subscription_id', 'subscription_id']));
			if ($user_id <= 0 || $level_id <= 0 || $subscription_id === '') {
				return new WP_Error('missing_subscription_keys', 'Could not resolve user, membership level, or subscription transaction ID.');
			}

			$gateway = sanitize_text_field($this->row_value($row, ['gateway', 'membership_gateway'])) ?: 'stripe';
			$gateway_environment = sanitize_text_field($this->row_value($row, ['gateway_environment', 'membership_gateway_environment'])) ?: 'sandbox';
			if (self::is_placeholder_gateway_reference($subscription_id)) {
				$gateway = 'imported';
				$gateway_environment = 'imported';
			}

			$data = [
				'user_id' => $user_id,
				'membership_level_id' => $level_id,
				'membership_id' => $level_id,
				'gateway' => $gateway,
				'gateway_environment' => $gateway_environment,
				'subscription_transaction_id' => $subscription_id,
				'status' => sanitize_text_field($this->row_value($row, ['status'])) ?: 'active',
				'startdate' => $this->normalize_date($this->row_value($row, ['startdate', 'membership_startdate', 'start_date'])),
				'enddate' => $this->normalize_date($this->row_value($row, ['enddate', 'membership_enddate', 'end_date', 'expiration_date'])),
				'next_payment_date' => $this->normalize_date($this->row_value($row, ['next_payment_date', 'renewal_date'])),
				'billing_amount' => $this->money_value($this->row_value($row, ['billing_amount', 'membership_billing_amount'])),
				'cycle_number' => absint($this->row_value($row, ['cycle_number', 'membership_cycle_number'])) ?: 1,
				'cycle_period' => sanitize_text_field($this->row_value($row, ['cycle_period', 'membership_cycle_period'])) ?: 'Year',
				'billing_limit' => absint($this->row_value($row, ['billing_limit', 'membership_billing_limit'])),
				'trial_amount' => $this->money_value($this->row_value($row, ['trial_amount', 'membership_trial_amount'])),
				'trial_limit' => absint($this->row_value($row, ['trial_limit', 'membership_trial_limit'])),
				'modified' => $this->normalize_date($this->row_value($row, ['modified', 'timestamp'])),
			];

			$customer_id = sanitize_text_field($this->row_value($row, ['stripe_customer_id', 'pmpro_stripe_customerid', 'customer_id']));
			if ($customer_id !== '') {
				update_user_meta($user_id, 'pmpro_stripe_customerid', $customer_id);
			}

			return [
				'data' => $this->filter_data_to_columns($data, $columns),
				'match' => [
					'user_id' => $user_id,
					'subscription_transaction_id' => $subscription_id,
				],
			];
		});
	}

	public function import_pmpro_orders_csv($file_path) {
		global $wpdb;

		if (!$wpdb || empty($wpdb->pmpro_membership_orders)) {
			return new WP_Error('pmpro_orders_table_missing', 'The PMPro orders/payments table is not available.');
		}

		return $this->import_pmpro_rows_from_csv($file_path, $wpdb->pmpro_membership_orders, function ($row, $user_id, $columns) {
			$level_id = $this->resolve_membership_level_id($row);
			$payment_id = sanitize_text_field($this->row_value($row, ['payment_transaction_id', 'membership_payment_transaction_id', 'stripe_payment_transaction_id']));
			$code = sanitize_text_field($this->row_value($row, ['code', 'order_code']));
			if ($code === '') {
				$code = 'AAC-' . sanitize_text_field($this->row_value($row, ['aac_member_id', 'member_id'])) . '-' . gmdate('YmdHis');
			}
			if ($user_id <= 0 || $level_id <= 0) {
				return new WP_Error('missing_order_user_or_level', 'Could not resolve user or membership level for order row.');
			}

			$subscription_id = sanitize_text_field($this->row_value($row, ['subscription_transaction_id', 'membership_subscription_transaction_id', 'stripe_subscription_id']));
			$gateway = sanitize_text_field($this->row_value($row, ['gateway', 'membership_gateway'])) ?: 'stripe';
			$gateway_environment = sanitize_text_field($this->row_value($row, ['gateway_environment', 'membership_gateway_environment'])) ?: 'sandbox';
			if (self::is_placeholder_gateway_reference($payment_id) || self::is_placeholder_gateway_reference($subscription_id)) {
				$gateway = 'imported';
				$gateway_environment = 'imported';
			}

			$data = [
				'code' => $code,
				'user_id' => $user_id,
				'membership_id' => $level_id,
				'subtotal' => $this->money_value($this->row_value($row, ['subtotal'])),
				'tax' => $this->money_value($this->row_value($row, ['tax'])),
				'couponamount' => $this->money_value($this->row_value($row, ['discount_amount', 'couponamount'])),
				'total' => $this->money_value($this->row_value($row, ['total'])),
				'status' => sanitize_text_field($this->row_value($row, ['status'])) ?: 'success',
				'gateway' => $gateway,
				'gateway_environment' => $gateway_environment,
				'payment_transaction_id' => $payment_id,
				'subscription_transaction_id' => $subscription_id,
				'cardtype' => sanitize_text_field($this->row_value($row, ['card_brand', 'cardtype'])),
				'accountnumber' => sanitize_text_field($this->row_value($row, ['card_last4', 'accountnumber'])),
				'timestamp' => $this->normalize_date($this->row_value($row, ['timestamp', 'membership_timestamp'])),
			];

			return [
				'data' => $this->filter_data_to_columns($data, $columns),
				'match' => $payment_id !== ''
					? ['payment_transaction_id' => $payment_id]
					: ['code' => $code],
			];
		});
	}

	private function import_pmpro_rows_from_csv($file_path, $table, $builder) {
		global $wpdb;

		$handle = fopen($file_path, 'r');
		if (!$handle) {
			return new WP_Error('unreadable_csv', 'The CSV file could not be read.');
		}

		$raw_headers = fgetcsv($handle);
		if (!is_array($raw_headers) || empty($raw_headers)) {
			fclose($handle);
			return new WP_Error('missing_headers', 'The CSV file does not include a header row.');
		}

		$headers = array_map([__CLASS__, 'normalize_header'], $raw_headers);
		$columns = $this->get_table_columns($table);
		$imported = 0;
		$skipped = 0;

		while (($values = fgetcsv($handle)) !== false) {
			if (!is_array($values) || count(array_filter($values, static function ($value) {
				return trim((string) $value) !== '';
			})) === 0) {
				continue;
			}

			$row = [];
			foreach ($headers as $index => $header) {
				if ($header !== '') {
					$row[$header] = isset($values[$index]) ? trim((string) $values[$index]) : '';
				}
			}

			$user = $this->resolve_user($row);
			$user_id = $user instanceof WP_User ? (int) $user->ID : absint($this->row_value($row, ['user_id', 'wp_user_id']));
			$built = $builder($row, $user_id, $columns);
			if (is_wp_error($built) || empty($built['data']) || empty($built['match'])) {
				$skipped++;
				continue;
			}

			if ($this->upsert_pmpro_table_row($table, $built['data'], $built['match'])) {
				$imported++;
			} else {
				$skipped++;
			}
		}

		fclose($handle);
		return [
			'imported' => $imported,
			'skipped' => $skipped,
		];
	}

	private function upsert_pmpro_table_row($table, $data, $match) {
		global $wpdb;

		$data = array_filter((array) $data, static function ($value) {
			return $value !== null;
		});
		$match = array_filter((array) $match, static function ($value) {
			return $value !== null && $value !== '';
		});
		if (empty($data) || empty($match)) {
			return false;
		}

		$where_sql = [];
		$where_values = [];
		foreach ($match as $column => $value) {
			$where_sql[] = "{$column} = %s";
			$where_values[] = (string) $value;
		}
		$id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE " . implode(' AND ', $where_sql) . ' ORDER BY id DESC LIMIT 1', $where_values)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table and match columns are plugin-controlled.

		if ($id > 0) {
			return false !== $wpdb->update($table, $data, ['id' => $id]);
		}

		return false !== $wpdb->insert($table, $data);
	}

	public function stage_csv($file_path) {
		global $wpdb;

		self::install_schema();
		$handle = fopen($file_path, 'r');
		if (!$handle) {
			return new WP_Error('unreadable_csv', 'The CSV file could not be read.');
		}

		$raw_headers = fgetcsv($handle);
		if (!is_array($raw_headers) || empty($raw_headers)) {
			fclose($handle);
			return new WP_Error('missing_headers', 'The CSV file does not include a header row.');
		}

		$headers = array_map([__CLASS__, 'normalize_header'], $raw_headers);
		$batch_id = 'import-' . current_time('Ymd-His');
		$source_row = 1;
		$staged = 0;
		$errors = 0;

		while (($values = fgetcsv($handle)) !== false) {
			$source_row++;
			if (!is_array($values) || count(array_filter($values, static function ($value) {
				return trim((string) $value) !== '';
			})) === 0) {
				continue;
			}

			$row = [];
			foreach ($headers as $index => $header) {
				if ($header === '') {
					continue;
				}
				$row[$header] = isset($values[$index]) ? trim((string) $values[$index]) : '';
			}

			$prepared = $this->prepare_row_summary($row);
			if ($prepared['status'] === 'error') {
				$errors++;
			}

			$now = current_time('mysql');
			$wpdb->insert(
				self::table_name(),
				[
					'batch_id' => $batch_id,
					'source_row' => $source_row,
					'row_type' => $prepared['row_type'],
					'status' => $prepared['status'],
					'aac_member_id' => $prepared['aac_member_id'],
					'email' => $prepared['email'],
					'user_login' => $prepared['user_login'],
					'membership_id' => $prepared['membership_id'],
					'membership_level' => $prepared['membership_level'],
					'parent_aac_member_id' => $prepared['parent_aac_member_id'],
					'parent_email' => $prepared['parent_email'],
					'parent_user_login' => $prepared['parent_user_login'],
					'error_message' => $prepared['error_message'],
					'raw_row' => wp_json_encode($row),
					'created_at' => $now,
					'updated_at' => $now,
				],
				['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
			);
			$staged++;
		}

		fclose($handle);
		return [
			'batch_id' => $batch_id,
			'staged' => $staged,
			'errors' => $errors,
		];
	}

	public function sync_staged_rows($dry_run = false, $limit = null) {
		global $wpdb;

		self::install_schema();
		$limit = $this->normalize_batch_limit($limit);
		$table = self::table_name();
		$status_clause = $dry_run ? "status = 'staged'" : "status IN ('staged', 'dry_run')";
		$rows = $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$table} WHERE {$status_clause} ORDER BY id ASC LIMIT %d", $limit),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin-owned table and fixed status clause.
		if (empty($rows)) {
			return [
				'limit' => $limit,
				'synced' => 0,
				'validated' => 0,
				'errors' => 0,
				'family_links' => 0,
				'remaining' => 0,
			];
		}

		$stats = [
			'limit' => $limit,
			'synced' => 0,
			'validated' => 0,
			'errors' => 0,
			'family_links' => 0,
			'remaining' => 0,
		];
		$family_link_rows = [];

		foreach ($rows as $stage_row) {
			$raw_row = json_decode((string) $stage_row['raw_row'], true);
			if (!is_array($raw_row)) {
				$this->mark_row_error((int) $stage_row['id'], 'The staged raw row is not valid JSON.');
				$stats['errors']++;
				continue;
			}

			$result = $this->sync_single_row($raw_row, $dry_run);
			if (is_wp_error($result)) {
				$this->mark_row_error((int) $stage_row['id'], $result->get_error_message());
				$stats['errors']++;
				continue;
			}

			$user_id = (int) ($result['user_id'] ?? 0);
			$status = $dry_run ? 'dry_run' : 'synced';
			$wpdb->update(
				self::table_name(),
				[
					'status' => $status,
					'match_user_id' => $user_id,
					'import_action' => (string) ($result['action'] ?? ''),
					'error_message' => '',
					'processed_at' => current_time('mysql'),
					'updated_at' => current_time('mysql'),
				],
				['id' => (int) $stage_row['id']],
				['%s', '%d', '%s', '%s', '%s', '%s'],
				['%d']
			);

			if ($dry_run) {
				$stats['validated']++;
			} else {
				$stats['synced']++;
				if ($this->is_family_child_row($raw_row)) {
					$family_link_rows[] = $this->build_family_link_row($raw_row, $user_id);
				}
			}
		}

		if (!$dry_run && !empty($family_link_rows)) {
			$link_stats = $this->import_family_links($family_link_rows);
			$stats['family_links'] = (int) ($link_stats['children_linked'] ?? 0);
			if (!empty($link_stats['rows_skipped'])) {
				$stats['errors'] += (int) $link_stats['rows_skipped'];
			}
		}

		$stats['remaining'] = $this->count_remaining_rows($dry_run);
		return $stats;
	}

	public static function cleanup_imported_data($args = []) {
		global $wpdb;

		if (!$wpdb) {
			return new WP_Error('database_unavailable', 'The WordPress database is not available.');
		}

		$email_domain = sanitize_text_field((string) ($args['email_domain'] ?? 'example.invalid'));
		$import_source = sanitize_text_field((string) ($args['import_source'] ?? ''));
		$dry_run = !empty($args['dry_run']);
		$user_ids = self::find_imported_user_ids_for_cleanup($email_domain, $import_source);
		$mirror_user_ids = self::find_mirror_user_ids_for_cleanup($email_domain);
		$data_user_ids = array_values(array_unique(array_merge($user_ids, $mirror_user_ids)));
		$stats = [
			'dry_run' => $dry_run,
			'matched_users' => count($user_ids),
			'matched_mirror_users' => count($mirror_user_ids),
			'users_deleted' => 0,
			'membership_rows_deleted' => 0,
			'order_rows_deleted' => 0,
			'subscription_rows_deleted' => 0,
			'group_member_rows_deleted' => 0,
			'group_rows_deleted' => 0,
			'mirror_rows_deleted' => 0,
			'staged_rows_deleted' => 0,
		];

		if (empty($data_user_ids)) {
			if (!$dry_run) {
				$stats['staged_rows_deleted'] = self::delete_staged_rows_for_cleanup($email_domain);
			} else {
				$stats['staged_rows_deleted'] = self::count_staged_rows_for_cleanup($email_domain);
			}
			return $stats;
		}

		$group_ids = self::get_group_ids_for_parent_users($data_user_ids);

		$stats['membership_rows_deleted'] = self::delete_rows_for_user_ids(self::pmpro_table_name('pmpro_memberships_users'), ['user_id'], $data_user_ids, $dry_run);
		$stats['order_rows_deleted'] = self::delete_rows_for_user_ids(self::pmpro_table_name('pmpro_membership_orders'), ['user_id'], $data_user_ids, $dry_run);
		$stats['subscription_rows_deleted'] = self::delete_rows_for_user_ids(self::pmpro_table_name('pmpro_subscriptions'), ['user_id'], $data_user_ids, $dry_run);
		$stats['group_member_rows_deleted'] = self::delete_group_member_rows($data_user_ids, $group_ids, $dry_run);
		$stats['group_rows_deleted'] = self::delete_group_rows($data_user_ids, $group_ids, $dry_run);
		$stats['mirror_rows_deleted'] = self::delete_mirror_rows($data_user_ids, $email_domain, $dry_run);

		if (!$dry_run) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			foreach ($user_ids as $user_id) {
				if (wp_delete_user((int) $user_id)) {
					$stats['users_deleted']++;
				}
			}
			$stats['staged_rows_deleted'] = self::delete_staged_rows_for_cleanup($email_domain);
		} else {
			$stats['users_deleted'] = count($user_ids);
			$stats['staged_rows_deleted'] = self::count_staged_rows_for_cleanup($email_domain);
		}

		return $stats;
	}

	public static function repair_placeholder_gateway_rows($dry_run = false) {
		global $wpdb;

		if (!$wpdb) {
			return new WP_Error('database_unavailable', 'The WordPress database is not available.');
		}

		return [
			'dry_run' => (bool) $dry_run,
			'subscription_rows_updated' => self::repair_placeholder_rows_in_table(
				self::pmpro_table_name('pmpro_subscriptions'),
				['subscription_transaction_id'],
				$dry_run
			),
			'order_rows_updated' => self::repair_placeholder_rows_in_table(
				self::pmpro_table_name('pmpro_membership_orders'),
				['payment_transaction_id', 'subscription_transaction_id'],
				$dry_run
			),
			'membership_history_rows_updated' => self::repair_placeholder_rows_in_table(
				self::pmpro_table_name('pmpro_memberships_users'),
				['payment_transaction_id', 'subscription_transaction_id'],
				$dry_run
			),
		];
	}

	private static function repair_placeholder_rows_in_table($table, $reference_columns, $dry_run = false) {
		global $wpdb;

		if (!self::table_exists($table)) {
			return 0;
		}

		$columns = self::get_columns_for_table($table);
		if (!in_array('gateway', $columns, true)) {
			return 0;
		}

		$conditions = [];
		$params = [];
		foreach ((array) $reference_columns as $column) {
			$column = sanitize_key((string) $column);
			if (!in_array($column, $columns, true)) {
				continue;
			}
			$conditions[] = "{$column} LIKE %s";
			$params[] = '%\_aac\_import\_%';
		}

		if (empty($conditions)) {
			return 0;
		}

		$where = '(' . implode(' OR ', $conditions) . ')';
		if (in_array('gateway_environment', $columns, true)) {
			$set_sql = "gateway = 'imported', gateway_environment = 'imported'";
		} else {
			$set_sql = "gateway = 'imported'";
		}

		if ($dry_run) {
			return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}", $params)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table/columns discovered from schema, values prepared.
		}

		return (int) $wpdb->query($wpdb->prepare("UPDATE {$table} SET {$set_sql} WHERE {$where}", $params)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table/columns discovered from schema, values prepared.
	}

	private static function is_placeholder_gateway_reference($reference) {
		$reference = strtolower(trim((string) $reference));
		return $reference !== '' && strpos($reference, '_aac_import_') !== false;
	}

	private static function find_imported_user_ids_for_cleanup($email_domain, $import_source = '') {
		global $wpdb;

		$where = [];
		$params = [];
		$email_domain = trim((string) $email_domain);
		if ($email_domain !== '') {
			$email_domain = ltrim($email_domain, '@');
			$where[] = 'u.user_email LIKE %s';
			$params[] = '%@' . $wpdb->esc_like($email_domain);
		}

		if ($import_source !== '') {
			$where[] = "EXISTS (
				SELECT 1 FROM {$wpdb->usermeta} source_meta
				WHERE source_meta.user_id = u.ID
				AND source_meta.meta_key = 'aac_import_source'
				AND source_meta.meta_value = %s
			)";
			$params[] = $import_source;
		}

		if (empty($where)) {
			return [];
		}

		$sql = 'SELECT DISTINCT u.ID FROM ' . $wpdb->users . ' u WHERE ' . implode(' OR ', $where);
		$user_ids = $wpdb->get_col($wpdb->prepare($sql, $params)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed SQL fragments with prepared values.
		return array_values(array_unique(array_map('intval', (array) $user_ids)));
	}

	private static function find_mirror_user_ids_for_cleanup($email_domain) {
		global $wpdb;

		$table = $wpdb->prefix . 'aac_member_db_profiles';
		if (!self::table_exists($table)) {
			return [];
		}

		$email_domain = ltrim(trim((string) $email_domain), '@');
		if ($email_domain === '') {
			return [];
		}

		$user_ids = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT user_id FROM {$table} WHERE email LIKE %s", '%@' . $wpdb->esc_like($email_domain))); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table.
		return array_values(array_unique(array_map('intval', (array) $user_ids)));
	}

	private static function delete_staged_rows_for_cleanup($email_domain) {
		global $wpdb;

		$table = self::table_name();
		if (!self::table_exists($table)) {
			return 0;
		}

		$email_domain = ltrim(trim((string) $email_domain), '@');
		if ($email_domain === '') {
			return (int) $wpdb->query("TRUNCATE TABLE {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table.
		}

		return (int) $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE email LIKE %s", '%@' . $wpdb->esc_like($email_domain))); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table.
	}

	private static function count_staged_rows_for_cleanup($email_domain) {
		global $wpdb;

		$table = self::table_name();
		if (!self::table_exists($table)) {
			return 0;
		}

		$email_domain = ltrim(trim((string) $email_domain), '@');
		if ($email_domain === '') {
			return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table.
		}

		return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE email LIKE %s", '%@' . $wpdb->esc_like($email_domain))); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table.
	}

	private static function delete_rows_for_user_ids($table, $columns, $user_ids, $dry_run = false) {
		global $wpdb;

		if (!self::table_exists($table) || empty($user_ids)) {
			return 0;
		}

		$available_columns = self::get_columns_for_table($table);
		$count = 0;
		foreach ((array) $columns as $column) {
			$column = sanitize_key((string) $column);
			if (!in_array($column, $available_columns, true)) {
				continue;
			}
			$count += self::delete_rows_by_int_values($table, $column, $user_ids, $dry_run);
		}

		return $count;
	}

	private static function delete_group_member_rows($user_ids, $group_ids, $dry_run = false) {
		$count = 0;
		foreach (self::group_member_tables() as $table) {
			$count += self::delete_rows_for_user_ids($table, [
				'group_child_user_id',
				'child_user_id',
				'group_user_id',
				'member_user_id',
				'user_id',
			], $user_ids, $dry_run);
			if (!empty($group_ids)) {
				$count += self::delete_rows_by_existing_int_columns($table, ['group_id', 'pmprogroupacct_group_id'], $group_ids, $dry_run);
			}
		}
		return $count;
	}

	private static function delete_group_rows($user_ids, $group_ids, $dry_run = false) {
		$count = 0;
		foreach (self::group_account_tables() as $table) {
			$count += self::delete_rows_by_existing_int_columns($table, ['group_parent_user_id', 'parent_user_id'], $user_ids, $dry_run);
			if (!empty($group_ids)) {
				$count += self::delete_rows_by_existing_int_columns($table, ['id', 'group_id'], $group_ids, $dry_run);
			}
		}
		return $count;
	}

	private static function delete_mirror_rows($user_ids, $email_domain, $dry_run = false) {
		global $wpdb;

		$count = 0;
		$tables = [
			$wpdb->prefix . 'aac_member_db_history',
			$wpdb->prefix . 'aac_member_db_subscriptions',
			$wpdb->prefix . 'aac_member_db_transactions',
			$wpdb->prefix . 'aac_member_db_profiles',
		];

		foreach ($tables as $table) {
			$count += self::delete_rows_for_user_ids($table, ['user_id'], $user_ids, $dry_run);
		}

		$profile_table = $wpdb->prefix . 'aac_member_db_profiles';
		$email_domain = ltrim(trim((string) $email_domain), '@');
		if ($email_domain !== '' && self::table_exists($profile_table)) {
			if ($dry_run) {
				$count += (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$profile_table} WHERE email LIKE %s", '%@' . $wpdb->esc_like($email_domain))); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table.
			} else {
				$count += (int) $wpdb->query($wpdb->prepare("DELETE FROM {$profile_table} WHERE email LIKE %s", '%@' . $wpdb->esc_like($email_domain))); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table.
			}
		}

		return $count;
	}

	private static function get_group_ids_for_parent_users($user_ids) {
		$group_ids = [];
		foreach (self::group_account_tables() as $table) {
			$columns = self::get_columns_for_table($table);
			if (!in_array('id', $columns, true)) {
				continue;
			}
			foreach (['group_parent_user_id', 'parent_user_id'] as $column) {
				if (!in_array($column, $columns, true)) {
					continue;
				}
				$group_ids = array_merge($group_ids, self::select_int_values_by_int_values($table, 'id', $column, $user_ids));
			}
		}
		return array_values(array_unique(array_map('intval', $group_ids)));
	}

	private static function delete_rows_by_existing_int_columns($table, $columns, $values, $dry_run = false) {
		$available_columns = self::get_columns_for_table($table);
		$count = 0;
		foreach ((array) $columns as $column) {
			$column = sanitize_key((string) $column);
			if (!in_array($column, $available_columns, true)) {
				continue;
			}
			$count += self::delete_rows_by_int_values($table, $column, $values, $dry_run);
		}
		return $count;
	}

	private static function delete_rows_by_int_values($table, $column, $values, $dry_run = false) {
		global $wpdb;

		$values = array_values(array_filter(array_unique(array_map('intval', (array) $values))));
		if (!self::table_exists($table) || empty($values)) {
			return 0;
		}

		$placeholders = implode(',', array_fill(0, count($values), '%d'));
		if ($dry_run) {
			$sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} IN ({$placeholders})";
			return (int) $wpdb->get_var($wpdb->prepare($sql, $values)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table/column are discovered sanitized names, values prepared.
		}

		$sql = "DELETE FROM {$table} WHERE {$column} IN ({$placeholders})";
		return (int) $wpdb->query($wpdb->prepare($sql, $values)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table/column are discovered sanitized names, values prepared.
	}

	private static function select_int_values_by_int_values($table, $select_column, $where_column, $values) {
		global $wpdb;

		$values = array_values(array_filter(array_unique(array_map('intval', (array) $values))));
		if (!self::table_exists($table) || empty($values)) {
			return [];
		}

		$placeholders = implode(',', array_fill(0, count($values), '%d'));
		$sql = "SELECT {$select_column} FROM {$table} WHERE {$where_column} IN ({$placeholders})";
		$selected = $wpdb->get_col($wpdb->prepare($sql, $values)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table/column are discovered sanitized names, values prepared.
		return array_values(array_unique(array_map('intval', (array) $selected)));
	}

	private static function pmpro_table_name($property) {
		global $wpdb;

		if (isset($wpdb->{$property}) && $wpdb->{$property}) {
			return $wpdb->{$property};
		}

		return $wpdb->prefix . preg_replace('/^pmpro_/', 'pmpro_', sanitize_key($property));
	}

	private static function group_account_tables() {
		return self::discover_tables($GLOBALS['wpdb']->prefix . 'pmprogroupacct%', static function ($table) {
			return strpos($table, 'group') !== false && strpos($table, 'member') === false;
		});
	}

	private static function group_member_tables() {
		return self::discover_tables($GLOBALS['wpdb']->prefix . 'pmprogroupacct%', static function ($table) {
			return strpos($table, 'member') !== false;
		});
	}

	private static function discover_tables($like, $filter) {
		global $wpdb;

		$tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));
		$tables = array_filter(array_map(function ($table) {
			return preg_replace('/[^A-Za-z0-9_]/', '', (string) $table);
		}, (array) $tables));

		return array_values(array_filter($tables, $filter));
	}

	private static function table_exists($table) {
		global $wpdb;

		$table = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table);
		if ($table === '') {
			return false;
		}

		return (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
	}

	private static function get_columns_for_table($table) {
		global $wpdb;

		if (!self::table_exists($table)) {
			return [];
		}

		$columns = $wpdb->get_col("DESC {$table}", 0); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- discovered sanitized table name.
		return is_array($columns) ? array_map('sanitize_key', $columns) : [];
	}

	private function sync_single_row($row, $dry_run) {
		$email = sanitize_email($this->row_value($row, ['user_email', 'email']));
		if (!$email || !is_email($email)) {
			return new WP_Error('invalid_email', 'A valid user_email/email is required.');
		}

		$level_id = $this->resolve_membership_level_id($row);
		if ($level_id <= 0) {
			return new WP_Error('missing_level', 'Could not resolve membership_id or membership_level to a PMPro level.');
		}

		$user = $this->resolve_user($row);
		$action = $user instanceof WP_User ? 'updated' : 'created';
		$user_login = $user instanceof WP_User ? $user->user_login : $this->make_unique_login($row, $email);

		if ($dry_run) {
			return [
				'user_id' => $user instanceof WP_User ? (int) $user->ID : 0,
				'action' => 'dry_run_' . $action,
			];
		}

		$user_id = $this->create_or_update_user($row, $user, $user_login, $email);
		if (is_wp_error($user_id)) {
			return $user_id;
		}

		$this->update_user_meta_from_row((int) $user_id, $row);
		$membership_result = $this->apply_pmpro_membership((int) $user_id, $row, $level_id);
		if (is_wp_error($membership_result)) {
			return $membership_result;
		}

		if (class_exists('AAC_Member_Portal_Member_Database')) {
			$member_database = new AAC_Member_Portal_Member_Database();
			$member_database->sync_member((int) $user_id);
		}

		return [
			'user_id' => (int) $user_id,
			'action' => $action,
		];
	}

	private function create_or_update_user($row, $user, $user_login, $email) {
		$first_name = sanitize_text_field($this->row_value($row, ['first_name', 'firstname', 'billing_first_name']));
		$last_name = sanitize_text_field($this->row_value($row, ['last_name', 'lastname', 'billing_last_name']));
		$display_name = sanitize_text_field($this->row_value($row, ['display_name', 'name']));
		if ($display_name === '') {
			$display_name = trim($first_name . ' ' . $last_name);
		}
		if ($display_name === '') {
			$display_name = $email;
		}

		$user_data = [
			'user_login' => $user_login,
			'user_email' => $email,
			'first_name' => $first_name,
			'last_name' => $last_name,
			'display_name' => $display_name,
			'role' => sanitize_key($this->row_value($row, ['role'])) ?: 'subscriber',
		];

		if ($user instanceof WP_User) {
			$user_data['ID'] = (int) $user->ID;
			return wp_update_user($user_data);
		}

		$password = (string) $this->row_value($row, ['user_pass', 'password']);
		$user_data['user_pass'] = $password !== '' ? $password : wp_generate_password(20, true, true);
		return wp_insert_user($user_data);
	}

	private function update_user_meta_from_row($user_id, $row) {
		$meta_map = [
			'aac_member_id' => ['aac_member_id', 'member_id', 'membership_number'],
			'aac_member_since_year' => ['aac_member_since_year', 'member_since_year', 'member_since'],
			'pmpro_sfirstname' => ['pmpro_sfirstname', 'sfirstname', 'first_name'],
			'pmpro_slastname' => ['pmpro_slastname', 'slastname', 'last_name'],
			'pmpro_stripe_customerid' => ['pmpro_stripe_customerid', 'stripe_customer_id', 'customer_id'],
			'aac_imported_stripe_subscription_id' => ['membership_subscription_transaction_id', 'stripe_subscription_id', 'subscription_id'],
			'aac_imported_payment_transaction_id' => ['membership_payment_transaction_id', 'stripe_payment_transaction_id', 'payment_transaction_id'],
			'aac_discount_type' => ['aac_discount_type', 'discount_type'],
			'aac_student_university' => ['aac_student_university', 'university_school', 'school', 'university'],
			'aac_student_graduation_date' => ['aac_student_graduation_date', 'graduation_date'],
			'aac_service_component' => ['aac_service_component', 'service_component'],
			'tshirt_size' => ['tshirt_size', 't_shirt_size', 'shirt_size'],
			'aac_publication_aaj' => ['aac_publication_aaj', 'aaj_preference'],
			'aac_publication_accidents' => ['aac_publication_accidents', 'accidents_preference'],
			'aac_publication_guidebook' => ['aac_publication_guidebook', 'guidebook_preference'],
			'aac_publication_acj' => ['aac_publication_acj', 'acj_preference'],
				'pmpro_sphone' => ['pmpro_sphone', 'sphone', 'bphone', 'phone', 'phone_number'],
				'pmpro_saddress1' => ['pmpro_saddress1', 'saddress1', 'baddress1', 'address1', 'address'],
				'pmpro_saddress2' => ['pmpro_saddress2', 'saddress2', 'baddress2', 'address2'],
				'pmpro_scity' => ['pmpro_scity', 'scity', 'bcity', 'city'],
				'pmpro_sstate' => ['pmpro_sstate', 'sstate', 'bstate', 'state'],
				'pmpro_szipcode' => ['pmpro_szipcode', 'szipcode', 'bzipcode', 'zip', 'zipcode', 'postal_code'],
				'pmpro_scountry' => ['pmpro_scountry', 'scountry', 'bcountry', 'country'],
			'birthdate' => ['birthdate', 'birthday', 'date_of_birth'],
			'aac_emergency_contact_name' => ['aac_emergency_contact_name', 'emergency_contact_name'],
			'aac_emergency_contact_phone' => ['aac_emergency_contact_phone', 'emergency_contact_phone'],
			'aac_family_account_role' => ['aac_family_account_role', 'family_role', 'row_type'],
			'aac_linked_parent_member_id' => ['aac_linked_parent_member_id', 'parent_member_id', 'parent_aac_member_id'],
			'aac_linked_parent_email' => ['aac_linked_parent_email', 'parent_email', 'parent_user_email'],
			'aac_linked_parent_user_login' => ['aac_linked_parent_user_login', 'parent_user_login', 'parent_login'],
		];

		foreach ($meta_map as $meta_key => $keys) {
			$value = $this->row_value($row, $keys);
			if ($value === '') {
				continue;
			}
			update_user_meta($user_id, $meta_key, sanitize_text_field($value));
		}

		update_user_meta($user_id, 'aac_import_source', 'member_import_manager');
		update_user_meta($user_id, 'aac_imported_member_row', wp_json_encode($row));
		update_user_meta($user_id, 'aac_imported_at', current_time('mysql'));
	}

	private function apply_pmpro_membership($user_id, $row, $level_id) {
		if (!function_exists('pmpro_changeMembershipLevel')) {
			return new WP_Error('pmpro_missing', 'PMPro is not active, so the membership cannot be applied.');
		}

		$startdate = $this->normalize_date($this->row_value($row, ['membership_startdate', 'start_date', 'startdate']));
		$enddate = $this->normalize_date($this->row_value($row, ['membership_enddate', 'end_date', 'enddate', 'expiration_date']));
		$level = [
			'user_id' => $user_id,
			'membership_id' => $level_id,
			'code_id' => absint($this->row_value($row, ['membership_code_id', 'code_id'])),
			'initial_payment' => $this->money_value($this->row_value($row, ['membership_initial_payment', 'initial_payment'])),
			'billing_amount' => $this->money_value($this->row_value($row, ['membership_billing_amount', 'billing_amount'])),
			'cycle_number' => absint($this->row_value($row, ['membership_cycle_number', 'cycle_number'])) ?: 1,
			'cycle_period' => sanitize_text_field($this->row_value($row, ['membership_cycle_period', 'cycle_period'])) ?: 'Year',
			'billing_limit' => absint($this->row_value($row, ['membership_billing_limit', 'billing_limit'])),
			'trial_amount' => $this->money_value($this->row_value($row, ['membership_trial_amount', 'trial_amount'])),
			'trial_limit' => absint($this->row_value($row, ['membership_trial_limit', 'trial_limit'])),
			'startdate' => $startdate,
			'enddate' => $enddate,
		];

		$changed = pmpro_changeMembershipLevel($level, $user_id, true);
		if (!$changed) {
			return new WP_Error('pmpro_level_failed', 'PMPro did not accept the membership level change.');
		}

		$this->update_latest_pmpro_membership_row($user_id, $row);
		return true;
	}

	private function update_latest_pmpro_membership_row($user_id, $row) {
		global $wpdb;

		if (!$wpdb || empty($wpdb->pmpro_memberships_users)) {
			return;
		}

		$table = $wpdb->pmpro_memberships_users;
		$id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT 1", $user_id)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		if ($id <= 0) {
			return;
		}

		$columns = $this->get_table_columns($table);
		$updates = [];
		$field_map = [
			'subscription_transaction_id' => ['membership_subscription_transaction_id', 'stripe_subscription_id', 'subscription_id'],
			'payment_transaction_id' => ['membership_payment_transaction_id', 'stripe_payment_transaction_id', 'payment_transaction_id'],
			'gateway' => ['membership_gateway', 'gateway'],
			'gateway_environment' => ['gateway_environment', 'membership_gateway_environment'],
			'status' => ['membership_status', 'status'],
		];

		foreach ($field_map as $column => $keys) {
			if (!in_array($column, $columns, true)) {
				continue;
			}
			$value = $this->row_value($row, $keys);
			if ($value !== '') {
				$updates[$column] = sanitize_text_field($value);
			}
		}

		if (!empty($updates)) {
			$wpdb->update($table, $updates, ['id' => $id]);
		}
	}

	private function import_family_links($rows) {
		if (!class_exists('AAC_Member_Portal_Group_Accounts')) {
			return ['children_linked' => 0, 'rows_skipped' => count($rows)];
		}

		$temp = wp_tempnam('aac-family-links.csv');
		if (!$temp) {
			return ['children_linked' => 0, 'rows_skipped' => count($rows)];
		}

		$handle = fopen($temp, 'w');
		fputcsv($handle, ['child_user_id', 'child_email', 'child_member_id', 'parent_email', 'parent_member_id', 'parent_user_login', 'account_type', 'slot_id', 'invite_code']);
		foreach ($rows as $row) {
			fputcsv($handle, [
				$row['child_user_id'],
				$row['child_email'],
				$row['child_member_id'],
				$row['parent_email'],
				$row['parent_member_id'],
				$row['parent_user_login'],
				$row['account_type'],
				$row['slot_id'],
				$row['invite_code'],
			]);
		}
		fclose($handle);

		$result = AAC_Member_Portal_Group_Accounts::import_family_group_links_csv($temp);
		wp_delete_file($temp);

		if (is_wp_error($result)) {
			return ['children_linked' => 0, 'rows_skipped' => count($rows)];
		}

		return is_array($result) ? $result : ['children_linked' => 0, 'rows_skipped' => 0];
	}

	private function prepare_row_summary($row) {
		$email = sanitize_email($this->row_value($row, ['user_email', 'email']));
		$membership_id = $this->resolve_membership_level_id($row);
		$row_type = sanitize_key($this->row_value($row, ['row_type', 'family_role', 'aac_family_account_role']));
		$errors = [];

		if (!$email || !is_email($email)) {
			$errors[] = 'Missing or invalid user_email/email.';
		}
		if ($membership_id <= 0) {
			$errors[] = 'Missing or unrecognized membership_id/membership_level.';
		}
		if ($this->is_family_child_row($row) && !$this->has_parent_identifier($row)) {
			$errors[] = 'Family child row is missing parent_member_id, parent_email, or parent_user_login.';
		}

		return [
			'row_type' => $row_type,
			'status' => empty($errors) ? 'staged' : 'error',
			'aac_member_id' => sanitize_text_field($this->row_value($row, ['aac_member_id', 'member_id', 'membership_number'])),
			'email' => $email,
			'user_login' => sanitize_user($this->row_value($row, ['user_login', 'username'])),
			'membership_id' => $membership_id,
			'membership_level' => sanitize_text_field($this->row_value($row, ['membership_level', 'level_name'])),
			'parent_aac_member_id' => sanitize_text_field($this->row_value($row, ['parent_member_id', 'parent_aac_member_id', 'aac_linked_parent_member_id'])),
			'parent_email' => sanitize_email($this->row_value($row, ['parent_email', 'parent_user_email', 'aac_linked_parent_email'])),
			'parent_user_login' => sanitize_user($this->row_value($row, ['parent_user_login', 'parent_login', 'aac_linked_parent_user_login'])),
			'error_message' => implode(' ', $errors),
		];
	}

	private function build_family_link_row($row, $user_id) {
		return [
			'child_user_id' => $user_id,
			'child_email' => sanitize_email($this->row_value($row, ['user_email', 'email'])),
			'child_member_id' => sanitize_text_field($this->row_value($row, ['aac_member_id', 'member_id', 'membership_number'])),
			'parent_email' => sanitize_email($this->row_value($row, ['parent_email', 'parent_user_email', 'aac_linked_parent_email'])),
			'parent_member_id' => sanitize_text_field($this->row_value($row, ['parent_member_id', 'parent_aac_member_id', 'aac_linked_parent_member_id'])),
			'parent_user_login' => sanitize_user($this->row_value($row, ['parent_user_login', 'parent_login', 'aac_linked_parent_user_login'])),
			'account_type' => sanitize_key($this->row_value($row, ['account_type', 'family_role', 'row_type'])) ?: 'dependent',
			'slot_id' => sanitize_key($this->row_value($row, ['slot_id', 'seat_id'])) ?: 'imported-' . $user_id,
			'invite_code' => sanitize_text_field($this->row_value($row, ['invite_code', 'family_invite_code'])),
		];
	}

	private function resolve_user($row) {
		$member_id = $this->row_value($row, ['aac_member_id', 'member_id', 'membership_number']);
		if ($member_id !== '') {
			$users = get_users([
				'meta_key' => 'aac_member_id',
				'meta_value' => $member_id,
				'number' => 1,
				'fields' => 'all',
			]);
			$user = is_array($users) ? reset($users) : null;
			if ($user instanceof WP_User) {
				return $user;
			}
		}

		$email = sanitize_email($this->row_value($row, ['user_email', 'email']));
		if ($email) {
			$user = get_user_by('email', $email);
			if ($user instanceof WP_User) {
				return $user;
			}
		}

		$login = sanitize_user($this->row_value($row, ['user_login', 'username']));
		return $login ? get_user_by('login', $login) : null;
	}

	private function resolve_membership_level_id($row) {
		global $wpdb;

		$id = absint($this->row_value($row, ['membership_id', 'level_id']));
		if ($id > 0) {
			return $id;
		}

		$name = sanitize_text_field($this->row_value($row, ['membership_level', 'level_name']));
		if ($name === '' || !$wpdb) {
			return 0;
		}

		$table = $wpdb->prefix . 'pmpro_membership_levels';
		$level_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE name = %s LIMIT 1", $name)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- PMPro table name.
		return $level_id > 0 ? $level_id : 0;
	}

	private function make_unique_login($row, $email) {
		$login = sanitize_user($this->row_value($row, ['user_login', 'username']), true);
		if ($login === '') {
			$member_id = preg_replace('/[^A-Za-z0-9_]+/', '', $this->row_value($row, ['aac_member_id', 'member_id', 'membership_number']));
			$email_parts = explode('@', $email);
			$login = $member_id ? 'member' . $member_id : sanitize_user((string) reset($email_parts), true);
		}
		if ($login === '') {
			$login = 'member';
		}

		$base = $login;
		$suffix = 2;
		while (username_exists($login)) {
			$login = $base . $suffix;
			$suffix++;
		}
		return $login;
	}

	private function is_family_child_row($row) {
		$row_type = strtolower($this->row_value($row, ['row_type', 'family_role', 'aac_family_account_role', 'account_type']));
		$level_name = strtolower($this->row_value($row, ['membership_level', 'level_name']));
		return in_array($row_type, ['child', 'dependent', 'adult'], true)
			|| strpos($level_name, 'dependent') !== false
			|| (strpos($level_name, 'adult') !== false && $this->has_parent_identifier($row));
	}

	private function has_parent_identifier($row) {
		return $this->row_value($row, ['parent_member_id', 'parent_aac_member_id', 'aac_linked_parent_member_id', 'parent_email', 'parent_user_email', 'aac_linked_parent_email', 'parent_user_login', 'parent_login', 'aac_linked_parent_user_login']) !== '';
	}

	private function row_value($row, $keys) {
		foreach ((array) $keys as $key) {
			$key = self::normalize_header($key);
			if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
				return trim((string) $row[$key]);
			}
		}
		return '';
	}

	private static function normalize_header($header) {
		$header = strtolower(trim((string) $header));
		$header = preg_replace('/[^a-z0-9_]+/', '_', $header);
		$header = preg_replace('/_+/', '_', (string) $header);
		return trim((string) $header, '_');
	}

	private function normalize_date($value) {
		$value = trim((string) $value);
		if ($value === '') {
			return '';
		}
		$timestamp = strtotime($value);
		return $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : '';
	}

	private function money_value($value) {
		$value = preg_replace('/[^0-9.\-]+/', '', (string) $value);
		return $value === '' ? 0 : (float) $value;
	}

	private function get_table_columns($table) {
		global $wpdb;
		$columns = $wpdb->get_col("DESC {$table}", 0); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- trusted table name.
		return is_array($columns) ? $columns : [];
	}

	private function filter_data_to_columns($data, $columns) {
		$filtered = [];
		foreach ((array) $data as $column => $value) {
			if (in_array($column, (array) $columns, true)) {
				$filtered[$column] = $value;
			}
		}
		return $filtered;
	}

	private function get_requested_batch_limit() {
		$requested = isset($_POST['import_batch_size']) ? absint(wp_unslash($_POST['import_batch_size'])) : self::DEFAULT_SYNC_LIMIT;
		return $this->normalize_batch_limit($requested);
	}

	private function normalize_batch_limit($limit) {
		$limit = absint($limit);
		if ($limit <= 0) {
			$limit = self::DEFAULT_SYNC_LIMIT;
		}

		return min($limit, self::MAX_SYNC_LIMIT);
	}

	private function count_remaining_rows($dry_run = false) {
		global $wpdb;

		$table = self::table_name();
		$status_clause = $dry_run ? "status = 'staged'" : "status IN ('staged', 'dry_run')";
		return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$status_clause}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin-owned table and fixed status clause.
	}

	private function mark_row_error($id, $message) {
		global $wpdb;
		$wpdb->update(
			self::table_name(),
			[
				'status' => 'error',
				'error_message' => sanitize_text_field($message),
				'processed_at' => current_time('mysql'),
				'updated_at' => current_time('mysql'),
			],
			['id' => $id],
			['%s', '%s', '%s', '%s'],
			['%d']
		);
	}

	private function get_stats() {
		global $wpdb;
		$table = self::table_name();
		$counts = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table.
		$map = [
			'Staged' => 0,
			'Dry Run' => 0,
			'Synced' => 0,
			'Errors' => 0,
		];
		foreach ((array) $counts as $row) {
			$status = (string) $row['status'];
			if ($status === 'staged') {
				$map['Staged'] = (int) $row['total'];
			} elseif ($status === 'dry_run') {
				$map['Dry Run'] = (int) $row['total'];
			} elseif ($status === 'synced') {
				$map['Synced'] = (int) $row['total'];
			} elseif ($status === 'error') {
				$map['Errors'] = (int) $row['total'];
			}
		}
		return $map;
	}

	private function get_recent_rows() {
		global $wpdb;
		$rows = $wpdb->get_results("SELECT * FROM " . self::table_name() . ' ORDER BY id DESC LIMIT 75', ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table.
		return is_array($rows) ? $rows : [];
	}

	private function get_admin_notice() {
		if (empty($_GET['aac_import_notice'])) {
			return null;
		}
		$type = sanitize_key((string) ($_GET['aac_import_type'] ?? 'success'));
		return [
			'type' => in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'success',
			'message' => sanitize_text_field(wp_unslash((string) $_GET['aac_import_notice'])),
		];
	}

	private function redirect_with_notice($type, $message) {
		wp_safe_redirect(add_query_arg([
			'page' => self::PAGE_SLUG,
			'aac_import_type' => sanitize_key($type),
			'aac_import_notice' => rawurlencode((string) $message),
		], admin_url('admin.php')));
		exit;
	}

	private function require_admin_post($nonce_action) {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to perform this action.', 'aac-member-portal'));
		}
		check_admin_referer($nonce_action);
	}

	private static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'aac_member_portal_import_rows';
	}
}
