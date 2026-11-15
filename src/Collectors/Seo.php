<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Collectors;

use Teme\Companion\Report\Collector;

/**
 * Which SEO plugin holds the site's SEO data, how much of it exists, and its title templates.
 */
final class Seo implements Collector
{
	private const YOAST = ['_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_canonical', '_yoast_wpseo_meta-robots-noindex', '_yoast_wpseo_meta-robots-nofollow', '_yoast_wpseo_meta-robots-adv', '_yoast_wpseo_focuskw', '_yoast_wpseo_primary_category', '_yoast_wpseo_opengraph-title', '_yoast_wpseo_opengraph-description', '_yoast_wpseo_opengraph-image', '_yoast_wpseo_twitter-title', '_yoast_wpseo_twitter-description', '_yoast_wpseo_twitter-image', '_yoast_wpseo_bctitle', '_yoast_wpseo_is_cornerstone', '_yoast_wpseo_schema_page_type', '_yoast_wpseo_schema_article_type', '_yoast_wpseo_redirect'];

	private const RANK_MATH = ['rank_math_title', 'rank_math_description', 'rank_math_canonical_url', 'rank_math_robots', 'rank_math_advanced_robots', 'rank_math_focus_keyword', 'rank_math_primary_category', 'rank_math_facebook_title', 'rank_math_facebook_description', 'rank_math_facebook_image', 'rank_math_twitter_title', 'rank_math_twitter_description', 'rank_math_pillar_content', 'rank_math_breadcrumb_title'];

	/**
	 * @return string
	 */
	public function key(): string
	{
		return 'seo';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function collect(): array
	{
		global $wpdb;
		$m = $wpdb->postmeta;

		$templates = static function (string $option, string $pattern): array {
			$out = [];
			foreach ((array) get_option($option, []) as $key => $value) {
				if (is_string($key) && preg_match($pattern, $key) && is_scalar($value)) {
					$out[$key] = $value;
				}
			}

			return $out;
		};

		$yoastTermMeta = [];
		foreach ((array) get_option('wpseo_taxonomy_meta', []) as $taxonomy => $terms) {
			$yoastTermMeta[(string) $taxonomy] = is_array($terms) ? count($terms) : 0;
		}

		return [
			'plugins' => [
				'yoast' => defined('WPSEO_VERSION') ? WPSEO_VERSION : null,
				'yoast_premium' => defined('WPSEO_PREMIUM_VERSION') ? WPSEO_PREMIUM_VERSION : null,
				'rank_math' => defined('RANK_MATH_VERSION') ? RANK_MATH_VERSION : null,
				'aioseo' => defined('AIOSEO_VERSION') ? AIOSEO_VERSION : null,
				'seopress' => defined('SEOPRESS_VERSION') ? SEOPRESS_VERSION : null,
				'the_seo_framework' => defined('THE_SEO_FRAMEWORK_VERSION') ? THE_SEO_FRAMEWORK_VERSION : null,
			],
			'yoast_fields' => Sql::metaPresence(self::YOAST),
			'yoast_values_with_variables' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $m WHERE meta_key IN ('_yoast_wpseo_title','_yoast_wpseo_metadesc') AND meta_value LIKE '%\\%\\%%'"),
			'yoast_term_meta' => $yoastTermMeta,
			'yoast_templates' => $templates('wpseo_titles', '/^(title-|metadesc-|separator$|noindex-|breadcrumbs-|company_or_person$|stripcategorybase$|disable-)/'),
			'rank_math_fields' => Sql::metaPresence(self::RANK_MATH),
			'rank_math_schema_rows' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $m WHERE meta_key LIKE 'rank\\_math\\_schema\\_%'"),
			'rank_math_values_with_variables' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $m WHERE meta_key IN ('rank_math_title','rank_math_description') AND meta_value LIKE '%\\%%'"),
			'rank_math_templates' => $templates('rank-math-options-titles', '/(_title|_description|_robots|_custom_robots|title_separator|knowledgegraph_type)$/'),
			'rank_math_modules' => (array) get_option('rank_math_modules', []),
			'aioseo_posts_rows' => Sql::rows($wpdb->prefix . 'aioseo_posts'),
			'seopress_titles' => Sql::postsWithMeta('_seopress_titles_title'),
			'tsf_titles' => Sql::postsWithMeta('_genesis_title'),
		];
	}
}
