<?php

declare(strict_types=1);

namespace Teme\Companion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Teme\Companion\Scan\HtmlScanner;

final class HtmlScannerTest extends TestCase
{
    private function scanner(): HtmlScanner
    {
        return new HtmlScanner(['example.com'], 'https', '/wp-content/uploads/');
    }

    public function test_it_classifies_links(): void
    {
        $s = $this->scanner();
        $s->addPost(implode(' ', [
            '<a href="https://example.com/my-post/">a</a>',
            '<a href="http://www.example.com/?p=12">b</a>',
            '<a href="https://example.com/?attachment_id=40">c</a>',
            '<a href="https://example.com/wp-content/uploads/2020/01/doc.pdf">d</a>',
            '<a href="/about/">e</a>',
            '<a href="#top">f</a>',
            '<a href="mailto:someone@x.org">g</a>',
            '<a href="https://other.org/x">h</a>',
        ]));

        $links = $s->summary()['links'];

        $this->assertSame(8, $links['total']);
        $this->assertSame(1, $links['own_permalink']);
        $this->assertSame(1, $links['own_query_id']);
        $this->assertSame(1, $links['attachment_id']);
        $this->assertSame(1, $links['own_uploads']);
        $this->assertSame(1, $links['own_http']);
        $this->assertSame(1, $links['own_www_variant']);
        $this->assertSame(1, $links['relative']);
        $this->assertSame(1, $links['fragment']);
        $this->assertSame(1, $links['mailto']);
        $this->assertSame(['other.org' => 1], $links['external_hosts']);
    }

    public function test_it_reads_images(): void
    {
        $s = $this->scanner();
        $s->addPost(implode(' ', [
            '<img class="alignnone size-medium wp-image-55" src="https://example.com/wp-content/uploads/2021/05/cat-300x200.jpg">',
            '<img src="https://example.com/wp-content/uploads/2021/05/big-scaled.jpg" srcset="a 1x">',
            '<img src="https://i0.wp.com/example.com/wp-content/uploads/x.jpg">',
            '<img src="data:image/png;base64,AAAA">',
            '<img src="https://example.com/wp-content/uploads/2021/05/pic-e1612345678901.jpg">',
        ]));

        $images = $s->summary()['images'];

        $this->assertSame(5, $images['total']);
        $this->assertSame(1, $images['wp_image_class']);
        $this->assertSame(1, $images['size_suffix']);
        $this->assertSame(1, $images['scaled']);
        $this->assertSame(1, $images['edited']);
        $this->assertSame(1, $images['srcset_stored']);
        $this->assertSame(1, $images['data_uris']);
        $this->assertSame(3, $images['own_uploads']);
        $this->assertSame(['i0.wp.com' => 1], $images['external_hosts']);
    }

    public function test_it_counts_risky_markup_embeds_and_markers(): void
    {
        $s = $this->scanner();
        $s->addPost("<p style=\"color:red\">x</p><script>alert(1)</script><iframe src=\"https://www.youtube.com/embed/abc\"></iframe>\n\nhttps://vimeo.com/123\n\n<!--more Read on--><!--nextpage--><table><tr><td>1</td></tr></table><form></form>");

        $sum = $s->summary();

        $this->assertSame(1, $sum['scripts']);
        $this->assertSame(1, $sum['style_attributes']);
        $this->assertSame(['www.youtube.com' => 1], $sum['iframe_hosts']);
        $this->assertSame(['vimeo.com' => 1], $sum['autoembed_hosts']);
        $this->assertSame(['more' => 1, 'noteaser' => 0, 'nextpage' => 1], $sum['markers']);
        $this->assertSame(1, $sum['posts_with']['table']);
        $this->assertSame(1, $sum['posts_with']['form']);
        $this->assertSame(1, $sum['tags']['table']);
    }
}
