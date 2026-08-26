<?php

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('WP_CLI_Command')) {
	return;
}

final class AAC_Member_Portal_WP_CLI_Importer extends WP_CLI_Command {
	/**
	 * Import a full AAC member import folder in the required order.
	 *
	 * ## OPTIONS
	 *
	 * <folder>
	 * : Folder containing the generated AAC import CSV files.
	 *
	 * [--batch-size=<number>]
	 * : Number of current-member rows to sync per batch. Default: 500.
	 *
	 * [--dry-run]
	 * : Stage and validate current-member rows without creating users.
	 *
	 * [--skip-current]
	 * : Skip current-member user imports.
	 *
	 * [--skip-family-links]
	 * : Skip family group linking.
	 *
	 * [--skip-subscriptions]
	 * : Skip PMPro subscription rows.
	 *
	 * [--skip-history]
	 * : Skip PMPro membership history rows.
	 *
	 * [--skip-orders]
	 * : Skip PMPro order/payment rows.
	 *
	 * ## EXAMPLES
	 *
	 *     wp aac-member-import folder /path/to/import --batch-size=1000
	 */
	public function folder($args, $assoc_args) {
		$folder = $this->resolve_folder($args[0] ?? '');
		$batch_size = $this->get_batch_size($assoc_args);
		$dry_run = !empty($assoc_args['dry-run']);

		$manifest = [
			'current' => [
				'01-member-import-single-members.csv',
				'02-member-import-family-parents.csv',
				'03-member-import-family-children.csv',
			],
			'family_links' => '05-family-group-links.csv',
			'subscriptions' => '08-subscriptions.csv',
			'history' => '06-membership-history.csv',
			'orders' => '07-transactions.csv',
		];

		if (empty($assoc_args['skip-current'])) {
			foreach ($manifest['current'] as $file_name) {
				$file = $folder . DIRECTORY_SEPARATOR . $file_name;
				if (!file_exists($file)) {
					WP_CLI::warning("Missing {$file_name}; skipping.");
					continue;
				}
				$this->run_current_import($file, $batch_size, $dry_run);
			}
		}

		if (empty($assoc_args['skip-family-links'])) {
			$this->run_optional_direct_import($folder, $manifest['family_links'], 'family_links');
		}
		if (empty($assoc_args['skip-subscriptions'])) {
			$this->run_optional_direct_import($folder, $manifest['subscriptions'], 'subscriptions');
		}
		if (empty($assoc_args['skip-history'])) {
			$this->run_optional_direct_import($folder, $manifest['history'], 'membership_history');
		}
		if (empty($assoc_args['skip-orders'])) {
			$this->run_optional_direct_import($folder, $manifest['orders'], 'orders');
		}

		WP_CLI::success('AAC member import folder complete.');
	}

	/**
	 * Import current members / WordPress users from one CSV.
	 *
	 * ## OPTIONS
	 *
	 * <csv>
	 * : Current-member CSV file.
	 *
	 * [--batch-size=<number>]
	 * : Number of rows per sync batch. Default: 500.
	 *
	 * [--dry-run]
	 * : Stage and validate without creating users.
	 */
	public function current($args, $assoc_args) {
		$this->run_current_import(
			$this->resolve_file($args[0] ?? ''),
			$this->get_batch_size($assoc_args),
			!empty($assoc_args['dry-run'])
		);
	}

	/**
	 * Import family group links from one CSV.
	 *
	 * ## OPTIONS
	 *
	 * <csv>
	 * : Family group links CSV file.
	 */
	public function family_links($args, $assoc_args) {
		$this->run_direct_import($this->resolve_file($args[0] ?? ''), 'family_links');
	}

	/**
	 * Import PMPro subscriptions from one CSV.
	 *
	 * ## OPTIONS
	 *
	 * <csv>
	 * : PMPro subscriptions CSV file.
	 */
	public function subscriptions($args, $assoc_args) {
		$this->run_direct_import($this->resolve_file($args[0] ?? ''), 'subscriptions');
	}

	/**
	 * Import PMPro membership history from one CSV.
	 *
	 * ## OPTIONS
	 *
	 * <csv>
	 * : PMPro membership history CSV file.
	 */
	public function membership_history($args, $assoc_args) {
		$this->run_direct_import($this->resolve_file($args[0] ?? ''), 'membership_history');
	}

	/**
	 * Import PMPro orders/payments from one CSV.
	 *
	 * ## OPTIONS
	 *
	 * <csv>
	 * : PMPro order/payment CSV file.
	 */
	public function orders($args, $assoc_args) {
		$this->run_direct_import($this->resolve_file($args[0] ?? ''), 'orders');
	}

