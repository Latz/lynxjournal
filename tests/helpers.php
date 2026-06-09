<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared test helpers — available in every Unit test closure.
 *
 * Included from bootstrap-unit.php AFTER stubs are loaded so that
 * WP_Post and WP_REST_Request are already defined.
 */

const LYNXJOURNAL_URL_EXAMPLE   = 'https://example.com';
const LYNXJOURNAL_TITLE_MY_LINK = 'My Link';

/**
 * Build a concrete WP_REST_Request stub with preset params and headers.
 */
function lynxjournal_make_request(array $params = [], array $headers = []): WP_REST_Request
{
    $request = new WP_REST_Request();
    foreach ($params  as $k => $v) { $request[$k] = $v; }
    foreach ($headers as $k => $v) { $request->set_header($k, $v); }
    return $request;
}

/**
 * Build a minimal WP_Post object.
 */
function lynxjournal_make_post(int $id, string $title, string $type = 'lynx-journal', string $content = ''): WP_Post
{
    $post               = new WP_Post();
    $post->ID           = $id;
    $post->post_title   = $title;
    $post->post_type    = $type;
    $post->post_content = $content;
    return $post;
}
