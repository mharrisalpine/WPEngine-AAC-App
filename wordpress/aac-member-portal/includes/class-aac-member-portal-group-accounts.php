<?php

if (!defined('ABSPATH')) {
	exit;
}

final class AAC_Member_Portal_Group_Accounts {
	const MIGRATION_OPTION = 'aac_member_portal_group_accounts_migration';

	public function __construct() {
		add_action('plugins_loaded', [$this, 'disable_native_parent_checkout_hooks'], 100);
		add_action('init', [$this, 'ensure_partner_group_settings'], 30);
		add_action('pmpro_after_change_membership_level', [$this, 'sync_parent_after_membership_change'], 30, 2);
		add_action('aac_member_portal_family_account_linked', [$this, 'sync_linked_family_account'], 10, 2);
		add_action('admin_post_aac_member_portal_migrate_group_accounts', [$this, 'handle_admin_migration']);
		add_action('admin_post_aac_member_portal_import_family_group_links', [$this, 'handle_admin_family_group_links_import']);
		add_action('show_user_profile', [$this, 'render_admin_parent_group_children'], 35);
		add_action('edit_user_profile', [$this, 'render_admin_parent_group_children'], 35);
	}

	public function disable_native_parent_checkout_hooks() {
		remove_action('pmpro_checkout_boxes', 'pmprogroupacct_pmpro_checkout_boxes_parent');
		remove_filter('pmpro_registration_checks', 'pmprogroupacct_pmpro_registration_checks_parent');
		remove_filter('pmpro_checkout_level', 'pmprogroupacct_pmpro_checkout_level_parent');
		remove_action('pmpro_after_checkout', 'pmprogroupacct_pmpro_after_checkout_parent');
	}

	public static function is_available() {
		return class_exists('PMProGroupAcct_Group')
			&& class_exists('PMProGroupAcct_Group_Member')
			&& function_exists('pmpro_changeMembershipLevel');
	}

	public function ensure_partner_group_settings() {
		if (!self::is_available() || !function_exists('get_pmpro_membership_level_meta') || !function_exists('update_pmpro_membership_level_meta')) {
			return;
		}

		$parent_level_id = self::get_level_id_by_name('Partner');
		$adult_level_id = self::get_level_id_by_name('Partner Adult');
		$dependent_level_id = self::get_level_id_by_name('Partner Dependent');
		if (!$parent_level_id || (!$adult_level_id && !$dependent_level_id)) {
			return;
		}

		$child_level_ids = array_values(array_filter(array_unique([$adult_level_id, $dependent_level_id])));
		$settings = function_exists('pmprogroupacct_get_settings_for_level')
			? pmprogroupacct_get_settings_for_level($parent_level_id)
			: get_pmpro_membership_level_meta($parent_level_id, 'pmprogroupacct_settings', true);
		$settings = is_array($settings) ? $settings : [];
		$current_child_ids = isset($settings['child_level_ids']) && is_array($settings['child_level_ids'])
			? array_map('intval', $settings['child_level_ids'])
			: [];

		$next_settings = array_merge([
			'child_level_ids' => [],
			'min_seats' => 0,
			'max_seats' => 20,
			'pricing_model' => 'none',
			'pricing_model_settings' => '0',
			'price_application' => 'both',
		], $settings);
		$next_settings['child_level_ids'] = array_values(array_unique(array_merge($current_child_ids, $child_level_ids)));
		if ((int) $next_settings['max_seats'] < 1) {
			$next_settings['max_seats'] = 20;
		}

		if ($next_settings !== $settings) {
			update_pmpro_membership_level_meta($parent_level_id, 'pmprogroupacct_settings', $next_settings);
		}
	}

	public function sync_parent_after_membership_change($level_id, $user_id) {
		$parent_level_id = self::get_level_id_by_name('Partner');
		if ((int) $level_id !== (int) $parent_level_id) {
			return;
		}

		self::sync_parent_group((int) $user_id);
	}

	public function sync_linked_family_account($parent_user_id, $child_user_id) {
		$parent_user_id = (int) $parent_user_id;
		$child_user_id = (int) $child_user_id;
		if ($parent_user_id <= 0 || $child_user_id <= 0) {
			return;
		}

		$slot = self::get_child_slot($parent_user_id, $child_user_id);
		if (!$slot) {
			return;
		}

		self::sync_parent_group($parent_user_id);
		self::link_child_to_group($parent_user_id, $child_user_id, $slot);
	}

