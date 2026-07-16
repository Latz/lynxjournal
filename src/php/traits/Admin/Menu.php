<?php

declare(strict_types=1);

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

        $schedule_hook = $this->addSubmenu(__('Schedule', 'lynx-journal'), __('Schedule', 'lynx-journal'), 'edit_posts', 'lynxjournal-schedule', 'schedulePage');
        if ($schedule_hook) {
            add_action("load-{$schedule_hook}", [$this, 'scheduleHelpTabs']);
        }
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
     * Register contextual Help tabs on the Schedule screen, one per notification channel.
     *
     * @since 1.0.0
     * @return void
     */
    public function scheduleHelpTabs(): void {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        foreach ($this->scheduleHelpTabContent() as $id => [$title, $content]) {
            $screen->add_help_tab([
                'id'      => "lynxjournal-help-{$id}",
                'title'   => $title,
                'content' => wp_kses_post($content),
            ]);
        }
    }

    /**
     * Content for each Schedule screen Help tab, keyed by tab id.
     *
     * @since 1.0.0
     * @return array<string, array{0: string, 1: string}> Map of tab id to [title, HTML content].
     */
    private function scheduleHelpTabContent(): array {
        return [
            'overview' => [
                __('Overview', 'lynx-journal'),
                '<p>' . __('The Schedule page controls when LynxJournal automatically publishes a roundup post, and (optionally) who gets notified when it does.', 'lynx-journal') . '</p>'
                . '<p>' . __('Choose a Mode (a recurring schedule like Daily/Weekly/Monthly, a trigger condition like "every N links" or "every N days", or Manual for no automatic trigger), set the Recurrence or Condition for that mode, and pick one or more Execution Times. Each notification channel below is an independent, optional add-on — you can enable any combination of them, or none at all.', 'lynx-journal') . '</p>',
            ],
            'discord' => [
                __('Discord', 'lynx-journal'),
                '<p>' . __('Posts a rich embed to a Discord channel via a webhook every time a scheduled run actually publishes (or attempts to publish) a roundup.', 'lynx-journal') . '</p>'
                . '<p><strong>' . __('Setup:', 'lynx-journal') . '</strong> ' . __('In Discord, open Server Settings → Integrations → Webhooks, click New Webhook, pick a channel, then Copy Webhook URL.', 'lynx-journal') . '</p>'
                . '<p><strong>' . __('Enable:', 'lynx-journal') . '</strong> ' . __('Check "Send a Discord notification after each run", paste the webhook URL, then Save Schedule.', 'lynx-journal') . '</p>'
                . '<p><strong>' . __('Accepted URL:', 'lynx-journal') . '</strong> ' . __('must start with https:// and point to discord.com, discordapp.com, ptb.discord.com, or canary.discord.com with a path shaped like /api/webhooks/{id}/{token}.', 'lynx-journal') . '</p>'
                . '<p>' . __('Sending is fire-and-forget: a failed or unreachable webhook is silently skipped and never blocks the scheduled run itself. If messages stop arriving, re-copy a fresh webhook URL from Discord and re-save.', 'lynx-journal') . '</p>'
                . $this->helpDocLink('discord'),
            ],
            'slack' => [
                __('Slack', 'lynx-journal'),
                '<p>' . __('Uses a single Slack Bot Token to post to a channel, DM a person, or both — independently — every time a scheduled run publishes (or attempts to publish) a roundup.', 'lynx-journal') . '</p>'
                . '<p><strong>' . __('Setup:', 'lynx-journal') . '</strong> ' . __('At api.slack.com/apps, create an app, add the chat:write Bot Token Scope under OAuth & Permissions, install the app to your workspace, and copy the Bot User OAuth Token (starts with xoxb-).', 'lynx-journal') . '</p>'
                . '<p>' . __('For a channel message: invite the bot to that channel and grab its channel ID. For a DM: get the recipient\'s Slack user ID from their profile.', 'lynx-journal') . '</p>'
                . '<p><strong>' . __('Accepted IDs:', 'lynx-journal') . '</strong> ' . __('bot token starts with xoxb-; channel ID starts with C or G; user ID starts with U. Display names like #general or @someone are not accepted.', 'lynx-journal') . '</p>'
                . '<p>' . __('Sending is fire-and-forget and never blocks the scheduled run. If messages stop arriving, confirm the bot is still in the channel and the token/IDs are current.', 'lynx-journal') . '</p>'
                . $this->helpDocLink('slack'),
            ],
            'telegram' => [
                __('Telegram', 'lynx-journal'),
                '<p>' . __('Uses a single Telegram bot token to post to a group/channel, DM you personally, or both — independently — every time a scheduled run publishes (or attempts to publish) a roundup.', 'lynx-journal') . '</p>'
                . '<p><strong>' . __('Setup:', 'lynx-journal') . '</strong> ' . __('Message @BotFather in Telegram and run /newbot to get a bot token (looks like 123456789:AAH...). To find a chat ID: message the bot then visit https://api.telegram.org/bot<TOKEN>/getUpdates and read message.chat.id, or use a helper bot like @userinfobot for your own user ID. For groups/channels, add the bot first (channels need it as admin); group/channel chat IDs are negative numbers.', 'lynx-journal') . '</p>'
                . '<p><strong>' . __('Accepted formats:', 'lynx-journal') . '</strong> ' . __('bot token is a numeric ID, a colon, and a secret; chat ID is any integer (negative for groups/channels, positive for a personal chat).', 'lynx-journal') . '</p>'
                . '<p>' . __('Sending is fire-and-forget and never blocks the scheduled run. If messages stop arriving, confirm the token is still valid and the bot is still a member of the target chat.', 'lynx-journal') . '</p>'
                . $this->helpDocLink('telegram'),
            ],
            'mastodon' => [
                __('Mastodon', 'lynx-journal'),
                '<p>' . __('Sends a visibility-restricted status (Mastodon\'s equivalent of a DM) to a recipient handle every time a scheduled run publishes (or attempts to publish) a roundup.', 'lynx-journal') . '</p>'
                . '<p><strong>' . __('Setup:', 'lynx-journal') . '</strong> ' . __('On your Mastodon instance, go to Settings → Development → New application, check only the write:statuses scope, save, then copy the Access Token and note your instance URL (e.g. https://mastodon.social).', 'lynx-journal') . '</p>'
                . '<p><strong>' . __('Accepted formats:', 'lynx-journal') . '</strong> ' . __('instance URL must start with https:// and include a host; recipient handle must include both parts, e.g. @you@mastodon.social.', 'lynx-journal') . '</p>'
                . '<p>' . __('This is not end-to-end encrypted — it\'s a regular post restricted to the people mentioned. Sending is fire-and-forget and never blocks the scheduled run. If messages stop arriving, confirm the access token hasn\'t been revoked and the recipient handle is spelled exactly right.', 'lynx-journal') . '</p>'
                . $this->helpDocLink('mastodon'),
            ],
            'bluesky' => [
                __('Bluesky', 'lynx-journal'),
                '<p>' . __('Sends a genuine private direct message via Bluesky\'s Chat API to a recipient handle every time a scheduled run publishes (or attempts to publish) a roundup.', 'lynx-journal') . '</p>'
                . '<p><strong>' . __('Setup:', 'lynx-journal') . '</strong> ' . __('In the Bluesky app, go to Settings → App Passwords → Add App Password, name it, and copy the generated password (format xxxx-xxxx-xxxx-xxxx) — you can\'t view it again after leaving that screen.', 'lynx-journal') . '</p>'
                . '<p><strong>' . __('Accepted formats:', 'lynx-journal') . '</strong> ' . __('handles are bare, e.g. you.bsky.social (no @ prefix); the app password must be exactly four groups of four characters separated by hyphens.', 'lynx-journal') . '</p>'
                . '<p>' . __('Sending is fire-and-forget and never blocks the scheduled run. If messages stop arriving, confirm the app password hasn\'t been revoked and both handles are spelled exactly right.', 'lynx-journal') . '</p>'
                . $this->helpDocLink('bluesky'),
            ],
        ];
    }

    /**
     * Build a "Detailed instructions" link to the hosted setup guide for a notification channel.
     *
     * @since 1.0.0
     * @param string $slug Channel slug, matching the path segment on elektroelch.net.
     * @return string HTML paragraph containing the link.
     */
    private function helpDocLink(string $slug): string {
        $url = esc_url("https://elektroelch.net/wordpress/lynxjournal/{$slug}/");
        return '<p><a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . __('Detailed instructions', 'lynx-journal') . '</a></p>';
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

        // The failure notice can render on any wp-admin screen, so its
        // dismiss-persistence script is enqueued globally; it no-ops if the
        // notice isn't present in the DOM. The dismiss nonce travels via the
        // notice markup's data-nonce attribute, not a localized global.
        $this->enqueuePageScript('lynxjournal-notification-failure-notice', 'notification-failure-notice.js');

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

            wp_set_script_translations('lynxjournal-schedule', 'lynx-journal', plugin_dir_path(LYNXJOURNAL_PLUGIN_FILE) . 'languages');

            wp_localize_script('lynxjournal-schedule', 'lynxjournalSchedule', array(
                'allModes'     => array_column(ScheduleMode::cases(), 'value'),
                'timeModes'    => array_column(ScheduleMode::timeBased(), 'value'),
                'triggerModes' => array_column(ScheduleMode::triggerBased(), 'value'),
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

            // Stack every help tab panel in the same grid cell so the contextual
            // Help box always sizes to the tallest tab, instead of resizing per tab.
            wp_register_style('lynxjournal-schedule-help-fix', false, array(), $asset['version']);
            wp_enqueue_style('lynxjournal-schedule-help-fix');
            wp_add_inline_style('lynxjournal-schedule-help-fix', '
                #contextual-help-wrap .contextual-help-tabs-wrap { display: grid; }
                #contextual-help-wrap .help-tab-content {
                    grid-area: 1 / 1;
                    display: block !important;
                    visibility: hidden;
                    pointer-events: none;
                }
                #contextual-help-wrap .help-tab-content.active {
                    visibility: visible;
                    pointer-events: auto;
                }
            ');
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
     * @return string|false The resulting page's hook suffix, or false on failure.
     */
    private function addSubmenu(string $page_title, string $menu_title, string $cap, string $slug, ?string $callback = null): string|false {
        return add_submenu_page('lynxjournal-dashboard', $page_title, $menu_title, $cap, $slug, $callback !== null ? [$this, $callback] : null);
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
