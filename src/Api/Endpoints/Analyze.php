<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Api\Endpoints;

use Teme\Companion\Api\Envelope;
use Teme\Companion\Report\Store;

/**
 * Returns part 1's latest site report, so the importer can plan before it imports.
 */
final class Analyze
{
    private Store $store;

    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    /**
     * @return array<string,mixed>
     */
    public function handle(\WP_REST_Request $request): array
    {
        $report = $this->store->report();

        return $report === null
            ? ['api' => Envelope::API, 'report' => null, 'message' => 'No report yet. Run Tools › Teme site report first.']
            : ['api' => Envelope::API, 'report' => $report];
    }
}
