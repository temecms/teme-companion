<?php
/**
 * Calls every export route on a real site and checks shapes and the access rules.
 *
 * Usage (PowerShell):
 *   $env:TEME_URL = "https://example.test"; $env:TEME_USER = "admin"; $env:TEME_APP_PASSWORD = "xxxx xxxx ..."
 *   php tests/smoke/check-api.php
 *
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

$base = rtrim((string) getenv('TEME_URL'), '/') . '/wp-json/teme/v1/';
$auth = base64_encode(getenv('TEME_USER') . ':' . getenv('TEME_APP_PASSWORD'));
$insecure = getenv('TEME_INSECURE') === '1';

if (getenv('TEME_URL') === false || getenv('TEME_USER') === false || getenv('TEME_APP_PASSWORD') === false) {
    fwrite(STDERR, "Set TEME_URL, TEME_USER, and TEME_APP_PASSWORD as environment variables before running this script.\n");
    exit(1);
}

/**
 * @return array{0:int,1:mixed,2:string}
 */
function call(string $url, ?string $auth, bool $insecure): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 120]);
    if ($auth !== null) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $auth]);
    }
    if ($insecure) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    $response = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    curl_close($ch);

    return [$code, json_decode($body, true), $headers];
}

$problems = [];

[$code, $probe] = call($base . 'probe', null, $insecure);
if ($code !== 200 || ($probe['name'] ?? '') !== 'teme-companion') {
    $problems[] = "probe: expected 200 and the plugin's name, got $code";
}
if (isset($probe['wordpress']) || isset($probe['counts'])) {
    $problems[] = 'probe reveals more than name and version';
}

[$code] = call($base . 'users', null, $insecure);
if ($code !== 401) {
    $problems[] = "users without credentials: expected 401, got $code";
}

$lists = ['users' => 'id', 'terms' => 'id', 'media' => 'id', 'content' => 'id', 'comments' => 'id'];
$checkedNoStore = false;
foreach ($lists as $route => $key) {
    [$code, $page, $headers] = call($base . $route . '?limit=3', $auth, $insecure);
    if ($code !== 200 || ! isset($page['api'], $page['items'], $page['limit']) || ! array_key_exists('next', $page)) {
        $problems[] = "$route: bad envelope (HTTP $code)";
        continue;
    }
    if (! $checkedNoStore) {
        if (stripos($headers, 'no-store') === false) {
            $problems[] = "$route: response is missing a Cache-Control: no-store header";
        }
        $checkedNoStore = true;
    }
    foreach ($page['items'] as $item) {
        if (isset($item['error'])) {
            echo "$route item {$item['id']} errored (allowed, but look at it): {$item['error']}\n";
        }
    }
    if ($page['next'] !== null) {
        [$code2, $page2] = call($base . $route . '?limit=3&after_id=' . $page['next']['after_id'], $auth, $insecure);
        if ($code2 !== 200) {
            $problems[] = "$route: second page returned HTTP $code2";
        }
        $firstIds = array_column($page['items'], $key);
        $secondIds = array_column($page2['items'] ?? [], $key);
        if (array_intersect($firstIds, $secondIds) !== [] || ($secondIds !== [] && min($secondIds) <= max($firstIds))) {
            $problems[] = "$route: the second page overlaps or goes backwards";
        }
    }
}

[$code, $rendered] = call($base . 'content?limit=2&include=rendered', $auth, $insecure);
if ($code !== 200 || ! array_key_exists('rendered', $rendered['items'][0] ?? ['rendered' => null])) {
    $problems[] = 'content with include=rendered has no rendered field';
}

foreach (['settings', 'menus', 'redirects', 'urls?kind=post&limit=3', 'urls?kind=term&limit=3', 'urls?kind=author&limit=3', 'urls?kind=attachment_page&limit=3', 'urls?kind=attachment_file&limit=3', 'urls?kind=archive', 'analyze'] as $route) {
    [$code] = call($base . $route, $auth, $insecure);
    if ($code !== 200) {
        $problems[] = "$route: HTTP $code";
    }
}

[$code, $redirectSources] = call($base . 'redirects', $auth, $insecure);
if ($code === 200 && is_array($redirectSources['sources'] ?? null)) {
    foreach (array_keys($redirectSources['sources']) as $source) {
        [$code] = call($base . 'redirects?source=' . urlencode((string) $source) . '&limit=3', $auth, $insecure);
        if ($code !== 200) {
            $problems[] = "redirects?source=$source: HTTP $code";
        }
    }
}

if ($problems !== []) {
    echo "FAIL\n - " . implode("\n - ", $problems) . "\n";
    exit(1);
}
echo "OK — every route answered, paging is monotonic, and unauthenticated access is refused.\n";
exit(0);
