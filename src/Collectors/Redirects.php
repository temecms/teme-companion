<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Collectors;

use Teme\Companion\Report\Collector;

/**
 * Where the site keeps redirects (core's old slugs and the common plugins) and how many, with regex counts.
 */
final class Redirects implements Collector
{
	/**
	 * @return string
	 */
	public function key(): string
	{
		return 'redirects';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function collect(): array
	{
		global $wpdb;
		$prefix = $wpdb->prefix;

		$redirection = null;
		if (Sql::tableExists($prefix . 'redirection_items')) {
			$redirection = [
				'rows' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}redirection_items"),
				'regex' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}redirection_items WHERE regex = 1"),
				'by_action_code' => $wpdb->get_results("SELECT action_code, COUNT(*) AS n FROM {$prefix}redirection_items GROUP BY action_code", ARRAY_A),
				'log_rows' => Sql::rows($prefix . 'redirection_logs'),
				'not_found_rows' => Sql::rows($prefix . 'redirection_404'),
			];
		}

		$rankMath = null;
		if (Sql::tableExists($prefix . 'rank_math_redirections')) {
			$rankMath = [
				'rows' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}rank_math_redirections"),
				'by_status' => $wpdb->get_results("SELECT status, header_code, COUNT(*) AS n FROM {$prefix}rank_math_redirections GROUP BY status, header_code", ARRAY_A),
				'regex_sources' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}rank_math_redirections WHERE sources LIKE '%\"regex\"%'"),
				'not_found_log_rows' => Sql::rows($prefix . 'rank_math_404_logs'),
			];
		}

		$yoastPlain = get_option('wpseo-premium-redirects-base', null);
		$yoastRegex = get_option('wpseo-premium-redirects-regex', null);
		$simple301 = get_option('301_redirects', null);

		return [
			'old_slugs' => Sql::postsWithMeta('_wp_old_slug'),
			'old_slug_rows' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_wp_old_slug'"),
			'old_dates' => Sql::postsWithMeta('_wp_old_date'),
			'redirection_plugin' => $redirection,
			'rank_math' => $rankMath,
			'yoast_premium' => ['plain' => is_array($yoastPlain) ? count($yoastPlain) : null, 'regex' => is_array($yoastRegex) ? count($yoastRegex) : null],
			'safe_redirect_manager' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'redirect_rule'"),
			'simple_301_redirects' => is_array($simple301) ? count($simple301) : null,
			'yoast_per_post_redirect' => Sql::postsWithMeta('_yoast_wpseo_redirect'),
		];
	}
}
