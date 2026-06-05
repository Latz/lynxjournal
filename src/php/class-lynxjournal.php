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
        add_action('save_post_lynxjournal',                 [$this, 'saveUrl']);
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
}
