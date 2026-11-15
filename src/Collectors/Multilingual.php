<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Collectors;

use Teme\Companion\Report\Collector;

/**
 * Translation plugins and how many languages and translated items they hold.
 */
final class Multilingual implements Collector
{
	/**
	 * @return string
	 */
	public function key(): string
	{
		return 'multilingual';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function collect(): array
	{
		global $wpdb;

		return [
			'wpml' => defined('ICL_SITEPRESS_VERSION') ? ['version' => ICL_SITEPRESS_VERSION, 'translation_rows' => Sql::rows($wpdb->prefix . 'icl_translations')] : null,
			'polylang' => defined('POLYLANG_VERSION') ? ['version' => POLYLANG_VERSION, 'languages' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'language'")] : null,
			'translatepress' => defined('TRP_PLUGIN_VERSION') ? TRP_PLUGIN_VERSION : null,
			'weglot' => defined('WEGLOT_VERSION') ? WEGLOT_VERSION : null,
		];
	}
}
