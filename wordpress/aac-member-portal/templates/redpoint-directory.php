<?php
if (!defined('ABSPATH')) {
	exit;
}

$search_value = esc_attr((string) ($args['search'] ?? ''));
$first_name_value = esc_attr((string) ($args['first_name'] ?? ''));
$last_name_value = esc_attr((string) ($args['last_name'] ?? ''));
$has_search = $search_value !== '' || $first_name_value !== '' || $last_name_value !== '';
$members = is_array($members ?? null) ? $members : [];
$result_count = count($members);

$format_redpoint_name = static function ($first_name, $last_name) {
	$name = trim(implode(' ', array_filter([(string) $first_name, (string) $last_name])));
	return $name !== '' ? $name : 'Not provided';
};

$format_redpoint_value = static function ($value, $fallback = 'Not provided') {
	$value = trim((string) $value);
	return $value !== '' ? $value : $fallback;
};

$format_redpoint_term = static function ($member) {
	$membership = is_array($member['membership'] ?? null) ? $member['membership'] : [];
	$start = trim((string) ($membership['start_date'] ?? ''));
	$end = trim((string) ($membership['end_date'] ?? ($membership['expiration_date'] ?? '')));
	if ($start === '' && $end === '') {
		return 'Not scheduled';
	}

	return sprintf('Start %s - End %s', $start !== '' ? $start : 'Not available', $end !== '' ? $end : 'Not available');
};
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Redpoint Member Lookup</title>
	<?php wp_head(); ?>
	<style>
		body {
			margin: 0;
			background: #f5f2eb;
			color: #111;
			font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
		}
		.aac-redpoint-shell {
			max-width: 980px;
			margin: 0 auto;
			padding: 40px 24px 56px;
		}
		.aac-redpoint-card,
		.aac-redpoint-toolbar,
		.aac-redpoint-result {
			background: #fff;
			border: 1px solid #ded7cb;
		}
		.aac-redpoint-card,
		.aac-redpoint-toolbar,
		.aac-redpoint-result {
			padding: 28px 32px;
			margin-bottom: 20px;
		}
		.aac-redpoint-eyebrow {
			margin: 0 0 10px;
			color: #b58a10;
			font-size: 13px;
			font-weight: 700;
			letter-spacing: 0.18em;
			text-transform: uppercase;
		}
		.aac-redpoint-title {
			margin: 0 0 10px;
			font-size: 54px;
			line-height: 0.95;
			font-weight: 800;
		}
		.aac-redpoint-copy {
			margin: 0;
			color: #4b4b4b;
			font-size: 18px;
			line-height: 1.5;
			max-width: 760px;
		}
		.aac-redpoint-field label {
			display: block;
			margin-bottom: 8px;
			font-size: 14px;
			font-weight: 700;
			color: #343434;
		}
		.aac-redpoint-search-row {
			display: grid;
			grid-template-columns: minmax(0, 1.2fr) minmax(0, 0.75fr) minmax(0, 0.75fr) auto auto;
			gap: 14px;
			align-items: end;
		}
		.aac-redpoint-input {
			width: 100%;
			height: 52px;
			padding: 0 14px;
			border: 1px solid #d8d0c2;
			background: #fff;
			font-size: 16px;
			box-sizing: border-box;
		}
		.aac-redpoint-button {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-height: 52px;
			padding: 0 20px;
			background: #bf1822;
			color: #fff;
			text-decoration: none;
			font-weight: 700;
			letter-spacing: 0.04em;
			text-transform: uppercase;
			border: 0;
			cursor: pointer;
		}
		.aac-redpoint-button--secondary {
			background: #fff;
			color: #111;
			border: 1px solid #d8d0c2;
		}
		.aac-redpoint-result-grid {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 18px;
		}
		.aac-redpoint-result-header {
			display: flex;
			align-items: baseline;
			justify-content: space-between;
			gap: 16px;
			margin-bottom: 18px;
			border-bottom: 2px solid #bf1822;
			padding-bottom: 14px;
		}
		.aac-redpoint-result-title {
			margin: 0;
			font-size: 24px;
			line-height: 1.2;
			font-weight: 800;
		}
		.aac-redpoint-result-count {
			margin: 0;
			color: #6b6256;
			font-size: 14px;
			font-weight: 700;
			letter-spacing: 0.08em;
			text-transform: uppercase;
		}
		.aac-redpoint-member-card {
			border-top: 1px solid #ded7cb;
			padding-top: 22px;
		}
		.aac-redpoint-member-card + .aac-redpoint-member-card {
			margin-top: 26px;
		}
		.aac-redpoint-member-heading {
			display: flex;
			align-items: baseline;
			justify-content: space-between;
			gap: 16px;
			margin-bottom: 16px;
		}
		.aac-redpoint-member-heading h2 {
			margin: 0;
			font-size: 30px;
			line-height: 1.1;
		}
		.aac-redpoint-member-id {
			color: #7a7469;
			font-size: 12px;
			font-weight: 800;
			letter-spacing: 0.12em;
			text-transform: uppercase;
		}
		.aac-redpoint-result-item {
			border: 1px solid #ece7df;
			padding: 18px 20px;
		}
		.aac-redpoint-result-label {
			display: block;
			margin-bottom: 8px;
			font-size: 12px;
			font-weight: 800;
			letter-spacing: 0.12em;
			text-transform: uppercase;
			color: #7a7469;
		}
		.aac-redpoint-result-value {
			font-size: 24px;
			line-height: 1.2;
			font-weight: 700;
			color: #111;
		}
		.aac-redpoint-result-value--compact {
			font-size: 18px;
			line-height: 1.35;
		}
		.aac-redpoint-section-title {
			margin: 24px 0 12px;
			color: #bf1822;
			font-size: 13px;
			font-weight: 800;
			letter-spacing: 0.14em;
			text-transform: uppercase;
		}
		.aac-redpoint-empty {
			color: #666;
			font-size: 16px;
			line-height: 1.5;
		}
		@media (max-width: 900px) {
			.aac-redpoint-title {
				font-size: 42px;
			}
			.aac-redpoint-search-row,
			.aac-redpoint-result-grid {
				grid-template-columns: 1fr;
			}
			.aac-redpoint-result-header,
			.aac-redpoint-member-heading {
				display: block;
			}
		}
	</style>
