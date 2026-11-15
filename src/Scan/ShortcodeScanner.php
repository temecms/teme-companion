<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Scan;

use Teme\Companion\Report\Limits;

/**
 * Finds every [shortcode] in post content, registered or not: how often, in how many posts,
 * with which attribute names, whether it encloses content, and one example opening tag.
 */
final class ShortcodeScanner
{
    private const ESCAPED = '/\[\[[A-Za-z][\w-]*[^\]]*\]\]/';

    private const OPENING = '/\[([A-Za-z][A-Za-z0-9_-]*)((?:\s[^\]]*)?)\](?!\()/';

    /** @var array<string,array{count:int,posts:int,example_ids:list<int>,attrs:array<string,int>,enclosing:bool,example:string}> */
    private array $tags = [];

    private int $escaped = 0;

    /**
     * @param array<mixed> $state
     * @return self
     */
    public static function fromState(array $state): self
    {
        $scanner = new self();
        $scanner->tags = $state['tags'] ?? [];
        $scanner->escaped = (int) ($state['escaped'] ?? 0);

        return $scanner;
    }

    /**
     * @return array<mixed>
     */
    public function state(): array
    {
        return ['tags' => $this->tags, 'escaped' => $this->escaped];
    }

    /**
     * @return void
     */
    public function addPost(int $postId, string $content, bool $withExamples): void
    {
        if (strpos($content, '[') === false) {
            return;
        }

        $this->escaped += (int) preg_match_all(self::ESCAPED, $content);
        $clean = (string) preg_replace(self::ESCAPED, '', $content);

        if (! preg_match_all(self::OPENING, $clean, $matches, PREG_SET_ORDER)) {
            return;
        }

        $seen = [];
        foreach ($matches as $match) {
            $name = $match[1];
            if (! isset($this->tags[$name])) {
                if (count($this->tags) >= 1000) {
                    continue;
                }
                $this->tags[$name] = ['count' => 0, 'posts' => 0, 'example_ids' => [], 'attrs' => [], 'enclosing' => false, 'example' => ''];
            }

            $this->tags[$name]['count']++;
            $seen[$name] = true;

            if (preg_match_all('/([A-Za-z_][\w-]*)\s*=/', $match[2], $attrs)) {
                foreach ($attrs[1] as $attr) {
                    Limits::bump($this->tags[$name]['attrs'], strtolower($attr), 30);
                }
            }

            if (strpos($clean, '[/' . $name . ']') !== false) {
                $this->tags[$name]['enclosing'] = true;
            }

            if ($withExamples && $this->tags[$name]['example'] === '') {
                $this->tags[$name]['example'] = Limits::cut($match[0], 400);
            }
        }

        foreach (array_keys($seen) as $name) {
            $this->tags[$name]['posts']++;
            if (count($this->tags[$name]['example_ids']) < 5) {
                $this->tags[$name]['example_ids'][] = $postId;
            }
        }
    }

    /**
     * Shortcodes from most to least used, and how many were escaped with double brackets.
     *
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $tags = $this->tags;
        uasort($tags, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return ['tags' => $tags, 'escaped' => $this->escaped];
    }
}
