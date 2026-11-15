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
 * Menus: how many, how many items of which kind, how deep, where they're placed, and block navigation.
 */
final class Menus implements Collector
{
	/**
	 * @return string
	 */
	public function key(): string
	{
		return 'menus';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function collect(): array
	{
		global $wpdb;

		$menus = [];
		foreach (wp_get_nav_menus() as $menu) {
			$items = wp_get_nav_menu_items($menu->term_id, ['update_post_term_cache' => false]) ?: [];
			$types = [];
			$parents = [];
			$invalid = 0;
			foreach ($items as $item) {
				Limits::bump($types, (string) $item->type . ':' . (string) $item->object, 100);
				$parents[(int) $item->ID] = (int) $item->menu_item_parent;
				if (! empty($item->_invalid)) {
					$invalid++;
				}
			}
			$depth = 0;
			foreach (array_keys($parents) as $id) {
				$d = 1;
				$seen = [$id => true];
				while (($parents[$id] ?? 0) > 0 && ! isset($seen[$parents[$id]])) {
					$id = $parents[$id];
					$seen[$id] = true;
					$d++;
				}
				$depth = max($depth, $d);
			}
			$menus[] = ['term_id' => (int) $menu->term_id, 'items' => count($items), 'item_types' => $types, 'max_depth' => $depth, 'invalid_items' => $invalid];
		}

		return [
			'menus' => $menus,
			'registered_locations' => array_keys(get_registered_nav_menus()),
			'assigned_locations' => get_nav_menu_locations(),
			'navigation_posts' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'wp_navigation' AND post_status = 'publish'"),
		];
	}
}
