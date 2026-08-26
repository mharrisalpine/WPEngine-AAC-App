<?php

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Member_Portal_Redpoint_API {
	const OPTION_KEY = 'aac_member_portal_redpoint_api_settings';
	const PAGE_SLUG = 'aac-member-portal-redpoint-api';
	const ROUTE_NAMESPACE = 'aac-redpoint/v1';
	const RIPCORD_ROUTE_NAMESPACE = 'aac-ripcord/v2';

	public function __construct() {
		add_action('rest_api_init', [$this, 'register_routes']);
		add_action('admin_post_aac_redpoint_api_save', [$this, 'handle_settings_save']);
		add_action('template_redirect', [$this, 'maybe_render_directory_page']);
	}

	public function register_routes() {
		register_rest_route(self::ROUTE_NAMESPACE, '/token', [
			'methods' => 'POST',
			'callback' => [$this, 'issue_token'],
			'permission_callback' => '__return_true',
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/members/by-email', [
			'methods' => 'GET',
			'callback' => [$this, 'get_member_by_email'],
			'permission_callback' => [$this, 'authorize_request'],
		]);

		register_rest_route(self::ROUTE_NAMESPACE, '/members', [
			'methods' => 'GET',
			'callback' => [$this, 'search_members'],
			'permission_callback' => [$this, 'authorize_request'],
		]);

		register_rest_route(self::RIPCORD_ROUTE_NAMESPACE, '/token', [
			'methods' => 'POST',
			'callback' => [$this, 'issue_token'],
			'permission_callback' => '__return_true',
		]);

		register_rest_route(self::RIPCORD_ROUTE_NAMESPACE, '/user', [
			'methods' => 'GET',
			'callback' => [$this, 'get_ripcord_user'],
			'permission_callback' => [$this, 'authorize_request'],
		]);
	}

	public function register_admin_page() {
		add_submenu_page(
			AAC_Member_Portal_Admin::MENU_SLUG,
			'Redpoint API',
			'Redpoint API',
			'manage_options',
			self::PAGE_SLUG,
			[$this, 'render_admin_page']
		);
	}

	public function render_admin_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$settings = $this->get_settings();
		$token_endpoint = rest_url(self::ROUTE_NAMESPACE . '/token');
		$email_endpoint = rest_url(self::ROUTE_NAMESPACE . '/members/by-email');
		$search_endpoint = rest_url(self::ROUTE_NAMESPACE . '/members');
		$ripcord_token_endpoint = rest_url(self::RIPCORD_ROUTE_NAMESPACE . '/token');
		$ripcord_user_endpoint = rest_url(self::RIPCORD_ROUTE_NAMESPACE . '/user');
		?>
		<div class="wrap">
			<h1>Redpoint API</h1>
			<p>This API is designed for Redpoint/Ripcord to retrieve structured member data from the PMPro-backed member database mirror.</p>

			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width: 780px;">
				<?php wp_nonce_field('aac_redpoint_api_save'); ?>
				<input type="hidden" name="action" value="aac_redpoint_api_save" />

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="aac_redpoint_api_username">API Username</label></th>
							<td>
								<input type="text" class="regular-text" id="aac_redpoint_api_username" name="aac_redpoint_api_username" value="<?php echo esc_attr($settings['username']); ?>" />
								<p class="description">Dedicated username Ripcord will use to request JWT tokens.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="aac_redpoint_api_password">API Password</label></th>
							<td>
								<input type="text" class="regular-text" id="aac_redpoint_api_password" name="aac_redpoint_api_password" value="" autocomplete="new-password" />
								<p class="description">Enter a new password to rotate credentials. Leave blank to keep the current password.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="aac_redpoint_api_token_ttl">Token Lifetime (seconds)</label></th>
							<td>
								<input type="number" class="small-text" min="300" step="60" id="aac_redpoint_api_token_ttl" name="aac_redpoint_api_token_ttl" value="<?php echo esc_attr((string) $settings['token_ttl']); ?>" />
								<p class="description">Default is 43200 seconds (12 hours).</p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button('Save Redpoint API Settings'); ?>
			</form>

			<hr />

			<h2>Endpoints</h2>
			<ul style="list-style:disc;padding-left:20px;">
				<li><code>POST <?php echo esc_html($token_endpoint); ?></code></li>
				<li><code>GET <?php echo esc_html($email_endpoint); ?>?email=member@example.com</code></li>
				<li><code>GET <?php echo esc_html($search_endpoint); ?>?search=smith&amp;membership_tier=Partner&amp;active=1</code></li>
				<li><code>POST <?php echo esc_html($ripcord_token_endpoint); ?></code></li>
				<li><code>GET <?php echo esc_html($ripcord_user_endpoint); ?>?email=member@example.com</code></li>
				<li><code>GET <?php echo esc_html($ripcord_user_endpoint); ?>?firstname=Michelle&amp;lastname=Hoffman</code></li>
			</ul>

			<h2>Security and Limits</h2>
			<ul style="list-style:disc;padding-left:20px;">
				<li>JWT bearer tokens signed with an AAC-managed secret.</li>
				<li>Rate limit: <strong>10 requests per 2 minutes per IP address</strong>.</li>
				<li>Searches are served from the mirrored AAC member database, not direct public frontend endpoints.</li>
			</ul>

			<h2>Sample Token Request</h2>
			<pre style="background:#fff;border:1px solid #dcdcde;padding:16px;overflow:auto;">curl -X POST <?php echo esc_html($token_endpoint); ?> \
  -H 'Content-Type: application/json' \
  -d '{
    "username": "<?php echo esc_html($settings['username']); ?>",
    "password": "REPLACE_WITH_PASSWORD"
  }'</pre>

			<h2>Sample Search Request</h2>
			<pre style="background:#fff;border:1px solid #dcdcde;padding:16px;overflow:auto;">curl '<?php echo esc_html($search_endpoint); ?>?membership_status=Active&amp;membership_tier=Partner' \
  -H 'Authorization: Bearer YOUR_JWT_TOKEN'</pre>

			<h2>Sample Ripcord Request</h2>
			<pre style="background:#fff;border:1px solid #dcdcde;padding:16px;overflow:auto;">curl '<?php echo esc_html($ripcord_user_endpoint); ?>?email=bbowling@americanalpineclub.org' \
  -H 'Authorization: Bearer YOUR_JWT_TOKEN'</pre>
		</div>
		<?php
	}

	public function handle_settings_save() {
		if (!current_user_can('manage_options')) {
			wp_die('Unauthorized.', 403);
		}

		check_admin_referer('aac_redpoint_api_save');
		$current = $this->get_settings();
		$username = sanitize_user(wp_unslash($_POST['aac_redpoint_api_username'] ?? ''), true);
		$password = (string) wp_unslash($_POST['aac_redpoint_api_password'] ?? '');
		$token_ttl = max(300, (int) ($_POST['aac_redpoint_api_token_ttl'] ?? $current['token_ttl']));

		$next = $current;
		$next['username'] = $username !== '' ? $username : $current['username'];
		$next['token_ttl'] = $token_ttl;
		if ($password !== '') {
			$next['password_hash'] = wp_hash_password($password);
		}

		update_option(self::OPTION_KEY, $next, false);

		wp_safe_redirect(add_query_arg(['page' => self::PAGE_SLUG, 'updated' => '1'], admin_url('admin.php')));
		exit;
	}

	public function issue_token(WP_REST_Request $request) {
		$rate_limit = $this->consume_rate_limit('redpoint_token', $this->build_rate_limit_identity($request), 10, 2 * MINUTE_IN_SECONDS);
		if (is_wp_error($rate_limit)) {
			return $rate_limit;
		}

		$settings = $this->get_settings();
		$username = sanitize_user((string) $request->get_param('username'), true);
		$password = (string) $request->get_param('password');

		if ($username === '' || $password === '') {
			return new WP_Error('invalid_request', 'Username and password are required.', ['status' => 400]);
		}

		if (!hash_equals($settings['username'], $username) || empty($settings['password_hash']) || !wp_check_password($password, $settings['password_hash'])) {
			return new WP_Error('invalid_credentials', 'Invalid Redpoint API credentials.', ['status' => 401]);
		}

		$now = time();
		$payload = [
			'iss' => home_url('/'),
			'aud' => 'redpoint',
			'sub' => $username,
			'iat' => $now,
			'nbf' => $now,
			'exp' => $now + (int) $settings['token_ttl'],
		];

		$token = $this->encode_jwt($payload, $settings['jwt_secret']);

		return rest_ensure_response([
			'token_type' => 'Bearer',
			'access_token' => $token,
			'expires_in' => (int) $settings['token_ttl'],
			'expires_at' => gmdate('c', $payload['exp']),
		]);
	}

	public function authorize_request(WP_REST_Request $request) {
		$rate_limit = $this->consume_rate_limit('redpoint_api', $this->build_rate_limit_identity($request), 10, 2 * MINUTE_IN_SECONDS);
		if (is_wp_error($rate_limit)) {
			return $rate_limit;
		}

		$token = $this->extract_bearer_token($request);
		if ($token === '') {
			return new WP_Error('missing_token', 'A bearer token is required.', ['status' => 401]);
		}

		$settings = $this->get_settings();
		$claims = $this->decode_jwt($token, $settings['jwt_secret']);
		if (is_wp_error($claims)) {
			return $claims;
		}

		return true;
	}

	public function get_member_by_email(WP_REST_Request $request) {
		$email = sanitize_email((string) $request->get_param('email'));
		if ($email === '') {
			return new WP_Error('invalid_request', 'A valid email address is required.', ['status' => 400]);
		}

		$row = $this->find_profile_by_email($email);
		if (!$row) {
			return new WP_Error('not_found', 'No member found for that email address.', ['status' => 404]);
		}

		return rest_ensure_response([
			'member' => $this->format_redpoint_member($row),
		]);
	}

	public function search_members(WP_REST_Request $request) {
		$args = [
			'search' => sanitize_text_field((string) $request->get_param('search')),
			'first_name' => sanitize_text_field((string) $request->get_param('first_name')),
			'last_name' => sanitize_text_field((string) $request->get_param('last_name')),
			'membership_status' => sanitize_text_field((string) $request->get_param('membership_status')),
			'active' => $this->normalize_boolean_param($request->get_param('active')),
		];

		$results = $this->query_exact_profiles($args);

		return rest_ensure_response([
			'total' => $results['total'],
			'members' => array_map([$this, 'format_redpoint_member'], $results['rows']),
		]);
	}

	public function get_ripcord_user(WP_REST_Request $request) {
		$email = sanitize_email((string) $request->get_param('email'));
		$firstname = sanitize_text_field((string) $request->get_param('firstname'));
		$lastname = sanitize_text_field((string) $request->get_param('lastname'));

		if ($email !== '') {
			$row = $this->find_profile_by_email($email);
			if (!$row) {
				$this->send_text_response("Status: 404\nContent-Type: text/plain\n\nNo member found for that email address.", 404);
			}

			$this->send_text_response($this->format_ripcord_single_member_text($row), 200);
		}

		if ($firstname !== '' && $lastname !== '') {
			$rows = $this->find_profiles_by_exact_name($firstname, $lastname);
			if (!$rows) {
				$this->send_text_response("Status: 404\nContent-Type: text/plain\n\nNo members found for that exact first name and last name.", 404);
			}

			$this->send_text_response($this->format_ripcord_multi_member_text($rows), 200);
		}

		$this->send_text_response(
			"Status: 400\nContent-Type: text/plain\n\nProvide either an exact email address, or both firstname and lastname.",
			400
		);
	}

	public function maybe_render_directory_page() {
		if (!$this->is_redpoint_directory_request()) {
			return;
		}

		$args = $this->get_directory_query_args();
		$results = $this->query_exact_profiles($args);
		$members = array_map([$this, 'format_redpoint_member'], $results['rows']);

		$template = AAC_MEMBER_PORTAL_DIR . 'templates/redpoint-directory.php';
		if (!file_exists($template)) {
			wp_die('The Redpoint directory template is missing.', 'Template Missing', ['response' => 500]);
		}

		status_header(200);
		nocache_headers();
		include $template;
		exit;
	}

	private function get_settings() {
		$stored = get_option(self::OPTION_KEY, []);
		$stored = is_array($stored) ? $stored : [];
		$settings = array_merge([
			'username' => 'redpoint',
			'password_hash' => '',
			'jwt_secret' => wp_generate_password(64, true, true),
			'token_ttl' => 12 * HOUR_IN_SECONDS,
		], $stored);

		if (empty($stored['jwt_secret'])) {
			update_option(self::OPTION_KEY, $settings, false);
		}

		return $settings;
	}

	private function is_redpoint_directory_request() {
		if (is_admin()) {
			return false;
		}

		$request_path = '/';
		if (!empty($_SERVER['REQUEST_URI'])) {
			$request_path = (string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH);
		}

		$request_path = '/' . trim($request_path, '/') . '/';
		return in_array($request_path, ['/redpoint/', '/redpoint-lookup/'], true);
	}

	private function get_directory_query_args() {
		return [
			'search' => sanitize_text_field((string) wp_unslash($_GET['search'] ?? '')),
			'first_name' => sanitize_text_field((string) wp_unslash($_GET['first_name'] ?? '')),
			'last_name' => sanitize_text_field((string) wp_unslash($_GET['last_name'] ?? '')),
			'membership_status' => '',
			'active' => $this->normalize_boolean_param(wp_unslash($_GET['active'] ?? '')),
		];
	}

	private function consume_rate_limit($action, $identity, $limit, $window_seconds) {
		$key = 'aac_redpoint_rate_' . md5($action . '|' . $identity);
		$state = get_transient($key);
		$state = is_array($state) ? $state : ['count' => 0];
		$state['count'] = isset($state['count']) ? (int) $state['count'] + 1 : 1;
		set_transient($key, $state, (int) $window_seconds);

		if ($state['count'] > (int) $limit) {
			return new WP_Error('rate_limited', 'Too many requests from this IP. Please wait and try again.', ['status' => 429]);
		}

		return true;
	}

	private function build_rate_limit_identity(WP_REST_Request $request) {
		$ip_address = '';
		$forwarded = (string) $request->get_header('x_forwarded_for');
		if ($forwarded !== '') {
			$parts = array_map('trim', explode(',', $forwarded));
			$ip_address = (string) ($parts[0] ?? '');
		}

		if ($ip_address === '') {
			$ip_address = (string) $request->get_header('x_real_ip');
		}

		if ($ip_address === '' && !empty($_SERVER['REMOTE_ADDR'])) {
			$ip_address = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
		}

		return $ip_address;
	}

	private function extract_bearer_token(WP_REST_Request $request) {
		$header = (string) $request->get_header('authorization');
		if ($header === '' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
			$header = (string) wp_unslash($_SERVER['HTTP_AUTHORIZATION']);
		}

		if (preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
			return trim((string) $matches[1]);
		}

		return '';
	}

	private function encode_jwt($payload, $secret) {
		$header = ['typ' => 'JWT', 'alg' => 'HS256'];
		$segments = [
			$this->base64url_encode(wp_json_encode($header)),
			$this->base64url_encode(wp_json_encode($payload)),
		];
		$signing_input = implode('.', $segments);
		$signature = hash_hmac('sha256', $signing_input, (string) $secret, true);
		$segments[] = $this->base64url_encode($signature);
		return implode('.', $segments);
	}

	private function decode_jwt($token, $secret) {
		$parts = explode('.', (string) $token);
		if (count($parts) !== 3) {
			return new WP_Error('invalid_token', 'Malformed JWT token.', ['status' => 401]);
		}

		[$header64, $payload64, $signature64] = $parts;
		$header = json_decode($this->base64url_decode($header64), true);
		$payload = json_decode($this->base64url_decode($payload64), true);
		$signature = $this->base64url_decode($signature64);

		if (!is_array($header) || !is_array($payload) || ($header['alg'] ?? '') !== 'HS256') {
			return new WP_Error('invalid_token', 'Unsupported JWT token.', ['status' => 401]);
		}

		$expected = hash_hmac('sha256', $header64 . '.' . $payload64, (string) $secret, true);
		if (!hash_equals($expected, $signature)) {
			return new WP_Error('invalid_token', 'JWT signature verification failed.', ['status' => 401]);
		}

		$now = time();
		if (!empty($payload['nbf']) && $now < (int) $payload['nbf']) {
			return new WP_Error('invalid_token', 'JWT token is not active yet.', ['status' => 401]);
		}
		if (empty($payload['exp']) || $now >= (int) $payload['exp']) {
			return new WP_Error('invalid_token', 'JWT token has expired.', ['status' => 401]);
		}

		return $payload;
	}

	private function base64url_encode($value) {
		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
	}

	private function base64url_decode($value) {
		$padding = strlen($value) % 4;
		if ($padding > 0) {
			$value .= str_repeat('=', 4 - $padding);
		}

		return (string) base64_decode(strtr($value, '-_', '+/'));
	}

	private function get_profiles_table() {
		global $wpdb;
		return $wpdb->prefix . 'aac_member_db_profiles';
	}

	private function find_profile_by_email($email) {
		global $wpdb;
		$table = $this->get_profiles_table();
		return $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$table} WHERE email = %s LIMIT 1", $email),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
	}

	private function query_profiles($args) {
		global $wpdb;
		$table = $this->get_profiles_table();
		$where = ['1=1'];
		$params = [];

		if (!empty($args['search'])) {
			$like = '%' . $wpdb->esc_like($args['search']) . '%';
			$where[] = '(display_name LIKE %s OR email LIKE %s OR member_id LIKE %s)';
			array_push($params, $like, $like, $like);
		}

		if (!empty($args['member_id'])) {
			$where[] = 'member_id = %s';
			$params[] = $args['member_id'];
		}

		if (!empty($args['membership_tier'])) {
			$where[] = 'membership_level = %s';
			$params[] = $args['membership_tier'];
		}

		if (!empty($args['active'])) {
			$where[] = 'membership_status = %s';
			$params[] = 'Active';
		} elseif (!empty($args['membership_status'])) {
			$where[] = 'membership_status = %s';
			$params[] = $args['membership_status'];
		}

		$where_sql = implode(' AND ', $where);
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$data_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY display_name ASC LIMIT %d OFFSET %d";

		$page = max(1, (int) ($args['page'] ?? 1));
		$per_page = min(100, max(1, (int) ($args['per_page'] ?? 25)));
		$offset = ($page - 1) * $per_page;

		$count_params = $params;
		$data_params = array_merge($params, [$per_page, $offset]);

		$prepared_count_sql = !empty($count_params) ? $wpdb->prepare($count_sql, $count_params) : $count_sql;
		$prepared_data_sql = $wpdb->prepare($data_sql, $data_params);

		$total = (int) $wpdb->get_var($prepared_count_sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above when needed.
		$rows = $wpdb->get_results($prepared_data_sql, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.

		return [
			'page' => $page,
			'per_page' => $per_page,
			'total' => $total,
			'rows' => is_array($rows) ? $rows : [],
		];
	}

	private function query_exact_profiles($args) {
		$search = trim((string) ($args['search'] ?? ''));
		$first_name = trim((string) ($args['first_name'] ?? ''));
		$last_name = trim((string) ($args['last_name'] ?? ''));
		if (($first_name === '' || $last_name === '') && $search !== '' && !$this->looks_like_email($search) && $this->normalize_phone($search) === '') {
			$name_parts = preg_split('/\s+/', $search);
			$name_parts = is_array($name_parts) ? array_values(array_filter($name_parts, 'strlen')) : [];
			if (count($name_parts) >= 2) {
				$first_name = (string) array_shift($name_parts);
				$last_name = implode(' ', $name_parts);
			}
		}

		if ($search === '' && ($first_name === '' || $last_name === '')) {
			return [
				'total' => 0,
				'rows' => [],
			];
		}

		if ($first_name !== '' && $last_name !== '') {
			$rows = $this->find_profiles_by_exact_name($first_name, $last_name);
		} elseif ($this->looks_like_email($search)) {
			$row = $this->find_profile_by_email($search);
			$rows = $row ? [$row] : [];
		} else {
			$rows = $this->find_profiles_by_exact_phone($search);
		}

		if (!empty($args['active'])) {
			$rows = array_values(array_filter($rows, static function ($row) {
				return (($row['membership_status'] ?? '') === 'Active');
			}));
		} elseif (!empty($args['membership_status'])) {
			$membership_status = (string) $args['membership_status'];
			$rows = array_values(array_filter($rows, static function ($row) use ($membership_status) {
				return (($row['membership_status'] ?? '') === $membership_status);
			}));
		}

		return [
			'total' => count($rows),
			'rows' => $rows,
		];
	}

	private function find_profiles_by_exact_name($first_name, $last_name) {
		global $wpdb;

		$normalized_first_name = $this->normalize_name_part($first_name);
		$normalized_last_name = $this->normalize_name_part($last_name);
		if ($normalized_first_name === '' || $normalized_last_name === '') {
			return [];
		}

		$table = $this->get_profiles_table();
		$rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY display_name ASC", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- static query.
		if (!is_array($rows)) {
			return [];
		}

		return array_values(array_filter($rows, function ($row) use ($normalized_first_name, $normalized_last_name) {
			$profile = json_decode($row['raw_profile'] ?? '', true);
			$profile = is_array($profile) ? $profile : [];
			$account_info = is_array($profile['account_info'] ?? null) ? $profile['account_info'] : [];

			$stored_first_name = $this->normalize_name_part((string) ($account_info['first_name'] ?? ''));
			$stored_last_name = $this->normalize_name_part((string) ($account_info['last_name'] ?? ''));

			return $stored_first_name === $normalized_first_name && $stored_last_name === $normalized_last_name;
		}));
	}

	private function find_profiles_by_exact_phone($phone) {
		global $wpdb;

		$normalized_phone = $this->normalize_phone($phone);
		if ($normalized_phone === '') {
			return [];
		}

		$table = $this->get_profiles_table();
		$rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY display_name ASC", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- static query.
		if (!is_array($rows)) {
			return [];
		}

		return array_values(array_filter($rows, function ($row) use ($normalized_phone) {
			$profile = json_decode($row['raw_profile'] ?? '', true);
			$profile = is_array($profile) ? $profile : [];
			$account_info = is_array($profile['account_info'] ?? null) ? $profile['account_info'] : [];
			$stored_phone = $this->normalize_phone((string) ($account_info['phone'] ?? ''));
			return $stored_phone !== '' && $stored_phone === $normalized_phone;
		}));
	}

	private function format_redpoint_member($row) {
		$profile = json_decode($row['raw_profile'] ?? '', true);
		$profile = is_array($profile) ? $profile : [];
		$account_info = is_array($profile['account_info'] ?? null) ? $profile['account_info'] : [];
		$profile_info = is_array($profile['profile_info'] ?? null) ? $profile['profile_info'] : [];
		$pmpro_membership = is_array($profile['pmpro_membership'] ?? null) ? $profile['pmpro_membership'] : [];
		$pmpro_subscription = is_array($profile['pmpro_subscription'] ?? null) ? $profile['pmpro_subscription'] : [];
		$membership_start_date = $this->normalize_date_for_output($pmpro_membership['startdate'] ?? '');
		if ($membership_start_date === '') {
			$membership_start_date = $this->normalize_date_for_output($pmpro_subscription['startdate'] ?? '');
		}
		$membership_end_date = $this->normalize_date_for_output($pmpro_membership['enddate'] ?? '');
		if ($membership_end_date === '') {
			$membership_end_date = $this->normalize_date_for_output($row['expiration_date'] ?? '');
		}
		if ($membership_end_date === '') {
			$membership_end_date = $this->normalize_date_for_output($row['renewal_date'] ?? '');
		}
		if ($membership_end_date === '') {
			$membership_end_date = $this->normalize_date_for_output($profile_info['valid_through_date'] ?? '');
		}
		if ($membership_end_date === '') {
			foreach (['next_payment_date', 'cycle_enddate', 'enddate'] as $subscription_date_key) {
				$membership_end_date = $this->normalize_date_for_output($pmpro_subscription[$subscription_date_key] ?? '');
				if ($membership_end_date !== '') {
					break;
				}
			}
		}

		return [
			'user_id' => (int) ($row['user_id'] ?? 0),
			'member_id' => (string) ($row['member_id'] ?? ''),
			'name' => (string) ($row['display_name'] ?? ''),
			'email' => (string) ($row['email'] ?? ''),
			'contact' => [
				'phone' => (string) ($account_info['phone'] ?? ''),
				'street' => (string) ($account_info['street'] ?? ''),
				'address2' => (string) ($account_info['address2'] ?? ''),
				'city' => (string) ($account_info['city'] ?? ''),
				'state' => (string) ($account_info['state'] ?? ''),
				'zip' => (string) ($account_info['zip'] ?? ''),
				'country' => (string) ($account_info['country'] ?? ''),
			],
			'emergency_contact' => [
				'first_name' => (string) ($account_info['emergency_contact_first_name'] ?? ''),
				'last_name' => (string) ($account_info['emergency_contact_last_name'] ?? ''),
				'phone' => (string) ($account_info['emergency_contact_phone'] ?? ''),
				'email' => (string) ($account_info['emergency_contact_email'] ?? ''),
				'relationship' => (string) ($account_info['emergency_contact_relationship'] ?? ''),
			],
			'membership' => [
				'tier' => (string) ($row['membership_level'] ?? ''),
				'status' => (string) ($row['membership_status'] ?? ''),
				'renewal_date' => (string) ($row['renewal_date'] ?? ''),
				'expiration_date' => (string) ($row['expiration_date'] ?? ''),
				'start_date' => $membership_start_date,
				'end_date' => $membership_end_date,
				'member_since' => (string) ($profile_info['joined_date'] ?? ''),
			],
		];
	}

	private function normalize_date_for_output($value) {
		$value = sanitize_text_field((string) $value);
		if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
			return '';
		}

		$timestamp = strtotime($value);
		return $timestamp === false ? $value : gmdate('Y-m-d', $timestamp);
	}

	private function build_ripcord_member_attributes($row, $include_email = true) {
		$profile = json_decode($row['raw_profile'] ?? '', true);
		$profile = is_array($profile) ? $profile : [];
		$account_info = is_array($profile['account_info'] ?? null) ? $profile['account_info'] : [];
		$profile_info = is_array($profile['profile_info'] ?? null) ? $profile['profile_info'] : [];
		$pmpro_membership = is_array($profile['pmpro_membership'] ?? null) ? $profile['pmpro_membership'] : [];

		$first_name = sanitize_text_field((string) ($account_info['first_name'] ?? ''));
		$last_name = sanitize_text_field((string) ($account_info['last_name'] ?? ''));
		$join_date = sanitize_text_field((string) ($profile_info['joined_date'] ?? ''));
		$membership_expiration_date = sanitize_text_field((string) ($row['expiration_date'] ?? ''));
		$active_member = (($row['membership_status'] ?? '') === 'Active');
		$active_level_name = $active_member ? sanitize_text_field((string) ($row['membership_level'] ?? '')) : '';
		$active_grf = $active_member && strtoupper($active_level_name) === 'GRF';
		$city = sanitize_text_field((string) ($account_info['city'] ?? ''));
		$state = sanitize_text_field((string) ($account_info['state'] ?? ''));
		$main_address = trim(implode(', ', array_filter([$city, $state])));
		if ($main_address === '') {
			$main_address = 'No Main Address on file';
		}
		$last_membership_start_date = sanitize_text_field((string) ($pmpro_membership['startdate'] ?? ''));
		if ($last_membership_start_date !== '') {
			$timestamp = strtotime($last_membership_start_date);
			$last_membership_start_date = $timestamp ? gmdate('Y-m-d', $timestamp) : $last_membership_start_date;
		}
		if ($last_membership_start_date === '') {
			$last_membership_start_date = 'No Membership Found';
		}

		$vip_meta = get_user_meta((int) ($row['user_id'] ?? 0), 'vip', true);
		$vip = $this->normalize_boolean_meta($vip_meta);

		$attributes = [
			'User ID' => (string) ((int) ($row['user_id'] ?? 0)),
			'FirstName' => $first_name,
			'LastName' => $last_name,
		];

		if ($include_email) {
			$attributes['Email'] = sanitize_email((string) ($row['email'] ?? ''));
		}

		$attributes['Membership Expiration Date'] = $membership_expiration_date !== '' ? $membership_expiration_date : 'null';
		$attributes['Join Date'] = $join_date !== '' ? $join_date : 'null';
		$attributes['Active Member'] = $active_member ? 'true' : 'false';
		$attributes['Active Level Name'] = $active_level_name !== '' ? $active_level_name : 'null';
		$attributes['Active GRF'] = $active_grf ? 'true' : 'false';
		$attributes['Main Address'] = $main_address;
		$attributes['Last Membership Start Date'] = $last_membership_start_date;
		$attributes['VIP'] = $vip;

		return $attributes;
	}

	private function format_ripcord_single_member_text($row) {
		$attributes = $this->build_ripcord_member_attributes($row, true);
		$lines = [
			'Status: 200',
			'Content-Type: text/plain',
			'',
			'Ripcord User Lookup',
			'===================',
		];

		foreach ($attributes as $label => $value) {
			$lines[] = $label . ': ' . $value;
		}

		return implode("\n", $lines);
	}

	private function format_ripcord_multi_member_text($rows) {
		$lines = [
			'Status: 200',
			'Content-Type: text/plain',
			'',
			'Ripcord User Search',
			'===================',
			'Results: ' . count($rows),
			'',
		];

		foreach ($rows as $index => $row) {
			$attributes = $this->build_ripcord_member_attributes($row, false);
			$lines[] = 'Member ' . ($index + 1);
			$lines[] = str_repeat('-', 8);
			foreach ($attributes as $label => $value) {
				$lines[] = $label . ': ' . $value;
			}
			if ($index < count($rows) - 1) {
				$lines[] = '';
			}
		}

		return implode("\n", $lines);
	}

	private function normalize_boolean_param($value) {
		if (is_bool($value)) {
			return $value;
		}

		$normalized = strtolower(trim((string) $value));
		return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
	}

	private function looks_like_email($value) {
		return is_email($value) !== false;
	}

	private function normalize_phone($value) {
		return preg_replace('/\D+/', '', (string) $value);
	}

	private function normalize_name_part($value) {
		return strtolower(trim(preg_replace('/\s+/', ' ', (string) $value)));
	}

	private function normalize_boolean_meta($value) {
		if ($value === '' || $value === null) {
			return 'nil';
		}

		if (is_bool($value)) {
			return $value ? 'true' : 'false';
		}

		$normalized = strtolower(trim((string) $value));
		if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
			return 'true';
		}
		if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
			return 'false';
		}

		return 'nil';
	}

	private function send_text_response($body, $status = 200) {
		status_header((int) $status);
		nocache_headers();
		header('Content-Type: text/plain; charset=utf-8');
		echo (string) $body;
		exit;
	}
}