</head>
<body <?php body_class('aac-redpoint-directory-page'); ?>>
<?php wp_body_open(); ?>
<main class="aac-redpoint-shell">
	<section class="aac-redpoint-card">
		<p class="aac-redpoint-eyebrow">Member Lookup</p>
		<h1 class="aac-redpoint-title">Redpoint Member Lookup</h1>
		<p class="aac-redpoint-copy">Search by exact email address, phone number, or first and last name. Multiple matching member records can be shown.</p>
	</section>

	<section class="aac-redpoint-toolbar">
		<form method="get" action="<?php echo esc_url(home_url('/redpoint-lookup/')); ?>">
			<div class="aac-redpoint-search-row">
				<div class="aac-redpoint-field">
					<label for="aac-redpoint-search">Email, Phone, or Full Name</label>
					<input class="aac-redpoint-input" id="aac-redpoint-search" type="search" name="search" value="<?php echo $search_value; ?>" placeholder="Email, phone, or full name" autocomplete="off">
				</div>
				<div class="aac-redpoint-field">
					<label for="aac-redpoint-first-name">First Name</label>
					<input class="aac-redpoint-input" id="aac-redpoint-first-name" type="search" name="first_name" value="<?php echo $first_name_value; ?>" placeholder="First name" autocomplete="off">
				</div>
				<div class="aac-redpoint-field">
					<label for="aac-redpoint-last-name">Last Name</label>
					<input class="aac-redpoint-input" id="aac-redpoint-last-name" type="search" name="last_name" value="<?php echo $last_name_value; ?>" placeholder="Last name" autocomplete="off">
				</div>
				<button class="aac-redpoint-button" type="submit">Search</button>
				<a class="aac-redpoint-button aac-redpoint-button--secondary" href="<?php echo esc_url(home_url('/redpoint-lookup/')); ?>">Reset</a>
			</div>
		</form>
	</section>

	<?php if ($has_search) : ?>
		<section class="aac-redpoint-result">
			<?php if ($result_count > 0) : ?>
				<div class="aac-redpoint-result-header">
					<h2 class="aac-redpoint-result-title">Search Results</h2>
					<p class="aac-redpoint-result-count"><?php echo esc_html((string) $result_count); ?> <?php echo esc_html($result_count === 1 ? 'result' : 'results'); ?></p>
				</div>

				<?php foreach ($members as $member) : ?>
					<?php
					$contact = is_array($member['contact'] ?? null) ? $member['contact'] : [];
					$emergency = is_array($member['emergency_contact'] ?? null) ? $member['emergency_contact'] : [];
					$membership = is_array($member['membership'] ?? null) ? $member['membership'] : [];
					$emergency_name = $format_redpoint_name($emergency['first_name'] ?? '', $emergency['last_name'] ?? '');
					?>
					<article class="aac-redpoint-member-card">
						<header class="aac-redpoint-member-heading">
							<h2><?php echo esc_html($member['name'] ?: 'Unknown Member'); ?></h2>
							<span class="aac-redpoint-member-id">Member ID <?php echo esc_html($member['member_id'] ?: 'Not available'); ?></span>
						</header>
						<div class="aac-redpoint-result-grid">
							<div class="aac-redpoint-result-item">
								<span class="aac-redpoint-result-label">Membership Tier</span>
								<div class="aac-redpoint-result-value"><?php echo esc_html($membership['tier'] ?: 'Not available'); ?></div>
							</div>
							<div class="aac-redpoint-result-item">
								<span class="aac-redpoint-result-label">Status</span>
								<div class="aac-redpoint-result-value"><?php echo esc_html($membership['status'] ?: 'Unknown'); ?></div>
							</div>
							<div class="aac-redpoint-result-item">
								<span class="aac-redpoint-result-label">Subscription Term</span>
								<div class="aac-redpoint-result-value aac-redpoint-result-value--compact"><?php echo esc_html($format_redpoint_term($member)); ?></div>
							</div>
							<div class="aac-redpoint-result-item">
								<span class="aac-redpoint-result-label">Country</span>
								<div class="aac-redpoint-result-value"><?php echo esc_html($format_redpoint_value($contact['country'] ?? '')); ?></div>
							</div>
						</div>

						<h3 class="aac-redpoint-section-title">Emergency Contact</h3>
						<div class="aac-redpoint-result-grid">
							<div class="aac-redpoint-result-item">
								<span class="aac-redpoint-result-label">Name</span>
								<div class="aac-redpoint-result-value aac-redpoint-result-value--compact"><?php echo esc_html($emergency_name); ?></div>
							</div>
							<div class="aac-redpoint-result-item">
								<span class="aac-redpoint-result-label">Relationship</span>
								<div class="aac-redpoint-result-value aac-redpoint-result-value--compact"><?php echo esc_html($format_redpoint_value($emergency['relationship'] ?? '')); ?></div>
							</div>
							<div class="aac-redpoint-result-item">
								<span class="aac-redpoint-result-label">Phone</span>
								<div class="aac-redpoint-result-value aac-redpoint-result-value--compact"><?php echo esc_html($format_redpoint_value($emergency['phone'] ?? '')); ?></div>
							</div>
							<div class="aac-redpoint-result-item">
								<span class="aac-redpoint-result-label">Email</span>
								<div class="aac-redpoint-result-value aac-redpoint-result-value--compact"><?php echo esc_html($format_redpoint_value($emergency['email'] ?? '')); ?></div>
							</div>
						</div>
					</article>
				<?php endforeach; ?>
			<?php else : ?>
				<div class="aac-redpoint-empty">No exact match found for that email address, phone number, or first and last name.</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
