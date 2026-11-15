<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion;

use Teme\Companion\Admin\Page;
use Teme\Companion\Cli\ReportCommand;
use Teme\Companion\Collectors;
use Teme\Companion\Report\Runner;
use Teme\Companion\Report\Store;
use Teme\Companion\Scan\ContentScan;

/**
 * Wires the admin page, the AJAX steps and the CLI command.
 */
final class Plugin
{
    /**
     * Builds the report runner and wires the admin page, AJAX actions and CLI command.
     *
     * @return void
     */
    public static function boot(): void
    {
        $store = new Store();
        $runner = new Runner($store, [
            new Collectors\Environment(),
            new Collectors\Plugins(),
            new Collectors\ContentTypes(),
            new Collectors\PostStates(),
            new Collectors\Media(),
            new Collectors\MetaKeys(),
            new Collectors\Options(),
            new Collectors\Tables(),
            new Collectors\Users(),
            new Collectors\Comments(),
            new Collectors\Seo(),
            new Collectors\Redirects(),
            new Collectors\Menus(),
            new Collectors\Widgets(),
            new Collectors\Templates(),
            new Collectors\Builders(),
            new Collectors\Multilingual(),
            new Collectors\Fields(),
            new Collectors\Cron(),
        ], new ContentScan());

        (new Page($store, $runner))->register();

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('teme report', new ReportCommand($store, $runner));
        }

        add_action('rest_api_init', static function () use ($store): void {
            \Teme\Companion\Api\Routes::register($store);
        });
    }
}
