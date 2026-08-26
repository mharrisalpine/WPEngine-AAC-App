<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Member_Portal_Member_Database {
	const PAGE_SLUG = 'aac-member-portal-member-database';
	const EXPORT_PAGE_SLUG = 'aac-member-portal-member-database-export';
	const SCHEMA_VERSION = '1.0.4';
	const SCHEMA_OPTION = 'aac_member_portal_member_db_schema_version';
	const EXPORT_FIELDS_OPTION = 'aac_member_portal_member_database_export_fields';

	public function __construct() {
		add_action('admin_menu', [$this, 'register_admin_page']);
		add_action('init', [$this, 'maybe_install_schema']);
		add_action('admin_post_aac_member_portal_export_member_database', [$this, 'handle_export_member_database']);
		add_action('admin_post_aac_member_portal_save_member_export_fields', [$this, 'handle_save_member_export_fields']);
		add_action('profile_update', [$this, 'sync_member_by_user_id'], 30, 1);
		add_action('personal_options_update', [$this, 'sync_member_by_user_id'], 100, 1);
		add_action('edit_user_profile_update', [$this, 'sync_member_by_user_id'], 100, 1);
		add_action('aac_member_portal_member_registered', [$this, 'sync_member_by_user_id'], 30, 1);
		add_action('aac_member_portal_profile_updated', [$this, 'sync_member_by_user_id'], 30, 1);
		add_action('pmpro_after_checkout', [$this, 'sync_member_after_checkout'], 40, 2);
		add_action('pmpro_after_change_membership_level', [$this, 'sync_member_after_level_change'], 40, 2);
		add_action('deleted_user', [$this, 'delete_member_by_user_id'], 30, 1);
		add_action('admin_init', [$this, 'prune_orphaned_members']);
	}

	public function delete_member_by_user_id($user_id) {
		global $wpdb;

		$user_id = absint($user_id);
		if (!$wpdb || !$user_id) {
			return;
		}

		foreach ([self::history_table(), self::subscriptions_table(), self::transactions_table(), self::profiles_table()] as $table) {
			$wpdb->delete($table, ['user_id' => $user_id], ['%d']);
		}
	}

	public function prune_orphaned_members() {
		global $wpdb;

		if (!$wpdb || !is_admin() || !current_user_can('manage_options')) {
			return;
		}

		$profiles = self::profiles_table();
		$users = $wpdb->users;
		$orphaned_user_ids = $wpdb->get_col(
			"SELECT profiles.user_id FROM {$profiles} profiles LEFT JOIN {$users} users ON users.ID = profiles.user_id WHERE users.ID IS NULL"
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin-owned table names.

		foreach ((array) $orphaned_user_ids as $user_id) {
			$this->delete_member_by_user_id($user_id);
		}
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
		$charset_collate = $wpdb->get_charset_collate();
		$profiles = self::profiles_table();
		$history = self::history_table();
		$subscriptions = self::subscriptions_table();
		$transactions = self::transactions_table();

		// Profiles keeps one flattened "what does this member look like right now?"
		// snapshot. The other tables keep mirrored PMPro rows for the moments when
		// staff need receipts, history, and answers.
		dbDelta("
			CREATE TABLE {$profiles} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				parent_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				account_role varchar(32) NOT NULL DEFAULT '',
				email varchar(190) NOT NULL DEFAULT '',
				display_name varchar(190) NOT NULL DEFAULT '',
				member_id varchar(190) NOT NULL DEFAULT '',
				membership_level varchar(100) NOT NULL DEFAULT '',
				membership_status varchar(100) NOT NULL DEFAULT '',
				renewal_date varchar(20) NOT NULL DEFAULT '',
				expiration_date varchar(20) NOT NULL DEFAULT '',
				raw_profile longtext NULL,
				mirrored_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_id (user_id),
				KEY membership_level (membership_level),
				KEY account_role (account_role)
			) {$charset_collate};
		");

		dbDelta("
			CREATE TABLE {$history} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				source_record_id bigint(20) unsigned NOT NULL DEFAULT 0,
				source_status varchar(100) NOT NULL DEFAULT '',
				source_date varchar(32) NOT NULL DEFAULT '',
				raw_record longtext NULL,
				mirrored_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_source (user_id, source_record_id),
				KEY source_status (source_status)
			) {$charset_collate};
		");

		dbDelta("
			CREATE TABLE {$subscriptions} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				source_record_id bigint(20) unsigned NOT NULL DEFAULT 0,
				source_status varchar(100) NOT NULL DEFAULT '',
				source_date varchar(32) NOT NULL DEFAULT '',
				raw_record longtext NULL,
				mirrored_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_source (user_id, source_record_id),
				KEY source_status (source_status)
			) {$charset_collate};
		");

		dbDelta("
			CREATE TABLE {$transactions} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				source_record_id bigint(20) unsigned NOT NULL DEFAULT 0,
				source_status varchar(100) NOT NULL DEFAULT '',
				source_date varchar(32) NOT NULL DEFAULT '',
				raw_record longtext NULL,
				mirrored_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_source (user_id, source_record_id),
				KEY source_status (source_status)
			) {$charset_collate};
		");

		update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION);
	}

	public function register_admin_page() {
		add_submenu_page(
			AAC_Member_Portal_Admin::MENU_SLUG,
			'Member Database',
			'Member Database',
			'manage_options',
			self::PAGE_SLUG,
			[$this, 'render_admin_page']
		);

		add_submenu_page(
			null,
			'Member Database Export',
			'Member Database Export',
			'manage_options',
			self::EXPORT_PAGE_SLUG,
			[$this, 'render_export_page']
		);
	}

	public static function render_database_tools_nav($active_slug = self::PAGE_SLUG) {
		$tools = [
			self::PAGE_SLUG => [
				'label' => 'Member Database',
				'url' => admin_url('admin.php?page=' . self::PAGE_SLUG),
			],
			self::EXPORT_PAGE_SLUG => [
				'label' => 'Member Database Export',
				'url' => admin_url('admin.php?page=' . self::EXPORT_PAGE_SLUG),
			],
		];

		if (class_exists('AAC_Member_Portal_Daily_Member_Export')) {
			$tools[AAC_Member_Portal_Daily_Member_Export::MENU_SLUG] = [
				'label' => 'Daily Member Export',
				'url' => admin_url('admin.php?page=' . AAC_Member_Portal_Daily_Member_Export::MENU_SLUG),
			];
		}

		if (class_exists('AAC_Member_Portal_Import_Manager')) {
			$tools[AAC_Member_Portal_Import_Manager::PAGE_SLUG] = [
				'label' => 'Member Import Manager',
				'url' => admin_url('admin.php?page=' . AAC_Member_Portal_Import_Manager::PAGE_SLUG),
			];
		}
		?>
		<nav class="nav-tab-wrapper" style="margin:16px 0 22px;">
			<?php foreach ($tools as $slug => $tool) : ?>
				<a class="nav-tab <?php echo $active_slug === $slug ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($tool['url']); ?>">
					<?php echo esc_html($tool['label']); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	public function sync_member_by_user_id($user_id) {
		$this->sync_member((int) $user_id);
	}

	public function sync_member_after_checkout($user_id, $morder = null) {
		$this->sync_member((int) $user_id);
	}

	public function sync_member_after_level_change($level_id, $user_id = 0) {
		$this->sync_member((int) $user_id);
	}

	public function sync_member($user_id) {
		global $wpdb;

		$user_id = (int) $user_id;
		if ($user_id <= 0 || !$wpdb) {
			return false;
		}

		$user = get_user_by('id', $user_id);
		if (!$user instanceof WP_User) {
			return false;
		}

		// We build the mirror from the same payload the frontend uses on purpose.
		// That keeps the admin view and the member-facing view from telling two
		// different stories about the same person.
		$api = AAC_Member_Portal_API::get_instance();
		$profile = $api instanceof AAC_Member_Portal_API ? $api->get_profile_for_user($user_id) : [];
		$account_info = is_array($profile['account_info'] ?? null) ? $profile['account_info'] : [];
		$profile_info = is_array($profile['profile_info'] ?? null) ? $profile['profile_info'] : [];
		$linked_parent = is_array($profile['linked_parent_account'] ?? null) ? $profile['linked_parent_account'] : [];
		$profile['pmpro_membership'] = $this->get_latest_pmpro_row($user_id, 'pmpro_memberships_users');
		$profile['pmpro_subscription'] = $this->get_latest_pmpro_row($user_id, 'pmpro_subscriptions');
		$profile['pmpro_transaction'] = $this->get_latest_pmpro_row($user_id, 'pmpro_membership_orders');
		$account_role = sanitize_text_field((string) get_user_meta($user_id, 'aac_family_account_role', true));
		$parent_user_id = absint(get_user_meta($user_id, 'aac_linked_parent_user_id', true));

		if ($parent_user_id <= 0 && !empty($linked_parent['user_id'])) {
			$parent_user_id = (int) $linked_parent['user_id'];
		}

		$mirrored_at = current_time('mysql');
		$wpdb->replace(
			self::profiles_table(),
			[
				'user_id' => $user_id,
				'parent_user_id' => $parent_user_id,
				'account_role' => $account_role,
				'email' => sanitize_email($account_info['email'] ?? $user->user_email),
				'display_name' => sanitize_text_field($account_info['name'] ?? $user->display_name),
				'member_id' => sanitize_text_field($profile_info['member_id'] ?? ''),
				'membership_level' => sanitize_text_field($profile_info['tier'] ?? ''),
				'membership_status' => sanitize_text_field($profile_info['status'] ?? ''),
				'renewal_date' => sanitize_text_field($profile_info['renewal_date'] ?? ''),
				'expiration_date' => sanitize_text_field($profile_info['expiration_date'] ?? ''),
				'raw_profile' => wp_json_encode($profile),
				'mirrored_at' => $mirrored_at,
			],
			['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
		);

		$this->mirror_pmpro_rows($user_id, 'pmpro_memberships_users', self::history_table(), 'status', ['startdate', 'modified', 'date']);
		$this->mirror_pmpro_rows($user_id, 'pmpro_subscriptions', self::subscriptions_table(), 'status', ['next_payment_date', 'cycle_enddate', 'startdate', 'modified']);
		$this->mirror_pmpro_rows($user_id, 'pmpro_membership_orders', self::transactions_table(), 'status', ['timestamp']);

		return true;
	}

	private function get_latest_pmpro_row($user_id, $wpdb_property) {
		global $wpdb;

		if (!$wpdb || empty($wpdb->{$wpdb_property})) {
			return [];
		}

		$row = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$wpdb->{$wpdb_property}} WHERE user_id = %d ORDER BY id DESC LIMIT 1", $user_id),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.

		return is_array($row) ? $row : [];
	}

	private function mirror_pmpro_rows($user_id, $wpdb_property, $mirror_table, $status_column = 'status', $date_candidates = []) {
		global $wpdb;

		if (!$wpdb || empty($wpdb->{$wpdb_property})) {
			$wpdb->delete($mirror_table, ['user_id' => $user_id], ['%d']);
			return;
		}

		$source_table = $wpdb->{$wpdb_property};
		// We wipe and rebuild the mirrored rows for a user on each sync. It is not
		// the fanciest move in the world, but it is wonderfully hard to lie to and
		// keeps stale PMPro rows from haunting the reports.
		$rows = $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$source_table} WHERE user_id = %d ORDER BY id DESC", $user_id),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.

		$wpdb->delete($mirror_table, ['user_id' => $user_id], ['%d']);

		if (!is_array($rows) || !$rows) {
			return;
		}

		$mirrored_at = current_time('mysql');
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$source_record_id = absint($row['id'] ?? 0);
			$source_status = sanitize_text_field((string) ($row[$status_column] ?? ''));
			$source_date = '';
			foreach ($date_candidates as $candidate) {
				if (!empty($row[$candidate])) {
					$source_date = sanitize_text_field((string) $row[$candidate]);
					break;
				}
			}

			$wpdb->insert(
				$mirror_table,
				[
					'user_id' => $user_id,
					'source_record_id' => $source_record_id,
					'source_status' => $source_status,
					'source_date' => $source_date,
					'raw_record' => wp_json_encode($row),
					'mirrored_at' => $mirrored_at,
				],
				['%d', '%d', '%s', '%s', '%s', '%s']
			);
		}
	}

	public function handle_export_member_database() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to export the member database.', 'aac-member-portal'));
		}

		check_admin_referer('aac_member_portal_export_member_database');

		$user_ids = $this->get_member_export_user_ids();
		foreach ($user_ids as $user_id) {
			$this->sync_member((int) $user_id);
		}

		$filename = 'aac-member-database-export-' . gmdate('Y-m-d-His') . '.csv';
		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');

		$output = fopen('php://output', 'w');
		if (!$output) {
			exit;
		}

		$headers = $this->get_selected_member_export_headers();
		fputcsv($output, $headers);
		foreach ($user_ids as $user_id) {
			$row = $this->build_member_export_row((int) $user_id);
			fputcsv($output, array_map(function ($header) use ($row) {
				return $row[$header] ?? '';
			}, $headers));
		}

		fclose($output);
		exit;
	}

	public function handle_save_member_export_fields() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to change member export fields.', 'aac-member-portal'));
		}

		check_admin_referer('aac_member_portal_save_member_export_fields');

		$all_headers = $this->get_member_export_headers();
		$posted_fields = isset($_POST['aac_member_export_fields']) ? (array) wp_unslash($_POST['aac_member_export_fields']) : [];
		$posted_fields = array_map('sanitize_text_field', $posted_fields);
		$selected_fields = array_values(array_intersect($all_headers, $posted_fields));

		if (empty($selected_fields)) {
			delete_option(self::EXPORT_FIELDS_OPTION);
			$redirect_status = 'all';
		} else {
			update_option(self::EXPORT_FIELDS_OPTION, $selected_fields, false);
			$redirect_status = 'saved';
		}

		wp_safe_redirect(add_query_arg('aac_export_fields_saved', $redirect_status, $this->build_export_admin_url()));
		exit;
	}

	public function render_export_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$export_url = wp_nonce_url(
			add_query_arg(['action' => 'aac_member_portal_export_member_database'], admin_url('admin-post.php')),
			'aac_member_portal_export_member_database'
		);
		$all_headers = $this->get_member_export_headers();
		$selected_headers = $this->get_selected_member_export_headers();
		$field_groups = $this->get_member_export_field_groups();
		$field_labels = $this->get_member_export_field_labels();
		$selected_lookup = array_fill_keys($selected_headers, true);
		$selected_count = count($selected_headers);
		$total_count = count($all_headers);
		$save_status = isset($_GET['aac_export_fields_saved']) ? sanitize_key(wp_unslash($_GET['aac_export_fields_saved'])) : '';
		?>
		<div class="wrap">
			<h1>Member Database Export</h1>
			<?php self::render_database_tools_nav(self::EXPORT_PAGE_SLUG); ?>
			<?php if ('saved' === $save_status) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Member export field selection saved.', 'aac-member-portal'); ?></p>
				</div>
			<?php elseif ('all' === $save_status) : ?>
				<div class="notice notice-info is-dismissible">
					<p><?php esc_html_e('No fields were selected, so the export was reset to include all fields.', 'aac-member-portal'); ?></p>
				</div>
			<?php endif; ?>
			<p>
				Choose which member fields should be included in the CSV. Your saved selection controls the export button on this page
				and the quick export button on the Member Database page.
			</p>
			<div style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:20px;max-width:920px;margin-top:18px;">
				<h2 style="margin-top:0;">Selected Member CSV</h2>
				<p>
					The next export will include <strong><?php echo esc_html((string) $selected_count); ?></strong> of
					<strong><?php echo esc_html((string) $total_count); ?></strong> available fields. The export syncs the member
					database mirror before download and streams the CSV directly to your browser. It includes PII, so store and share
					the file carefully.
				</p>
				<p style="margin-top:18px;">
					<a class="button button-primary button-hero" href="<?php echo esc_url($export_url); ?>">
						Export Selected Fields CSV
					</a>
					<a class="button button-secondary button-hero" href="<?php echo esc_url($this->build_admin_url()); ?>" style="margin-left:8px;">
						Open Member Database
					</a>
				</p>
			</div>

			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:1180px;margin-top:18px;">
				<?php wp_nonce_field('aac_member_portal_save_member_export_fields'); ?>
				<input type="hidden" name="action" value="aac_member_portal_save_member_export_fields" />
				<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0 0 14px;">
					<button type="submit" class="button button-primary">Save Field Selection</button>
					<button type="button" class="button" data-aac-export-fields="all">Select All</button>
					<button type="button" class="button" data-aac-export-fields="none">Clear All</button>
				</div>
				<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;">
					<?php foreach ($field_groups as $group_label => $group_fields) : ?>
						<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px;">
							<h2 style="font-size:16px;margin:0 0 10px;"><?php echo esc_html($group_label); ?></h2>
							<?php foreach ($group_fields as $field_key) : ?>
								<?php if (!in_array($field_key, $all_headers, true)) : ?>
									<?php continue; ?>
								<?php endif; ?>
								<label style="display:block;margin:8px 0;line-height:1.35;">
									<input
										type="checkbox"
										name="aac_member_export_fields[]"
										value="<?php echo esc_attr($field_key); ?>"
										<?php checked(isset($selected_lookup[$field_key])); ?>
									/>
									<span><?php echo esc_html($field_labels[$field_key] ?? $this->format_member_export_field_label($field_key)); ?></span>
									<code style="display:block;margin:2px 0 0 24px;color:#646970;"><?php echo esc_html($field_key); ?></code>
								</label>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</div>
				<p style="margin-top:16px;">
					<button type="submit" class="button button-primary">Save Field Selection</button>
				</p>
			</form>
			<script>
				document.addEventListener('click', function(event) {
					var action = event.target && event.target.getAttribute('data-aac-export-fields');
					if (!action) {
						return;
					}
					document.querySelectorAll('input[name="aac_member_export_fields[]"]').forEach(function(input) {
						input.checked = action === 'all';
					});
				});
			</script>
		</div>
		<?php
	}

	private function get_selected_member_export_headers() {
		$all_headers = $this->get_member_export_headers();
		$stored_headers = get_option(self::EXPORT_FIELDS_OPTION, []);

		if (!is_array($stored_headers) || empty($stored_headers)) {
			return $all_headers;
		}

		$stored_headers = array_map('sanitize_text_field', $stored_headers);
		$selected_headers = array_values(array_intersect($all_headers, $stored_headers));

		return !empty($selected_headers) ? $selected_headers : $all_headers;
	}

	private function get_member_export_field_groups() {
		$groups = [
			'WordPress Account' => [
				'wp_user_id',
				'user_login',
				'user_email',
				'user_registered',
				'display_name',
				'roles',
			],
			'AAC Profile' => [
				'aac_member_id',
				'first_name',
				'last_name',
				'phone',
				'birthdate',
				'billing_address_1',
				'billing_address_2',
				'billing_city',
				'billing_state',
				'billing_zip',
				'billing_country',
				't_shirt_size',
				'aaj_preference',
				'accidents_preference',
				'acj_preference',
				'guidebook_preference',
				'membership_discount_type',
				'student_university',
				'student_graduation_date',
				'military_service_component',
				'emergency_contact_first_name',
				'emergency_contact_last_name',
				'emergency_contact_phone',
				'emergency_contact_email',
				'emergency_contact_relationship',
			],
			'Current Membership' => [
				'current_membership_id',
				'current_membership_level',
				'current_membership_status',
				'membership_startdate',
				'membership_enddate',
				'membership_modified',
				'member_since',
				'renewal_date',
				'expiration_date',
				'valid_through_date',
				'auto_renew',
			],
			'Subscription / Stripe' => [
				'current_subscription_id',
				'current_subscription_gateway',
				'current_subscription_environment',
				'current_subscription_transaction_id',
				'current_subscription_status',
				'current_subscription_startdate',
				'current_subscription_enddate',
				'current_subscription_next_payment_date',
				'current_subscription_billing_amount',
				'current_subscription_cycle_number',
				'current_subscription_cycle_period',
				'stripe_customer_id',
			],
			'Latest Order / Payment' => [
				'latest_order_id',
				'latest_order_code',
				'latest_order_membership_id',
				'latest_order_status',
				'latest_order_gateway',
				'latest_order_environment',
				'latest_order_payment_transaction_id',
				'latest_order_subscription_transaction_id',
				'latest_order_subtotal',
				'latest_order_tax',
				'latest_order_total',
				'latest_order_timestamp',
				'latest_order_discount_code',
			],
			'Family / Group Accounts' => [
				'account_role',
				'parent_user_id',
				'parent_member_id',
				'parent_email',
				'linked_account_slot_id',
				'family_membership_mode',
				'family_additional_adult',
				'family_dependent_count',
				'aac_group_account_group_id',
				'aac_group_account_checkout_code',
				'aac_group_account_child_level_id',
				'aac_group_account_synced_at',
				'pmpro_group_account_id',
				'pmpro_group_account_checkout_code',
				'pmpro_group_account_total_seats',
				'pmpro_group_account_active_members',
			],
			'Raw JSON / Audit' => [
				'connected_accounts_json',
				'linked_parent_account_json',
				'family_membership_json',
				'pmpro_membership_history_json',
				'pmpro_subscriptions_json',
				'pmpro_orders_json',
				'pmpro_group_accounts_json',
				'all_user_meta_json',
				'portal_profile_json',
				'mirrored_at',
			],
		];
		$grouped_fields = [];
		foreach ($groups as $fields) {
			$grouped_fields = array_merge($grouped_fields, $fields);
		}
		$ungrouped_fields = array_values(array_diff($this->get_member_export_headers(), $grouped_fields));
		if (!empty($ungrouped_fields)) {
			$groups['Other Fields'] = $ungrouped_fields;
		}

		return $groups;
	}

	private function get_member_export_field_labels() {
		return [
			'wp_user_id' => 'WordPress user ID',
			'user_login' => 'Username',
			'user_email' => 'Email address',
			'user_registered' => 'WordPress registration date',
			'display_name' => 'Display name',
			'roles' => 'WordPress roles',
			'aac_member_id' => 'AAC member ID',
			'first_name' => 'First name',
			'last_name' => 'Last name',
			'phone' => 'Phone',
			'birthdate' => 'Birthdate',
			'billing_address_1' => 'Billing address 1',
			'billing_address_2' => 'Billing address 2',
			'billing_city' => 'Billing city',
			'billing_state' => 'Billing state',
			'billing_zip' => 'Billing ZIP',
			'billing_country' => 'Billing country',
			't_shirt_size' => 'T-shirt size',
			'aaj_preference' => 'AAJ preference',
			'accidents_preference' => 'Accidents preference',
			'acj_preference' => 'ACJ preference',
			'guidebook_preference' => 'Guidebook preference',
			'membership_discount_type' => 'Membership discount type',
			'student_university' => 'Student university/school',
			'student_graduation_date' => 'Student graduation date',
			'military_service_component' => 'Military service component',
			'emergency_contact_first_name' => 'Emergency contact first name',
			'emergency_contact_last_name' => 'Emergency contact last name',
			'emergency_contact_phone' => 'Emergency contact phone',
			'emergency_contact_email' => 'Emergency contact email',
			'emergency_contact_relationship' => 'Emergency contact relationship',
			'current_membership_id' => 'Current membership level ID',
			'current_membership_level' => 'Current membership level',
			'current_membership_status' => 'Current membership status',
			'membership_startdate' => 'Membership start date',
			'membership_enddate' => 'Membership end date',
			'membership_modified' => 'Membership modified date',
			'member_since' => 'Member since',
			'renewal_date' => 'Renewal date',
			'expiration_date' => 'Expiration date',
			'valid_through_date' => 'Valid through date',
			'auto_renew' => 'Auto renewal enabled',
			'current_subscription_id' => 'Current subscription ID',
			'current_subscription_gateway' => 'Current subscription gateway',
			'current_subscription_environment' => 'Current subscription environment',
			'current_subscription_transaction_id' => 'Current subscription transaction ID',
			'current_subscription_status' => 'Current subscription status',
			'current_subscription_startdate' => 'Current subscription start date',
			'current_subscription_enddate' => 'Current subscription end date',
			'current_subscription_next_payment_date' => 'Next payment date',
			'current_subscription_billing_amount' => 'Subscription billing amount',
			'current_subscription_cycle_number' => 'Subscription cycle number',
			'current_subscription_cycle_period' => 'Subscription cycle period',
			'latest_order_id' => 'Latest order ID',
			'latest_order_code' => 'Latest order code',
			'latest_order_membership_id' => 'Latest order membership level ID',
			'latest_order_status' => 'Latest order status',
			'latest_order_gateway' => 'Latest order gateway',
			'latest_order_environment' => 'Latest order environment',
			'latest_order_payment_transaction_id' => 'Latest payment transaction ID',
			'latest_order_subscription_transaction_id' => 'Latest order subscription transaction ID',
			'latest_order_subtotal' => 'Latest order subtotal',
			'latest_order_tax' => 'Latest order tax',
			'latest_order_total' => 'Latest order total',
			'latest_order_timestamp' => 'Latest order date',
			'latest_order_discount_code' => 'Latest order discount code',
			'stripe_customer_id' => 'Stripe customer ID',
			'account_role' => 'Family account role',
			'parent_user_id' => 'Parent WordPress user ID',
			'parent_member_id' => 'Parent AAC member ID',
			'parent_email' => 'Parent email',
			'linked_account_slot_id' => 'Linked account slot ID',
			'family_membership_mode' => 'Family membership mode',
			'family_additional_adult' => 'Family additional adult',
			'family_dependent_count' => 'Family dependent count',
			'aac_group_account_group_id' => 'AAC group account group ID',
			'aac_group_account_checkout_code' => 'AAC group checkout code',
			'aac_group_account_child_level_id' => 'AAC group child level ID',
			'aac_group_account_synced_at' => 'AAC group synced at',
			'pmpro_group_account_id' => 'PMPro group account ID',
			'pmpro_group_account_checkout_code' => 'PMPro group checkout code',
			'pmpro_group_account_total_seats' => 'PMPro group total seats',
			'pmpro_group_account_active_members' => 'PMPro group active members',
			'connected_accounts_json' => 'Connected accounts JSON',
			'linked_parent_account_json' => 'Linked parent account JSON',
			'family_membership_json' => 'Family membership JSON',
			'pmpro_membership_history_json' => 'PMPro membership history JSON',
			'pmpro_subscriptions_json' => 'PMPro subscriptions JSON',
			'pmpro_orders_json' => 'PMPro orders JSON',
			'pmpro_group_accounts_json' => 'PMPro group accounts JSON',
			'all_user_meta_json' => 'All user meta JSON',
			'portal_profile_json' => 'Portal profile JSON',
			'mirrored_at' => 'Mirror updated at',
		];
	}

	private function format_member_export_field_label($field_key) {
		return ucwords(str_replace('_', ' ', (string) $field_key));
	}

	private function get_member_export_headers() {
		return [
			'wp_user_id',
			'user_login',
			'user_email',
			'user_registered',
			'display_name',
			'roles',
			'aac_member_id',
			'first_name',
			'last_name',
			'phone',
			'birthdate',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_state',
			'billing_zip',
			'billing_country',
			't_shirt_size',
			'aaj_preference',
			'accidents_preference',
			'acj_preference',
			'guidebook_preference',
			'membership_discount_type',
			'student_university',
			'student_graduation_date',
			'military_service_component',
			'emergency_contact_first_name',
			'emergency_contact_last_name',
			'emergency_contact_phone',
			'emergency_contact_email',
			'emergency_contact_relationship',
			'current_membership_id',
			'current_membership_level',
			'current_membership_status',
			'membership_startdate',
			'membership_enddate',
			'membership_modified',
			'member_since',
			'renewal_date',
			'expiration_date',
			'valid_through_date',
			'auto_renew',
			'current_subscription_id',
			'current_subscription_gateway',
			'current_subscription_environment',
			'current_subscription_transaction_id',
			'current_subscription_status',
			'current_subscription_startdate',
			'current_subscription_enddate',
			'current_subscription_next_payment_date',
			'current_subscription_billing_amount',
			'current_subscription_cycle_number',
			'current_subscription_cycle_period',
			'latest_order_id',
			'latest_order_code',
			'latest_order_membership_id',
			'latest_order_status',
			'latest_order_gateway',
			'latest_order_environment',
			'latest_order_payment_transaction_id',
			'latest_order_subscription_transaction_id',
			'latest_order_subtotal',
			'latest_order_tax',
			'latest_order_total',
			'latest_order_timestamp',
			'latest_order_discount_code',
			'stripe_customer_id',
			'account_role',
			'parent_user_id',
			'parent_member_id',
			'parent_email',
			'linked_account_slot_id',
			'family_membership_mode',
			'family_additional_adult',
			'family_dependent_count',
			'group_account_id',
			'group_account_code',
			'group_account_seat_count',
			'aac_group_account_group_id',
			'aac_group_account_checkout_code',
			'aac_group_account_child_level_id',
			'aac_group_account_synced_at',
			'pmpro_group_account_id',
			'pmpro_group_account_checkout_code',
			'pmpro_group_account_total_seats',
			'pmpro_group_account_active_members',
			'connected_accounts_json',
			'linked_parent_account_json',
			'family_membership_json',
			'pmpro_membership_history_json',
			'pmpro_subscriptions_json',
			'pmpro_orders_json',
			'pmpro_group_accounts_json',
			'all_user_meta_json',
			'portal_profile_json',
			'mirrored_at',
		];
	}

	private function get_member_export_user_ids() {
		global $wpdb;

		$user_ids = [];
		if ($wpdb && !empty($wpdb->pmpro_memberships_users)) {
			$pmpro_user_ids = $wpdb->get_col("SELECT DISTINCT user_id FROM {$wpdb->pmpro_memberships_users} WHERE user_id > 0"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- static PMPro table name.
			$user_ids = array_merge($user_ids, array_map('intval', (array) $pmpro_user_ids));
		}

		if ($wpdb) {
			$profile_user_ids = $wpdb->get_col('SELECT DISTINCT user_id FROM ' . self::profiles_table() . ' WHERE user_id > 0'); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- plugin table name.
			$user_ids = array_merge($user_ids, array_map('intval', (array) $profile_user_ids));
		}

		$member_id_users = get_users([
			'meta_key' => 'aac_member_id',
			'number' => -1,
			'fields' => 'ids',
		]);
		$user_ids = array_merge($user_ids, array_map('intval', (array) $member_id_users));
		$user_ids = array_values(array_unique(array_filter($user_ids)));
		sort($user_ids);

		return $user_ids;
	}

	private function build_member_export_row($user_id) {
		$user = get_user_by('id', $user_id);
		if (!$user instanceof WP_User) {
			return [];
		}

		$profile_row = $this->get_profile_row($user_id);
		$profile = is_array($profile_row) ? $this->decode_profile_row($profile_row) : [
			'account_info' => [],
			'profile_info' => [],
			'benefits_info' => [],
			'family_membership' => [],
			'connected_accounts' => [],
			'linked_parent_account' => [],
			'raw' => [],
		];
		$account_info = $profile['account_info'];
		$profile_info = $profile['profile_info'];
		$family_membership = $profile['family_membership'];
		$connected_accounts = $profile['connected_accounts'];
		$linked_parent_account = $profile['linked_parent_account'];
		$latest_membership = $this->get_latest_pmpro_row($user_id, 'pmpro_memberships_users');
		$latest_subscription = $this->get_latest_pmpro_row($user_id, 'pmpro_subscriptions');
		$latest_order = $this->get_latest_pmpro_row($user_id, 'pmpro_membership_orders');
		$user_meta = $this->get_user_meta_export_values($user_id);
		$group_summary = AAC_Member_Portal_Group_Accounts::is_available()
			? AAC_Member_Portal_Group_Accounts::get_group_summary_for_parent($user_id)
			: null;
		$group_account = AAC_Member_Portal_Group_Accounts::is_available()
			? AAC_Member_Portal_Group_Accounts::get_group_summary_for_user($user_id)
			: null;
		$pmpro_group_rows = $this->get_pmpro_group_account_rows($user_id);
		$parent_user_id = absint($user_meta['aac_linked_parent_user_id'] ?? ($profile_row['parent_user_id'] ?? 0));
		$parent_user = $parent_user_id > 0 ? get_user_by('id', $parent_user_id) : null;
		$membership_level_id = (int) ($latest_membership['membership_id'] ?? ($profile_info['level_id'] ?? 0));
		$latest_order_discount_code = $this->get_discount_code_for_order($latest_order);

		return [
			'wp_user_id' => (string) $user_id,
			'user_login' => $user->user_login,
			'user_email' => $user->user_email,
			'user_registered' => $user->user_registered,
			'display_name' => $user->display_name,
			'roles' => implode('|', (array) $user->roles),
			'aac_member_id' => $this->first_present_value([
				$user_meta['aac_member_id'] ?? '',
				$profile_info['member_id'] ?? '',
				$profile_row['member_id'] ?? '',
			]),
			'first_name' => $this->first_present_value([$account_info['first_name'] ?? '', $user_meta['first_name'] ?? '']),
			'last_name' => $this->first_present_value([$account_info['last_name'] ?? '', $user_meta['last_name'] ?? '']),
				'phone' => $this->first_present_value([$account_info['phone'] ?? '', $user_meta['pmpro_sphone'] ?? '', $user_meta['bphone'] ?? '']),
				'birthdate' => $this->first_present_value([$account_info['birthdate'] ?? '', $user_meta['birthdate'] ?? '']),
				'billing_address_1' => $this->first_present_value([$account_info['street'] ?? '', $user_meta['pmpro_saddress1'] ?? '', $user_meta['baddress1'] ?? '']),
				'billing_address_2' => $this->first_present_value([$account_info['address2'] ?? '', $user_meta['pmpro_saddress2'] ?? '', $user_meta['baddress2'] ?? '']),
				'billing_city' => $this->first_present_value([$account_info['city'] ?? '', $user_meta['pmpro_scity'] ?? '', $user_meta['bcity'] ?? '']),
				'billing_state' => $this->first_present_value([$account_info['state'] ?? '', $user_meta['pmpro_sstate'] ?? '', $user_meta['bstate'] ?? '']),
				'billing_zip' => $this->first_present_value([$account_info['zip'] ?? '', $user_meta['pmpro_szipcode'] ?? '', $user_meta['bzipcode'] ?? '']),
				'billing_country' => $this->first_present_value([$account_info['country'] ?? '', $user_meta['pmpro_scountry'] ?? '', $user_meta['bcountry'] ?? '']),
			't_shirt_size' => $this->first_present_value([$account_info['size'] ?? '', $user_meta['t_shirt'] ?? '']),
			'aaj_preference' => $this->first_present_value([$account_info['aaj_pref'] ?? '', $user_meta['aaj_preference'] ?? '']),
			'accidents_preference' => $this->first_present_value([$account_info['anac_pref'] ?? '', $user_meta['anac_preference'] ?? '']),
			'acj_preference' => $this->first_present_value([$account_info['acj_pref'] ?? '', $user_meta['american_climbing_journal_preference'] ?? '']),
			'guidebook_preference' => $this->first_present_value([$account_info['guidebook_pref'] ?? '', $user_meta['guidebook_preferences'] ?? '']),
			'membership_discount_type' => $this->first_present_value([$account_info['membership_discount_type'] ?? '', $user_meta['aac_membership_discount_type'] ?? '']),
			'student_university' => $this->first_present_value([$user_meta['university_school'] ?? '', $user_meta['student_university'] ?? '', $user_meta['aac_student_university'] ?? '']),
			'student_graduation_date' => $this->first_present_value([$user_meta['graduation_date'] ?? '', $user_meta['student_graduation_date'] ?? '', $user_meta['aac_graduation_date'] ?? '']),
			'military_service_component' => $this->first_present_value([$user_meta['service_component'] ?? '', $user_meta['military_service_component'] ?? '', $user_meta['aac_service_component'] ?? '']),
			'emergency_contact_first_name' => $account_info['emergency_contact_first_name'] ?? '',
			'emergency_contact_last_name' => $account_info['emergency_contact_last_name'] ?? '',
			'emergency_contact_phone' => $account_info['emergency_contact_phone'] ?? '',
			'emergency_contact_email' => $account_info['emergency_contact_email'] ?? '',
			'emergency_contact_relationship' => $account_info['emergency_contact_relationship'] ?? '',
			'current_membership_id' => (string) $membership_level_id,
			'current_membership_level' => $this->first_present_value([$profile_row['membership_level'] ?? '', $profile_info['tier'] ?? '', $this->get_pmpro_level_name($membership_level_id)]),
			'current_membership_status' => $this->first_present_value([$profile_row['membership_status'] ?? '', $profile_info['status'] ?? '', $latest_membership['status'] ?? '']),
			'membership_startdate' => (string) ($latest_membership['startdate'] ?? ''),
			'membership_enddate' => (string) ($latest_membership['enddate'] ?? ''),
			'membership_modified' => (string) ($latest_membership['modified'] ?? ''),
			'member_since' => (string) ($profile_info['joined_date'] ?? ''),
			'renewal_date' => $this->first_present_value([$profile_row['renewal_date'] ?? '', $profile_info['renewal_date'] ?? '']),
			'expiration_date' => $this->first_present_value([$profile_row['expiration_date'] ?? '', $profile_info['expiration_date'] ?? '']),
			'valid_through_date' => (string) ($profile_info['valid_through_date'] ?? ''),
			'auto_renew' => !empty($account_info['auto_renew']) ? 'true' : 'false',
			'current_subscription_id' => (string) ($latest_subscription['id'] ?? ''),
			'current_subscription_gateway' => (string) ($latest_subscription['gateway'] ?? ''),
			'current_subscription_environment' => (string) ($latest_subscription['gateway_environment'] ?? ''),
			'current_subscription_transaction_id' => (string) ($latest_subscription['subscription_transaction_id'] ?? ''),
			'current_subscription_status' => (string) ($latest_subscription['status'] ?? ''),
			'current_subscription_startdate' => (string) ($latest_subscription['startdate'] ?? ''),
			'current_subscription_enddate' => (string) ($latest_subscription['enddate'] ?? ''),
			'current_subscription_next_payment_date' => (string) ($latest_subscription['next_payment_date'] ?? ''),
			'current_subscription_billing_amount' => (string) ($latest_subscription['billing_amount'] ?? ''),
			'current_subscription_cycle_number' => (string) ($latest_subscription['cycle_number'] ?? ''),
			'current_subscription_cycle_period' => (string) ($latest_subscription['cycle_period'] ?? ''),
			'latest_order_id' => (string) ($latest_order['id'] ?? ''),
			'latest_order_code' => (string) ($latest_order['code'] ?? ''),
			'latest_order_membership_id' => (string) ($latest_order['membership_id'] ?? ''),
			'latest_order_status' => (string) ($latest_order['status'] ?? ''),
			'latest_order_gateway' => (string) ($latest_order['gateway'] ?? ''),
			'latest_order_environment' => (string) ($latest_order['gateway_environment'] ?? ''),
			'latest_order_payment_transaction_id' => (string) ($latest_order['payment_transaction_id'] ?? ''),
			'latest_order_subscription_transaction_id' => (string) ($latest_order['subscription_transaction_id'] ?? ''),
			'latest_order_subtotal' => (string) ($latest_order['subtotal'] ?? ''),
			'latest_order_tax' => (string) ($latest_order['tax'] ?? ''),
			'latest_order_total' => (string) ($latest_order['total'] ?? ''),
			'latest_order_timestamp' => (string) ($latest_order['timestamp'] ?? ''),
			'latest_order_discount_code' => $latest_order_discount_code,
			'stripe_customer_id' => $this->first_present_value([$user_meta['pmpro_stripe_customerid'] ?? '', $user_meta['stripe_customer_id'] ?? '']),
			'account_role' => $this->first_present_value([$profile_row['account_role'] ?? '', $user_meta['aac_family_account_role'] ?? '']),
			'parent_user_id' => $parent_user_id > 0 ? (string) $parent_user_id : '',
			'parent_member_id' => $parent_user_id > 0 ? (string) get_user_meta($parent_user_id, 'aac_member_id', true) : '',
			'parent_email' => $parent_user instanceof WP_User ? $parent_user->user_email : '',
			'linked_account_slot_id' => (string) ($user_meta['aac_linked_account_slot_id'] ?? ''),
			'family_membership_mode' => (string) ($family_membership['mode'] ?? ''),
			'family_additional_adult' => !empty($family_membership['additional_adult']) ? 'true' : 'false',
			'family_dependent_count' => isset($family_membership['dependent_count']) ? (string) $family_membership['dependent_count'] : '',
			'group_account_id' => $group_account ? (string) ($group_account['id'] ?? '') : '',
			'group_account_code' => $group_account ? (string) ($group_account['checkout_code'] ?? '') : '',
			'group_account_seat_count' => $group_account ? (string) ($group_account['total_seats'] ?? '') : '',
			'aac_group_account_group_id' => (string) ($user_meta['aac_group_account_group_id'] ?? ''),
			'aac_group_account_checkout_code' => (string) ($user_meta['aac_group_account_checkout_code'] ?? ''),
			'aac_group_account_child_level_id' => (string) ($user_meta['aac_group_account_child_level_id'] ?? ''),
			'aac_group_account_synced_at' => (string) ($user_meta['aac_group_account_synced_at'] ?? ''),
			'pmpro_group_account_id' => $group_summary ? (string) ($group_summary['id'] ?? '') : '',
			'pmpro_group_account_checkout_code' => $group_summary ? (string) ($group_summary['checkout_code'] ?? '') : '',
			'pmpro_group_account_total_seats' => $group_summary ? (string) ($group_summary['total_seats'] ?? '') : '',
			'pmpro_group_account_active_members' => $group_summary ? (string) ($group_summary['active_members'] ?? '') : '',
			'connected_accounts_json' => $this->encode_export_json($connected_accounts),
			'linked_parent_account_json' => $this->encode_export_json($linked_parent_account),
			'family_membership_json' => $this->encode_export_json($family_membership),
			'pmpro_membership_history_json' => $this->encode_export_json($this->get_all_pmpro_rows($user_id, 'pmpro_memberships_users')),
			'pmpro_subscriptions_json' => $this->encode_export_json($this->get_all_pmpro_rows($user_id, 'pmpro_subscriptions')),
			'pmpro_orders_json' => $this->encode_export_json($this->get_all_pmpro_rows($user_id, 'pmpro_membership_orders')),
			'pmpro_group_accounts_json' => $this->encode_export_json($pmpro_group_rows),
			'all_user_meta_json' => $this->encode_export_json($user_meta),
			'portal_profile_json' => $this->encode_export_json($profile['raw']),
			'mirrored_at' => (string) ($profile_row['mirrored_at'] ?? ''),
		];
	}

	private function get_user_meta_export_values($user_id) {
		$raw_meta = get_user_meta($user_id);
		$meta = [];
		foreach ((array) $raw_meta as $key => $values) {
			$value = is_array($values) && count($values) === 1 ? $values[0] : $values;
			$meta[$key] = maybe_unserialize($value);
		}

		ksort($meta);
		return $meta;
	}

	private function get_all_pmpro_rows($user_id, $wpdb_property) {
		global $wpdb;

		if (!$wpdb || empty($wpdb->{$wpdb_property})) {
			return [];
		}

		$table = $wpdb->{$wpdb_property};
		$rows = $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC", $user_id),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.

		return is_array($rows) ? $rows : [];
	}

	private function get_pmpro_group_account_rows($user_id) {
		global $wpdb;

		if (!$wpdb) {
			return [];
		}

		$like = $wpdb->esc_like($wpdb->prefix . 'pmprogroupacct') . '%';
		$tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));
		$rows = [];
		foreach ((array) $tables as $table) {
			$table = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table);
			if ($table === '') {
				continue;
			}

			$columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names discovered from DB and sanitized above.
			$columns = is_array($columns) ? $columns : [];
			$user_columns = array_values(array_intersect($columns, [
				'user_id',
				'parent_user_id',
				'child_user_id',
				'group_parent_user_id',
				'group_child_user_id',
			]));
			if (!$user_columns) {
				continue;
			}

			$where_parts = array_map(function ($column) {
				return $column . ' = %d';
			}, $user_columns);
			$query = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE " . implode(' OR ', $where_parts) . ' ORDER BY 1 DESC',
				array_fill(0, count($where_parts), $user_id)
			);
			$table_rows = $wpdb->get_results($query, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
			foreach ((array) $table_rows as $row) {
				$rows[] = [
					'table' => $table,
					'row' => $row,
				];
			}
		}

		return $rows;
	}

	private function get_pmpro_level_name($level_id) {
		$level_id = absint($level_id);
		if ($level_id <= 0 || !function_exists('pmpro_getLevel')) {
			return '';
		}

		$level = pmpro_getLevel($level_id);
		return is_object($level) && !empty($level->name) ? (string) $level->name : '';
	}

	private function get_discount_code_for_order($order) {
		global $wpdb;

		if (!is_array($order) || empty($order['id']) || !$wpdb || empty($wpdb->pmpro_discount_codes_uses) || empty($wpdb->pmpro_discount_codes)) {
			return '';
		}

		$code = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT dc.code
				FROM {$wpdb->pmpro_discount_codes_uses} dcu
				INNER JOIN {$wpdb->pmpro_discount_codes} dc ON dc.id = dcu.code_id
				WHERE dcu.order_id = %d
				ORDER BY dcu.id DESC
				LIMIT 1",
				(int) $order['id']
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.

		return is_string($code) ? $code : '';
	}

	private function first_present_value($values) {
		foreach ((array) $values as $value) {
			if (is_scalar($value) && trim((string) $value) !== '') {
				return (string) $value;
			}
		}

		return '';
	}

	private function encode_export_json($value) {
		if ($value === null || $value === '') {
			return '';
		}

		$json = wp_json_encode($value, JSON_UNESCAPED_SLASHES);
		return is_string($json) ? $json : '';
	}

	public function render_admin_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$member_id = isset($_GET['member_id']) ? absint(wp_unslash($_GET['member_id'])) : 0;
		$tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'profile';
		$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
		$paged = isset($_GET['paged']) ? max(1, absint(wp_unslash($_GET['paged']))) : 1;
		$did_sync = false;
		$sync_all_count = null;

		if (isset($_GET['aac_sync_all']) && '1' === wp_unslash($_GET['aac_sync_all'])) {
			check_admin_referer('aac_member_db_sync_all');
			$sync_all_count = $this->sync_all_members();
		}

		if ($member_id > 0) {
			$did_sync = $this->sync_member($member_id);
		}

		$member_list = $this->get_member_list_rows($search, $paged, 20);
		$profile_row = $member_id > 0 ? $this->get_profile_row($member_id) : null;
		$history_rows = $member_id > 0 ? $this->get_mirror_rows(self::history_table(), $member_id) : [];
		$subscription_rows = $member_id > 0 ? $this->get_mirror_rows(self::subscriptions_table(), $member_id) : [];
		$transaction_rows = $member_id > 0 ? $this->get_mirror_rows(self::transactions_table(), $member_id) : [];
		?>
		<div class="wrap">
			<h1>Member Database</h1>
			<?php self::render_database_tools_nav(self::PAGE_SLUG); ?>
			<p>This AAC-owned backend mirror stores a portal copy of member profile data plus mirrored PMPro membership history, subscriptions, and transactions.</p>

			<form method="get" style="margin:16px 0 24px;">
				<input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>" />
				<input
					type="search"
					name="s"
					value="<?php echo esc_attr($search); ?>"
					placeholder="Search by name or email"
					class="regular-text"
				/>
				<?php submit_button('Search Members', 'secondary', '', false); ?>
				<a
					class="button button-secondary"
					style="margin-left:8px;"
					href="<?php echo esc_url(wp_nonce_url($this->build_admin_url(['member_id' => $member_id, 'tab' => $tab, 's' => $search, 'paged' => $paged, 'aac_sync_all' => 1]), 'aac_member_db_sync_all')); ?>"
				>
					Sync All Members
				</a>
				<a
					class="button button-primary"
					style="margin-left:8px;"
					href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'aac_member_portal_export_member_database'], admin_url('admin-post.php')), 'aac_member_portal_export_member_database')); ?>"
				>
					Export Selected Fields CSV
				</a>
			</form>

			<?php if ($sync_all_count !== null) : ?>
				<div class="notice notice-success inline" style="margin:0 0 16px;">
					<p><?php echo esc_html(sprintf('Synced %d members into the AAC Portal database mirror.', $sync_all_count)); ?></p>
				</div>
			<?php endif; ?>

			<section style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:20px;margin-bottom:24px;">
				<div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;">
					<div>
						<h2 style="margin:0;">All Mirrored Members</h2>
						<p style="margin:8px 0 0;color:#50575e;max-width:900px;">
							This table lists mirrored AAC Portal members with profile/contact details and their current membership status. Open a member to inspect deeper profile, preference, subscription, and transaction records.
						</p>
					</div>
					<div style="color:#50575e;font-size:13px;">
						<?php echo esc_html(sprintf('%d total mirrored members', (int) $member_list['total'])); ?>
					</div>
				</div>

				<?php if (!$member_list['rows']) : ?>
					<p style="margin-top:16px;">No mirrored members found. Use “Sync All Members” to build the AAC Portal database mirror.</p>
				<?php else : ?>
					<div style="overflow:auto;margin-top:20px;">
						<table class="widefat striped">
							<thead>
								<tr>
									<th style="min-width:140px;">Open Member</th>
									<th style="min-width:180px;">Member</th>
									<th style="min-width:220px;">Email</th>
									<th style="min-width:160px;">Phone</th>
									<th style="min-width:140px;">City</th>
									<th style="min-width:120px;">State</th>
									<th style="min-width:140px;">Country</th>
									<th>User ID</th>
									<th>Member ID</th>
									<th>Membership Level</th>
									<th>Status</th>
									<th>Role</th>
									<th>Renewal</th>
									<th>Expiration</th>
									<th>Mirrored At</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($member_list['rows'] as $row) : ?>
									<?php $is_selected_member_row = $member_id > 0 && (int) $row['user_id'] === (int) $member_id && $profile_row; ?>
									<tr<?php echo $is_selected_member_row ? ' style="background:#f6f7f7;"' : ''; ?>>
										<td>
											<div style="display:grid;gap:6px;">
												<a class="button <?php echo $is_selected_member_row ? 'button-primary' : 'button-secondary'; ?>" href="<?php echo esc_url($is_selected_member_row ? $this->build_admin_url(['s' => $search, 'paged' => $paged]) : $this->build_admin_url(['member_id' => $row['user_id'], 'tab' => 'profile', 's' => $search, 'paged' => $paged])); ?>">
													<?php echo esc_html($is_selected_member_row ? 'Close Member' : 'Open Member'); ?>
												</a>
												<?php
												$list_member_user = get_user_by('id', (int) $row['user_id']);
												$list_member_return_url = $this->build_admin_url(['member_id' => $row['user_id'], 'tab' => 'profile', 's' => $search, 'paged' => $paged]);
												if ($list_member_user instanceof WP_User && !user_can($list_member_user, 'manage_options')) :
													?>
													<a class="button button-primary" href="<?php echo esc_url(AAC_Member_Portal_Impersonation::get_switch_url($row['user_id'], $list_member_return_url)); ?>">
														View as Member
													</a>
												<?php endif; ?>
											</div>
										</td>
										<td style="white-space:normal;word-break:break-word;"><?php echo esc_html($row['display_name'] ?: 'Unknown member'); ?></td>
										<td style="white-space:normal;word-break:break-word;"><?php echo esc_html($row['email']); ?></td>
										<td style="white-space:normal;word-break:break-word;"><?php echo esc_html($row['account_info']['phone'] ?? ''); ?></td>
										<td style="white-space:normal;word-break:break-word;"><?php echo esc_html($row['account_info']['city'] ?? ''); ?></td>
										<td style="white-space:normal;word-break:break-word;"><?php echo esc_html($row['account_info']['state'] ?? ''); ?></td>
										<td style="white-space:normal;word-break:break-word;"><?php echo esc_html($row['account_info']['country'] ?? ''); ?></td>
										<td><?php echo esc_html($row['user_id']); ?></td>
										<td><?php echo esc_html($row['member_id']); ?></td>
										<td><?php echo esc_html($row['membership_level']); ?></td>
										<td><?php echo esc_html($row['membership_status']); ?></td>
										<td><?php echo esc_html($row['account_role'] ?: 'Standard'); ?></td>
										<td><?php echo esc_html($row['renewal_date']); ?></td>
										<td><?php echo esc_html($row['expiration_date']); ?></td>
										<td><?php echo esc_html($row['mirrored_at']); ?></td>
									</tr>
									<?php if ($is_selected_member_row) : ?>
										<tr>
											<td colspan="15" style="padding:0;background:#fff;">
												<div style="padding:20px;border-left:4px solid #2271b1;border-bottom:1px solid #dcdcde;">
													<?php
													$this->render_member_detail_panel(
														$member_id,
														$profile_row,
														$tab,
														$search,
														$paged,
														$did_sync,
														$history_rows,
														$subscription_rows,
														$transaction_rows
													);
													?>
												</div>
											</td>
										</tr>
									<?php endif; ?>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>

					<?php echo $this->render_member_list_pagination($member_list['total'], $member_list['page'], $member_list['per_page'], $search, $member_id, $tab); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
			</section>

			<?php if ($member_id > 0 && !$profile_row) : ?>
				<div class="notice notice-warning"><p>This member could not be mirrored yet.</p></div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_member_detail_panel($member_id, $profile_row, $tab, $search, $paged, $did_sync, $history_rows, $subscription_rows, $transaction_rows) {
		$tabs = [
			'profile' => 'Profile',
			'preferences' => 'Preferences',
			'membership-history' => 'Membership History',
			'subscriptions' => 'Subscriptions',
			'transactions' => 'Transactions',
		];
		?>
		<div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;">
			<div>
				<h2 style="margin:0;"><?php echo esc_html($profile_row['display_name'] ?: $profile_row['email']); ?></h2>
				<p style="margin:8px 0 0;color:#50575e;">
					<?php echo esc_html($profile_row['email']); ?> · User ID <?php echo esc_html($member_id); ?>
				</p>
			</div>
			<div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
				<a class="button button-secondary" href="<?php echo esc_url($this->build_admin_url(['member_id' => $member_id, 'tab' => $tab, 's' => $search, 'paged' => $paged])); ?>">
					Sync This Member
				</a>
				<?php
				$detail_member_user = get_user_by('id', $member_id);
				if ($detail_member_user instanceof WP_User && !user_can($detail_member_user, 'manage_options')) :
					?>
					<a class="button button-primary" href="<?php echo esc_url(AAC_Member_Portal_Impersonation::get_switch_url($member_id, $this->build_admin_url(['member_id' => $member_id, 'tab' => $tab, 's' => $search, 'paged' => $paged]))); ?>">
						View as Member
					</a>
				<?php endif; ?>
			</div>
		</div>

		<?php if ($did_sync) : ?>
			<div class="notice notice-success inline" style="margin:16px 0 0;">
				<p>Member mirror refreshed.</p>
			</div>
		<?php endif; ?>

		<nav class="nav-tab-wrapper" style="margin-top:20px;">
			<?php foreach ($tabs as $tab_key => $tab_label) : ?>
				<a class="nav-tab <?php echo $tab === $tab_key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($this->build_admin_url(['member_id' => $member_id, 'tab' => $tab_key, 's' => $search, 'paged' => $paged])); ?>">
					<?php echo esc_html($tab_label); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<div style="margin-top:20px;">
			<?php
			if ($tab === 'preferences') {
				$this->render_preferences_tab($profile_row);
			} elseif ($tab === 'membership-history') {
				$this->render_json_tab_table($history_rows, 'No mirrored membership history found.', 'membership');
			} elseif ($tab === 'subscriptions') {
				$this->render_json_tab_table($subscription_rows, 'No mirrored subscriptions found.', 'subscription');
			} elseif ($tab === 'transactions') {
				$this->render_json_tab_table($transaction_rows, 'No mirrored transactions found.', 'transaction');
			} else {
				$this->render_profile_tab($profile_row);
			}
			?>
		</div>
		<?php
	}

	private function render_profile_tab($profile_row) {
		$profile = $this->decode_profile_row($profile_row);
		$account_info = $profile['account_info'];
		$profile_info = $profile['profile_info'];
		$stored_group_account = is_array($profile['group_account'] ?? null) ? $profile['group_account'] : [];
		$live_group_account = class_exists('AAC_Member_Portal_Group_Accounts')
			? AAC_Member_Portal_Group_Accounts::get_group_summary_for_user((int) ($profile_row['user_id'] ?? 0))
			: null;
		$group_account = is_array($live_group_account) ? $live_group_account : $stored_group_account;
		?>
		<table class="widefat striped">
			<tbody>
				<tr><th style="width:240px;">First Name</th><td><?php echo esc_html($account_info['first_name'] ?? ''); ?></td></tr>
				<tr><th>Last Name</th><td><?php echo esc_html($account_info['last_name'] ?? ''); ?></td></tr>
				<tr><th>Display Name</th><td><?php echo esc_html($profile_row['display_name']); ?></td></tr>
				<tr><th>Email</th><td><?php echo esc_html($profile_row['email']); ?></td></tr>
				<tr><th>Phone</th><td><?php echo esc_html($account_info['phone'] ?? ''); ?></td></tr>
				<tr><th>Birthdate</th><td><?php echo esc_html($account_info['birthdate'] ?? ''); ?></td></tr>
				<tr><th>Street</th><td><?php echo esc_html($account_info['street'] ?? ''); ?></td></tr>
				<tr><th>Address 2</th><td><?php echo esc_html($account_info['address2'] ?? ''); ?></td></tr>
				<tr><th>City</th><td><?php echo esc_html($account_info['city'] ?? ''); ?></td></tr>
				<tr><th>State</th><td><?php echo esc_html($account_info['state'] ?? ''); ?></td></tr>
				<tr><th>ZIP</th><td><?php echo esc_html($account_info['zip'] ?? ''); ?></td></tr>
				<tr><th>Country</th><td><?php echo esc_html($account_info['country'] ?? ''); ?></td></tr>
			</tbody>
		</table>

		<h3 style="margin-top:24px;">Current Membership</h3>
		<table class="widefat striped">
			<tbody>
				<tr><th style="width:240px;">Membership Level</th><td><?php echo esc_html($profile_row['membership_level']); ?></td></tr>
				<tr><th>Membership Status</th><td><?php echo esc_html($profile_row['membership_status']); ?></td></tr>
				<tr><th>Member ID</th><td><?php echo esc_html($profile_row['member_id']); ?></td></tr>
				<tr><th>Member Since</th><td><?php echo esc_html($profile_info['joined_date'] ?? ''); ?></td></tr>
				<tr><th>Renewal Date</th><td><?php echo esc_html($profile_row['renewal_date']); ?></td></tr>
				<tr><th>Expiration Date</th><td><?php echo esc_html($profile_row['expiration_date']); ?></td></tr>
				<tr><th>Account Role</th><td><?php echo esc_html($profile_row['account_role'] ?: 'Standard'); ?></td></tr>
				<tr><th>Mirrored At</th><td><?php echo esc_html($profile_row['mirrored_at']); ?></td></tr>
			</tbody>
		</table>

		<h3 style="margin-top:24px;">Group Account</h3>
		<table class="widefat striped">
			<tbody>
				<tr><th style="width:240px;">Group ID</th><td><?php echo esc_html((string) ($group_account['id'] ?? '')); ?></td></tr>
				<tr><th>Group Code</th><td><code><?php echo esc_html((string) ($group_account['checkout_code'] ?? '')); ?></code></td></tr>
				<tr><th>Seat Count</th><td><?php echo esc_html((string) ($group_account['total_seats'] ?? '')); ?></td></tr>
				<tr><th>Active Associated Accounts</th><td><?php echo esc_html((string) ($group_account['active_members'] ?? '')); ?></td></tr>
				<tr><th>Group Account Role</th><td><?php echo esc_html((string) ($group_account['account_role'] ?? '')); ?></td></tr>
				<tr><th>Parent User ID</th><td><?php echo esc_html((string) ($group_account['parent_user_id'] ?? '')); ?></td></tr>
			</tbody>
		</table>
		<?php
	}

	private function render_preferences_tab($profile_row) {
		$profile = $this->decode_profile_row($profile_row);
		$account_info = $profile['account_info'];
		$benefits_info = $profile['benefits_info'];
		$family_membership = $profile['family_membership'];
		$connected_accounts = is_array($profile['connected_accounts']) ? $profile['connected_accounts'] : [];
		$linked_parent_account = is_array($profile['linked_parent_account']) ? $profile['linked_parent_account'] : [];
		$preference_fields = [
			'T-Shirt Size' => $account_info['size'] ?? '',
			'AAJ Preference' => $account_info['aaj_pref'] ?? '',
			'ANAC Preference' => $account_info['anac_pref'] ?? '',
			'American Climbing Journal Preference' => $account_info['acj_pref'] ?? '',
			'Guidebook Preference' => $account_info['guidebook_pref'] ?? '',
			'Membership Discount Type' => $account_info['membership_discount_type'] ?? '',
			'Auto Renew' => !empty($account_info['auto_renew']) ? 'true' : 'false',
			'Magazine Subscriptions' => !empty($account_info['magazine_subscriptions']) ? implode(', ', (array) $account_info['magazine_subscriptions']) : '',
			'Family Mode' => $family_membership['mode'] ?? '',
			'Additional Adult' => !empty($family_membership['additional_adult']) ? 'true' : 'false',
			'Dependent Count' => isset($family_membership['dependent_count']) ? (string) $family_membership['dependent_count'] : '',
			'Connected Accounts Count' => (string) count($connected_accounts),
			'Linked Parent Account' => $linked_parent_account['name'] ?? '',
			'Rescue Amount' => isset($benefits_info['rescue_amount']) ? (string) $benefits_info['rescue_amount'] : '',
			'Medical Amount' => isset($benefits_info['medical_amount']) ? (string) $benefits_info['medical_amount'] : '',
			'Mortal Remains Amount' => isset($benefits_info['mortal_remains_amount']) ? (string) $benefits_info['mortal_remains_amount'] : '',
			'Rescue Reimbursement Process' => !empty($benefits_info['rescue_reimbursement_process']) ? 'true' : 'false',
		];
		$flat_profile = $this->flatten_assoc($profile);
		?>
		<table class="widefat striped">
			<tbody>
				<?php foreach ($preference_fields as $label => $value) : ?>
					<tr>
						<th style="width:280px;"><?php echo esc_html($label); ?></th>
						<td><?php echo esc_html($value); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_json_tab_table($rows, $empty_message, $record_type = '') {
		if (!$rows) {
			echo '<p>' . esc_html($empty_message) . '</p>';
			return;
		}

		$column_priority_map = [
			'membership' => [
				'membership_id',
				'status',
				'startdate',
				'enddate',
				'initial_payment',
				'billing_amount',
				'cycle_number',
				'cycle_period',
				'billing_limit',
				'trial_amount',
				'trial_limit',
				'modified',
			],
			'subscription' => [
				'id',
				'membership_id',
				'status',
				'gateway',
				'billing_amount',
				'cycle_number',
				'cycle_period',
				'next_payment_date',
				'startdate',
				'enddate',
				'subscription_transaction_id',
				'modified',
			],
			'transaction' => [
				'id',
				'membership_id',
				'code',
				'total',
				'subtotal',
				'tax',
				'payment_type',
				'status',
				'gateway',
				'payment_transaction_id',
				'subscription_transaction_id',
				'timestamp',
				'notes',
			],
		];
		$prioritized_columns = $column_priority_map[$record_type] ?? [];
		$decoded_rows = [];
		$present_keys = [];

		foreach ($rows as $row) {
			$record = json_decode($row['raw_record'] ?? '', true);
			$flat_record = $this->flatten_assoc($record);
			$decoded_rows[] = [
				'meta' => $row,
				'record' => $flat_record,
			];
			foreach ($flat_record as $key => $value) {
				if ($value !== '' && $value !== null) {
					$present_keys[$key] = true;
				}
			}
		}

		$all_keys = [];
		foreach ($prioritized_columns as $key) {
			if (!empty($present_keys[$key])) {
				$all_keys[] = $key;
			}
		}

		if (!$all_keys) {
			$all_keys = array_keys($present_keys);
			sort($all_keys);
		}

		?>
		<div style="overflow:auto;">
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Source ID</th>
						<th>Status</th>
						<th>Date</th>
						<?php foreach ($all_keys as $key) : ?>
							<th><?php echo esc_html($key); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($decoded_rows as $row) : ?>
						<tr>
							<td><?php echo esc_html($row['meta']['source_record_id']); ?></td>
							<td><?php echo esc_html($row['meta']['source_status']); ?></td>
							<td><?php echo esc_html($row['meta']['source_date']); ?></td>
							<?php foreach ($all_keys as $key) : ?>
								<td><?php echo esc_html($row['record'][$key] ?? ''); ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function get_member_list_rows($search = '', $page = 1, $per_page = 20) {
		global $wpdb;

		$page = max(1, (int) $page);
		$per_page = max(1, (int) $per_page);
		$offset = ($page - 1) * $per_page;
		$table = self::profiles_table();
		$where_sql = '1=1';
		$params = [];

		if ($search !== '') {
			$like = '%' . $wpdb->esc_like($search) . '%';
			$where_sql .= ' AND (display_name LIKE %s OR email LIKE %s OR member_id LIKE %s OR membership_level LIKE %s OR membership_status LIKE %s OR account_role LIKE %s OR raw_profile LIKE %s)';
			$params = [$like, $like, $like, $like, $like, $like, $like];
		}

		if ($params) {
			$total_sql = $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $params);
			$rows_sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY display_name ASC, email ASC, user_id ASC LIMIT %d OFFSET %d",
				array_merge($params, [$per_page, $offset])
			);
		} else {
			$total_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
			$rows_sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY display_name ASC, email ASC, user_id ASC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			);
		}

		$total = (int) $wpdb->get_var($total_sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above when needed.
		$rows = $wpdb->get_results($rows_sql, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.

		$member_rows = [];

		foreach ((array) $rows as $row) {
			$profile = $this->decode_profile_row($row);
			$row['account_info'] = $profile['account_info'];
			$row['profile_info'] = $profile['profile_info'];
			$member_rows[] = $row;
		}

		return [
			'total' => $total,
			'page' => $page,
			'per_page' => $per_page,
			'rows' => $member_rows,
		];
	}

	private function decode_profile_row($profile_row) {
		$profile = json_decode($profile_row['raw_profile'] ?? '', true);
		$profile = is_array($profile) ? $profile : [];

		return [
			'account_info' => is_array($profile['account_info'] ?? null) ? $profile['account_info'] : [],
			'profile_info' => is_array($profile['profile_info'] ?? null) ? $profile['profile_info'] : [],
			'benefits_info' => is_array($profile['benefits_info'] ?? null) ? $profile['benefits_info'] : [],
			'family_membership' => is_array($profile['family_membership'] ?? null) ? $profile['family_membership'] : [],
			'connected_accounts' => is_array($profile['connected_accounts'] ?? null) ? $profile['connected_accounts'] : [],
			'linked_parent_account' => is_array($profile['linked_parent_account'] ?? null) ? $profile['linked_parent_account'] : [],
			'raw' => $profile,
		];
	}

	private function render_member_list_pagination($total, $page, $per_page, $search, $member_id, $tab) {
		$total_pages = (int) ceil($total / max(1, $per_page));
		if ($total_pages <= 1) {
			return '';
		}

		$links = paginate_links([
			'base' => add_query_arg([
				'page' => self::PAGE_SLUG,
				's' => $search,
				'member_id' => $member_id,
				'tab' => $tab,
				'paged' => '%#%',
			], admin_url('admin.php')),
			'format' => '',
			'current' => max(1, $page),
			'total' => $total_pages,
			'type' => 'plain',
			'prev_text' => '&laquo;',
			'next_text' => '&raquo;',
		]);

		if (!$links) {
			return '';
		}

		return '<div class="tablenav" style="margin-top:16px;"><div class="tablenav-pages">' . $links . '</div></div>';
	}

	private function sync_all_members() {
		$user_query = new WP_User_Query([
			'number' => -1,
			'fields' => 'ID',
			'orderby' => 'ID',
			'order' => 'ASC',
		]);

		$count = 0;
		foreach ((array) $user_query->get_results() as $user_id) {
			if ($this->sync_member((int) $user_id)) {
				$count++;
			}
		}

		return $count;
	}

	private function get_profile_row($user_id) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM " . self::profiles_table() . ' WHERE user_id = %d LIMIT 1', $user_id),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
	}

	private function get_mirror_rows($table, $user_id) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d ORDER BY source_record_id DESC, id DESC", $user_id),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
	}

	private function flatten_assoc($value, $prefix = '') {
		$rows = [];

		if (!is_array($value)) {
			$rows[$prefix !== '' ? $prefix : 'value'] = $this->stringify_value($value);
			return $rows;
		}

		foreach ($value as $key => $item) {
			$child_key = $prefix === '' ? (string) $key : $prefix . '.' . $key;
			if (is_array($item)) {
				if ($this->is_assoc($item)) {
					$rows = array_merge($rows, $this->flatten_assoc($item, $child_key));
				} else {
					$rows[$child_key] = wp_json_encode($item);
				}
				continue;
			}

			$rows[$child_key] = $this->stringify_value($item);
		}

		return $rows;
	}

	private function stringify_value($value) {
		if (is_bool($value)) {
			return $value ? 'true' : 'false';
		}

		if ($value === null) {
			return '';
		}

		if (is_scalar($value)) {
			return (string) $value;
		}

		return wp_json_encode($value);
	}

	private function is_assoc(array $array) {
		return array_keys($array) !== range(0, count($array) - 1);
	}

	private function build_admin_url($args = []) {
		return add_query_arg(array_merge(['page' => self::PAGE_SLUG], $args), admin_url('admin.php'));
	}

	private static function profiles_table() {
		global $wpdb;
		return $wpdb->prefix . 'aac_member_db_profiles';
	}

	private static function history_table() {
		global $wpdb;
		return $wpdb->prefix . 'aac_member_db_membership_history';
	}

	private static function subscriptions_table() {
		global $wpdb;
		return $wpdb->prefix . 'aac_member_db_subscriptions';
	}

	private static function transactions_table() {
		global $wpdb;
		return $wpdb->prefix . 'aac_member_db_transactions';
	}

}
