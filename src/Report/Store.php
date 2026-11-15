<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Report;

/**
 * The run's state and the finished report, in two options that are never autoloaded.
 */
final class Store
{
    public const STATE = 'teme_companion_state';

    public const REPORT = 'teme_companion_report';

    /**
     * The saved run state, or an empty array.
     *
     * @return array<mixed>
     */
    public function state(): array
    {
        $value = get_option(self::STATE, []);

        return is_array($value) ? $value : [];
    }

    /**
     * @param array<mixed> $state
     */
    public function saveState(array $state): void
    {
        update_option(self::STATE, $state, false);
    }

    /**
     * The finished report, or null when there is none.
     *
     * @return array<mixed>|null
     */
    public function report(): ?array
    {
        $value = get_option(self::REPORT, null);

        return is_array($value) ? $value : null;
    }

    /**
     * @param array<mixed> $report
     */
    public function saveReport(array $report): void
    {
        update_option(self::REPORT, $report, false);
    }

    public function clear(): void
    {
        delete_option(self::STATE);
        delete_option(self::REPORT);
    }
}
