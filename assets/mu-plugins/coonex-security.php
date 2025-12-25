<?php
/**
 * Plugin Name: Coonex Security – UI & Profile Lock
 * Description: Disable theme editor and user profile editing for Coonex managed WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ==================================================
 * 1) Disable Theme File Editor (UI + Direct Access)
 * ==================================================
 */

// Remove Theme Editor from UI
add_action('admin_menu', function () {
    remove_submenu_page('themes.php', 'theme-editor.php');
});

// Block direct access to theme editor
add_action('admin_init', function () {
    if (strpos($_SERVER['PHP_SELF'], 'theme-editor.php') !== false) {
        wp_die(__('Theme file editor is disabled on this platform.'));
    }
});

// Extra safety (WordPress native editor switch)
if (!defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}


/**
 * ==================================================
 * 2) Disable User Profile & Edit Profile (UI + Direct)
 * ==================================================
 */

// Remove Profile from admin menu
add_action('admin_menu', function () {
    remove_menu_page('profile.php');                 // Profile
    remove_submenu_page('users.php', 'profile.php'); // Edit own profile
});

// Block direct access to profile pages
add_action('admin_init', function () {

    $blocked_pages = [
        'profile.php',
        'user-edit.php',
    ];

    $current_page = basename($_SERVER['PHP_SELF']);

    if (in_array($current_page, $blocked_pages, true)) {
        wp_die(__('User profile editing is disabled on this platform.'));
    }
});

// Block REST API user profile updates
add_filter('rest_authentication_errors', function ($result) {

    if (!is_user_logged_in()) {
        return $result;
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '';

    if (strpos($uri, '/wp/v2/users') !== false) {
        return new WP_Error(
            'coonex_user_profile_blocked',
            __('User profile modification is disabled on this platform.'),
            ['status' => 403]
        );
    }

    return $result;
});
