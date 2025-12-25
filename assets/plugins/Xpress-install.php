<?php
/**
 * Plugin Name: Xpress – Install & Activate
 * Description: Auto installs & activates Xpress plugin.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

define('XPRESS_INSTALL_OPTION', 'xpress_auto_activated');

add_action('init', 'xpress_auto_install_and_activate', 20);

function xpress_auto_install_and_activate()
{
    // Already activated before
    if (get_option(XPRESS_INSTALL_OPTION)) {
        return;
    }

    // Plugin main file
    $plugin = 'xpress/xpress.php';

    // Make sure plugin functions are available
    if (!function_exists('activate_plugin')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    // Activate plugin if not active
    if (!is_plugin_active($plugin)) {
        activate_plugin($plugin, '', false, true);
    }

    // Mark as done
    update_option(XPRESS_INSTALL_OPTION, 1);
}
