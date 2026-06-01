<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Auto-activate the LinkDigest plugin for testing.
 * Also ensures permalinks are set up for REST API.
 */

// Activate the plugin if not already active
if (!function_exists('is_plugin_active')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

if (!is_plugin_active('linkdigest/linkdigest.php')) {
    activate_plugin('linkdigest/linkdigest.php');
}

// Ensure permalinks are set to pretty URLs (required for REST API)
$permalink_structure = get_option('permalink_structure');
if (empty($permalink_structure)) {
    update_option('permalink_structure', '/%postname%/');
    flush_rewrite_rules();
}
