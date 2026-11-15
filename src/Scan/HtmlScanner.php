<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Scan;

use Teme\Companion\Report\Limits;

/**
 * Reads post HTML for what conversion must handle: tag names, inline styles, scripts, iframes,
 * forms, how images and links point at the site, bare embed URLs, and more/nextpage markers.
 * It counts; it never stores the text.
 */
final class HtmlScanner
{
    /** @var list<string> */
    private array $ownHosts;

    private string $homeScheme;

    private string $uploadsPath;

    /** @var array<string,mixed> */
    private array $s;

    /**
     * @param list<string> $ownHosts
     */
    public function __construct(array $ownHosts, string $homeScheme, string $uploadsPath)
    {
        $this->ownHosts = $ownHosts;
        $this->homeScheme = $homeScheme;
        $this->uploadsPath = $uploadsPath;
        $this->s = [
            'tags' => [],
            'style_attributes' => 0,
            'scripts' => 0,
            'iframe_hosts' => [],
            'autoembed_hosts' => [],
            'markers' => ['more' => 0, 'noteaser' => 0, 'nextpage' => 0],
            'posts_with' => ['script' => 0, 'iframe' => 0, 'style_attribute' => 0, 'table' => 0, 'form' => 0],
            'images' => ['total' => 0, 'wp_image_class' => 0, 'size_suffix' => 0, 'scaled' => 0, 'rotated' => 0, 'edited' => 0, 'srcset_stored' => 0, 'data_uris' => 0, 'own_uploads' => 0, 'external_hosts' => []],
            'links' => ['total' => 0, 'own_permalink' => 0, 'own_query_id' => 0, 'attachment_id' => 0, 'own_uploads' => 0, 'own_http' => 0, 'own_www_variant' => 0, 'relative' => 0, 'fragment' => 0, 'mailto' => 0, 'tel' => 0, 'external' => 0, 'external_hosts' => []],
        ];
    }

    /**
     * @param array<mixed> $state
     * @param list<string> $ownHosts
     * @return self
     */
    public static function fromState(array $state, array $ownHosts, string $homeScheme, string $uploadsPath): self
    {
        $scanner = new self($ownHosts, $homeScheme, $uploadsPath);
        if ($state !== []) {
            $scanner->s = array_replace_recursive($scanner->s, $state);
        }

        return $scanner;
    }

    /**
     * @return array<string,mixed>
     */
    public function state(): array
    {
        return $this->s;
    }

    /**
     * @return void
     */
    public function addPost(string $content): void
    {
        if ($content === '') {
            return;
        }

        if (preg_match_all('/<([a-zA-Z][a-zA-Z0-9-]*)\b/', $content, $tags)) {
            foreach ($tags[1] as $tag) {
                Limits::bump($this->s['tags'], strtolower($tag), 300);
            }
        }

        $styles = (int) preg_match_all('/\sstyle\s*=\s*["\']/i', $content);
        $scripts = (int) preg_match_all('/<script\b/i', $content);
        $this->s['style_attributes'] += $styles;
        $this->s['scripts'] += $scripts;
        $this->s['posts_with']['style_attribute'] += $styles > 0 ? 1 : 0;
        $this->s['posts_with']['script'] += $scripts > 0 ? 1 : 0;
        $this->s['posts_with']['table'] += preg_match('/<table\b/i', $content) ? 1 : 0;
        $this->s['posts_with']['form'] += preg_match('/<form\b/i', $content) ? 1 : 0;

        if (preg_match_all('/<iframe\b[^>]*\bsrc\s*=\s*["\']([^"\']+)/i', $content, $frames)) {
            $this->s['posts_with']['iframe']++;
            foreach ($frames[1] as $src) {
                Limits::bump($this->s['iframe_hosts'], $this->host($src) ?? '(relative)', 200);
            }
        }

        if (preg_match_all('/^\s*(?:<p>)?\s*(https?:\/\/[^\s<>"]+)\s*(?:<\/p>)?\s*$/im', $content, $embeds)) {
            foreach ($embeds[1] as $url) {
                Limits::bump($this->s['autoembed_hosts'], $this->host($url) ?? '(unknown)', 200);
            }
        }

        $this->s['markers']['more'] += (int) preg_match_all('/<!--more(.*?)?-->/', $content);
        $this->s['markers']['noteaser'] += (int) preg_match_all('/<!--noteaser-->/', $content);
        $this->s['markers']['nextpage'] += (int) preg_match_all('/<!--nextpage-->/', $content);

        $this->images($content);
        $this->links($content);
    }

