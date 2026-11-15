<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Collectors;

use Teme\Companion\Report\Collector;

/**
 * Every registered post type and taxonomy: how it's configured, its address shape, and counts.
 */
final class ContentTypes implements Collector
{
    /**
     * The section's key in the report.
     *
     * @return string
     */
    public function key(): string
    {
        return 'content_types';
    }

    /**
     * The section's data: counts, names and site settings only.
     *
     * @return array<mixed>
     */
    public function collect(): array
    {
        global $wpdb;

        $types = [];
        foreach (get_post_types([], 'objects') as $name => $type) {
            $types[$name] = [
                'label' => $type->label,
                'builtin' => (bool) $type->_builtin,
                'public' => (bool) $type->public,
                'publicly_queryable' => (bool) $type->publicly_queryable,
                'hierarchical' => (bool) $type->hierarchical,
                'has_archive' => $type->has_archive,
                'rewrite' => is_array($type->rewrite) ? ['slug' => $type->rewrite['slug'] ?? null, 'with_front' => $type->rewrite['with_front'] ?? null] : $type->rewrite,
                'show_in_rest' => (bool) $type->show_in_rest,
                'supports' => array_keys(get_all_post_type_supports($name)),
                'taxonomies' => get_object_taxonomies($name),
                'counts' => (array) wp_count_posts($name),
            ];
        }

        $taxonomies = [];
        foreach (get_taxonomies([], 'objects') as $name => $tax) {
            $entry = [
                'label' => $tax->label,
                'builtin' => (bool) $tax->_builtin,
                'public' => (bool) $tax->public,
                'hierarchical' => (bool) $tax->hierarchical,
                'object_type' => $tax->object_type,
                'rewrite' => is_array($tax->rewrite) ? ['slug' => $tax->rewrite['slug'] ?? null, 'with_front' => $tax->rewrite['with_front'] ?? null, 'hierarchical' => $tax->rewrite['hierarchical'] ?? null] : $tax->rewrite,
                'terms' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $name)),
                'empty_terms' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s AND count = 0", $name)),
            ];
            if ($tax->hierarchical) {
                $entry['max_depth'] = $this->depth($name);
            }
            $taxonomies[$name] = $entry;
        }

        return ['post_types' => $types, 'taxonomies' => $taxonomies];
    }

    private function depth(string $taxonomy): int
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT term_id, parent FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $taxonomy), ARRAY_A);
        $parents = [];
        foreach ((array) $rows as $row) {
            $parents[(int) $row['term_id']] = (int) $row['parent'];
        }

        $max = 0;
        foreach (array_keys($parents) as $id) {
            $depth = 1;
            $seen = [$id => true];
            while (($parents[$id] ?? 0) > 0 && ! isset($seen[$parents[$id]])) {
                $id = $parents[$id];
                $seen[$id] = true;
                $depth++;
            }
            $max = max($max, $depth);
        }

        return $max;
    }
}
