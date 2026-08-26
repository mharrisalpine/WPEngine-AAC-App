<?php
/**
 * Builds the frontend runtime settings passed to the React member portal.
 */

if (!defined('ABSPATH')) {
	exit;
}

class AAC_Member_Portal_Runtime_Config {
	private $portal_url;
	private $top_nav_registry;
	private $sidebar_registry;

	public function __construct($portal_url, $top_nav_registry, $sidebar_registry) {
		$this->portal_url = untrailingslashit((string) $portal_url);
		$this->top_nav_registry = is_array($top_nav_registry) ? $top_nav_registry : [];
		$this->sidebar_registry = is_array($sidebar_registry) ? $sidebar_registry : [];
	}

	public function get_portal_ui_settings($settings) {
		$settings = is_array($settings) ? $settings : [];
		$content = isset($settings['content']) && is_array($settings['content']) ? $settings['content'] : [];
		$design = isset($settings['design']) && is_array($settings['design']) ? $settings['design'] : [];
		$signup_hero_image_url = AAC_MEMBER_PORTAL_URL . 'app/assets/join-hero-static-image.jpg';

		$content_settings = array_merge(
			$content,
			[
				'discountCards' => array_values(isset($content['discount_cards']) && is_array($content['discount_cards']) ? $content['discount_cards'] : []),
				'contactIssueTypes' => array_values(isset($content['contact_issue_types']) && is_array($content['contact_issue_types']) ? $content['contact_issue_types'] : AAC_Member_Portal_Admin::get_default_contact_issue_types()),
				'benefitsGalleryItems' => $this->build_benefits_gallery_items($settings),
				'homeInvolvementCards' => array_values(isset($content['home_involvement_cards']) && is_array($content['home_involvement_cards']) ? $content['home_involvement_cards'] : []),
				'homePublicationCards' => array_values(isset($content['home_publication_cards']) && is_array($content['home_publication_cards']) ? $content['home_publication_cards'] : []),
				'homePartnerLogos' => array_values(isset($content['home_partner_logos']) && is_array($content['home_partner_logos']) ? $content['home_partner_logos'] : []),
				'memberProfileBlocks' => array_values(isset($content['member_profile_blocks']) && is_array($content['member_profile_blocks']) ? $content['member_profile_blocks'] : []),
				'memberProfileCardSections' => isset($content['member_profile_card_sections']) && is_array($content['member_profile_card_sections']) ? $content['member_profile_card_sections'] : [],
				'rescueLevels' => array_values(isset($content['rescue_levels']) && is_array($content['rescue_levels']) ? $content['rescue_levels'] : []),
				'membershipLevelBenefits' => $this->build_signup_level_benefits($settings),
				'signupBenefitsMatrixImageUrl' => esc_url_raw($content['signup_benefits_matrix_image_url'] ?? ''),
				'publicationViewUrls' => [
					'aaj' => $content['publication_view_url_aaj'] ?? '',
					'anac' => $content['publication_view_url_anac'] ?? '',
					'acj' => $content['publication_view_url_acj'] ?? '',
					'guidebook' => $content['publication_view_url_guidebook'] ?? '',
				],
			]
		);

		return [
			'content' => $content_settings,
			'design' => [
				'sidebarBackgroundUrl' => self::resolve_sidebar_background_url($settings),
				'sidebarOverlayStart' => $design['sidebar_overlay_start'] ?? '',
				'sidebarOverlayEnd' => $design['sidebar_overlay_end'] ?? '',
				'sidebarButtonBackground' => $design['sidebar_button_background'] ?? '',
				'sidebarButtonHoverBackground' => $design['sidebar_button_hover_background'] ?? '',
				'sidebarButtonActiveBackground' => $design['sidebar_button_active_background'] ?? '',
				'sidebarAccentColor' => $design['sidebar_accent_color'] ?? '',
				'primaryActionBackground' => $design['primary_action_background'] ?? '',
				'primaryActionText' => $design['primary_action_text'] ?? '',
				'secondaryActionBackground' => $design['secondary_action_background'] ?? '',
				'secondaryActionText' => $design['secondary_action_text'] ?? '',
				'pageBackground' => $design['page_background'] ?? '',
				'panelBackground' => $design['panel_background'] ?? '',
				'panelBorderColor' => $design['panel_border_color'] ?? '',
				'heroPanelBackground' => $design['hero_panel_background'] ?? '',
				'heroPanelBorderColor' => $design['hero_panel_border_color'] ?? '',
				'heroChipBackground' => $design['hero_chip_background'] ?? '',
				'heroChipBorderColor' => $design['hero_chip_border_color'] ?? '',
				'loginFormBackground' => $design['login_form_background'] ?? '',
				'loginOverlay' => $design['login_overlay'] ?? '',
				'homeHeroOverlay' => $design['home_hero_overlay'] ?? '',
				'homeHeroTintOverlay' => $design['home_hero_tint_overlay'] ?? '',
				'joinHeroOverlay' => $design['join_hero_overlay'] ?? '',
				'joinHeroTintOverlay' => $design['join_hero_tint_overlay'] ?? '',
				'navBackground' => $design['nav_background'] ?? '',
				'navTextColor' => $design['nav_text_color'] ?? '',
				'navHoverTextColor' => $design['nav_hover_text_color'] ?? '',
				'navIconColor' => $design['nav_icon_color'] ?? '',
				'navDropdownBackground' => $design['nav_dropdown_background'] ?? '',
				'navDropdownTextColor' => $design['nav_dropdown_text_color'] ?? '',
				'joinHeroImageUrl' => $signup_hero_image_url,
				'homeHeroVideoUrl' => $design['home_hero_video_url'] ?? '',
				'joinHeroVideoUrl' => '',
				'loginBackgroundImageUrl' => !empty($design['login_background_image_url']) ? $design['login_background_image_url'] : $signup_hero_image_url,
				'homeIntroImageUrl' => $design['home_intro_image_url'] ?? '',
				'homeIntroAccentImageUrl' => $design['home_intro_accent_image_url'] ?? '',
				'publicationTileImages' => [
					'aaj' => $design['publication_tile_image_aaj'] ?? '',
					'anac' => $design['publication_tile_image_anac'] ?? '',
					'acj' => $design['publication_tile_image_acj'] ?? '',
					'guidebook' => $design['publication_tile_image_guidebook'] ?? '',
				],
			],
			'navigation' => [
				'topNavSections' => $this->build_top_nav_sections($settings),
				'sidebarSections' => $this->build_sidebar_sections($settings),
			],
			'layout' => [
				'homeSections' => $this->build_home_sections($settings),
			],
		];
	}

