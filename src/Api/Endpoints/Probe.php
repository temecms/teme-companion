<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Api\Endpoints;

use Teme\Companion\Api\Envelope;

/**
 * The only public route: says this plugin is here and which API version it speaks, nothing more.
 */
final class Probe
{
    /**
     * @return array<string,mixed>
     */
    public function handle(\WP_REST_Request $request): array
    {
        return [
            'name' => 'teme-companion',
            'version' => defined('TEME_COMPANION_VERSION') ? TEME_COMPANION_VERSION : 'unknown',
            'api' => Envelope::API,
            'application_passwords' => function_exists('wp_is_application_passwords_available') && wp_is_application_passwords_available(),
        ];
    }
}
