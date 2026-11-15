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
 * Every public address the site answers today, with what it points at, so Teme can prove each
 * one still answers after the move (Teme I-339).
 */
final class Urls
{
    /**
     * @return array<string,mixed>
     */
    public function handle(\WP_REST_Request $request): array
    {
        global $wpdb;
        $kind = (string) $request->get_param('kind');
        $p = Paging::read($request->get_params(), 1000, 2000);
        $items = [];

        switch ($kind) {
            case 'post':
                $types = array_values(array_filter(get_post_types(['public' => true]), static fn (string $t): bool => $t !== 'attachment'));
                $in = implode(',', array_fill(0, count($types), '%s'));
                $ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_status = 'publish' AND post_type IN ($in) ORDER BY ID ASC LIMIT %d", array_merge([$p['after_id']], $types, [$p['limit']]))));
                _prime_post_caches($ids, false, false);
                foreach ($ids as $id) {
                    $items[] = ['id' => $id, 'kind' => 'post', 'type' => get_post_type($id), 'url' => get_permalink($id)];
                }
                break;

            case 'term':
                $taxonomies = array_values(get_taxonomies(['public' => true]));
                $in = implode(',', array_fill(0, count($taxonomies), '%s'));
                $rows = $wpdb->get_results($wpdb->prepare("SELECT term_taxonomy_id, term_id, taxonomy FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id > %d AND taxonomy IN ($in) ORDER BY term_taxonomy_id ASC LIMIT %d", array_merge([$p['after_id']], $taxonomies, [$p['limit']])), ARRAY_A);
                foreach ((array) $rows as $row) {
                    $link = get_term_link((int) $row['term_id'], (string) $row['taxonomy']);
                    $items[] = ['id' => (int) $row['term_taxonomy_id'], 'kind' => 'term', 'taxonomy' => $row['taxonomy'], 'url' => is_string($link) ? $link : null];
                }
                break;

            case 'author':
                $ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare("SELECT DISTINCT post_author FROM {$wpdb->posts} WHERE post_author > %d AND post_status = 'publish' AND post_type = 'post' ORDER BY post_author ASC LIMIT %d", $p['after_id'], $p['limit'])));
                foreach ($ids as $id) {
                    $items[] = ['id' => $id, 'kind' => 'author', 'url' => get_author_posts_url($id)];
                }
                break;

            case 'attachment_page':
            case 'attachment_file':
                $ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_type = 'attachment' ORDER BY ID ASC LIMIT %d", $p['after_id'], $p['limit'])));
                _prime_post_caches($ids, false, true);
                foreach ($ids as $id) {
                    if ($kind === 'attachment_page') {
                        $items[] = ['id' => $id, 'kind' => 'attachment_page', 'url' => get_attachment_link($id)];
                        continue;
                    }
                    $url = wp_get_attachment_url($id);
                    $files = [];
                    if (is_string($url)) {
                        $files['full'] = $url;
                        $meta = wp_get_attachment_metadata($id);
                        foreach ((array) ($meta['sizes'] ?? []) as $name => $size) {
                            if (is_array($size) && ! empty($size['file'])) {
                                $files[(string) $name] = trailingslashit(dirname($url)) . $size['file'];
                            }
                        }
                        if (function_exists('wp_get_original_image_url') && ($original = wp_get_original_image_url($id))) {
                            $files['original'] = $original;
                        }
                    }
                    $items[] = ['id' => $id, 'kind' => 'attachment_file', 'files' => $files];
                }
                break;

            case 'archive':
                $items[] = ['id' => 0, 'kind' => 'home', 'url' => home_url('/')];
                foreach (get_post_types(['public' => true, 'has_archive' => true]) as $type) {
                    $link = get_post_type_archive_link($type);
                    if (is_string($link)) {
                        $items[] = ['id' => 0, 'kind' => 'archive', 'type' => $type, 'url' => $link];
                    }
                }

                return ['api' => Envelope::API, 'items' => $items, 'next' => null, 'limit' => count($items)];

            default:
                return ['api' => Envelope::API, 'kinds' => ['post', 'term', 'author', 'attachment_page', 'attachment_file', 'archive']];
        }

        return Envelope::make($items, $p['limit'], static fn (array $i): int => (int) $i['id']);
    }
}
