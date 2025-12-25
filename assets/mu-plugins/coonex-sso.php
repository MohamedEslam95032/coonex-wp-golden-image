<?php
/**
 * Plugin Name: Coonex JWT SSO
 */

add_action('init', function () {

    if (!isset($_GET['token'])) {
        return;
    }

    $jwt = trim($_GET['token']);
    $secret = getenv('COONEX_SSO_SECRET');

    error_log('SSO DEBUG: Secret loaded = ' . ($secret ? 'YES' : 'NO'));
    error_log('SSO DEBUG: JWT = ' . $jwt);

    if (!$secret) {
        wp_die('SSO secret not configured');
    }

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

    error_log('SSO DEBUG: Expected = ' . $expected);
    error_log('SSO DEBUG: Provided = ' . $signature);

    if (!hash_equals($expected, $signature)) {
        wp_die('Invalid SSO signature');
    }

    $data = json_decode(base64_decode($payload), true);

    if (
        empty($data['email']) ||
        empty($data['exp']) ||
        time() > $data['exp']
    ) {
        wp_die('Expired or invalid token');
    }

    $email = sanitize_email($data['email']);
    $name  = sanitize_text_field($data['name'] ?? '');
    $role  = sanitize_key($data['role'] ?? 'subscriber');

    $user = get_user_by('email', $email);

    if (!$user) {
        $username = sanitize_user(strstr($email, '@', true));

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

    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);
    do_action('wp_login', $user->user_login, $user);

    wp_safe_redirect(admin_url());
    exit;
});

/**
 * Disable native wp-login
 */
add_action('login_init', function () {
    if (!isset($_GET['token'])) {
        wp_die('Login via Coonex only');
    }
});
