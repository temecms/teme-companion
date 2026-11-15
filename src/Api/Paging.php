<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Api;

/**
 * Reads keyset paging parameters from a request, with limits enforced.
 */
final class Paging
{
    /**
     * The cursor, the page size, and an optional UTC time for catch-up runs.
     *
     * @param array<string,mixed> $params
     * @return array{after_id:int, limit:int, modified_after:?string}
     */
    public static function read(array $params, int $default, int $max): array
    {
        $after = isset($params['after_id']) ? (int) $params['after_id'] : 0;
        $limit = isset($params['limit']) ? (int) $params['limit'] : $default;

        $modified = null;
        if (isset($params['modified_after']) && is_string($params['modified_after'])
            && preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})Z?$/', $params['modified_after'], $m)) {
            $modified = $m[1] . ' ' . $m[2];
        }

        return [
            'after_id' => max(0, $after),
            'limit' => min($max, max(1, $limit)),
            'modified_after' => $modified,
        ];
    }
}
