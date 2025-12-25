<?php
/**
 * Plugin Name: Coonex – Disable UIXPress
 * Description: Completely disables UIXPress to prevent wp-admin crashes, JS MIME errors, and SSO conflicts.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * Disable UIXPress globally
 */
add_action('plugins_loaded', function () {

    // Official filter (if plugin exists)
    add_filter('uipress_disable', '__return_true');

    // Hard flag (extra safety)
    if (!defined('UIPRESS_DISABLE')) {
        define('UIPRESS_DISABLE', true);
    }

}, 1);

/**
 * Hide UIXPress from Plugins UI
 */
add_filter('all_plugins', function ($plugins) {
    foreach ($plugins as $key => $plugin) {
        if (
            stripos($key, 'uixpress') !== false ||
            stripos($key, 'uipress') !== false
        ) {
            unset($plugins[$key]);
        }
    }
    return $plugins;
});
