<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main LynxJournal class — orchestrates plugin functionality.
 *
 * @since 1.0.0
 */
class LynxJournal {

    private const ADMIN_LINKS_PAGE = 'admin.php?page=lynxjournal-admin'; // NOSONAR — used in traits via self::

    public const MAX_PER_RUN           = 200;
    public const UNPUBLISHED_PAGE_SIZE = 500;
    public const RESCHEDULE_DELAY      = 60;
    public const SEARCH_HORIZON_DAYS   = 366;
    public const DEFAULT_TIME          = '09:00';

    private ?LynxJournal_Notify_Manager $notificationManager = null;

    use LynxJournal_PostType;
    use LynxJournal_MetaBoxes;
    use LynxJournal_Publishing;
    use LynxJournal_Batch;
    use LynxJournal_Queries;
    use LynxJournal_RestApi;
    use LynxJournal_ScheduleValidator;
    use LynxJournal_Scheduler;
    use LynxJournal_Admin_Menu;
    use LynxJournal_Admin_Dashboard;
    use LynxJournal_Admin_LinksPage;
    use LynxJournal_Admin_AddLink;
    use LynxJournal_Admin_Categories;

    /**
     * Register hooks and initialize plugin.
     *
     * @since 1.0.0
     * @return void
     */
    public static function register(): void {
        $instance = new self();

        // Universal hooks: run on every request (front-end, admin, REST, cron, CLI)
        add_action('init', [$instance, 'register_post_type'],    0);
        add_action('init', [$instance, 'register_taxonomies'],   0);
        add_action('init', [$instance, 'maybeRunMigration'],     5);
        add_action('init', [$instance, 'registerSchedulerHooks'], 0);
        add_action('init', [$instance, 'registerNotificationHooks'], 0);
        add_action('created_lynxjournal_category', [$instance, 'invalidateCategoriesCache']);
        add_action('edited_lynxjournal_category',  [$instance, 'invalidateCategoriesCache']);
        add_action('delete_lynxjournal_category',  [$instance, 'invalidateCategoriesCache']);

        // REST hooks: only register during REST requests
        add_action('rest_api_init', [$instance, 'register_rest_hooks'], 1);

        // Admin hooks: only register in admin context (includes wp-admin and admin-ajax.php)
        if (is_admin()) {
            $instance->register_admin_hooks();
        }
    }

    /**
     * Register admin-specific hooks.
     *
     * Only called in admin context (wp-admin pages and admin-ajax.php).
     *
     * @since 1.0.0
     * @return void
     */
    private function register_admin_hooks(): void {
        add_action('add_meta_boxes',                       [$this, 'addMetaBoxes']);
        add_action('save_post_lynx-journal',                [$this, 'saveUrl']);
        add_action('wp_ajax_lynxjournal_get_rest_nonce',    [$this, 'handleGetRestNonce']);
        add_action('admin_menu',                           [$this, 'adminMenu']);
        add_action('admin_enqueue_scripts',                [$this, 'enqueueAdminAssets']);
        add_action('wp_dashboard_setup',                   [$this, 'addDashboardWidget']);
        add_filter('parent_file',                          [$this, 'parentFileFilter']);
        add_filter('submenu_file',                         [$this, 'submenuFileFilter']);
    }

    /**
     * Register REST API hooks.
     *
     * Called via rest_api_init hook, so only fires during REST requests.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_rest_hooks(): void {
        add_action('init', [$this, 'handlePreflight'], 1);
        $this->registerRestRoutes();
        add_filter('rest_pre_serve_request', [$this, 'addCorsHeaders'], 15);
    }

    /**
     * The notification channel registry. Lazily instantiated since it's
     * only needed on requests that actually touch notifications.
     *
     * @since 1.0.0
     * @return LynxJournal_Notify_Manager
     */
    private function notifications(): LynxJournal_Notify_Manager {
        return $this->notificationManager ??= new LynxJournal_Notify_Manager();
    }

    /**
     * Register notification hooks.
     *
     * Called from the main plugin initialization.
     *
     * @since 1.0.0
     * @return void
     */
    public function registerNotificationHooks(): void {
        $this->notifications()->registerHooks();
    }

