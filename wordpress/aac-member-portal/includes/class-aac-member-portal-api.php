<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Member_Portal_API {
	const ROUTE_NAMESPACE = 'aac/v1';
	const PROFILE_CACHE_TTL = 90;
	private static $instance = null;
	private static $university_school_index = null;

	public function __construct() {
		self::$instance = $this;
		add_action('rest_api_init', [$this, 'register_routes']);
		add_action('profile_update', [$this, 'flush_profile_cache_for_user'], 10, 1);
		add_action('added_user_meta', [$this, 'maybe_flush_profile_cache_for_meta_change'], 10, 4);
		add_action('updated_user_meta', [$this, 'maybe_flush_profile_cache_for_meta_change'], 10, 4);
		add_action('deleted_user_meta', [$this, 'maybe_flush_profile_cache_for_meta_change'], 10, 4);
		add_action('pmpro_after_checkout', [$this, 'flush_profile_cache_after_checkout'], 5, 2);
		add_action('pmpro_after_change_membership_level', [$this, 'flush_profile_cache_after_membership_change'], 5, 2);
		add_action('aac_member_portal_profile_updated', [$this, 'flush_profile_cache_for_user'], 5, 1);
		add_action('aac_member_portal_family_account_linked', [$this, 'flush_profile_cache_for_family_link'], 5, 2);
		add_action('update_option_aac_member_portal_settings', [$this, 'bump_profile_cache_version'], 10, 3);
	}

	public static function get_instance() {
		return self::$instance;
	}

	public function register_routes() {
		// Public routes: login, signup, recovery, and the other doors that need to
		// work before we know who someone is.
		register_rest_route(self::ROUTE_NAMESPACE, '/login', [
			'methods' => 'POST',
			'callback' => [$this, 'login'],
			'permission_callback' => '__return_true',
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/logout', [
			'methods' => 'POST',
			'callback' => [$this, 'logout'],
			'permission_callback' => [$this, 'is_logged_in'],
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/register', [
			'methods' => 'POST',
			'callback' => [$this, 'register_member'],
			'permission_callback' => '__return_true',
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/email-availability', [
			'methods' => 'GET',
			'callback' => [$this, 'email_availability'],
			'permission_callback' => '__return_true',
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/universities', [
			'methods' => 'GET',
			'callback' => [$this, 'search_universities'],
			'permission_callback' => '__return_true',
			'args' => [
				'q' => [
					'type' => 'string',
					'required' => false,
				],
				'limit' => [
					'type' => 'integer',
					'required' => false,
				],
			],
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/invite-code', [
			'methods' => 'GET',
			'callback' => [$this, 'validate_invite_code'],
			'permission_callback' => '__return_true',
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/redeem-invite', [
			'methods' => 'POST',
			'callback' => [$this, 'redeem_invite_code'],
			'permission_callback' => '__return_true',
		]);

		// Member routes: once someone is signed in, these power the actual portal.
		register_rest_route(self::ROUTE_NAMESPACE, '/linked-accounts/remove', [
			'methods' => 'POST',
			'callback' => [$this, 'schedule_linked_account_removal'],
			'permission_callback' => [$this, 'is_logged_in'],
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/linked-accounts/create', [
			'methods' => 'POST',
			'callback' => [$this, 'create_linked_account'],
			'permission_callback' => [$this, 'is_logged_in'],
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/change-password', [
			'methods' => 'POST',
			'callback' => [$this, 'change_password'],
			'permission_callback' => [$this, 'is_logged_in'],
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/me', [
			'methods' => 'GET',
			'callback' => [$this, 'me'],
			'permission_callback' => [$this, 'is_logged_in'],
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/profile', [
			'methods' => 'PATCH',
			'callback' => [$this, 'update_profile'],
			'permission_callback' => [$this, 'is_logged_in'],
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/membership/downgrade', [
			'methods' => 'POST',
			'callback' => [$this, 'schedule_membership_downgrade'],
			'permission_callback' => [$this, 'is_logged_in'],
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/contact', [
			'methods' => 'POST',
			'callback' => [$this, 'contact'],
			'permission_callback' => [$this, 'is_logged_in'],
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/transactions', [
			'methods' => 'GET',
			'callback' => [$this, 'transactions'],
			'permission_callback' => [$this, 'is_logged_in'],
		]);

		// Admin diagnostics: useful for staff, unnecessary for the rest of the internet.
		register_rest_route(self::ROUTE_NAMESPACE, '/debug/last-fatal', [
			'methods' => 'GET',
			'callback' => [$this, 'debug_last_fatal'],
			'permission_callback' => [$this, 'can_manage_options'],
		]);

	}

	public function is_logged_in() {
		return is_user_logged_in();
	}

	public function can_manage_options() {
		return current_user_can('manage_options');
	}

	public function flush_profile_cache_for_user($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return;
		}

		delete_transient($this->get_profile_cache_key($user_id));

		$parent_user_id = absint(get_user_meta($user_id, 'aac_linked_parent_user_id', true));
		if ($parent_user_id > 0 && $parent_user_id !== $user_id) {
			delete_transient($this->get_profile_cache_key($parent_user_id));
		}

		$accounts = get_user_meta($user_id, 'aac_connected_accounts', true);
		if (!is_array($accounts)) {
			return;
		}

		foreach ($accounts as $account) {
			$child_user_id = absint($account['child_user_id'] ?? 0);
			if ($child_user_id > 0) {
				delete_transient($this->get_profile_cache_key($child_user_id));
			}
		}
	}

	public function flush_profile_cache_after_checkout($user_id, $order = null) {
		$this->flush_profile_cache_for_user($user_id);
	}

	public function flush_profile_cache_after_membership_change($level_id, $user_id) {
		$this->flush_profile_cache_for_user($user_id);
	}

	public function flush_profile_cache_for_family_link($parent_user_id, $child_user_id) {
		$this->flush_profile_cache_for_user($parent_user_id);
		$this->flush_profile_cache_for_user($child_user_id);
	}

	public function maybe_flush_profile_cache_for_meta_change($meta_id, $user_id, $meta_key, $meta_value = null) {
		static $watched_meta_keys = [
			'aac_account_info',
			'aac_profile_info',
			'aac_benefits_info',
			'aac_member_id',
			'aac_membership_discount_type',
			'aac_magazine_addons',
			'aac_magazine_subscription_labels',
			'aac_has_alpinist_subscription',
			'aac_has_backcountry_subscription',
			'aac_connected_accounts',
			'aac_partner_family_config',
			'aac_partner_family_mode',
			'aac_partner_family_additional_adult',
			'aac_partner_family_dependents',
			'aac_family_account_role',
			'aac_linked_parent_user_id',
			'aac_linked_account_slot_id',
			'aac_linked_account_invite_code',
			'aac_linked_account_type',
			'aac_linked_account_label',
			'aac_family_membership_access_until',
			'aac_family_membership_pending_removal',
			'aac_pending_membership_downgrade',
			'aac_group_account_group_id',
			'aac_group_account_child_level_id',
			'aac_group_account_checkout_code',
			'first_name',
			'last_name',
			'pmpro_sfirstname',
			'pmpro_slastname',
			'pmpro_sphone',
			'pmpro_saddress1',
			'pmpro_saddress2',
			'pmpro_scity',
			'pmpro_sstate',
			'pmpro_szipcode',
				'pmpro_scountry',
				't_shirt',
				'birthdate',
				'aaj_preference',
			'anac_preference',
			'american_climbing_journal_preference',
			'guidebook_preferences',
			'student_university',
			'university_or_school',
			'student_university_id',
			'graduation_date',
			'service_component',
			'service_branch',
		];

		if (in_array((string) $meta_key, $watched_meta_keys, true)) {
			$this->flush_profile_cache_for_user($user_id);
		}
	}

	public function bump_profile_cache_version($old_value = null, $value = null, $option = '') {
		update_option('aac_member_portal_profile_cache_version', (string) microtime(true), false);
	}

	public function login(WP_REST_Request $request) {
		$email = sanitize_email($request->get_param('email'));
		$password = (string) $request->get_param('password');

		$rate_limit = $this->consume_rate_limit('login', $this->build_rate_limit_identity($request, $email), 8, 15 * MINUTE_IN_SECONDS);
		if (is_wp_error($rate_limit)) {
			return $rate_limit;
		}

		$user = get_user_by('email', $email);

		if (!$user) {
			return new WP_Error('invalid_credentials', 'Incorrect email or password. Please try again.', ['status' => 401]);
		}

		if (!wp_check_password($password, $user->user_pass, $user->ID)) {
			return new WP_Error('invalid_credentials', 'Incorrect email or password. Please try again.', ['status' => 401]);
		}

		if (is_user_logged_in()) {
			wp_logout();
		}

		$rest_nonce = $this->establish_fresh_auth_session($user->ID);

		do_action('aac_member_portal_member_logged_in', $user->ID, $request);

		return $this->build_auth_response($user, $rest_nonce);
	}

	public function logout() {
		wp_logout();
		return rest_ensure_response([
			'success' => true,
			'restNonce' => '',
		]);
	}

	public function debug_last_fatal() {
		return rest_ensure_response(get_option('aac_member_portal_last_fatal', []));
	}

	public function register_member(WP_REST_Request $request) {
		$email = sanitize_email($request->get_param('email'));
		$password = (string) $request->get_param('password');
		$first_name = sanitize_text_field($request->get_param('first_name'));
		$last_name = sanitize_text_field($request->get_param('last_name'));
		$username = sanitize_user($request->get_param('username'), true);

		$rate_limit = $this->consume_rate_limit('register', $this->build_rate_limit_identity($request, $email), 5, HOUR_IN_SECONDS);
		if (is_wp_error($rate_limit)) {
			return $rate_limit;
		}

		if (!$email || !$password) {
			return new WP_Error('invalid_input', 'Email and password are required.', ['status' => 400]);
		}

		if (!is_email($email)) {
			return new WP_Error('invalid_input', 'Please enter a valid email address.', ['status' => 400]);
		}

		if (email_exists($email)) {
			return new WP_Error('email_exists', 'An account with that email already exists.', ['status' => 409]);
		}

		if (strlen($password) < 8) {
			return new WP_Error('invalid_input', 'Password must be at least 8 characters long.', ['status' => 400]);
		}

		if (!$username) {
			$email_parts = explode('@', $email);
			$username = sanitize_user($email_parts[0], true);
		}

		$base_username = $username;
		$suffix = 1;
		while (username_exists($username)) {
			$username = sprintf('%s%d', $base_username, $suffix);
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
			'birthdate' => '',
		]);

		$rest_nonce = $this->establish_fresh_auth_session($user_id);

		do_action('aac_member_portal_member_registered', $user_id, $request);

		return rest_ensure_response(array_merge(
			['requires_email_verification' => false],
			$this->build_auth_response(get_user_by('id', $user_id), $rest_nonce)
		));
	}

	public function email_availability(WP_REST_Request $request) {
		$email = sanitize_email($request->get_param('email'));

		if (!$email || !is_email($email)) {
			return rest_ensure_response([
				'valid' => false,
				'available' => false,
				'message' => 'Enter a valid email address.',
			]);
		}

		$exists = (bool) email_exists($email);

		return rest_ensure_response([
			'valid' => true,
			'available' => !$exists,
			'message' => $exists
				? 'An account with this email already exists.'
				: 'Email address is available.',
		]);
	}

	public function search_universities(WP_REST_Request $request) {
		$query = sanitize_text_field((string) $request->get_param('q'));
		$normalized_query = $this->normalize_university_search_value($query);
		$limit = absint($request->get_param('limit'));
		$limit = min(50, max(1, $limit ?: 25));

		if (strlen($normalized_query) < 2) {
			return rest_ensure_response([
				'schools' => [],
			]);
		}

		$rate_limit = $this->consume_rate_limit('university_search', $this->build_rate_limit_identity($request), 120, 5 * MINUTE_IN_SECONDS);
		if (is_wp_error($rate_limit)) {
			return $rate_limit;
		}

		$cache_key = 'aac_university_search_' . md5($normalized_query . '|' . $limit);
		$cached = get_transient($cache_key);
		if (is_array($cached)) {
			return rest_ensure_response([
				'schools' => $cached,
			]);
		}

		$terms = preg_split('/\s+/', $normalized_query, -1, PREG_SPLIT_NO_EMPTY);
		$matches = [];

		foreach ($this->load_university_school_index() as $school) {
			$searchable = $school['search'] ?? '';
			if ($searchable === '') {
				continue;
			}

			$contains_query = strpos($searchable, $normalized_query) !== false;
			$contains_terms = false;
			if (!$contains_query && $terms) {
				$contains_terms = true;
				foreach ($terms as $term) {
					if (strpos($searchable, $term) === false) {
						$contains_terms = false;
						break;
					}
				}
			}

			if (!$contains_query && !$contains_terms) {
				continue;
			}

			$name_search = $school['name_search'] ?? '';
			$parent_search = $school['parent_search'] ?? '';
			$location_search = $school['location_search'] ?? '';
			$score = 8;
			if ($name_search === $normalized_query) {
				$score = 0;
			} elseif (strpos($name_search, $normalized_query) === 0) {
				$score = 1;
			} elseif (strpos($name_search, $normalized_query) !== false) {
				$score = 2;
			} elseif ($parent_search !== '' && strpos($parent_search, $normalized_query) === 0) {
				$score = 3;
			} elseif ($parent_search !== '' && strpos($parent_search, $normalized_query) !== false) {
				$score = 4;
			} elseif ($location_search !== '' && strpos($location_search, $normalized_query) !== false) {
				$score = 6;
			} elseif ($contains_terms) {
				$score = 7;
			}

			$matches[] = [
				'score' => $score,
				'label' => $school['label'],
				'school' => $school['public'],
			];
		}

		usort($matches, static function ($a, $b) {
			if ($a['score'] !== $b['score']) {
				return $a['score'] <=> $b['score'];
			}

			return strcasecmp((string) $a['label'], (string) $b['label']);
		});

		$schools = array_map(static function ($match) {
			return $match['school'];
		}, array_slice($matches, 0, $limit));

		set_transient($cache_key, $schools, 12 * HOUR_IN_SECONDS);

		return rest_ensure_response([
			'schools' => $schools,
		]);
	}

	public function validate_invite_code(WP_REST_Request $request) {
		$invite_code = $this->normalize_invite_code($request->get_param('code'));

		if ($invite_code === '') {
			return new WP_Error('invalid_invite', 'Enter a valid invite code.', ['status' => 400]);
		}

		$rate_limit = $this->consume_rate_limit('invite_lookup', $this->build_rate_limit_identity($request, $invite_code), 20, 15 * MINUTE_IN_SECONDS);
		if (is_wp_error($rate_limit)) {
			return $rate_limit;
		}

		$match = $this->find_connected_account_slot_by_invite_code($invite_code);
		if (!$match) {
			return new WP_Error('invalid_invite', 'Invite code not found.', ['status' => 404]);
		}

		return rest_ensure_response([
			'success' => true,
			'invite' => $this->build_linked_account_invite_payload($match),
		]);
	}

	public function redeem_invite_code(WP_REST_Request $request) {
		$invite_code = $this->normalize_invite_code($request->get_param('invite_code'));
		if ($invite_code === '') {
			return new WP_Error('invalid_invite', 'Invite code is required.', ['status' => 400]);
		}

		$rate_limit_identity = $this->build_rate_limit_identity($request, sanitize_email($request->get_param('email')) ?: $invite_code);
		$rate_limit = $this->consume_rate_limit('invite_redeem', $rate_limit_identity, 10, 15 * MINUTE_IN_SECONDS);
		if (is_wp_error($rate_limit)) {
			return $rate_limit;
		}

		$match = $this->find_connected_account_slot_by_invite_code($invite_code);
		if (!$match) {
			return new WP_Error('invalid_invite', 'Invite code not found.', ['status' => 404]);
		}

		$parent_user_id = (int) $match['parent_user_id'];
		$slot = $match['account'];

		$current_user = wp_get_current_user();
		$child_user = null;
		$created_user_id = 0;

		if ($current_user instanceof WP_User && $current_user->exists()) {
			$child_user = $current_user;
		} else {
			$email = sanitize_email($request->get_param('email'));
			$password = (string) $request->get_param('password');
			$first_name = sanitize_text_field($request->get_param('first_name'));
			$last_name = sanitize_text_field($request->get_param('last_name'));

			if (!$email || !is_email($email)) {
				return new WP_Error('invalid_input', 'Enter a valid email address to redeem this invite.', ['status' => 400]);
			}

			if ($password === '') {
				return new WP_Error('invalid_input', 'Password is required to redeem this invite.', ['status' => 400]);
			}

			$existing_user = get_user_by('email', $email);
			if ($existing_user instanceof WP_User) {
				$signon = wp_signon([
					'user_login' => $existing_user->user_login,
					'user_password' => $password,
					'remember' => true,
				], is_ssl());

				if (is_wp_error($signon)) {
					return new WP_Error('invalid_credentials', 'Incorrect email or password. Please try again.', ['status' => 401]);
				}

				$child_user = $signon;
			} else {
				if (strlen($password) < 8) {
					return new WP_Error('invalid_input', 'Password must be at least 8 characters long.', ['status' => 400]);
				}

				$username = $this->generate_unique_username_from_email($email);
				$created_user_id = wp_create_user($username, $password, $email);
				if (is_wp_error($created_user_id)) {
					return $created_user_id;
				}

				wp_update_user([
					'ID' => $created_user_id,
					'first_name' => $first_name,
					'last_name' => $last_name,
					'display_name' => trim($first_name . ' ' . $last_name) ?: $email,
				]);

				update_user_meta($created_user_id, 'aac_account_info', [
					'first_name' => $first_name,
					'last_name' => $last_name,
					'name' => trim($first_name . ' ' . $last_name),
					'email' => $email,
				]);
				update_user_meta($created_user_id, 't_shirt', 'No T-shirt');
				update_user_meta($created_user_id, 'birthdate', '');

				$child_user = get_user_by('id', $created_user_id);
			}
		}

		if (!$child_user instanceof WP_User || !$child_user->exists()) {
			return new WP_Error('invite_redeem_failed', 'Unable to redeem this invite right now.', ['status' => 500]);
		}

		if ((int) $child_user->ID === $parent_user_id) {
			return new WP_Error('invalid_invite', 'The parent account cannot redeem its own invite code.', ['status' => 400]);
		}

		$existing_parent_link = absint(get_user_meta($child_user->ID, 'aac_linked_parent_user_id', true));
		if ($existing_parent_link > 0 && $existing_parent_link !== $parent_user_id) {
			return new WP_Error('invite_redeem_failed', 'This account is already linked to another family membership.', ['status' => 409]);
		}

		if (($slot['status'] ?? '') === 'connected' && absint($slot['child_user_id'] ?? 0) > 0 && absint($slot['child_user_id']) !== (int) $child_user->ID) {
			return new WP_Error('invite_redeem_failed', 'This invite code has already been redeemed.', ['status' => 409]);
		}

		$parent_accounts = $match['accounts'];
		$parent_accounts[$match['account_index']] = array_merge($slot, [
			'status' => 'connected',
			'child_user_id' => (int) $child_user->ID,
			'child_name' => trim($child_user->first_name . ' ' . $child_user->last_name) ?: $child_user->display_name,
			'child_email' => $child_user->user_email,
		]);
		update_user_meta($parent_user_id, 'aac_connected_accounts', array_values($parent_accounts));

		update_user_meta($child_user->ID, 'aac_linked_parent_user_id', $parent_user_id);
		update_user_meta($child_user->ID, 'aac_linked_account_slot_id', sanitize_text_field($slot['id'] ?? ''));
		update_user_meta($child_user->ID, 'aac_linked_account_invite_code', $invite_code);
		update_user_meta($child_user->ID, 'aac_linked_account_type', sanitize_key($slot['type'] ?? 'dependent'));
		update_user_meta($child_user->ID, 'aac_linked_account_label', sanitize_text_field($slot['label'] ?? 'Family member'));
		update_user_meta($parent_user_id, 'aac_family_account_role', 'Parent');
		update_user_meta($child_user->ID, 'aac_family_account_role', 'Child');
		delete_user_meta($child_user->ID, 'aac_family_membership_access_until');
		delete_user_meta($child_user->ID, 'aac_family_membership_pending_removal');

		do_action('aac_member_portal_family_account_linked', $parent_user_id, (int) $child_user->ID);

		$rest_nonce = $this->establish_fresh_auth_session($child_user->ID);

		return rest_ensure_response(array_merge(
			[
				'success' => true,
				'invite' => $this->build_linked_account_invite_payload($this->find_connected_account_slot_by_invite_code($invite_code)),
				'linked_parent_account' => $this->build_linked_parent_account($child_user->ID),
			],
			$this->build_auth_response($child_user, $rest_nonce)
		));
	}

	public function schedule_linked_account_removal(WP_REST_Request $request) {
		$parent_user_id = get_current_user_id();
		if ($parent_user_id <= 0) {
			return new WP_Error('not_authenticated', 'You must be signed in to manage linked accounts.', ['status' => 401]);
		}

		$slot_id = sanitize_text_field((string) $request->get_param('slot_id'));
		if ($slot_id === '') {
			return new WP_Error('invalid_input', 'A linked account selection is required.', ['status' => 400]);
		}

		$accounts = get_user_meta($parent_user_id, 'aac_connected_accounts', true);
		$accounts = is_array($accounts) ? $this->sanitize_connected_accounts($accounts) : [];
		if (empty($accounts)) {
			return new WP_Error('not_found', 'No linked accounts were found for this member.', ['status' => 404]);
		}

		$account_index = null;
		foreach ($accounts as $index => $account) {
			if (($account['id'] ?? '') === $slot_id) {
				$account_index = $index;
				break;
			}
		}

		if ($account_index === null) {
			return new WP_Error('not_found', 'That linked account could not be found.', ['status' => 404]);
		}

		$account = $accounts[$account_index];
		$child_user_id = absint($account['child_user_id'] ?? 0);
		$family_config = get_user_meta($parent_user_id, 'aac_partner_family_config', true);
		$family_config = is_array($family_config) ? $family_config : ['mode' => '', 'additional_adult' => false, 'dependent_count' => 0];
		if (($account['status'] ?? '') === 'removal_pending') {
			return rest_ensure_response([
				'success' => true,
				'profile' => $this->build_profile($parent_user_id),
			]);
		}

		if ($child_user_id <= 0) {
			unset($accounts[$account_index]);
			update_user_meta($parent_user_id, 'aac_connected_accounts', array_values($accounts));
			if (($account['type'] ?? '') === 'adult') {
				$family_config['additional_adult'] = false;
			} elseif (($account['type'] ?? '') === 'dependent') {
				$family_config['dependent_count'] = max(0, ((int) ($family_config['dependent_count'] ?? 0)) - 1);
			}
			if (empty($family_config['additional_adult']) && empty($family_config['dependent_count'])) {
				$family_config['mode'] = '';
			}
			update_user_meta($parent_user_id, 'aac_partner_family_config', $family_config);

			return rest_ensure_response([
				'success' => true,
				'profile' => $this->build_profile($parent_user_id),
			]);
		}

		$access_until = $this->get_family_membership_term_end_date($parent_user_id);
		if ($access_until === '') {
			return new WP_Error(
				'invalid_membership_state',
				'We could not determine the family plan renewal date for this linked account.',
				['status' => 409]
			);
		}

		$accounts[$account_index]['status'] = 'removal_pending';
		$accounts[$account_index]['scheduled_removal_date'] = $access_until;
		update_user_meta($parent_user_id, 'aac_connected_accounts', array_values($accounts));
		if (($account['type'] ?? '') === 'adult') {
			$family_config['additional_adult'] = false;
		} elseif (($account['type'] ?? '') === 'dependent') {
			$family_config['dependent_count'] = max(0, ((int) ($family_config['dependent_count'] ?? 0)) - 1);
		}
		if (empty($family_config['additional_adult']) && empty($family_config['dependent_count'])) {
			$family_config['mode'] = '';
		}
		update_user_meta($parent_user_id, 'aac_partner_family_config', $family_config);

		update_user_meta($child_user_id, 'aac_family_membership_access_until', $access_until);
		update_user_meta($child_user_id, 'aac_family_membership_pending_removal', '1');
		update_user_meta($child_user_id, 'aac_family_account_role', 'Child');

		return rest_ensure_response([
			'success' => true,
			'profile' => $this->build_profile($parent_user_id),
		]);
	}

	public function create_linked_account(WP_REST_Request $request) {
		$parent_user_id = get_current_user_id();
		if ($parent_user_id <= 0) {
			return new WP_Error('not_authenticated', 'You must be signed in to manage linked accounts.', ['status' => 401]);
		}

		$slot_id = sanitize_text_field((string) $request->get_param('slot_id'));
		$first_name = sanitize_text_field((string) $request->get_param('first_name'));
		$last_name = sanitize_text_field((string) $request->get_param('last_name'));
		$email = sanitize_email((string) $request->get_param('email'));

		if ($slot_id === '' || $first_name === '' || $last_name === '' || !$email || !is_email($email)) {
			return new WP_Error('invalid_input', 'Enter a first name, last name, and valid email address.', ['status' => 400]);
		}

		$parent_user = get_user_by('id', $parent_user_id);
		if (!$parent_user instanceof WP_User) {
			return new WP_Error('not_authenticated', 'Unable to load the parent account.', ['status' => 401]);
		}

		if (strtolower($parent_user->user_email) === strtolower($email)) {
			return new WP_Error('invalid_input', 'Use a different email address for the linked family member.', ['status' => 400]);
		}

		if (email_exists($email)) {
			return new WP_Error(
				'email_exists',
				'An account already exists for that email address. Send the invite code to that member so they can link their existing account.',
				['status' => 409]
			);
		}

		$accounts = get_user_meta($parent_user_id, 'aac_connected_accounts', true);
		$accounts = is_array($accounts) ? $this->sanitize_connected_accounts($accounts) : [];
		if (empty($accounts)) {
			return new WP_Error('not_found', 'No linked accounts were found for this member.', ['status' => 404]);
		}

		$account_index = null;
		foreach ($accounts as $index => $account) {
			if (($account['id'] ?? '') === $slot_id) {
				$account_index = $index;
				break;
			}
		}

		if ($account_index === null) {
			return new WP_Error('not_found', 'That linked account could not be found.', ['status' => 404]);
		}

		$slot = $accounts[$account_index];
		if (($slot['status'] ?? '') === 'connected' || absint($slot['child_user_id'] ?? 0) > 0) {
			return new WP_Error('linked_account_claimed', 'That linked account has already been created.', ['status' => 409]);
		}

		if (($slot['status'] ?? '') === 'removal_pending') {
			return new WP_Error('linked_account_pending_removal', 'That linked account is scheduled for removal and cannot be created.', ['status' => 409]);
		}

		$username = $this->generate_unique_username_from_email($email);
		$password = wp_generate_password(24, true, true);
		$child_user_id = wp_create_user($username, $password, $email);
		if (is_wp_error($child_user_id)) {
			return $child_user_id;
		}

		wp_update_user([
			'ID' => $child_user_id,
			'first_name' => $first_name,
			'last_name' => $last_name,
			'display_name' => trim($first_name . ' ' . $last_name) ?: $email,
		]);

		update_user_meta($child_user_id, 'aac_account_info', [
			'first_name' => $first_name,
			'last_name' => $last_name,
			'name' => trim($first_name . ' ' . $last_name),
			'email' => $email,
		]);
		update_user_meta($child_user_id, 't_shirt', 'No T-shirt');
		update_user_meta($child_user_id, 'birthdate', '');

		$child_user = get_user_by('id', $child_user_id);
		if (!$child_user instanceof WP_User) {
			return new WP_Error('linked_account_create_failed', 'Unable to create that linked account right now.', ['status' => 500]);
		}

		$invite_code = $this->normalize_invite_code($slot['invite_code'] ?? '');
		$accounts[$account_index] = array_merge($slot, [
			'status' => 'connected',
			'child_user_id' => (int) $child_user->ID,
			'child_name' => trim($child_user->first_name . ' ' . $child_user->last_name) ?: $child_user->display_name,
			'child_email' => $child_user->user_email,
		]);
		update_user_meta($parent_user_id, 'aac_connected_accounts', array_values($accounts));

		update_user_meta($child_user->ID, 'aac_linked_parent_user_id', $parent_user_id);
		update_user_meta($child_user->ID, 'aac_linked_account_slot_id', sanitize_text_field($slot['id'] ?? ''));
		update_user_meta($child_user->ID, 'aac_linked_account_invite_code', $invite_code);
		update_user_meta($child_user->ID, 'aac_linked_account_type', sanitize_key($slot['type'] ?? 'dependent'));
		update_user_meta($child_user->ID, 'aac_linked_account_label', sanitize_text_field($slot['label'] ?? 'Family member'));
		update_user_meta($parent_user_id, 'aac_family_account_role', 'Parent');
		update_user_meta($child_user->ID, 'aac_family_account_role', 'Child');
		delete_user_meta($child_user->ID, 'aac_family_membership_access_until');
		delete_user_meta($child_user->ID, 'aac_family_membership_pending_removal');

		do_action('aac_member_portal_family_account_linked', $parent_user_id, (int) $child_user->ID);

		$email_sent = $this->send_linked_account_password_setup_email($child_user, $parent_user, $slot);

		return rest_ensure_response([
			'success' => true,
			'email_sent' => (bool) $email_sent,
			'profile' => $this->build_profile($parent_user_id),
		]);
	}

	public function change_password(WP_REST_Request $request) {
		$user = wp_get_current_user();
		$current_password = (string) $request->get_param('current_password');
		$new_password = (string) $request->get_param('new_password');
		$confirm_password = (string) $request->get_param('confirm_password');

		if (!$user instanceof WP_User || !$user->exists()) {
			return new WP_Error('not_authenticated', 'You must be logged in to change your password.', ['status' => 401]);
		}

		if ($current_password === '' || $new_password === '' || $confirm_password === '') {
			return new WP_Error('invalid_input', 'Current password, new password, and confirmation are required.', ['status' => 400]);
		}

		if (!wp_check_password($current_password, $user->user_pass, $user->ID)) {
			return new WP_Error('invalid_password', 'Your current password is incorrect.', ['status' => 400]);
		}

		if ($new_password !== $confirm_password) {
			return new WP_Error('password_mismatch', 'New password and confirmation must match.', ['status' => 400]);
		}

		if (strlen($new_password) < 8) {
			return new WP_Error('weak_password', 'New password must be at least 8 characters long.', ['status' => 400]);
		}

		if ($new_password === $current_password) {
			return new WP_Error('password_reuse', 'Choose a new password that is different from your current password.', ['status' => 400]);
		}

		wp_set_password($new_password, $user->ID);
		$fresh_user = get_user_by('id', $user->ID);
		$rest_nonce = $this->establish_fresh_auth_session($user->ID);

		return rest_ensure_response([
			'success' => true,
			'profile' => $this->build_profile($user->ID),
			'restNonce' => $rest_nonce,
			'user' => [
				'id' => $user->ID,
				'email' => $fresh_user ? $fresh_user->user_email : $user->user_email,
			],
		]);
	}

	public function me() {
		return rest_ensure_response($this->build_auth_response(wp_get_current_user()));
	}

	public function get_current_user_auth_payload() {
		if (!is_user_logged_in()) {
			return null;
		}

		$user = wp_get_current_user();
		if (!$user instanceof WP_User || !$user->exists()) {
			return null;
		}

		return $this->build_auth_response($user);
	}

	public function update_profile(WP_REST_Request $request) {
		$user_id = get_current_user_id();
		$account_info = $request->get_param('account_info');
		$profile_info = $request->get_param('profile_info');
		$benefits_info = $request->get_param('benefits_info');
		$saved_account_info = null;
		$saved_profile_info = null;
		$saved_benefits_info = null;

		if (
			!is_array($account_info) &&
			!is_array($profile_info) &&
			!is_array($benefits_info)
		) {
			return new WP_Error('invalid_input', 'At least one profile section must be provided.', ['status' => 400]);
		}

		if ($account_info !== null && !is_array($account_info)) {
			return new WP_Error('invalid_input', 'account_info must be an object.', ['status' => 400]);
		}

		if (is_array($account_info)) {
			$stored_account_info = get_user_meta($user_id, 'aac_account_info', true);
			$stored_account_info = is_array($stored_account_info) ? $stored_account_info : [];
			$sanitized_account_info = $this->sanitize_account_info($account_info, $stored_account_info);
			$required_account_info_error = $this->validate_required_account_info($sanitized_account_info);
			if (is_wp_error($required_account_info_error)) {
				return $required_account_info_error;
			}

			$synced_account_info = $this->sync_wp_user_from_account_info($user_id, $sanitized_account_info);
			if (is_wp_error($synced_account_info)) {
				return $synced_account_info;
			}

			update_user_meta($user_id, 'aac_account_info', $this->strip_pmpro_managed_account_fields_for_storage($synced_account_info));
			if (function_exists('aac_member_portal') && aac_member_portal() && method_exists(aac_member_portal(), 'sync_account_info_to_pmpro_fields')) {
				aac_member_portal()->sync_account_info_to_pmpro_fields($user_id, $synced_account_info);
			} elseif (function_exists('aac_member_portal') && aac_member_portal() && method_exists(aac_member_portal(), 'sync_member_record_to_pmpro_fields')) {
				aac_member_portal()->sync_member_record_to_pmpro_fields($user_id);
			} else {
				$this->sync_reportable_member_fields($user_id, $synced_account_info);
			}
			$saved_account_info = $synced_account_info;
		}

		if (is_array($profile_info)) {
			$saved_profile_info = $this->sanitize_profile_info($profile_info);
			update_user_meta($user_id, 'aac_profile_info', $saved_profile_info);
		}

		if (is_array($benefits_info)) {
			$saved_benefits_info = $this->sanitize_benefits_info($benefits_info);
			update_user_meta($user_id, 'aac_benefits_info', $saved_benefits_info);
		}

		$this->flush_profile_cache_for_user($user_id);
		$profile = $this->build_profile($user_id);
		if (is_array($saved_account_info)) {
			$profile['account_info'] = array_merge(
				is_array($profile['account_info'] ?? null) ? $profile['account_info'] : [],
				$saved_account_info,
				$this->get_normalized_publication_preferences($saved_account_info)
			);
			$profile['account_info']['size'] = $this->normalize_tshirt_size_value($saved_account_info['size'] ?? 'No T-shirt');
		}
		if (is_array($saved_profile_info)) {
			$profile['profile_info'] = array_merge(
				is_array($profile['profile_info'] ?? null) ? $profile['profile_info'] : [],
				$saved_profile_info
			);
		}
		if (is_array($saved_benefits_info)) {
			$profile['benefits_info'] = array_merge(
				is_array($profile['benefits_info'] ?? null) ? $profile['benefits_info'] : [],
				$saved_benefits_info
			);
		}
		do_action('aac_member_portal_profile_updated', $user_id, $profile, $request);
		$this->set_cached_profile($user_id, $profile);

		return rest_ensure_response([
			'success' => true,
			'profile' => $profile,
		]);
	}

	public function schedule_membership_downgrade(WP_REST_Request $request) {
		$user_id = get_current_user_id();
		$target_tier = sanitize_text_field((string) $request->get_param('target_tier'));
		$target_tier = class_exists('AAC_Member_Portal_PMPro') ? AAC_Member_Portal_PMPro::normalize_tier_name($target_tier) : $target_tier;

		if ($user_id <= 0) {
			return new WP_Error('not_authenticated', 'You must be logged in to schedule a downgrade.', ['status' => 401]);
		}

		if (!class_exists('AAC_Member_Portal_PMPro') || !AAC_Member_Portal_PMPro::is_available()) {
			return new WP_Error('pmpro_unavailable', 'Membership changes are not available right now.', ['status' => 503]);
		}

		$current_membership = AAC_Member_Portal_PMPro::get_primary_membership($user_id);
		if (!is_array($current_membership) || empty($current_membership['level_id'])) {
			return new WP_Error('membership_not_found', 'We could not find an active membership to downgrade.', ['status' => 409]);
		}

		$target_level = AAC_Member_Portal_PMPro::find_level_by_tier($target_tier);
		if (!is_object($target_level) || empty($target_level->id)) {
			return new WP_Error('invalid_target_tier', 'Choose a valid membership level.', ['status' => 400]);
		}

		$current_rank = AAC_Member_Portal_PMPro::get_tier_rank_for_level_id((int) $current_membership['level_id']);
		$target_rank = AAC_Member_Portal_PMPro::get_tier_rank_from_name($target_tier);
		if ($current_rank <= 0 || $target_rank <= 0 || $target_rank >= $current_rank) {
			return new WP_Error('not_a_downgrade', 'Only lower membership levels can be scheduled for renewal.', ['status' => 400]);
		}

		if (!AAC_Member_Portal_PMPro::has_active_auto_renewal($user_id, (int) $current_membership['level_id'])) {
			return new WP_Error(
				'downgrade_requires_autorenew',
				'Downgrades can only be scheduled for memberships with active automatic renewal.',
				['status' => 409]
			);
		}

		if (!AAC_Member_Portal_PMPro::can_schedule_membership_downgrade($user_id, $current_membership)) {
			return new WP_Error(
				'downgrade_window_closed',
				'Downgrades can only be scheduled within 30 days of your renewal date.',
				['status' => 409]
			);
		}

		$effective_date = sanitize_text_field((string) ($current_membership['renewal_date'] ?: $current_membership['expiration_date']));
		if ($effective_date === '') {
			return new WP_Error(
				'missing_renewal_date',
				'We could not determine the renewal date for this membership. Please contact AAC support to schedule the downgrade.',
				['status' => 409]
			);
		}

		update_user_meta($user_id, 'aac_pending_membership_downgrade', [
			'target_level_id' => (int) $target_level->id,
			'target_tier' => $target_tier,
			'effective_date' => $effective_date,
			'requested_at' => current_time('mysql'),
			'status' => 'scheduled',
		]);

		$this->flush_profile_cache_for_user($user_id);
		return rest_ensure_response([
			'success' => true,
			'profile' => $this->build_profile($user_id),
		]);
	}

	public function contact(WP_REST_Request $request) {
		$current_user = wp_get_current_user();
		$message = sanitize_textarea_field($request->get_param('message'));
		$sender_name = sanitize_text_field($request->get_param('name'));
		$sender_email = sanitize_email($request->get_param('email')) ?: $current_user->user_email;
		$issue_type = sanitize_text_field($request->get_param('issue_type'));
		$allowed_issue_types = AAC_Member_Portal_Admin::get_contact_issue_types();

		if (!$message) {
			return new WP_Error('invalid_input', 'Message is required.', ['status' => 400]);
		}

		if (!in_array($issue_type, $allowed_issue_types, true)) {
			return new WP_Error('invalid_input', 'Issue type is required.', ['status' => 400]);
		}

		$recipient_email = AAC_Member_Portal_Admin::get_contact_recipient_email();
		$subject = sprintf('AAC Member Portal: %s', $issue_type);
		$body = sprintf(
			"Name: %s\nEmail: %s\nIssue Type: %s\n\n%s",
			$sender_name,
			$sender_email,
			$issue_type,
			$message
		);

		$headers = [];
		$from_email = sanitize_email(get_option('admin_email'));
		if ($from_email && is_email($from_email)) {
			$headers[] = sprintf('From: Member Request Message <%s>', $from_email);
		}
		if ($sender_email && is_email($sender_email)) {
			$headers[] = sprintf('Reply-To: %s <%s>', $sender_name ?: $sender_email, $sender_email);
		}

		wp_mail($recipient_email, $subject, $body, $headers);

		return rest_ensure_response(['success' => true]);
	}

	private function get_request_ip_address(WP_REST_Request $request) {
		$server = $request->get_server_params();
		$candidates = [
			$server['HTTP_CF_CONNECTING_IP'] ?? '',
			$server['HTTP_X_REAL_IP'] ?? '',
			$server['HTTP_X_FORWARDED_FOR'] ?? '',
			$server['REMOTE_ADDR'] ?? '',
		];

		foreach ($candidates as $candidate) {
			$candidate = sanitize_text_field((string) $candidate);
			if ($candidate === '') {
				continue;
			}

			$parts = array_map('trim', explode(',', $candidate));
			foreach ($parts as $part) {
				if ($part !== '' && filter_var($part, FILTER_VALIDATE_IP)) {
					return $part;
				}
			}
		}

		return '';
	}

	public function transactions() {
		$user_id = get_current_user_id();
		$transactions = AAC_Member_Portal_PMPro::is_available()
			? AAC_Member_Portal_PMPro::get_membership_transactions($user_id)
			: [];

		return rest_ensure_response([
			'transactions' => $transactions,
		]);
	}

	public function get_profile_for_user($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return [];
		}

		return $this->get_cached_profile($user_id);
	}

	private function build_auth_response($user, $rest_nonce = null) {
		$can_access_admin = $user instanceof WP_User && $user->has_cap('edit_posts');

		return [
			'session' => [
				'user' => [
					'id' => $user->ID,
					'email' => $user->user_email,
				],
			],
			'user' => [
				'id' => $user->ID,
				'email' => $user->user_email,
				'adminUrl' => $can_access_admin ? admin_url() : '',
			],
			'profile' => $this->get_cached_profile($user->ID),
			'restNonce' => $rest_nonce ?: wp_create_nonce('wp_rest'),
		];
	}

	private function get_profile_cache_key($user_id) {
		$settings_version = (string) get_option('aac_member_portal_profile_cache_version', '1');
		return 'aac_member_profile_' . md5(AAC_MEMBER_PORTAL_VERSION . '|' . $settings_version) . '_' . (int) $user_id;
	}

	private function get_cached_profile($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return [];
		}

		$cached = get_transient($this->get_profile_cache_key($user_id));
		if (is_array($cached)) {
			return $cached;
		}

		$profile = $this->build_profile($user_id);
		$this->set_cached_profile($user_id, $profile);
		return $profile;
	}

	private function set_cached_profile($user_id, $profile) {
		if ((int) $user_id <= 0 || !is_array($profile)) {
			return;
		}

		set_transient($this->get_profile_cache_key($user_id), $profile, self::PROFILE_CACHE_TTL);
	}

	private function establish_fresh_auth_session($user_id) {
		$remember = true;
		$secure = is_ssl();
		$expiration = time() + (int) apply_filters('auth_cookie_expiration', 14 * DAY_IN_SECONDS, $user_id, $remember);
		$session_manager = WP_Session_Tokens::get_instance($user_id);
		$token = $session_manager->create($expiration);

		wp_set_current_user($user_id);
		wp_set_auth_cookie($user_id, $remember, $secure, $token);

		if (defined('AUTH_COOKIE')) {
			$_COOKIE[AUTH_COOKIE] = wp_generate_auth_cookie($user_id, $expiration, 'auth', $token);
		}

		if (defined('SECURE_AUTH_COOKIE')) {
			$_COOKIE[SECURE_AUTH_COOKIE] = wp_generate_auth_cookie($user_id, $expiration, 'secure_auth', $token);
		}

		if (defined('LOGGED_IN_COOKIE')) {
			$_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie($user_id, $expiration, 'logged_in', $token);
		}

		return wp_create_nonce('wp_rest');
	}

	private function build_profile($user_id) {
		$this->prune_expired_connected_accounts($user_id);
		$this->expire_scheduled_family_access_if_needed($user_id);

		$user = get_user_by('id', $user_id);
		$linked_parent_user_id = $this->get_linked_parent_user_id($user_id);
		$membership_owner_user_id = $linked_parent_user_id ?: $user_id;
		$account_info = get_user_meta($user_id, 'aac_account_info', true);
		$account_info = is_array($account_info) ? $this->hydrate_pmpro_managed_account_info($user_id, $account_info) : $this->hydrate_pmpro_managed_account_info($user_id, []);
		$stored_profile_info = get_user_meta($user_id, 'aac_profile_info', true);
		$stored_profile_info = is_array($stored_profile_info) ? $stored_profile_info : [];
		$computed_profile_info = $this->build_profile_info($user_id, $membership_owner_user_id);
		if ($this->has_managed_membership_plugin()) {
			$profile_info = array_merge($stored_profile_info, $computed_profile_info);
		} else {
			$profile_info = array_merge($computed_profile_info, $stored_profile_info);
		}

		$stored_benefits_info = get_user_meta($user_id, 'aac_benefits_info', true);
		$stored_benefits_info = is_array($stored_benefits_info) ? $stored_benefits_info : [];
		$computed_benefits_info = $this->build_benefits_info($profile_info['tier'], $profile_info['status']);
		if ($this->has_managed_membership_plugin()) {
			$benefits_info = $computed_benefits_info;
		} else {
			$benefits_info = array_merge($computed_benefits_info, $stored_benefits_info);
		}

		$account_info = array_merge([
			'first_name' => $user->first_name,
			'last_name' => $user->last_name,
			'name' => $user->display_name,
			'email' => $user->user_email,
			'photo_url' => get_avatar_url($user_id),
			'phone' => '',
			'birthdate' => '',
			'street' => '',
			'address2' => '',
			'city' => '',
			'state' => '',
			'zip' => '',
			'country' => '',
			'size' => 'No T-shirt',
			'publication_pref' => 'Print',
			'aaj_pref' => 'Print',
			'anac_pref' => 'Print',
			'acj_pref' => 'Print',
			'guidebook_pref' => 'Print',
			'magazine_subscriptions' => [],
			'membership_discount_type' => '',
			'auto_renew' => false,
		], $account_info, $this->get_normalized_publication_preferences($account_info));

		$account_info['magazine_subscriptions'] = $this->get_member_magazine_subscription_labels($user_id);
		$account_info['membership_discount_type'] = sanitize_key(get_user_meta($user_id, 'aac_membership_discount_type', true));
		$account_info['size'] = $this->normalize_tshirt_size_value($account_info['size'] ?? 'No T-shirt');

		$membership_actions = $this->build_membership_actions($membership_owner_user_id, $profile_info);

		if ($this->has_managed_membership_plugin()) {
			$account_info['auto_renew'] = AAC_Member_Portal_PMPro::has_active_auto_renewal(
				$membership_owner_user_id,
				$membership_actions['current_level_id'] ?? null
			);
		}

		if ($linked_parent_user_id > 0 && $this->is_family_membership_pending_removal($user_id)) {
			$account_info['auto_renew'] = false;
		}

		$connected_accounts = get_user_meta($user_id, 'aac_connected_accounts', true);
		$connected_accounts = is_array($connected_accounts)
			? $this->sanitize_connected_accounts($connected_accounts)
			: [];
		if (class_exists('AAC_Member_Portal_Group_Accounts')) {
			$group_connected_accounts = AAC_Member_Portal_Group_Accounts::get_connected_accounts_for_parent($user_id);
			if (!empty($group_connected_accounts)) {
				$connected_accounts = $this->merge_connected_accounts(
					$connected_accounts,
					$this->sanitize_connected_accounts($group_connected_accounts)
				);
			}
		}

		$linked_parent_account = $this->build_linked_parent_account($user_id);
		$group_account = class_exists('AAC_Member_Portal_Group_Accounts')
			? AAC_Member_Portal_Group_Accounts::get_group_summary_for_user($user_id)
			: null;

		$family_membership = get_user_meta($user_id, 'aac_partner_family_config', true);
		$family_membership = is_array($family_membership)
			? $this->sanitize_family_membership($family_membership)
			: ['mode' => '', 'additional_adult' => false, 'dependent_count' => 0];
		if (($family_membership['mode'] ?? '') !== 'family' && class_exists('AAC_Member_Portal_Group_Accounts')) {
			$family_membership = $this->sanitize_family_membership(
				AAC_Member_Portal_Group_Accounts::get_family_config_for_parent($user_id)
			);
		}
		if (($family_membership['mode'] ?? '') !== 'family' && !empty($connected_accounts)) {
			$family_membership = $this->family_membership_from_connected_accounts($connected_accounts);
		}

		$profile = [
			'account_info' => $account_info,
			'profile_info' => $profile_info,
			'benefits_info' => $benefits_info,
			'membership_actions' => $membership_actions,
			'connected_accounts' => $connected_accounts,
			'family_membership' => $family_membership,
			'linked_parent_account' => $linked_parent_account,
			'group_account' => $group_account,
		];

		return apply_filters('aac_member_portal_profile', $profile, $user_id, $user);
	}

	private function build_profile_info($user_id, $membership_owner_user_id = null) {
		$member_id = $this->get_normalized_member_id_for_user($user_id);
		$membership_owner_user_id = $membership_owner_user_id ? (int) $membership_owner_user_id : (int) $user_id;
		$is_linked_child_account = $membership_owner_user_id > 0 && $membership_owner_user_id !== (int) $user_id;
		$family_membership_access_until = $is_linked_child_account
			? $this->get_family_membership_access_until($user_id)
			: '';
		$family_membership_pending_removal = $is_linked_child_account && $this->is_family_membership_pending_removal($user_id);
		$family_membership_active_until = $family_membership_pending_removal
			? $this->normalize_family_membership_access_date($family_membership_access_until)
			: '';
		$is_child_membership_expired = $family_membership_pending_removal
			&& !$this->is_family_membership_active_through($family_membership_active_until);

		if (AAC_Member_Portal_PMPro::is_available() && !$is_child_membership_expired) {
			// Child accounts borrow the parent's membership timing and status because
			// the family plan is really owned upstream by the parent. In the UI we
			// present them as Partner so members do not have to learn the secret lore
			// of helper tiers.
			$primary = AAC_Member_Portal_PMPro::get_primary_membership($membership_owner_user_id);
			if ($primary) {
				$valid_through_date = $family_membership_pending_removal
					? $family_membership_active_until
					: ($primary['valid_through_date'] ?: ($primary['expiration_date'] ?: $primary['renewal_date']));
				$status_reference_date = $valid_through_date;
				$primary_tier = $is_linked_child_account
					? 'Partner'
					: (isset($primary['tier']) && $primary['tier'] === 'Partner Family'
						? 'Partner'
						: $primary['tier']);
				$primary_status = sanitize_key((string) ($primary['status'] ?? ''));

					return [
						'member_id' => $member_id ?: (string) $user_id,
					'tier' => $primary_tier,
					'renewal_date' => $family_membership_pending_removal ? '' : $primary['renewal_date'],
					'expiration_date' => $family_membership_pending_removal ? $family_membership_active_until : $primary['expiration_date'],
					'valid_through_date' => $valid_through_date,
					'joined_date' => $primary['joined_date'] ?? '',
					'status' => $this->membership_status_pmpro($status_reference_date, $primary_status),
				];
			}
		}

		return [
			'member_id' => $member_id ?: (string) $user_id,
			'tier' => 'Free',
			'renewal_date' => '',
			'expiration_date' => '',
			'valid_through_date' => '',
			'joined_date' => '',
			'status' => 'Inactive',
		];
	}

	private function build_benefits_info($tier, $status = 'Active') {
		if ($status !== 'Active') {
			return [
				'rescue_amount' => 0,
				'medical_amount' => 0,
				'mortal_remains_amount' => 0,
				'rescue_reimbursement_process' => false,
			];
		}

		// Rescue values come from the admin-managed matrix now, which means staff can
		// update benefits without opening PHP and sighing deeply at line numbers.
		$settings = AAC_Member_Portal_Admin::get_settings();
		$rescue_levels = isset($settings['content']['rescue_levels']) && is_array($settings['content']['rescue_levels'])
			? $settings['content']['rescue_levels']
			: AAC_Member_Portal_Admin::get_default_rescue_levels();

		$matrix = [];
		foreach ($rescue_levels as $level) {
			if (!is_array($level)) {
				continue;
			}

			$level_name = sanitize_text_field($level['level_name'] ?? '');
			if ($level_name === '') {
				continue;
			}

			$matrix[strtolower($level_name)] = [
				'rescue_amount' => max(0, (int) ($level['rescue_amount'] ?? 0)),
				'medical_amount' => max(0, (int) ($level['medical_amount'] ?? 0)),
				'mortal_remains_amount' => max(0, (int) ($level['mortal_remains_amount'] ?? 0)),
				'rescue_reimbursement_process' => !empty($level['rescue_reimbursement_process']),
			];
		}

		$fallback = $matrix['free'] ?? [
			'rescue_amount' => 0,
			'medical_amount' => 0,
			'mortal_remains_amount' => 0,
			'rescue_reimbursement_process' => false,
		];

		$normalized_tier = strtolower(trim((string) $tier));
		// Helper/family tiers are really aliases in a nice outfit. We map them back
		// to the published parent tier so rescue benefits stay consistent.
		$tier_aliases = [
			'partner family' => 'partner',
			'partner adult' => 'partner',
			'partner dependent' => 'partner',
		];
		if (isset($tier_aliases[$normalized_tier])) {
			$normalized_tier = $tier_aliases[$normalized_tier];
		}

		return $matrix[$normalized_tier] ?? $fallback;
	}

	private function load_university_school_index() {
		if (is_array(self::$university_school_index)) {
			return self::$university_school_index;
		}

		self::$university_school_index = [];
		$data_path = AAC_MEMBER_PORTAL_DIR . 'includes/data/us-universities-dapip-static.json';
		if (!is_readable($data_path)) {
			$data_path = AAC_MEMBER_PORTAL_DIR . 'includes/data/us-universities-scorecard-seed.json';
		}

		if (!is_readable($data_path)) {
			return self::$university_school_index;
		}

		$payload = json_decode((string) file_get_contents($data_path), true);
		$schools = is_array($payload['schools'] ?? null) ? $payload['schools'] : [];

		foreach ($schools as $school) {
			if (!is_array($school)) {
				continue;
			}

			$name = trim((string) ($school['name'] ?? ''));
			if ($name === '') {
				continue;
			}

			$city = trim((string) ($school['city'] ?? ''));
			$state = trim((string) ($school['state'] ?? ''));
			$parent = trim((string) ($school['parent'] ?? ''));
			$location = trim(implode(', ', array_filter([$city, $state])));
			$campus_label = $parent !== '' && strcasecmp($parent, $name) !== 0 ? sprintf('%s (%s)', $name, $parent) : $name;
			$label = trim(implode(' - ', array_filter([$campus_label, $location])));
			$public = [
				'id' => sanitize_text_field((string) ($school['id'] ?? '')),
				'name' => sanitize_text_field($name),
				'city' => sanitize_text_field($city),
				'state' => sanitize_text_field($state),
				'parent' => sanitize_text_field($parent),
			];

			self::$university_school_index[] = [
				'label' => $label,
				'public' => $public,
				'name_search' => $this->normalize_university_search_value($name),
				'parent_search' => $this->normalize_university_search_value($parent),
				'location_search' => $this->normalize_university_search_value($location),
				'search' => $this->normalize_university_search_value(implode(' ', [
					$name,
					$parent,
					$city,
					$state,
					$school['type'] ?? '',
					$school['address'] ?? '',
				])),
			];
		}

		return self::$university_school_index;
	}

	private function normalize_university_search_value($value) {
		$value = strtolower((string) $value);
		$value = preg_replace('/[^a-z0-9]+/', ' ', $value);
		return trim((string) $value);
	}

	private function build_membership_actions($user_id, $profile_info) {
		if (AAC_Member_Portal_PMPro::is_available()) {
			return AAC_Member_Portal_PMPro::build_membership_actions($user_id, $profile_info);
		}

		return [
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
	}

	/**
	 * PMPro: empty renewal means no fixed expiration date, which we treat as active.
	 */
	private function membership_status_pmpro($renewal_date, $pmpro_status = '') {
		$pmpro_status = sanitize_key((string) $pmpro_status);
		if ($renewal_date === '' || $renewal_date === null) {
			return in_array($pmpro_status, ['expired', 'inactive'], true) ? 'Inactive' : 'Active';
		}

		$is_future_date = strtotime($renewal_date . ' 23:59:59') >= current_time('timestamp');
		if ($is_future_date || in_array($pmpro_status, ['active', 'trialing'], true)) {
			return 'Active';
		}

		return 'Inactive';
	}

	private function has_managed_membership_plugin() {
		return AAC_Member_Portal_PMPro::is_available();
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

	private function normalize_print_digital_value($value, $fallback = 'Print') {
		return $value === 'Print' ? 'Print' : ($value === 'Digital' ? 'Digital' : $fallback);
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

	private function sanitize_account_info($account_info, $stored_account_info = []) {
		$first_name = sanitize_text_field($account_info['first_name'] ?? '');
		$last_name = sanitize_text_field($account_info['last_name'] ?? '');
		$name = trim($first_name . ' ' . $last_name);
		if (!$name) {
			$name = sanitize_text_field($account_info['name'] ?? '');
		}

		$publication_preferences = $this->get_normalized_publication_preferences($account_info);

		$membership_discount_type = sanitize_key($account_info['membership_discount_type'] ?? '');
		if (!in_array($membership_discount_type, ['student', 'military'], true)) {
			$membership_discount_type = '';
		}

		$stored_account_info = is_array($stored_account_info) ? $stored_account_info : [];
		$stored_tshirt_size = $this->normalize_tshirt_size_value($stored_account_info['size'] ?? 'No T-shirt');

		return [
			'first_name' => $first_name,
			'last_name' => $last_name,
			'name' => $name,
			'email' => sanitize_email($account_info['email'] ?? ''),
			'photo_url' => esc_url_raw($account_info['photo_url'] ?? ''),
			'phone' => sanitize_text_field($account_info['phone'] ?? ''),
			'birthdate' => $this->sanitize_birthdate_value($account_info['birthdate'] ?? ''),
			'street' => sanitize_text_field($account_info['street'] ?? ''),
			'address2' => sanitize_text_field($account_info['address2'] ?? ''),
			'city' => sanitize_text_field($account_info['city'] ?? ''),
			'state' => sanitize_text_field($account_info['state'] ?? ''),
			'zip' => sanitize_text_field($account_info['zip'] ?? ''),
			'country' => sanitize_text_field($account_info['country'] ?? ''),
			'emergency_contact_first_name' => sanitize_text_field($account_info['emergency_contact_first_name'] ?? ''),
			'emergency_contact_last_name' => sanitize_text_field($account_info['emergency_contact_last_name'] ?? ''),
			'emergency_contact_phone' => sanitize_text_field($account_info['emergency_contact_phone'] ?? ''),
			'emergency_contact_email' => sanitize_email($account_info['emergency_contact_email'] ?? ''),
			'emergency_contact_relationship' => sanitize_text_field($account_info['emergency_contact_relationship'] ?? ''),
			'student_university' => sanitize_text_field($account_info['student_university'] ?? $account_info['university_or_school'] ?? ''),
			'student_university_id' => sanitize_text_field($account_info['student_university_id'] ?? $account_info['university_school_id'] ?? ''),
			'graduation_date' => $this->sanitize_birthdate_value($account_info['graduation_date'] ?? $account_info['student_graduation_date'] ?? ''),
			'service_component' => sanitize_text_field($account_info['service_component'] ?? $account_info['service_branch'] ?? $account_info['military_service_component'] ?? ''),
			'size' => $this->normalize_tshirt_size_value($account_info['size'] ?? $stored_tshirt_size, $stored_tshirt_size),
			'aaj_pref' => $publication_preferences['aaj_pref'],
			'anac_pref' => $publication_preferences['anac_pref'],
			'acj_pref' => $publication_preferences['acj_pref'],
			'guidebook_pref' => $publication_preferences['guidebook_pref'],
			'magazine_subscriptions' => array_values(array_filter(array_map('sanitize_text_field', (array) ($account_info['magazine_subscriptions'] ?? [])))),
			'membership_discount_type' => $membership_discount_type,
			'auto_renew' => !empty($account_info['auto_renew']),
		];
	}

	private function validate_required_account_info($account_info) {
		$required_fields = [
			'first_name' => 'First name',
			'last_name' => 'Last name',
			'email' => 'Email',
			'street' => 'Street address',
			'city' => 'City',
			'state' => 'State / Province',
			'zip' => 'ZIP / Postal code',
			'country' => 'Country',
		];

		foreach ($required_fields as $field_key => $field_label) {
			if (trim((string) ($account_info[$field_key] ?? '')) === '') {
				return new WP_Error(
					'missing_required_account_info',
					sprintf('%s is required.', $field_label),
					['status' => 400, 'field' => $field_key]
				);
			}
		}

		if (!is_email($account_info['email'] ?? '')) {
			return new WP_Error('invalid_email', 'A valid email address is required.', ['status' => 400, 'field' => 'email']);
		}

		return true;
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
			'emergency_contact_first_name',
			'emergency_contact_last_name',
			'emergency_contact_phone',
			'emergency_contact_email',
			'emergency_contact_relationship',
			'student_university',
			'student_university_id',
			'graduation_date',
			'service_component',
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

	private function hydrate_pmpro_managed_account_info($user_id, $account_info = []) {
		$account_info = is_array($account_info) ? $this->strip_pmpro_managed_account_fields_for_storage($account_info) : [];
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return $account_info;
		}

		$account_info['first_name'] = $this->get_preferred_user_meta_value($user_id, ['pmpro_sfirstname', 'first_name', 'bfirstname'], $account_info['first_name'] ?? '');
		$account_info['last_name'] = $this->get_preferred_user_meta_value($user_id, ['pmpro_slastname', 'last_name', 'blastname'], $account_info['last_name'] ?? '');
		$account_info['name'] = trim((string) ($account_info['first_name'] ?? '') . ' ' . (string) ($account_info['last_name'] ?? '')) ?: ($account_info['name'] ?? '');
		$account_info['phone'] = $this->get_preferred_user_meta_value($user_id, ['pmpro_sphone', 'bphone'], $account_info['phone'] ?? '');
		$account_info['street'] = $this->get_preferred_user_meta_value($user_id, ['pmpro_saddress1', 'saddress1', 'baddress1'], $account_info['street'] ?? '');
		$account_info['address2'] = $this->get_preferred_user_meta_value($user_id, ['pmpro_saddress2', 'saddress2', 'baddress2'], $account_info['address2'] ?? '');
		$account_info['city'] = $this->get_preferred_user_meta_value($user_id, ['pmpro_scity', 'scity', 'bcity'], $account_info['city'] ?? '');
		$account_info['state'] = $this->get_preferred_user_meta_value($user_id, ['pmpro_sstate', 'sstate', 'bstate'], $account_info['state'] ?? '');
		$account_info['zip'] = $this->get_preferred_user_meta_value($user_id, ['pmpro_szipcode', 'szipcode', 'bzipcode'], $account_info['zip'] ?? '');
		$account_info['country'] = $this->get_preferred_user_meta_value($user_id, ['pmpro_scountry', 'scountry', 'bcountry'], $account_info['country'] ?? '');
		$account_info['emergency_contact_first_name'] = sanitize_text_field(
			$this->get_preferred_user_meta_value(
				$user_id,
				$this->get_emergency_contact_meta_key_candidates('emergency_contact_first_name'),
				$account_info['emergency_contact_first_name'] ?? ''
			)
		);
		$account_info['emergency_contact_last_name'] = sanitize_text_field(
			$this->get_preferred_user_meta_value(
				$user_id,
				$this->get_emergency_contact_meta_key_candidates('emergency_contact_last_name'),
				$account_info['emergency_contact_last_name'] ?? ''
			)
		);
		$account_info['emergency_contact_phone'] = sanitize_text_field(
			$this->get_preferred_user_meta_value(
				$user_id,
				$this->get_emergency_contact_meta_key_candidates('emergency_contact_phone'),
				$account_info['emergency_contact_phone'] ?? ''
			)
		);
		$account_info['emergency_contact_email'] = sanitize_email(
			$this->get_preferred_user_meta_value(
				$user_id,
				$this->get_emergency_contact_meta_key_candidates('emergency_contact_email'),
				$account_info['emergency_contact_email'] ?? ''
			)
		);
		$account_info['emergency_contact_relationship'] = sanitize_text_field(
			$this->get_preferred_user_meta_value(
				$user_id,
				$this->get_emergency_contact_meta_key_candidates('emergency_contact_relationship'),
				$account_info['emergency_contact_relationship'] ?? ''
			)
		);
		$account_info['emergency_contact_relationship_options'] = $this->get_emergency_contact_relationship_options();
		$account_info['student_university'] = sanitize_text_field(
			$this->get_preferred_user_meta_value($user_id, ['student_university', 'university_or_school'], $account_info['student_university'] ?? '')
		);
		$account_info['student_university_id'] = sanitize_text_field(
			$this->get_preferred_user_meta_value($user_id, ['student_university_id', 'university_school_id'], $account_info['student_university_id'] ?? '')
		);
		$account_info['graduation_date'] = $this->sanitize_birthdate_value(
			$this->get_preferred_user_meta_value($user_id, ['graduation_date', 'student_graduation_date'], $account_info['graduation_date'] ?? '')
		);
		$account_info['service_component'] = sanitize_text_field(
			$this->get_preferred_user_meta_value($user_id, ['service_component', 'service_branch', 'military_service_component'], $account_info['service_component'] ?? '')
		);
		$account_info['birthdate'] = $this->sanitize_birthdate_value(
			$this->get_preferred_user_meta_value($user_id, ['birthdate'], $account_info['birthdate'] ?? '')
		);
		$account_info['size'] = $this->normalize_tshirt_size_value(
			$this->get_preferred_user_meta_value($user_id, ['t_shirt'], $account_info['size'] ?? 'No T-shirt')
		);
		unset($account_info['email_opt_out'], $account_info['do_not_call'], $account_info['do_not_contact']);
		$account_info['aaj_pref'] = $this->normalize_print_digital_value(
			$this->get_preferred_user_meta_value($user_id, ['aaj_preference'], $account_info['aaj_pref'] ?? 'Print')
		);
		$account_info['anac_pref'] = $this->normalize_print_digital_value(
			$this->get_preferred_user_meta_value($user_id, ['anac_preference'], $account_info['anac_pref'] ?? 'Print')
		);
		$account_info['acj_pref'] = $this->normalize_print_digital_value(
			$this->get_preferred_user_meta_value($user_id, ['american_climbing_journal_preference'], $account_info['acj_pref'] ?? 'Print')
		);
		$account_info['guidebook_pref'] = $this->normalize_print_digital_value(
			$this->get_preferred_user_meta_value($user_id, ['guidebook_preferences'], $account_info['guidebook_pref'] ?? 'Print')
		);
		unset($account_info['phone_type'], $account_info['payment_method']);

		return $account_info;
	}

	private function normalize_boolean_meta_value($value) {
		if (is_bool($value)) {
			return $value;
		}

		if (is_numeric($value)) {
			return (int) $value === 1;
		}

		$normalized = strtolower(trim((string) $value));
		return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
	}

	private function get_emergency_contact_meta_key_candidates($logical_key) {
		$fallback_map = [
			'emergency_contact_first_name' => ['emergency_contact_first_name', 'emergency_first_name', 'emergency_first'],
			'emergency_contact_last_name' => ['emergency_contact_last_name', 'emergency_last_name', 'emergency_last'],
			'emergency_contact_phone' => ['emergency_contact_phone', 'emergency_phone', 'emergency_contact_phone_number'],
			'emergency_contact_email' => ['emergency_contact_email', 'emergency_email'],
			'emergency_contact_relationship' => ['emergency_contact_relationship', 'emergency_relationship'],
		];

		$candidates = $fallback_map[$logical_key] ?? [$logical_key];
		if (function_exists('aac_member_portal') && aac_member_portal() && method_exists(aac_member_portal(), 'get_emergency_contact_meta_key_candidates')) {
			$resolved = aac_member_portal()->get_emergency_contact_meta_key_candidates($logical_key);
			if (is_array($resolved) && !empty($resolved)) {
				$candidates = array_merge($resolved, $candidates);
			}
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

	private function get_emergency_contact_relationship_options() {
		$options = [];
		if (function_exists('aac_member_portal') && aac_member_portal() && method_exists(aac_member_portal(), 'get_emergency_contact_relationship_options')) {
			$options = aac_member_portal()->get_emergency_contact_relationship_options();
		}

		if (!is_array($options) || empty($options)) {
			$options = [
				['value' => 'Spouse / Partner', 'label' => 'Spouse / Partner'],
				['value' => 'Parent', 'label' => 'Parent'],
				['value' => 'Sibling', 'label' => 'Sibling'],
				['value' => 'Child', 'label' => 'Child'],
				['value' => 'Friend', 'label' => 'Friend'],
				['value' => 'Other', 'label' => 'Other'],
			];
		}

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

	private function sanitize_profile_info($profile_info) {
		return [
			'member_id' => $this->normalize_member_id_value($profile_info['member_id'] ?? ''),
			'tier' => sanitize_text_field($profile_info['tier'] ?? ''),
			'renewal_date' => sanitize_text_field($profile_info['renewal_date'] ?? ''),
			'expiration_date' => sanitize_text_field($profile_info['expiration_date'] ?? ''),
			'joined_date' => sanitize_text_field($profile_info['joined_date'] ?? ''),
			'status' => sanitize_text_field($profile_info['status'] ?? ''),
		];
	}

	private function sanitize_benefits_info($benefits_info) {
		return [
			'rescue_amount' => intval($benefits_info['rescue_amount'] ?? 0),
			'medical_amount' => intval($benefits_info['medical_amount'] ?? 0),
			'mortal_remains_amount' => intval($benefits_info['mortal_remains_amount'] ?? 0),
			'rescue_reimbursement_process' => !empty($benefits_info['rescue_reimbursement_process']),
		];
	}

	private function sanitize_family_membership($family_membership) {
		if (!is_array($family_membership)) {
			return ['mode' => '', 'additional_adult' => false, 'dependent_count' => 0];
		}

		$mode = sanitize_key($family_membership['mode'] ?? '');
		if ($mode !== 'family') {
			$mode = '';
		}

		return [
			'mode' => $mode,
			'additional_adult' => !empty($family_membership['additional_adult']) && $mode === 'family',
			'dependent_count' => $mode === 'family' ? max(0, min(3, (int) ($family_membership['dependent_count'] ?? 0))) : 0,
		];
	}

	private function sanitize_connected_accounts($connected_accounts) {
		if (!is_array($connected_accounts)) {
			return [];
		}

		return array_values(array_filter(array_map(function ($account) {
			if (!is_array($account)) {
				return null;
			}

			$type = sanitize_key($account['type'] ?? '');
			if (!in_array($type, ['adult', 'dependent'], true)) {
				$type = 'dependent';
			}

			$status = sanitize_key($account['status'] ?? 'pending');
			if (!in_array($status, ['pending', 'connected', 'removal_pending'], true)) {
				$status = 'pending';
			}

			return [
				'id' => sanitize_text_field($account['id'] ?? wp_generate_uuid4()),
				'type' => $type,
				'label' => sanitize_text_field($account['label'] ?? 'Family member'),
				'status' => $status,
				'invite_code' => sanitize_text_field($account['invite_code'] ?? ''),
				'child_user_id' => absint($account['child_user_id'] ?? 0),
				'child_name' => sanitize_text_field($account['child_name'] ?? ''),
				'child_email' => sanitize_email($account['child_email'] ?? ''),
				'price' => round((float) ($account['price'] ?? 0), 2),
				'scheduled_removal_date' => $this->normalize_family_membership_access_date($account['scheduled_removal_date'] ?? ''),
			];
		}, $connected_accounts)));
	}

	private function merge_connected_accounts($stored_accounts, $group_accounts) {
		$merged = [];
		$seen = [];
		foreach (array_merge((array) $stored_accounts, (array) $group_accounts) as $account) {
			if (!is_array($account)) {
				continue;
			}

			$child_user_id = absint($account['child_user_id'] ?? 0);
			$slot_id = sanitize_text_field((string) ($account['id'] ?? ''));
			$key = $child_user_id > 0 ? 'child-' . $child_user_id : 'slot-' . $slot_id;
			if ($key === 'slot-' || isset($seen[$key])) {
				continue;
			}

			$seen[$key] = true;
			$merged[] = $account;
		}

		return $this->sanitize_connected_accounts($merged);
	}

	private function family_membership_from_connected_accounts($connected_accounts) {
		$dependent_count = 0;
		$has_adult = false;
		foreach ((array) $connected_accounts as $account) {
			if (!is_array($account) || ($account['status'] ?? '') === 'removal_pending') {
				continue;
			}

			if (($account['type'] ?? '') === 'adult') {
				$has_adult = true;
			} elseif (($account['type'] ?? '') === 'dependent') {
				$dependent_count++;
			}
		}

		return [
			'mode' => ($has_adult || $dependent_count > 0) ? 'family' : '',
			'additional_adult' => $has_adult,
			'dependent_count' => max(0, min(3, $dependent_count)),
		];
	}

	private function sanitize_linked_parent_account($linked_parent_account) {
		if (!is_array($linked_parent_account)) {
			return null;
		}

		return [
			'parent_user_id' => absint($linked_parent_account['parent_user_id'] ?? 0),
			'parent_name' => sanitize_text_field($linked_parent_account['parent_name'] ?? ''),
			'parent_email' => sanitize_email($linked_parent_account['parent_email'] ?? ''),
			'invite_code' => sanitize_text_field($linked_parent_account['invite_code'] ?? ''),
			'type' => sanitize_key($linked_parent_account['type'] ?? ''),
			'label' => sanitize_text_field($linked_parent_account['label'] ?? ''),
			'status' => sanitize_key($linked_parent_account['status'] ?? 'connected'),
			'scheduled_removal_date' => $this->normalize_family_membership_access_date($linked_parent_account['scheduled_removal_date'] ?? ''),
		];
	}

	private function build_linked_parent_account($user_id) {
		$parent_user_id = $this->get_linked_parent_user_id($user_id);
		if ($parent_user_id <= 0) {
			return null;
		}

		$parent_user = get_user_by('id', $parent_user_id);
		if (!$parent_user instanceof WP_User) {
			return null;
		}

		$slot_label = sanitize_text_field(get_user_meta($user_id, 'aac_linked_account_label', true));
		$slot_type = sanitize_key(get_user_meta($user_id, 'aac_linked_account_type', true));
		$invite_code = sanitize_text_field(get_user_meta($user_id, 'aac_linked_account_invite_code', true));
		$slot_status = $this->is_family_membership_pending_removal($user_id) ? 'removal_pending' : 'connected';
		$scheduled_removal_date = $this->get_family_membership_access_until($user_id);

		return $this->sanitize_linked_parent_account([
			'parent_user_id' => $parent_user_id,
			'parent_name' => trim($parent_user->first_name . ' ' . $parent_user->last_name) ?: $parent_user->display_name,
			'parent_email' => $parent_user->user_email,
			'invite_code' => $invite_code,
			'type' => $slot_type,
			'label' => $slot_label ?: 'Family member',
			'status' => $slot_status,
			'scheduled_removal_date' => $scheduled_removal_date,
		]);
	}

	private function get_linked_parent_user_id($user_id) {
		return absint(get_user_meta($user_id, 'aac_linked_parent_user_id', true));
	}

	private function normalize_invite_code($invite_code) {
		$invite_code = strtoupper(sanitize_text_field((string) $invite_code));
		return preg_replace('/[^A-Z0-9\-]/', '', $invite_code);
	}

	private function find_connected_account_slot_by_invite_code($invite_code) {
		$invite_code = $this->normalize_invite_code($invite_code);
		if ($invite_code === '') {
			return null;
		}

		$users = get_users([
			'meta_key' => 'aac_connected_accounts',
			'number' => -1,
			'fields' => ['ID', 'display_name', 'user_email'],
		]);

		foreach ($users as $user) {
			$accounts = get_user_meta($user->ID, 'aac_connected_accounts', true);
			$accounts = is_array($accounts) ? $this->sanitize_connected_accounts($accounts) : [];

			foreach ($accounts as $index => $account) {
				if ($this->normalize_invite_code($account['invite_code'] ?? '') !== $invite_code) {
					continue;
				}

				return [
					'parent_user_id' => (int) $user->ID,
					'parent_user' => $user,
					'accounts' => $accounts,
					'account' => $account,
					'account_index' => $index,
				];
			}
		}

		return null;
	}

	private function build_linked_account_invite_payload($match) {
		if (!is_array($match) || empty($match['account']) || empty($match['parent_user'])) {
			return null;
		}

		$parent_user = $match['parent_user'];
		$account = $match['account'];

		return [
			'code' => sanitize_text_field($account['invite_code'] ?? ''),
			'label' => sanitize_text_field($account['label'] ?? 'Family member'),
			'type' => sanitize_key($account['type'] ?? 'dependent'),
			'status' => sanitize_key($account['status'] ?? 'pending'),
			'price' => round((float) ($account['price'] ?? 0), 2),
			'parent_name' => trim(($parent_user->first_name ?? '') . ' ' . ($parent_user->last_name ?? '')) ?: $parent_user->display_name,
		];
	}

	private function send_linked_account_password_setup_email(WP_User $child_user, WP_User $parent_user, array $slot) {
		$reset_key = get_password_reset_key($child_user);
		if (is_wp_error($reset_key)) {
			return false;
		}

		$reset_url = network_site_url(
			'wp-login.php?action=rp&key=' . rawurlencode($reset_key) . '&login=' . rawurlencode($child_user->user_login),
			'login'
		);
		$parent_name = trim($parent_user->first_name . ' ' . $parent_user->last_name) ?: $parent_user->display_name;
		$slot_label = sanitize_text_field($slot['label'] ?? 'family member');

		return wp_mail(
			$child_user->user_email,
			'Set up your American Alpine Club account',
			"{$parent_name} created an American Alpine Club {$slot_label} account for you.\n\nUse this secure link to set your password and sign in:\n\n{$reset_url}\n\nIf you were not expecting this email, you can ignore it."
		);
	}

	private function generate_unique_username_from_email($email) {
		$email_parts = explode('@', (string) $email);
		$username = sanitize_user($email_parts[0] ?? 'aacmember', true);
		if (!$username) {
			$username = 'aacmember';
		}

		$base_username = $username;
		$suffix = 1;
		while (username_exists($username)) {
			$username = sprintf('%s%d', $base_username, $suffix);
			$suffix++;
		}

		return $username;
	}

	private function sync_wp_user_from_account_info($user_id, $account_info) {
		$user = get_user_by('id', $user_id);
		if (!$user instanceof WP_User) {
			return new WP_Error('invalid_user', 'Unable to update this member account right now.', ['status' => 400]);
		}

		$first_name = $account_info['first_name'] ?? '';
		$last_name = $account_info['last_name'] ?? '';
		$display_name = $account_info['name'] ?? trim($first_name . ' ' . $last_name);
		$email = $account_info['email'] ?? '';

		$user_update = [
			'ID' => $user_id,
			'first_name' => $first_name,
			'last_name' => $last_name,
			'display_name' => $display_name,
		];

		if ($email && is_email($email)) {
			$user_update['user_email'] = $email;
		}

		$result = wp_update_user($user_update);
		if (is_wp_error($result)) {
			return new WP_Error('profile_update_failed', $result->get_error_message(), ['status' => 400]);
		}

		$refreshed_user = get_user_by('id', $user_id);
		if (!$refreshed_user instanceof WP_User) {
			return new WP_Error('profile_update_failed', 'Unable to refresh account information after saving.', ['status' => 500]);
		}

		return array_merge($account_info, [
			'first_name' => $refreshed_user->first_name,
			'last_name' => $refreshed_user->last_name,
			'name' => $refreshed_user->display_name,
			'email' => $refreshed_user->user_email,
		]);
	}

	private function sync_reportable_member_fields($user_id, $account_info) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !is_array($account_info)) {
			return;
		}

		$publication_preferences = $this->get_normalized_publication_preferences($account_info);
		$first_name = sanitize_text_field($account_info['first_name'] ?? '');
		$last_name = sanitize_text_field($account_info['last_name'] ?? '');
		update_user_meta($user_id, 'first_name', $first_name);
		update_user_meta($user_id, 'last_name', $last_name);
		update_user_meta($user_id, 'pmpro_sfirstname', $first_name);
		update_user_meta($user_id, 'pmpro_slastname', $last_name);
		update_user_meta($user_id, 'birthdate', $this->sanitize_birthdate_value($account_info['birthdate'] ?? ''));
		update_user_meta($user_id, 't_shirt', sanitize_text_field($account_info['size'] ?? ''));
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
		update_user_meta($user_id, 'student_university', sanitize_text_field($account_info['student_university'] ?? ''));
		update_user_meta($user_id, 'university_or_school', sanitize_text_field($account_info['student_university'] ?? ''));
		update_user_meta($user_id, 'student_university_id', sanitize_text_field($account_info['student_university_id'] ?? ''));
		update_user_meta($user_id, 'graduation_date', $this->sanitize_birthdate_value($account_info['graduation_date'] ?? ''));
		update_user_meta($user_id, 'student_graduation_date', $this->sanitize_birthdate_value($account_info['graduation_date'] ?? ''));
		update_user_meta($user_id, 'service_component', sanitize_text_field($account_info['service_component'] ?? ''));
		update_user_meta($user_id, 'military_service_component', sanitize_text_field($account_info['service_component'] ?? ''));
		update_user_meta($user_id, 'aaj_preference', sanitize_text_field($publication_preferences['aaj_pref']));
		update_user_meta($user_id, 'anac_preference', sanitize_text_field($publication_preferences['anac_pref']));
		update_user_meta($user_id, 'american_climbing_journal_preference', sanitize_text_field($publication_preferences['acj_pref']));
		update_user_meta($user_id, 'guidebook_preferences', sanitize_text_field($publication_preferences['guidebook_pref']));
		delete_user_meta($user_id, 'aac_birthdate');
		delete_user_meta($user_id, 'aac_tshirt_size');
		delete_user_meta($user_id, 'aac_publication_pref');
		delete_user_meta($user_id, 'aac_aaj_pref');
		delete_user_meta($user_id, 'aac_anac_pref');
		delete_user_meta($user_id, 'aac_acj_pref');
		delete_user_meta($user_id, 'aac_guidebook_pref');

		$selected_addons = $this->get_member_magazine_subscription_slugs($user_id);
		$labels = $this->get_member_magazine_subscription_labels($user_id);

		update_user_meta($user_id, 'aac_magazine_subscription_labels', implode(', ', $labels));
		update_user_meta($user_id, 'aac_has_alpinist_subscription', in_array('alpinist', $selected_addons, true) ? '1' : '0');
		update_user_meta($user_id, 'aac_has_backcountry_subscription', in_array('backcountry', $selected_addons, true) ? '1' : '0');
		update_user_meta($user_id, 'aac_family_account_role', $this->get_family_account_role($user_id));
	}

	private function normalize_member_id_value($value) {
		$member_id = sanitize_text_field((string) $value);
		$member_id = trim($member_id);
		if ($member_id === '') {
			return '';
		}

		$member_id = preg_replace('/^AAC[\s\-_]*/i', '', $member_id);
		$member_id = trim((string) $member_id);

		if ($member_id !== '' && preg_match('/(\d+)/', $member_id, $matches)) {
			return (string) $matches[1];
		}

		return $member_id;
	}

	private function get_normalized_member_id_for_user($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return '';
		}

		$stored_profile = get_user_meta($user_id, 'aac_profile_info', true);
		$stored_profile = is_array($stored_profile) ? $stored_profile : [];
		$stored_member_id = $this->normalize_member_id_value(get_user_meta($user_id, 'aac_member_id', true));
		if ($stored_member_id !== '') {
			update_user_meta($user_id, 'aac_member_id', $stored_member_id);
			if (($stored_profile['member_id'] ?? '') !== $stored_member_id) {
				$stored_profile['member_id'] = $stored_member_id;
				update_user_meta($user_id, 'aac_profile_info', $stored_profile);
			}
			return $stored_member_id;
		}

		$profile_member_id = $this->normalize_member_id_value($stored_profile['member_id'] ?? '');
		if ($profile_member_id !== '') {
			update_user_meta($user_id, 'aac_member_id', $profile_member_id);
			$stored_profile['member_id'] = $profile_member_id;
			update_user_meta($user_id, 'aac_profile_info', $stored_profile);
			return $profile_member_id;
		}

		return '';
	}

	private function sanitize_birthdate_value($value) {
		$normalized = sanitize_text_field((string) $value);
		$normalized = trim($normalized);
		if ($normalized === '') {
			return '';
		}

		return preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized) ? $normalized : '';
	}

	private function get_family_account_role($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return '';
		}

		if ($this->get_linked_parent_user_id($user_id) > 0) {
			return 'Child';
		}

		$connected_accounts = get_user_meta($user_id, 'aac_connected_accounts', true);
		if (is_array($connected_accounts) && !empty($connected_accounts)) {
			return 'Parent';
		}

		return '';
	}

	private function get_family_membership_term_end_date($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !AAC_Member_Portal_PMPro::is_available()) {
			return '';
		}

		$primary = AAC_Member_Portal_PMPro::get_primary_membership($user_id);
		if (!is_array($primary) || empty($primary)) {
			return '';
		}

		$term_end_date = $primary['renewal_date'] ?: $primary['expiration_date'];
		if (class_exists('AAC_Member_Portal_PMPro')) {
			return $this->normalize_family_membership_access_date(
				AAC_Member_Portal_PMPro::normalize_date_to_month_end($term_end_date)
			);
		}

		return $this->normalize_family_membership_access_date($term_end_date);
	}

	private function get_family_membership_access_until($user_id) {
		return $this->normalize_family_membership_access_date(get_user_meta((int) $user_id, 'aac_family_membership_access_until', true));
	}

	private function is_family_membership_pending_removal($user_id) {
		return get_user_meta((int) $user_id, 'aac_family_membership_pending_removal', true) === '1';
	}

	private function normalize_family_membership_access_date($value) {
		$value = sanitize_text_field((string) $value);
		if ($value === '') {
			return '';
		}

		$timestamp = strtotime($value);
		if (!$timestamp) {
			return '';
		}

		return gmdate('Y-m-d', $timestamp);
	}

	private function is_family_membership_active_through($value) {
		$normalized = $this->normalize_family_membership_access_date($value);
		if ($normalized === '') {
			return false;
		}

		return strtotime($normalized . ' 23:59:59') >= current_time('timestamp');
	}

	private function clear_family_child_linkage($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return;
		}

		delete_user_meta($user_id, 'aac_linked_parent_user_id');
		delete_user_meta($user_id, 'aac_linked_account_slot_id');
		delete_user_meta($user_id, 'aac_linked_account_invite_code');
		delete_user_meta($user_id, 'aac_linked_account_type');
		delete_user_meta($user_id, 'aac_linked_account_label');
		delete_user_meta($user_id, 'aac_family_membership_access_until');
		delete_user_meta($user_id, 'aac_family_membership_pending_removal');
		update_user_meta($user_id, 'aac_family_account_role', '');
	}

	private function prune_expired_connected_accounts($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return;
		}

		$accounts = get_user_meta($user_id, 'aac_connected_accounts', true);
		$accounts = is_array($accounts) ? $this->sanitize_connected_accounts($accounts) : [];
		if (empty($accounts)) {
			return;
		}

		$did_prune = false;
		$accounts = array_values(array_filter($accounts, function ($account) use (&$did_prune) {
			$scheduled_removal_date = $this->normalize_family_membership_access_date($account['scheduled_removal_date'] ?? '');
			if (($account['status'] ?? '') !== 'removal_pending' || $this->is_family_membership_active_through($scheduled_removal_date)) {
				return true;
			}

			$this->clear_family_child_linkage(absint($account['child_user_id'] ?? 0));
			$did_prune = true;
			return false;
		}));

		if ($did_prune) {
			if (empty($accounts)) {
				delete_user_meta($user_id, 'aac_connected_accounts');
			} else {
				update_user_meta($user_id, 'aac_connected_accounts', $accounts);
			}
		}
	}

	private function expire_scheduled_family_access_if_needed($user_id) {
		$user_id = (int) $user_id;
		if ($user_id <= 0 || !$this->is_family_membership_pending_removal($user_id)) {
			return;
		}

		$access_until = $this->get_family_membership_access_until($user_id);
		if ($this->is_family_membership_active_through($access_until)) {
			return;
		}

		$parent_user_id = $this->get_linked_parent_user_id($user_id);
		$slot_id = sanitize_text_field(get_user_meta($user_id, 'aac_linked_account_slot_id', true));
		if ($parent_user_id > 0) {
			$accounts = get_user_meta($parent_user_id, 'aac_connected_accounts', true);
			$accounts = is_array($accounts) ? $this->sanitize_connected_accounts($accounts) : [];
			$accounts = array_values(array_filter($accounts, static function ($account) use ($slot_id, $user_id) {
				$account_child_user_id = absint($account['child_user_id'] ?? 0);
				$account_id = sanitize_text_field($account['id'] ?? '');
				return $account_child_user_id !== (int) $user_id && ($slot_id === '' || $account_id !== $slot_id);
			}));
			update_user_meta($parent_user_id, 'aac_connected_accounts', $accounts);
		}

		$this->clear_family_child_linkage($user_id);
	}

	private function get_member_magazine_subscription_slugs($user_id) {
		$stored = get_user_meta((int) $user_id, 'aac_magazine_addons', true);
		$stored = is_array($stored) ? $stored : [];
		$allowed = ['alpinist', 'backcountry'];

		return array_values(array_filter(array_map('sanitize_key', $stored), function ($slug) use ($allowed) {
			return in_array($slug, $allowed, true);
		}));
	}

	private function get_member_magazine_subscription_labels($user_id) {
		$labels_by_slug = [
			'alpinist' => 'Alpinist magazine',
			'backcountry' => 'Backcountry magazine',
		];

		return array_values(array_filter(array_map(function ($slug) use ($labels_by_slug) {
			return $labels_by_slug[$slug] ?? null;
		}, $this->get_member_magazine_subscription_slugs($user_id))));
	}

	private function consume_rate_limit($action, $identity, $limit, $window_seconds) {
		$key = 'aac_rate_limit_' . md5($action . '|' . $identity);
		$state = get_transient($key);
		$state = is_array($state) ? $state : ['count' => 0];
		$state['count'] = isset($state['count']) ? (int) $state['count'] + 1 : 1;

		set_transient($key, $state, (int) $window_seconds);

		if ($state['count'] > (int) $limit) {
			return new WP_Error(
				'rate_limited',
				'Too many attempts. Please wait a few minutes and try again.',
				['status' => 429]
			);
		}

		return true;
	}

	private function build_rate_limit_identity(WP_REST_Request $request, $email = '') {
		$email = strtolower(trim((string) $email));
		$ip_address = '';

		if (method_exists($request, 'get_header')) {
			$forwarded = $request->get_header('x_forwarded_for');
			if ($forwarded) {
				$parts = array_map('trim', explode(',', $forwarded));
				$ip_address = (string) ($parts[0] ?? '');
			}

			if ($ip_address === '') {
				$ip_address = (string) $request->get_header('x_real_ip');
			}
		}

		if ($ip_address === '' && !empty($_SERVER['REMOTE_ADDR'])) {
			$ip_address = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
		}

		return $email !== '' ? $ip_address . '|' . $email : $ip_address;
	}
}
