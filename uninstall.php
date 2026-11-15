<?php
/**
 * Removes the plugin's two options.
 *
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('teme_companion_state');
delete_option('teme_companion_report');
