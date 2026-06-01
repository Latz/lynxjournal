<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Allow plain username:password Basic Auth for E2E tests in wp-env.
 *
 * WordPress authenticates REST API requests via `determine_current_user`.
 * The built-in handler (`wp_validate_application_password`) only accepts
 * Application Passwords (24-char format). This mu-plugin falls back to
 * wp_authenticate() so regular admin credentials work in tests.
 *
 * Also clears any Application Password error set in rest_authentication_errors
 * (priority 90) when we can authenticate the request ourselves.
 *
 * NOT for production — excluded by .distignore.
 */

/**
 * Returns user ID from Basic Auth headers, or null if unavailable.
 */
function linkdigest_basic_auth_user_id(): ?int {
    $username = isset($_SERVER['PHP_AUTH_USER']) ? sanitize_text_field(wp_unslash($_SERVER['PHP_AUTH_USER'])) : null;
    $password = isset($_SERVER['PHP_AUTH_PW']) ? sanitize_text_field(wp_unslash($_SERVER['PHP_AUTH_PW'])) : null;

    if ( empty( $username ) || empty( $password ) ) {
        return null;
    }

    $user = wp_authenticate( $username, $password );
    return is_wp_error( $user ) ? null : $user->ID;
}

// Hook after Application Passwords (priority 20) so we only run when it passes.
add_filter( 'determine_current_user', function ( $user_id ) {
    if ( ! empty( $user_id ) ) {
        return $user_id; // Already authenticated (cookie or app password).
    }
    return linkdigest_basic_auth_user_id() ?? $user_id;
}, 30 );

// Clear any Application Password WP_Error from rest_authentication_errors.
// Application Passwords hooks at priority 90; we clean up at priority 99.
add_filter( 'rest_authentication_errors', function ( $result ) {
    if ( ! is_wp_error( $result ) ) {
        return $result;
    }
    $user_id = linkdigest_basic_auth_user_id();
    if ( $user_id ) {
        wp_set_current_user( $user_id );
        return null;
    }
    return $result;
}, 99 );
