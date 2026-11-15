<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Api\Endpoints;

use Teme\Companion\Api\Envelope;
use Teme\Companion\Api\Paging;
use Teme\Companion\Collectors\Sql;
use Teme\Companion\Support\Safe;

/**
 * Redirect rules from the stores WordPress sites commonly use. Old slugs and old dates travel with
 * each content item instead (Content). Plugin data is unserialized without objects.
 */
final class Redirects
{
    /**
     * @return array<string,mixed>
     */
    public function handle(\WP_REST_Request $request): array
    {
        $source = (string) $request->get_param('source');
        $p = Paging::read($request->get_params(), 500, 1000);

        switch ($source) {
            case 'redirection':
                return $this->redirection($p);
            case 'rank_math':
                return $this->rankMath($p);
            case 'yoast_premium':
                return ['api' => Envelope::API, 'plain' => $this->option('wpseo-premium-redirects-base'), 'regex' => $this->option('wpseo-premium-redirects-regex')];
            case 'safe_redirect_manager':
                return $this->safeRedirectManager($p);
            case 'simple_301':
                return ['api' => Envelope::API, 'rules' => $this->option('301_redirects')];
            default:
                return ['api' => Envelope::API, 'sources' => $this->sources()];
        }
    }

    /**
     * @return array<string,int|null>
     */
    private function sources(): array
    {
        global $wpdb;
        $count = static function (string $table) use ($wpdb): ?int {
            $name = $wpdb->prefix . $table;
            return Sql::tableExists($name) ? (int) $wpdb->get_var("SELECT COUNT(*) FROM `$name`") : null;
        };

        return [
            'redirection' => $count('redirection_items'),
            'rank_math' => $count('rank_math_redirections'),
            'yoast_premium' => is_array(get_option('wpseo-premium-redirects-base', null)) ? count((array) get_option('wpseo-premium-redirects-base')) : null,
            'safe_redirect_manager' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'redirect_rule'") ?: null,
            'simple_301' => is_array(get_option('301_redirects', null)) ? count((array) get_option('301_redirects')) : null,
        ];
    }

    /**
     * @param array{after_id:int, limit:int, modified_after:?string} $p
     * @return array<string,mixed>
     */
    private function redirection(array $p): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'redirection_items';
        if (! Sql::tableExists($table)) {
            return Envelope::make([], $p['limit'], static fn (array $i): int => (int) $i['id']);
        }
        $columns = array_map('strval', (array) $wpdb->get_col("SHOW COLUMNS FROM `$table`"));
        $wanted = array_values(array_intersect(['id', 'url', 'match_url', 'match_type', 'regex', 'action_type', 'action_code', 'action_data', 'status', 'group_id', 'position', 'title'], $columns));
        $rows = $wpdb->get_results($wpdb->prepare('SELECT `' . implode('`, `', $wanted) . "` FROM `$table` WHERE id > %d ORDER BY id ASC LIMIT %d", $p['after_id'], $p['limit']), ARRAY_A);

        $items = [];
        foreach ((array) $rows as $row) {
            if (isset($row['action_data']) && is_string($row['action_data'])) {
                $row['action_data'] = Safe::unserialize($row['action_data']);
            }
            $row['id'] = (int) $row['id'];
            $items[] = $row;
        }

        return Envelope::make($items, $p['limit'], static fn (array $i): int => (int) $i['id']);
    }

    /**
     * @param array{after_id:int, limit:int, modified_after:?string} $p
     * @return array<string,mixed>
     */
    private function rankMath(array $p): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rank_math_redirections';
        if (! Sql::tableExists($table)) {
            return Envelope::make([], $p['limit'], static fn (array $i): int => (int) $i['id']);
        }
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, sources, url_to, header_code, status FROM `$table` WHERE id > %d ORDER BY id ASC LIMIT %d", $p['after_id'], $p['limit']), ARRAY_A);

        $items = [];
        foreach ((array) $rows as $row) {
            $items[] = [
                'id' => (int) $row['id'],
                'sources' => Safe::unserialize((string) $row['sources']),
                'url_to' => $row['url_to'],
                'header_code' => (int) $row['header_code'],
                'status' => $row['status'],
            ];
        }

        return Envelope::make($items, $p['limit'], static fn (array $i): int => (int) $i['id']);
    }

    /**
     * @param array{after_id:int, limit:int, modified_after:?string} $p
     * @return array<string,mixed>
     */
    private function safeRedirectManager(array $p): array
    {
        global $wpdb;
        $ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'redirect_rule' AND ID > %d ORDER BY ID ASC LIMIT %d", $p['after_id'], $p['limit'])));

        $items = [];
        foreach ($ids as $id) {
            $items[] = [
                'id' => $id,
                'status' => get_post_status($id),
                'from' => (string) get_post_meta($id, '_redirect_rule_from', true),
                'to' => (string) get_post_meta($id, '_redirect_rule_to', true),
                'code' => (int) get_post_meta($id, '_redirect_rule_status_code', true),
                'regex' => (bool) get_post_meta($id, '_redirect_rule_from_regex', true),
            ];
        }

        return Envelope::make($items, $p['limit'], static fn (array $i): int => (int) $i['id']);
    }

    /**
     * @return mixed
     */
    private function option(string $name)
    {
        $value = get_option($name, null);

        return is_array($value) ? $value : null;
    }
}
