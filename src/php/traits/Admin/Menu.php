<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait LynxJournal_Admin_Menu {

    /**
     * Register admin menu pages and submenus.
     *
     * @since 1.0.0
     * @return void
     */
    public function adminMenu(): void {
        add_menu_page(
            __('LynxJournal', 'lynx-journal'),
            __('LynxJournal', 'lynx-journal'),
            'read',
            'lynxjournal-dashboard',
            [$this, 'dashboardPage'],
            plugins_url('assets/icon-menu.png', LYNXJOURNAL_PLUGIN_FILE),
            null
        );

        $this->addSubmenu(__('Dashboard',        'lynx-journal'), __('Dashboard',        'lynx-journal'), 'read',              'lynxjournal-dashboard',                                    'dashboardPage');
        $this->addSubmenu(__('Show Links',       'lynx-journal'), __('All Links',        'lynx-journal'), 'read',              'lynxjournal-admin',                                        'showLinksPage');
        $this->addSubmenu(__('Add Link',         'lynx-journal'), __('Add Link',         'lynx-journal'), 'read',              'lynxjournal-add',                                          'addLinkPage');
        $this->addSubmenu(__('Categories',       'lynx-journal'), __('Categories',       'lynx-journal'), 'edit_posts', 'lynxjournal-categories',                                   'categoriesPage');
        $this->addSubmenu(__('Tags',             'lynx-journal'), __('Tags',             'lynx-journal'), 'edit_posts', 'edit-tags.php?taxonomy=lynxjournal_tag&post_type=lynx-journal');
        $this->addSubmenu(__('Chrome Extension', 'lynx-journal'), __('Chrome Extension', 'lynx-journal'), 'edit_posts', 'lynxjournal-settings',                                     'settingsPage');
        $this->addSubmenu(__('Schedule',         'lynx-journal'), __('Schedule',         'lynx-journal'), 'edit_posts', 'lynxjournal-schedule',                                     'schedulePage');
        $this->addSubmenu(__('Post Template',    'lynx-journal'), __('Post Template',    'lynx-journal'), 'edit_posts', 'lynxjournal-template',                                     'templatePage');
    }

    /**
     * Filter the parent menu file for tag management pages.
     *
     * @since 1.0.0
     * @param string $parent_file The parent menu file name.
     * @return string The filtered parent menu file name.
     */
    public function parentFileFilter(string $parent_file): string {
        return $this->isLynxJournalTag() ? 'lynxjournal-dashboard' : $parent_file;
    }

    /**
     * Filter the submenu file for tag management pages.
     *
     * @since 1.0.0
     * @param string|null $submenu_file The current submenu file name.
     * @return string The filtered submenu file name.
     */
    public function submenuFileFilter(?string $submenu_file): string {
        return $this->isLynxJournalTag()
            ? 'edit-tags.php?taxonomy=lynxjournal_tag&post_type=lynx-journal'
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
        <div class="lynxjournal-row">
            <input
                type="text"
                id="<?php echo esc_attr($id); ?>"
                value="<?php echo esc_attr($value); ?>"
                readonly
                onclick="this.select();"
                class="large-text code"
            >
            <button type="button" class="button lynxjournal-copy-btn" data-clipboard-target="<?php echo esc_attr($id); ?>">
                <span class="dashicons dashicons-clipboard lynxjournal-btn-icon"></span>
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
        $nonce = isset($_POST['lynxjournal_settings_nonce']) ? sanitize_text_field(wp_unslash($_POST['lynxjournal_settings_nonce'])) : '';
        if (isset($_POST['lynxjournal_generate_api_key']) && wp_verify_nonce($nonce, 'lynxjournal_settings') && current_user_can('edit_posts')) {
            $api_key = wp_generate_password(32, false);
            update_option('lynxjournal_api_key', $api_key);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('New API key generated successfully!', 'lynx-journal') . '</p></div>';
        }

        $api_key     = get_option('lynxjournal_api_key');
        $endpoint    = rest_url(LYNXJOURNAL_REST_NAMESPACE);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('LynxJournal Chrome Extension', 'lynx-journal'); ?></h1>

            <div class="card lynxjournal-settings-card">
                <h2><?php esc_html_e('Chrome Extension Access Data', 'lynx-journal'); ?></h2>
                <p><?php esc_html_e('Use these credentials to connect the LynxJournal Chrome extension to your WordPress site.', 'lynx-journal'); ?></p>

                <div class="lynxjournal-settings-field">
                    <label class="lynxjournal-settings-label" for="lynxjournal-api-endpoint">
                        <?php esc_html_e('API Endpoint', 'lynx-journal'); ?>
                        <span class="lynxjournal-settings-note">(<?php esc_html_e('read-only', 'lynx-journal'); ?>)</span>
                    </label>
                    <div class="lynxjournal-row">
                        <input
                            type="text"
                            id="lynxjournal-api-endpoint"
                            value="<?php echo esc_attr($endpoint); ?>"
                            readonly
                            class="large-text code"
                        >
                        <button type="button" class="button lynxjournal-copy-btn" data-clipboard-target="lynxjournal-api-endpoint">
                            <span class="dashicons dashicons-clipboard lynxjournal-btn-icon"></span>
                        </button>
                    </div>
                    <p class="description">
                        <?php esc_html_e('Use this URL in the Chrome extension settings.', 'lynx-journal'); ?>
                        <a href="<?php echo esc_url($endpoint); ?>" target="_blank" class="lynxjournal-rest-link">
                            <?php esc_html_e('View REST API', 'lynx-journal'); ?> ↗
                        </a>
                    </p>
                </div>

                <?php if ($api_key) : ?>
                    <div class="lynxjournal-settings-field">
                        <label for="lynxjournal-api-key" class="lynxjournal-settings-label">
                            <?php esc_html_e('API Key:', 'lynx-journal'); ?>
                        </label>
                        <div class="lynxjournal-row">
                            <input
                                type="password"
                                id="lynxjournal-api-key"
                                value="<?php echo esc_attr($api_key); ?>"
                                readonly
                                class="large-text code"
                            >
                            <button type="button" class="button lynxjournal-toggle-key" title="<?php esc_attr_e('Show / hide API key', 'lynx-journal'); ?>">
                                <span class="dashicons dashicons-visibility lynxjournal-btn-icon"></span>
                            </button>
                            <button type="button" class="button lynxjournal-copy-btn" data-clipboard-target="lynxjournal-api-key">
                                <span class="dashicons dashicons-clipboard lynxjournal-btn-icon"></span>
                            </button>
                        </div>
                        <p class="description lynxjournal-settings-desc">
                            <?php esc_html_e('Keep this key secure. Use the copy button to transfer it without revealing it.', 'lynx-journal'); ?>
                        </p>
                        <div class="lynxjournal-settings-test-row">
                            <button type="button" id="lynxjournal-test-connection" class="button">
                                <?php esc_html_e('Test Connection', 'lynx-journal'); ?>
                            </button>
                            <span id="lynxjournal-connection-status"></span>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="post" action="" id="lynxjournal-generate-form" <?php if ( $api_key ) : ?>data-has-key="1"<?php endif; ?>>
                    <?php wp_nonce_field('lynxjournal_settings', 'lynxjournal_settings_nonce'); ?>
                    <?php if ($api_key) : ?>
                        <div class="notice notice-warning inline">
                            <p><?php esc_html_e('Warning: Generating a new key will permanently invalidate the current one. You will need to update the Chrome extension with the new key.', 'lynx-journal'); ?></p>
                        </div>
                    <?php endif; ?>
                    <button type="submit" name="lynxjournal_generate_api_key" class="button button-primary">
                        <?php echo $api_key ? esc_html__('Generate New API Key', 'lynx-journal') : esc_html__('Generate API Key', 'lynx-journal'); ?>
                    </button>
                </form>
            </div>

            <div class="card lynxjournal-setup-card">
                <h2><?php esc_html_e('Chrome Extension Setup', 'lynx-journal'); ?></h2>
                <ol>
                    <li><?php esc_html_e('Download and install the LynxJournal Chrome extension', 'lynx-journal'); ?></li>
                    <li><?php esc_html_e('Click the extension icon and go to Settings', 'lynx-journal'); ?></li>
                    <li><?php esc_html_e('Paste your API Endpoint and API Key from above', 'lynx-journal'); ?></li>
                    <li><?php esc_html_e('Click Save', 'lynx-journal'); ?></li>
                    <li><?php esc_html_e('Now you can save links directly from any webpage!', 'lynx-journal'); ?></li>
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
            <h1><?php esc_html_e('Schedule Configuration', 'lynx-journal'); ?></h1>
            <div id="lynxjournal-schedule-root"></div>
        </div>
        <?php
    }

    /**
     * Enqueue admin CSS and JavaScript assets for LynxJournal pages.
     *
     * @since 1.0.0
     * @param string $hook The current admin page hook.
     * @return void
     */
    public function enqueueAdminAssets(string $hook): void {
        $is_lynxjournal = strpos($hook, 'lynxjournal') !== false;

        // CSS must also load on the WP core dashboard (index.php) for the lynxjournal widget
        if ($is_lynxjournal || $hook === 'index.php') {
            wp_enqueue_style('dashicons');
            wp_enqueue_style(
                'lynxjournal-dashboard',
                plugin_dir_url(LYNXJOURNAL_PLUGIN_FILE) . 'dashboard.css',
                array(),
                (string) filemtime(plugin_dir_path(LYNXJOURNAL_PLUGIN_FILE) . 'dashboard.css')
            );
        }

        if (!$is_lynxjournal) {
            return;
        }

        if (strpos($hook, 'lynxjournal-dashboard') !== false) {
            wp_enqueue_script('postbox');
            $this->enqueuePageScript('lynxjournal-dashboard-js', 'dashboard.js');
            wp_localize_script('lynxjournal-dashboard-js', 'lynxjournalDash', array(
                'restUrl' => rest_url(LYNXJOURNAL_REST_NAMESPACE . '/links/'),
                'nonce'   => wp_create_nonce('wp_rest'),
                'labels'  => array(
                    'delete' => __('Delete?', 'lynx-journal'),
                    'yes'    => __('Yes', 'lynx-journal'),
                    'cancel' => __('Cancel', 'lynx-journal'),
                ),
            ));
        }

        if (strpos($hook, 'lynxjournal-settings') !== false) {
            $this->enqueuePageScript('lynxjournal-settings-page', 'settings-page.js', array('jquery'));
            wp_localize_script('lynxjournal-settings-page', 'lynxjournalSettings', array(
                'labels' => array(
                    'confirmRegenerate' => __('This will permanently invalidate your current API key. You will need to update the Chrome extension with the new key. Continue?', 'lynx-journal'),
                    'missingFields'     => __('Missing endpoint or API key.', 'lynx-journal'),
                    'statusTesting'     => __('Testing…', 'lynx-journal'),
                    'statusOk'          => __('Connected successfully.', 'lynx-journal'),
                    'statusFail'        => __('Connection failed', 'lynx-journal'),
                    'statusUnreachable' => __('Could not reach endpoint.', 'lynx-journal'),
                ),
            ));
        }

        if (strpos($hook, 'lynxjournal-admin') !== false) {
            $this->enqueuePageScript('lynxjournal-links-page', 'links-page.js');
        }

        if (strpos($hook, 'lynxjournal-categories') !== false) {
            $this->enqueuePageScript('lynxjournal-categories-js', 'categories.js');
            wp_localize_script('lynxjournal-categories-js', 'lynxjournalCats', array(
                'restUrl' => rest_url(LYNXJOURNAL_REST_NAMESPACE . '/categories/'),
                'nonce'   => wp_create_nonce('wp_rest'),
                'labels'  => array(
                    'edit'            => __('Edit', 'lynx-journal'),
                    'save'            => __('Save', 'lynx-journal'),
                    'cancel'          => __('Cancel', 'lynx-journal'),
                    'saving'          => __('Saving…', 'lynx-journal'),
                    'saveError'       => __('Save failed.', 'lynx-journal'),
                    'nameRequired'    => __('Name is required.', 'lynx-journal'),
                    'descPlaceholder' => __('Description (optional)', 'lynx-journal'),
                    'slugPlaceholder' => __('Leave blank to keep current', 'lynx-journal'),
                    'deleteOne'       => __('link will become uncategorized.', 'lynx-journal'),
                    'deleteMany'      => __('links will become uncategorized.', 'lynx-journal'),
                ),
            ));
        }

        if (strpos($hook, 'lynxjournal-template') !== false) {
            $this->enqueuePageStyle('lynxjournal-template-css', 'template-page.css', ['lynxjournal-dashboard']);
            wp_enqueue_script(
                'marked',
                plugin_dir_url(LYNXJOURNAL_PLUGIN_FILE) . 'assets/js/marked.min.js',
                [],
                '15.0.12',
                true
            );
            $editor_settings = wp_enqueue_code_editor([
                'type'       => 'text/plain',
                'codemirror' => [
                    'mode'           => 'null',
                    'lineWrapping'   => true,
                    'lineNumbers'    => false,
                    'tabSize'        => 2,
                    'indentWithTabs' => false,
                ],
            ]);
            add_filter('script_loader_tag', function (string $tag, string $handle): string {
                if ($handle === 'lynxjournal-template-js') {
                    return str_replace(' src=', ' type="module" src=', $tag);
                }
                return $tag;
            }, 10, 2);
            $this->enqueuePageScript('lynxjournal-template-js', 'template-page.js', ['marked', 'code-editor']);
            // template-page.js appends this version to the dynamic import() URL of every
            // module below — take the newest mtime across all of them so editing any one
            // busts the shared cache-buster, not just template-utils.js.
            $template_js_modules = [
                'src/js/template-utils.js',
                'src/js/template-preview.js',
                'src/js/template-toolbar-fallback.js',
            ];
            $template_utils_version = (string) max(array_map(
                static fn (string $file): int => (int) filemtime(plugin_dir_path(LYNXJOURNAL_PLUGIN_FILE) . $file),
                $template_js_modules
            ));
            wp_add_inline_script(
                'lynxjournal-template-js',
                'var lynxjournalTemplateUtilsVersion = ' . wp_json_encode($template_utils_version) . ';',
                'before'
            );
            if (false !== $editor_settings) {
                wp_add_inline_script(
                    'lynxjournal-template-js',
                    'var lynxjournalEditorSettings = ' . wp_json_encode($editor_settings) . ';',
                    'before'
                );
            }
            $data_file = plugin_dir_path(LYNXJOURNAL_PLUGIN_FILE) . 'assets/js/template-preview-data.json';
            if (file_exists($data_file)) {
                $preview_data = json_decode((string) file_get_contents($data_file), true);
                if (is_array($preview_data) && isset($preview_data['scalar'])) {
                    $preview_data['scalar']['[roundup_count]'] = (string) get_option('lynxjournal_roundup_count', 0);
                }
                wp_localize_script('lynxjournal-template-js', 'lynxjournalPreviewData', $preview_data);
            }
            wp_localize_script('lynxjournal-template-js', 'lynxjournalTemplate', array(
                'restUrl' => rest_url(LYNXJOURNAL_REST_NAMESPACE . '/template/test-post'),
                'nonce'   => wp_create_nonce('wp_rest'),
            ));
        }

        if (strpos($hook, 'lynxjournal-schedule') !== false) {
            $asset_file = plugin_dir_path(LYNXJOURNAL_PLUGIN_FILE) . 'build/schedule.asset.php';
            if (file_exists($asset_file)) {
                $asset = require_once $asset_file;
            } else {
                $asset = array('dependencies' => array(), 'version' => '1.0.0');
            }

            wp_enqueue_script(
                'lynxjournal-schedule',
                plugin_dir_url(LYNXJOURNAL_PLUGIN_FILE) . 'build/schedule.js',
                $asset['dependencies'],
                $asset['version'],
                true
            );

            wp_localize_script('lynxjournal-schedule', 'lynxjournalSchedule', array(
                'allModes'     => array_column(LynxJournal_ScheduleMode::cases(), 'value'),
                'timeModes'    => array_column(LynxJournal_ScheduleMode::time_based(), 'value'),
                'triggerModes' => array_column(LynxJournal_ScheduleMode::trigger_based(), 'value'),
                'timezone'     => wp_timezone_string(),
            ));

            if (file_exists(plugin_dir_path(LYNXJOURNAL_PLUGIN_FILE) . 'build/schedule.css')) {
                wp_enqueue_style(
                    'lynxjournal-schedule-style',
                    plugin_dir_url(LYNXJOURNAL_PLUGIN_FILE) . 'build/schedule.css',
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
            <h1><?php esc_html_e('Settings', 'lynx-journal'); ?></h1>
        </div>
        <?php
    }

    /**
     * Register a submenu page under the lynxjournal-dashboard parent.
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
        add_submenu_page('lynxjournal-dashboard', $page_title, $menu_title, $cap, $slug, $callback !== null ? [$this, $callback] : null);
    }

    /**
     * Return true when the current request is for the lynxjournal_tag taxonomy screen.
     *
     * @since 1.0.0
     * @return bool
     */
    private function isLynxJournalTag(): bool {
        global $pagenow;
        if ($pagenow !== 'edit-tags.php') {
            return false;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $taxonomy = isset($_GET['taxonomy']) ? sanitize_key(wp_unslash($_GET['taxonomy'])) : '';
        return $taxonomy === 'lynxjournal_tag';
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
        $js_dir = plugin_dir_path(LYNXJOURNAL_PLUGIN_FILE) . 'assets/js/';
        $js_url = plugin_dir_url(LYNXJOURNAL_PLUGIN_FILE) . 'assets/js/';
        wp_enqueue_script($handle, $js_url . $file, $deps, (string) filemtime($js_dir . $file), true);
    }

    /**
     * Enqueue a versioned CSS asset from the assets/css/ directory.
     *
     * @since 1.0.0
     * @param string   $handle Style handle.
     * @param string   $file   Filename inside assets/css/.
     * @param string[] $deps   Style dependencies.
     * @return void
     */
    private function enqueuePageStyle(string $handle, string $file, array $deps = []): void {
        $css_dir = plugin_dir_path(LYNXJOURNAL_PLUGIN_FILE) . 'assets/css/';
        $css_url = plugin_dir_url(LYNXJOURNAL_PLUGIN_FILE) . 'assets/css/';
        wp_enqueue_style($handle, $css_url . $file, $deps, (string) filemtime($css_dir . $file));
    }

    /**
     * Add the LynxJournal dashboard widget to the WordPress dashboard.
     *
     * @since 1.0.0
     * @return void
     */
    public function addDashboardWidget(): void {
        wp_add_dashboard_widget(
            'lynxjournal_dashboard_widget',
            __('LynxJournal Summary', 'lynx-journal'),
            [$this, 'dashboardWidgetContent']
        );
    }
}
