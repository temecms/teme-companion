<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Collectors;

use Teme\Companion\Report\Collector;

/**
 * Meta key names and how often each is used, for posts, terms, users and comments. Never values.
 */
final class MetaKeys implements Collector
{
    /**
     * The section's key in the report.
     *
     * @return string
     */
    public function key(): string
    {
        return 'meta_keys';
    }

    /**
     * The section's data: key names and counts for all four meta tables.
     *
     * @return array<string,array<mixed>>
     */
    public function collect(): array
    {
        global $wpdb;

        return [
            'post' => $this->keys($wpdb->postmeta, 400, true),
            'term' => $this->keys($wpdb->termmeta, 200, false),
            'user' => $this->keys($wpdb->usermeta, 200, false),
            'comment' => $this->keys($wpdb->commentmeta, 100, false),
        ];
    }

    /**
     * Get meta key names and counts for a table.
     *
     * @param string $table
     * @param int    $limit
     * @param bool   $serialized
     *
     * @return array<string,array{rows:int,serialized_arrays?:int,serialized_objects?:int}>
     */
    private function keys(string $table, int $limit, bool $serialized): array
    {
        global $wpdb;
        $select = $serialized
            ? "SELECT meta_key, COUNT(*) AS n_rows, SUM(meta_value LIKE 'a:%') AS n_arrays, SUM(meta_value LIKE 'O:%') AS n_objects"
            : 'SELECT meta_key, COUNT(*) AS n_rows';
        $rows = $wpdb->get_results("$select FROM $table GROUP BY meta_key ORDER BY n_rows DESC LIMIT " . (int) $limit, ARRAY_A);

        $out = [];
        foreach ((array) $rows as $row) {
            $key = str_replace($wpdb->prefix, '{prefix}', (string) $row['meta_key']);
            $entry = ['rows' => (int) $row['n_rows']];
            if ($serialized) {
                $entry['serialized_arrays'] = (int) $row['n_arrays'];
                $entry['serialized_objects'] = (int) $row['n_objects'];
            }
            $out[$key] = $entry;
        }

        return $out;
    }
}
