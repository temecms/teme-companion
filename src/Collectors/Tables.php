<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Collectors;

use Teme\Companion\Report\Collector;

/**
 * Database tables with the site's prefix: rows, sizes, engine and collation, core or plugin.
 */
final class Tables implements Collector
{
    /**
     * The section's key in the report.
     *
     * @return string
     */
    public function key(): string
    {
        return 'tables';
    }

    /**
     * The section's data: all tables with the site's prefix with sizes and engine info.
     *
     * @return array<mixed>
     */
    public function collect(): array
    {
        global $wpdb;
        $core = array_values($wpdb->tables('all', true));
        $rows = $wpdb->get_results($wpdb->prepare('SHOW TABLE STATUS LIKE %s', $wpdb->esc_like($wpdb->prefix) . '%'), ARRAY_A);

        $tables = [];
        $total = 0;
        foreach ((array) $rows as $row) {
            $name = (string) $row['Name'];
            $bytes = (int) $row['Data_length'] + (int) $row['Index_length'];
            $total += $bytes;
            $tables[str_replace($wpdb->prefix, '{prefix}', $name)] = [
                'core' => in_array($name, $core, true),
                'rows_estimate' => (int) $row['Rows'],
                'bytes' => $bytes,
                'engine' => $row['Engine'],
                'collation' => $row['Collation'],
            ];
        }

        return ['total_bytes' => $total, 'tables' => $tables];
    }
}
