<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Admin;

use Teme\Companion\Report\Runner;
use Teme\Companion\Report\Store;

/**
 * Tools › Teme site report: start a scan, watch it progress, read the summary, download or delete.
 */
final class Page
{
    private const SLUG = 'teme-site-report';

    private const NONCE = 'teme_companion';

    private Store $store;

    private Runner $runner;

    public function __construct(Store $store, Runner $runner)
    {
        $this->store = $store;
        $this->runner = $runner;
    }

    /**
     * Registers the admin page and AJAX/post actions.
     *
     * @return void
     */
    public function register(): void
    {
        add_action('admin_menu', function (): void {
            add_management_page('Teme site report', 'Teme site report', 'manage_options', self::SLUG, [$this, 'render']);
        });
        add_action('wp_ajax_teme_companion_begin', [$this, 'begin']);
        add_action('wp_ajax_teme_companion_step', [$this, 'step']);
        add_action('admin_post_teme_companion_download', [$this, 'download']);
        add_action('admin_post_teme_companion_delete', [$this, 'delete']);
    }

    /**
     * AJAX action: begins the scan.
     *
     * @return void
     */
    public function begin(): void
    {
        $this->guardAjax();
        try {
            $this->runner->begin(! empty($_POST['examples']));
            wp_send_json_success(['started' => true]);
        } catch (\Throwable $e) {
            error_log('Teme Companion: begin() failed: ' . get_class($e) . ': ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX action: runs the next step.
     *
     * @return void
     */
    public function step(): void
    {
        $this->guardAjax();

        $responded = false;
        register_shutdown_function(static function () use (&$responded): void {
            if ($responded) {
                return;
            }
            // A true fatal may already have printed its own message into the buffer opened
            // below (PHP does this itself when display_errors is on, independently of
            // WP_DEBUG) — discard every open buffer level so that text never reaches the
            // browser ahead of our own clean JSON.
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $error = error_get_last();
            $message = $error !== null
                ? $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']
                : 'The scan stopped unexpectedly.';
            if (! headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo (string) wp_json_encode(['success' => false, 'data' => ['message' => $message]]);
        });

        ob_start();
        try {
            $result = $this->runner->step();
            $responded = true;
            ob_end_clean();
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            $responded = true;
            ob_end_clean();
            error_log('Teme Companion: step() failed: ' . get_class($e) . ': ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Admin-post action: downloads the report as JSON.
     *
     * @return void
     */
    public function download(): void
    {
        $this->guard();
        $report = $this->store->report();
        if ($report === null) {
            wp_die('There is no report yet.');
        }
        $host = (string) preg_replace('/[^a-z0-9.-]/', '-', strtolower((string) parse_url(home_url(), PHP_URL_HOST)));
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="teme-site-report-' . $host . '-' . gmdate('Ymd-His') . '.json"');
        echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Admin-post action: deletes the report.
     *
     * @return void
     */
    public function delete(): void
    {
        $this->guard();
        $this->store->clear();
        wp_safe_redirect(admin_url('tools.php?page=' . self::SLUG));
        exit;
    }

    /**
     * Renders the admin page.
     *
     * @return void
     */
    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $report = $this->store->report();
        $nonce = wp_create_nonce(self::NONCE);
        $ajax = admin_url('admin-ajax.php');
        $post = admin_url('admin-post.php');
        ?>
        <div class="wrap">
            <h1>Teme site report</h1>
            <p>This scans your site and writes a report of everything a move to Teme must handle: versions, settings, content types, which blocks and shortcodes your posts use, media, menus, SEO data, redirects and plugins.</p>
            <p><strong>It never collects</strong> email addresses, names, IP addresses, passwords or comment text, and it sends nothing anywhere. The report stays on this site until you download it. Read it before you share it.</p>

            <p><label><input type="checkbox" id="teme-examples" checked> Include short examples of block and shortcode markup (up to 400 characters each, email and IP addresses removed)</label></p>
            <p><button type="button" class="button button-primary" id="teme-start">Scan this site</button></p>
            <div id="teme-progress" hidden>
                <progress id="teme-bar" max="1" value="0" style="width: 100%; max-width: 480px;"></progress>
                <p id="teme-status" aria-live="polite"></p>
            </div>

            <?php if ($report !== null) : ?>
                <h2>Latest report</h2>
                <p>
                    <a class="button" href="<?php echo esc_url(wp_nonce_url($post . '?action=teme_companion_download', self::NONCE)); ?>">Download JSON</a>
                    <a class="button" href="<?php echo esc_url(wp_nonce_url($post . '?action=teme_companion_delete', self::NONCE)); ?>">Delete report</a>
                </p>
                <?php Summary::render($report); ?>
            <?php endif; ?>

            <h2>Import access</h2>
            <p>The export API is available at <code><?php echo esc_html(rest_url('teme/v1/')); ?></code></p>
            <p>Teme connects using <strong>an application password</strong> created in Users › Profile › Application Passwords, specifically for this import. Create one there, use it once, then revoke it when the migration is complete.</p>
            <p>Only administrators can use the API, and only over HTTPS.</p>
        </div>
        <script>
        (function () {
            var ajax = <?php echo wp_json_encode($ajax); ?>, nonce = <?php echo wp_json_encode($nonce); ?>;
            var start = document.getElementById('teme-start');
            var STEP_DELAY_MS = 500;
            var MAX_RETRIES = 6;
            var MAX_BACKOFF_MS = 10000;

            function call(action, extra) {
                var body = new URLSearchParams(Object.assign({ action: action, _wpnonce: nonce }, extra || {}));
                return fetch(ajax, { method: 'POST', credentials: 'same-origin', body: body }).then(function (r) { return r.json(); });
            }
            function wait(ms) {
                return new Promise(function (resolve) { setTimeout(resolve, ms); });
            }
            // A busy host (a caching layer or PHP-FPM pool under load from real site traffic)
            // can intermittently reject one of these requests before it ever reaches the plugin,
            // returning something that isn't JSON. That's a transient condition, not a real
            // failure, so retry with backoff before giving up.
            function callWithRetry(action, extra, attempt) {
                attempt = attempt || 0;
                return call(action, extra).catch(function (e) {
                    if (attempt >= MAX_RETRIES) { throw e; }
                    var backoff = Math.min(STEP_DELAY_MS * Math.pow(2, attempt), MAX_BACKOFF_MS);
                    document.getElementById('teme-status').textContent = 'Reading… retrying after a hiccup (' + (attempt + 1) + '/' + MAX_RETRIES + ')';
                    return wait(backoff).then(function () { return callWithRetry(action, extra, attempt + 1); });
                });
            }
            function loop() {
                wait(STEP_DELAY_MS).then(function () {
                    return callWithRetry('teme_companion_step');
                }).then(function (r) {
                    if (!r.success) { document.getElementById('teme-status').textContent = 'Stopped: ' + JSON.stringify(r.data); start.disabled = false; return; }
                    document.getElementById('teme-bar').value = r.data.progress;
                    document.getElementById('teme-status').textContent = 'Reading: ' + r.data.section + ' (' + Math.round(r.data.progress * 100) + '%)';
                    if (r.data.done) { window.location.reload(); } else { loop(); }
                }).catch(function (e) { document.getElementById('teme-status').textContent = 'Stopped: ' + e; start.disabled = false; });
            }
            start.addEventListener('click', function () {
                start.disabled = true;
                document.getElementById('teme-progress').hidden = false;
                call('teme_companion_begin', { examples: document.getElementById('teme-examples').checked ? '1' : '' }).then(loop);
            });
        })();
        </script>
        <?php
    }

    /**
     * Checks manage_options capability and nonce for the admin-post (download/delete) actions.
     * A failure here is a full-page navigation, so dying with an HTML page is the right response.
     *
     * @return void
     */
    private function guard(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Not allowed.', 403);
        }
        check_admin_referer(self::NONCE);
    }

    /**
     * Checks manage_options capability and nonce for the AJAX (begin/step) actions. A failure here
     * must stay JSON — check_admin_referer()'s HTML die page is exactly the malformed response that
     * broke the client-side fetch().then(r => r.json()) loop with a nonce that expired mid-scan.
     *
     * @return void
     */
    private function guardAjax(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Not allowed.']);
        }
        if (! check_ajax_referer(self::NONCE, '_wpnonce', false)) {
            wp_send_json_error(['message' => 'Your session has expired. Reload the page and try again.']);
        }
    }
}
