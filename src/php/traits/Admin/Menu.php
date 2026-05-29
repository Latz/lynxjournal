<?php

declare(strict_types=1);

trait LinkDigest_Admin_Menu {

    /**
     * Register admin menu pages and submenus.
     *
     * @since 1.0.0
     * @return void
     */
    public function adminMenu(): void {
        add_menu_page(
            __('Link Digest', 'linkdigest'),
            __('Link Digest', 'linkdigest'),
            'read',
            'linkdigest-dashboard',
            [$this, 'dashboardPage'],
            plugins_url('assets/icon-menu.png', LINKDIGEST_PLUGIN_FILE),
            6
        );

        $this->addSubmenu(__('Dashboard',        'linkdigest'), __('Dashboard',        'linkdigest'), 'read',              'linkdigest-dashboard',                                    'dashboardPage');
        $this->addSubmenu(__('Show Links',       'linkdigest'), __('All Links',        'linkdigest'), 'read',              'linkdigest-admin',                                        'showLinksPage');
        $this->addSubmenu(__('Add Link',         'linkdigest'), __('Add Link',         'linkdigest'), 'read',              'linkdigest-add',                                          'addLinkPage');
        $this->addSubmenu(__('Categories',       'linkdigest'), __('Categories',       'linkdigest'), 'edit_posts', 'linkdigest-categories',                                   'categoriesPage');
        $this->addSubmenu(__('Tags',             'linkdigest'), __('Tags',             'linkdigest'), 'edit_posts', 'edit-tags.php?taxonomy=linkdigest_tag&post_type=linkdigest');
        $this->addSubmenu(__('Chrome Extension', 'linkdigest'), __('Chrome Extension', 'linkdigest'), 'edit_posts', 'linkdigest-settings',                                     'settingsPage');
        $this->addSubmenu(__('Settings',         'linkdigest'), __('Settings',         'linkdigest'), 'edit_posts', 'linkdigest-setting-x',                                    'settingXPage');
        $this->addSubmenu(__('Schedule',         'linkdigest'), __('Schedule',         'linkdigest'), 'edit_posts', 'linkdigest-schedule',                                     'schedulePage');
    }

    /**
     * Filter the parent menu file for tag management pages.
     *
     * @since 1.0.0
     * @param string $parent_file The parent menu file name.
     * @return string The filtered parent menu file name.
     */
    public function parentFileFilter(string $parent_file): string {
        return $this->isLinkDigestTag() ? 'linkdigest-dashboard' : $parent_file;
    }

    /**
     * Filter the submenu file for tag management pages.
     *
     * @since 1.0.0
     * @param string|null $submenu_file The current submenu file name.
     * @return string The filtered submenu file name.
     */
    public function submenuFileFilter(?string $submenu_file): string {
        return $this->isLinkDigestTag()
            ? 'edit-tags.php?taxonomy=linkdigest_tag&post_type=linkdigest'
            : ($submenu_file ?? '');
    }

    /**
     * Render a readonly copyable text field.
     *
     * @since 1.0.0
     * @param string $id The field ID.
     * @param string $value The field value.
     * @return void
     */
    private function renderCopyableField(string $id, string $value): void {
        ?>
        <div class="linkdigest-row">
            <input
                type="text"
                id="<?php echo esc_attr($id); ?>"
                value="<?php echo esc_attr($value); ?>"
                readonly
                onclick="this.select();"
                class="large-text code"
            >
            <button type="button" class="button linkdigest-copy-btn" data-clipboard-target="<?php echo esc_attr($id); ?>">
                <span class="dashicons dashicons-clipboard linkdigest-btn-icon"></span>
            </button>
        </div>
        <?php
    }

