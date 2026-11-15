<?php

declare(strict_types=1);

namespace Teme\Companion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Teme\Companion\Support\SeoFields;

final class SeoFieldsTest extends TestCase
{
    private function resolve(): callable
    {
        return static fn (string $t): string => str_replace(['%%title%%', '%title%', '%%sep%%', '%sep%', '%%sitename%%', '%sitename%'], ['My post', 'My post', '-', '-', 'Site', 'Site'], $t);
    }

    public function test_yoast_fields_map_to_the_neutral_shape(): void
    {
        $meta = [
            '_yoast_wpseo_title' => ['%%title%% %%sep%% %%sitename%%'],
            '_yoast_wpseo_metadesc' => ['A description'],
            '_yoast_wpseo_canonical' => ['https://example.com/other/'],
            '_yoast_wpseo_meta-robots-noindex' => ['1'],
            '_yoast_wpseo_meta-robots-nofollow' => ['1'],
            '_yoast_wpseo_opengraph-title' => [''],
            '_yoast_wpseo_twitter-title' => ['From X'],
            '_yoast_wpseo_opengraph-image-id' => ['55'],
            '_yoast_wpseo_opengraph-image' => ['https://example.com/wp-content/uploads/a.jpg'],
            '_yoast_wpseo_primary_category' => ['12'],
            '_yoast_wpseo_focuskw' => ['ignored'],
        ];

        $f = SeoFields::fromYoast($meta, $this->resolve());

        $this->assertSame('My post - Site', $f['title']);
        $this->assertSame('A description', $f['description']);
        $this->assertSame('https://example.com/other/', $f['canonical']);
        $this->assertTrue($f['noindex']);
        $this->assertTrue($f['nofollow']);
        $this->assertSame('From X', $f['social_title']);
        $this->assertSame(55, $f['social_image_id']);
        $this->assertSame(['category' => 12], $f['primary_terms']);
        $this->assertSame(['title'], $f['had_variables']);
        $this->assertArrayNotHasKey('focus_keyword', $f);
    }

    public function test_yoast_forced_index_and_default_differ(): void
    {
        $this->assertFalse(SeoFields::fromYoast(['_yoast_wpseo_meta-robots-noindex' => ['2']], $this->resolve())['noindex']);
        $this->assertNull(SeoFields::fromYoast(['_yoast_wpseo_meta-robots-noindex' => ['0']], $this->resolve())['noindex']);
        $this->assertNull(SeoFields::fromYoast([], $this->resolve())['noindex']);
    }

    public function test_rank_math_fields_map_to_the_same_shape(): void
    {
        $meta = [
            'rank_math_title' => ['%title% %sep% %sitename%'],
            'rank_math_description' => ['RM description'],
            'rank_math_canonical_url' => [''],
            'rank_math_robots' => [['noindex', 'nofollow']],
            'rank_math_facebook_title' => ['FB title'],
            'rank_math_facebook_image_id' => ['9'],
            'rank_math_primary_category' => ['4'],
        ];

        $f = SeoFields::fromRankMath($meta, $this->resolve());

        $this->assertSame('My post - Site', $f['title']);
        $this->assertNull($f['canonical']);
        $this->assertTrue($f['noindex']);
        $this->assertTrue($f['nofollow']);
        $this->assertSame('FB title', $f['social_title']);
        $this->assertSame(9, $f['social_image_id']);
        $this->assertSame(['category' => 4], $f['primary_terms']);
    }

    public function test_rank_math_index_means_false_and_absence_means_null(): void
    {
        $this->assertFalse(SeoFields::fromRankMath(['rank_math_robots' => [['index']]], $this->resolve())['noindex']);
        $this->assertNull(SeoFields::fromRankMath([], $this->resolve())['noindex']);
    }
}
