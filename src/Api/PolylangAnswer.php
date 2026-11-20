<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Api;

/**
 * Shapes the `languages` route's answer from what Polylang gave, as Teme's
 * docs/specs/companion-languages.md describes it: plain JSON, never serialized PHP.
 */
final class PolylangAnswer
{
    /** The Polylang settings Teme reads, all as booleans except force_lang. */
    private const FLAGS = ['hide_default', 'rewrite', 'redirect_lang', 'browser', 'media_support'];

    /**
     * The answer for a site without Polylang.
     *
     * @param array<string,array{name:string,languages:int}> $others
     * @return array{api:int, active:false, others:array<string,array{name:string,languages:int}>|object}
     */
    public static function inactive(int $api, array $others): array
    {
        return ['api' => $api, 'active' => false, 'others' => $others === [] ? (object) [] : $others];
    }

    /**
     * The answer for a site with Polylang.
     *
     * @param list<array{slug:string,locale:string,name:string,rtl:bool,order:int}> $languages
     * @param array<string,mixed> $options the `polylang` option, as WordPress returns it
     * @param list<string> $postTypes
     * @param list<string> $taxonomies
     * @param array<string,list<array{name:string,translation:string}>> $strings
     * @param array<string,array{name:string,languages:int}> $others
     * @return array<string,mixed>
     */
    public static function active(int $api, string $version, string $default, array $languages, array $options, array $postTypes, array $taxonomies, array $strings, array $others): array
    {
        usort($languages, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        $settings = ['force_lang' => (int) ($options['force_lang'] ?? 1)];
        foreach (self::FLAGS as $flag) {
            $settings[$flag] = ! empty($options[$flag]);
        }

        $menus = [];
        foreach ((array) ($options['nav_menus'] ?? []) as $theme => $locations) {
            foreach ((array) $locations as $location => $byLanguage) {
                foreach ((array) $byLanguage as $slug => $menuId) {
                    if ((int) $menuId > 0) {
                        $menus[(string) $theme][(string) $location][(string) $slug] = (int) $menuId;
                    }
                }
            }
        }

        $kept = [];
        foreach ($strings as $slug => $pairs) {
            foreach ($pairs as $pair) {
                if ($pair['name'] !== '' && $pair['translation'] !== '') {
                    $kept[(string) $slug][] = $pair;
                }
            }
        }

        return [
            'api' => $api,
            'active' => true,
            'plugin_version' => $version,
            'default' => $default,
            'languages' => $languages,
            'options' => $settings,
            'post_types' => array_values(array_unique(['post', 'page', ...$postTypes])),
            'taxonomies' => array_values(array_unique(['category', 'post_tag', ...$taxonomies])),
            'menus' => $menus === [] ? (object) [] : $menus,
            'strings' => $kept === [] ? (object) [] : $kept,
            'others' => $others === [] ? (object) [] : $others,
        ];
    }

    /**
     * The other translation plugins active on the site, by slug; null for one that isn't active.
     *
     * @return array<string,array{name:string,languages:int}>
     */
    public static function others(?int $translatePress, ?int $multilingualPress): array
    {
        $others = [];
        if ($translatePress !== null) {
            $others['translatepress-multilingual'] = ['name' => 'TranslatePress', 'languages' => $translatePress];
        }
        if ($multilingualPress !== null) {
            $others['multilingualpress'] = ['name' => 'MultilingualPress', 'languages' => $multilingualPress];
        }

        return $others;
    }
}
