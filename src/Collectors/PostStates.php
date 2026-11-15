<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Collectors;

use Teme\Companion\Report\Collector;

/**
 * States a move must keep: passwords, schedules, formats, templates, featured images,
 * discussion flags, revisions, date ranges and page nesting.
 */
final class PostStates implements Collector
{
    /**
     * The section's key in the report.
     *
     * @return string
     */
    public function key(): string
    {
        return 'post_states';
    }

    /**
     * The section's data: counts, names and site settings only.
     *
     * @return array<mixed>
     */
    public function collect(): array
    {
        global $wpdb;
        $p = $wpdb->posts;
        $m = $wpdb->postmeta;

        $pairs = static function (string $sql) use ($wpdb): array {
            $out = [];
            foreach ((array) $wpdb->get_results($sql, ARRAY_N) as $row) {
                $out[(string) $row[0]] = (int) $row[1];
            }

            return $out;
        };

        return [
            'password_protected' => $pairs("SELECT post_type, COUNT(*) FROM $p WHERE post_password <> '' AND post_status NOT IN ('trash','auto-draft') GROUP BY post_type"),
            'scheduled' => $pairs("SELECT post_type, COUNT(*) FROM $p WHERE post_status = 'future' GROUP BY post_type"),
            'missed_schedule' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $p WHERE post_status = 'future' AND post_date_gmt < UTC_TIMESTAMP()"),
            'private' => $pairs("SELECT post_type, COUNT(*) FROM $p WHERE post_status = 'private' GROUP BY post_type"),
            'formats' => $pairs("SELECT t.slug, tt.count FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = 'post_format'"),
            'page_templates' => $pairs("SELECT meta_value, COUNT(*) FROM $m WHERE meta_key = '_wp_page_template' GROUP BY meta_value"),
            'featured_images' => $pairs("SELECT p.post_type, COUNT(*) FROM $m m JOIN $p p ON p.ID = m.post_id WHERE m.meta_key = '_thumbnail_id' GROUP BY p.post_type"),
            'comments_closed' => $pairs("SELECT post_type, COUNT(*) FROM $p WHERE comment_status = 'closed' AND post_status = 'publish' GROUP BY post_type"),
            'pings_open' => $pairs("SELECT post_type, COUNT(*) FROM $p WHERE ping_status = 'open' AND post_status = 'publish' GROUP BY post_type"),
            'revisions' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $p WHERE post_type = 'revision' AND post_name NOT LIKE '%autosave%'"),
            'autosaves' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $p WHERE post_type = 'revision' AND post_name LIKE '%autosave%'"),
            'trash' => $pairs("SELECT post_type, COUNT(*) FROM $p WHERE post_status = 'trash' GROUP BY post_type"),
            'published_range' => $wpdb->get_results("SELECT post_type, MIN(post_date) AS first, MAX(post_date) AS last FROM $p WHERE post_status = 'publish' GROUP BY post_type", ARRAY_A),
            'with_parent' => $pairs("SELECT post_type, COUNT(*) FROM $p WHERE post_parent > 0 AND post_type NOT IN ('revision','attachment','nav_menu_item') GROUP BY post_type"),
            'page_max_depth' => $this->pageDepth(),
            'slugs_percent_encoded' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $p WHERE post_name LIKE '%\\%%' AND post_status = 'publish'"),
            'duplicate_slugs' => (int) $wpdb->get_var("SELECT COUNT(*) FROM (SELECT post_name FROM $p WHERE post_status = 'publish' AND post_type IN ('post','page') AND post_name <> '' GROUP BY post_name HAVING COUNT(*) > 1) d"),
        ];
    }

    private function pageDepth(): int
    {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT ID, post_parent FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status NOT IN ('trash','auto-draft') LIMIT 50000", ARRAY_A);
        $parents = [];
        foreach ((array) $rows as $row) {
            $parents[(int) $row['ID']] = (int) $row['post_parent'];
        }
        $max = 0;
        foreach (array_keys($parents) as $id) {
            $depth = 1;
            $seen = [$id => true];
            while (($parents[$id] ?? 0) > 0 && isset($parents[$parents[$id]]) && ! isset($seen[$parents[$id]])) {
                $id = $parents[$id];
                $seen[$id] = true;
                $depth++;
            }
            $max = max($max, $depth);
        }

        return $max;
    }
}
