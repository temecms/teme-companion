<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Collectors;

use Teme\Companion\Report\Collector;

/**
 * Comments by status and type, how deep threads go, and which fields are filled, as counts only.
 */
final class Comments implements Collector
{
    /**
     * The section's key in the report.
     *
     * @return string
     */
    public function key(): string
    {
        return 'comments';
    }

    /**
     * The section's data: comment counts by status, type, thread depth and field presence.
     *
     * @return array<mixed>
     */
    public function collect(): array
    {
        global $wpdb;
        $c = $wpdb->comments;

        $byStatus = [];
        foreach ((array) $wpdb->get_results("SELECT comment_approved, comment_type, COUNT(*) FROM $c GROUP BY comment_approved, comment_type", ARRAY_N) as $row) {
            $byStatus[(string) $row[0] . '|' . ((string) $row[1] === '' ? '(empty)' : (string) $row[1])] = (int) $row[2];
        }

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $c");

        return [
            'total' => $total,
            'by_status_and_type' => $byStatus,
            'max_depth' => $total <= 500000 ? $this->depth() : null,
            'busiest_post_ids' => array_map('intval', (array) $wpdb->get_col("SELECT comment_post_ID FROM $c GROUP BY comment_post_ID ORDER BY COUNT(*) DESC LIMIT 5")),
            'busiest_count' => (int) $wpdb->get_var("SELECT COUNT(*) AS n FROM $c GROUP BY comment_post_ID ORDER BY n DESC LIMIT 1"),
            'first' => $wpdb->get_var("SELECT MIN(comment_date_gmt) FROM $c"),
            'last' => $wpdb->get_var("SELECT MAX(comment_date_gmt) FROM $c"),
            'by_registered_users' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $c WHERE user_id > 0"),
            'with_url' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $c WHERE comment_author_url <> ''"),
            'with_user_agent' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $c WHERE comment_agent <> ''"),
            'with_ip' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $c WHERE comment_author_IP <> ''"),
            'orphaned' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $c c LEFT JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID WHERE p.ID IS NULL"),
        ];
    }

    /**
     * Calculate the maximum thread depth in comments.
     *
     * @return int
     */
    private function depth(): int
    {
        global $wpdb;
        $parents = [];
        foreach ((array) $wpdb->get_results("SELECT comment_ID, comment_parent FROM {$wpdb->comments} WHERE comment_parent > 0", ARRAY_N) as $row) {
            $parents[(int) $row[0]] = (int) $row[1];
        }

        $max = $parents === [] ? 1 : 2;
        foreach (array_keys($parents) as $id) {
            $depth = 1;
            $seen = [$id => true];
            while (isset($parents[$id]) && ! isset($seen[$parents[$id]])) {
                $id = $parents[$id];
                $seen[$id] = true;
                $depth++;
            }
            $max = max($max, $depth);
        }

        return $max;
    }
}
