<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Report;

/**
 * One section of the report. Collectors read; they never write.
 */
interface Collector
{
    /**
     * The section's key in the report.
     */
    public function key(): string;

    /**
     * The section's data: counts, names and site settings only.
     *
     * @return array<mixed>
     */
    public function collect(): array;
}