    /**
     * Send notification email after schedule runs, if enabled.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param array $link_ids Array of link post IDs that were published.
     * @param string $mode The schedule mode that ran.
     * @return void
     */
    public function maybeSendRunNotification(int|null $post_id, array $link_ids, string $mode): void {
        $this->notifications()->channel('email')->send($post_id, $link_ids, $mode, $this->currentNotify());
    }

    /**
     * Send a Discord webhook notification after schedule runs, if enabled.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param array $link_ids Array of link post IDs that were published.
     * @param string $mode The schedule mode that ran.
     * @return void
     */
    public function maybeSendDiscordNotification(int|null $post_id, array $link_ids, string $mode): void {
        $this->notifications()->channel('discord')->send($post_id, $link_ids, $mode, $this->currentNotify());
    }

    /**
     * Send a Slack channel notification after schedule runs, if enabled.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param array $link_ids Array of link post IDs that were published.
     * @param string $mode The schedule mode that ran.
     * @return void
     */
    public function maybeSendSlackChannelNotification(int|null $post_id, array $link_ids, string $mode): void {
        $this->notifications()->channel('slack_channel')->send($post_id, $link_ids, $mode, $this->currentNotify());
    }

    /**
     * Send a Slack direct message notification after schedule runs, if enabled.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param array $link_ids Array of link post IDs that were published.
     * @param string $mode The schedule mode that ran.
     * @return void
     */
    public function maybeSendSlackDmNotification(int|null $post_id, array $link_ids, string $mode): void {
        $this->notifications()->channel('slack_dm')->send($post_id, $link_ids, $mode, $this->currentNotify());
    }

    /**
     * Send a Telegram notification after schedule runs, if enabled.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param array $link_ids Array of link post IDs that were published.
     * @param string $mode The schedule mode that ran.
     * @return void
     */
    public function maybeSendTelegramNotification(int|null $post_id, array $link_ids, string $mode): void {
        $this->notifications()->channel('telegram')->send($post_id, $link_ids, $mode, $this->currentNotify());
    }

    /**
     * Send a Telegram personal DM notification after schedule runs, if enabled.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param array $link_ids Array of link post IDs that were published.
     * @param string $mode The schedule mode that ran.
     * @return void
     */
    public function maybeSendTelegramDmNotification(int|null $post_id, array $link_ids, string $mode): void {
        $this->notifications()->channel('telegram_dm')->send($post_id, $link_ids, $mode, $this->currentNotify());
    }

    /**
     * Send a Mastodon direct message after schedule runs, if enabled.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param array $link_ids Array of link post IDs that were published.
     * @param string $mode The schedule mode that ran.
     * @return void
     */
    public function maybeSendMastodonNotification(int|null $post_id, array $link_ids, string $mode): void {
        $this->notifications()->channel('mastodon')->send($post_id, $link_ids, $mode, $this->currentNotify());
    }

    /**
     * Send a Bluesky direct message after schedule runs, if enabled.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param array $link_ids Array of link post IDs that were published.
     * @param string $mode The schedule mode that ran.
     * @return void
     */
    public function maybeSendBlueskyNotification(int|null $post_id, array $link_ids, string $mode): void {
        $this->notifications()->channel('bluesky')->send($post_id, $link_ids, $mode, $this->currentNotify());
    }

    /**
     * Read the currently stored `notify` settings.
     *
     * @since 1.0.0
     * @return array The `notify` option array.
     */
    private function currentNotify(): array {
        $config = get_option('lynxjournal_schedule', []);
        return is_array($config) && isset($config['notify']) && is_array($config['notify']) ? $config['notify'] : [];
    }

    /**
     * Send a one-off test notification for a single channel, using ad-hoc
     * (possibly unsaved) notify settings rather than the stored option.
     *
     * @since 1.0.0
     * @param string $channel One of email|discord|slack_channel|slack_dm|telegram|telegram_dm|mastodon|bluesky.
     * @param array $notify Notify settings to test with (already sanitized by validateNotifyChannel()).
     * @return true|\WP_Error True on success, WP_Error describing why the test couldn't be sent.
     */
    public function dispatchTestNotification(string $channel, array $notify): true|\WP_Error {
        return $this->notifications()->test($channel, $notify);
    }

