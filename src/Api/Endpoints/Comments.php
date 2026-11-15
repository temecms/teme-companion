<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Api\Endpoints;

use Teme\Companion\Api\Envelope;
use Teme\Companion\Api\Paging;

/**
 * Real reader comments and pings, in ID order so parents come first (Teme I-341). Other rows in
 * the comments table (likes, notifications, editorial notes) are not exported.
 */
final class Comments
{
    /**
     * @return array<string,mixed>
     */
    public function handle(\WP_REST_Request $request): array
    {
        global $wpdb;
        $p = Paging::read($request->get_params(), 500, 1000);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT comment_ID, comment_post_ID, comment_parent, comment_type, comment_approved, comment_author,
                    comment_author_email, comment_author_url, comment_author_IP, comment_agent, comment_date_gmt,
                    comment_content, user_id
             FROM {$wpdb->comments}
             WHERE comment_ID > %d
               AND comment_type IN ('comment', '', 'pingback', 'trackback')
               AND comment_approved IN ('0', '1', 'spam', 'trash', 'post-trashed')
             ORDER BY comment_ID ASC LIMIT %d",
            $p['after_id'],
            $p['limit']
        ), ARRAY_A);

        $items = [];
        foreach ((array) $rows as $row) {
            $items[] = [
                'id' => (int) $row['comment_ID'],
                'post_id' => (int) $row['comment_post_ID'],
                'parent' => (int) $row['comment_parent'],
                'type' => $row['comment_type'] === '' ? 'comment' : $row['comment_type'],
                'status' => $row['comment_approved'],
                'author_name' => $row['comment_author'],
                'author_email' => $row['comment_author_email'],
                'author_url' => $row['comment_author_url'],
                'author_ip' => $row['comment_author_IP'],
                'user_agent' => $row['comment_agent'],
                'date_gmt' => $row['comment_date_gmt'],
                'content' => $row['comment_content'],
                'user_id' => (int) $row['user_id'],
            ];
        }

        return Envelope::make($items, $p['limit'], static fn (array $i): int => (int) $i['id']);
    }
}
