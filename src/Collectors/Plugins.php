<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Collectors;

use Teme\Companion\Report\Collector;

/**
 * Installed, active and must-use plugins with versions, and drop-ins such as object-cache.php.
 */
final class Plugins implements Collector
{
    /**
     * The section's key in the report.
     *
     * @return string
     */
    public function key(): string
    {
        return 'plugins';
    }

    /**
     * The section's data: counts, names and site settings only.
     *
     * @return array<mixed>
     */
    public function collect(): array
    {
        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $active = (array) get_option('active_plugins', []);
        $network = is_multisite() ? array_keys((array) get_site_option('active_sitewide_plugins', [])) : [];

        $installed = [];
        foreach (get_plugins() as $file => $data) {
            $installed[] = [
                'file' => $file,
                'name' => $data['Name'],
                'version' => $data['Version'],
                'plugin_uri' => $data['PluginURI'],
                'active' => in_array($file, $active, true) || in_array($file, $network, true),
            ];
        }

        $mu = [];
        foreach (get_mu_plugins() as $file => $data) {
            $mu[] = ['file' => $file, 'name' => $data['Name'], 'version' => $data['Version']];
        }

        return [
            'installed' => $installed,
            'active_count' => count(array_filter($installed, static fn (array $p): bool => $p['active'])),
            'must_use' => $mu,
            'dropins' => function_exists('get_dropins') ? array_keys(get_dropins()) : [],
        ];
    }
}
