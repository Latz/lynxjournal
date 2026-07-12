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
        $this->addSubmenu(__('Categories & Tags', 'lynx-journal'), __('Categories & Tags', 'lynx-journal'), 'edit_posts', 'lynxjournal-categories',                                   'categoriesPage');
        $this->addSubmenu(__('Chrome Extension', 'lynx-journal'), __('Chrome Extension', 'lynx-journal'), 'edit_posts', 'lynxjournal-settings',                                     'settingsPage');
        $this->addSubmenu(__('Schedule',         'lynx-journal'), __('Schedule',         'lynx-journal'), 'edit_posts', 'lynxjournal-schedule',                                     'schedulePage');
        $this->addSubmenu(__('Post Template',    'lynx-journal'), __('Post Template',    'lynx-journal'), 'edit_posts', 'lynxjournal-template',                                     'templatePage');
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
            $this->enqueueDashboardAssets();
        }

        if (strpos($hook, 'lynxjournal-settings') !== false) {
            $this->enqueueSettingsPageAssets();
        }

        if (strpos($hook, 'lynxjournal-admin') !== false) {
            $this->enqueuePageScript('lynxjournal-links-page', 'links-page.js');
        }

        if (strpos($hook, 'lynxjournal-categories') !== false) {
            $this->enqueueTaxonomiesAssets();
        }

        if (strpos($hook, 'lynxjournal-template') !== false) {
            $this->enqueueTemplatePageAssets();
        }

        if (strpos($hook, 'lynxjournal-schedule') !== false) {
            $this->enqueueSchedulePageAssets();
        }
    }

    /**
     * Enqueue dashboard page scripts and localized data.
     *
     * @since 1.0.0
     * @return void
     */
    private function enqueueDashboardAssets(): void {
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

    /**
     * Enqueue Chrome extension settings page scripts and styles.
     *
     * @since 1.0.0
     * @return void
     */
    private function enqueueSettingsPageAssets(): void {
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

        $this->enqueuePageScriptTranslations('lynxjournal-settings-page');

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

    /**
     * Enqueue the combined Categories & Tags page scripts and localized data.
     *
     * @since 1.0.0
     * @return void
     */
    private function enqueueTaxonomiesAssets(): void {
        $asset_file = plugin_dir_path(LYNXJOURNAL_PLUGIN_FILE) . 'build/taxonomies.asset.php';
        if (file_exists($asset_file)) {
            $asset = require_once $asset_file;
        } else {
            $asset = array('dependencies' => array(), 'version' => '1.0.0');
        }

        wp_enqueue_script(
            'lynxjournal-taxonomies',
            plugin_dir_url(LYNXJOURNAL_PLUGIN_FILE) . 'build/taxonomies.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        $this->enqueuePageScriptTranslations('lynxjournal-taxonomies');

        if (file_exists(plugin_dir_path(LYNXJOURNAL_PLUGIN_FILE) . 'build/taxonomies.css')) {
            wp_enqueue_style(
                'lynxjournal-taxonomies-style',
                plugin_dir_url(LYNXJOURNAL_PLUGIN_FILE) . 'build/taxonomies.css',
                array('wp-components'),
                $asset['version']
            );
        }
    }

    /**
     * Enqueue post template page scripts, styles, and localized data.
     *
     * @since 1.0.0
     * @return void
     */
    private function enqueueTemplatePageAssets(): void {
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
        wp_localize_script('lynxjournal-template-js', 'lynxjournalThemePreview', $this->getThemePreviewAssets());
    }

    /**
     * Collects the active theme's stylesheet URLs and global styles so the
     * Post Template live preview can render inside an iframe styled like a
     * real post, instead of the plugin's own generic preview CSS.
     *
     * @since 1.0.0
     * @return array{stylesheets: string[], globalStyles: string, contentClass: string}
     */
    private function getThemePreviewAssets(): array {
        $stylesheets = [get_stylesheet_uri()];
        if (get_template_directory() !== get_stylesheet_directory()) {
            array_unshift($stylesheets, get_template_directory_uri() . '/style.css');
        }
        if (current_theme_supports('editor-styles')) {
            $stylesheets = array_merge($stylesheets, get_editor_stylesheets());
        }

        $global_styles = wp_get_global_stylesheet();

        return [
            'stylesheets'  => array_values(array_unique($stylesheets)),
            'globalStyles' => $global_styles,
            'contentClass' => 'entry-content',
        ];
    }

    /**
     * Enqueue schedule page scripts, styles, and localized data.
     *
     * @since 1.0.0
     * @return void
     */
    private function enqueueSchedulePageAssets(): void {
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

        $this->enqueuePageScriptTranslations('lynxjournal-schedule');

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
     * Register the lynx-journal translation catalog for a React-driven script handle.
     *
     * @since 1.0.0
     * @param string $handle Script handle.
     * @return void
     */
    private function enqueuePageScriptTranslations(string $handle): void {
        wp_set_script_translations($handle, 'lynx-journal', plugin_dir_path(LYNXJOURNAL_PLUGIN_FILE) . 'languages');
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
