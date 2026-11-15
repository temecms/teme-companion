<?php

declare(strict_types=1);

namespace Teme\Companion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Teme\Companion\Support\Blocks;

final class BlocksTest extends TestCase
{
    public function test_freeform_chunks_gain_autop_html_at_any_depth_and_blocks_are_untouched(): void
    {
        $autop = static fn (string $html): string => '<p>' . trim($html) . '</p>';
        $tree = [
            ['blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => "Classic text", 'innerContent' => ['Classic text']],
            ['blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => "\n\n", 'innerContent' => ["\n\n"]],
            ['blockName' => 'core/group', 'attrs' => [], 'innerHTML' => '', 'innerContent' => [], 'innerBlocks' => [
                ['blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => 'Nested', 'innerContent' => ['Nested']],
            ]],
            ['blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<p>Hi</p>', 'innerContent' => ['<p>Hi</p>']],
        ];

        $out = Blocks::annotate($tree, $autop);

        $this->assertSame('<p>Classic text</p>', $out[0]['autop']);
        $this->assertArrayNotHasKey('autop', $out[1]);
        $this->assertSame('<p>Nested</p>', $out[2]['innerBlocks'][0]['autop']);
        $this->assertArrayNotHasKey('autop', $out[3]);
    }
}
