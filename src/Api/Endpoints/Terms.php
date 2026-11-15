<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Api\Endpoints;

use Teme\Companion\Api\Envelope;
use Teme\Companion\Api\Paging;

/**
 * Terms of every content taxonomy, by term_taxonomy_id: name, slug, parent, public link, the active
 * SEO plugin's fields, and translations when Polylang is active.
 */
final class Terms
{
    private const INTERNAL = ['nav_menu', 'wp_theme', 'wp_template_part_area', 'wp_pattern_category', 'link_category', 'language', 'term_language', 'post_translations', 'term_translations'];

    /**
     * @return array<string,mixed>
     */
    public function handle(\WP_REST_Request $request): array
    {
        global $wpdb;
        $p = Paging::read($request->get_params(), 500, 1000);
        $in = implode(',', array_fill(0, count(self::INTERNAL), '%s'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT tt.term_taxonomy_id, tt.term_id, tt.taxonomy, tt.description, tt.parent, tt.count, t.name, t.slug
             FROM {$wpdb->term_taxonomy} tt JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
             WHERE tt.term_taxonomy_id > %d AND tt.taxonomy NOT IN ($in)
             ORDER BY tt.term_taxonomy_id ASC LIMIT %d",
            array_merge([$p['after_id']], self::INTERNAL, [$p['limit']])
        ), ARRAY_A);

        $yoast = defined('WPSEO_VERSION') ? (array) get_option('wpseo_taxonomy_meta', []) : [];

        $items = [];
        foreach ((array) $rows as $row) {
            $ttid = (int) $row['term_taxonomy_id'];
            try {
                $termId = (int) $row['term_id'];
                $taxonomy = (string) $row['taxonomy'];
                $viewable = function_exists('is_taxonomy_viewable') ? is_taxonomy_viewable($taxonomy) : (bool) get_taxonomy($taxonomy)->public;
                $link = taxonomy_exists($taxonomy) && $viewable ? get_term_link($termId, $taxonomy) : null;

                $item = [
                    'id' => $ttid,
                    'term_id' => $termId,
                    'taxonomy' => $taxonomy,
                    'name' => $row['name'],
                    'slug' => $row['slug'],
                    'description' => $row['description'],
                    'parent' => (int) $row['parent'],
                    'count' => (int) $row['count'],
                    'link' => is_string($link) ? $link : null,
                    'seo' => $this->seo($termId, $taxonomy, $yoast),
                ];
                if (function_exists('pll_get_term_language')) {
                    $item['language'] = pll_get_term_language($termId);
                    $item['translations'] = function_exists('pll_get_term_translations') ? pll_get_term_translations($termId) : [];
                }
                $items[] = $item;
            } catch (\Throwable $e) {
                $items[] = ['id' => $ttid, 'error' => get_class($e) . ': ' . $e->getMessage()];
            }
        }

        return Envelope::make($items, $p['limit'], static fn (array $i): int => (int) $i['id']);
    }

    /**
     * @param array<mixed> $yoast
     * @return array<string,mixed>|null
     */
    private function seo(int $termId, string $taxonomy, array $yoast): ?array
    {
        if (defined('WPSEO_VERSION')) {
            $m = (array) ($yoast[$taxonomy][$termId] ?? []);
            if ($m === []) {
                return null;
            }
            $noindex = (string) ($m['wpseo_noindex'] ?? 'default');

            return [
                'title' => ($m['wpseo_title'] ?? '') !== '' ? (string) $m['wpseo_title'] : null,
                'description' => ($m['wpseo_desc'] ?? '') !== '' ? (string) $m['wpseo_desc'] : null,
                'canonical' => ($m['wpseo_canonical'] ?? '') !== '' ? (string) $m['wpseo_canonical'] : null,
                'noindex' => $noindex === 'noindex' || $noindex === 'on' ? true : ($noindex === 'index' ? false : null),
            ];
        }
        if (defined('RANK_MATH_VERSION')) {
            $robots = get_term_meta($termId, 'rank_math_robots', true);
            $title = (string) get_term_meta($termId, 'rank_math_title', true);
            $description = (string) get_term_meta($termId, 'rank_math_description', true);
            $canonical = (string) get_term_meta($termId, 'rank_math_canonical_url', true);

            return [
                'title' => $title !== '' ? $title : null,
                'description' => $description !== '' ? $description : null,
                'canonical' => $canonical !== '' ? $canonical : null,
                'noindex' => is_array($robots) ? in_array('noindex', $robots, true) : null,
            ];
        }

        return null;
    }
}