	public function handle_admin_migration() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to migrate group accounts.', 'aac-member-portal'));
		}

		check_admin_referer('aac_member_portal_migrate_group_accounts');

		$result = self::migrate_existing_family_accounts();
		$redirect_args = [
			'page' => 'aac-member-portal-settings',
			'tab' => 'linked_accounts',
			'aac_group_accounts_migrated' => is_wp_error($result) ? 'error' : 'success',
		];
		if (is_wp_error($result)) {
			$redirect_args['aac_group_accounts_message'] = rawurlencode($result->get_error_message());
		}

		wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
		exit;
	}

	public function handle_admin_family_group_links_import() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to import family group links.', 'aac-member-portal'));
		}

		check_admin_referer('aac_member_portal_import_family_group_links');

		if (empty($_FILES['family_group_links_csv']['tmp_name']) || !is_uploaded_file($_FILES['family_group_links_csv']['tmp_name'])) {
			$result = new WP_Error('missing_file', 'Upload a CSV file containing family parent/child link rows.');
		} else {
			$result = self::import_family_group_links_csv((string) $_FILES['family_group_links_csv']['tmp_name']);
		}

		$redirect_args = [
			'page' => 'aac-member-portal-settings',
			'tab' => 'linked_accounts',
			'aac_family_group_links_imported' => is_wp_error($result) ? 'error' : 'success',
		];
		if (is_wp_error($result)) {
			$redirect_args['aac_family_group_links_message'] = rawurlencode($result->get_error_message());
		} else {
			$redirect_args['aac_family_group_links_message'] = rawurlencode(sprintf(
				'Imported %d rows. Linked %d children. Skipped %d rows.',
				(int) ($result['rows_checked'] ?? 0),
				(int) ($result['children_linked'] ?? 0),
				(int) ($result['rows_skipped'] ?? 0)
			));
		}

		wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
		exit;
	}

	public function render_admin_parent_group_children($user) {
		if (!current_user_can('manage_options') || !$user instanceof WP_User) {
			return;
		}

		$summary = self::get_group_summary_for_parent((int) $user->ID);
		$accounts = self::get_connected_accounts_for_parent((int) $user->ID);
		?>
		<h2><?php esc_html_e('AAC Family Group Children', 'aac-member-portal'); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th><?php esc_html_e('Owned group', 'aac-member-portal'); ?></th>
					<td>
						<?php if ($summary) : ?>
							<p>
								<strong><?php echo esc_html(sprintf('Group #%d', (int) ($summary['id'] ?? 0))); ?></strong>
								<?php if (!empty($summary['checkout_code'])) : ?>
									<br /><code><?php echo esc_html($summary['checkout_code']); ?></code>
								<?php endif; ?>
								<br />
								<?php echo esc_html(sprintf(
									'%d active child account(s) / %d total seat(s)',
									(int) ($summary['active_members'] ?? 0),
									(int) ($summary['total_seats'] ?? 0)
								)); ?>
							</p>
							<?php if (!empty($summary['manage_url'])) : ?>
								<p><a class="button" href="<?php echo esc_url($summary['manage_url']); ?>"><?php esc_html_e('Open PMPro Group', 'aac-member-portal'); ?></a></p>
							<?php endif; ?>
						<?php else : ?>
							<p class="description"><?php esc_html_e('This user does not own a PMPro Group Account.', 'aac-member-portal'); ?></p>
						<?php endif; ?>
						<p class="description"><?php esc_html_e('PMPro’s “Manage Child Memberships” profile box shows groups where this user is a child member. Parent-owned AAC family members are listed below.', 'aac-member-portal'); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e('Child accounts', 'aac-member-portal'); ?></th>
					<td>
						<?php if (!empty($accounts)) : ?>
							<table class="widefat striped" style="max-width: 980px;">
								<thead>
									<tr>
										<th><?php esc_html_e('Name', 'aac-member-portal'); ?></th>
										<th><?php esc_html_e('Email', 'aac-member-portal'); ?></th>
										<th><?php esc_html_e('Type', 'aac-member-portal'); ?></th>
										<th><?php esc_html_e('Status', 'aac-member-portal'); ?></th>
										<th><?php esc_html_e('Invite Code', 'aac-member-portal'); ?></th>
										<th><?php esc_html_e('User', 'aac-member-portal'); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($accounts as $account) : ?>
										<?php
										$child_user_id = absint($account['child_user_id'] ?? 0);
										$edit_url = $child_user_id > 0 ? get_edit_user_link($child_user_id) : '';
										?>
										<tr>
											<td><?php echo esc_html((string) ($account['child_name'] ?? $account['label'] ?? 'Family member')); ?></td>
											<td><?php echo esc_html((string) ($account['child_email'] ?? '')); ?></td>
											<td><?php echo esc_html(ucfirst((string) ($account['type'] ?? 'dependent'))); ?></td>
											<td><?php echo esc_html(ucwords(str_replace('_', ' ', (string) ($account['status'] ?? 'connected')))); ?></td>
											<td><code><?php echo esc_html((string) ($account['invite_code'] ?? '')); ?></code></td>
											<td>
												<?php if ($edit_url) : ?>
													<a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html(sprintf('User #%d', $child_user_id)); ?></a>
												<?php else : ?>
													<?php esc_html_e('Not linked', 'aac-member-portal'); ?>
												<?php endif; ?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php else : ?>
							<p class="description"><?php esc_html_e('No AAC family child accounts were found for this parent account.', 'aac-member-portal'); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	public static function import_family_group_links_csv($file_path) {
		if (!self::is_available()) {
			return new WP_Error('group_accounts_unavailable', 'PMPro Group Accounts is not active or its API is unavailable.');
		}

		$handle = fopen($file_path, 'r');
		if (!$handle) {
			return new WP_Error('unreadable_file', 'The uploaded family links CSV could not be read.');
		}

		$raw_headers = fgetcsv($handle);
		if (!is_array($raw_headers) || empty($raw_headers)) {
			fclose($handle);
			return new WP_Error('missing_headers', 'The uploaded family links CSV does not have a header row.');
		}

		$headers = array_map([__CLASS__, 'normalize_csv_header'], $raw_headers);
		$stats = [
			'rows_checked' => 0,
			'children_linked' => 0,
			'rows_skipped' => 0,
			'errors' => [],
			'imported_at' => current_time('mysql'),
		];

		while (($values = fgetcsv($handle)) !== false) {
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

			$stats['rows_checked']++;
			$result = self::link_imported_family_group_row($row);
			if (is_wp_error($result)) {
				$stats['rows_skipped']++;
				$stats['errors'][] = sprintf('Row %d: %s', $stats['rows_checked'] + 1, $result->get_error_message());
				continue;
			}

			$stats['children_linked']++;
		}

		fclose($handle);
		update_option(self::MIGRATION_OPTION, array_merge((array) get_option(self::MIGRATION_OPTION, []), [
			'family_links_import' => $stats,
			'migrated_at' => current_time('mysql'),
		]), false);

		return $stats;
	}

	private static function link_imported_family_group_row($row) {
		$child_user = self::resolve_child_from_import_row($row);
		if (!$child_user instanceof WP_User || !$child_user->exists()) {
			return new WP_Error('missing_child', 'Could not resolve child user from row.');
		}

		$parent_user = self::resolve_import_parent_for_child((int) $child_user->ID, $row);
		if (!$parent_user instanceof WP_User || !$parent_user->exists()) {
			return new WP_Error('missing_parent', 'Could not resolve parent user from row.');
		}

		if ((int) $parent_user->ID === (int) $child_user->ID) {
			return new WP_Error('parent_child_match', 'Parent and child resolve to the same user.');
		}

		$slot = self::ensure_imported_child_slot((int) $parent_user->ID, (int) $child_user->ID, $row);
		if (!$slot) {
			return new WP_Error('missing_slot', 'Could not create or update a family slot for this row.');
		}

		if (!self::link_child_to_group((int) $parent_user->ID, (int) $child_user->ID, $slot)) {
			return new WP_Error('group_link_failed', 'Could not attach child to PMPro Group Account.');
		}

		do_action('aac_member_portal_family_account_linked', (int) $parent_user->ID, (int) $child_user->ID);
		return true;
	}

	public static function migrate_existing_family_accounts() {
		if (!self::is_available()) {
			return new WP_Error('group_accounts_unavailable', 'PMPro Group Accounts is not active or its API is unavailable.');
		}

		$users = get_users([
			'meta_key' => 'aac_connected_accounts',
			'number' => 1000,
			'fields' => 'ids',
		]);
		$stats = [
			'parents_checked' => 0,
			'groups_synced' => 0,
			'children_linked' => 0,
			'imported_children_checked' => 0,
			'imported_children_linked' => 0,
			'imported_children_unresolved' => 0,
			'errors' => [],
			'migrated_at' => current_time('mysql'),
		];

		foreach ($users as $parent_user_id) {
			$parent_user_id = (int) $parent_user_id;
			$stats['parents_checked']++;
			$group = self::sync_parent_group($parent_user_id);
			if (!$group) {
				continue;
			}

			$stats['groups_synced']++;
			$accounts = self::get_parent_slots($parent_user_id);
			foreach ($accounts as $slot) {
				$child_user_id = absint($slot['child_user_id'] ?? 0);
				if ($child_user_id <= 0 || ($slot['status'] ?? '') === 'removal_pending') {
					continue;
				}

				$linked = self::link_child_to_group($parent_user_id, $child_user_id, $slot);
				if ($linked) {
					$stats['children_linked']++;
				}
			}
		}

		self::migrate_imported_family_accounts($stats);

		update_option(self::MIGRATION_OPTION, $stats, false);
		return $stats;
	}

	private static function migrate_imported_family_accounts(&$stats) {
		$children = self::get_imported_family_child_users();
		foreach ($children as $child_user_id) {
			$child_user_id = (int) $child_user_id;
			if ($child_user_id <= 0) {
				continue;
			}

			$stats['imported_children_checked']++;
			$parent_user = self::resolve_import_parent_for_child($child_user_id);
			if (!$parent_user instanceof WP_User || !$parent_user->exists() || (int) $parent_user->ID === $child_user_id) {
				$stats['imported_children_unresolved']++;
				$stats['errors'][] = sprintf('Could not resolve imported parent for child user ID %d.', $child_user_id);
				continue;
			}

			$slot = self::ensure_imported_child_slot((int) $parent_user->ID, $child_user_id);
			if (!$slot) {
				$stats['errors'][] = sprintf('Could not create family slot for parent user ID %d and child user ID %d.', (int) $parent_user->ID, $child_user_id);
				continue;
			}

			$group = self::sync_parent_group((int) $parent_user->ID);
			if ($group) {
				$stats['groups_synced']++;
			}

			if (self::link_child_to_group((int) $parent_user->ID, $child_user_id, $slot)) {
				$stats['children_linked']++;
				$stats['imported_children_linked']++;
				do_action('aac_member_portal_family_account_linked', (int) $parent_user->ID, $child_user_id);
			}
		}
	}

	private static function get_imported_family_child_users() {
		$meta_keys = [
			'aac_linked_parent_user_id',
			'aac_linked_parent_member_id',
			'aac_linked_account_type',
			'aac_linked_account_slot_id',
			'pmprogroupacct_group_id',
			'pmprogroupacct_group_code',
			'pmprogroupacct_group_order',
			'pmprogroupacct_group_order_id',
			'pmprogroupacct_parent_user_login',
			'pmprogroupacct_parent_user_email',
			'group_order',
			'group_order_id',
			'aac_group_order',
			'aac_group_order_id',
		];
		$meta_query = ['relation' => 'OR'];
		foreach ($meta_keys as $meta_key) {
			$meta_query[] = [
				'key' => $meta_key,
				'compare' => 'EXISTS',
			];
		}

		$users = get_users([
			'number' => -1,
			'fields' => 'ids',
			'meta_query' => $meta_query,
		]);

		return array_values(array_unique(array_map('intval', (array) $users)));
	}

	private static function resolve_import_parent_for_child($child_user_id, $row = []) {
		$parent_login = sanitize_user(self::row_value($row, ['pmprogroupacct_parent_user_login', 'parent_user_login', 'parent_login']) ?: (string) get_user_meta($child_user_id, 'pmprogroupacct_parent_user_login', true), true);
		if ($parent_login !== '') {
			$user = get_user_by('login', $parent_login);
			if ($user instanceof WP_User) {
				return $user;
			}
		}

		$parent_email = sanitize_email(self::row_value($row, ['pmprogroupacct_parent_user_email', 'parent_user_email', 'parent_email']) ?: (string) get_user_meta($child_user_id, 'pmprogroupacct_parent_user_email', true));
		if ($parent_email !== '') {
			$user = get_user_by('email', $parent_email);
			if ($user instanceof WP_User) {
				return $user;
			}
		}

		$parent_member_id = sanitize_text_field(self::row_value($row, ['aac_linked_parent_member_id', 'parent_member_id', 'linked_parent_member_id']) ?: (string) get_user_meta($child_user_id, 'aac_linked_parent_member_id', true));
		if ($parent_member_id !== '') {
			$user = self::get_user_by_meta_value('aac_member_id', $parent_member_id);
			if ($user instanceof WP_User) {
				return $user;
			}
		}

		$parent_user_id = absint(self::row_value($row, ['aac_linked_parent_user_id', 'linked_parent_user_id', 'parent_user_id']) ?: get_user_meta($child_user_id, 'aac_linked_parent_user_id', true));
		if ($parent_user_id > 0) {
			$user = get_user_by('id', $parent_user_id);
			if ($user instanceof WP_User) {
				return $user;
			}
		}

		$group = self::resolve_group_from_imported_child($child_user_id, $row);
		if ($group && !empty($group->group_parent_user_id)) {
			$user = get_user_by('id', (int) $group->group_parent_user_id);
			if ($user instanceof WP_User) {
				return $user;
			}
		}

		return null;
	}

	private static function resolve_group_from_imported_child($child_user_id, $row = []) {
		$group_id = self::first_non_placeholder_row_or_meta($child_user_id, $row, [
			'pmprogroupacct_group_id',
			'aac_group_account_group_id',
		]);
		if ($group_id !== '' && ctype_digit($group_id)) {
			$group = self::get_group_by_id((int) $group_id);
			if ($group) {
				return $group;
			}
		}

		$group_code = self::first_non_placeholder_row_or_meta($child_user_id, $row, [
			'pmprogroupacct_group_code',
			'pmprogroupacct_group_checkout_code',
			'aac_group_account_checkout_code',
		]);
		if ($group_code !== '') {
			$group = self::get_group_by_column_value('group_checkout_code', $group_code);
			if ($group) {
				return $group;
			}
		}

		$group_order = self::first_non_placeholder_row_or_meta($child_user_id, $row, [
			'pmprogroupacct_group_order',
			'pmprogroupacct_group_order_id',
			'group_order',
			'group_order_id',
			'aac_group_order',
			'aac_group_order_id',
		]);
		if ($group_order !== '') {
			foreach (['group_order_id', 'group_parent_order_id', 'parent_order_id', 'order_id'] as $column) {
				$group = self::get_group_by_column_value($column, $group_order);
				if ($group) {
					return $group;
				}
			}
		}

		return null;
	}

	private static function ensure_imported_child_slot($parent_user_id, $child_user_id, $row = []) {
		$parent_user_id = (int) $parent_user_id;
		$child_user_id = (int) $child_user_id;
		$child_user = get_user_by('id', $child_user_id);
		if (!$child_user instanceof WP_User) {
			return null;
		}

		$accounts = self::get_parent_slots($parent_user_id);
		$slot_id = sanitize_text_field(self::row_value($row, ['aac_linked_account_slot_id', 'linked_account_slot_id', 'slot_id']) ?: (string) get_user_meta($child_user_id, 'aac_linked_account_slot_id', true));
		if ($slot_id === '') {
			$slot_id = 'import-' . $parent_user_id . '-' . $child_user_id;
		}

		$member_type = self::normalize_imported_member_type(self::row_value($row, ['aac_linked_account_type', 'linked_account_type', 'relationship', 'family_role']) ?: get_user_meta($child_user_id, 'aac_linked_account_type', true));
		if ($member_type === '') {
			$member_type = self::normalize_imported_member_type(get_user_meta($child_user_id, 'family_role', true));
		}
		if ($member_type === '') {
			$member_type = self::infer_child_type_from_membership($child_user_id);
		}
		$member_type = $member_type ?: 'dependent';

		$invite_code = sanitize_text_field(self::row_value($row, ['aac_linked_account_invite_code', 'linked_account_invite_code', 'invite_code']) ?: (string) get_user_meta($child_user_id, 'aac_linked_account_invite_code', true));
		if ($invite_code === '') {
			$invite_code = 'AACF-IMPORT' . $child_user_id;
		}

		$label = sanitize_text_field(self::row_value($row, ['aac_linked_account_label', 'linked_account_label', 'relationship_to_primary']) ?: (string) get_user_meta($child_user_id, 'aac_linked_account_label', true));
		if ($label === '') {
			$label = $member_type === 'adult' ? 'Additional adult' : self::get_next_imported_dependent_label($accounts, $child_user_id);
		}

		$slot = [
			'id' => $slot_id,
			'type' => $member_type,
			'label' => $label,
			'status' => 'connected',
			'invite_code' => $invite_code,
			'child_user_id' => $child_user_id,
			'child_name' => trim($child_user->first_name . ' ' . $child_user->last_name) ?: $child_user->display_name,
			'child_email' => $child_user->user_email,
			'price' => $member_type === 'adult' ? 80.0 : 45.0,
			'scheduled_removal_date' => '',
		];

		$updated = false;
		foreach ($accounts as $index => $existing_slot) {
			if (!is_array($existing_slot)) {
				continue;
			}

			$existing_slot_id = sanitize_text_field((string) ($existing_slot['id'] ?? ''));
			$existing_child_user_id = absint($existing_slot['child_user_id'] ?? 0);
			if (($existing_slot_id !== '' && $existing_slot_id === $slot_id) || $existing_child_user_id === $child_user_id) {
				$accounts[$index] = array_merge($existing_slot, $slot);
				$updated = true;
				break;
			}
		}

		if (!$updated) {
			$accounts[] = $slot;
		}

		update_user_meta($parent_user_id, 'aac_connected_accounts', array_values($accounts));
		update_user_meta($parent_user_id, 'aac_family_account_role', 'Parent');
		update_user_meta($child_user_id, 'aac_linked_parent_user_id', $parent_user_id);
		update_user_meta($child_user_id, 'aac_linked_account_slot_id', $slot_id);
		update_user_meta($child_user_id, 'aac_linked_account_invite_code', $invite_code);
		update_user_meta($child_user_id, 'aac_linked_account_type', $member_type);
		update_user_meta($child_user_id, 'aac_linked_account_label', $label);
		update_user_meta($child_user_id, 'aac_family_account_role', 'Child');
		self::update_parent_family_config_from_slots($parent_user_id, $accounts);

		return $slot;
	}

	private static function normalize_imported_member_type($value) {
		$value = strtolower(trim((string) $value));
		if ($value === '') {
			return '';
		}

		if (strpos($value, 'adult') !== false) {
			return 'adult';
		}

		if (strpos($value, 'dependent') !== false || strpos($value, 'child') !== false) {
			return 'dependent';
		}

		return in_array($value, ['adult', 'dependent'], true) ? $value : '';
	}

	private static function infer_child_type_from_membership($child_user_id) {
		if (!function_exists('pmpro_getMembershipLevelsForUser')) {
			return '';
		}

		$adult_level_id = self::get_level_id_by_name('Partner Adult');
		$dependent_level_id = self::get_level_id_by_name('Partner Dependent');
		foreach ((array) pmpro_getMembershipLevelsForUser((int) $child_user_id) as $level) {
			$level_id = (int) ($level->id ?? 0);
			if ($adult_level_id && $level_id === $adult_level_id) {
				return 'adult';
			}
			if ($dependent_level_id && $level_id === $dependent_level_id) {
				return 'dependent';
			}
		}

		return '';
	}

	private static function get_next_imported_dependent_label($accounts, $child_user_id) {
		$count = 0;
		foreach ((array) $accounts as $slot) {
			if (!is_array($slot) || absint($slot['child_user_id'] ?? 0) === (int) $child_user_id) {
				continue;
			}
			if (sanitize_key((string) ($slot['type'] ?? '')) === 'dependent') {
				$count++;
			}
		}

		return sprintf('Dependent %d', $count + 1);
	}

	private static function update_parent_family_config_from_slots($parent_user_id, $accounts) {
		$dependent_count = 0;
		$has_adult = false;
		foreach ((array) $accounts as $slot) {
			if (!is_array($slot) || ($slot['status'] ?? '') === 'removal_pending') {
				continue;
			}
			if (($slot['type'] ?? '') === 'adult') {
				$has_adult = true;
			} elseif (($slot['type'] ?? '') === 'dependent') {
				$dependent_count++;
			}
		}

		update_user_meta((int) $parent_user_id, 'aac_partner_family_config', [
			'mode' => ($has_adult || $dependent_count > 0) ? 'family' : '',
			'additional_adult' => $has_adult,
			'dependent_count' => $dependent_count,
		]);
		update_user_meta((int) $parent_user_id, 'aac_family_mode', ($has_adult || $dependent_count > 0) ? 'family' : '');
		update_user_meta((int) $parent_user_id, 'aac_family_additional_adult', $has_adult ? '1' : '0');
		update_user_meta((int) $parent_user_id, 'aac_family_dependent_count', (string) $dependent_count);
	}

	private static function first_non_placeholder_meta($user_id, $keys) {
		foreach ((array) $keys as $key) {
			$value = sanitize_text_field((string) get_user_meta((int) $user_id, $key, true));
			if ($value === '' || strtoupper($value) === 'FILL_AFTER_PARENT_IMPORT') {
				continue;
			}
			return $value;
		}

		return '';
	}

	private static function first_non_placeholder_row_or_meta($user_id, $row, $keys) {
		foreach ((array) $keys as $key) {
			$value = sanitize_text_field(self::row_value($row, [$key]));
			if ($value !== '' && strtoupper($value) !== 'FILL_AFTER_PARENT_IMPORT') {
				return $value;
			}
		}

		return self::first_non_placeholder_meta($user_id, $keys);
	}

	private static function resolve_child_from_import_row($row) {
		$user_id = absint(self::row_value($row, ['child_user_id', 'wp_user_id', 'user_id']));
		if ($user_id > 0) {
			$user = get_user_by('id', $user_id);
			if ($user instanceof WP_User) {
				return $user;
			}
		}

		$email = sanitize_email(self::row_value($row, ['child_email', 'child_user_email', 'wp_user_email', 'user_email']));
		if ($email !== '') {
			$user = get_user_by('email', $email);
			if ($user instanceof WP_User) {
				return $user;
			}
		}

		$login = sanitize_user(self::row_value($row, ['child_user_login', 'wp_user_login', 'user_login']), true);
		if ($login !== '') {
			$user = get_user_by('login', $login);
			if ($user instanceof WP_User) {
				return $user;
			}
		}

		$member_id = sanitize_text_field(self::row_value($row, ['child_member_id', 'member_id', 'aac_member_id']));
		if ($member_id !== '') {
			$user = self::get_user_by_meta_value('aac_member_id', $member_id);
			if ($user instanceof WP_User) {
				return $user;
			}
		}

		return null;
	}

	private static function row_value($row, $keys) {
		if (!is_array($row)) {
			return '';
		}

		foreach ((array) $keys as $key) {
			$normalized_key = self::normalize_csv_header($key);
			if (array_key_exists($normalized_key, $row) && trim((string) $row[$normalized_key]) !== '') {
				return trim((string) $row[$normalized_key]);
			}
		}

		return '';
	}

	private static function normalize_csv_header($header) {
		$header = strtolower(trim((string) $header));
		$header = preg_replace('/[^a-z0-9_]+/', '_', $header);
		$header = preg_replace('/_+/', '_', (string) $header);
		return trim((string) $header, '_');
	}

	private static function get_user_by_meta_value($meta_key, $meta_value) {
		$users = get_users([
			'meta_key' => $meta_key,
			'meta_value' => $meta_value,
			'number' => 1,
			'fields' => 'all',
		]);

		$user = is_array($users) ? reset($users) : null;
		return $user instanceof WP_User ? $user : null;
	}

	public static function sync_parent_group($parent_user_id, $accounts = null) {
		if (!self::is_available()) {
			return null;
		}

		$parent_user_id = (int) $parent_user_id;
		if ($parent_user_id <= 0) {
			return null;
		}

		$parent_level_id = self::get_level_id_by_name('Partner');
		if (!$parent_level_id) {
			return null;
		}

		$accounts = is_array($accounts) ? $accounts : self::get_parent_slots($parent_user_id);
		$total_seats = self::count_billable_slots($accounts);
		$group = PMProGroupAcct_Group::get_group_by_parent_user_id_and_parent_level_id($parent_user_id, $parent_level_id);
		if ($total_seats <= 0) {
			if ($group && (int) $group->group_total_seats !== 0) {
				$group->update_group_total_seats(0);
			}
			return $group;
		}

		if ($group) {
			if ((int) $group->group_total_seats !== $total_seats) {
				$group->update_group_total_seats($total_seats);
			}
		} else {
			$group = PMProGroupAcct_Group::create($parent_user_id, $parent_level_id, $total_seats);
		}

		if ($group) {
			update_user_meta($parent_user_id, 'aac_group_account_group_id', (int) $group->id);
			update_user_meta($parent_user_id, 'aac_group_account_checkout_code', sanitize_text_field($group->group_checkout_code));
			update_user_meta($parent_user_id, 'aac_group_account_synced_at', current_time('mysql'));
		}

		return $group ?: null;
	}

	public static function link_child_to_group($parent_user_id, $child_user_id, $slot) {
		if (!self::is_available()) {
			return false;
		}

		$parent_user_id = (int) $parent_user_id;
		$child_user_id = (int) $child_user_id;
		if ($parent_user_id <= 0 || $child_user_id <= 0 || $parent_user_id === $child_user_id) {
			return false;
		}

		$group = self::sync_parent_group($parent_user_id);
		if (!$group) {
			return false;
		}

		$child_level_id = self::get_child_level_id_for_slot($slot);
		if (!$child_level_id) {
			return false;
		}

		$user_level = function_exists('pmpro_getSpecificMembershipLevelForUser')
			? pmpro_getSpecificMembershipLevelForUser($child_user_id, $child_level_id)
			: null;
		if (!empty($user_level) && function_exists('pmpro_cancelMembershipLevel')) {
			pmpro_cancelMembershipLevel($child_level_id, $child_user_id);
			if (function_exists('pmpro_do_action_after_all_membership_level_changes')) {
				pmpro_do_action_after_all_membership_level_changes();
			}
		}

		$changed = pmpro_changeMembershipLevel($child_level_id, $child_user_id, true);
		if (!$changed) {
			return false;
		}
		if (function_exists('pmpro_do_action_after_all_membership_level_changes')) {
			pmpro_do_action_after_all_membership_level_changes();
		}

		$existing_members = PMProGroupAcct_Group_Member::get_group_members([
			'group_id' => (int) $group->id,
			'group_child_user_id' => $child_user_id,
			'group_child_level_id' => $child_level_id,
		]);
		if (!empty($existing_members)) {
			foreach ($existing_members as $member) {
				if (($member->group_child_status ?? '') !== 'active') {
					$member->update_group_child_status('active');
				}
			}
		} else {
			PMProGroupAcct_Group_Member::create($child_user_id, $child_level_id, (int) $group->id);
		}

		update_user_meta($child_user_id, 'aac_group_account_group_id', (int) $group->id);
		update_user_meta($child_user_id, 'aac_group_account_child_level_id', $child_level_id);
		update_user_meta($child_user_id, 'aac_group_account_synced_at', current_time('mysql'));
		return true;
	}

	public static function get_group_summary_for_parent($parent_user_id) {
		if (!self::is_available()) {
			return null;
		}

		$group = self::get_group_for_parent((int) $parent_user_id);
		if (!$group) {
			return null;
		}

		return [
			'id' => (int) $group->id,
			'checkout_code' => sanitize_text_field($group->group_checkout_code),
			'total_seats' => (int) $group->group_total_seats,
			'active_members' => (int) $group->get_active_members(true),
			'manage_url' => self::get_manage_group_url($group),
		];
	}

	public static function get_group_summary_for_user($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return null;
		}

		$parent_user_id = absint(get_user_meta($user_id, 'aac_linked_parent_user_id', true));
		$is_child = $parent_user_id > 0 && $parent_user_id !== $user_id;
		$group_owner_user_id = $is_child ? $parent_user_id : $user_id;
		$summary = self::get_group_summary_for_parent($group_owner_user_id);
		if (!$summary) {
			return null;
		}

		return array_merge($summary, [
			'parent_user_id' => $group_owner_user_id,
			'account_role' => $is_child ? 'Child' : 'Parent',
		]);
	}

	public static function get_connected_accounts_for_parent($parent_user_id, $repair_meta = true) {
		if (!self::is_available()) {
			return [];
		}

		$parent_user_id = (int) $parent_user_id;
		if ($parent_user_id <= 0) {
			return [];
		}

		$group = self::get_group_for_parent($parent_user_id);
		if (!$group || empty($group->id)) {
			return [];
		}

		$members = self::get_group_members_for_group((int) $group->id);
		if (empty($members)) {
			return [];
		}

		$accounts = [];
		$adult_count = 0;
		$dependent_count = 0;
		foreach ($members as $member) {
			$member = is_array($member) ? $member : (array) $member;
			$child_user_id = self::get_first_int_value($member, [
				'group_child_user_id',
				'child_user_id',
				'group_user_id',
				'member_user_id',
				'user_id',
			]);
			if ($child_user_id <= 0 || $child_user_id === $parent_user_id) {
				continue;
			}

			$child_user = get_user_by('id', $child_user_id);
			if (!$child_user instanceof WP_User) {
				continue;
			}

			$status = sanitize_key((string) self::get_first_value($member, ['group_child_status', 'child_status', 'status']));
			if ($status === '') {
				$status = 'active';
			}
			if (in_array($status, ['inactive', 'cancelled', 'canceled', 'expired'], true)) {
				continue;
			}

			$type = self::infer_group_member_type($child_user_id, self::get_first_int_value($member, [
				'group_child_level_id',
				'child_level_id',
				'membership_id',
				'level_id',
			]));
			if ($type === 'adult') {
				$adult_count++;
				$label = sanitize_text_field((string) get_user_meta($child_user_id, 'aac_linked_account_label', true)) ?: 'Additional adult';
			} else {
				$dependent_count++;
				$label = sanitize_text_field((string) get_user_meta($child_user_id, 'aac_linked_account_label', true)) ?: sprintf('Dependent %d', $dependent_count);
			}

			$slot_id = sanitize_text_field((string) get_user_meta($child_user_id, 'aac_linked_account_slot_id', true));
			if ($slot_id === '') {
				$slot_id = 'pmpro-group-' . (int) $group->id . '-' . $child_user_id;
			}

			$invite_code = sanitize_text_field((string) get_user_meta($child_user_id, 'aac_linked_account_invite_code', true));
			if ($invite_code === '') {
				$invite_code = sanitize_text_field((string) ($group->group_checkout_code ?? ''));
			}

			$pending_removal = get_user_meta($child_user_id, 'aac_family_membership_pending_removal', true) === '1';
			$accounts[] = [
				'id' => $slot_id,
				'type' => $type,
				'label' => $label,
				'status' => $pending_removal ? 'removal_pending' : 'connected',
				'invite_code' => $invite_code,
				'child_user_id' => $child_user_id,
				'child_name' => trim($child_user->first_name . ' ' . $child_user->last_name) ?: $child_user->display_name,
				'child_email' => $child_user->user_email,
				'price' => $type === 'adult' ? 80.0 : 45.0,
				'scheduled_removal_date' => sanitize_text_field((string) get_user_meta($child_user_id, 'aac_family_membership_access_until', true)),
			];
		}

		$accounts = self::dedupe_connected_accounts($accounts);
		if ($repair_meta && !empty($accounts)) {
			update_user_meta($parent_user_id, 'aac_connected_accounts', $accounts);
			update_user_meta($parent_user_id, 'aac_family_account_role', 'Parent');
			update_user_meta($parent_user_id, 'aac_group_account_group_id', (int) $group->id);
			update_user_meta($parent_user_id, 'aac_group_account_checkout_code', sanitize_text_field((string) ($group->group_checkout_code ?? '')));
			self::update_parent_family_config_from_slots($parent_user_id, $accounts);

			foreach ($accounts as $account) {
				$child_user_id = absint($account['child_user_id'] ?? 0);
				if ($child_user_id <= 0) {
					continue;
				}
				update_user_meta($child_user_id, 'aac_linked_parent_user_id', $parent_user_id);
				update_user_meta($child_user_id, 'aac_linked_account_slot_id', sanitize_text_field((string) ($account['id'] ?? '')));
				update_user_meta($child_user_id, 'aac_linked_account_invite_code', sanitize_text_field((string) ($account['invite_code'] ?? '')));
				update_user_meta($child_user_id, 'aac_linked_account_type', sanitize_key((string) ($account['type'] ?? 'dependent')));
				update_user_meta($child_user_id, 'aac_linked_account_label', sanitize_text_field((string) ($account['label'] ?? 'Family member')));
				update_user_meta($child_user_id, 'aac_family_account_role', 'Child');
			}
		}

		return $accounts;
	}

	public static function get_family_config_for_parent($parent_user_id) {
		$accounts = self::get_connected_accounts_for_parent($parent_user_id);
		$dependent_count = 0;
		$has_adult = false;
		foreach ($accounts as $account) {
			if (!is_array($account) || ($account['status'] ?? '') === 'removal_pending') {
				continue;
			}
			if (($account['type'] ?? '') === 'adult') {
				$has_adult = true;
			} elseif (($account['type'] ?? '') === 'dependent') {
				$dependent_count++;
			}
		}

		if (!$has_adult && $dependent_count === 0) {
			$summary = self::get_group_summary_for_parent($parent_user_id);
			if ($summary && (int) ($summary['total_seats'] ?? 0) > 0) {
				$dependent_count = max(1, (int) ($summary['total_seats'] ?? 0));
			}
		}

		return [
			'mode' => ($has_adult || $dependent_count > 0) ? 'family' : '',
			'additional_adult' => $has_adult,
			'dependent_count' => $dependent_count,
		];
	}

	public static function get_invite_url_for_parent_slot($parent_user_id, $slot) {
		if (!self::is_available() || !function_exists('pmpro_url')) {
			return '';
		}

		$summary = self::get_group_summary_for_parent($parent_user_id);
		if (!$summary || empty($summary['checkout_code'])) {
			$group = self::sync_parent_group($parent_user_id);
			$summary = $group ? self::get_group_summary_for_parent($parent_user_id) : null;
		}
		if (!$summary || empty($summary['checkout_code'])) {
			return '';
		}

		$child_level_id = self::get_child_level_id_for_slot($slot);
		if (!$child_level_id) {
			return '';
		}

		return add_query_arg([
			'level' => $child_level_id,
			'pmprogroupacct_group_code' => $summary['checkout_code'],
		], pmpro_url('checkout'));
	}

	public static function get_manage_group_url($group) {
		if (!$group || empty($group->id)) {
			return '';
		}

		if (function_exists('pmpro_url')) {
			$manage_url = pmpro_url('pmprogroupacct_manage_group');
			if (!empty($manage_url)) {
				return add_query_arg('pmprogroupacct_group_id', (int) $group->id, $manage_url);
			}
		}

		if (function_exists('pmprogroupacct_admin_groups_url')) {
			return pmprogroupacct_admin_groups_url();
		}

		return add_query_arg('page', 'pmpro-groupacct-groups', admin_url('admin.php'));
	}

	private static function get_group_by_id($group_id) {
		$group_id = absint($group_id);
		if ($group_id <= 0 || !class_exists('PMProGroupAcct_Group')) {
			return null;
		}

		foreach (['get_group_by_id', 'get_group', 'get'] as $method) {
			if (method_exists('PMProGroupAcct_Group', $method)) {
				$group = PMProGroupAcct_Group::$method($group_id);
				if ($group && !empty($group->id)) {
					return $group;
				}
			}
		}

		$row = self::get_group_row_by_column_value('id', $group_id);
		return $row ? self::get_group_from_row($row) : null;
	}

	private static function get_group_by_column_value($column, $value) {
		$row = self::get_group_row_by_column_value($column, $value);
		return $row ? self::get_group_from_row($row) : null;
	}

	private static function get_group_for_parent($parent_user_id) {
		$parent_user_id = (int) $parent_user_id;
		if ($parent_user_id <= 0 || !class_exists('PMProGroupAcct_Group')) {
			return null;
		}

		$parent_level_id = self::get_level_id_by_name('Partner');
		if ($parent_level_id && method_exists('PMProGroupAcct_Group', 'get_group_by_parent_user_id_and_parent_level_id')) {
			$group = PMProGroupAcct_Group::get_group_by_parent_user_id_and_parent_level_id($parent_user_id, $parent_level_id);
			if ($group && !empty($group->id)) {
				return $group;
			}
		}

		$stored_group_id = absint(get_user_meta($parent_user_id, 'aac_group_account_group_id', true));
		if ($stored_group_id > 0) {
			$group = self::get_group_by_id($stored_group_id);
			if ($group) {
				return $group;
			}
		}

		$row = self::get_group_row_by_column_value('group_parent_user_id', $parent_user_id);
		return $row ? self::get_group_from_row($row) : null;
	}

	private static function get_group_members_for_group($group_id) {
		$group_id = (int) $group_id;
		if ($group_id <= 0) {
			return [];
		}

		$members = [];
		if (class_exists('PMProGroupAcct_Group_Member') && method_exists('PMProGroupAcct_Group_Member', 'get_group_members')) {
			$members = PMProGroupAcct_Group_Member::get_group_members([
				'group_id' => $group_id,
			]);
			if (!empty($members)) {
				return (array) $members;
			}
		}

		return self::get_group_member_rows_by_group_id($group_id);
	}

	private static function get_group_member_rows_by_group_id($group_id) {
		global $wpdb;

		if (!$wpdb) {
			return [];
		}

		$group_id = (int) $group_id;
		$rows = [];
		foreach (self::get_group_member_account_tables() as $table) {
			$columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- discovered and sanitized table name.
			$columns = is_array($columns) ? $columns : [];
			foreach (['group_id', 'pmprogroupacct_group_id'] as $column) {
				if (!in_array($column, $columns, true)) {
					continue;
				}
				$table_rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE {$column} = %d", $group_id), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- discovered and sanitized table/column names.
				if (is_array($table_rows) && !empty($table_rows)) {
					$rows = array_merge($rows, $table_rows);
				}
				break;
			}
		}

		return $rows;
	}

	private static function get_group_member_account_tables() {
		global $wpdb;

		if (!$wpdb) {
			return [];
		}

		$like = $wpdb->esc_like($wpdb->prefix . 'pmprogroupacct') . '%';
		$tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));
		$tables = array_filter(array_map(function ($table) {
			return preg_replace('/[^A-Za-z0-9_]/', '', (string) $table);
		}, (array) $tables));

		return array_values(array_filter($tables, static function ($table) {
			return strpos($table, 'member') !== false;
		}));
	}

	private static function get_group_from_row($row) {
		if (!$row || empty($row['group_parent_user_id'])) {
			return null;
		}

		$parent_level_id = !empty($row['group_parent_level_id'])
			? (int) $row['group_parent_level_id']
			: self::get_level_id_by_name('Partner');
		if (!$parent_level_id || !method_exists('PMProGroupAcct_Group', 'get_group_by_parent_user_id_and_parent_level_id')) {
			return null;
		}

		return PMProGroupAcct_Group::get_group_by_parent_user_id_and_parent_level_id((int) $row['group_parent_user_id'], $parent_level_id);
	}

	private static function get_group_row_by_column_value($column, $value) {
		global $wpdb;

		if (!$wpdb) {
			return null;
		}

		$column = sanitize_key((string) $column);
		if ($column === '') {
			return null;
		}

		foreach (self::get_group_account_tables() as $table) {
			$columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- discovered and sanitized table name.
			$columns = is_array($columns) ? $columns : [];
			if (!in_array($column, $columns, true) || !in_array('group_parent_user_id', $columns, true)) {
				continue;
			}

			$row = ctype_digit((string) $value)
				? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE {$column} = %d LIMIT 1", (int) $value), ARRAY_A)
				: $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE {$column} = %s LIMIT 1", (string) $value), ARRAY_A);
			if (is_array($row) && !empty($row)) {
				return $row;
			}
		}

		return null;
	}

	private static function get_group_account_tables() {
		global $wpdb;

		if (!$wpdb) {
			return [];
		}

		$like = $wpdb->esc_like($wpdb->prefix . 'pmprogroupacct') . '%';
		$tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));
		$tables = array_filter(array_map(function ($table) {
			return preg_replace('/[^A-Za-z0-9_]/', '', (string) $table);
		}, (array) $tables));

		return array_values(array_filter($tables, static function ($table) {
			return strpos($table, 'group') !== false && strpos($table, 'member') === false;
		}));
	}

	private static function get_first_value($row, $keys) {
		foreach ((array) $keys as $key) {
			if (is_array($row) && array_key_exists($key, $row) && trim((string) $row[$key]) !== '') {
				return $row[$key];
			}
		}

		return '';
	}

	private static function get_first_int_value($row, $keys) {
		$value = self::get_first_value($row, $keys);
		return absint($value);
	}

	private static function infer_group_member_type($child_user_id, $child_level_id = 0) {
		$adult_level_id = self::get_level_id_by_name('Partner Adult');
		$dependent_level_id = self::get_level_id_by_name('Partner Dependent');
		$child_level_id = (int) $child_level_id;

		if ($adult_level_id && $child_level_id === $adult_level_id) {
			return 'adult';
		}
		if ($dependent_level_id && $child_level_id === $dependent_level_id) {
			return 'dependent';
		}

		$stored_type = self::normalize_imported_member_type(get_user_meta((int) $child_user_id, 'aac_linked_account_type', true));
		if ($stored_type !== '') {
			return $stored_type;
		}

		return self::infer_child_type_from_membership((int) $child_user_id) ?: 'dependent';
	}

	private static function dedupe_connected_accounts($accounts) {
		$seen = [];
		$deduped = [];
		foreach ((array) $accounts as $account) {
			if (!is_array($account)) {
				continue;
			}
			$child_user_id = absint($account['child_user_id'] ?? 0);
			$key = $child_user_id > 0
				? 'child-' . $child_user_id
				: 'slot-' . sanitize_text_field((string) ($account['id'] ?? ''));
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$deduped[] = $account;
		}

		return $deduped;
	}

	private static function count_billable_slots($accounts) {
		$count = 0;
		foreach ((array) $accounts as $slot) {
			if (!is_array($slot)) {
				continue;
			}
			if (($slot['status'] ?? '') === 'removal_pending') {
				$count++;
				continue;
			}
			if (in_array(($slot['type'] ?? ''), ['adult', 'dependent'], true)) {
				$count++;
			}
		}
		return $count;
	}

	private static function get_child_slot($parent_user_id, $child_user_id) {
		$slot_id = sanitize_text_field((string) get_user_meta($child_user_id, 'aac_linked_account_slot_id', true));
		foreach (self::get_parent_slots($parent_user_id) as $slot) {
			if ($slot_id !== '' && sanitize_text_field((string) ($slot['id'] ?? '')) === $slot_id) {
				return $slot;
			}
			if (absint($slot['child_user_id'] ?? 0) === (int) $child_user_id) {
				return $slot;
			}
		}
		return null;
	}

	private static function get_parent_slots($parent_user_id) {
		$accounts = get_user_meta((int) $parent_user_id, 'aac_connected_accounts', true);
		return is_array($accounts) ? array_values(array_filter($accounts, 'is_array')) : [];
	}

	private static function get_child_level_id_for_slot($slot) {
		$type = is_array($slot) ? sanitize_key((string) ($slot['type'] ?? 'dependent')) : 'dependent';
		return $type === 'adult'
			? self::get_level_id_by_name('Partner Adult')
			: self::get_level_id_by_name('Partner Dependent');
	}

	private static function get_level_id_by_name($name) {
		static $levels_by_name = null;

		if ($levels_by_name === null) {
			$levels_by_name = [];
			if (function_exists('pmpro_getAllLevels')) {
				foreach ((array) pmpro_getAllLevels(true, true) as $level) {
					if (!empty($level->name) && !empty($level->id)) {
						$levels_by_name[strtolower(trim((string) $level->name))] = (int) $level->id;
					}
				}
			}
		}

		return $levels_by_name[strtolower(trim((string) $name))] ?? 0;
	}
}
