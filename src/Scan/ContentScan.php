<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Scan;

/**
 * Walks every post that holds content, 200 at a time by ID, through the block, shortcode and
 * HTML scanners. Its state is saved between steps, so no request runs long.
 */
final class ContentScan
{
    private const BATCH = 200;

    private const SKIPPED_TYPES = ['revision', 'attachment', 'nav_menu_item', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_global_styles', 'acf-field', 'acf-field-group', 'scheduled-action'];

    /**
     * @return array<string,mixed>
     */
    public function start(): array
    {
        global $wpdb;

        return [
            'last_id' => 0,
            'processed' => 0,
            'total' => (int) $wpdb->get_var($this->countSql()),
            'done' => false,
            'blocks' => [],
            'shortcodes' => [],
            'html' => [],
            'by_type' => [],
            'parser' => function_exists('parse_blocks') ? 'parse_blocks' : 'none',
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    public function step(array $state, bool $withExamples): array
    {
        global $wpdb;

        [$ownHosts, $scheme, $uploadsPath] = $this->site();
        $blocks = BlockCounter::fromState($state['blocks']);
        $shortcodes = ShortcodeScanner::fromState($state['shortcodes']);
        $html = HtmlScanner::fromState($state['html'], $ownHosts, $scheme, $uploadsPath);

        $placeholders = implode(',', array_fill(0, count(self::SKIPPED_TYPES), '%s'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_type, post_status, post_content, post_excerpt FROM {$wpdb->posts}
             WHERE ID > %d AND post_type NOT IN ($placeholders) AND post_status NOT IN ('auto-draft', 'trash')
             ORDER BY ID ASC LIMIT %d",
            array_merge([(int) $state['last_id']], self::SKIPPED_TYPES, [self::BATCH])
        ), ARRAY_A);

        foreach ((array) $rows as $row) {
            $id = (int) $row['ID'];
            $content = (string) $row['post_content'];
            $type = (string) $row['post_type'];

            $parsed = function_exists('parse_blocks')
                ? parse_blocks($content)
                : [['blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => $content, 'innerContent' => [$content]]];

            $blocks->addPost($id, $parsed, $withExamples);
            $shortcodes->addPost($id, $content, $withExamples);
            $html->addPost($content);

            $t = $state['by_type'][$type] ?? ['scanned' => 0, 'bytes' => 0, 'max_bytes' => 0, 'manual_excerpt' => 0, 'empty' => 0];
            $t['scanned']++;
            $t['bytes'] += strlen($content);
            $t['max_bytes'] = max($t['max_bytes'], strlen($content));
            $t['manual_excerpt'] += trim((string) $row['post_excerpt']) !== '' ? 1 : 0;
            $t['empty'] += trim($content) === '' ? 1 : 0;
            $state['by_type'][$type] = $t;

            $state['last_id'] = $id;
            $state['processed']++;
        }

        $state['blocks'] = $blocks->state();
        $state['shortcodes'] = $shortcodes->state();
        $state['html'] = $html->state();
        $state['done'] = count((array) $rows) < self::BATCH;

        return $state;
    }

    /**
     * The content section of the report.
     *
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    public function summary(array $state): array
    {
        [$ownHosts, $scheme, $uploadsPath] = $this->site();
        $shortcodes = ShortcodeScanner::fromState($state['shortcodes'])->summary();
        foreach ($shortcodes['tags'] as $name => $tag) {
            $shortcodes['tags'][$name]['registered'] = function_exists('shortcode_exists') && shortcode_exists((string) $name);
        }

        return [
            'parser' => $state['parser'],
            'posts_scanned' => $state['processed'],
            'skipped_types' => self::SKIPPED_TYPES,
            'by_type' => $state['by_type'],
            'blocks' => BlockCounter::fromState($state['blocks'])->summary(),
            'shortcodes' => $shortcodes,
            'html' => HtmlScanner::fromState($state['html'], $ownHosts, $scheme, $uploadsPath)->summary(),
            'own_hosts' => $ownHosts,
        ];
    }

    private function countSql(): string
    {
        global $wpdb;
        $placeholders = implode(',', array_fill(0, count(self::SKIPPED_TYPES), '%s'));

        return (string) $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type NOT IN ($placeholders) AND post_status NOT IN ('auto-draft', 'trash')",
            self::SKIPPED_TYPES
        );
    }

    /**
     * @return array{0:list<string>,1:string,2:string}
     */
    private function site(): array
    {
        $hosts = [];
        foreach ([home_url(), site_url()] as $url) {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if ($host !== '') {
                $hosts[] = (string) preg_replace('/^www\./', '', $host);
            }
        }
        $uploads = wp_upload_dir(null, false);
        $uploadsPath = (string) (parse_url((string) $uploads['baseurl'], PHP_URL_PATH) ?? '/wp-content/uploads');

        return [array_values(array_unique($hosts)), (string) parse_url(home_url(), PHP_URL_SCHEME), rtrim($uploadsPath, '/') . '/'];
    }
}
