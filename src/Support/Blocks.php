<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Support;

/**
 * Adds each classic (freeform) chunk's paragraph-wrapped HTML to a parsed block tree, so Teme
 * never has to reimplement WordPress's wpautop rules (Teme study V §4.4).
 */
final class Blocks
{
    /**
     * The same tree, where every freeform chunk with visible content gains an `autop` key.
     *
     * @param list<array<string,mixed>> $blocks
     * @param callable(string):string   $autop
     * @return list<array<string,mixed>>
     */
    public static function annotate(array $blocks, callable $autop): array
    {
        foreach ($blocks as $i => $block) {
            if (($block['blockName'] ?? null) === null) {
                $html = (string) ($block['innerHTML'] ?? '');
                if (trim($html) !== '') {
                    $blocks[$i]['autop'] = $autop($html);
                }
                continue;
            }
            if (! empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                $blocks[$i]['innerBlocks'] = self::annotate($block['innerBlocks'], $autop);
            }
        }

        return $blocks;
    }
}
