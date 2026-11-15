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
 * The media library: types, attachment, alt text, sizes, scaled and edited files, and where files live.
 */
final class Media implements Collector
{
    /**
     * The section's key in the report.
     *
     * @return string
     */
    public function key(): string
    {
        return 'media';
    }

    /**
     * The section's data: counts by type, attachment state, alt and caption coverage, and file details.
     *
     * @return array<mixed>
     */
    public function collect(): array
    {
        global $wpdb;
        $p = $wpdb->posts;
        $m = $wpdb->postmeta;

        $byMime = [];
        foreach ((array) $wpdb->get_results("SELECT post_mime_type, COUNT(*) FROM $p WHERE post_type = 'attachment' GROUP BY post_mime_type", ARRAY_N) as $row) {
            $byMime[(string) $row[0]] = (int) $row[1];
        }

        $sample = ['checked' => 0, 'with_metadata' => 0, 'scaled_original' => 0, 'sizes_per_image' => [], 'bytes_known' => 0, 'bytes_sum' => 0, 'extensions' => [], 'missing_files_checked' => 0, 'missing_files' => 0];
        $ids = $wpdb->get_col("SELECT ID FROM $p WHERE post_type = 'attachment' ORDER BY ID DESC LIMIT 2000");
        foreach ((array) $ids as $i => $id) {
            $id = (int) $id;
            $sample['checked']++;
            $file = (string) get_post_meta($id, '_wp_attached_file', true);
            Limits::bump($sample['extensions'], strtolower((string) pathinfo($file, PATHINFO_EXTENSION)), 50);

            $meta = wp_get_attachment_metadata($id);
            if (is_array($meta)) {
                $sample['with_metadata']++;
                if (! empty($meta['original_image'])) {
                    $sample['scaled_original']++;
                }
                Limits::bump($sample['sizes_per_image'], (string) count((array) ($meta['sizes'] ?? [])), 50);
                if (! empty($meta['filesize'])) {
                    $sample['bytes_known']++;
                    $sample['bytes_sum'] += (int) $meta['filesize'];
                }
            }

            if ($i < 200) {
                $sample['missing_files_checked']++;
                $path = get_attached_file($id);
                if (! is_string($path) || ! file_exists($path)) {
                    $sample['missing_files']++;
                }
            }
        }

        $uploads = wp_upload_dir(null, false);
        $homeHost = strtolower((string) parse_url(home_url(), PHP_URL_HOST));
        $uploadsHost = strtolower((string) parse_url((string) $uploads['baseurl'], PHP_URL_HOST));
        $jetpack = (array) get_option('jetpack_active_modules', []);

        return [
            'total' => array_sum($byMime),
            'by_mime' => $byMime,
            'unattached' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $p WHERE post_type = 'attachment' AND post_parent = 0"),
            'with_alt' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $m WHERE meta_key = '_wp_attachment_image_alt' AND meta_value <> ''"),
            'with_caption' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $p WHERE post_type = 'attachment' AND post_excerpt <> ''"),
            'with_description' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $p WHERE post_type = 'attachment' AND post_content <> ''"),
            'edited' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $m WHERE meta_key = '_wp_attachment_backup_sizes'"),
            'sample' => $sample,
            'uploads' => [
                'baseurl_path' => parse_url((string) $uploads['baseurl'], PHP_URL_PATH),
                'on_other_host' => $uploadsHost !== '' && $uploadsHost !== $homeHost,
                'uploads_host' => $uploadsHost,
                'year_month_folders' => (bool) get_option('uploads_use_yearmonth_folders'),
            ],
            'jetpack_photon' => in_array('photon', $jetpack, true) || in_array('photon-cdn', $jetpack, true),
        ];
    }
}
