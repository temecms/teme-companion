<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Api\Endpoints;

use Teme\Companion\Api\Envelope;
use Teme\Companion\Api\PolylangAnswer;

/**
 * The site's Polylang languages and settings, read through Polylang's own functions, so Teme never
 * unserializes the `polylang` option itself (Teme's docs/specs/companion-languages.md).
 */
final class Languages
{
    /**
     * @return array<string,mixed>
     */
    public function handle(\WP_REST_Request $request): array
    {
        $others = PolylangAnswer::others(
            defined('TRP_PLUGIN_VERSION') ? self::translatePressLanguages() : null,
            class_exists('Inpsyde\\MultilingualPress\\MultilingualPress') ? self::multilingualPressSites() : null
        );

        if (! function_exists('pll_languages_list') || ! function_exists('PLL')) {
            return PolylangAnswer::inactive(Envelope::API, $others);
        }

        $languages = [];
        foreach ((array) PLL()->model->get_languages_list() as $language) {
            $languages[] = [
                'slug' => (string) ($language->slug ?? ''),
                'locale' => (string) ($language->locale ?? ''),
                'name' => (string) ($language->name ?? ''),
                'rtl' => ! empty($language->is_rtl),
                'order' => (int) ($language->term_group ?? 0),
            ];
        }

        $options = get_option('polylang');
        $options = is_array($options) ? $options : [];

        $postTypes = method_exists(PLL()->model, 'get_translated_post_types') ? (array) PLL()->model->get_translated_post_types() : [];
        $taxonomies = method_exists(PLL()->model, 'get_translated_taxonomies') ? (array) PLL()->model->get_translated_taxonomies() : [];

        $strings = [];
        if (function_exists('pll_translate_string')) {
            foreach ($languages as $language) {
                $strings[$language['slug']] = [
                    ['name' => 'Site Title', 'translation' => (string) pll_translate_string((string) get_option('blogname'), $language['slug'])],
                    ['name' => 'Tagline', 'translation' => (string) pll_translate_string((string) get_option('blogdescription'), $language['slug'])],
                ];
            }
        }

        return PolylangAnswer::active(
            Envelope::API,
            defined('POLYLANG_VERSION') ? (string) POLYLANG_VERSION : '',
            function_exists('pll_default_language') ? (string) pll_default_language('slug') : '',
            $languages,
            $options,
            array_values(array_map('strval', $postTypes)),
            array_values(array_map('strval', $taxonomies)),
            $strings,
            $others
        );
    }

    private static function translatePressLanguages(): int
    {
        $settings = get_option('trp_settings');

        return is_array($settings) && is_array($settings['translation-languages'] ?? null) ? count($settings['translation-languages']) : 0;
    }

    private static function multilingualPressSites(): int
    {
        $relations = function_exists('get_site_option') ? get_site_option('multilingualpress_site_relations') : null;

        return is_array($relations) ? count($relations) + 1 : 1;
    }
}
