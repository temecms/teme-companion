<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Api\Endpoints;

use Teme\Companion\Api\Envelope;
use Teme\Companion\Api\Paging;
use Teme\Companion\Support\Blocks;
use Teme\Companion\Support\SeoFields;

/**
 * Posts, pages and reusable blocks (other types when asked for) with everything Teme converts:
 * raw content, the block tree with classic chunks already paragraph-wrapped, the permalink and
 * old addresses, the active SEO plugin's fields, and optionally the page as rendered today.
 */
final class Content
{
    private const DEFAULT_TYPES = ['post', 'page', 'wp_block'];

    private const DEFAULT_STATUSES = ['publish', 'future', 'draft', 'pending', 'private'];

    /**
     * @return array<string,mixed>
     */
    public function handle(\WP_REST_Request $request): array
    {
        global $wpdb;
        $params = $request->get_params();
        $rendered = in_array('rendered', array_map('trim', explode(',', (string) ($params['include'] ?? ''))), true);
        $p = Paging::read($params, $rendered ? 20 : 50, $rendered ? 20 : 200);

        $types = $this->csv($params['types'] ?? '', self::DEFAULT_TYPES, static fn (string $t): bool => post_type_exists($t) && $t !== 'attachment' && $t !== 'revision');
        $statuses = $this->csv($params['statuses'] ?? '', self::DEFAULT_STATUSES, static fn (string $s): bool => in_array($s, self::DEFAULT_STATUSES, true));
        if ($types === [] || $statuses === []) {
            return Envelope::make([], $p['limit'], static fn (array $i): int => (int) $i['id']);
        }

        $typeIn = implode(',', array_fill(0, count($types), '%s'));
        $statusIn = implode(',', array_fill(0, count($statuses), '%s'));
        $sql = "SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_type IN ($typeIn) AND post_status IN ($statusIn)";
        $args = array_merge([$p['after_id']], $types, $statuses);
        if ($p['modified_after'] !== null) {
            $sql .= ' AND post_modified_gmt > %s';
            $args[] = $p['modified_after'];
        }
        $sql .= ' ORDER BY ID ASC LIMIT %d';
        $args[] = $p['limit'];
        $ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare($sql, $args)));

        _prime_post_caches($ids, true, true);

        $items = [];
        foreach ($ids as $id) {
            try {
                $post = get_post($id);
                if ($post instanceof \WP_Post) {
                    $items[] = $this->item($post, $rendered);
                } else {
                    $items[] = ['id' => $id, 'error' => 'RuntimeException: could not be loaded'];
                }
            } catch (\Throwable $e) {
                $items[] = ['id' => $id, 'error' => get_class($e) . ': ' . $e->getMessage()];
            }
        }

        return Envelope::make($items, $p['limit'], static fn (array $i): int => (int) $i['id']);
    }

    /**
     * @return array<string,mixed>
     */
    private function item(\WP_Post $post, bool $rendered): array
    {
        $id = (int) $post->ID;
        $meta = get_post_meta($id);
        $content = (string) $post->post_content;
        $blocks = function_exists('parse_blocks') ? parse_blocks($content) : [['blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => $content, 'innerContent' => [$content]]];

        $terms = [];
        foreach (get_object_taxonomies($post->post_type) as $taxonomy) {
            if ($taxonomy === 'post_format' || $taxonomy === 'language' || $taxonomy === 'post_translations') {
                continue;
            }
            $ids = wp_get_object_terms($id, $taxonomy, ['fields' => 'tt_ids']);
            if (is_array($ids) && $ids !== []) {
                $terms[$taxonomy] = array_map('intval', $ids);
            }
        }

        $item = [
            'id' => $id,
            'type' => $post->post_type,
            'status' => $post->post_status,
            'slug' => $post->post_name,
            'title' => $post->post_title,
            'date' => $post->post_date,
            'date_gmt' => $post->post_date_gmt,
            'modified_gmt' => $post->post_modified_gmt,
            'author' => (int) $post->post_author,
            'parent' => (int) $post->post_parent,
            'menu_order' => (int) $post->menu_order,
            'comment_status' => $post->comment_status,
            'ping_status' => $post->ping_status,
            'password' => $post->post_password,
            'sticky' => $post->post_type === 'post' && is_sticky($id),
            'format' => get_post_format($id) ?: null,
            'template' => get_page_template_slug($id) ?: null,
            'featured_media' => (int) get_post_thumbnail_id($id) ?: null,
            'terms' => $terms,
            'excerpt' => $post->post_excerpt,
            'content' => $content,
            'has_blocks' => function_exists('has_blocks') && has_blocks($content),
            'blocks' => Blocks::annotate($blocks, static fn (string $html): string => wpautop($html)),
            'permalink' => get_permalink($post),
            'old_slugs' => array_values(array_filter(array_map('strval', (array) ($meta['_wp_old_slug'] ?? [])))),
            'old_dates' => array_values(array_filter(array_map('strval', (array) ($meta['_wp_old_date'] ?? [])))),
            'seo' => $this->seo($post, $meta),
            'comment_count' => (int) $post->comment_count,
        ];

        if (function_exists('pll_get_post_language')) {
            $item['language'] = pll_get_post_language($id);
            $item['translations'] = function_exists('pll_get_post_translations') ? pll_get_post_translations($id) : [];
        }

        if ($rendered) {
            $item['rendered'] = $this->render($post);
        }

        return $item;
    }

    /**
     * @param array<string,list<mixed>> $meta
     * @return array<string,mixed>|null
     */
    private function seo(\WP_Post $post, array $meta): ?array
    {
        if (defined('WPSEO_VERSION')) {
            $resolve = function_exists('wpseo_replace_vars')
                ? static fn (string $t): string => (string) wpseo_replace_vars($t, $post)
                : static fn (string $t): string => $t;

            return SeoFields::fromYoast($meta, $resolve);
        }
        if (defined('RANK_MATH_VERSION')) {
            $resolve = class_exists('\RankMath\Helper') && method_exists('\RankMath\Helper', 'replace_vars')
                ? static fn (string $t): string => (string) \RankMath\Helper::replace_vars($t, $post)
                : static fn (string $t): string => $t;

            return SeoFields::fromRankMath($meta, $resolve);
        }

        return null;
    }

    /**
     * The content as a reader sees it today: the_content with this post set up as the current one.
     */
    private function render(\WP_Post $post): string
    {
        global $wp_query;
        $previous = $GLOBALS['post'] ?? null;
        ob_start();
        try {
            $GLOBALS['post'] = $post;
            setup_postdata($post);
            if ($wp_query instanceof \WP_Query) {
                $wp_query->in_the_loop = true;
            }

            return (string) apply_filters('the_content', $post->post_content);
        } finally {
            ob_end_clean();
            if ($wp_query instanceof \WP_Query) {
                $wp_query->in_the_loop = false;
            }
            $GLOBALS['post'] = $previous;
            wp_reset_postdata();
        }
    }

    /**
     * A comma-separated request value, filtered, or the defaults when none is given.
     *
     * @param mixed               $value
     * @param list<string>        $defaults
     * @param callable(string):bool $allowed
     * @return list<string>
     */
    private function csv($value, array $defaults, callable $allowed): array
    {
        $given = array_filter(array_map('trim', explode(',', is_string($value) ? $value : '')));
        $chosen = $given === [] ? $defaults : $given;

        return array_values(array_filter($chosen, $allowed));
    }
}
