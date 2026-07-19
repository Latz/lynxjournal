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
        $this->registerLinkRoutes();
        $this->registerCategoryRoutes();
        $this->registerTagRoutes();
        $this->registerScheduleRoutes();
        $this->registerAuthRoutes();
    }

    /**
     * Register REST routes for adding and deleting links.
     *
     * @since 1.0.0
     * @return void
     */
    private function registerLinkRoutes(): void {
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

        register_rest_route(LYNXJOURNAL_REST_NAMESPACE, '/links/(?P<id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => [$this, 'restDeleteLink'],
            'permission_callback' => function( \WP_REST_Request $request ) {
                return current_user_can('delete_post', (int) $request->get_param('id'));
            },
        ));
    }

    /**
     * Register REST routes for listing and updating link categories.
     *
     * @since 1.0.0
     * @return void
     */
    private function registerCategoryRoutes(): void {
        register_rest_route(LYNXJOURNAL_REST_NAMESPACE, '/categories', array(
            array(
                'methods'             => 'GET',
                'callback'            => [$this, 'restGetCategories'],
                'permission_callback' => [$this, 'restPermissionCheck'],
            ),
            array(
                'methods'             => 'POST',
                'callback'            => [$this, 'restCreateCategory'],
                'permission_callback' => fn() => current_user_can('edit_posts'),
                'args'                => array(
                    'name'        => array( 'required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
                    'description' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ),
                ),
            ),
        ));

        register_rest_route(LYNXJOURNAL_REST_NAMESPACE, '/categories/counts', array(
            'methods'             => 'GET',
            'callback'            => [$this, 'restGetCategoryLinkCounts'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ));

        register_rest_route(LYNXJOURNAL_REST_NAMESPACE, '/categories/(?P<id>\d+)', array(
            array(
                'methods'             => 'POST',
                'callback'            => [$this, 'updateCategory'],
                'permission_callback' => fn() => current_user_can('edit_posts'),
                'args'                => array(
                    'id'          => array( 'required' => true,  'type' => 'integer' ),
                    'name'        => array( 'required' => true,  'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ),
                    'description' => array( 'required' => false, 'type' => 'string',  'sanitize_callback' => 'sanitize_textarea_field' ),
                    'slug'        => array( 'required' => false, 'type' => 'string',  'sanitize_callback' => 'sanitize_title' ),
                ),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => [$this, 'restDeleteCategory'],
                'permission_callback' => fn() => current_user_can('edit_posts'),
                'args'                => array(
                    'id' => array( 'required' => true, 'type' => 'integer' ),
                ),
            ),
        ));
    }

    /**
     * Register REST routes for listing and updating link tags.
     *
     * @since 1.0.0
     * @return void
     */
    private function registerTagRoutes(): void {
        register_rest_route(LYNXJOURNAL_REST_NAMESPACE, '/tags', array(
            array(
                'methods'             => 'GET',
                'callback'            => [$this, 'restGetTags'],
                'permission_callback' => [$this, 'restPermissionCheck'],
            ),
            array(
                'methods'             => 'POST',
                'callback'            => [$this, 'restCreateTag'],
                'permission_callback' => fn() => current_user_can('edit_posts'),
                'args'                => array(
                    'name'        => array( 'required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
                    'description' => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ),
                ),
            ),
        ));

        register_rest_route(LYNXJOURNAL_REST_NAMESPACE, '/tags/counts', array(
            'methods'             => 'GET',
            'callback'            => [$this, 'restGetTagLinkCounts'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ));

        register_rest_route(LYNXJOURNAL_REST_NAMESPACE, '/tags/(?P<id>\d+)', array(
            array(
                'methods'             => 'POST',
                'callback'            => [$this, 'updateTag'],
                'permission_callback' => fn() => current_user_can('edit_posts'),
                'args'                => array(
                    'id'          => array( 'required' => true,  'type' => 'integer' ),
                    'name'        => array( 'required' => true,  'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ),
                    'description' => array( 'required' => false, 'type' => 'string',  'sanitize_callback' => 'sanitize_textarea_field' ),
                    'slug'        => array( 'required' => false, 'type' => 'string',  'sanitize_callback' => 'sanitize_title' ),
                ),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => [$this, 'restDeleteTag'],
                'permission_callback' => fn() => current_user_can('edit_posts'),
                'args'                => array(
                    'id' => array( 'required' => true, 'type' => 'integer' ),
                ),
            ),
        ));
    }

    /**
     * Register REST routes for reading, saving, running, and previewing the schedule.
     *
     * @since 1.0.0
     * @return void
     */
    private function registerScheduleRoutes(): void {
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
                update_option('lynxjournal_cron_notice_dismissed', true, false);
                return rest_ensure_response(array('success' => true));
            },
            'permission_callback' => fn() => current_user_can('manage_options'),
        ));
    }

    /**
     * Register REST routes for the Chrome extension API key and nonce.
     *
     * @since 1.0.0
     * @return void
     */
    private function registerAuthRoutes(): void {
        register_rest_route(LYNXJOURNAL_REST_NAMESPACE, '/api-key', array(
            array(
                'methods'             => 'GET',
                'callback'            => [$this, 'restGetApiKey'],
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ),
            array(
                'methods'             => 'POST',
                'callback'            => [$this, 'restGenerateApiKey'],
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ),
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
     * Generate a new API key via REST, replacing any existing key.
     *
     * @since 1.0.0
     * @return mixed REST response with the newly generated API key.
     */
    public function restGenerateApiKey(): mixed {
        $key = wp_generate_password(32, false);
        update_option('lynxjournal_api_key', $key, false);
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
        if ($this->isFromChromeExtension($origin)) {
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
            'times'   => array(self::DEFAULT_TIME),
        );
        $config = get_option('lynxjournal_schedule', $default);
        if (empty($config['times'])) {
            $config['times'] = array(self::DEFAULT_TIME);
        }
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

        $post_id = wp_insert_post($post_data, true);
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
                    'id'          => $category->term_id,
                    'name'        => $category->name,
                    'slug'        => $category->slug,
                    'description' => $category->description,
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
        delete_transient('lynxjournal_category_link_counts');
    }

    /**
     * Create a lynxjournal category via REST API.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The REST request with category data.
     * @return mixed REST response with the created category, or a WP_Error.
     */
    public function restCreateCategory( \WP_REST_Request $request ): mixed {
        $result = wp_insert_term(
            $request->get_param('name'),
            'lynxjournal_category',
            array( 'description' => $request->get_param('description') ?? '' )
        );
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
     * Delete a lynxjournal category via REST API.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The REST request with the category ID.
     * @return \WP_REST_Response|\WP_Error 204 on success, or an error.
     */
    public function restDeleteCategory( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $term_id = (int) $request['id'];
        $result  = wp_delete_term( $term_id, 'lynxjournal_category' );
        if ( is_wp_error( $result ) || $result === false ) {
            return new \WP_Error( 'delete_failed', __( 'Could not delete category.', 'lynx-journal' ), array( 'status' => 500 ) );
        }
        $this->invalidateCategoriesCache();
        return new \WP_REST_Response( null, 204 );
    }

    /**
     * Get per-category link counts via REST API.
     *
     * @since 1.0.0
     * @return mixed REST response with a term_id => count map.
     */
    public function restGetCategoryLinkCounts(): mixed {
        return rest_ensure_response( $this->getCategoryLinkCounts() );
    }

    /**
     * Get all lynxjournal tags via REST API.
     *
     * @since 1.0.0
     * @return mixed REST response with tags list.
     */
    public function restGetTags(): mixed {
        $cache_key = 'lynxjournal_api_tags_list';
        $tag_list  = get_transient($cache_key);

        if (false === $tag_list) {
            $tags = get_terms(array(
                'taxonomy'   => 'lynxjournal_tag',
                'hide_empty' => false,
            ));

            if (is_wp_error($tags)) {
                return new \WP_Error('fetch_failed', __('Failed to fetch tags.', 'lynx-journal'), array('status' => 500));
            }

            $tag_list = array();
            foreach ($tags as $tag) {
                $tag_list[] = array(
                    'id'          => $tag->term_id,
                    'name'        => $tag->name,
                    'slug'        => $tag->slug,
                    'description' => $tag->description,
                );
            }
            set_transient($cache_key, $tag_list, HOUR_IN_SECONDS);
        }
        return rest_ensure_response($tag_list);
    }

    /**
     * Update a lynxjournal tag via REST API.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The REST request with tag data.
     * @return mixed REST response with updated tag data.
     */
    public function updateTag( \WP_REST_Request $request ): mixed {
        $term_id = (int) $request['id'];
        $args    = array( 'name' => $request['name'] );
        if ( $request->has_param('description') ) {
            $args['description'] = $request['description'];
        }
        if ( $request->has_param('slug') && $request['slug'] !== '' ) {
            $args['slug'] = $request['slug'];
        }
        $result = wp_update_term( $term_id, 'lynxjournal_tag', $args );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        $this->invalidateTagsCache();
        $term = get_term( $result['term_id'], 'lynxjournal_tag' );
        return rest_ensure_response( array(
            'id'          => $term->term_id,
            'name'        => $term->name,
            'slug'        => $term->slug,
            'description' => $term->description,
        ) );
    }

    /**
     * Invalidate all tags-related caches.
     *
     * @since 1.0.0
     * @return void
     */
    public function invalidateTagsCache(): void {
        delete_transient('lynxjournal_api_tags_list');
        delete_transient('lynxjournal_tags_terms');
        delete_transient('lynxjournal_tag_link_counts');
    }

    /**
     * Create a lynxjournal tag via REST API.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The REST request with tag data.
     * @return mixed REST response with the created tag, or a WP_Error.
     */
    public function restCreateTag( \WP_REST_Request $request ): mixed {
        $result = wp_insert_term(
            $request->get_param('name'),
            'lynxjournal_tag',
            array( 'description' => $request->get_param('description') ?? '' )
        );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        $this->invalidateTagsCache();
        $term = get_term( $result['term_id'], 'lynxjournal_tag' );
        return rest_ensure_response( array(
            'id'          => $term->term_id,
            'name'        => $term->name,
            'slug'        => $term->slug,
            'description' => $term->description,
        ) );
    }

    /**
     * Delete a lynxjournal tag via REST API.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The REST request with the tag ID.
     * @return \WP_REST_Response|\WP_Error 204 on success, or an error.
     */
    public function restDeleteTag( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $term_id = (int) $request['id'];
        $result  = wp_delete_term( $term_id, 'lynxjournal_tag' );
        if ( is_wp_error( $result ) || $result === false ) {
            return new \WP_Error( 'delete_failed', __( 'Could not delete tag.', 'lynx-journal' ), array( 'status' => 500 ) );
        }
        $this->invalidateTagsCache();
        return new \WP_REST_Response( null, 204 );
    }

    /**
     * Get per-tag link counts via REST API.
     *
     * @since 1.0.0
     * @return mixed REST response with a term_id => count map.
     */
    public function restGetTagLinkCounts(): mixed {
        return rest_ensure_response( $this->getTagLinkCounts() );
    }

}