    /**
     * Validate and sanitize the full `notify` settings block.
     *
     * @since 1.0.0
     * @param array $data The request data containing a 'notify' key, modified in place.
     * @return \WP_Error|null Error if invalid, null if valid.
     */
    private function validateNotify(array &$data): ?\WP_Error {
        if (!isset($data['notify']) || !is_array($data['notify'])) {
            return isset($data['notify']) ? new \WP_Error('invalid_notify', __('notify must be an object', 'lynx-journal'), ['status' => 400]) : null;
        }
        return $this->notifications()->validateAll($data['notify']);
    }

    /**
     * Validate and sanitize only the notification fields for one channel.
     *
     * Used by the single-channel test-notification endpoint so that stray
     * or unfinished input in an unrelated channel's fields doesn't block
     * testing the channel currently being configured.
     *
     * @since 1.0.0
     * @param string $channel One of email|discord|slack_channel|slack_dm|telegram|telegram_dm|mastodon|bluesky.
     * @param array $data The request data containing a 'notify' key.
     * @return \WP_Error|null Error if invalid, null if valid.
     */
    public function validateNotifyChannel(string $channel, array &$data): ?\WP_Error {
        if (!isset($data['notify']) || !is_array($data['notify'])) {
            return isset($data['notify']) ? new \WP_Error('invalid_notify', __('notify must be an object', 'lynx-journal'), ['status' => 400]) : null;
        }
        return $this->notifications()->validateChannel($channel, $data['notify']);
    }

    /**
     * The notify field names that belong to one notification channel.
     *
     * @since 1.0.0
     * @param string $channel One of email|discord|slack_channel|slack_dm|telegram|telegram_dm|mastodon|bluesky.
     * @return string[] Field names within notify.
     */
    private function notifyChannelFields(string $channel): array {
        return $this->notifications()->channelFields($channel);
    }

    /**
     * Send a one-off test notification for a single channel via REST.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The REST request with channel and notify settings.
     * @return \WP_REST_Response|\WP_Error Response or error.
     */
    public function restTestNotification(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $channel = (string) $request->get_param('channel');
        if (!in_array($channel, $this->notifications()->knownChannelKeys(), true)) {
            return new \WP_Error('invalid_channel', __('Unknown notification channel.', 'lynx-journal'), array('status' => 400));
        }

        $data = array('notify' => $request->get_param('notify'));
        $validated = $this->validateNotifyChannel($channel, $data);
        if (is_wp_error($validated)) {
            return $validated;
        }

        $result = $this->dispatchTestNotification($channel, $data['notify']);
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response(array('success' => true));
    }

    /**
     * Persist just one notification channel's fields into the stored
     * schedule config, leaving everything else (other channels, mode,
     * times, trigger, etc.) untouched.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The REST request with channel and notify settings.
     * @return \WP_REST_Response|\WP_Error Response with the saved fields, or error.
     */
    public function restSaveNotification(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $channel = (string) $request->get_param('channel');
        if (!in_array($channel, $this->notifications()->knownChannelKeys(), true)) {
            return new \WP_Error('invalid_channel', __('Unknown notification channel.', 'lynx-journal'), array('status' => 400));
        }

        $data = array('notify' => $request->get_param('notify'));
        $validated = $this->validateNotifyChannel($channel, $data);
        if (is_wp_error($validated)) {
            return $validated;
        }

        $fields = $this->notifyChannelFields($channel);
        $config = get_option('lynxjournal_schedule', array());
        if (!is_array($config)) {
            $config = array();
        }
        if (!isset($config['notify']) || !is_array($config['notify'])) {
            $config['notify'] = array();
        }
        foreach ($fields as $field) {
            $config['notify'][$field] = $data['notify'][$field];
        }
        update_option('lynxjournal_schedule', $config);

        return rest_ensure_response(array(
            'success' => true,
            'notify'  => array_intersect_key($data['notify'], array_flip($fields)),
        ));
    }
}