	/**
	 * Delete imported test data and related PMPro rows.
	 *
	 * ## OPTIONS
	 *
	 * [--email-domain=<domain>]
	 * : Delete users whose email ends with this domain. Default: example.invalid.
	 *
	 * [--import-source=<source>]
	 * : Also match users with this aac_import_source meta value.
	 *
	 * [--dry-run]
	 * : Show what would be deleted without deleting.
	 *
	 * [--yes]
	 * : Confirm deletion.
	 *
	 * ## EXAMPLES
	 *
	 *     wp aac-member-import cleanup-imported --email-domain=example.invalid --yes
	 */
	public function cleanup_imported($args, $assoc_args) {
		$dry_run = !empty($assoc_args['dry-run']);
		if (!$dry_run && empty($assoc_args['yes'])) {
			WP_CLI::confirm('Delete matched imported users and related PMPro rows?');
		}

		$result = AAC_Member_Portal_Import_Manager::cleanup_imported_data([
			'email_domain' => $assoc_args['email-domain'] ?? 'example.invalid',
			'import_source' => $assoc_args['import-source'] ?? '',
			'dry_run' => $dry_run,
		]);
		if (is_wp_error($result)) {
			WP_CLI::error($result->get_error_message());
		}

		$this->print_result($dry_run ? 'Cleanup dry run' : 'Cleanup complete', $result);
	}

	/**
	 * Repair generated placeholder Stripe IDs so PMPro does not sync them with Stripe.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show what would be updated without changing rows.
	 *
	 * ## EXAMPLES
	 *
	 *     wp aac-member-import repair-placeholders
	 */
	public function repair_placeholders($args, $assoc_args) {
		$dry_run = !empty($assoc_args['dry-run']);
		$result = AAC_Member_Portal_Import_Manager::repair_placeholder_gateway_rows($dry_run);
		if (is_wp_error($result)) {
			WP_CLI::error($result->get_error_message());
		}

		$this->print_result($dry_run ? 'Placeholder repair dry run' : 'Placeholder repair complete', $result);
	}

	private function run_optional_direct_import($folder, $file_name, $kind) {
		$file = $folder . DIRECTORY_SEPARATOR . $file_name;
		if (!file_exists($file)) {
			WP_CLI::warning("Missing {$file_name}; skipping.");
			return;
		}

		$this->run_direct_import($file, $kind);
	}

	private function run_direct_import($file, $kind) {
		$manager = new AAC_Member_Portal_Import_Manager();
		WP_CLI::log(sprintf('Importing %s: %s', str_replace('_', ' ', $kind), $file));

		if ($kind === 'family_links') {
			$result = $manager->import_family_links_csv_file($file);
		} elseif ($kind === 'subscriptions') {
			$result = $manager->import_pmpro_subscriptions_csv($file);
		} elseif ($kind === 'membership_history') {
			$result = $manager->import_pmpro_membership_history_csv($file);
		} elseif ($kind === 'orders') {
			$result = $manager->import_pmpro_orders_csv($file);
		} else {
			WP_CLI::error('Unknown direct import type.');
		}

		if (is_wp_error($result)) {
			WP_CLI::error($result->get_error_message());
		}

		$this->print_result(ucwords(str_replace('_', ' ', $kind)) . ' import', $result);
	}

	private function run_current_import($file, $batch_size, $dry_run) {
		$manager = new AAC_Member_Portal_Import_Manager();
		WP_CLI::log(sprintf('%s current members: %s', $dry_run ? 'Dry-running' : 'Importing', $file));
		$stage = $manager->stage_csv($file);
		if (is_wp_error($stage)) {
			WP_CLI::error($stage->get_error_message());
		}
		$this->print_result('Staged current members', $stage);

		$total = [
			'limit' => $batch_size,
			'synced' => 0,
			'validated' => 0,
			'errors' => 0,
			'family_links' => 0,
			'remaining' => 0,
		];

		do {
			$result = $manager->sync_staged_rows($dry_run, $batch_size);
			if (is_wp_error($result)) {
				WP_CLI::error($result->get_error_message());
			}
			foreach (['synced', 'validated', 'errors', 'family_links'] as $key) {
				$total[$key] += (int) ($result[$key] ?? 0);
			}
			$total['remaining'] = (int) ($result['remaining'] ?? 0);
			WP_CLI::log(sprintf('Batch complete. Remaining rows: %d', $total['remaining']));
		} while ($total['remaining'] > 0);

		$this->print_result($dry_run ? 'Current-member dry run' : 'Current-member import', $total);
	}

	private function resolve_file($path) {
		$path = (string) $path;
		if ($path === '' || !file_exists($path) || !is_readable($path) || is_dir($path)) {
			WP_CLI::error('Provide a readable CSV file path.');
		}

		return $path;
	}

	private function resolve_folder($path) {
		$path = rtrim((string) $path, DIRECTORY_SEPARATOR);
		if ($path === '' || !is_dir($path) || !is_readable($path)) {
			WP_CLI::error('Provide a readable import folder path.');
		}

		return $path;
	}

	private function get_batch_size($assoc_args) {
		$batch_size = isset($assoc_args['batch-size']) ? absint($assoc_args['batch-size']) : 500;
		return max(1, min(5000, $batch_size));
	}

	private function print_result($label, $result) {
		WP_CLI::log($label . ':');
		\WP_CLI\Utils\format_items('table', [(array) $result], array_keys((array) $result));
	}
}

WP_CLI::add_command('aac-member-import', 'AAC_Member_Portal_WP_CLI_Importer');