	public static function resolve_sidebar_background_url($settings) {
		$custom_url = trim((string) ($settings['design']['sidebar_background_url'] ?? ''));
		if ($custom_url !== '') {
			return $custom_url;
		}

		return AAC_MEMBER_PORTAL_URL . 'app/sidebar-topo-v2.svg';
	}

	private function build_signup_level_benefits($settings) {
		$catalog = AAC_Member_Portal_Admin::get_signup_level_benefit_catalog();
		$levels = AAC_Member_Portal_Admin::get_signup_level_labels();
		$content = isset($settings['content']) && is_array($settings['content']) ? $settings['content'] : [];
		$selected = isset($content['signup_level_benefits']) && is_array($content['signup_level_benefits'])
			? $content['signup_level_benefits']
			: AAC_Member_Portal_Admin::get_default_signup_level_benefits();
		$rescue_levels = isset($content['rescue_levels']) && is_array($content['rescue_levels'])
			? $content['rescue_levels']
			: AAC_Member_Portal_Admin::get_default_rescue_levels();
		$rescue_levels_by_name = [];

		foreach ($rescue_levels as $rescue_level) {
			if (!is_array($rescue_level)) {
				continue;
			}
			$level_name = sanitize_text_field((string) ($rescue_level['level_name'] ?? ''));
			if ($level_name !== '') {
				$rescue_levels_by_name[strtolower($level_name)] = $rescue_level;
			}
		}

		$runtime = [];
		foreach ($levels as $level_id => $level_label) {
			$runtime[$level_id] = [];
			$rescue_level = $rescue_levels_by_name[strtolower($level_id)] ?? [];
			$selected_lookup = array_fill_keys(is_array($selected[$level_id] ?? null) ? $selected[$level_id] : [], true);
			foreach ($catalog as $benefit_key => $benefit_label) {
				if (empty($selected_lookup[$benefit_key])) {
					continue;
				}
				if ($benefit_key === 'rescue_coverage') {
					$benefit_label = $this->format_signup_benefit_amount_label($benefit_label, $rescue_level['rescue_amount'] ?? null);
				} elseif ($benefit_key === 'medical_expense_coverage') {
					$benefit_label = $this->format_signup_benefit_amount_label($benefit_label, $rescue_level['medical_amount'] ?? null);
				} elseif ($benefit_key === 'mortal_remains_transport') {
					$benefit_label = $this->format_signup_benefit_amount_label($benefit_label, $rescue_level['mortal_remains_amount'] ?? null);
				}
				$runtime[$level_id][] = $benefit_label;
			}
		}

		return $runtime;
	}

	private function build_benefits_gallery_items($settings) {
		$items = isset($settings['content']['benefits_gallery_items']) && is_array($settings['content']['benefits_gallery_items'])
			? array_values($settings['content']['benefits_gallery_items'])
			: [];

		if (!$items && method_exists('AAC_Member_Portal_Admin', 'get_default_benefits_gallery_items')) {
			$items = AAC_Member_Portal_Admin::get_default_benefits_gallery_items();
		}

		return array_values(array_map(
			static function ($item) {
				$item = is_array($item) ? $item : [];
				return [
					'id' => sanitize_key($item['id'] ?? ''),
					'title' => sanitize_text_field($item['title'] ?? ''),
					'imageUrl' => esc_url_raw($item['image_url'] ?? $item['imageUrl'] ?? ''),
					'url' => esc_url_raw($item['url'] ?? ''),
					'actionLabel' => sanitize_text_field($item['action_label'] ?? $item['actionLabel'] ?? ''),
					'description' => sanitize_textarea_field($item['description'] ?? ''),
				];
			},
			$items
		));
	}

