<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Api;

/**
 * Builds the response every list endpoint returns: items, the next cursor, and the page size.
 */
final class Envelope
{
    public const API = 1;

    /**
     * The response for one page. A page shorter than the limit is the last one.
     *
     * @param list<array<string,mixed>> $items
     * @param callable(array<string,mixed>):int $idOf
     * @return array{api:int, items:list<array<string,mixed>>, next:?array{after_id:int}, limit:int}
     */
    public static function make(array $items, int $limit, callable $idOf): array
    {
        $next = null;
        if ($items !== [] && count($items) >= $limit) {
            $next = ['after_id' => $idOf($items[count($items) - 1])];
        }

        return ['api' => self::API, 'items' => $items, 'next' => $next, 'limit' => $limit];
    }
}