    /**
     * Everything counted so far, with the host lists cut to their top entries.
     *
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $s = $this->s;
        $s['tags'] = Limits::top($s['tags'], 300);
        $s['iframe_hosts'] = Limits::top($s['iframe_hosts'], 100);
        $s['autoembed_hosts'] = Limits::top($s['autoembed_hosts'], 100);
        $s['images']['external_hosts'] = Limits::top($s['images']['external_hosts'], 100);
        $s['links']['external_hosts'] = Limits::top($s['links']['external_hosts'], 100);

        return $s;
    }

    private function images(string $content): void
    {
        if (! preg_match_all('/<img\b[^>]*>/i', $content, $imgs)) {
            return;
        }

        foreach ($imgs[0] as $img) {
            $this->s['images']['total']++;
            if (preg_match('/\bwp-image-\d+\b/', $img)) {
                $this->s['images']['wp_image_class']++;
            }
            if (preg_match('/\ssrcset\s*=/i', $img)) {
                $this->s['images']['srcset_stored']++;
            }
            if (! preg_match('/\ssrc\s*=\s*["\']([^"\']+)/i', $img, $src)) {
                continue;
            }
            $url = html_entity_decode($src[1]);
            if (stripos($url, 'data:') === 0) {
                $this->s['images']['data_uris']++;
                continue;
            }
            $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
            if (preg_match('/-\d+x\d+\.(?:jpe?g|png|gif|webp|avif)$/i', $path)) {
                $this->s['images']['size_suffix']++;
            }
            if (preg_match('/-scaled\.[a-z]+$/i', $path)) {
                $this->s['images']['scaled']++;
            }
            if (preg_match('/-rotated\.[a-z]+$/i', $path)) {
                $this->s['images']['rotated']++;
            }
            if (preg_match('/-e\d{13}\.[a-z]+$/i', $path)) {
                $this->s['images']['edited']++;
            }

            $host = $this->host($url);
            if ($host === null || $this->isOwn($host)) {
                if (strpos($path, $this->uploadsPath) !== false) {
                    $this->s['images']['own_uploads']++;
                }
            } else {
                Limits::bump($this->s['images']['external_hosts'], $host, 500);
            }
        }
    }

    private function links(string $content): void
    {
        if (! preg_match_all('/<a\b[^>]*\bhref\s*=\s*["\']([^"\']*)/i', $content, $hrefs)) {
            return;
        }

        foreach ($hrefs[1] as $href) {
            $href = trim(html_entity_decode($href));
            $this->s['links']['total']++;

            if ($href === '' || $href[0] === '#') {
                $this->s['links']['fragment']++;
                continue;
            }
            if (stripos($href, 'mailto:') === 0) {
                $this->s['links']['mailto']++;
                continue;
            }
            if (stripos($href, 'tel:') === 0) {
                $this->s['links']['tel']++;
                continue;
            }

            $parts = parse_url($href);
            if ($parts === false) {
                continue;
            }
            $query = (string) ($parts['query'] ?? '');
            $path = (string) ($parts['path'] ?? '');

            if (! isset($parts['host'])) {
                $this->s['links']['relative']++;
                continue;
            }

            $host = strtolower($parts['host']);
            if (! $this->isOwn($host)) {
                $this->s['links']['external']++;
                Limits::bump($this->s['links']['external_hosts'], $host, 500);
                continue;
            }

            if (strtolower((string) ($parts['scheme'] ?? '')) === 'http' && $this->homeScheme === 'https') {
                $this->s['links']['own_http']++;
            }
            if (strpos($host, 'www.') === 0) {
                $this->s['links']['own_www_variant']++;
            }

            if (preg_match('/(?:^|&)attachment_id=\d+/', $query)) {
                $this->s['links']['attachment_id']++;
            } elseif (preg_match('/(?:^|&)(?:p|page_id)=\d+/', $query)) {
                $this->s['links']['own_query_id']++;
            } elseif (strpos($path, $this->uploadsPath) !== false) {
                $this->s['links']['own_uploads']++;
            } else {
                $this->s['links']['own_permalink']++;
            }
        }
    }

    private function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    private function isOwn(string $host): bool
    {
        $bare = (string) preg_replace('/^www\./', '', $host);

        return in_array($bare, $this->ownHosts, true);
    }
}
