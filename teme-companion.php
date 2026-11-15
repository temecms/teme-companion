<?php
/**
 * Plugin Name:       Teme Companion
 * Description:       A read-only site report plus a read-only export API for moving your site to Teme. The report stays local until you download it; the export API — administrators only, HTTPS only — is how Teme's importer reads your content, including the personal data it needs to migrate.
 * Version:           0.2.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            Teme
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       teme-companion
 *
 * @package Teme\Companion
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('TEME_COMPANION_VERSION', '0.2.0');
define('TEME_COMPANION_FILE', __FILE__);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Teme\\Companion\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

add_action('plugins_loaded', static function (): void {
    \Teme\Companion\Plugin::boot();
});
