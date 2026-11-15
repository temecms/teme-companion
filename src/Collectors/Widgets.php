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
 * Sidebars and the widget types in them, including block widgets, active or not.
 */
final class Widgets implements Collector
{
	/**
	 * @return string
	 */
	public function key(): string
	{
		return 'widgets';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function collect(): array
	{
		$sidebars = [];
		$types = [];
		foreach ((array) get_option('sidebars_widgets', []) as $sidebar => $widgets) {
			if ($sidebar === 'array_version' || ! is_array($widgets)) {
				continue;
			}
			$sidebars[(string) $sidebar] = count($widgets);
			foreach ($widgets as $widget) {
				$base = (string) preg_replace('/-\d+$/', '', (string) $widget);
				Limits::bump($types, ($sidebar === 'wp_inactive_widgets' ? 'inactive:' : '') . $base, 200);
			}
		}

		return ['sidebars' => $sidebars, 'widget_types' => $types, 'registered_sidebars' => array_keys($GLOBALS['wp_registered_sidebars'] ?? [])];
	}
}
