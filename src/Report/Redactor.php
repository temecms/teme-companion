<?php
/**
 * Keeps personal data out of the report.
 *
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Report;

/**
 * Replaces email and IPv4 addresses in report strings, and finds keys that must never appear.
 */
final class Redactor
{
    private const EMAIL = '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/';

    private const IPV4 = '/\b(?:25[0-5]|2[0-4]\d|1?\d?\d)(?:\.(?:25[0-5]|2[0-4]\d|1?\d?\d)){3}\b/';

    private const FORBIDDEN_KEYS = [
        'user_email', 'user_pass', 'user_login', 'display_name', 'user_nicename',
        'comment_author', 'comment_author_email', 'comment_author_ip', 'comment_author_url',
        'comment_agent', 'comment_content', 'user_activation_key', 'session_tokens',
    ];

    /**
     * The text with email and IPv4 addresses replaced.
     */
    public static function text(string $text): string
    {
        $text = (string) preg_replace(self::EMAIL, '[email]', $text);

        return (string) preg_replace(self::IPV4, '[ip]', $text);
    }

    /**
     * The same tree with every string passed through text().
     *
     * @param array<mixed> $tree
     * @return array<mixed>
     */
    public static function tree(array $tree): array
    {
        foreach ($tree as $key => $value) {
            if (is_string($value)) {
                $tree[$key] = self::text($value);
            } elseif (is_array($value)) {
                $tree[$key] = self::tree($value);
            }
        }

        return $tree;
    }

    /**
     * Dotted paths of keys that name personal data. Empty means the tree is clean.
     *
     * @param array<mixed> $tree
     * @return list<string>
     */
    public static function forbiddenKeys(array $tree, string $path = ''): array
    {
        $found = [];
        foreach ($tree as $key => $value) {
            $here = $path === '' ? (string) $key : $path . '.' . $key;
            if (is_string($key) && in_array(strtolower($key), self::FORBIDDEN_KEYS, true)) {
                $found[] = $here;
            }
            if (is_array($value)) {
                $found = array_merge($found, self::forbiddenKeys($value, $here));
            }
        }

        return $found;
    }
}
