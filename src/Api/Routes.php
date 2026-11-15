<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Api;

use Teme\Companion\Api\Endpoints;
use Teme\Companion\Report\Store;

/**
 * Registers the read-only export API under /wp-json/teme/v1/.
 */
final class Routes
{
    public const NS = 'teme/v1';

    /**
     * @return void
     */
    public static function register(Store $store): void
    {
        register_rest_route(self::NS, '/probe', [
            'methods' => 'GET',
            'callback' => [new Endpoints\Probe(), 'handle'],
            'permission_callback' => '__return_true',
        ]);

        $guarded = [
            'analyze' => new Endpoints\Analyze($store),
            'settings' => new Endpoints\Settings(),
            'users' => new Endpoints\Users(),
            'terms' => new Endpoints\Terms(),
            'media' => new Endpoints\Media(),
            'content' => new Endpoints\Content(),
            'comments' => new Endpoints\Comments(),
            'menus' => new Endpoints\Menus(),
            'redirects' => new Endpoints\Redirects(),
            'urls' => new Endpoints\Urls(),
        ];

        foreach ($guarded as $path => $endpoint) {
            register_rest_route(self::NS, '/' . $path, [
                'methods' => 'GET',
                'callback' => static function (\WP_REST_Request $request) use ($endpoint): \WP_REST_Response {
                    return Guard::noStore(new \WP_REST_Response($endpoint->handle($request), 200));
                },
                'permission_callback' => [Guard::class, 'check'],
            ]);
        }
    }
}
