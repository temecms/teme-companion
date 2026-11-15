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
 * Attachments with everything Teme needs to download the original and trace every copy back to it:
 * metadata with every size, the pre-scaling original, edited copies, alt, caption and parent
 * (Teme study V §4.6).
 */
final class Media
{
    /**
     * @return array<string,mixed>
     */
    public function handle(\WP_REST_Request $request): array
    {
        global $wpdb;
        $p = Paging::read($request->get_params(), 200, 500);
        $sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND ID > %d";
        $args = [$p['after_id']];
        if ($p['modified_after'] !== null) {
            $sql .= ' AND post_modified_gmt > %s';
            $args[] = $p['modified_after'];
        }
        $sql .= ' ORDER BY ID ASC LIMIT %d';
        $args[] = $p['limit'];
        $ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare($sql, $args)));
        _prime_post_caches($ids, false, true);

        $items = [];
        foreach ($ids as $id) {
            try {
                $post = get_post($id);
                if (! $post instanceof \WP_Post) {
                    $items[] = ['id' => $id, 'error' => 'RuntimeException: could not be loaded'];
                    continue;
                }
                $meta = wp_get_attachment_metadata($id);
                $backup = get_post_meta($id, '_wp_attachment_backup_sizes', true);
                $items[] = [
                    'id' => $id,
                    'parent' => (int) $post->post_parent,
                    'author' => (int) $post->post_author,
                    'date_gmt' => $post->post_date_gmt,
                    'modified_gmt' => $post->post_modified_gmt,
                    'status' => $post->post_status,
                    'title' => $post->post_title,
                    'caption' => $post->post_excerpt,
                    'description' => $post->post_content,
                    'alt' => (string) get_post_meta($id, '_wp_attachment_image_alt', true),
                    'mime' => $post->post_mime_type,
                    'file' => (string) get_post_meta($id, '_wp_attached_file', true),
                    'url' => wp_get_attachment_url($id) ?: null,
                    'original_url' => function_exists('wp_get_original_image_url') ? (wp_get_original_image_url($id) ?: null) : null,
                    'attachment_page' => get_attachment_link($id),
                    'metadata' => is_array($meta) ? $this->metadata($meta) : null,
                    'backup_sizes' => is_array($backup) ? $backup : null,
                ];
            } catch (\Throwable $e) {
                $items[] = ['id' => $id, 'error' => get_class($e) . ': ' . $e->getMessage()];
            }
        }

        return Envelope::make($items, $p['limit'], static fn (array $i): int => (int) $i['id']);
    }

    /**
     * The parts of attachment metadata Teme uses; EXIF image_meta is left out.
     *
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private function metadata(array $meta): array
    {
        $sizes = [];
        foreach ((array) ($meta['sizes'] ?? []) as $name => $size) {
            if (is_array($size)) {
                $sizes[(string) $name] = [
                    'file' => (string) ($size['file'] ?? ''),
                    'width' => (int) ($size['width'] ?? 0),
                    'height' => (int) ($size['height'] ?? 0),
                    'mime' => (string) ($size['mime-type'] ?? ''),
                ];
            }
        }

        return [
            'width' => isset($meta['width']) ? (int) $meta['width'] : null,
            'height' => isset($meta['height']) ? (int) $meta['height'] : null,
            'file' => (string) ($meta['file'] ?? ''),
            'filesize' => isset($meta['filesize']) ? (int) $meta['filesize'] : null,
            'original_image' => isset($meta['original_image']) ? (string) $meta['original_image'] : null,
            'sizes' => $sizes,
        ];
    }
}
