<?php
/**
 * Plugin Name: Xpress Core Installer
 * Description: Auto-activates, hides, and enforces uiXpress as an always-on admin engine.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * CONFIG
 */
define('XPRESS_OPTION', 'xpress_uixpress_enforced');
define('XPRESS_PLUGIN_FILE', 'xpress/uixpress.php');

/**
 * 1️⃣ Auto-activate uiXpress
 * (same context as manual activation)
 */
add_action('current_screen', function () {

    if (get_option(XPRESS_OPTION)) {
        return;
    }

    if (!is_admin()) {
        return;
    }

    if (!function_exists('get_current_screen')) {
        return;
    }

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

    update_option(XPRESS_OPTION, 1);
});

/**
 * 2️⃣ Self-healing (re-activate if someone disables it)
 */
add_action('admin_init', function () {

    $plugin_path = WP_PLUGIN_DIR . '/' . XPRESS_PLUGIN_FILE;
    if (!file_exists($plugin_path)) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    if (!is_plugin_active(XPRESS_PLUGIN_FILE)) {
        activate_plugin(XPRESS_PLUGIN_FILE, '', false, true);
    }
});

/**
 * 3️⃣ Hide uiXpress from Plugins list
 */
add_filter('all_plugins', function ($plugins) {
    unset($plugins[XPRESS_PLUGIN_FILE]);
    return $plugins;
});

/**
 * 4️⃣ Remove deactivate & delete actions (extra safety)
 */
add_filter('plugin_action_links', function ($actions, $plugin_file) {
    if ($plugin_file === XPRESS_PLUGIN_FILE) {
        unset($actions['deactivate'], $actions['delete']);
    }
    return $actions;
}, 10, 2);

/**
 * 5️⃣ Hide uiXpress menu from admin sidebar
 */
add_action('admin_menu', function () {

    // Common slugs used by uiXpress
    remove_menu_page('uipress');
    remove_menu_page('uixpress');
    remove_menu_page('ui-xpress');

}, 999);

/**
 * 6️⃣ Block direct access to uiXpress pages
 */
add_action('admin_init', function () {
    if (
        isset($_GET['page']) &&
        in_array($_GET['page'], ['uipress', 'uixpress', 'ui-xpress'], true)
    ) {
        wp_die('Access denied');
    }
});
