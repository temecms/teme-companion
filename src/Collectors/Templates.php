<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Collectors;

use Teme\Companion\Report\Collector;

/**
 * Reusable blocks (synced patterns) and theme-owned block templates, parts, styles and custom CSS.
 */
final class Templates implements Collector
{
	/**
	 * @return string
	 */
	public function key(): string
	{
		return 'templates';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function collect(): array
	{
		global $wpdb;
		$count = static function (string $type) use ($wpdb): int {
			return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('trash','auto-draft')", $type));
		};

		$sync = [];
		foreach ((array) $wpdb->get_results("SELECT m.meta_value, COUNT(*) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'wp_pattern_sync_status' WHERE p.post_type = 'wp_block' AND p.post_status NOT IN ('trash','auto-draft') GROUP BY m.meta_value", ARRAY_N) as $row) {
			$sync[$row[0] === null || $row[0] === '' ? 'synced' : (string) $row[0]] = (int) $row[1];
		}

		return [
			'reusable_blocks' => $count('wp_block'),
			'reusable_sync' => $sync,
			'block_templates' => $count('wp_template'),
			'block_template_parts' => $count('wp_template_part'),
			'global_styles' => $count('wp_global_styles'),
			'custom_css' => $count('custom_css'),
		];
	}
}
