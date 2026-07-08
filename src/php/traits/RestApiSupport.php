<?php

declare(strict_types=1);

trait LynxJournal_RestApi_Support {

    /**
     * Validate link data for REST submission.
     *
     * @since 1.0.0
     * @param string $title The link title.
     * @param string|null $url The link URL.
     * @return bool|\WP_Error True if valid, WP_Error otherwise.
     */
    private function validateRestLink(string $title, ?string $url): bool|\WP_Error {
        if (empty($title)) {
            return new \WP_Error('missing_title', __('Title is required.', 'lynx-journal'), array('status' => 400));
        }

        if (!empty($url)) {
            $existing = get_posts(array(
                'post_type'   => 'lynx-journal',
                'post_status' => 'any',
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                'meta_query'  => array(
                    array(
                        'key'   => '_lynxjournal_url',
                        'value' => $url,
                    ),
                ),
                'numberposts' => 1,
                'fields'      => 'ids',
            ));
            if (!empty($existing)) {
                return new \WP_Error('duplicate_url', __('This URL has already been saved.', 'lynx-journal'), array('status' => 409));
            }
        }

        return true;
    }

    /**
     * Resolve or create lynxjournal categories from names.
     *
     * @since 1.0.0
     * @param array $categories Array of category names.
     * @return array Array of category term IDs.
     */
    private function resolveOrCreateCategories(array $categories): array {
        $ids = array();
        foreach ($categories as $cat_name) {
            $term = get_term_by('name', $cat_name, 'lynxjournal_category');
            if (!$term) {
                $result = wp_insert_term($cat_name, 'lynxjournal_category');
                if (!is_wp_error($result)) {
                    $ids[] = $result['term_id'];
                }
            } else {
                $ids[] = $term->term_id;
            }
        }
        return $ids;
    }

    /**
     * Apply categories and tags to a link post.
     *
     * @since 1.0.0
     * @param int $post_id The link post ID.
     * @param mixed $categories Array of category names or IDs.
     * @param mixed $tags Comma-separated tag names or array.
     * @return void
     */
    private function applyLinkTaxonomies(int $post_id, mixed $categories, mixed $tags): void {
        if (!empty($categories) && is_array($categories)) {
            $ids = $this->resolveOrCreateCategories($categories);
            if (!empty($ids)) {
                wp_set_object_terms($post_id, $ids, 'lynxjournal_category');
            }
        }
        if (!empty($tags)) {
            $tag_names = array_map('trim', explode(',', $tags));
            wp_set_object_terms($post_id, $tag_names, 'lynxjournal_tag');
        }
    }

    /**
     * Add CORS headers for Chrome extension requests.
     *
     * @since 1.0.0
     * @param bool $served Whether the request was served.
     * @return bool The served status.
     */
    public function addCorsHeaders(bool $served): bool {
        $origin = get_http_origin();
        if ($this->isFromChromeExtension($origin)) {
            $this->setCorsOriginHeaders($origin);
            header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE');
            header('Access-Control-Allow-Headers: Content-Type, X-LynxJournal-API-Key, X-WP-Nonce, Authorization');
        }
        return $served;
    }

    /**
     * Handle preflight OPTIONS requests from the Chrome extension.
     *
     * @since 1.0.0
     * @return void
     */
    public function handlePreflight(): void {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if (!$request_uri || !str_contains($request_uri, '/wp-json/')) {
            return;
        }
        if (isset($_SERVER['REQUEST_METHOD']) && sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) === 'OPTIONS') {
            $origin = get_http_origin();
            if ($this->isFromChromeExtension($origin)) {
                $this->setCorsOriginHeaders($origin);
                header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, X-LynxJournal-API-Key, X-WP-Nonce, Authorization');
                header('Access-Control-Max-Age: 86400');
                exit;
            }
        }
    }

    /**
     * Check if a request origin is from the Chrome extension.
     *
     * @since 1.0.0
     * @param string $origin The request origin.
     * @return bool True if from Chrome extension.
     */
    private function isFromChromeExtension( string $origin ): bool {
        return str_starts_with( $origin, 'chrome-extension://' );
    }

    /**
     * Set CORS origin headers for a specific origin.
     *
     * @since 1.0.0
     * @param string $origin The request origin.
     * @return void
     */
    private function setCorsOriginHeaders( string $origin ): void {
        header( 'Access-Control-Allow-Origin: ' . $origin );
        header( 'Access-Control-Allow-Credentials: true' );
    }
}
