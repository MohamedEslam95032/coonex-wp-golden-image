<?php
/**
 * Plugin Name: Coonex JWT SSO
 * Description: JWT-based SSO for Coonex (passwordless, stable, no login loop)
 * Version: 2.1.0
 * Author: Coonex
 */

if (!defined('ABSPATH')) {
    exit;
}

function coonex_jwt_sso() {

    // شغّل SSO فقط لو token موجود
    if (!isset($_GET['token'])) {
        return;
    }

    // لو المستخدم داخل خلاص
    if (is_user_logged_in()) {
        return;
    }

    $jwt    = trim($_GET['token']);
    $secret = getenv('COONEX_SSO_SECRET');

    if (!$secret) {
        wp_die('SSO secret not configured');
    }

    // Validate JWT
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        wp_die('Invalid token structure');
    }

    [$header, $payload, $signature] = $parts;

    $expected = rtrim(strtr(
        base64_encode(
            hash_hmac('sha256', "$header.$payload", $secret, true)
        ),
        '+/',
        '-_'
    ), '=');

    if (!hash_equals($expected, $signature)) {
        wp_die('Invalid SSO signature');
    }

    $data = json_decode(
        base64_decode(strtr($payload, '-_', '+/')),
        true
    );

    if (
        empty($data['email']) ||
        empty($data['exp']) ||
        time() > (int) $data['exp']
    ) {
        wp_die('Expired or invalid token');
    }

    $email = sanitize_email($data['email']);
    $name  = sanitize_text_field($data['name'] ?? '');

    if (!$email) {
        wp_die('Invalid email in token');
    }

    // Resolve role
    $allowed_roles = ['administrator', 'editor', 'author', 'subscriber'];
    $role = in_array($data['role'] ?? '', $allowed_roles, true)
        ? $data['role']
        : 'subscriber';

    // Find or create user
    $user = get_user_by('email', $email);

    if (!$user) {
        $username = sanitize_user(strstr($email, '@', true));

        if (username_exists($username)) {
            $username .= '_' . wp_generate_password(4, false);
        }

        $user_id = wp_create_user(
            $username,
            wp_generate_password(32),
            $email
        );

        wp_update_user([
            'ID'           => $user_id,
            'display_name' => $name,
            'role'         => $role
        ]);

        $user = get_user_by('id', $user_id);
    }

    /**
     * ✅ LOGIN (الطريقة الصح للـ SSO)
     */
    wp_clear_auth_cookie();
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true, is_ssl());

    do_action('wp_login', $user->user_login, $user);

    /**
     * Redirect يدوي
     */
    wp_safe_redirect(admin_url());
    exit;
}

/**
 * شغّل SSO بدري قبل wp-login
 */
add_action('init', 'coonex_jwt_sso', 1);
