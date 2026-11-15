<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Cli;

use Teme\Companion\Report\Runner;
use Teme\Companion\Report\Store;

/**
 * Writes the Teme site report to a file.
 */
final class ReportCommand
{
    private Store $store;

    private Runner $runner;

    public function __construct(Store $store, Runner $runner)
    {
        $this->store = $store;
        $this->runner = $runner;
    }

    /**
     * Scans the site and writes the report.
     *
     * ## OPTIONS
     *
     * [--output=<file>]
     * : Where to write the JSON. Defaults to teme-site-report.json in the current folder.
     *
     * [--[no-]examples]
     * : Include the markup examples. On by default; --no-examples leaves them out.
     *
     * @param list<string>              $args
     * @param array<string,string|bool> $assoc
     * @return void
     */
    public function __invoke(array $args, array $assoc): void
    {
        $this->runner->begin((bool) \WP_CLI\Utils\get_flag_value($assoc, 'examples', true));
        do {
            $step = $this->runner->step();
            \WP_CLI::log(sprintf('%3d%%  %s', (int) round($step['progress'] * 100), $step['section']));
        } while (! $step['done']);

        $file = $assoc['output'] ?? 'teme-site-report.json';
        file_put_contents($file, (string) wp_json_encode($this->store->report(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        \WP_CLI::success("Report written to $file");
    }
}
