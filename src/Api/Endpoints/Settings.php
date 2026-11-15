<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Api\Endpoints;

use Teme\Companion\Api\Envelope;

/**
 * The site settings Teme imports (Teme study V §4.10), by name. SEO templates are left out (I-353).
 */
final class Settings
{
    private const OPTIONS = [
        'blogname', 'blogdescription', 'timezone_string', 'gmt_offset', 'date_format', 'time_format', 'start_of_week',
        'posts_per_page', 'posts_per_rss', 'rss_use_excerpt', 'show_on_front', 'page_on_front', 'page_for_posts',
        'permalink_structure', 'category_base', 'tag_base', 'default_category', 'default_post_format', 'site_icon', 'blog_public',
        'default_comment_status', 'default_ping_status', 'comment_registration', 'require_name_email',
        'close_comments_for_old_posts', 'close_comments_days_old', 'thread_comments', 'thread_comments_depth',
        'page_comments', 'comments_per_page', 'comment_order', 'comment_moderation', 'comment_previously_approved',
        'comment_max_links', 'show_avatars', 'comments_notify', 'moderation_notify',
    ];

    /**
     * @return array<string,mixed>
     */
    public function handle(\WP_REST_Request $request): array
    {
        $options = [];
        foreach (self::OPTIONS as $name) {
            $value = get_option($name);
            $options[$name] = is_scalar($value) || $value === null ? $value : null;
        }

        return [
            'api' => Envelope::API,
            'home' => home_url(),
            'siteurl' => site_url(),
            'locale' => get_locale(),
            'options' => $options,
            'sticky_posts' => array_map('intval', (array) get_option('sticky_posts', [])),
            'menu_locations' => get_nav_menu_locations(),
            'seo_plugin' => defined('WPSEO_VERSION') ? 'yoast' : (defined('RANK_MATH_VERSION') ? 'rank_math' : null),
            'multilingual' => defined('POLYLANG_VERSION') ? 'polylang' : (defined('ICL_SITEPRESS_VERSION') ? 'wpml' : null),
        ];
    }
}
