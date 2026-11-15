<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Support;

/**
 * Maps one item's Yoast or Rank Math meta to the neutral fields Teme imports (Teme study T §3.13),
 * with template variables resolved to literal text. Focus keywords, scores and cornerstone flags
 * are left out on purpose (Teme I-353).
 */
final class SeoFields
{
    /**
     * @param array<string,list<mixed>> $meta
     * @param callable(string):string   $resolve
     * @return array<string,mixed>
     */
    public static function fromYoast(array $meta, callable $resolve): array
    {
        $v = static fn (string $key): string => trim((string) ($meta[$key][0] ?? ''));
        $f = self::blank();

        $f['noindex'] = ['1' => true, '2' => false][$v('_yoast_wpseo_meta-robots-noindex')] ?? null;
        $f['nofollow'] = $v('_yoast_wpseo_meta-robots-nofollow') === '1';
        $f['canonical'] = $v('_yoast_wpseo_canonical') !== '' ? $v('_yoast_wpseo_canonical') : null;

        self::text($f, 'title', $v('_yoast_wpseo_title'), $resolve, '/%%[a-z_]+%%/i');
        self::text($f, 'description', $v('_yoast_wpseo_metadesc'), $resolve, '/%%[a-z_]+%%/i');
        self::text($f, 'social_title', $v('_yoast_wpseo_opengraph-title') !== '' ? $v('_yoast_wpseo_opengraph-title') : $v('_yoast_wpseo_twitter-title'), $resolve, '/%%[a-z_]+%%/i');
        self::text($f, 'social_description', $v('_yoast_wpseo_opengraph-description') !== '' ? $v('_yoast_wpseo_opengraph-description') : $v('_yoast_wpseo_twitter-description'), $resolve, '/%%[a-z_]+%%/i');

        $imageId = $v('_yoast_wpseo_opengraph-image-id') !== '' ? $v('_yoast_wpseo_opengraph-image-id') : $v('_yoast_wpseo_twitter-image-id');
        $f['social_image_id'] = ctype_digit($imageId) ? (int) $imageId : null;
        $imageUrl = $v('_yoast_wpseo_opengraph-image') !== '' ? $v('_yoast_wpseo_opengraph-image') : $v('_yoast_wpseo_twitter-image');
        $f['social_image_url'] = $imageUrl !== '' ? $imageUrl : null;

        foreach ($meta as $key => $values) {
            if (strpos((string) $key, '_yoast_wpseo_primary_') === 0 && ctype_digit(trim((string) ($values[0] ?? '')))) {
                $f['primary_terms'][substr((string) $key, strlen('_yoast_wpseo_primary_'))] = (int) $values[0];
            }
        }

        return $f;
    }

    /**
     * @param array<string,list<mixed>> $meta
     * @param callable(string):string   $resolve
     * @return array<string,mixed>
     */
    public static function fromRankMath(array $meta, callable $resolve): array
    {
        $v = static fn (string $key): string => is_scalar($meta[$key][0] ?? null) ? trim((string) $meta[$key][0]) : '';
        $f = self::blank();

        $robots = $meta['rank_math_robots'][0] ?? null;
        if (is_string($robots)) {
            $robots = Safe::unserialize($robots);
        }
        if (is_array($robots)) {
            $f['noindex'] = in_array('noindex', $robots, true) ? true : (in_array('index', $robots, true) ? false : null);
            $f['nofollow'] = in_array('nofollow', $robots, true);
        }

        $f['canonical'] = $v('rank_math_canonical_url') !== '' ? $v('rank_math_canonical_url') : null;

        self::text($f, 'title', $v('rank_math_title'), $resolve, '/%[a-z_]+(\([^)]*\))?%/i');
        self::text($f, 'description', $v('rank_math_description'), $resolve, '/%[a-z_]+(\([^)]*\))?%/i');
        self::text($f, 'social_title', $v('rank_math_facebook_title') !== '' ? $v('rank_math_facebook_title') : $v('rank_math_twitter_title'), $resolve, '/%[a-z_]+(\([^)]*\))?%/i');
        self::text($f, 'social_description', $v('rank_math_facebook_description') !== '' ? $v('rank_math_facebook_description') : $v('rank_math_twitter_description'), $resolve, '/%[a-z_]+(\([^)]*\))?%/i');

        $imageId = $v('rank_math_facebook_image_id') !== '' ? $v('rank_math_facebook_image_id') : $v('rank_math_twitter_image_id');
        $f['social_image_id'] = ctype_digit($imageId) ? (int) $imageId : null;
        $imageUrl = $v('rank_math_facebook_image') !== '' ? $v('rank_math_facebook_image') : $v('rank_math_twitter_image');
        $f['social_image_url'] = $imageUrl !== '' ? $imageUrl : null;

        foreach ($meta as $key => $values) {
            if (strpos((string) $key, 'rank_math_primary_') === 0 && ctype_digit(trim((string) ($values[0] ?? '')))) {
                $f['primary_terms'][substr((string) $key, strlen('rank_math_primary_'))] = (int) $values[0];
            }
        }

        return $f;
    }

    /**
     * @return array<string,mixed>
     */
    private static function blank(): array
    {
        return [
            'title' => null, 'description' => null, 'canonical' => null, 'noindex' => null, 'nofollow' => false,
            'social_title' => null, 'social_description' => null, 'social_image_id' => null, 'social_image_url' => null,
            'primary_terms' => [], 'had_variables' => [],
        ];
    }

    /**
     * @param array<string,mixed>     $f
     * @param callable(string):string $resolve
     */
    private static function text(array &$f, string $field, string $value, callable $resolve, string $variable): void
    {
        if ($value === '') {
            return;
        }
        if (preg_match($variable, $value)) {
            $f['had_variables'][] = $field;
            $value = trim($resolve($value));
        }
        $f[$field] = $value !== '' ? $value : null;
    }
}
