<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Collectors;

/**
 * Small read-only query helpers shared by the collectors.
 */
final class Sql
{
	/**
	 * Whether a table with this full name exists.
	 *
	 * @return bool
	 */
	public static function tableExists(string $table): bool
	{
		global $wpdb;

		return (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
	}

	/**
	 * Row count of a table, or null when it doesn't exist.
	 *
	 * @return ?int
	 */
	public static function rows(string $table): ?int
	{
		global $wpdb;

		return self::tableExists($table) ? (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table`") : null;
	}

	/**
	 * For each post meta key, how many rows have a non-empty value.
	 *
	 * @param list<string> $keys
	 * @return array<string,int>
	 */
	public static function metaPresence(array $keys): array
	{
		global $wpdb;
		if ($keys === []) {
			return [];
		}
		$in = implode(',', array_fill(0, count($keys), '%s'));
		$rows = $wpdb->get_results($wpdb->prepare("SELECT meta_key, COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN ($in) AND meta_value <> '' GROUP BY meta_key", $keys), ARRAY_N);
		$out = array_fill_keys($keys, 0);
		foreach ((array) $rows as $row) {
			$out[(string) $row[0]] = (int) $row[1];
		}

		return $out;
	}

	/**
	 * How many posts carry a post meta key, optionally with a given value.
	 *
	 * @return int
	 */
	public static function postsWithMeta(string $key, ?string $value = null): int
	{
		global $wpdb;
		if ($value === null) {
			return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s", $key));
		}

		return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", $key, $value));
	}
}
