<?php
/**
 * Checks a downloaded Teme site report: shape, sections, and no personal data.
 * Usage: php tests/smoke/check-report.php path\to\teme-site-report-….json
 *
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Teme\Companion\Report\Redactor;

$file = $argv[1] ?? '';
$report = json_decode((string) @file_get_contents($file), true);
if (! is_array($report)) {
    fwrite(STDERR, "Not a JSON report: $file\n");
    exit(1);
}

$problems = [];
foreach (['report_version', 'plugin_version', 'generated_at', 'sections', 'errors'] as $key) {
    if (! array_key_exists($key, $report)) {
        $problems[] = "missing top-level key: $key";
    }
}
$expected = ['environment', 'plugins', 'content_types', 'post_states', 'media', 'meta_keys', 'options', 'tables', 'users', 'comments', 'seo', 'redirects', 'menus', 'widgets', 'templates', 'builders', 'multilingual', 'fields', 'cron', 'content'];
foreach ($expected as $section) {
    if (! isset($report['sections'][$section]) && ! isset($report['errors'][$section])) {
        $problems[] = "section neither present nor reported as an error: $section";
    }
}

$json = (string) json_encode($report);
if (preg_match('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $json, $m)) {
    $problems[] = "an email address survived: {$m[0]}";
}
if (preg_match('/\$(?:P|wp|2y)\$/', $json)) {
    $problems[] = 'something shaped like a password hash is present';
}
// Scoped the same way as Runner::assemble() — keep the two in sync. See that method's comment
// for why only users/comments are scanned for forbidden key names.
$forbidden = [];
if (isset($report['sections']['users']) && is_array($report['sections']['users'])) {
    $forbidden = array_merge($forbidden, Redactor::forbiddenKeys($report['sections']['users'], 'sections.users'));
}
if (isset($report['sections']['comments']) && is_array($report['sections']['comments'])) {
    $forbidden = array_merge($forbidden, Redactor::forbiddenKeys($report['sections']['comments'], 'sections.comments'));
}
foreach ($forbidden as $path) {
    $problems[] = "forbidden key: $path";
}

foreach ((array) ($report['errors'] ?? []) as $section => $message) {
    echo "section error (allowed, but look at it): $section — $message\n";
}

if ($problems !== []) {
    echo "FAIL\n - " . implode("\n - ", $problems) . "\n";
    exit(1);
}

$c = $report['sections']['content'] ?? [];
printf("OK — %d posts scanned, %d block types, %d shortcodes, %s bytes of JSON\n", $c['posts_scanned'] ?? 0, count($c['blocks']['blocks'] ?? []), count($c['shortcodes']['tags'] ?? []), number_format(strlen($json)));
exit(0);
