<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Api\Endpoints;

use Teme\Companion\Api\Envelope;
use Teme\Companion\Api\Paging;

/**
 * Users, with roles and the stored password hash, which Teme verifies once at sign-in and then
 * upgrades (Teme I-119). Application passwords and two-factor secrets are never included.
 */
final class Users
{
    /**
     * @return array<string,mixed>
     */
    public function handle(\WP_REST_Request $request): array
    {
        global $wpdb;
        $p = Paging::read($request->get_params(), 200, 500);
        $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->users} WHERE ID > %d ORDER BY ID ASC LIMIT %d", $p['after_id'], $p['limit']));

        $items = [];
        foreach ((array) $ids as $id) {
            $id = (int) $id;
            try {
                $user = get_userdata($id);
                if (! $user instanceof \WP_User) {
                    $items[] = ['id' => $id, 'error' => 'RuntimeException: could not be loaded'];
                    continue;
                }
                $items[] = [
                    'id' => $id,
                    'login' => $user->user_login,
                    'email' => $user->user_email,
                    'nicename' => $user->user_nicename,
                    'display_name' => $user->display_name,
                    'first_name' => (string) get_user_meta($id, 'first_name', true),
                    'last_name' => (string) get_user_meta($id, 'last_name', true),
                    'description' => (string) get_user_meta($id, 'description', true),
                    'url' => $user->user_url,
                    'registered' => $user->user_registered,
                    'roles' => array_values($user->roles),
                    'password_hash' => $user->user_pass,
                    'published_posts' => (int) count_user_posts($id, 'post', true),
                ];
            } catch (\Throwable $e) {
                $items[] = ['id' => $id, 'error' => get_class($e) . ': ' . $e->getMessage()];
            }
        }

        return Envelope::make($items, $p['limit'], static fn (array $i): int => (int) $i['id']);
    }
}