	private function format_signup_benefit_amount_label($label, $amount) {
		if ($amount === null || $amount === '') {
			return $label;
		}

		$amount = max(0, (float) $amount);
		return sprintf('%s: $%s', $label, number_format($amount, 0));
	}

	private function build_home_sections($settings) {
		$components = isset($settings['components']) && is_array($settings['components']) ? $settings['components'] : [];
		$home_sections = isset($components['home_sections']) && is_array($components['home_sections']) ? $components['home_sections'] : [];
		$sections = [];

		foreach ($home_sections as $section_id => $section_settings) {
			if (empty($section_settings['visible'])) {
				continue;
			}
			$sections[] = [
				'id' => $section_id,
				'label' => $section_settings['label'],
				'order' => (int) ($section_settings['order'] ?? 0),
			];
		}

		usort($sections, static function ($left, $right) {
			return ($left['order'] ?? 0) <=> ($right['order'] ?? 0);
		});

		return $sections;
	}

	private function build_top_nav_sections($settings) {
		$components = isset($settings['components']) && is_array($settings['components']) ? $settings['components'] : [];
		$top_nav_items = isset($components['top_nav_items']) && is_array($components['top_nav_items']) ? $components['top_nav_items'] : [];
		$sections = [];

		foreach ($top_nav_items as $item_id => $item_settings) {
			if ($item_id === 'get_involved') {
				continue;
			}
			if (empty($item_settings['visible']) || empty($this->top_nav_registry[$item_id])) {
				continue;
			}

			$section = $this->top_nav_registry[$item_id];
			$children = isset($item_settings['children']) && is_array($item_settings['children']) && !empty($item_settings['children'])
				? array_values($item_settings['children'])
				: $section['children'];
			if ($item_id === 'membership') {
				$children = $this->ensure_membership_sign_in_nav_child($children);
			}

			$sections[] = [
				'id' => $item_id,
				'label' => $item_settings['label'],
				'href' => $section['href'],
				'children' => $children,
				'order' => (int) $item_settings['order'],
			];
		}

		usort($sections, static function ($left, $right) {
			return ($left['order'] ?? 0) <=> ($right['order'] ?? 0);
		});

		return $sections;
	}

	private function build_sidebar_sections($settings) {
		$components = isset($settings['components']) && is_array($settings['components']) ? $settings['components'] : [];
		$section_titles = isset($components['section_titles']) && is_array($components['section_titles']) ? $components['section_titles'] : [];
		$sidebar_items = isset($components['sidebar_items']) && is_array($components['sidebar_items']) ? $components['sidebar_items'] : [];
		$sections = [];

		foreach ($section_titles as $section_id => $section_title) {
			$sections[$section_id] = [
				'id' => $section_id,
				'title' => $section_title,
				'items' => [],
			];
		}

		foreach ($sidebar_items as $item_id => $item_settings) {
			if (empty($item_settings['visible']) || empty($this->sidebar_registry[$item_id])) {
				continue;
			}
			$section_id = $item_settings['section'];
			if (!isset($sections[$section_id])) {
				continue;
			}

			$item = [
				'id' => $item_id,
				'label' => $item_settings['label'],
				'icon' => $this->sidebar_registry[$item_id]['icon'],
				'order' => (int) $item_settings['order'],
			];
			if (!empty($this->sidebar_registry[$item_id]['href'])) {
				$item['href'] = $this->sidebar_registry[$item_id]['href'];
			} else {
				$item['to'] = $this->sidebar_registry[$item_id]['route'];
			}
			$sections[$section_id]['items'][] = $item;
		}

		foreach ($sections as &$section) {
			usort($section['items'], static function ($left, $right) {
				return ($left['order'] ?? 0) <=> ($right['order'] ?? 0);
			});
		}
		unset($section);

		return array_values(array_filter($sections, static function ($section) {
			return !empty($section['items']);
		}));
	}

	private function ensure_membership_sign_in_nav_child($children) {
		$children = is_array($children) ? array_values($children) : [];
		foreach ($children as $child) {
			$label = isset($child['label']) ? strtolower(trim((string) $child['label'])) : '';
			if (in_array($label, ['sign in', 'login', 'log in'], true)) {
				return $children;
			}
		}

		$sign_in_child = ['label' => 'Sign In', 'href' => $this->portal_url . '#/login'];
		$insert_after = null;
		foreach ($children as $index => $child) {
			$label = isset($child['label']) ? strtolower(trim((string) $child['label'])) : '';
			if ($label === 'join') {
				$insert_after = $index;
				break;
			}
		}

		if ($insert_after === null) {
			$children[] = $sign_in_child;
			return $children;
		}

		array_splice($children, $insert_after + 1, 0, [$sign_in_child]);
		return $children;
	}
}
