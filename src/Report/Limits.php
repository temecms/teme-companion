<?php
/**
 * Caps that keep the report and its saved state small.
 *
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Report;

/**
 * Counting with a cap on distinct keys, trimming snippets, and top-N lists.
 */
final class Limits
{
    /**
     * Adds one to $counts[$key]. Once $cap distinct keys exist, new keys count under "(other)".
     *
     * @param array<string,int> $counts
     */
    public static function bump(array &$counts, string $key, int $cap = 500, int $by = 1): void
    {
        if (! isset($counts[$key]) && count($counts) >= $cap) {
            $key = '(other)';
        }
        $counts[$key] = ($counts[$key] ?? 0) + $by;
    }

    /**
     * The text cut to $length characters, with an ellipsis when it was longer.
     */
    public static function cut(string $text, int $length = 400): string
    {
        $size = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($size <= $length) {
            return $text;
        }

        return (function_exists('mb_substr') ? mb_substr($text, 0, $length) : substr($text, 0, $length)) . '…';
    }

    /**
     * The $n largest entries, largest first.
     *
     * @param array<string,int> $counts
     * @return array<string,int>
     */
    public static function top(array $counts, int $n): array
    {
        arsort($counts);

        return array_slice($counts, 0, $n, true);
    }
}
