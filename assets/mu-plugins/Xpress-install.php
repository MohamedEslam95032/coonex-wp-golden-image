<?php
/**
 * Plugin Name: Xpress – Auto Install
 */

defined('ABSPATH') || exit;

define('XPRESS_INSTALL_OPTION', 'xpress_auto_activated');
define('XPRESS_PLUGIN_FILE', 'xpress/uixpress.php');

add_action('current_screen', function () {

    // Already activated
    if (get_option(XPRESS_INSTALL_OPTION)) {
        return;
    }

    // لازم نكون في plugins screen
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'plugins') {
        return;
    }

    $plugin_path = WP_PLUGIN_DIR . '/' . XPRESS_PLUGIN_FILE;
    if (!file_exists($plugin_path)) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    if (!is_plugin_active(XPRESS_PLUGIN_FILE)) {
        activate_plugin(XPRESS_PLUGIN_FILE);
    }

    update_option(XPRESS_INSTALL_OPTION, 1);
});
