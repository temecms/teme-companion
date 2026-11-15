<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Api\Endpoints;

use Teme\Companion\Api\Envelope;

/**
 * Classic menus as trees of items pointing at IDs, their theme locations, and block-theme
 * navigation posts with their block trees (Teme study V §4.11).
 */
final class Menus
{
    /**
     * @return array<string,mixed>
     */
    public function handle(\WP_REST_Request $request): array
    {
        $menus = [];
        foreach (wp_get_nav_menus() as $menu) {
            $items = [];
            foreach ((wp_get_nav_menu_items($menu->term_id, ['update_post_term_cache' => false]) ?: []) as $item) {
                $items[] = [
                    'id' => (int) $item->ID,
                    'parent' => (int) $item->menu_item_parent,
                    'order' => (int) $item->menu_order,
                    'type' => (string) $item->type,
                    'object' => (string) $item->object,
                    'object_id' => (int) $item->object_id,
                    'url' => (string) $item->url,
                    'label' => (string) $item->title,
                    'label_is_custom' => (string) get_post_field('post_title', (int) $item->ID, 'raw') !== '',
                    'target' => (string) $item->target,
                    'attr_title' => (string) $item->attr_title,
                    'classes' => array_values(array_filter((array) $item->classes)),
                    'xfn' => (string) $item->xfn,
                    'invalid' => ! empty($item->_invalid),
                ];
            }
            $menus[] = ['id' => (int) $menu->term_id, 'name' => $menu->name, 'slug' => $menu->slug, 'items' => $items];
        }

        $navigation = [];
        foreach (get_posts(['post_type' => 'wp_navigation', 'post_status' => ['publish', 'draft'], 'numberposts' => 50]) as $nav) {
            $navigation[] = [
                'id' => (int) $nav->ID,
                'title' => $nav->post_title,
                'status' => $nav->post_status,
                'blocks' => function_exists('parse_blocks') ? parse_blocks((string) $nav->post_content) : [],
            ];
        }

        return [
            'api' => Envelope::API,
            'menus' => $menus,
            'registered_locations' => get_registered_nav_menus(),
            'assigned_locations' => get_nav_menu_locations(),
            'navigation' => $navigation,
        ];
    }
}