    /**
     * Render the Chrome extension settings page.
     *
     * @since 1.0.0
     * @return void
     */
    public function settingsPage(): void {
        // Handle API key generation
        $nonce = isset($_POST['linkdigest_settings_nonce']) ? sanitize_text_field(wp_unslash($_POST['linkdigest_settings_nonce'])) : '';
        if (isset($_POST['linkdigest_generate_api_key']) && wp_verify_nonce($nonce, 'linkdigest_settings') && current_user_can('edit_posts')) {
            $api_key = wp_generate_password(32, false);
            update_option('linkdigest_api_key', $api_key);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('New API key generated successfully!', 'linkdigest') . '</p></div>';
        }

        $api_key     = get_option('linkdigest_api_key');
        $endpoint    = rest_url(LINKDIGEST_REST_NAMESPACE);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('LinkDigest Chrome Extension', 'linkdigest'); ?></h1>

            <div class="card linkdigest-settings-card">
                <h2><?php esc_html_e('Chrome Extension Access Data', 'linkdigest'); ?></h2>
                <p><?php esc_html_e('Use these credentials to connect the LinkDigest Chrome extension to your WordPress site.', 'linkdigest'); ?></p>

                <div class="linkdigest-settings-field">
                    <label class="linkdigest-settings-label" for="linkdigest-api-endpoint">
                        <?php esc_html_e('API Endpoint', 'linkdigest'); ?>
                        <span class="linkdigest-settings-note">(<?php esc_html_e('read-only', 'linkdigest'); ?>)</span>
                    </label>
                    <div class="linkdigest-row">
                        <input
                            type="text"
                            id="linkdigest-api-endpoint"
                            value="<?php echo esc_attr($endpoint); ?>"
                            readonly
                            class="large-text code"
                        >
                        <button type="button" class="button linkdigest-copy-btn" data-clipboard-target="linkdigest-api-endpoint">
                            <span class="dashicons dashicons-clipboard linkdigest-btn-icon"></span>
                        </button>
                    </div>
                    <p class="description">
                        <?php esc_html_e('Use this URL in the Chrome extension settings.', 'linkdigest'); ?>
                        <a href="<?php echo esc_url($endpoint); ?>" target="_blank" class="linkdigest-rest-link">
                            <?php esc_html_e('View REST API', 'linkdigest'); ?> ↗
                        </a>
                    </p>
                </div>

                <?php if ($api_key) : ?>
                    <div class="linkdigest-settings-field">
                        <label for="linkdigest-api-key" class="linkdigest-settings-label">
                            <?php esc_html_e('API Key:', 'linkdigest'); ?>
                        </label>
                        <div class="linkdigest-row">
                            <input
                                type="password"
                                id="linkdigest-api-key"
                                value="<?php echo esc_attr($api_key); ?>"
                                readonly
                                class="large-text code"
                            >
                            <button type="button" class="button linkdigest-toggle-key" title="<?php esc_attr_e('Show / hide API key', 'linkdigest'); ?>">
                                <span class="dashicons dashicons-visibility linkdigest-btn-icon"></span>
                            </button>
                            <button type="button" class="button linkdigest-copy-btn" data-clipboard-target="linkdigest-api-key">
                                <span class="dashicons dashicons-clipboard linkdigest-btn-icon"></span>
                            </button>
                        </div>
                        <p class="description linkdigest-settings-desc">
                            <?php esc_html_e('Keep this key secure. Use the copy button to transfer it without revealing it.', 'linkdigest'); ?>
                        </p>
                        <div class="linkdigest-settings-test-row">
                            <button type="button" id="linkdigest-test-connection" class="button">
                                <?php esc_html_e('Test Connection', 'linkdigest'); ?>
                            </button>
                            <span id="linkdigest-connection-status"></span>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="post" action="" id="linkdigest-generate-form" <?php if ( $api_key ) : ?>data-has-key="1"<?php endif; ?>>
                    <?php wp_nonce_field('linkdigest_settings', 'linkdigest_settings_nonce'); ?>
                    <?php if ($api_key) : ?>
                        <div class="notice notice-warning inline">
                            <p><?php esc_html_e('Warning: Generating a new key will permanently invalidate the current one. You will need to update the Chrome extension with the new key.', 'linkdigest'); ?></p>
                        </div>
                    <?php endif; ?>
                    <button type="submit" name="linkdigest_generate_api_key" class="button button-primary">
                        <?php echo $api_key ? esc_html__('Generate New API Key', 'linkdigest') : esc_html__('Generate API Key', 'linkdigest'); ?>
                    </button>
                </form>
            </div>

            <div class="card linkdigest-setup-card">
                <h2><?php esc_html_e('Chrome Extension Setup', 'linkdigest'); ?></h2>
                <ol>
                    <li><?php esc_html_e('Download and install the LinkDigest Chrome extension', 'linkdigest'); ?></li>
                    <li><?php esc_html_e('Click the extension icon and go to Settings', 'linkdigest'); ?></li>
                    <li><?php esc_html_e('Paste your API Endpoint and API Key from above', 'linkdigest'); ?></li>
                    <li><?php esc_html_e('Click Save', 'linkdigest'); ?></li>
                    <li><?php esc_html_e('Now you can save links directly from any webpage!', 'linkdigest'); ?></li>
                </ol>
            </div>
        </div>

        <?php
    }

