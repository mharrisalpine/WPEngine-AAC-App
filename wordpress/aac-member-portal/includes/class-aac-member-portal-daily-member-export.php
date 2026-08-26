<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Member_Portal_Daily_Member_Export {
	const OPTION_KEY = 'aac_member_portal_daily_member_export_settings';
	const MENU_SLUG = 'aac-member-portal-daily-member-export';
	const CRON_HOOK = 'aac_member_portal_daily_member_export';

	public function __construct() {
		add_action('admin_menu', [$this, 'register_admin_page']);
		add_action('admin_post_aac_member_portal_save_daily_export', [$this, 'handle_save_settings']);
		add_action('admin_post_aac_member_portal_run_daily_export', [$this, 'handle_run_now']);
		add_action('admin_post_aac_member_portal_download_daily_export', [$this, 'handle_download_report']);
		add_action('init', [$this, 'maybe_schedule_export']);
		add_action(self::CRON_HOOK, [$this, 'run_scheduled_export']);
	}

	public static function activate() {
		self::schedule_next_export();
	}

	public static function deactivate() {
		$timestamp = wp_next_scheduled(self::CRON_HOOK);
		while ($timestamp) {
			wp_unschedule_event($timestamp, self::CRON_HOOK);
			$timestamp = wp_next_scheduled(self::CRON_HOOK);
		}
	}

	public static function get_defaults() {
		return [
			'enabled' => '0',
			'host' => 'sftp.expertvoice.com',
			'port' => 22,
			'remote_path' => '/incoming/',
			'unique_identifier' => '',
			'username' => '',
			'password' => '',
			'send_time' => '02:00',
			'timezone' => wp_timezone_string() ?: 'UTC',
			'last_run_at' => '',
			'last_run_status' => '',
			'last_run_message' => '',
			'last_row_count' => 0,
		];
	}

	public static function get_settings() {
		$settings = get_option(self::OPTION_KEY, []);
		$settings = is_array($settings) ? $settings : [];

		return array_merge(self::get_defaults(), $settings);
	}

	public function register_admin_page() {
		add_submenu_page(
			null,
			'Daily Member Export',
			'Daily Member Export',
			'manage_options',
			self::MENU_SLUG,
			[$this, 'render_admin_page']
		);
	}

	public function maybe_schedule_export() {
		$settings = self::get_settings();
		if ($settings['enabled'] !== '1') {
			return;
		}

		if (!wp_next_scheduled(self::CRON_HOOK)) {
			self::schedule_next_export();
		}
	}

	public static function schedule_next_export() {
		$settings = self::get_settings();
		self::deactivate();

		if ($settings['enabled'] !== '1') {
			return;
		}

		$timestamp = self::get_next_run_timestamp($settings);
		if ($timestamp > 0) {
			wp_schedule_event($timestamp, 'daily', self::CRON_HOOK);
		}
	}

	public function handle_save_settings() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to manage this export.', 'aac-member-portal'));
		}

		check_admin_referer('aac_member_portal_save_daily_export');

		$current = self::get_settings();
		$input = isset($_POST[self::OPTION_KEY]) && is_array($_POST[self::OPTION_KEY])
			? wp_unslash($_POST[self::OPTION_KEY])
			: [];

		$password = trim((string) ($input['password'] ?? ''));
		$settings = array_merge($current, [
			'enabled' => !empty($input['enabled']) ? '1' : '0',
			'host' => self::normalize_host((string) ($input['host'] ?? 'sftp.expertvoice.com')),
			'port' => max(1, min(65535, (int) ($input['port'] ?? 22))),
			'remote_path' => self::normalize_remote_path((string) ($input['remote_path'] ?? '/incoming/')),
			'unique_identifier' => sanitize_file_name((string) ($input['unique_identifier'] ?? '')),
			'username' => sanitize_text_field((string) ($input['username'] ?? '')),
			'send_time' => self::normalize_send_time((string) ($input['send_time'] ?? '02:00')),
			'timezone' => self::normalize_timezone((string) ($input['timezone'] ?? wp_timezone_string())),
		]);

		if ($password !== '') {
			$settings['password'] = $password;
		}

		update_option(self::OPTION_KEY, $settings, false);
		self::schedule_next_export();

		wp_safe_redirect(add_query_arg(['page' => self::MENU_SLUG, 'aac_export_saved' => '1'], admin_url('admin.php')));
		exit;
	}

	public function handle_run_now() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to run this export.', 'aac-member-portal'));
		}

		check_admin_referer('aac_member_portal_run_daily_export');
		$result = $this->run_export();
		$status = is_wp_error($result) ? 'error' : 'success';

		wp_safe_redirect(add_query_arg(['page' => self::MENU_SLUG, 'aac_export_run' => $status], admin_url('admin.php')));
		exit;
	}

	public function handle_download_report() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to download this report.', 'aac-member-portal'));
		}

		check_admin_referer('aac_member_portal_download_daily_export');

		$rows = $this->get_active_member_rows();
		$csv = $this->build_csv($rows);
		$filename = $this->build_download_filename();

		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Length: ' . strlen($csv));

		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download content.
		exit;
	}

	public function run_scheduled_export() {
		$settings = self::get_settings();
		if ($settings['enabled'] !== '1') {
			return;
		}

		$this->run_export();
	}

	public function run_export() {
		$settings = self::get_settings();
		$validation = $this->validate_settings($settings);
		if (is_wp_error($validation)) {
			$this->record_run('error', $validation->get_error_message(), 0);
			return $validation;
		}

		$rows = $this->get_active_member_rows();
		$csv = $this->build_csv($rows);
		$filename = $settings['unique_identifier'] . '.memberauth.csv';
		$remote_file = trailingslashit($settings['remote_path']) . $filename;
		$result = $this->send_to_sftp($settings, $remote_file, $csv);

		if (is_wp_error($result)) {
			$this->record_run('error', $result->get_error_message(), count($rows));
			return $result;
		}

		$message = sprintf('Uploaded %s with %d active members.', $remote_file, count($rows));
		$this->record_run('success', $message, count($rows));

		return [
			'remote_file' => $remote_file,
			'row_count' => count($rows),
		];
	}

	private function build_download_filename() {
		$settings = self::get_settings();
		$identifier = sanitize_file_name((string) ($settings['unique_identifier'] ?? ''));
		if ($identifier !== '') {
			return $identifier . '.memberauth.csv';
		}

		return 'aac-active-members-' . gmdate('Y-m-d') . '.memberauth.csv';
	}

	private function get_active_member_rows() {
		global $wpdb;

		if (!$wpdb || empty($wpdb->pmpro_memberships_users)) {
			return [];
		}

		$memberships_table = $wpdb->pmpro_memberships_users;
		$users_table = $wpdb->users;
		$usermeta_table = $wpdb->usermeta;

		$query = "
			SELECT DISTINCT
				u.ID AS user_id,
				COALESCE(member_id_meta.meta_value, '') AS member_id,
				COALESCE(first_name_meta.meta_value, '') AS first_name,
				COALESCE(last_name_meta.meta_value, '') AS last_name,
				u.display_name
			FROM {$memberships_table} pmu
			INNER JOIN {$users_table} u ON u.ID = pmu.user_id
			LEFT JOIN {$usermeta_table} member_id_meta
				ON member_id_meta.user_id = u.ID AND member_id_meta.meta_key = 'aac_member_id'
			LEFT JOIN {$usermeta_table} first_name_meta
				ON first_name_meta.user_id = u.ID AND first_name_meta.meta_key = 'first_name'
			LEFT JOIN {$usermeta_table} last_name_meta
				ON last_name_meta.user_id = u.ID AND last_name_meta.meta_key = 'last_name'
			WHERE pmu.status = 'active'
			ORDER BY u.ID ASC
		";

		$results = $wpdb->get_results($query, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- static table names and no external params.
		if (!is_array($results)) {
			return [];
		}

		$rows = [];
		foreach ($results as $row) {
			$user_id = (int) ($row['user_id'] ?? 0);
			if ($user_id <= 0) {
				continue;
			}

			$name_parts = $this->split_display_name((string) ($row['display_name'] ?? ''));
			$first_name = trim((string) ($row['first_name'] ?? ''));
			$last_name = trim((string) ($row['last_name'] ?? ''));

			$rows[] = [
				'member_id' => trim((string) ($row['member_id'] ?? '')) ?: (string) $user_id,
				'first_name' => $first_name !== '' ? $first_name : $name_parts['first_name'],
				'last_name' => $last_name !== '' ? $last_name : $name_parts['last_name'],
			];
		}

		return $rows;
	}

	private function build_csv($rows) {
		$handle = fopen('php://temp', 'r+');
		if (!$handle) {
			return '';
		}

		fputcsv($handle, ['member_id', 'first_name', 'last_name']);
		foreach ($rows as $row) {
			fputcsv($handle, [
				$row['member_id'] ?? '',
				$row['first_name'] ?? '',
				$row['last_name'] ?? '',
			]);
		}

		rewind($handle);
		$csv = stream_get_contents($handle);
		fclose($handle);

		return is_string($csv) ? $csv : '';
	}

	private function send_to_sftp($settings, $remote_file, $contents) {
		if (!extension_loaded('ssh2')) {
			return new WP_Error(
				'missing_ssh2',
				'The PHP ssh2 extension is required for SFTP uploads on this server.'
			);
		}

		$connection = @ssh2_connect($settings['host'], (int) $settings['port']);
		if (!$connection) {
			return new WP_Error('sftp_connect_failed', 'Could not connect to the SFTP server.');
		}

		if (!@ssh2_auth_password($connection, $settings['username'], $settings['password'])) {
			return new WP_Error('sftp_auth_failed', 'Could not authenticate with the SFTP server.');
		}

		$sftp = @ssh2_sftp($connection);
		if (!$sftp) {
			return new WP_Error('sftp_init_failed', 'Could not initialize the SFTP session.');
		}

		$stream = @fopen('ssh2.sftp://' . intval($sftp) . $remote_file, 'w');
		if (!$stream) {
			return new WP_Error('sftp_open_failed', 'Could not open the remote SFTP file for writing.');
		}

		$bytes_written = fwrite($stream, $contents);
		fclose($stream);

		if ($bytes_written === false || $bytes_written < strlen($contents)) {
			return new WP_Error('sftp_write_failed', 'The SFTP upload did not write the full CSV file.');
		}

		return true;
	}

	private function validate_settings($settings) {
		if (trim((string) $settings['host']) === '') {
			return new WP_Error('missing_host', 'SFTP host is required.');
		}

		if (trim((string) $settings['unique_identifier']) === '') {
			return new WP_Error('missing_identifier', 'ExpertVoice unique identifier is required.');
		}

		if (trim((string) $settings['username']) === '') {
			return new WP_Error('missing_username', 'SFTP username is required.');
		}

		if ((string) $settings['password'] === '') {
			return new WP_Error('missing_password', 'SFTP password is required.');
		}

		return true;
	}

	private function record_run($status, $message, $row_count) {
		$settings = self::get_settings();
		$settings['last_run_at'] = current_time('mysql');
		$settings['last_run_status'] = sanitize_text_field($status);
		$settings['last_run_message'] = sanitize_text_field($message);
		$settings['last_row_count'] = max(0, (int) $row_count);
		update_option(self::OPTION_KEY, $settings, false);
	}

	private static function get_next_run_timestamp($settings) {
		$timezone = new DateTimeZone(self::normalize_timezone((string) ($settings['timezone'] ?? wp_timezone_string())));
		$now = new DateTimeImmutable('now', $timezone);
		$time = self::normalize_send_time((string) ($settings['send_time'] ?? '02:00'));
		[$hour, $minute] = array_map('intval', explode(':', $time));
		$next = $now->setTime($hour, $minute, 0);

		if ($next <= $now) {
			$next = $next->modify('+1 day');
		}

		return $next->getTimestamp();
	}

	private static function normalize_send_time($value) {
		$value = trim($value);
		if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $matches)) {
			return '02:00';
		}

		return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
	}

	private static function normalize_timezone($value) {
		$value = trim($value);
		return in_array($value, timezone_identifiers_list(), true) ? $value : 'UTC';
	}

	private static function normalize_remote_path($value) {
		$value = trim($value);
		if ($value === '') {
			return '/incoming/';
		}

		$value = '/' . trim($value, '/') . '/';
		return $value === '//' ? '/incoming/' : $value;
	}

	private static function normalize_host($value) {
		$value = trim($value);
		$value = preg_replace('#^sftp://#i', '', $value);
		$value = preg_replace('#/.*$#', '', (string) $value);

		return sanitize_text_field((string) $value);
	}

	private function split_display_name($display_name) {
		$display_name = trim($display_name);
		if ($display_name === '') {
			return ['first_name' => '', 'last_name' => ''];
		}

		$parts = preg_split('/\s+/', $display_name);
		if (!is_array($parts) || count($parts) === 0) {
			return ['first_name' => '', 'last_name' => ''];
		}

		$first_name = array_shift($parts);

		return [
			'first_name' => (string) $first_name,
			'last_name' => implode(' ', $parts),
		];
	}

	public function render_admin_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$settings = self::get_settings();
		$next_run = wp_next_scheduled(self::CRON_HOOK);
		?>
		<div class="wrap">
			<h1>Daily Member Export</h1>
			<?php
			if (class_exists('AAC_Member_Portal_Member_Database')) {
				AAC_Member_Portal_Member_Database::render_database_tools_nav(self::MENU_SLUG);
			}
			?>

			<?php if (isset($_GET['aac_export_saved'])) : ?>
				<div class="notice notice-success is-dismissible"><p>Daily member export settings saved.</p></div>
			<?php endif; ?>

			<?php if (isset($_GET['aac_export_run'])) : ?>
				<?php $run_status = sanitize_text_field((string) wp_unslash($_GET['aac_export_run'])); ?>
				<div class="notice <?php echo $run_status === 'success' ? 'notice-success' : 'notice-error'; ?> is-dismissible">
					<p><?php echo esc_html($settings['last_run_message'] ?: 'Export run completed.'); ?></p>
				</div>
			<?php endif; ?>

			<p>This sends a daily CSV of active PMPro members to ExpertVoice over SFTP.</p>

			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<?php wp_nonce_field('aac_member_portal_save_daily_export'); ?>
				<input type="hidden" name="action" value="aac_member_portal_save_daily_export" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Enabled</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enabled]" value="1" <?php checked($settings['enabled'], '1'); ?> />
								Send the active member CSV once daily.
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aac-daily-export-time">Send time</label></th>
						<td>
							<input id="aac-daily-export-time" type="time" name="<?php echo esc_attr(self::OPTION_KEY); ?>[send_time]" value="<?php echo esc_attr($settings['send_time']); ?>" />
							<select name="<?php echo esc_attr(self::OPTION_KEY); ?>[timezone]">
								<?php foreach (timezone_identifiers_list() as $timezone) : ?>
									<option value="<?php echo esc_attr($timezone); ?>" <?php selected($settings['timezone'], $timezone); ?>><?php echo esc_html($timezone); ?></option>
								<?php endforeach; ?>
							</select>
							<?php if ($next_run) : ?>
								<p class="description">Next scheduled run: <?php echo esc_html(wp_date('Y-m-d H:i:s T', $next_run)); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aac-daily-export-host">SFTP host</label></th>
						<td><input id="aac-daily-export-host" class="regular-text" type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[host]" value="<?php echo esc_attr($settings['host']); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="aac-daily-export-port">SFTP port</label></th>
						<td><input id="aac-daily-export-port" type="number" min="1" max="65535" name="<?php echo esc_attr(self::OPTION_KEY); ?>[port]" value="<?php echo esc_attr((string) $settings['port']); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="aac-daily-export-path">Remote path</label></th>
						<td><input id="aac-daily-export-path" class="regular-text" type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[remote_path]" value="<?php echo esc_attr($settings['remote_path']); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="aac-daily-export-identifier">ExpertVoice unique identifier</label></th>
						<td>
							<input id="aac-daily-export-identifier" class="regular-text" type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[unique_identifier]" value="<?php echo esc_attr($settings['unique_identifier']); ?>" />
							<p class="description">The uploaded filename will be <code>&lt;unique_identifier&gt;.memberauth.csv</code>.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aac-daily-export-username">Username</label></th>
						<td><input id="aac-daily-export-username" class="regular-text" type="text" autocomplete="off" name="<?php echo esc_attr(self::OPTION_KEY); ?>[username]" value="<?php echo esc_attr($settings['username']); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="aac-daily-export-password">Password</label></th>
						<td>
							<input id="aac-daily-export-password" class="regular-text" type="password" autocomplete="new-password" name="<?php echo esc_attr(self::OPTION_KEY); ?>[password]" value="" />
							<p class="description"><?php echo $settings['password'] !== '' ? 'Password is saved. Enter a new password only to replace it.' : 'Enter the password provided by ExpertVoice.'; ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button('Save Export Settings'); ?>
			</form>

			<hr />

			<h2>Last Run</h2>
			<table class="widefat striped" style="max-width: 760px;">
				<tbody>
					<tr><th>Status</th><td><?php echo esc_html($settings['last_run_status'] ?: 'Not run yet'); ?></td></tr>
					<tr><th>Ran at</th><td><?php echo esc_html($settings['last_run_at'] ?: ''); ?></td></tr>
					<tr><th>Rows</th><td><?php echo esc_html((string) $settings['last_row_count']); ?></td></tr>
					<tr><th>Message</th><td><?php echo esc_html($settings['last_run_message']); ?></td></tr>
				</tbody>
			</table>

			<div style="display: flex; flex-wrap: wrap; gap: 12px; margin-top: 16px;">
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
					<?php wp_nonce_field('aac_member_portal_download_daily_export'); ?>
					<input type="hidden" name="action" value="aac_member_portal_download_daily_export" />
					<?php submit_button('Download CSV Report', 'secondary', 'submit', false); ?>
				</form>

				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
					<?php wp_nonce_field('aac_member_portal_run_daily_export'); ?>
					<input type="hidden" name="action" value="aac_member_portal_run_daily_export" />
					<?php submit_button('Send CSV to SFTP Now', 'secondary', 'submit', false); ?>
				</form>
			</div>
		</div>
		<?php
	}
}
