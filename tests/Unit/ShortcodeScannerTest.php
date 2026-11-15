<?php

declare(strict_types=1);

namespace Teme\Companion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Teme\Companion\Scan\ShortcodeScanner;

final class ShortcodeScannerTest extends TestCase
{
    public function test_it_finds_self_closing_and_enclosing_shortcodes_with_attribute_names(): void
    {
        $scanner = new ShortcodeScanner();
        $scanner->addPost(7, '[caption id="attachment_12" align="alignleft" width="300"]<img src="a.jpg"> A cat[/caption] and [gallery ids="1,2,3"]', true);
        $scanner->addPost(8, 'Text [gallery ids="4"] [my_old_plugin foo=bar]', false);

        $tags = $scanner->summary()['tags'];

        $this->assertSame(1, $tags['caption']['count']);
        $this->assertTrue($tags['caption']['enclosing']);
        $this->assertSame(['id' => 1, 'align' => 1, 'width' => 1], $tags['caption']['attrs']);
        $this->assertSame(2, $tags['gallery']['count']);
        $this->assertSame(2, $tags['gallery']['posts']);
        $this->assertSame([7, 8], $tags['gallery']['example_ids']);
        $this->assertFalse($tags['gallery']['enclosing']);
        $this->assertSame(1, $tags['my_old_plugin']['count']);
        $this->assertSame('[caption id="attachment_12" align="alignleft" width="300"]', $tags['caption']['example']);
    }

    public function test_it_skips_escaped_shortcodes_markdown_links_and_closing_tags(): void
    {
        $scanner = new ShortcodeScanner();
        $scanner->addPost(1, 'Write [[gallery]] to show it. See [the docs](https://x.org). [/caption] [1]', false);

        $summary = $scanner->summary();

        $this->assertSame([], $summary['tags']);
        $this->assertSame(1, $summary['escaped']);
    }
}
