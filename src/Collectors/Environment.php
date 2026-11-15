<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Collectors;

use Teme\Companion\Report\Collector;

/**
 * WordPress, PHP and database versions, the site's addresses, and the settings the importer depends on.
 */
final class Environment implements Collector
{
    private const OPTIONS = [
        'permalink_structure', 'category_base', 'tag_base', 'show_on_front', 'page_on_front', 'page_for_posts',
        'posts_per_page', 'posts_per_rss', 'rss_use_excerpt', 'blog_public', 'blog_charset', 'timezone_string', 'gmt_offset',
        'date_format', 'time_format', 'start_of_week', 'uploads_use_yearmonth_folders', 'upload_path', 'upload_url_path',
        'thumbnail_size_w', 'thumbnail_size_h', 'thumbnail_crop', 'medium_size_w', 'medium_size_h', 'medium_large_size_w', 'large_size_w', 'large_size_h',
        'default_comment_status', 'default_ping_status', 'default_pingback_flag', 'comment_registration', 'require_name_email',
        'close_comments_for_old_posts', 'close_comments_days_old', 'thread_comments', 'thread_comments_depth', 'page_comments',
        'comments_per_page', 'default_comments_page', 'comment_order', 'comment_moderation', 'comment_previously_approved',
        'comment_max_links', 'show_avatars', 'avatar_default', 'avatar_rating', 'comments_notify', 'moderation_notify',
        'show_comments_cookies_opt_in', 'use_smilies', 'use_trackback', 'default_category', 'default_post_format',
        'wp_page_for_privacy_policy', 'users_can_register', 'default_role', 'WPLANG', 'site_icon', 'db_version',
    ];

    /**
     * The section's key in the report.
     *
     * @return string
     */
    public function key(): string
    {
        return 'environment';
    }

    /**
     * The section's data: counts, names and site settings only.
     *
     * @return array<mixed>
     */
    public function collect(): array
    {
        global $wpdb, $wp_version;

        $options = [];
        foreach (self::OPTIONS as $name) {
            $value = get_option($name);
            $options[$name] = is_scalar($value) || $value === null ? $value : gettype($value);
        }

        $listLines = static function (string $name): int {
            $value = trim((string) get_option($name, ''));

            return $value === '' ? 0 : count(preg_split('/\r\n|\r|\n/', $value) ?: []);
        };

        $theme = wp_get_theme();
        $sizes = function_exists('wp_get_registered_image_subsizes') ? wp_get_registered_image_subsizes() : get_intermediate_image_sizes();

        return [
            'wordpress' => (string) $wp_version,
            'php' => PHP_VERSION,
            'database' => (string) $wpdb->get_var('SELECT VERSION()'),
            'table_prefix' => $wpdb->prefix,
            'base_prefix' => $wpdb->base_prefix,
            'charset' => $wpdb->charset,
            'collate' => $wpdb->collate,
            'multisite' => is_multisite(),
            'locale' => get_locale(),
            'home' => home_url(),
            'siteurl' => site_url(),
            'options' => $options,
            'moderation_keys_lines' => $listLines('moderation_keys'),
            'disallowed_keys_lines' => $listLines('disallowed_keys') ?: $listLines('blacklist_keys'),
            'sticky_posts' => count((array) get_option('sticky_posts', [])),
            'image_sizes' => $sizes,
            'big_image_size_threshold' => apply_filters('big_image_size_threshold', 2560, [0, 0], '', 0),
            'theme' => [
                'name' => $theme->get('Name'),
                'version' => $theme->get('Version'),
                'stylesheet' => $theme->get_stylesheet(),
                'template' => $theme->get_template(),
                'block_theme' => function_exists('wp_is_block_theme') ? wp_is_block_theme() : false,
                'installed' => array_keys(wp_get_themes()),
            ],
            'php_limits' => [
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
            ],
            'php_extensions' => array_values(array_filter(['imagick', 'gd', 'intl', 'mbstring', 'exif', 'zip', 'curl', 'fileinfo', 'sodium'], 'extension_loaded')),
            'object_cache' => wp_using_ext_object_cache(),
            'constants' => [
                'WP_DEBUG' => defined('WP_DEBUG') ? (bool) WP_DEBUG : null,
                'DISALLOW_FILE_EDIT' => defined('DISALLOW_FILE_EDIT') ? (bool) DISALLOW_FILE_EDIT : null,
                'DISALLOW_FILE_MODS' => defined('DISALLOW_FILE_MODS') ? (bool) DISALLOW_FILE_MODS : null,
                'WP_POST_REVISIONS' => defined('WP_POST_REVISIONS') ? WP_POST_REVISIONS : null,
                'EMPTY_TRASH_DAYS' => defined('EMPTY_TRASH_DAYS') ? EMPTY_TRASH_DAYS : null,
                'AUTOSAVE_INTERVAL' => defined('AUTOSAVE_INTERVAL') ? AUTOSAVE_INTERVAL : null,
                'DISABLE_WP_CRON' => defined('DISABLE_WP_CRON') ? (bool) DISABLE_WP_CRON : null,
                'WP_ENVIRONMENT_TYPE' => function_exists('wp_get_environment_type') ? wp_get_environment_type() : null,
            ],
        ];
    }
}
