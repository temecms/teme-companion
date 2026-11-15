<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Collectors;

use Teme\Companion\Report\Collector;

/**
 * Users by role, custom roles and their capabilities, and how passwords are hashed, as counts only.
 */
final class Users implements Collector
{
    private const CORE_ROLES = ['administrator', 'editor', 'author', 'contributor', 'subscriber'];

    /**
     * The section's key in the report.
     *
     * @return string
     */
    public function key(): string
    {
        return 'users';
    }

    /**
     * The section's data: user counts by role, custom roles and password hash formats as counts.
     *
     * @return array<mixed>
     */
    public function collect(): array
    {
        global $wpdb;
        $counts = count_users();

        $custom = [];
        foreach (wp_roles()->roles as $role => $data) {
            if (! in_array($role, self::CORE_ROLES, true)) {
                $custom[$role] = array_keys(array_filter((array) $data['capabilities']));
            }
        }

        $hashes = $wpdb->get_row(
            "SELECT
                SUM(user_pass LIKE '\$P\$%') AS phpass,
                SUM(user_pass LIKE '\$wp\$%') AS wp_bcrypt,
                SUM(user_pass LIKE '\$2y\$%') AS bcrypt,
                SUM(user_pass LIKE '\$argon2%') AS argon2,
                SUM(LENGTH(user_pass) = 32 AND user_pass NOT LIKE '\$%') AS md5,
                COUNT(*) AS total
             FROM {$wpdb->users}",
            ARRAY_A
        );

        return [
            'total' => (int) $counts['total_users'],
            'by_role' => $counts['avail_roles'],
            'custom_roles' => $custom,
            'password_hashes' => array_map('intval', (array) $hashes),
            'with_application_passwords' => (int) $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = '_application_passwords'"),
            'authors_with_published_posts' => (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_author) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type NOT IN ('attachment','revision','nav_menu_item')"),
        ];
    }
}
