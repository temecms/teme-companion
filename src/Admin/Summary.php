<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Admin;

/**
 * The on-screen summary of a finished report: the figures to read before downloading it.
 */
final class Summary
{
    /**
     * Renders the report summary as HTML tables and sections.
     *
     * @param array<string,mixed> $report
     * @return void
     */
    public static function render(array $report): void
    {
        $s = $report['sections'] ?? [];
        $env = $s['environment'] ?? [];
        $types = $s['content_types']['post_types'] ?? [];
        $content = $s['content'] ?? [];

        $rows = [
            'Generated' => $report['generated_at'] ?? '',
            'WordPress / PHP / database' => ($env['wordpress'] ?? '?') . ' / ' . ($env['php'] ?? '?') . ' / ' . ($env['database'] ?? '?'),
            'Table prefix' => $env['table_prefix'] ?? '',
            'Permalink structure' => $env['options']['permalink_structure'] ?? '',
            'Theme' => ($env['theme']['name'] ?? '') . (! empty($env['theme']['block_theme']) ? ' (block theme)' : ''),
            'Published posts / pages' => (($types['post']['counts']['publish'] ?? 0)) . ' / ' . (($types['page']['counts']['publish'] ?? 0)),
            'Media items' => $s['media']['total'] ?? 0,
            'Comments' => $s['comments']['total'] ?? 0,
            'Users' => $s['users']['total'] ?? 0,
            'Posts scanned' => $content['posts_scanned'] ?? 0,
            'Post shapes (blocks / classic / mixed / empty)' => implode(' / ', array_map('strval', (array) ($content['blocks']['shapes'] ?? []))),
            'Scripts inside content' => $content['html']['scripts'] ?? 0,
            'Active plugins' => $s['plugins']['active_count'] ?? 0,
        ];

        echo '<table class="widefat striped" style="max-width: 900px"><tbody>';
        foreach ($rows as $label => $value) {
            echo '<tr><th scope="row">' . esc_html((string) $label) . '</th><td>' . esc_html((string) $value) . '</td></tr>';
        }
        echo '</tbody></table>';

        self::top('Most used blocks', $content['blocks']['blocks'] ?? [], 'count', 15);
        self::top('Shortcodes', $content['shortcodes']['tags'] ?? [], 'count', 30, true);

        if (! empty($report['errors'])) {
            echo '<h3>Sections that could not be read</h3><ul>';
            foreach ((array) $report['errors'] as $section => $message) {
                echo '<li><code>' . esc_html((string) $section) . '</code>: ' . esc_html((string) $message) . '</li>';
            }
            echo '</ul>';
        }
    }

    /**
     * Renders a table of the top N items.
     *
     * @param array<string,array<string,mixed>> $items
     * @return void
     */
    private static function top(string $title, array $items, string $field, int $n, bool $registered = false): void
    {
        if ($items === []) {
            return;
        }
        echo '<h3>' . esc_html($title) . '</h3><table class="widefat striped" style="max-width: 900px"><thead><tr><th>Name</th><th>Uses</th><th>Posts</th>' . ($registered ? '<th>Still registered</th>' : '') . '</tr></thead><tbody>';
        foreach (array_slice($items, 0, $n, true) as $name => $item) {
            echo '<tr><td><code>' . esc_html((string) $name) . '</code></td><td>' . esc_html((string) ($item[$field] ?? 0)) . '</td><td>' . esc_html((string) ($item['posts'] ?? 0)) . '</td>'
                . ($registered ? '<td>' . (! empty($item['registered']) ? 'yes' : '<strong>no</strong>') . '</td>' : '') . '</tr>';
        }
        echo '</tbody></table>';
    }
}
