<?php

declare(strict_types=1);

namespace Teme\Companion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Teme\Companion\Scan\BlockCounter;

final class BlockCounterTest extends TestCase
{
    /** @return array<string,mixed> */
    private function block(?string $name, array $attrs = [], array $inner = [], string $html = ''): array
    {
        return ['blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => $inner, 'innerHTML' => $html, 'innerContent' => [$html]];
    }

    public function test_it_counts_blocks_posts_attributes_and_depth(): void
    {
        $counter = new BlockCounter();
        $counter->addPost(10, [
            $this->block('core/paragraph', [], [], '<p>Hi</p>'),
            $this->block(null, [], [], "\n\n"),
            $this->block('core/group', ['layout' => ['type' => 'constrained']], [
                $this->block('core/image', ['id' => 5, 'sizeSlug' => 'large'], [], '<figure><img src="x.jpg"/></figure>'),
                $this->block('core/paragraph', [], [], '<p>In</p>'),
            ]),
        ], true);
        $counter->addPost(11, [$this->block('core/paragraph', [], [], '<p>Again</p>')], false);

        $summary = $counter->summary();

        $this->assertSame(3, $summary['blocks']['core/paragraph']['count']);
        $this->assertSame(2, $summary['blocks']['core/paragraph']['posts']);
        $this->assertSame([10, 11], $summary['blocks']['core/paragraph']['example_ids']);
        $this->assertSame(['id' => 1, 'sizeSlug' => 1], $summary['blocks']['core/image']['attrs']);
        $this->assertSame(2, $summary['blocks']['core/image']['max_depth']);
        $this->assertSame(2, $summary['max_depth']);
        $this->assertSame(['blocks' => 2, 'classic' => 0, 'mixed' => 0, 'empty' => 0], $summary['shapes']);
        $this->assertStringContainsString('<!-- wp:core/image {"id":5,"sizeSlug":"large"} -->', $summary['blocks']['core/image']['example']);
    }

    public function test_it_tells_classic_mixed_and_empty_posts_apart(): void
    {
        $counter = new BlockCounter();
        $counter->addPost(1, [$this->block(null, [], [], "Classic text\n\nMore")], false);
        $counter->addPost(2, [$this->block(null, [], [], 'Before'), $this->block('core/quote', [], [], '<blockquote>Q</blockquote>')], false);
        $counter->addPost(3, [$this->block(null, [], [], "  \n ")], false);

        $this->assertSame(['blocks' => 0, 'classic' => 1, 'mixed' => 1, 'empty' => 1], $counter->summary()['shapes']);
    }

    public function test_state_round_trips(): void
    {
        $a = new BlockCounter();
        $a->addPost(1, [$this->block('core/heading', ['level' => 2], [], '<h2>H</h2>')], false);

        $b = BlockCounter::fromState($a->state());
        $b->addPost(2, [$this->block('core/heading', [], [], '<h2>I</h2>')], false);

        $this->assertSame(2, $b->summary()['blocks']['core/heading']['count']);
    }
}
