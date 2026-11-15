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
 * Option names grouped by prefix, their sizes and autoload weight. Never values.
 */
final class Options implements Collector
{
    /**
     * The section's key in the report.
     *
     * @return string
     */
    public function key(): string
    {
        return 'options';
    }

    /**
     * The section's data: option names grouped by prefix, sizes and autoload weight.
     *
     * @return array<mixed>
     */
    public function collect(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT option_name, LENGTH(option_value) AS bytes, autoload FROM {$wpdb->options}", ARRAY_A);

        $prefixes = [];
        $sizes = [];
        $autoloadBytes = 0;
        $transients = 0;
        foreach ((array) $rows as $row) {
            $name = (string) $row['option_name'];
            if (strpos($name, '_transient_') === 0 || strpos($name, '_site_transient_') === 0) {
                $transients++;
                continue;
            }
            $prefix = (string) preg_replace('/[_-].*$/', '', ltrim($name, '_'));
            Limits::bump($prefixes, $prefix === '' ? '(none)' : $prefix, 400);
            $sizes[$name] = (int) $row['bytes'];
            if (in_array((string) $row['autoload'], ['yes', 'on', 'auto', 'auto-on'], true)) {
                $autoloadBytes += (int) $row['bytes'];
            }
        }

        return [
            'count' => count($sizes),
            'transients' => $transients,
            'autoload_bytes' => $autoloadBytes,
            'by_prefix' => Limits::top($prefixes, 400),
            'largest' => Limits::top($sizes, 30),
        ];
    }
}
