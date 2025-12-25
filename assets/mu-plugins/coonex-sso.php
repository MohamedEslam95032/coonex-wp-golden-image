<?php
/**
 * Plugin Name: Coonex JWT SSO
 * Description: Secure WordPress login via Coonex using JWT (SSO only).
 * Version: 1.0.0
 * Author: Coonex
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle SSO login ONLY on wp-login.php
 */
add_action('login_init', 'coonex_handle_sso');

function coonex_handle_sso() {

    // لو المستخدم داخل بالفعل، سيبه
    if (is_user_logged_in()) {
        return;
    }

    // اسمح لـ WP-CLI
    if (defined('WP_CLI') && WP_CLI) {
        return;
    }

    // امنع أي دخول من غير token
    if (!isset($_GET['token'])) {
        wp_die('Login via Coonex only');
    }

    $jwt = trim($_GET['token']);
    $secret = getenv('COONEX_SSO_SECRET');

    if (!$secret) {
        wp_die('SSO secret not configured');
    }

    // --------------------------------
    // JWT validation (HS256)
    // --------------------------------
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        wp_die('Invalid token structure');
    }

    [$header, $payload, $signature] = $parts;

    $expected = rtrim(strtr(
        base64_encode(hash_hmac(
            'sha256',
            "$header.$payload",
            $secret,
            true
        )),
        '+/',
        '-_'
    ), '=');

    if (!hash_equals($expected, $signature)) {
        wp_die('Invalid SSO signature');
    }

    // Decode payload safely
    $data = json_decode(coonex_base64url_decode($payload), true);

    if (!$data || empty($data['email'])) {
        wp_die('Invalid SSO payload');
    }

    // --------------------------------
    // Token expiry check (optional but recommended)
    // --------------------------------
    if (!empty($data['exp']) && time() > intval($data['exp'])) {
        wp_die('SSO token expired');
    }

    // --------------------------------
    // User handling
    // --------------------------------
    $email = sanitize_email($data['email']);
    $name  = !empty($data['name']) ? sanitize_text_field($data['name']) : '';
    $role  = !empty($data['role']) ? sanitize_text_field($data['role']) : 'subscriber';

    $user = get_user_by('email', $email);

    if (!$user) {
        // Create user if not exists
        $user_id = wp_create_user(
            $email,
            wp_generate_password(32),
            $email
        );

        if (is_wp_error($user_id)) {
            wp_die('Failed to create user');
        }

        wp_update_user([
            'ID'           => $user_id,
            'display_name' => $name ?: $email,
            'role'         => $role,
        ]);

        $user = get_user_by('id', $user_id);
    }

    // --------------------------------
    // Login user
    // --------------------------------
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);
    do_action('wp_login', $user->user_login, $user);

    // Redirect to admin
    wp_redirect(admin_url());
    exit;
}

/**
 * Base64 URL decode helper
 */
function coonex_base64url_decode($data) {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}