    /**
     * Render the schedule configuration page.
     *
     * @since 1.0.0
     * @return void
     */
    public function schedulePage(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Schedule Configuration', 'linkdigest'); ?></h1>
            <div id="linkdigest-schedule-root"></div>
        </div>
        <?php
    }

    /**
     * Enqueue admin CSS and JavaScript assets for LinkDigest pages.
     *
     * @since 1.0.0
     * @param string $hook The current admin page hook.
     * @return void
     */
    public function enqueueAdminAssets(string $hook): void {
        $is_linkdigest = strpos($hook, 'linkdigest') !== false;

        // CSS must also load on the WP core dashboard (index.php) for the linkdigest widget
        if ($is_linkdigest || $hook === 'index.php') {
            wp_enqueue_style('dashicons');
            wp_enqueue_style(
                'linkdigest-dashboard',
                plugin_dir_url(LINKDIGEST_PLUGIN_FILE) . 'dashboard.css',
                array(),
                (string) filemtime(plugin_dir_path(LINKDIGEST_PLUGIN_FILE) . 'dashboard.css')
            );
        }

        if (!$is_linkdigest) {
            return;
        }

        if (strpos($hook, 'linkdigest-dashboard') !== false) {
            wp_enqueue_script('postbox');
            $this->enqueuePageScript('linkdigest-dashboard-js', 'dashboard.js');
            wp_localize_script('linkdigest-dashboard-js', 'linkdigestDash', array(
                'restUrl' => rest_url(LINKDIGEST_REST_NAMESPACE . '/links/'),
                'nonce'   => wp_create_nonce('wp_rest'),
                'labels'  => array(
                    'delete' => __('Delete?', 'linkdigest'),
                    'yes'    => __('Yes', 'linkdigest'),
                    'cancel' => __('Cancel', 'linkdigest'),
                ),
            ));
        }

        if (strpos($hook, 'linkdigest-settings') !== false) {
            $this->enqueuePageScript('linkdigest-settings-page', 'settings-page.js', array('jquery'));
            wp_localize_script('linkdigest-settings-page', 'linkdigestSettings', array(
                'labels' => array(
                    'confirmRegenerate' => __('This will permanently invalidate your current API key. You will need to update the Chrome extension with the new key. Continue?', 'linkdigest'),
                    'missingFields'     => __('Missing endpoint or API key.', 'linkdigest'),
                    'statusTesting'     => __('Testing…', 'linkdigest'),
                    'statusOk'          => __('Connected successfully.', 'linkdigest'),
                    'statusFail'        => __('Connection failed', 'linkdigest'),
                    'statusUnreachable' => __('Could not reach endpoint.', 'linkdigest'),
                ),
            ));
        }

        if (strpos($hook, 'linkdigest-admin') !== false) {
            $this->enqueuePageScript('linkdigest-links-page', 'links-page.js');
        }

        if (strpos($hook, 'linkdigest-categories') !== false) {
            $this->enqueuePageScript('linkdigest-categories-js', 'categories.js');
            wp_localize_script('linkdigest-categories-js', 'linkdigestCats', array(
                'restUrl' => rest_url(LINKDIGEST_REST_NAMESPACE . '/categories/'),
                'nonce'   => wp_create_nonce('wp_rest'),
                'labels'  => array(
                    'edit'            => __('Edit', 'linkdigest'),
                    'save'            => __('Save', 'linkdigest'),
                    'cancel'          => __('Cancel', 'linkdigest'),
                    'saving'          => __('Saving…', 'linkdigest'),
                    'saveError'       => __('Save failed.', 'linkdigest'),
                    'nameRequired'    => __('Name is required.', 'linkdigest'),
                    'descPlaceholder' => __('Description (optional)', 'linkdigest'),
                    'slugPlaceholder' => __('Leave blank to keep current', 'linkdigest'),
                    'deleteOne'       => __('link will become uncategorized.', 'linkdigest'),
                    'deleteMany'      => __('links will become uncategorized.', 'linkdigest'),
                ),
            ));
        }

        if (strpos($hook, 'linkdigest-schedule') !== false) {
            $asset_file = plugin_dir_path(LINKDIGEST_PLUGIN_FILE) . 'build/schedule.asset.php';
            if (file_exists($asset_file)) {
                $asset = require_once $asset_file;
            } else {
                $asset = array('dependencies' => array(), 'version' => '1.0.0');
            }

            wp_enqueue_script(
                'linkdigest-schedule',
                plugin_dir_url(LINKDIGEST_PLUGIN_FILE) . 'build/schedule.js',
                $asset['dependencies'],
                $asset['version'],
                true
            );

            wp_localize_script('linkdigest-schedule', 'linkdigestSchedule', array(
                'allModes'     => array_column(\ScheduleMode::cases(), 'value'),
                'timeModes'    => array_column(\ScheduleMode::timeBased(), 'value'),
                'triggerModes' => array_column(\ScheduleMode::triggerBased(), 'value'),
                'timezone'     => wp_timezone_string(),
            ));

            if (file_exists(plugin_dir_path(LINKDIGEST_PLUGIN_FILE) . 'build/schedule.css')) {
                wp_enqueue_style(
                    'linkdigest-schedule-style',
                    plugin_dir_url(LINKDIGEST_PLUGIN_FILE) . 'build/schedule.css',
                    array('wp-components'),
                    $asset['version']
                );
            }
        }
    }

