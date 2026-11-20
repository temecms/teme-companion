<?php

declare(strict_types=1);

namespace Teme\Companion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Teme\Companion\Api\PolylangAnswer;

final class PolylangAnswerTest extends TestCase
{
    public function test_a_site_without_polylang_says_so_and_names_the_other_plugins(): void
    {
        $answer = PolylangAnswer::inactive(1, PolylangAnswer::others(3, null));

        $this->assertSame(['api' => 1, 'active' => false, 'others' => ['translatepress-multilingual' => ['name' => 'TranslatePress', 'languages' => 3]]], $answer);
        $this->assertSame('{"api":1,"active":false,"others":{}}', json_encode(PolylangAnswer::inactive(1, [])));
    }

    public function test_the_languages_come_in_polylangs_order_with_its_settings_as_plain_values(): void
    {
        $answer = PolylangAnswer::active(
            1,
            '3.8.9',
            'es',
            [
                ['slug' => 'hi', 'locale' => 'hi_IN', 'name' => 'हिन्दी', 'rtl' => false, 'order' => 4],
                ['slug' => 'es', 'locale' => 'es_ES', 'name' => 'Español', 'rtl' => false, 'order' => 0],
                ['slug' => 'en', 'locale' => 'en_US', 'name' => 'English', 'rtl' => false, 'order' => 1],
            ],
            ['force_lang' => '1', 'hide_default' => 1, 'rewrite' => 1, 'redirect_lang' => 0, 'browser' => 0, 'media_support' => 1, 'nav_menus' => ['theme' => ['primary' => ['es' => 3, 'en' => '4', 'hi' => 0]]]],
            ['post', 'page'],
            ['category', 'post_tag'],
            ['en' => [['name' => 'Site Title', 'translation' => 'The Verbalia'], ['name' => 'Tagline', 'translation' => '']]],
            []
        );

        $this->assertSame(['es', 'en', 'hi'], array_column($answer['languages'], 'slug'));
        $this->assertSame('es', $answer['default']);
        $this->assertSame(['force_lang' => 1, 'hide_default' => true, 'rewrite' => true, 'redirect_lang' => false, 'browser' => false, 'media_support' => true], $answer['options']);
        $this->assertSame(['theme' => ['primary' => ['es' => 3, 'en' => 4]]], $answer['menus']);
        $this->assertSame(['en' => [['name' => 'Site Title', 'translation' => 'The Verbalia']]], $answer['strings']);
        $this->assertSame(['post', 'page'], $answer['post_types']);
        $this->assertSame('{}', json_encode($answer['others']));
    }

    public function test_posts_pages_categories_and_tags_are_always_listed_as_translated(): void
    {
        $answer = PolylangAnswer::active(1, '3.8.9', 'es', [], [], ['product'], [], [], []);

        $this->assertSame(['post', 'page', 'product'], $answer['post_types']);
        $this->assertSame(['category', 'post_tag'], $answer['taxonomies']);
    }
}
