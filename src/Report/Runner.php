<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Report;

use Teme\Companion\Scan\ContentScan;

/**
 * Runs the report one section at a time, and the content scan one batch at a time,
 * saving progress between steps, then assembles, redacts and checks the finished report.
 */
final class Runner
{
    public const REPORT_VERSION = 1;

    private Store $store;

    /** @var list<Collector> */
    private array $collectors;

    private ContentScan $scan;

    /**
     * @param list<Collector> $collectors
     */
    public function __construct(Store $store, array $collectors, ContentScan $scan)
    {
        $this->store = $store;
        $this->collectors = $collectors;
        $this->scan = $scan;
    }

    /**
     * Clears the old report and state, ready to start a new scan.
     *
     * @return void
     */
    public function begin(bool $withExamples): void
    {
        $this->store->clear();
        $this->store->saveState([
            'index' => 0,
            'with_examples' => $withExamples,
            'sections' => [],
            'errors' => [],
            'content' => null,
            'started_at' => gmdate('c'),
        ]);
    }

    /**
     * Runs the next section or content batch.
     *
     * @return array{done:bool,section:string,progress:float}
     */
    public function step(): array
    {
        $state = $this->store->state();
        if ($state === []) {
            return ['done' => true, 'section' => '', 'progress' => 1.0];
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $total = count($this->collectors) + 1;
        $index = (int) $state['index'];

        if ($index < count($this->collectors)) {
            $collector = $this->collectors[$index];
            try {
                $state['sections'][$collector->key()] = $collector->collect();
            } catch (\Throwable $e) {
                $state['errors'][$collector->key()] = get_class($e) . ': ' . $e->getMessage();
            }
            $state['index'] = $index + 1;
            $this->store->saveState($state);

            return ['done' => false, 'section' => $collector->key(), 'progress' => ($index + 1) / $total];
        }

        try {
            $content = $state['content'] ?? $this->scan->start();
            $content = $this->scan->step($content, (bool) $state['with_examples']);
            $state['content'] = $content;
            if ($content['done']) {
                $state['sections']['content'] = $this->scan->summary($content);
            }
        } catch (\Throwable $e) {
            $state['errors']['content'] = get_class($e) . ': ' . $e->getMessage();
            $content = ['done' => true];
        }

        if (! empty($content['done'])) {
            try {
                $report = self::assemble($state['sections'], $state['errors'], [
                    'plugin_version' => defined('TEME_COMPANION_VERSION') ? TEME_COMPANION_VERSION : 'unknown',
                    'include_examples' => (bool) $state['with_examples'],
                    'started_at' => $state['started_at'],
                    'generated_at' => gmdate('c'),
                ]);
                $this->store->saveReport($report);
            } finally {
                delete_option(Store::STATE);
            }

            return ['done' => true, 'section' => 'content', 'progress' => 1.0];
        }

        $this->store->saveState($state);
        $done = (int) ($content['processed'] ?? 0);
        $all = max(1, (int) ($content['total'] ?? 1));

        return ['done' => false, 'section' => 'content', 'progress' => (count($this->collectors) + min(1, $done / $all)) / $total];
    }

    /**
     * The finished report: a header, every section and any errors, with emails and IPs redacted.
     *
     * @param array<string,mixed>  $sections
     * @param array<string,string> $errors
     * @param array<string,mixed>  $meta
     * @return array<string,mixed>
     */
    public static function assemble(array $sections, array $errors, array $meta): array
    {
        $report = Redactor::tree(array_merge(
            ['report_version' => self::REPORT_VERSION],
            $meta,
            ['sections' => $sections, 'errors' => $errors]
        ));

        // Scoped to users/comments only: every other section legitimately uses a forbidden name
        // (e.g. "session_tokens") as a dictionary KEY to report a count, never as a field holding
        // a real value. Only users/comments collectors ever touch WP_User/WP_Comment-shaped data,
        // so they're the only place a forbidden key could hold the real thing. A new collector
        // that returns per-user or per-comment records must be added here, not exempted from it.
        $forbidden = [];
        if (isset($report['sections']['users']) && is_array($report['sections']['users'])) {
            $forbidden = array_merge($forbidden, Redactor::forbiddenKeys($report['sections']['users'], 'sections.users'));
        }
        if (isset($report['sections']['comments']) && is_array($report['sections']['comments'])) {
            $forbidden = array_merge($forbidden, Redactor::forbiddenKeys($report['sections']['comments'], 'sections.comments'));
        }
        if ($forbidden !== []) {
            throw new \RuntimeException('The report contains personal-data keys: ' . implode(', ', $forbidden));
        }

        return $report;
    }
}
