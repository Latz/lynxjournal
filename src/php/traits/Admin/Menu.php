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
     * Render the Chrome extension settings page.
     *
     * @since 1.0.0
     * @return void
     */
    public function settingsPage(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('LynxJournal Chrome Extension', 'lynx-journal'); ?></h1>
            <div id="lynxjournal-settings-root"></div>
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
            $asset_file = plugin_dir_path(LYNXJOURNAL_PLUGIN_FILE) . 'build/settings.asset.php';
            if (file_exists($asset_file)) {
                $asset = require_once $asset_file;
            } else {
                $asset = array('dependencies' => array(), 'version' => '1.0.0');
            }

            wp_enqueue_script(
                'lynxjournal-settings-page',
                plugin_dir_url(LYNXJOURNAL_PLUGIN_FILE) . 'build/settings.js',
                $asset['dependencies'],
                $asset['version'],
                true
            );

            wp_localize_script('lynxjournal-settings-page', 'lynxjournalSettings', array(
                'restUrl' => rest_url(LYNXJOURNAL_REST_NAMESPACE),
            ));

            if (file_exists(plugin_dir_path(LYNXJOURNAL_PLUGIN_FILE) . 'build/settings.css')) {
                wp_enqueue_style(
                    'lynxjournal-settings-style',
                    plugin_dir_url(LYNXJOURNAL_PLUGIN_FILE) . 'build/settings.css',
                    array('wp-components'),
                    $asset['version']
                );
            }
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
