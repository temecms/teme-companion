<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Collectors;

use Teme\Companion\Report\Collector;
use Teme\Companion\Report\Limits;

/**
 * Custom-field plugins, and for ACF the field groups and how many fields of each type.
 */
final class Fields implements Collector
{
	/**
	 * @return string
	 */
	public function key(): string
	{
		return 'fields';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function collect(): array
	{
		global $wpdb;

		$acfTypes = [];
		$rows = $wpdb->get_col("SELECT post_content FROM {$wpdb->posts} WHERE post_type = 'acf-field' LIMIT 20000");
		foreach ((array) $rows as $content) {
			$data = @unserialize((string) $content, ['allowed_classes' => false]);
			if (is_array($data) && isset($data['type'])) {
				Limits::bump($acfTypes, (string) $data['type'], 100);
			}
		}

		return [
			'acf' => defined('ACF_VERSION') ? ACF_VERSION : null,
			'acf_pro' => defined('ACF_PRO') ? (bool) ACF_PRO : null,
			'acf_field_groups' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'acf-field-group' AND post_status <> 'trash'"),
			'acf_fields' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'acf-field' AND post_status <> 'trash'"),
			'acf_field_types' => $acfTypes,
			'meta_box' => defined('RWMB_VER') ? RWMB_VER : null,
			'pods' => defined('PODS_VERSION') ? PODS_VERSION : null,
			'cmb2' => defined('CMB2_LOADED'),
			'carbon_fields' => class_exists('Carbon_Fields\\Carbon_Fields'),
			'jet_engine' => function_exists('jet_engine'),
		];
	}
}
