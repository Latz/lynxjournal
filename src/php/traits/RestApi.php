<?php

declare(strict_types=1);

trait LynxJournal_RestApi {

    /**
     * Register REST API routes for LynxJournal.
     *
     * @since 1.0.0
     * @return void
     */
    public function registerRestRoutes(): void {
        register_rest_route(LYNXJOURNAL_REST_NAMESPACE, '/add-link', array(
            'methods' => 'POST',
            'callback' => [$this, 'restAddLink'],
            'permission_callback' => [$this, 'restPermissionCheck'],
            'args' => array(
                'title' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'url' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ),
                'content' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'wp_kses_post',
                ),
                'categories' => array(
                    'required' => false,
                    'type' => 'array',
                ),
                'tags' => array(
                    'required' => false,
                    'type' => 'string',
                ),
            ),
        ));

        register_rest_route(LYNXJOURNAL_REST_NAMESPACE, '/categories', array(
            'methods' => 'GET',
            'callback' => [$this, 'restGetCategories'],
            'permission_callback' => [$this, 'restPermissionCheck'],
        ));

        register_rest_route(LYNXJOURNAL_REST_NAMESPACE, '/categories/(?P<id>\d+)', array(
            'methods'             => 'POST',
            'callback'            => [$this, 'updateCategory'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args'                => array(
                'id'          => array( 'required' => true,  'type' => 'integer' ),
                'name'        => array( 'required' => true,  'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ),
                'description' => array( 'required' => false, 'type' => 'string',  'sanitize_callback' => 'sanitize_textarea_field' ),
                'slug'        => array( 'required' => false, 'type' => 'string',  'sanitize_callback' => 'sanitize_title' ),
            ),
        ));

        register_rest_route(LYNXJOURNAL_REST_NAMESPACE, '/links/(?P<id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => [$this, 'restDeleteLink'],
            'permission_callback' => function( \WP_REST_Request $request ) {
                return current_user_can('delete_post', (int) $request->get_param('id'));
            },
        ));

        register_rest_route(LYNXJOURNAL_REST_NAMESPACE, '/schedule', array(
            array(
                'methods'             => 'GET',
                'callback'            => [$this, 'getSchedule'],
                'permission_callback' => function() { return current_user_can('edit_posts'); },
            ),
            array(
                'methods'             => 'POST',
                'callback'            => [$this, 'saveSchedule'],
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ),
        ));

        register_rest_route(LYNXJOURNAL_REST_NAMESPACE, '/schedule/run', array(
            'methods'             => 'POST',
            'callback'            => [$this, 'runScheduleNow'],
            'permission_callback' => function() { return current_user_can('manage_options'); },
        ));

        register_rest_route(LYNXJOURNAL_REST_NAMESPACE, '/schedule/preview', array(
            'methods'             => 'POST',
            'callback'            => fn() => rest_ensure_response($this->previewSchedule()),
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ));

        register_rest_route(LYNXJOURNAL_REST_NAMESPACE, '/schedule/diagnostics', array(
            'methods'             => 'GET',
            'callback'            => [$this, 'getScheduleDiagnostics'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ));

        register_rest_route(LYNXJOURNAL_REST_NAMESPACE, '/schedule/test-notification', array(
            'methods'             => 'POST',
            'callback'            => [$this, 'restTestNotification'],
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'args'                => array(
                'channel' => array('required' => true, 'type' => 'string'),
                'notify'  => array('required' => true, 'type' => 'object'),
            ),
        ));

        register_rest_route(LYNXJOURNAL_REST_NAMESPACE, '/schedule/save-notification', array(
            'methods'             => 'POST',
            'callback'            => [$this, 'restSaveNotification'],
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'args'                => array(
                'channel' => array('required' => true, 'type' => 'string'),
                'notify'  => array('required' => true, 'type' => 'object'),
            ),
        ));

        register_rest_route(LYNXJOURNAL_REST_NAMESPACE, '/schedule/dismiss-cron-notice', array(
            'methods'             => 'POST',
            'callback'            => function() {
                update_option('lynxjournal_cron_notice_dismissed', true);
                return rest_ensure_response(array('success' => true));
            },
            'permission_callback' => fn() => current_user_can('manage_options'),
        ));

        register_rest_route(LYNXJOURNAL_REST_NAMESPACE, '/api-key', array(
            'methods'             => 'GET',
            'callback'            => [$this, 'restGetApiKey'],
            'permission_callback' => function() { return current_user_can('manage_options'); },
        ));

        register_rest_route(LYNXJOURNAL_REST_NAMESPACE, '/nonce', array(
            'methods'             => 'GET',
            'callback'            => [$this, 'restGetNonce'],
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Get the current API key via REST.
     *
     * @since 1.0.0
     * @return mixed REST response with API key or WP_Error if not configured.
     */
    public function restGetApiKey(): mixed {
        $key = get_option('lynxjournal_api_key', '');
        if (empty($key)) {
            return new \WP_Error('no_key', __('No API key configured.', 'lynx-journal'), ['status' => 404]);
        }
        return rest_ensure_response(['key' => $key]);
    }

    /**
     * Return a wp_rest nonce via REST for the Chrome extension.
     *
     * @since 1.0.0
     * @return mixed REST response with nonce.
     */
    public function restGetNonce(): mixed {
        // REST cookie auth requires an existing nonce (circular). Fall back to
        // validating the WordPress auth cookie directly so the returned nonce
        // belongs to the actual logged-in user.
        if ( ! is_user_logged_in() ) {
            $user_id = wp_validate_auth_cookie( '', 'logged_in' );
            if ( $user_id ) {
                wp_set_current_user( $user_id );
            }
        }
        return rest_ensure_response(['nonce' => wp_create_nonce('wp_rest')]);
    }

    /**
     * Handle nonce requests from the Chrome extension via admin-ajax (legacy).
     *
     * @since 1.0.0
     * @return void
     */
    public function handleGetRestNonce(): void {
        $origin = get_http_origin();
        if (is_string($origin) && $this->isFromChromeExtension($origin)) {
            $this->setCorsOriginHeaders($origin);
        }
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Forbidden', 'lynx-journal'), 403);
        }
        wp_send_json_success(['nonce' => wp_create_nonce('wp_rest')]);
    }

    /**
     * Delete a link via REST API.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The REST request with link ID.
     * @return \WP_REST_Response|\WP_Error Response or error.
     */
    public function restDeleteLink(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $link_id = (int) $request['id'];
        if (get_post_type($link_id) !== 'lynx-journal') {
            return new \WP_Error('invalid_link', __('Link not found', 'lynx-journal'), array('status' => 404));
        }
        $result = wp_delete_post($link_id, true);
        if (!$result) {
            return new \WP_Error('delete_failed', __('Could not delete link', 'lynx-journal'), array('status' => 500));
        }
        delete_transient('lynxjournal_publish_stats');
        return new \WP_REST_Response(null, 204);
    }

    /**
     * Get the schedule configuration.
     *
     * @since 1.0.0
     * @return mixed REST response with schedule configuration.
     */
    public function getSchedule(): mixed {
        $default = array(
            'mode'       => 'daily',
            'recurrence' => array(
                'interval'  => 1,
                'weekdays'  => array(),
                'monthDays' => array(array('type' => 'day', 'value' => 1, 'nth' => 1, 'weekday' => 'MO')),
                'nthWeek'   => null,
            ),
            'trigger' => array('count' => 10, 'tag_id' => null, 'days' => 7),
            'times'   => array(),
        );
        $config = get_option('lynxjournal_schedule', $default);
        return rest_ensure_response($config);
    }

    /**
     * Save the schedule configuration.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The REST request with schedule data.
     * @return mixed REST response or error.
     */
    public function saveSchedule(\WP_REST_Request $request): mixed {
        $data = $request->get_json_params();
        if (empty($data)) {
            return new \WP_Error('invalid_data', __('Invalid schedule data', 'lynx-journal'), array('status' => 400));
        }
        $validated = $this->validateScheduleConfig($data);
        if (is_wp_error($validated)) {
            return $validated;
        }
        if (!array_key_exists('publishAs', $validated)) {
            $validated['publishAs'] = get_current_user_id() ?: null;
        }
        update_option('lynxjournal_schedule', $validated);
        $this->scheduleNextEvent();
        return rest_ensure_response(array('success' => true));
    }

    /**
     * Get schedule diagnostics and status information.
     *
     * @since 1.0.0
     * @return mixed REST response with diagnostics data.
     */
    public function getScheduleDiagnostics(): mixed {
        $next_ts  = wp_next_scheduled('lynxjournal_execute_schedule');
        $last_run = get_option('lynxjournal_last_run', null);
        $config   = get_option('lynxjournal_schedule', []);
        $mode     = $config['mode'] ?? 'daily';

        $response = [
            'next_scheduled'        => $next_ts ?: null,
            'last_run'              => $last_run ?: null,
            'wp_cron_disabled'      => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'cron_notice_dismissed' => (bool) get_option('lynxjournal_cron_notice_dismissed', false),
            'run_history'           => get_option('lynxjournal_run_history', []),
        ];

        if ($mode === 'count') {
            $trigger = $config['trigger'] ?? [];
            $count_threshold = (int) ($trigger['count'] ?? 10);
            $unpublished_count = count($this->getUnpublishedLinkIds());
            $links_until_post = max(0, $count_threshold - $unpublished_count);
            $response['links_until_post'] = $links_until_post;
        }

        return rest_ensure_response($response);
    }

    /**
     * Manually trigger the schedule to run immediately.
     *
     * @since 1.0.0
     * @return \WP_REST_Response|\WP_Error Response or error.
     */
    public function runScheduleNow(): \WP_REST_Response|\WP_Error {
        if (get_transient('lynxjournal_run_lock')) {
            return new \WP_Error('run_in_progress', __('A schedule run is already in progress', 'lynx-journal'), array('status' => 429));
        }
        $result = $this->executeSchedule(false);
        return rest_ensure_response($result);
    }

    /**
     * Check permissions for REST API requests.
     *
     * Supports API key authentication and standard WordPress user capabilities.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The REST request.
     * @return bool True if user has permission.
     */
    public function restPermissionCheck(\WP_REST_Request $request): bool {
        // API key first: used by the Chrome extension and external callers that can't
        // hold a WP session cookie. Falls back to standard WP capability check.
        $api_key = $request->get_header('X-LynxJournal-API-Key');
        $stored_key = get_option('lynxjournal_api_key');

        if (!empty($api_key) && !empty($stored_key) && hash_equals($stored_key, $api_key)) {
            return true;
        }

        return current_user_can('edit_posts');
    }

    /**
     * Add a new link via REST API.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The REST request with link data.
     * @return mixed REST response with link creation result.
     */
    public function restAddLink(\WP_REST_Request $request): mixed {
        $title = $request->get_param('title');
        $url = $request->get_param('url');
        $content = $request->get_param('content') ?? '';
        $categories = $request->get_param('categories');
        $tags = $request->get_param('tags');

        $validation = $this->validateRestLink($title, $url);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $post_data = array(
            'post_title'   => $title,
            'post_content' => $content,
            'post_type'    => 'lynx-journal',
            'post_status'  => 'lynxjournal_pending',
        );

        $post_id = wp_insert_post($post_data);
        if (is_wp_error($post_id)) {
            return new \WP_Error('insert_failed', __('Failed to create link.', 'lynx-journal'), array('status' => 500));
        }

        delete_transient('lynxjournal_publish_stats');

        if (!empty($url)) {
            update_post_meta($post_id, '_lynxjournal_url', $url);
        }

        $this->applyLinkTaxonomies($post_id, $categories, $tags);

        return rest_ensure_response(array(
            'success' => true,
            'post_id' => $post_id,
            'message' => __('Link added successfully!', 'lynx-journal'),
        ));
    }

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
     * Get all lynxjournal categories via REST API.
     *
     * @since 1.0.0
     * @return mixed REST response with categories list.
     */
    public function restGetCategories(): mixed {
        $cache_key = 'lynxjournal_api_categories_list';
        $category_list = get_transient($cache_key);

        if (false === $category_list) {
            $categories = get_terms(array(
                'taxonomy'   => 'lynxjournal_category',
                'hide_empty' => false,
            ));

            if (is_wp_error($categories)) {
                return new \WP_Error('fetch_failed', __('Failed to fetch categories.', 'lynx-journal'), array('status' => 500));
            }

            $category_list = array();
            foreach ($categories as $category) {
                $category_list[] = array(
                    'id'   => $category->term_id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                );
            }
            set_transient($cache_key, $category_list, HOUR_IN_SECONDS);
        }
        return rest_ensure_response($category_list);
    }

    /**
     * Update a lynxjournal category via REST API.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The REST request with category data.
     * @return mixed REST response with updated category data.
     */
    public function updateCategory( \WP_REST_Request $request ): mixed {
        $term_id = (int) $request['id'];
        $args    = array( 'name' => $request['name'] );
        if ( $request->has_param('description') ) {
            $args['description'] = $request['description'];
        }
        if ( $request->has_param('slug') && $request['slug'] !== '' ) {
            $args['slug'] = $request['slug'];
        }
        $result = wp_update_term( $term_id, 'lynxjournal_category', $args );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        $this->invalidateCategoriesCache();
        $term = get_term( $result['term_id'], 'lynxjournal_category' );
        return rest_ensure_response( array(
            'id'          => $term->term_id,
            'name'        => $term->name,
            'slug'        => $term->slug,
            'description' => $term->description,
        ) );
    }

    /**
     * Invalidate all categories-related caches.
     *
     * @since 1.0.0
     * @return void
     */
    public function invalidateCategoriesCache(): void {
        delete_transient('lynxjournal_api_categories_list');
        delete_transient('lynxjournal_categories_terms');
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
        if (is_string($origin) && $this->isFromChromeExtension($origin)) {
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
