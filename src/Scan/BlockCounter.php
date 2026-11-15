<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Scan;

use Teme\Companion\Report\Limits;

/**
 * Counts block types across posts from parse_blocks() output: how often, in how many posts,
 * which attributes they carry, how deeply they nest, and one short example of each.
 */
final class BlockCounter
{
    /** @var array<string,array{count:int,posts:int,example_ids:list<int>,attrs:array<string,int>,max_depth:int,example:string}> */
    private array $blocks = [];

    /** @var array{blocks:int,classic:int,mixed:int,empty:int} */
    private array $shapes = ['blocks' => 0, 'classic' => 0, 'mixed' => 0, 'empty' => 0];

    private int $maxDepth = 0;

    /**
     * @param array<mixed> $state
     * @return self
     */
    public static function fromState(array $state): self
    {
        $counter = new self();
        $counter->blocks = $state['blocks'] ?? [];
        $counter->shapes = $state['shapes'] ?? $counter->shapes;
        $counter->maxDepth = (int) ($state['max_depth'] ?? 0);

        return $counter;
    }

    /**
     * @return array<mixed>
     */
    public function state(): array
    {
        return ['blocks' => $this->blocks, 'shapes' => $this->shapes, 'max_depth' => $this->maxDepth];
    }

    /**
     * @param list<array<string,mixed>> $parsed
     */
    public function addPost(int $postId, array $parsed, bool $withExamples): void
    {
        $seen = [];
        $named = false;
        $classic = false;
        $this->walk($parsed, 1, $withExamples, $seen, $named, $classic);

        foreach (array_keys($seen) as $name) {
            $this->blocks[$name]['posts']++;
            if (count($this->blocks[$name]['example_ids']) < 5) {
                $this->blocks[$name]['example_ids'][] = $postId;
            }
        }

        if ($named && $classic) {
            $this->shapes['mixed']++;
        } elseif ($named) {
            $this->shapes['blocks']++;
        } elseif ($classic) {
            $this->shapes['classic']++;
        } else {
            $this->shapes['empty']++;
        }
    }

    /**
     * Shapes of posts, the deepest nesting, and block types from most to least used.
     *
     * @return array<string,mixed>
     */
    public function summary(): array
    {
        $blocks = $this->blocks;
        uasort($blocks, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return ['shapes' => $this->shapes, 'max_depth' => $this->maxDepth, 'blocks' => $blocks];
    }

    /**
     * @param list<array<string,mixed>> $blocks
     * @param array<string,bool>        $seen
     */
    private function walk(array $blocks, int $depth, bool $withExamples, array &$seen, bool &$named, bool &$classic): void
    {
        foreach ($blocks as $block) {
            $name = $block['blockName'] ?? null;
            if (! is_string($name) || $name === '') {
                if (trim((string) ($block['innerHTML'] ?? '')) !== '') {
                    $classic = true;
                }
                continue;
            }

            $named = true;
            if (! isset($this->blocks[$name]) && count($this->blocks) >= 1000) {
                $name = '(other)';
            }
            if (! isset($this->blocks[$name])) {
                $this->blocks[$name] = ['count' => 0, 'posts' => 0, 'example_ids' => [], 'attrs' => [], 'max_depth' => 0, 'example' => ''];
            }
            $seen[$name] = true;

            $this->blocks[$name]['count']++;
            $this->blocks[$name]['max_depth'] = max($this->blocks[$name]['max_depth'], $depth);
            $this->maxDepth = max($this->maxDepth, $depth);

            $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
            foreach (array_keys($attrs) as $attr) {
                Limits::bump($this->blocks[$name]['attrs'], (string) $attr, 50);
            }

            if ($withExamples && $this->blocks[$name]['example'] === '') {
                $json = $attrs === [] ? '' : ' ' . (string) json_encode($attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $this->blocks[$name]['example'] = Limits::cut('<!-- wp:' . $name . $json . ' -->' . (string) ($block['innerHTML'] ?? ''));
            }

            $inner = is_array($block['innerBlocks'] ?? null) ? $block['innerBlocks'] : [];
            if ($inner !== []) {
                $this->walk($inner, $depth + 1, $withExamples, $seen, $named, $classic);
            }
        }
    }
}
