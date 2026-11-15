<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Api;

/**
 * Decides who may read the export API: an administrator, over HTTPS, and — on multisite — a
 * network super administrator (Teme study V §4.3).
 */
final class Guard
{
    /**
     * True when the request may proceed, or an error saying why not.
     *
     * @return true|\WP_Error
     */
    public static function check()
    {
        if (! current_user_can('manage_options')) {
            return new \WP_Error('teme_forbidden', 'An administrator account is required.', ['status' => is_user_logged_in() ? 403 : 401]);
        }
        if (is_multisite() && ! is_super_admin()) {
            return new \WP_Error('teme_forbidden', 'A network super administrator account is required on multisite.', ['status' => 403]);
        }
        if (! self::secure()) {
            return new \WP_Error('teme_https_required', 'The export API only answers over HTTPS.', ['status' => 403]);
        }

        return true;
    }

    /**
     * Whether this request is allowed without HTTPS: local or development sites, or an explicit override.
     *
     * @return bool
     */
    public static function secure(): bool
    {
        if (is_ssl()) {
            return true;
        }
        $env = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
        if (in_array($env, ['local', 'development'], true)) {
            return true;
        }

        return defined('TEME_COMPANION_ALLOW_HTTP') && TEME_COMPANION_ALLOW_HTTP === true;
    }

    /**
     * Stops any cache between the site and the importer from keeping a response.
     *
     * @return \WP_REST_Response
     */
    public static function noStore(\WP_REST_Response $response): \WP_REST_Response
    {
        $response->header('Cache-Control', 'no-store, private');

        return $response;
    }
}
