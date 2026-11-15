<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Support;

/**
 * Unserializes plugin data without ever creating an object, so a crafted value can't run code.
 */
final class Safe
{
    /**
     * The unserialized value (objects become __PHP_Incomplete_Class), or the original string
     * when it isn't serialized or is damaged.
     *
     * @return mixed
     */
    public static function unserialize(string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === 'b:0;') {
            return false;
        }
        if (! preg_match('/^(a|O|s|i|d|b|N):/', $trimmed)) {
            return $value;
        }

        $result = @unserialize($trimmed, ['allowed_classes' => false]);

        return $result === false ? $value : $result;
    }
}