    /**
     * Render the experimental Setting X configuration page.
     *
     * @since 1.0.0
     * @return void
     */
    public function settingXPage(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Settings', 'linkdigest'); ?></h1>
        </div>
        <?php
    }

    /**
     * Register a submenu page under the linkdigest-dashboard parent.
     *
     * @since 1.0.0
     * @param string      $page_title Page title.
     * @param string      $menu_title Menu label.
     * @param string      $cap        Required capability.
     * @param string      $slug       Menu slug.
     * @param string|null $callback   Method name on $this, or null for no render callback.
     * @return void
     */
    private function addSubmenu(string $page_title, string $menu_title, string $cap, string $slug, ?string $callback = null): void {
        add_submenu_page('linkdigest-dashboard', $page_title, $menu_title, $cap, $slug, $callback !== null ? [$this, $callback] : null);
    }

    /**
     * Return true when the current request is for the linkdigest_tag taxonomy screen.
     *
     * @since 1.0.0
     * @return bool
     */
    private function isLinkDigestTag(): bool {
        global $pagenow;
        if ($pagenow !== 'edit-tags.php') {
            return false;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $taxonomy = isset($_GET['taxonomy']) ? sanitize_key(wp_unslash($_GET['taxonomy'])) : '';
        return $taxonomy === 'linkdigest_tag';
    }

    /**
     * Enqueue a versioned JS asset from the assets/js/ directory.
     *
     * @since 1.0.0
     * @param string   $handle Script handle.
     * @param string   $file   Filename inside assets/js/.
     * @param string[] $deps   Script dependencies.
     * @return void
     */
    private function enqueuePageScript(string $handle, string $file, array $deps = []): void {
        $js_dir = plugin_dir_path(LINKDIGEST_PLUGIN_FILE) . 'assets/js/';
        $js_url = plugin_dir_url(LINKDIGEST_PLUGIN_FILE) . 'assets/js/';
        wp_enqueue_script($handle, $js_url . $file, $deps, (string) filemtime($js_dir . $file), true);
    }

    /**
     * Add the LinkDigest dashboard widget to the WordPress dashboard.
     *
     * @since 1.0.0
     * @return void
     */
    public function addDashboardWidget(): void {
        wp_add_dashboard_widget(
            'linkdigest_dashboard_widget',
            __('LinkDigest Summary', 'linkdigest'),
            [$this, 'dashboardWidgetContent']
        );
    }
}
