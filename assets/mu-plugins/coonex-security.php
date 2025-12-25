<?php
/**
 * Plugin Name: Coonex Security – Navigation Lock
 * Description: Hide theme editor and user profile from all navigation and block direct access
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ==================================================
 * 1) THEME FILE EDITOR
 * ==================================================
 */

/* Hide from left admin menu */
add_action('admin_menu', function () {
    remove_submenu_page('themes.php', 'theme-editor.php');
}, 999);

/* Hide from admin bar (top navigation) */
add_action('admin_bar_menu', function ($wp_admin_bar) {
    $wp_admin_bar->remove_node('theme-editor');
}, 999);

/* Block direct access */
add_action('admin_init', function () {
    if (strpos($_SERVER['PHP_SELF'], 'theme-editor.php') !== false) {
        wp_die(__('Theme file editor is disabled on this platform.'));
    }
});

/* Native WP safeguard */
if (!defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}

/**
 * ==================================================
 * 2) USER PROFILE / EDIT PROFILE
 * ==================================================
 */

/* Hide from left admin menu */
add_action('admin_menu', function () {
    remove_menu_page('profile.php');
    remove_submenu_page('users.php', 'profile.php');
}, 999);

/* Hide from admin bar (top navigation) */
add_action('admin_bar_menu', function ($wp_admin_bar) {
    $wp_admin_bar->remove_node('my-account');           // User menu
    $wp_admin_bar->remove_node('edit-profile');         // Some themes/plugins add this
}, 999);

/* Block direct access */
add_action('admin_init', function () {

    $blocked = ['profile.php', 'user-edit.php'];
    $current = basename($_SERVER['PHP_SELF']);

    if (in_array($current, $blocked, true)) {
        wp_die(__('User profile editing is disabled on this platform.'));
    }
});

/* Block REST API profile updates */
add_filter('rest_authentication_errors', function ($result) {

    if (!is_user_logged_in()) {
        return $result;
    }

    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/wp/v2/users') !== false) {
        return new WP_Error(
            'coonex_profile_blocked',
            __('User profile modification is disabled on this platform.'),
            ['status' => 403]
        );
    }

    return $result;
});
